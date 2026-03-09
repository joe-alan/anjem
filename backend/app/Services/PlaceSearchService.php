<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * PlaceSearchService handles location search for the Anjem platform
 *
 * Searches local database first, then falls back to Mapbox Search API
 * Auto-caches API results for future queries
 */
class PlaceSearchService
{
    /**
     * Minimum results before triggering API fallback
     *
     * NOTE: Set to 0 to disable Mapbox API fallback for MVP
     * Local database search is sufficient for campus locations
     */
    private const MIN_RESULTS_THRESHOLD = 3;

    /**
     * Search for places using local database + Mapbox API fallback
     *
     * @param  string  $query  Search query string
     * @param  float|null  $latitude  User's latitude for proximity sorting
     * @param  float|null  $longitude  User's longitude for proximity sorting
     * @param  float  $radiusKm  Search radius in kilometers
     * @param  int  $limit  Maximum number of results
     * @return array Search results with metadata
     */
    public function search(
        string $query,
        ?float $latitude = null,
        ?float $longitude = null,
        float $radiusKm = 5.0,
        int $limit = 10
    ): array {
        // 1. Search local database
        $localResults = $this->searchLocalDatabase($query, $latitude, $longitude, $radiusKm, $limit);

        Log::info('Place search - local results', [
            'query' => $query,
            'local_count' => $localResults->count(),
            'has_coordinates' => $latitude !== null && $longitude !== null,
        ]);

        // 2. If we have enough results, return them
        if ($localResults->count() >= self::MIN_RESULTS_THRESHOLD) {
            return $this->formatResults($localResults, $latitude, $longitude);
        }

        // 3. Fallback to Mapbox API if we need more results
        if ($latitude !== null && $longitude !== null) {
            try {
                $apiResults = $this->searchMapboxAPI($query, $latitude, $longitude);

                // Cache API results to database
                foreach ($apiResults as $result) {
                    $cached = $this->cacheAPIResult($result);
                    if ($cached) {
                        $localResults->push($cached);
                    }
                }

                Log::info('Place search - API results cached', [
                    'query' => $query,
                    'api_count' => count($apiResults),
                    'total_count' => $localResults->count(),
                ]);
            } catch (\Exception $e) {
                Log::error('Mapbox API search failed', [
                    'query' => $query,
                    'error' => $e->getMessage(),
                ]);
                // Continue with local results only
            }
        }

        // 4. Increment usage count for matched local results
        $this->incrementUsageCounts($localResults);

        // 5. Return combined results
        return $this->formatResults($localResults->take($limit), $latitude, $longitude);
    }

    /**
     * Search local locations database
     */
    private function searchLocalDatabase(
        string $query,
        ?float $latitude,
        ?float $longitude,
        float $radiusKm,
        int $limit
    ): Collection {
        $queryBuilder = Location::query()->where('is_active', true);

        // NOTE: Proximity filter disabled for MVP testing
        // Using proximity for SORTING only, not filtering
        // This allows emulator (San Jose) to see Indonesia locations
        //
        // TODO: Re-enable with larger radius or smarter logic for production
        // if ($latitude !== null && $longitude !== null) {
        //     $radiusMeters = $radiusKm * 1000;
        //     $queryBuilder->whereRaw(
        //         'ST_DWithin(coordinates::geography, ST_MakePoint(?, ?)::geography, ?)',
        //         [$longitude, $latitude, $radiusMeters]
        //     );
        // }

        // Apply search only if query is not empty
        if (! empty($query)) {
            // Use hybrid approach: full-text search OR partial ILIKE matching
            // This allows "kanti" to match "kantin"
            $searchPattern = '%'.$query.'%';

            $queryBuilder->where(function ($q) use ($query, $searchPattern) {
                // Full-text search for complete words (more accurate)
                $q->whereRaw(
                    "to_tsvector('indonesian', name || ' ' || COALESCE(address, '') || ' ' || COALESCE(description, '')) @@ plainto_tsquery('indonesian', ?)",
                    [$query]
                )
                // OR partial match using ILIKE (case-insensitive LIKE)
                    ->orWhereRaw('LOWER(name) LIKE LOWER(?)', [$searchPattern])
                    ->orWhereRaw('LOWER(address) LIKE LOWER(?)', [$searchPattern])
                    ->orWhereRaw('LOWER(description) LIKE LOWER(?)', [$searchPattern]);
            });
        }

        // Sort by: beacons first, then popularity, then distance (if coordinates provided)
        $queryBuilder->orderByRaw('is_beacon DESC');
        $queryBuilder->orderByRaw('COALESCE(usage_count, 0) DESC');

        if ($latitude !== null && $longitude !== null) {
            $queryBuilder->orderByRaw(
                'ST_Distance(coordinates::geography, ST_MakePoint(?, ?)::geography) ASC',
                [$longitude, $latitude]
            );
        }

        return $queryBuilder->limit($limit * 2)->get(); // Get extra for filtering
    }

    /**
     * Search Mapbox Search API (fallback)
     */
    private function searchMapboxAPI(string $query, float $latitude, float $longitude): array
    {
        $publicToken = config('services.mapbox.public_token');

        if (! $publicToken) {
            throw new \Exception('Mapbox public token not configured');
        }

        // Generate session token — groups suggest + retrieve calls into one billable session
        $sessionToken = (string) Str::uuid();

        // Use Mapbox Search Box API (Suggest endpoint)
        $response = Http::timeout(5)->get('https://api.mapbox.com/search/searchbox/v1/suggest', [
            'q' => $query,
            'language' => 'id', // Indonesian
            'proximity' => "$longitude,$latitude",
            'bbox' => config('services.mapbox.search_bbox', '106.80,-6.39,106.86,-6.33'),
            'limit' => 5,
            'session_token' => $sessionToken,
            'access_token' => $publicToken,
        ]);

        if (! $response->successful()) {
            throw new \Exception("Mapbox API error: {$response->status()}");
        }

        $data = $response->json();
        $suggestions = $data['suggestions'] ?? [];

        // Retrieve full details for each suggestion
        $results = [];
        foreach ($suggestions as $suggestion) {
            if (isset($suggestion['mapbox_id'])) {
                try {
                    $details = $this->retrieveMapboxPlace($suggestion['mapbox_id'], $publicToken, $sessionToken);
                    if ($details) {
                        $results[] = $details;
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to retrieve Mapbox place details', [
                        'mapbox_id' => $suggestion['mapbox_id'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $results;
    }

    /**
     * Retrieve full place details from Mapbox
     */
    private function retrieveMapboxPlace(string $mapboxId, string $token, string $sessionToken): ?array
    {
        $response = Http::timeout(5)->get("https://api.mapbox.com/search/searchbox/v1/retrieve/{$mapboxId}", [
            'session_token' => $sessionToken,
            'access_token' => $token,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        $feature = $data['features'][0] ?? null;

        if (! $feature) {
            return null;
        }

        $coordinates = $feature['geometry']['coordinates'] ?? null;
        $properties = $feature['properties'] ?? [];

        if (! $coordinates || count($coordinates) < 2) {
            return null;
        }

        return [
            'name' => $properties['name'] ?? 'Unknown',
            'address' => $properties['full_address'] ?? $properties['place_formatted'] ?? '',
            'longitude' => $coordinates[0],
            'latitude' => $coordinates[1],
            'mapbox_id' => $mapboxId,
            'category' => $properties['poi_category'] ?? null,
        ];
    }

    /**
     * Cache API result to database
     */
    private function cacheAPIResult(array $result): ?Location
    {
        try {
            $point = new Point($result['latitude'], $result['longitude'], 4326);

            // Check if location already exists within 100m
            $existing = Location::query()
                ->whereRaw(
                    'ST_DWithin(coordinates::geography, ST_MakePoint(?, ?)::geography, ?)',
                    [$result['longitude'], $result['latitude'], 100]
                )
                ->first();

            if ($existing) {
                // Update usage count if exists
                $existing->increment('usage_count');

                return $existing;
            }

            // Create new location
            $metadata = [
                'source' => 'mapbox_api',
                'mapbox_id' => $result['mapbox_id'] ?? null,
                'category' => $result['category'] ?? null,
            ];

            return Location::create([
                'name' => $result['name'],
                'address' => $result['address'],
                'coordinates' => $point,
                'is_beacon' => false, // API results are P2P destinations
                'is_active' => true,
                'usage_count' => 1,
                'metadata' => $metadata,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cache API result', [
                'result' => $result,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Increment usage count for search results
     */
    private function incrementUsageCounts(Collection $locations): void
    {
        $ids = $locations->pluck('id')->toArray();

        if (! empty($ids)) {
            Location::whereIn('id', $ids)->increment('usage_count');
        }
    }

    /**
     * Format results for API response
     */
    private function formatResults(Collection $locations, ?float $userLat, ?float $userLng): array
    {
        return $locations->map(function ($location) use ($userLat, $userLng) {
            $result = [
                'id' => $location->id,
                'name' => $location->name,
                'address' => $location->address,
                'coordinates' => [
                    'latitude' => $location->coordinates->latitude,
                    'longitude' => $location->coordinates->longitude,
                ],
                'is_beacon' => $location->is_beacon,
                'usage_count' => $location->usage_count ?? 0,
                'category' => $location->metadata['category'] ?? ($location->is_beacon ? 'beacon' : 'destination'),
            ];

            // Add distance if user coordinates provided
            if ($userLat !== null && $userLng !== null) {
                $userPoint = new Point($userLat, $userLng, 4326);
                $distance = $location->getDistanceTo($userPoint);
                $result['distance_km'] = round($distance / 1000, 2);
            }

            // Add beacon-specific info
            if ($location->is_beacon) {
                $result['beacon_capacity'] = $location->beacon_capacity;
                $result['current_queue_size'] = $location->current_queue_size ?? 0;
                $result['has_capacity'] = $location->hasCapacity();
            }

            return $result;
        })->toArray();
    }
}
