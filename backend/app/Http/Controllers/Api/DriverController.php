<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GoOnlineRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Http\Resources\DriverQueueResource;
use App\Services\LocationService;
use App\Services\QueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function __construct(
        private QueueService $queueService,
        private LocationService $locationService
    ) {}

    /**
     * Go online and join a beacon queue
     */
    public function goOnline(GoOnlineRequest $request): JsonResponse
    {
        $driver = $request->user();

        // Check if driver is already in a queue
        $existingQueue = $this->queueService->getDriverQueueStatus($driver->id);
        if ($existingQueue['in_queue']) {
            return response()->json([
                'success' => false,
                'message' => 'Already in queue',
                'data' => $existingQueue,
            ], 400);
        }

        // Check if driver can join the beacon
        $canJoin = $this->queueService->canDriverJoinBeacon($driver->id, $request->beacon_id);
        if (!$canJoin['can_join']) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot join beacon queue',
                'reasons' => $canJoin['reasons'],
            ], 400);
        }

        // Update driver location
        $this->locationService->updateDriverLocation(
            $driver->id,
            $request->current_latitude,
            $request->current_longitude
        );

        // Join the queue
        $queueEntry = $this->queueService->joinQueue($driver->id, $request->beacon_id);

        if (!$queueEntry) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to join queue',
            ], 500);
        }

        $queueEntry->load(['beacon']);

        return response()->json([
            'success' => true,
            'message' => 'Successfully joined queue',
            'data' => new DriverQueueResource($queueEntry),
        ]);
    }

    /**
     * Go offline and leave current queue
     */
    public function goOffline(Request $request): JsonResponse
    {
        $driver = $request->user();

        // Check driver permissions
        if (!$driver->tokenCan('driver:go-online')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Driver permissions required',
            ], 403);
        }

        $success = $this->queueService->leaveQueue($driver->id);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Not currently in queue or failed to leave',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Successfully went offline',
        ]);
    }

    /**
     * Get current queue status for driver
     */
    public function getQueue(Request $request): JsonResponse
    {
        $driver = $request->user();

        // Check driver permissions
        if (!$driver->tokenCan('driver:go-online')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Driver permissions required',
            ], 403);
        }

        $queueStatus = $this->queueService->getDriverQueueStatus($driver->id);

        return response()->json([
            'success' => true,
            'data' => $queueStatus,
        ]);
    }

    /**
     * Update driver's current location
     */
    public function updateLocation(UpdateLocationRequest $request): JsonResponse
    {
        $driver = $request->user();

        $success = $this->locationService->updateDriverLocation(
            $driver->id,
            $request->latitude,
            $request->longitude,
            $request->heading,
            $request->speed
        );

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update location',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Location updated successfully',
            'data' => [
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'heading' => $request->heading,
                'speed' => $request->speed,
                'updated_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Get available beacons for joining queue
     */
    public function getAvailableBeacons(Request $request): JsonResponse
    {
        $driver = $request->user();

        // Check driver permissions
        if (!$driver->tokenCan('driver:go-online')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Driver permissions required',
            ], 403);
        }

        $beacons = $this->queueService->getAllBeaconStatistics();

        return response()->json([
            'success' => true,
            'data' => $beacons,
        ]);
    }

    /**
     * Get driver statistics and performance
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $driver = $request->user();

        // Check driver permissions
        if (!$driver->tokenCan('driver:go-online')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Driver permissions required',
            ], 403);
        }

        // Get basic driver stats from driver profile
        $driverProfile = $driver->driverProfile;
        if (!$driverProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }

        $stats = [
            'total_rides' => $driverProfile->total_rides,
            'rating' => $driverProfile->rating,
            'is_verified' => $driverProfile->is_verified,
            'is_available' => $driverProfile->is_available,
            'vehicle_info' => [
                'type' => $driverProfile->vehicle_type,
                'model' => $driverProfile->vehicle_model,
                'year' => $driverProfile->vehicle_year,
                'plate_number' => $driverProfile->plate_number,
            ],
        ];

        // Add queue status if currently in queue
        $queueStatus = $this->queueService->getDriverQueueStatus($driver->id);
        if ($queueStatus['in_queue']) {
            $stats['current_queue'] = $queueStatus;
        }

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
