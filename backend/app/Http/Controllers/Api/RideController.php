<?php

namespace App\Http\Controllers\Api;

use App\Events\RideRequestMatched;
use App\Events\RideStatusUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\RateRideRequest;
use App\Http\Requests\UpdateRideStatusRequest;
use App\Http\Resources\RideResource;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Services\NotificationService;
use App\Services\RideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RideController extends Controller
{
    public function __construct(
        private RideService $rideService,
        private NotificationService $notificationService
    ) {}

    /**
     * Get rides for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Ride::with(['rider', 'driver', 'pickupLocation', 'destinationLocation']);

        // Filter by user role
        if ($user->user_type === 'rider') {
            $query->where('rider_id', $user->id);
        } elseif ($user->user_type === 'driver') {
            $query->where('driver_id', $user->id);
        } else {
            // For 'both' user type, show all rides for this user
            $query->where(function ($q) use ($user) {
                $q->where('rider_id', $user->id)
                    ->orWhere('driver_id', $user->id);
            });
        }

        // Optional status filter
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $rides = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => RideResource::collection($rides),
            'meta' => [
                'current_page' => $rides->currentPage(),
                'last_page' => $rides->lastPage(),
                'per_page' => $rides->perPage(),
                'total' => $rides->total(),
            ],
        ]);
    }

    /**
     * Show a specific ride
     */
    public function show(Request $request, Ride $ride): JsonResponse
    {
        $user = $request->user();

        // Check if user is authorized to view this ride
        if ($ride->rider_id !== $user->id && $ride->driver_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this ride',
            ], 403);
        }

        $ride->load(['rider', 'driver', 'pickupLocation', 'destinationLocation', 'ratings']);

        return response()->json([
            'success' => true,
            'data' => new RideResource($ride),
        ]);
    }

    /**
     * Accept a ride request (driver only)
     */
    public function accept(Request $request, RideRequest $rideRequest): JsonResponse
    {
        $driver = $request->user();

        // Validate driver permissions
        if (! $driver->tokenCan('driver:accept-ride')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Driver permissions required',
            ], 403);
        }

        try {
            $ride = $this->rideService->acceptRideRequest($rideRequest->id, $driver->id);

            $ride->load(['rider', 'driver', 'pickupLocation', 'destinationLocation']);

            // Broadcast ride request matched event
            broadcast(new RideRequestMatched($rideRequest, $ride));

            // Broadcast ride status updated event
            broadcast(new RideStatusUpdated($ride, 'pending', 'driver'));

            // Send notification to rider
            $this->notificationService->sendRideAcceptedToRider($ride);

            return response()->json([
                'success' => true,
                'message' => 'Ride accepted successfully',
                'data' => new RideResource($ride),
            ]);

        } catch (\Exception $e) {
            // ✅ FIX: Map exception codes to HTTP status codes
            $statusCode = match ($e->getCode()) {
                409 => 409,  // Conflict (already accepted by another driver)
                404 => 404,  // Not found (expired/cancelled)
                403 => 403,  // Forbidden (invalid driver credentials)
                400 => 400,  // Bad request (driver has active ride)
                default => 500,  // Internal server error
            };

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Update ride status
     */
    public function updateStatus(UpdateRideStatusRequest $request, Ride $ride): JsonResponse
    {
        $user = $request->user();
        $status = $request->status;

        // Check if user is authorized to update this ride
        if ($ride->driver_id !== $user->id && $ride->rider_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this ride',
            ], 403);
        }

        $success = false;
        $message = '';
        $previousStatus = $ride->status;

        switch ($status) {
            case 'driver_arrived':
                if ($ride->driver_id === $user->id && $user->tokenCan('driver:accept-ride')) {
                    $success = $this->rideService->markDriverArrived($ride->id, $user->id);
                    $message = $success ? 'Marked as arrived at pickup' : 'Unable to update status';

                    if ($success) {
                        $ride->refresh();
                        broadcast(new RideStatusUpdated($ride, $previousStatus, 'driver'));
                        $this->notificationService->sendDriverArrivedToRider($ride);
                    }
                }
                break;

            case 'in_progress':
                if ($ride->driver_id === $user->id && $user->tokenCan('driver:accept-ride')) {
                    $success = $this->rideService->startRide($ride->id, $user->id);
                    $message = $success ? 'Ride started successfully' : 'Unable to start ride';

                    if ($success) {
                        $ride->refresh();
                        broadcast(new RideStatusUpdated($ride, $previousStatus, 'driver'));
                        $this->notificationService->sendRideStartedToRider($ride);
                    }
                }
                break;

            case 'completed':
                if ($ride->driver_id === $user->id && $user->tokenCan('driver:complete-ride')) {
                    $completionData = $request->only([
                        'actual_distance_km',
                        'actual_duration_minutes',
                        'actual_fare_rp',
                        'driver_notes',
                    ]);

                    $success = $this->rideService->completeRide($ride->id, $user->id, $completionData);
                    $message = $success ? 'Ride completed successfully' : 'Unable to complete ride';

                    if ($success) {
                        $ride->refresh();
                        broadcast(new RideStatusUpdated($ride, $previousStatus, 'driver'));
                        $this->notificationService->sendRideCompletedNotifications($ride);
                    }
                }
                break;

            case 'cancelled':
                $cancelReason = $request->cancel_reason;
                $success = $this->rideService->cancelRide($ride->id, $user->id, $cancelReason);
                $message = $success ? 'Ride cancelled successfully' : 'Unable to cancel ride';

                if ($success) {
                    $ride->refresh();
                    $updatedBy = $ride->rider_id === $user->id ? 'rider' : 'driver';
                    broadcast(new RideStatusUpdated($ride, $previousStatus, $updatedBy));
                    $this->notificationService->sendRideCancelledNotification($ride, $user->id, $cancelReason);
                }
                break;

            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid status update',
                ], 400);
        }

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 400);
        }

        $ride->refresh();
        $ride->load(['rider', 'driver', 'pickupLocation', 'destinationLocation']);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => new RideResource($ride),
        ]);
    }

    /**
     * Rate a ride
     */
    public function rate(RateRideRequest $request, Ride $ride): JsonResponse
    {
        $user = $request->user();

        // Check if user can rate this ride
        if ($ride->rider_id !== $user->id && $ride->driver_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to rate this ride',
            ], 403);
        }

        // Check if ride is completed
        if ($ride->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Can only rate completed rides',
            ], 400);
        }

        // Determine who is being rated
        $ratedUserId = $ride->rider_id === $user->id ? $ride->driver_id : $ride->rider_id;

        // ✅ Check if user already rated this ride
        $existingRating = $ride->ratings()
            ->where('rater_id', $user->id)
            ->where('rated_id', $ratedUserId)  // ✅ Match database column name
            ->exists();

        if ($existingRating) {
            return response()->json([
                'success' => false,
                'message' => 'You have already rated this ride',
            ], 400);
        }

        // ✅ Pass feedback (matches database column and mobile app)
        $rating = $this->rideService->rateRide(
            $ride->id,
            $user->id,
            $ratedUserId,
            $request->rating,
            $request->feedback,  // ✅ Changed from 'comment' to 'feedback'
            $request->tags ?? []
        );

        if (! $rating) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to submit rating',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rating submitted successfully',
            'data' => [
                'rating_id' => $rating->id,
                'rating' => $rating->rating,
                'comment' => $rating->comment,
                'tags' => $rating->tags,
            ],
        ]);
    }
}
