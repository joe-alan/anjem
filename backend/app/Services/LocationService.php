<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * LocationService handles all spatial operations for the Anjem platform
 *
 * Provides methods for finding nearby beacons, managing P2P destinations,
 * and integrating with external mapping services for distance calculations.
 */
class LocationService
{
    private MapboxService $mapboxService;

    private RouteCacheService $routeCacheService;

    public function __construct(MapboxService $mapboxService, RouteCacheService $routeCacheService)
    {
        $this->mapboxService = $mapboxService;
        $this->routeCacheService = $routeCacheService;
    }

    /**
     * Find nearby beacon locations within walking distance
     */
    public function findNearbyBeacons(float $latitude, float $longitude, float $radiusMeters = 500): Collection
    {
        try {
            return Location::findNearbyBeacons($latitude, $longitude, $radiusMeters);
        } catch (\Exception $e) {
            Log::error('Failed to find nearby beacons', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'radius' => $radiusMeters,
                'error' => $e->getMessage(),
            ]);

            return new Collection;
        }
    }

    /**
     * Find or create a P2P destination location
     */
    public function findOrCreateDestination(string $name, string $address, float $latitude, float $longitude): ?Location
    {
        try {
            return Location::findOrCreateDestination($name, $address, $latitude, $longitude);
        } catch (\Exception $e) {
            Log::error('Failed to find or create destination', [
                'name' => $name,
                'address' => $address,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get the closest beacon to a given location
     */
    public function getClosestBeacon(float $latitude, float $longitude): ?Location
    {
        $point = new Point($latitude, $longitude, 4326);

        return Location::beacons()
            ->active()
            ->orderByDistance($point)
            ->first();
    }

    /**
     * Calculate straight-line distance between two points in meters
     */
    public function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        // Use Haversine formula for distance calculation
        $earthRadius = 6371000; // Earth's radius in meters

        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLatRad = deg2rad($lat2 - $lat1);
        $deltaLngRad = deg2rad($lng2 - $lng1);

        $a = sin($deltaLatRad / 2) * sin($deltaLatRad / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLngRad / 2) * sin($deltaLngRad / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Get beacons with available capacity for drivers
     */
    public function getAvailableBeacons(): Collection
    {
        return Location::beacons()
            ->active()
            ->whereRaw('COALESCE(current_queue_size, 0) < COALESCE(beacon_capacity, 10)')
            ->orderBy('current_queue_size', 'asc')
            ->get();
    }

    /**
     * Get driving distance and duration using Mapbox Directions API
     * Falls back to straight-line estimate if API fails
     *
     * @param  float  $originLat  Origin latitude
     * @param  float  $originLng  Origin longitude
     * @param  float  $destLat  Destination latitude
     * @param  float  $destLng  Destination longitude
     * @param  int|null  $originLocationId  Optional origin location ID for caching
     * @param  int|null  $destLocationId  Optional destination location ID for caching
     * @return array ['distance_meters', 'duration_minutes', 'geometry', 'estimated', 'cached'?]
     */
    public function getDrivingDetails(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        ?int $originLocationId = null,
        ?int $destLocationId = null
    ): array {
        // If location IDs are provided, use route caching for 80-90% cost reduction
        if ($originLocationId && $destLocationId) {
            return $this->routeCacheService->getOrFetchRoute(
                $originLocationId,
                $destLocationId,
                $originLat,
                $originLng,
                $destLat,
                $destLng
            );
        }

        // Fallback: Direct Mapbox API call (for backward compatibility)
        return $this->mapboxService->getDirections($originLat, $originLng, $destLat, $destLng);
    }

    /**
     * Future: Google Maps API integration for accurate routing
     * Commented out for MVP due to API costs
     */
    private function getGoogleMapsDetails(float $originLat, float $originLng, float $destLat, float $destLng): array
    {
        /*
        $apiKey = config('services.google_maps.api_key');

        if (!$apiKey) {
            throw new \Exception('Google Maps API key not configured');
        }

        $response = Http::get('https://maps.googleapis.com/maps/api/distancematrix/json', [
            'origins' => "{$originLat},{$originLng}",
            'destinations' => "{$destLat},{$destLng}",
            'units' => 'metric',
            'mode' => 'driving',
            'key' => $apiKey
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $element = $data['rows'][0]['elements'][0];

            if ($element['status'] === 'OK') {
                return [
                    'distance_meters' => $element['distance']['value'],
                    'duration_minutes' => ceil($element['duration']['value'] / 60),
                    'estimated' => false
                ];
            }
        }

        throw new \Exception('Failed to get routing details from Google Maps');
        */

        return [];
    }

    /**
     * Find optimal pickup beacon for a ride request
     * Considers distance, queue size, and availability
     */
    public function findOptimalPickupBeacon(float $riderLat, float $riderLng): ?Location
    {
        $nearbyBeacons = $this->findNearbyBeacons($riderLat, $riderLng, 1000); // 1km radius

        if ($nearbyBeacons->isEmpty()) {
            return null;
        }

        // Score beacons based on distance and queue size
        $scored = $nearbyBeacons->map(function ($beacon) use ($riderLat, $riderLng) {
            $distance = $this->calculateDistance(
                $riderLat,
                $riderLng,
                $beacon->coordinates->latitude,
                $beacon->coordinates->longitude
            );

            // Scoring algorithm: lower is better
            // Distance penalty: 1 point per 100m
            // Queue penalty: 5 points per waiting driver
            // Capacity bonus: -10 points if has capacity
            $score = ($distance / 100) + ($beacon->current_queue_size * 5);

            if ($beacon->hasCapacity()) {
                $score -= 10;
            }

            return [
                'beacon' => $beacon,
                'score' => $score,
                'distance' => $distance,
            ];
        })->sortBy('score');

        return $scored->first()['beacon'];
    }

    /**
     * Update queue sizes for all beacon locations
     * Should be called periodically or after queue changes
     */
    public function updateAllBeaconQueueSizes(): void
    {
        Location::beacons()->each(function ($beacon) {
            $beacon->updateQueueSize();
        });
    }

    /**
     * Get location details for mobile app display
     */
    public function getLocationDetails(int $locationId): array
    {
        $location = Location::find($locationId);

        if (! $location) {
            return [];
        }

        $details = [
            'id' => $location->id,
            'name' => $location->name,
            'address' => $location->address,
            'is_beacon' => $location->is_beacon,
            'coordinates' => $location->latLng,
            'is_active' => $location->is_active,
        ];

        // Add beacon-specific details
        if ($location->isBeacon()) {
            $details['beacon_capacity'] = $location->beacon_capacity;
            $details['current_queue_size'] = $location->current_queue_size;
            $details['has_capacity'] = $location->hasCapacity();
        }

        return $details;
    }

    /**
     * Update driver's current location with optional heading and speed
     * Uses Redis caching for high-frequency updates with periodic DB writes
     */
    public function updateDriverLocation(
        int $driverId,
        float $latitude,
        float $longitude,
        ?float $heading = null,
        ?float $speed = null
    ): bool {
        try {
            $user = \App\Models\User::find($driverId);

            if (! $user || ! $user->driverProfile) {
                Log::error('Cannot update location: Driver profile not found', [
                    'driver_id' => $driverId,
                ]);

                return false;
            }

            // Update driver profile location
            $user->driverProfile->updateLocation($latitude, $longitude);

            // Cache the location update for real-time features (optional heading/speed)
            $cacheKey = "driver_location:{$driverId}";
            $locationData = [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'heading' => $heading,
                'speed' => $speed,
                'updated_at' => now()->toISOString(),
            ];

            \Illuminate\Support\Facades\Cache::put($cacheKey, $locationData, 300); // 5 minutes TTL

            Log::debug('Driver location updated', [
                'driver_id' => $driverId,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to update driver location', [
                'driver_id' => $driverId,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
