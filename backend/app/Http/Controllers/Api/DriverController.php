<?php

namespace App\Http\Controllers\Api;

use App\Events\DriverLocationUpdated;
use App\Events\DriverOnlineStatusChanged;
use App\Events\SessionReplaced;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLocationRequest;
use App\Models\Location;
use App\Models\Ride;
use App\Services\CreditService;
use App\Services\LocationService;
use App\Services\MatchingQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function __construct(
        private LocationService $locationService,
        private MatchingQueueService $matchingQueueService,
        private CreditService $creditService,
    ) {}

    /**
     * Go online (mark driver as available to accept ride requests)
     */
    public function goOnline(Request $request): JsonResponse
    {
        $driver = $request->user();

        if (! $driver->tokenCan('driver:go-online')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Driver permissions required',
            ], 403);
        }

        if (! $driver->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been suspended. Please contact admin.',
            ], 403);
        }

        $driverProfile = $driver->driverProfile;
        if (! $driverProfile || ! $driverProfile->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your student email before going online',
            ], 403);
        }
        if (! $driverProfile->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'Your KYC is pending admin approval. Please wait for review.',
            ], 403);
        }

        $activeRide = $driver->driverRides()
            ->whereIn('status', Ride::ACTIVE_STATUSES)
            ->exists();

        if ($activeRide) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active ride',
            ], 400);
        }

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

        $activeRiderRide = $driver->riderRides()
            ->whereIn('status', ['matched', 'accepted', 'driver_arrived', 'in_progress'])
            ->exists();

        if ($activeRiderRide) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot go online as a driver while you have an active ride as a rider. Please complete or cancel it first.',
            ], 400);
        }

        // Enforce single active session: broadcast a displacement event on the
        // driver channel BEFORE revoking tokens so any old device still connected
        // can receive and react to the event, then revoke the stale tokens.
        $currentTokenId = $driver->currentAccessToken()->id;
        $hasOtherSessions = $driver->tokens()
            ->where('id', '!=', $currentTokenId)
            ->exists();

        if ($hasOtherSessions) {
            broadcast(new SessionReplaced($driver->id));

            // Give the broadcast a moment to flush, then revoke the old tokens.
            $driver->tokens()
                ->where('id', '!=', $currentTokenId)
                ->delete();

            \Log::info('Driver session replaced: revoked old tokens', [
                'driver_id' => $driver->id,
            ]);
        }

        if (! $this->creditService->canGoOnline($driver->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You need at least 1 credit to go online. Contact admin to top up.',
            ], 402);
        }

        if ($request->has('current_latitude') && $request->has('current_longitude')) {
            $this->locationService->updateDriverLocation(
                $driver->id,
                $request->current_latitude,
                $request->current_longitude
            );
        }

        $driverProfile->update(['went_online_at' => now()]);

        // Add driver to the FIFO matching queue
        $this->matchingQueueService->addToQueue($driver->id);
        $queuePosition = $this->matchingQueueService->getQueuePosition($driver->id);

        broadcast(new DriverOnlineStatusChanged($driver, true, null));

        \Log::info('Driver went online', [
            'driver_id' => $driver->id,
            'email' => $driver->email,
            'queue_position' => $queuePosition,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Successfully went online. You can now accept ride requests.',
            'data' => [
                'status' => 'online',
                'is_available' => true,
                'driver_id' => $driver->id,
                'queue_position' => $queuePosition,
            ],
        ]);
    }

    /**
     * Go offline (mark driver as unavailable)
     */
    public function goOffline(Request $request): JsonResponse
    {
        $driver = $request->user();

        if (! $driver->tokenCan('driver:go-online')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Driver permissions required',
            ], 403);
        }

        $activeRide = $driver->driverRides()
            ->whereIn('status', Ride::ACTIVE_STATUSES)
            ->exists();

        if ($activeRide) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot go offline while you have an active ride',
            ], 400);
        }

        $driverProfile = $driver->driverProfile;
        if ($driverProfile) {
            // Remove from FIFO matching queue before going offline
            $this->matchingQueueService->removeFromQueue($driver->id);
            $driverProfile->update(['went_online_at' => null]);
        }

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
     * Get driver's current position in the global FIFO matching queue
     */
    public function getQueuePosition(Request $request): JsonResponse
    {
        $driver = $request->user();

        if (! $driver->tokenCan('driver:go-online')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Driver permissions required',
            ], 403);
        }

        $profile = $driver->driverProfile;
        $position = $this->matchingQueueService->getQueuePosition($driver->id);

        return response()->json([
            'success' => true,
            'data' => [
                'in_queue' => $position > 0,
                'queue_position' => $position,
                'is_in_cooldown' => $profile?->isInCooldown() ?? false,
                'cooldown_until' => $profile?->queue_cooldown_until?->toISOString(),
                'max_pickup_radius_km' => (float) ($profile?->max_pickup_radius_km ?? 5.0),
            ],
        ]);
    }

    /**
     * Update driver settings (max pickup radius, etc.)
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $driver = $request->user();

        if (! $driver->tokenCan('driver:go-online')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Driver permissions required',
            ], 403);
        }

        $validated = $request->validate([
            'max_pickup_radius_km' => 'required|numeric|min:0.5|max:20',
        ]);

        $driverProfile = $driver->driverProfile;
        if (! $driverProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }

        $driverProfile->update([
            'max_pickup_radius_km' => $validated['max_pickup_radius_km'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
            'data' => [
                'max_pickup_radius_km' => (float) $driverProfile->max_pickup_radius_km,
            ],
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

        $activeRide = $driver->driverRides()
            ->whereIn('status', Ride::ACTIVE_STATUSES)
            ->latest()
            ->first();

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
     * Get driver statistics and performance
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $driver = $request->user();

        if (! $driver->tokenCan('driver:go-online')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Driver permissions required',
            ], 403);
        }

        $driverProfile = $driver->driverProfile;
        if (! $driverProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }

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
            'rating' => (float) ($driverProfile->rating_average ?? 0.0),
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
            'queue_info' => [
                'in_queue' => $driverProfile->isInQueue(),
                'queue_position' => $this->matchingQueueService->getQueuePosition($driver->id),
                'is_in_cooldown' => $driverProfile->isInCooldown(),
                'cooldown_until' => $driverProfile->queue_cooldown_until?->toISOString(),
                'max_pickup_radius_km' => (float) ($driverProfile->max_pickup_radius_km ?? 5.0),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
