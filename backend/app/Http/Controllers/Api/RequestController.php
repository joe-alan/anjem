<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateRideRequestRequest;
use App\Http\Resources\RideRequestResource;
use App\Models\RideRequest;
use App\Services\NotificationService;
use App\Services\RideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    public function __construct(
        private RideService $rideService,
        private NotificationService $notificationService
    ) {}

    /**
     * Get ride requests for the authenticated rider
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only riders can view their ride requests
        if (!$user->tokenCan('rider:request-ride')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Rider permissions required',
            ], 403);
        }

        $query = RideRequest::with(['pickupLocation', 'destinationLocation'])
            ->where('rider_id', $user->id);

        // Optional status filter
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Show only active requests by default
        if (!$request->has('include_expired')) {
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

        // Check if rider has any active ride requests
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

        $rideRequest = $this->rideService->createRideRequest([
            'rider_id' => $rider->id,
            'pickup_location_id' => $request->pickup_location_id,
            'destination_location_id' => $request->destination_location_id,
            'passenger_count' => $request->passenger_count,
            'special_requests' => $request->special_requests,
        ]);

        if (!$rideRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to create ride request',
            ], 500);
        }

        $rideRequest->load(['pickupLocation', 'destinationLocation']);

        return response()->json([
            'success' => true,
            'message' => 'Ride request created successfully',
            'data' => new RideRequestResource($rideRequest),
        ], 201);
    }

    /**
     * Show a specific ride request
     */
    public function show(Request $request, RideRequest $rideRequest): JsonResponse
    {
        $user = $request->user();

        // Check if user is authorized to view this request
        if ($rideRequest->rider_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this ride request',
            ], 403);
        }

        $rideRequest->load(['pickupLocation', 'destinationLocation']);

        return response()->json([
            'success' => true,
            'data' => new RideRequestResource($rideRequest),
        ]);
    }

    /**
     * Cancel a ride request
     */
    public function cancel(Request $request, RideRequest $rideRequest): JsonResponse
    {
        $user = $request->user();

        // Check if user is authorized to cancel this request
        if ($rideRequest->rider_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to cancel this ride request',
            ], 403);
        }

        // Check token permissions
        if (!$user->tokenCan('rider:cancel-ride')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Cancel ride permissions required',
            ], 403);
        }

        // Check if request can be cancelled
        if (!in_array($rideRequest->status, ['pending', 'matched'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel ride request in current status',
                'current_status' => $rideRequest->status,
            ], 400);
        }

        $success = $this->rideService->cancelRideRequest($rideRequest->id, $user->id);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to cancel ride request',
            ], 500);
        }

        // If the request was matched, notify the driver
        if ($rideRequest->status === 'matched') {
            $ride = $rideRequest->rides()->first();
            if ($ride && $ride->driver) {
                $this->notificationService->sendRideCancelledNotification(
                    $ride,
                    $user->id,
                    'Cancelled by rider'
                );
            }
        }

        $rideRequest->refresh();
        $rideRequest->load(['pickupLocation', 'destinationLocation']);

        return response()->json([
            'success' => true,
            'message' => 'Ride request cancelled successfully',
            'data' => new RideRequestResource($rideRequest),
        ]);
    }

    /**
     * Get ride request estimates for planning
     */
    public function getEstimates(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check rider permissions
        if (!$user->tokenCan('rider:request-ride')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Rider permissions required',
            ], 403);
        }

        $request->validate([
            'pickup_location_id' => 'required|integer|exists:locations,id',
            'destination_location_id' => 'required|integer|exists:locations,id|different:pickup_location_id',
            'passenger_count' => 'required|integer|min:1|max:4',
        ]);

        $estimates = $this->rideService->getRideEstimates(
            $request->pickup_location_id,
            $request->destination_location_id,
            $request->passenger_count
        );

        if (!$estimates) {
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
