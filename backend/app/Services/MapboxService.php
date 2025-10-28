<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MapboxService handles integration with Mapbox APIs
 *
 * Provides routing, geocoding, and place search functionality
 * using Mapbox's free tier (100k requests/month).
 */
class MapboxService
{
    private string $accessToken;
    private string $baseUrl = 'https://api.mapbox.com';

    public function __construct()
    {
        $this->accessToken = config('services.mapbox.public_token');

        if (!$this->accessToken) {
            throw new \Exception('Mapbox access token not configured');
        }
    }

    /**
     * Get driving directions between two points
     * Uses Mapbox Directions API with driving-traffic profile
     *
     * @param float $originLat Origin latitude
     * @param float $originLng Origin longitude
     * @param float $destLat Destination latitude
     * @param float $destLng Destination longitude
     * @return array ['distance_meters' => int, 'duration_minutes' => int, 'geometry' => string, 'estimated' => bool]
     */
    public function getDirections(float $originLat, float $originLng, float $destLat, float $destLng): array
    {
        try {
            // Mapbox Directions API expects lng,lat format
            $coordinates = "{$originLng},{$originLat};{$destLng},{$destLat}";

            $url = "{$this->baseUrl}/directions/v5/mapbox/driving/{$coordinates}";

            $response = Http::timeout(10)->get($url, [
                'access_token' => $this->accessToken,
                'geometries' => 'geojson', // Get route geometry for map display
                'overview' => 'full', // Full route geometry
                // Don't include 'steps' or 'alternatives' - omit for defaults
            ]);

            if (!$response->successful()) {
                Log::error('Mapbox Directions API error', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'origin' => "{$originLat},{$originLng}",
                    'destination' => "{$destLat},{$destLng}",
                ]);

                // Fallback to straight-line estimate
                return $this->getStraightLineEstimate($originLat, $originLng, $destLat, $destLng);
            }

            $data = $response->json();

            // Check if we got routes
            if (empty($data['routes'])) {
                Log::warning('Mapbox returned no routes', [
                    'origin' => "{$originLat},{$originLng}",
                    'destination' => "{$destLat},{$destLng}",
                ]);

                return $this->getStraightLineEstimate($originLat, $originLng, $destLat, $destLng);
            }

            $route = $data['routes'][0];

            return [
                'distance_meters' => (int) $route['distance'], // Mapbox returns meters
                'duration_minutes' => (int) ceil($route['duration'] / 60), // Convert seconds to minutes
                'geometry' => $route['geometry'], // GeoJSON LineString for map display
                'estimated' => false, // Real route from Mapbox
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get Mapbox directions', [
                'error' => $e->getMessage(),
                'origin' => "{$originLat},{$originLng}",
                'destination' => "{$destLat},{$destLng}",
            ]);

            // Fallback to straight-line estimate
            return $this->getStraightLineEstimate($originLat, $originLng, $destLat, $destLng);
        }
    }

    /**
     * Fallback: Calculate straight-line estimate when Mapbox API fails
     * Uses Haversine formula with campus-specific multipliers
     */
    private function getStraightLineEstimate(float $lat1, float $lng1, float $lat2, float $lng2): array
    {
        $distance = $this->calculateDistance($lat1, $lng1, $lat2, $lng2);

        // Campus estimates:
        // - Distance factor: 1.3x straight line (road curvature)
        // - Average speed: 20 km/h (campus speed limits)
        $estimatedDistance = $distance * 1.3;
        $estimatedDuration = ($estimatedDistance / 1000) / 20 * 60; // minutes

        return [
            'distance_meters' => (int) $estimatedDistance,
            'duration_minutes' => (int) ceil($estimatedDuration),
            'geometry' => null, // No geometry for straight-line estimate
            'estimated' => true, // Flag as estimate
        ];
    }

    /**
     * Calculate straight-line distance using Haversine formula
     * Returns distance in meters
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
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
     * Get distance matrix for multiple origin-destination pairs
     * Useful for batch calculations (driver matching, etc.)
     *
     * Note: For MVP, we'll implement single-route only.
     * Matrix API has different pricing/limits.
     */
    public function getDistanceMatrix(array $origins, array $destinations): array
    {
        // TODO: Implement if needed for optimized driver matching
        throw new \Exception('Distance Matrix not implemented in MVP');
    }
}
