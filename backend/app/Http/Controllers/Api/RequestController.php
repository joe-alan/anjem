<?php

namespace App\Http\Controllers\Api;

use App\Events\RideRequestCancelled;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateRideRequestRequest;
use App\Http\Resources\RideRequestResource;
use App\Models\RideRequest;
use App\Services\MatchingQueueService;
use App\Services\NotificationService;
use App\Services\RideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RequestController extends Controller
{
    public function __construct(
        private RideService $rideService,
        private NotificationService $notificationService,
        private MatchingQueueService $matchingQueueService,
    ) {}

    /**
     * Get ride requests for the authenticated rider
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->tokenCan('rider:request-ride')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Rider permissions required',
            ], 403);
        }

        $query = RideRequest::with(['pickupLocation', 'destinationLocation'])
            ->where('rider_id', $user->id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if (! $request->has('include_expired')) {
            $query->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
        }

        $requests = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => RideRequestResource::collection($requests),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * Create a new ride request
     */
    public function store(CreateRideRequestRequest $request): JsonResponse
    {
        $rider = $request->user();

        // Rider cannot request while online as a driver
        if ($rider->driverProfile && $rider->driverProfile->went_online_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot request a ride while you are online as a driver. Please go offline first.',
            ], 400);
        }

        // Check rider cooldown (enforced after cancel/expire)
        $cooldownUntil = RideRequest::where('rider_id', $rider->id)
            ->where('rider_cooldown_until', '>', now())
            ->orderBy('rider_cooldown_until', 'desc')
            ->value('rider_cooldown_until');

        if ($cooldownUntil) {
            return response()->json([
                'success' => false,
                'message' => 'Please wait before making another ride request.',
                'data' => [
                    'cooldown_until' => $cooldownUntil->toISOString(),
                    'seconds_remaining' => max(0, now()->diffInSeconds($cooldownUntil, false) * -1),
                ],
            ], 429);
        }

        // Check if rider already has an active ride request
        $activeRequest = RideRequest::where('rider_id', $rider->id)
            ->whereIn('status', ['pending', 'matched'])
            ->where('expires_at', '>', now())
            ->first();

        if ($activeRequest) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active ride request',
                'data' => new RideRequestResource($activeRequest),
            ], 400);
        }

        $validatedData = $request->validated();

        $rideRequest = $this->rideService->createRideRequest([
            'rider_id' => $rider->id,
            'pickup_location_id' => $validatedData['pickup_location_id'],
            'destination_location_id' => $validatedData['destination_location_id'],
            'passenger_count' => $validatedData['passenger_count'],
            'special_requests' => $validatedData['special_requests'] ?? null,
        ]);

        if (! $rideRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to create ride request',
            ], 500);
        }

        $rideRequest->load(['pickupLocation', 'destinationLocation', 'rider']);

        // Queue-based dispatch: find the top eligible driver and send only to them
        $driver = $this->matchingQueueService->findTopDriver($rideRequest);

        if ($driver) {
            $this->matchingQueueService->dispatchToDriver($rideRequest, $driver);

            Log::info('Ride request dispatched to top queue driver', [
                'ride_request_id' => $rideRequest->id,
                'driver_id' => $driver->user_id,
            ]);
        } else {
            Log::info('No eligible drivers in queue for new ride request', [
                'ride_request_id' => $rideRequest->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ride request created successfully',
            'data' => new RideRequestResource($rideRequest),
        ], 201);
    }

    /**
     * Show a specific ride request
     */
    public function show(Request $request, RideRequest $ride_request): JsonResponse
    {
        $user = $request->user();

        if ($ride_request->rider_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this ride request',
            ], 403);
        }

        $ride_request->load(['pickupLocation', 'destinationLocation']);

        return response()->json([
            'success' => true,
            'data' => new RideRequestResource($ride_request),
        ]);
    }

    /**
     * Cancel a ride request
     */
    public function cancel(Request $request, RideRequest $ride_request): JsonResponse
    {
        $user = $request->user();

        if ($ride_request->rider_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to cancel this ride request',
            ], 403);
        }

        if (! $user->tokenCan('rider:cancel-ride')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Cancel ride permissions required',
            ], 403);
        }

        if (! in_array($ride_request->status, ['pending', 'matched'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel ride request in current status',
                'current_status' => $ride_request->status,
            ], 400);
        }

        // Capture before the service clears it
        $previousStatus = $ride_request->status;
        $assignedDriverId = $ride_request->current_driver_id;

        $success = $this->rideService->cancelRideRequest($ride_request->id, $user->id);

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to cancel ride request',
            ], 500);
        }

        // Apply rider cooldown after cancellation
        $this->matchingQueueService->applyRiderCooldown($ride_request);

        // Notify the assigned driver (pending dispatch) via WebSocket so their screen dismisses
        if ($assignedDriverId) {
            broadcast(new RideRequestCancelled($ride_request, 'rider'));
        }

        // If matched, also notify driver via push notification
        if ($previousStatus === 'matched') {
            $ride = $ride_request->ride;
            if ($ride && $ride->driver) {
                $this->notificationService->sendRideCancelledNotification(
                    $ride,
                    $user->id,
                    'Cancelled by rider'
                );
            }
        }

        $ride_request->refresh();
        $ride_request->load(['pickupLocation', 'destinationLocation']);

        return response()->json([
            'success' => true,
            'message' => 'Ride request cancelled successfully',
            'data' => new RideRequestResource($ride_request),
            'meta' => [
                'cooldown_until' => $ride_request->rider_cooldown_until?->toISOString(),
            ],
        ]);
    }

    /**
     * Get ride request estimates for planning
     */
    public function getEstimates(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->tokenCan('rider:request-ride')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Rider permissions required',
            ], 403);
        }

        $request->validate([
            'pickup_beacon_id' => 'required_without:pickup_location_id|integer|exists:locations,id',
            'pickup_location_id' => 'required_without:pickup_beacon_id|integer|exists:locations,id',
            'destination_beacon_id' => 'required_without:destination_location_id|integer|exists:locations,id|different:pickup_beacon_id,pickup_location_id',
            'destination_location_id' => 'required_without:destination_beacon_id|integer|exists:locations,id|different:pickup_beacon_id,pickup_location_id',
            'passenger_count' => 'required|integer|min:1|max:4',
        ]);

        $pickupId = $request->input('pickup_beacon_id') ?? $request->input('pickup_location_id');
        $destinationId = $request->input('destination_beacon_id') ?? $request->input('destination_location_id');

        $estimates = $this->rideService->getRideEstimates(
            $pickupId,
            $destinationId,
            $request->passenger_count
        );

        if (! $estimates) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to calculate estimates',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $estimates,
        ]);
    }
}
