<?php

namespace App\Http\Controllers\Api;

use App\Events\DriverLocationUpdated;
use App\Events\DriverOnlineStatusChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLocationRequest;
use App\Models\Location;
use App\Models\Ride;
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
     * Go online (mark driver as available to accept ride requests)
     */
    public function goOnline(Request $request): JsonResponse
    {
        $driver = $request->user();

        // Check driver permissions
        if (! $driver->tokenCan('driver:go-online')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Driver permissions required',
            ], 403);
        }

        // Check if driver is verified (has completed KYC)
        $driverProfile = $driver->driverProfile;
        if (! $driverProfile || ! $driverProfile->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'Please complete driver verification (KYC) before going online',
            ], 403);
        }

        // Check if driver already has an active ride
        $activeRide = $driver->driverRides()
            ->whereIn('status', Ride::ACTIVE_STATUSES)
            ->exists();

        if ($activeRide) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active ride',
            ], 400);
        }

        // Check if user has pending ride requests as rider
        $activeRiderRequest = $driver->rideRequests()
            ->whereIn('status', ['pending', 'matched'])
            ->where('expires_at', '>', now())
            ->exists();

        if ($activeRiderRequest) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot go online as a driver while you have an active ride request. Please cancel it first.',
            ], 400);
        }

        // Check if user has active rides as rider
        $activeRiderRide = $driver->riderRides()
            ->whereIn('status', ['matched', 'accepted', 'driver_arrived', 'in_progress'])
            ->exists();

        if ($activeRiderRide) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot go online as a driver while you have an active ride as a rider. Please complete or cancel it first.',
            ], 400);
        }

        // Update driver location if provided
        if ($request->has('current_latitude') && $request->has('current_longitude')) {
            $this->locationService->updateDriverLocation(
                $driver->id,
                $request->current_latitude,
                $request->current_longitude
            );
        }

        // Mark driver as online (set went_online_at timestamp)
        $driverProfile->update(['went_online_at' => now()]);

        // Broadcast driver online status changed
        broadcast(new DriverOnlineStatusChanged($driver, true, null));

        \Log::info('Driver went online', [
            'driver_id' => $driver->id,
            'email' => $driver->email,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Successfully went online. You can now accept ride requests.',
            'data' => [
                'status' => 'online',
                'is_available' => true,
                'driver_id' => $driver->id,
            ],
        ]);
    }

    /**
     * Go offline (mark driver as unavailable)
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

        // Check if driver has an active ride
        $activeRide = $driver->driverRides()
            ->whereIn('status', Ride::ACTIVE_STATUSES)
            ->exists();

        if ($activeRide) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot go offline while you have an active ride',
            ], 400);
        }

        // Mark driver as offline (clear went_online_at timestamp)
        $driverProfile = $driver->driverProfile;
        if ($driverProfile) {
            $driverProfile->update(['went_online_at' => null]);
        }

        // Broadcast driver offline status changed
        broadcast(new DriverOnlineStatusChanged($driver, false, null));

        \Log::info('Driver went offline', [
            'driver_id' => $driver->id,
            'email' => $driver->email,
        ]);

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
            ->whereIn('status', Ride::ACTIVE_STATUSES)
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

        // Calculate today's stats from completed rides
        $todayRides = $driver->driverRides()
            ->whereDate('dropoff_time', today())
            ->where('status', 'completed')
            ->count();

        $todayEarnings = $driver->driverRides()
            ->whereDate('dropoff_time', today())
            ->where('status', 'completed')
            ->sum('actual_fare_rp');

        $stats = [
            'total_rides' => $driverProfile->total_rides_given ?? 0,
            'rating' => (float) ($driverProfile->driver_rating_avg ?? 0.0),
            'today_rides' => $todayRides,
            'today_earnings' => (float) ($todayEarnings ?? 0.0),
            'is_verified' => $driverProfile->is_verified,
            'is_available' => $driverProfile->isAvailable(),
            'vehicle_info' => [
                'type' => $driverProfile->vehicle_type,
                'model' => $driverProfile->vehicle_model,
                'year' => $driverProfile->vehicle_year,
                'plate_number' => $driverProfile->vehicle_plate,
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
