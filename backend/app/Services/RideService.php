<?php

namespace App\Services;

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

    private CreditService $creditService;

    public function __construct(LocationService $locationService, QueueService $queueService, CreditService $creditService)
    {
        $this->locationService = $locationService;
        $this->queueService = $queueService;
        $this->creditService = $creditService;
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
            if (! $rider || ! $rider->is_active) {
                throw new \Exception('Invalid or inactive rider');
            }

            // Find or create pickup location
            $pickupLocation = $this->resolvePickupLocation($requestData);
            if (! $pickupLocation) {
                throw new \Exception('Invalid pickup location');
            }

            // Find or create destination location
            $destinationLocation = $this->resolveDestinationLocation($requestData);
            if (! $destinationLocation) {
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
                'estimated_fare_rp' => $estimates['fare_rp'],
            ]);

            return $rideRequest;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create ride request', [
                'error' => $e->getMessage(),
                'request_data' => $requestData,
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

            if (! $rideRequest || ! $rideRequest->isActive()) {
                return null;
            }

            // Find the best driver for this request
            $driver = $this->findBestDriver($rideRequest);
            if (! $driver) {
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
                'rider_id' => $rideRequest->rider_id,
            ]);

            return $ride;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to match driver', [
                'ride_request_id' => $rideRequestId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Accept a ride request by driver (standard ride-sharing model)
     * Any online driver can accept any pending request
     */
    public function acceptRideRequest(int $rideRequestId, int $driverId): ?Ride
    {
        try {
            DB::beginTransaction();

            // ✅ FIX: Add row locking with status filter to prevent race condition
            $rideRequest = RideRequest::lockForUpdate()
                ->where('id', $rideRequestId)
                ->where('status', 'pending')  // Only lock if pending
                ->with(['pickupLocation', 'destinationLocation', 'rider'])
                ->first();

            // ✅ FIX: Throw specific exceptions instead of returning null
            if (! $rideRequest) {
                // Secondary lookup (no lock) to give a meaningful error.
                // Runs inside the transaction — READ COMMITTED lets us see other
                // transactions' commits, so the status is accurate.
                $actualStatus = RideRequest::where('id', $rideRequestId)->value('status');
                if (in_array($actualStatus, ['cancelled', 'expired'])) {
                    throw new \Exception('This ride request was cancelled by the rider', 410);
                }
                throw new \Exception('This ride request has already been accepted by another driver', 409);
            }

            if (! $rideRequest->isActive()) {
                throw new \Exception('This ride request is no longer available', 404);
            }

            // Only the currently-dispatched driver may accept.
            // current_driver_id is null when no driver has been assigned yet (edge-case
            // on very fast acceptance before the first dispatch completes).
            if ($rideRequest->current_driver_id !== null && $rideRequest->current_driver_id !== $driverId) {
                throw new \Exception('This ride request is assigned to another driver', 403);
            }

            // Verify driver is valid and online
            $driver = User::with('driverProfile')->find($driverId);
            if (
                ! $driver ||
                ! $driver->is_active ||
                ! in_array($driver->role, ['driver', 'both', 'admin'])
            ) {
                throw new \Exception('Invalid driver credentials', 403);
            }

            // Check if driver already has an active ride
            $existingRide = $this->getActiveRide($driverId);
            if ($existingRide) {
                throw new \Exception('You already have an active ride. Complete it before accepting another.', 400);
            }

            // Check if driver has active rides as rider
            $activeRiderRide = $driver->riderRides()
                ->whereIn('status', ['matched', 'accepted', 'driver_arrived', 'in_progress'])
                ->exists();

            if ($activeRiderRide) {
                throw new \Exception('You cannot accept a ride while you have an active ride as a rider.', 400);
            }

            // Create the ride
            $ride = Ride::create([
                'ride_request_id' => $rideRequest->id,
                'rider_id' => $rideRequest->rider_id,
                'driver_id' => $driver->id,
                'pickup_location_id' => $rideRequest->pickup_location_id,
                'destination_location_id' => $rideRequest->destination_location_id,
                'status' => 'accepted',  // Direct to accepted status
                'passenger_count' => $rideRequest->passenger_count,
                'estimated_fare_rp' => $rideRequest->estimated_fare_rp,
                'special_requests' => $rideRequest->special_requests,
            ]);

            // Deduct one credit after ride is created so ride_id FK is satisfied
            $this->creditService->deductCredit($driverId, $ride->id);

            $ride->markAsAccepted();

            // Update ride request status
            $rideRequest->markAsMatched();

            // Remove from active requests cache
            $this->removeActiveRequestCache($rideRequest->id);

            DB::commit();

            Log::info('Ride request accepted by driver', [
                'ride_id' => $ride->id,
                'ride_request_id' => $rideRequestId,
                'driver_id' => $driverId,
                'rider_id' => $rideRequest->rider_id,
            ]);

            return $ride;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to accept ride request', [
                'ride_request_id' => $rideRequestId,
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            // ✅ FIX: Re-throw exception for controller to handle
            throw $e;
        }
    }

    /**
     * Accept a ride by driver
     */
    public function acceptRide(int $rideId, int $driverId): bool
    {
        try {
            $ride = Ride::find($rideId);

            if (! $ride || $ride->driver_id !== $driverId || $ride->status !== 'matched') {
                return false;
            }

            $ride->markAsAccepted();

            Log::info('Ride accepted by driver', [
                'ride_id' => $rideId,
                'driver_id' => $driverId,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to accept ride', [
                'ride_id' => $rideId,
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Mark driver as arrived at pickup location
     */
    public function markDriverArrived(int $rideId, int $driverId): bool
    {
        try {
            $ride = Ride::find($rideId);

            if (! $ride || $ride->driver_id !== $driverId || $ride->status !== 'accepted') {
                return false;
            }

            $ride->status = 'driver_arrived';
            $ride->arrived_at = now();
            $ride->save();

            Log::info('Driver marked as arrived', [
                'ride_id' => $rideId,
                'driver_id' => $driverId,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to mark driver as arrived', [
                'ride_id' => $rideId,
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
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

            if (! $ride || $ride->driver_id !== $driverId) {
                return false;
            }

            // Allow starting from either 'accepted' or 'driver_arrived' status
            if (! in_array($ride->status, ['accepted', 'driver_arrived'])) {
                return false;
            }

            $ride->startRide();

            // Keep the parent ride request in sync so riders can resume their session
            if ($ride->rideRequest) {
                $ride->rideRequest->markAsInProgress();
            }

            Log::info('Ride started', [
                'ride_id' => $rideId,
                'driver_id' => $driverId,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to start ride', [
                'ride_id' => $rideId,
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
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

            if (! $ride || $ride->driver_id !== $driverId || $ride->status !== 'in_progress') {
                return false;
            }

            $actualDistance = $completionData['actual_distance_km'] ?? null;
            $actualFare = $completionData['actual_fare_rp'] ?? null;

            // If no actual fare provided, use estimated fare
            if ($actualFare === null) {
                $actualFare = $ride->estimated_fare_rp;
            }

            $ride->completeRide($actualDistance, $actualFare);

            if ($ride->rideRequest) {
                $ride->rideRequest->markAsCompleted();
            }

            $this->removeActiveRequestCache($ride->ride_request_id);

            Log::info('Ride completed', [
                'ride_id' => $rideId,
                'driver_id' => $driverId,
                'actual_fare_rp' => $actualFare,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to complete ride', [
                'ride_id' => $rideId,
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
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

            if (! $rideRequest || $rideRequest->rider_id !== $riderId) {
                return false;
            }

            $rideRequest->markAsCancelled();
            $this->removeActiveRequestCache($rideRequestId);

            Log::info('Ride request cancelled by rider', [
                'ride_request_id' => $rideRequestId,
                'rider_id' => $riderId,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to cancel ride request', [
                'ride_request_id' => $rideRequestId,
                'rider_id' => $riderId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Cancel an active ride
     */
    public function cancelRide(int $rideId, int $userId, ?string $reason = null): bool
    {
        try {
            $ride = Ride::find($rideId);

            if (! $ride || ! in_array($userId, [$ride->rider_id, $ride->driver_id])) {
                return false;
            }

            if (! $ride->isActive()) {
                return false; // Can't cancel completed rides
            }

            $ride->cancelRide($reason);

            if ($ride->rideRequest) {
                $ride->rideRequest->markAsCancelled();
            }

            $this->removeActiveRequestCache($ride->ride_request_id);

            Log::info('Ride cancelled', [
                'ride_id' => $rideId,
                'cancelled_by_user_id' => $userId,
                'reason' => $reason,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to cancel ride', [
                'ride_id' => $rideId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
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
            ->whereIn('status', Ride::ACTIVE_STATUSES)
            ->with(['rider', 'driver', 'pickupLocation', 'destinationLocation', 'rideRequest'])
            ->latest('updated_at')
            ->first();
    }

    /**
     * Get an active ride request for a rider
     */
    public function getActiveRideRequest(int $riderId): ?RideRequest
    {
        return RideRequest::where('rider_id', $riderId)
            ->whereIn('status', ['pending', 'matched', 'in_progress'])
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->with(['pickupLocation', 'destinationLocation'])
            ->latest('updated_at')
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
     * Get ride estimates by location IDs
     */
    public function getRideEstimates(int $pickupLocationId, int $destinationLocationId, int $passengerCount = 1): ?array
    {
        try {
            $pickupLocation = Location::find($pickupLocationId);
            $destinationLocation = Location::find($destinationLocationId);

            if (! $pickupLocation || ! $destinationLocation) {
                return null;
            }

            // Pass location IDs to enable route caching
            return $this->calculateRideEstimates(
                $pickupLocation->coordinates->latitude,
                $pickupLocation->coordinates->longitude,
                $destinationLocation->coordinates->latitude,
                $destinationLocation->coordinates->longitude,
                $passengerCount,
                $pickupLocationId,          // NEW: Enable caching
                $destinationLocationId       // NEW: Enable caching
            );
        } catch (\Exception $e) {
            Log::error('Failed to get ride estimates', [
                'pickup_location_id' => $pickupLocationId,
                'destination_location_id' => $destinationLocationId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Calculate ride estimates (distance, duration, fare)
     * Returns mobile-friendly field names with route geometry
     *
     * @param  float  $pickupLat  Pickup latitude
     * @param  float  $pickupLng  Pickup longitude
     * @param  float  $destLat  Destination latitude
     * @param  float  $destLng  Destination longitude
     * @param  int  $passengerCount  Number of passengers
     * @param  int|null  $pickupLocationId  Optional pickup location ID for caching
     * @param  int|null  $destLocationId  Optional destination location ID for caching
     * @return array Ride estimates with fare breakdown and route geometry
     */
    public function calculateRideEstimates(
        float $pickupLat,
        float $pickupLng,
        float $destLat,
        float $destLng,
        int $passengerCount = 1,
        ?int $pickupLocationId = null,
        ?int $destLocationId = null
    ): array {
        // Get driving details with route geometry (uses caching if IDs provided)
        $drivingDetails = $this->locationService->getDrivingDetails(
            $pickupLat,
            $pickupLng,
            $destLat,
            $destLng,
            $pickupLocationId,
            $destLocationId
        );

        $distanceKm = $drivingDetails['distance_meters'] / 1000;
        $durationMinutes = $drivingDetails['duration_minutes'];

        // Calculate fare breakdown using campus-specific pricing
        $fareBreakdown = $this->calculateFareBreakdown($distanceKm, $durationMinutes, $passengerCount);

        // Return mobile-friendly field names (camelCase)
        return [
            'baseFare' => $fareBreakdown['base_fare'],
            'distanceFare' => $fareBreakdown['distance_fare'],
            'totalFare' => $fareBreakdown['total_fare'],
            'estimatedDistance' => round($distanceKm, 2),
            'estimatedDuration' => $durationMinutes,
            'currency' => 'IDR',
            'routeGeometry' => $drivingDetails['geometry'] ?? null, // GeoJSON for map display

            // Keep old field names for backward compatibility (internal use)
            'distance_km' => round($distanceKm, 2),
            'duration_minutes' => $durationMinutes,
            'fare_rp' => $fareBreakdown['total_fare'],
            'estimated' => $drivingDetails['estimated'] ?? true,
        ];
    }

    /**
     * Campus-specific fare calculation with breakdown
     */
    private function calculateFareBreakdown(float $distanceKm, int $durationMinutes, int $passengerCount = 1): array
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
        $finalFare = max(5000, min(50000, (int) round($totalFare)));

        return [
            'base_fare' => (int) $baseFare,
            'distance_fare' => (int) round($distanceFare + $timeFare),
            'total_fare' => $finalFare,
        ];
    }

    /**
     * Campus-specific fare calculation (legacy method for backward compatibility)
     */
    private function calculateFare(float $distanceKm, int $durationMinutes, int $passengerCount = 1): int
    {
        $breakdown = $this->calculateFareBreakdown($distanceKm, $durationMinutes, $passengerCount);

        return $breakdown['total_fare'];
    }

    /**
     * Find the best available driver for a ride request (proximity-based)
     * NOTE: For standard model, drivers accept requests directly
     * This method is kept for potential auto-matching features
     */
    private function findBestDriver(RideRequest $rideRequest): ?User
    {
        // Get online drivers near the pickup location
        $pickupLat = $rideRequest->pickupLocation->coordinates->latitude;
        $pickupLng = $rideRequest->pickupLocation->coordinates->longitude;

        // Find drivers within 5km radius
        $nearbyDrivers = User::whereIn('role', ['driver', 'both'])
            ->where('is_active', true)
            ->whereHas('driverProfile', function ($query) use ($pickupLat, $pickupLng) {
                $query->whereNotNull('current_location')
                    ->whereRaw('ST_DWithin(current_location::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [
                        $pickupLng,
                        $pickupLat,
                        5000, // 5km radius
                    ]);
            })
            ->get();

        // Filter out drivers with active rides
        foreach ($nearbyDrivers as $driver) {
            $activeRide = $this->getActiveRide($driver->id);
            if (! $activeRide) {
                return $driver; // Return first available driver
            }
        }

        return null; // No drivers available
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

    /**
     * Rate a ride (rider rates driver or driver rates rider)
     */
    public function rateRide(
        int $rideId,
        int $raterId,
        int $ratedUserId,
        int $rating,
        ?string $feedback = null,  // ✅ Renamed from $comment to match database and API
        array $tags = []
    ): ?\App\Models\Rating {
        try {
            $ride = Ride::find($rideId);

            if (! $ride || $ride->status !== 'completed') {
                return null;
            }

            // Verify the rater is part of this ride
            if (! in_array($raterId, [$ride->rider_id, $ride->driver_id])) {
                return null;
            }

            // ✅ Determine the rating_type based on who is being rated
            // Database constraint expects: 'rider_to_driver' or 'driver_to_rider'
            $ratingType = $ratedUserId === $ride->driver_id ? 'rider_to_driver' : 'driver_to_rider';

            // ✅ Create the rating with correct database column names
            $ratingRecord = \App\Models\Rating::create([
                'ride_id' => $rideId,
                'rater_id' => $raterId,
                'rated_id' => $ratedUserId,     // ✅ Match database column name
                'rating_type' => $ratingType,   // ✅ Match database column name and constraint
                'score' => $rating,             // ✅ Match database column name
                'feedback' => $feedback,        // ✅ Match database column name
                'tags' => $tags,
            ]);

            // Note: Driver rating average is updated automatically by Rating model listener
            // when rating_type === 'rider_to_driver' via the updateCachedRating() method

            Log::info('Ride rated', [
                'ride_id' => $rideId,
                'rater_id' => $raterId,
                'rated_id' => $ratedUserId,
                'rating_type' => $ratingType,
                'score' => $rating,
            ]);

            return $ratingRecord;

        } catch (\Exception $e) {
            Log::error('Failed to rate ride', [
                'ride_id' => $rideId,
                'rater_id' => $raterId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
