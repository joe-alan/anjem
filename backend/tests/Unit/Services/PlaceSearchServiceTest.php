<?php

namespace Tests\Unit\Services;

use App\Models\Location;
use App\Services\PlaceSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

/**
 * Tests for Phase 2 of mapbox_optimisation.md
 * Verifies Search Box API: threshold, session token, bbox, result caching
 */
class PlaceSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlaceSearchService $service;

    private float $lat = -6.3615;

    private float $lng = 106.8242;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mapbox.public_token' => 'pk.test_token']);
        config(['services.mapbox.search_bbox' => '106.80,-6.39,106.86,-6.33']);
        $this->service = new PlaceSearchService();
    }

    // -------------------------------------------------------------------------
    // Threshold behaviour
    // -------------------------------------------------------------------------

    public function test_search_does_not_call_mapbox_when_local_results_meet_threshold(): void
    {
        Http::fake(); // Any HTTP call will fail the assertion

        $this->seedLocations('Canteen', 3);

        $this->service->search('canteen', $this->lat, $this->lng);

        Http::assertNothingSent();
    }

    public function test_search_calls_mapbox_when_local_results_below_threshold(): void
    {
        $this->seedLocations('Canteen', 2); // Only 2 — below threshold of 3

        Http::fake([
            'api.mapbox.com/search/searchbox/v1/suggest*' => Http::response([
                'suggestions' => [], // Empty — simplest stub
            ], 200),
        ]);

        $this->service->search('canteen', $this->lat, $this->lng);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/search/searchbox/v1/suggest');
        });
    }

    public function test_search_does_not_call_mapbox_when_exactly_three_local_results(): void
    {
        Http::fake();

        $this->seedLocations('Gate', 3);

        $this->service->search('gate', $this->lat, $this->lng);

        Http::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // Mapbox request parameters
    // -------------------------------------------------------------------------

    public function test_mapbox_suggest_request_includes_bbox_parameter(): void
    {
        $this->seedLocations('Library', 1); // 1 result — triggers fallback

        Http::fake([
            'api.mapbox.com/search/searchbox/v1/suggest*' => Http::response([
                'suggestions' => [],
            ], 200),
        ]);

        $this->service->search('library', $this->lat, $this->lng);

        Http::assertSent(function ($request) {
            $params = $request->data();

            return str_contains($request->url(), '/search/searchbox/v1/suggest')
                && isset($params['bbox'])
                && $params['bbox'] === '106.80,-6.39,106.86,-6.33';
        });
    }

    public function test_mapbox_suggest_request_includes_session_token(): void
    {
        $this->seedLocations('Library', 1);

        Http::fake([
            'api.mapbox.com/search/searchbox/v1/suggest*' => Http::response([
                'suggestions' => [],
            ], 200),
        ]);

        $this->service->search('library', $this->lat, $this->lng);

        Http::assertSent(function ($request) {
            $params = $request->data();

            return str_contains($request->url(), '/search/searchbox/v1/suggest')
                && isset($params['session_token'])
                && preg_match(
                    '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                    $params['session_token']
                );
        });
    }

    public function test_suggest_and_retrieve_share_same_session_token(): void
    {
        $this->seedLocations('Library', 1);

        $capturedTokens = [];

        Http::fake([
            'api.mapbox.com/search/searchbox/v1/suggest*' => function ($request) use (&$capturedTokens) {
                $capturedTokens['suggest'] = $request->data()['session_token'] ?? null;

                return Http::response([
                    'suggestions' => [['mapbox_id' => 'test.place.123']],
                ], 200);
            },
            'api.mapbox.com/search/searchbox/v1/retrieve/*' => function ($request) use (&$capturedTokens) {
                $capturedTokens['retrieve'] = $request->data()['session_token'] ?? null;

                return Http::response(['features' => []], 200);
            },
        ]);

        $this->service->search('library', $this->lat, $this->lng);

        $this->assertNotNull($capturedTokens['suggest'] ?? null);
        $this->assertNotNull($capturedTokens['retrieve'] ?? null);
        $this->assertEquals($capturedTokens['suggest'], $capturedTokens['retrieve']);
    }

    // -------------------------------------------------------------------------
    // Result caching
    // -------------------------------------------------------------------------

    public function test_mapbox_result_is_cached_to_database_with_an_id(): void
    {
        $this->seedLocations('Library', 0); // 0 local results

        Http::fake([
            'api.mapbox.com/search/searchbox/v1/suggest*' => Http::response([
                'suggestions' => [['mapbox_id' => 'place.abc123']],
            ], 200),
            'api.mapbox.com/search/searchbox/v1/retrieve/*' => Http::response([
                'features' => [[
                    'geometry' => ['coordinates' => [106.8295, -6.3595]],
                    'properties' => [
                        'name' => 'Engineering Faculty',
                        'full_address' => 'Depok, West Java',
                    ],
                ]],
            ], 200),
        ]);

        $results = $this->service->search('engineering', $this->lat, $this->lng);

        // Result from Mapbox should be persisted and have a DB ID
        $this->assertNotEmpty($results);
        foreach ($results as $result) {
            $this->assertNotNull($result['id'], 'Cached Mapbox result must have a non-null DB id');
        }

        // Verify it was saved to locations table
        $this->assertDatabaseHas('locations', ['name' => 'Engineering Faculty']);
    }

    public function test_nearby_existing_location_is_returned_instead_of_creating_duplicate(): void
    {
        // Existing location within 100m of Mapbox result
        Location::create([
            'name' => 'Existing Engineering',
            'address' => 'Depok',
            'coordinates' => new Point(-6.3595, 106.8295, 4326),
            'is_beacon' => false,
            'is_active' => true,
        ]);

        Http::fake([
            'api.mapbox.com/search/searchbox/v1/suggest*' => Http::response([
                'suggestions' => [['mapbox_id' => 'place.eng123']],
            ], 200),
            'api.mapbox.com/search/searchbox/v1/retrieve/*' => Http::response([
                'features' => [[
                    'geometry' => ['coordinates' => [106.8295, -6.3595]], // Same spot
                    'properties' => [
                        'name' => 'Engineering Faculty',
                        'full_address' => 'Depok, West Java',
                    ],
                ]],
            ], 200),
        ]);

        $this->service->search('engineering', $this->lat, $this->lng);

        // Should not have created a second record — only 1 location near this point
        $this->assertEquals(1, Location::where('is_beacon', false)->count());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function seedLocations(string $namePrefix, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            Location::create([
                'name' => "{$namePrefix} {$i}",
                'address' => 'UI Campus',
                'coordinates' => new Point(-6.3605 - ($i * 0.001), 106.8271 + ($i * 0.001), 4326),
                'is_beacon' => false,
                'is_active' => true,
            ]);
        }
    }
}
