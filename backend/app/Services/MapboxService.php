<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MapboxService
{
    /**
     * Fetch driving route data from the Mapbox Directions API.
     *
     * @return array{distance_meters: int, duration_seconds: int}|null
     */
    public function getRoute(float $originLat, float $originLng, float $destLat, float $destLng): ?array
    {
        $token = config('services.mapbox.access_token');

        if (! $token) {
            Log::warning('Mapbox access token not configured');
            return null;
        }

        $url = sprintf(
            'https://api.mapbox.com/directions/v5/mapbox/driving/%s,%s;%s,%s',
            $originLng, $originLat,
            $destLng, $destLat
        );

        $response = Http::timeout(5)->get($url, ['access_token' => $token]);

        if (! $response->successful()) {
            Log::error('Mapbox Directions API request failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        }

        $routes = $response->json('routes');

        if (empty($routes)) {
            Log::warning('Mapbox returned no routes', [
                'origin' => [$originLat, $originLng],
                'dest'   => [$destLat, $destLng],
            ]);
            return null;
        }

        return [
            'distance_meters'  => (int) $routes[0]['distance'],
            'duration_seconds' => (int) $routes[0]['duration'],
        ];
    }
}
