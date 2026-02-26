<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class RouteCacheService
{
    public function __construct(private MapboxService $mapboxService) {}

    /**
     * Return driving route data, using a 24-hour Redis cache to avoid
     * redundant Mapbox API calls for the same origin/destination pair.
     *
     * @return array{distance_meters: int, duration_seconds: int}|null
     */
    public function getRoute(float $originLat, float $originLng, float $destLat, float $destLng): ?array
    {
        $key = sprintf(
            'route:%.4f,%.4f:%.4f,%.4f',
            $originLat, $originLng,
            $destLat, $destLng
        );

        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached;
        }

        $route = $this->mapboxService->getRoute($originLat, $originLng, $destLat, $destLng);
        if ($route !== null) {
            Cache::put($key, $route, now()->addHours(24));
        }

        return $route;
    }
}
