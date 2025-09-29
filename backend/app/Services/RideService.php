<?php

namespace App\Services;

use App\Models\DriverQueue;
use App\Models\Location;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RideService handles the core ride-sharing business logic
 *
 * Manages the complete ride lifecycle from request creation to completion,
 * including driver-rider matching algorithms and fare calculations.
 */
class RideService
{
    private LocationService $locationService;
    private QueueService $queueService;

    public function __construct(LocationService $locationService, QueueService $queueService)
    {
        $this->locationService = $locationService;
        $this->queueService = $queueService;
    }

    /**
     * Create a new ride request
     */
    public function createRideRequest(array $requestData): ?RideRequest
    {
        try {
            DB::beginTransaction();

            // Validate rider
            $rider = User::find($requestData['rider_id']);
            if (!$rider || !$rider->is_active) {
                throw new \Exception('Invalid or inactive rider');
            }

            // Find or create pickup location
            $pickupLocation = $this->resolvePickupLocation($requestData);
            if (!$pickupLocation) {
                throw new \Exception('Invalid pickup location');
            }

            // Find or create destination location
            $destinationLocation = $this->resolveDestinationLocation($requestData);
            if (!$destinationLocation) {
                throw new \Exception('Invalid destination location');
            }

            // Calculate distance and fare estimates
            $estimates = $this->calculateRideEstimates(
                $pickupLocation->coordinates->latitude,
                $pickupLocation->coordinates->longitude,
                $destinationLocation->coordinates->latitude,
                $destinationLocation->coordinates->longitude,
                $requestData['passenger_count'] ?? 1
            );

            // Create ride request
            $rideRequest = RideRequest::create([
                'rider_id' => $requestData['rider_id'],
                'pickup_location_id' => $pickupLocation->id,
                'destination_location_id' => $destinationLocation->id,
                'estimated_distance_km' => $estimates['distance_km'],
                'estimated_duration_minutes' => $estimates['duration_minutes'],
                'estimated_fare_rp' => $estimates['fare_rp'],
                'passenger_count' => $requestData['passenger_count'] ?? 1,
                'special_requests' => $requestData['special_requests'] ?? null,
                'status' => 'pending',
                'expires_at' => now()->addMinutes(30), // 30-minute expiration
            ]);

            // Cache for quick matching
            $this->cacheActiveRequest($rideRequest);

            DB::commit();

            Log::info('Ride request created', [
                'ride_request_id' => $rideRequest->id,
                'rider_id' => $requestData['rider_id'],
                'pickup_location_id' => $pickupLocation->id,
                'destination_location_id' => $destinationLocation->id,
                'estimated_fare_rp' => $estimates['fare_rp']
            ]);

            return $rideRequest;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create ride request', [
                'error' => $e->getMessage(),
                'request_data' => $requestData
            ]);

            return null;
        }
    }

    /**
     * Find and match a driver to a ride request
     */
    public function matchDriver(int $rideRequestId): ?Ride
    {
        try {
            DB::beginTransaction();

            $rideRequest = RideRequest::with(['pickupLocation', 'destinationLocation', 'rider'])
                ->find($rideRequestId);

            if (!$rideRequest || !$rideRequest->isActive()) {
                return null;
            }

            // Find the best driver for this request
            $driver = $this->findBestDriver($rideRequest);
            if (!$driver) {
                return null;
            }

            // Create the ride
            $ride = Ride::create([
                'ride_request_id' => $rideRequest->id,
                'rider_id' => $rideRequest->rider_id,
                'driver_id' => $driver->id,
                'pickup_location_id' => $rideRequest->pickup_location_id,
                'destination_location_id' => $rideRequest->destination_location_id,
                'status' => 'matched',
                'passenger_count' => $rideRequest->passenger_count,
                'estimated_fare_rp' => $rideRequest->estimated_fare_rp,
                'special_requests' => $rideRequest->special_requests,
            ]);

            // Update ride request status
            $rideRequest->markAsMatched();

            // Mark driver as served (remove from queue)
            $this->queueService->markDriverServed($driver->id);

            // Remove from active requests cache
            $this->removeActiveRequestCache($rideRequest->id);

            DB::commit();

            Log::info('Driver matched to ride', [
                'ride_id' => $ride->id,
                'ride_request_id' => $rideRequest->id,
                'driver_id' => $driver->id,
                'rider_id' => $rideRequest->rider_id
            ]);

            return $ride;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to match driver', [
                'ride_request_id' => $rideRequestId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Accept a ride by driver
     */
    public function acceptRide(int $rideId, int $driverId): bool
    {
        try {
            $ride = Ride::find($rideId);

            if (!$ride || $ride->driver_id !== $driverId || $ride->status !== 'matched') {
                return false;
            }

            $ride->markAsAccepted();

            Log::info('Ride accepted by driver', [
                'ride_id' => $rideId,
                'driver_id' => $driverId
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to accept ride', [
                'ride_id' => $rideId,
                'driver_id' => $driverId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Start a ride (driver picked up rider)
     */
    public function startRide(int $rideId, int $driverId): bool
    {
        try {
            $ride = Ride::find($rideId);

            if (!$ride || $ride->driver_id !== $driverId || $ride->status !== 'accepted') {
                return false;
            }

            $ride->startRide();

            Log::info('Ride started', [
                'ride_id' => $rideId,
                'driver_id' => $driverId
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to start ride', [
                'ride_id' => $rideId,
                'driver_id' => $driverId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Complete a ride
     */
    public function completeRide(int $rideId, int $driverId, ?array $completionData = null): bool
    {
        try {
            $ride = Ride::find($rideId);

            if (!$ride || $ride->driver_id !== $driverId || $ride->status !== 'in_progress') {
                return false;
            }

            $actualDistance = $completionData['actual_distance_km'] ?? null;
            $actualFare = $completionData['actual_fare_rp'] ?? null;

            // If no actual fare provided, use estimated fare
            if ($actualFare === null) {
                $actualFare = $ride->estimated_fare_rp;
            }

            $ride->completeRide($actualDistance, $actualFare);

            Log::info('Ride completed', [
                'ride_id' => $rideId,
                'driver_id' => $driverId,
                'actual_fare_rp' => $actualFare
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to complete ride', [
                'ride_id' => $rideId,
                'driver_id' => $driverId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Cancel a ride request by rider
     */
    public function cancelRideRequest(int $rideRequestId, int $riderId): bool
    {
        try {
            $rideRequest = RideRequest::find($rideRequestId);

            if (!$rideRequest || $rideRequest->rider_id !== $riderId) {
                return false;
            }

            $rideRequest->markAsCancelled();
            $this->removeActiveRequestCache($rideRequestId);

            Log::info('Ride request cancelled by rider', [
                'ride_request_id' => $rideRequestId,
                'rider_id' => $riderId
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to cancel ride request', [
                'ride_request_id' => $rideRequestId,
                'rider_id' => $riderId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Cancel an active ride
     */
    public function cancelRide(int $rideId, int $userId, string $reason = null): bool
    {
        try {
            $ride = Ride::find($rideId);

            if (!$ride || !in_array($userId, [$ride->rider_id, $ride->driver_id])) {
                return false;
            }

            if (!$ride->isActive()) {
                return false; // Can't cancel completed rides
            }

            $ride->cancelRide($reason);

            Log::info('Ride cancelled', [
                'ride_id' => $rideId,
                'cancelled_by_user_id' => $userId,
                'reason' => $reason
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to cancel ride', [
                'ride_id' => $rideId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get active ride for a user (rider or driver)
     */
    public function getActiveRide(int $userId): ?Ride
    {
        return Ride::where(function ($query) use ($userId) {
            $query->where('rider_id', $userId)
                  ->orWhere('driver_id', $userId);
        })
        ->whereIn('status', ['matched', 'accepted', 'in_progress'])
        ->with(['rider', 'driver', 'pickupLocation', 'destinationLocation'])
        ->first();
    }

    /**
     * Get ride history for a user
     */
    public function getRideHistory(int $userId, int $limit = 20): Collection
    {
        return Ride::where(function ($query) use ($userId) {
            $query->where('rider_id', $userId)
                  ->orWhere('driver_id', $userId);
        })
        ->whereIn('status', ['completed', 'cancelled'])
        ->with(['rider', 'driver', 'pickupLocation', 'destinationLocation'])
        ->orderBy('created_at', 'desc')
        ->limit($limit)
        ->get();
    }

    /**
     * Calculate ride estimates (distance, duration, fare)
     */
    public function calculateRideEstimates(float $pickupLat, float $pickupLng, float $destLat, float $destLng, int $passengerCount = 1): array
    {
        // Get driving details
        $drivingDetails = $this->locationService->getDrivingDetails($pickupLat, $pickupLng, $destLat, $destLng);

        $distanceKm = $drivingDetails['distance_meters'] / 1000;
        $durationMinutes = $drivingDetails['duration_minutes'];

        // Calculate fare using campus-specific pricing
        $fareRp = $this->calculateFare($distanceKm, $durationMinutes, $passengerCount);

        return [
            'distance_km' => round($distanceKm, 2),
            'duration_minutes' => $durationMinutes,
            'fare_rp' => $fareRp,
            'estimated' => $drivingDetails['estimated'] ?? true,
        ];
    }

    /**
     * Campus-specific fare calculation
     */
    private function calculateFare(float $distanceKm, int $durationMinutes, int $passengerCount = 1): int
    {
        // Campus fare structure:
        // - Base fare: Rp 3,000
        // - Distance: Rp 2,000 per km
        // - Time: Rp 500 per minute (for long waits in traffic)
        // - Passenger multiplier: +20% per additional passenger

        $baseFare = 3000;
        $distanceFare = $distanceKm * 2000;
        $timeFare = max(0, ($durationMinutes - 5)) * 500; // Free first 5 minutes

        $totalFare = $baseFare + $distanceFare + $timeFare;

        // Passenger multiplier
        if ($passengerCount > 1) {
            $multiplier = 1 + (($passengerCount - 1) * 0.2);
            $totalFare *= $multiplier;
        }

        // Minimum fare: Rp 5,000
        // Maximum fare: Rp 50,000 (for campus rides)
        return max(5000, min(50000, (int) round($totalFare)));
    }

    /**
     * Find the best available driver for a ride request
     */
    private function findBestDriver(RideRequest $rideRequest): ?User
    {
        if (!$rideRequest->pickupLocation->isBeacon()) {
            return null; // For MVP, only support beacon pickups
        }

        // Get the next driver in queue at the pickup beacon
        $queueEntry = $this->queueService->getNextDriverAtBeacon($rideRequest->pickup_location_id);

        if (!$queueEntry) {
            return null; // No drivers available
        }

        return $queueEntry->driver;
    }

    /**
     * Resolve pickup location from request data
     */
    private function resolvePickupLocation(array $requestData): ?Location
    {
        if (isset($requestData['pickup_location_id'])) {
            return Location::find($requestData['pickup_location_id']);
        }

        if (isset($requestData['pickup_latitude'], $requestData['pickup_longitude'])) {
            // Find the closest beacon for pickup
            return $this->locationService->getClosestBeacon(
                $requestData['pickup_latitude'],
                $requestData['pickup_longitude']
            );
        }

        return null;
    }

    /**
     * Resolve destination location from request data
     */
    private function resolveDestinationLocation(array $requestData): ?Location
    {
        if (isset($requestData['destination_location_id'])) {
            return Location::find($requestData['destination_location_id']);
        }

        if (isset($requestData['destination_latitude'], $requestData['destination_longitude'])) {
            // Create or find P2P destination
            return $this->locationService->findOrCreateDestination(
                $requestData['destination_name'] ?? 'Custom Destination',
                $requestData['destination_address'] ?? 'User-provided location',
                $requestData['destination_latitude'],
                $requestData['destination_longitude']
            );
        }

        return null;
    }

    /**
     * Cache active ride request for quick matching
     */
    private function cacheActiveRequest(RideRequest $rideRequest): void
    {
        $cacheKey = "active_request:{$rideRequest->pickup_location_id}:{$rideRequest->id}";
        $data = [
            'id' => $rideRequest->id,
            'rider_id' => $rideRequest->rider_id,
            'priority' => $rideRequest->calculatePriority(),
            'created_at' => $rideRequest->created_at->toISOString(),
            'expires_at' => $rideRequest->expires_at->toISOString(),
        ];

        Cache::put($cacheKey, $data, 1800); // 30 minutes
    }

    /**
     * Remove ride request from cache
     */
    private function removeActiveRequestCache(int $rideRequestId): void
    {
        // In a production system, we'd maintain a reverse index
        // For MVP, we rely on TTL expiration
        $cacheKey = "active_request:*:{$rideRequestId}";
        // Cache::forget($cacheKey); // Would need wildcard support
    }

    /**
     * Clean up expired ride requests
     */
    public function cleanupExpiredRequests(): int
    {
        try {
            $expiredCount = RideRequest::pending()
                ->where('expires_at', '<=', now())
                ->update(['status' => 'expired']);

            Log::info('Expired ride requests cleaned up', ['count' => $expiredCount]);

            return $expiredCount;

        } catch (\Exception $e) {
            Log::error('Failed to cleanup expired requests', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Get ride statistics for analytics
     */
    public function getRideStatistics(): array
    {
        $today = now()->startOfDay();

        return [
            'today' => [
                'completed_rides' => Ride::completed()->where('created_at', '>=', $today)->count(),
                'cancelled_rides' => Ride::cancelled()->where('created_at', '>=', $today)->count(),
                'active_rides' => Ride::whereIn('status', ['matched', 'accepted', 'in_progress'])->count(),
                'pending_requests' => RideRequest::pending()->notExpired()->count(),
            ],
            'all_time' => [
                'total_completed' => Ride::completed()->count(),
                'total_cancelled' => Ride::cancelled()->count(),
                'average_fare' => Ride::completed()->avg('actual_fare_rp') ?? 0,
                'average_distance' => Ride::completed()->avg('actual_distance_km') ?? 0,
            ],
        ];
    }
}