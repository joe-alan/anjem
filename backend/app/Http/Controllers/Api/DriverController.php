<?php

namespace App\Http\Controllers\Api;

use App\Events\DriverLocationUpdated;
use App\Events\DriverOnlineStatusChanged;
use App\Events\QueuePositionChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\GoOnlineRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Http\Resources\DriverQueueResource;
use App\Models\Location;
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
        if (! $canJoin['can_join']) {
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

        if (! $queueEntry) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to join queue',
            ], 500);
        }

        $queueEntry->load(['beacon']);
        $beacon = Location::find($request->beacon_id);

        // Get updated queue status for broadcasting
        $queueStatus = $this->queueService->getDriverQueueStatus($driver->id);

        // Broadcast driver online status changed
        broadcast(new DriverOnlineStatusChanged(
            $driver,
            true,
            $beacon,
            $queueStatus['position'] ?? 1
        ));

        // Broadcast queue position changed
        broadcast(new QueuePositionChanged(
            $driver,
            $beacon,
            $queueStatus['position'] ?? 1,
            $queueStatus['total_drivers'] ?? 1,
            $queueStatus['estimated_wait_minutes'] ?? 10,
            'joined'
        ));

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
        if (! $driver->tokenCan('driver:go-online')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Driver permissions required',
            ], 403);
        }

        // Get queue status before leaving for broadcasting
        $queueStatus = $this->queueService->getDriverQueueStatus($driver->id);
        $beacon = null;
        if ($queueStatus['in_queue'] && $queueStatus['beacon_id']) {
            $beacon = Location::find($queueStatus['beacon_id']);
        }

        $success = $this->queueService->leaveQueue($driver->id);

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Not currently in queue or failed to leave',
            ], 400);
        }

        // Broadcast driver offline status changed
        broadcast(new DriverOnlineStatusChanged($driver, false, $beacon));

        // Broadcast queue position changed for other drivers if was in queue
        if ($beacon) {
            broadcast(new QueuePositionChanged(
                $driver,
                $beacon,
                0, // position 0 means left queue
                $queueStatus['total_drivers'] - 1,
                $queueStatus['estimated_wait_minutes'] ?? 0,
                'left'
            ));
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
        if (! $driver->tokenCan('driver:go-online')) {
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

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update location',
            ], 500);
        }

        // Check if driver has an active ride for real-time tracking
        $activeRide = $driver->driverRides()
            ->whereIn('status', ['accepted', 'in_progress'])
            ->latest()
            ->first();

        // Broadcast location update if driver is online or has active ride
        if ($driver->isDriverOnline() || $activeRide) {
            broadcast(new DriverLocationUpdated(
                $driver,
                $request->latitude,
                $request->longitude,
                $activeRide?->id,
                $request->heading,
                $request->speed
            ));
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
                'broadcast_sent' => $driver->isDriverOnline() || $activeRide !== null,
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
        if (! $driver->tokenCan('driver:go-online')) {
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
        if (! $driver->tokenCan('driver:go-online')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Driver permissions required',
            ], 403);
        }

        // Get basic driver stats from driver profile
        $driverProfile = $driver->driverProfile;
        if (! $driverProfile) {
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
