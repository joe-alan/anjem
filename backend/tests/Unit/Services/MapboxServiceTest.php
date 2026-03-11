<?php

namespace Tests\Unit\Services;

use App\Services\MapboxService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests for Phase 3 of mapbox_optimisation.md
 * Verifies MapboxService uses driving-traffic profile
 */
class MapboxServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mapbox.public_token' => 'pk.test_token_for_unit_tests']);
    }

    public function test_get_directions_uses_driving_traffic_profile(): void
    {
        Http::fake([
            'api.mapbox.com/directions/v5/mapbox/driving-traffic/*' => Http::response([
                'routes' => [[
                    'distance' => 500,
                    'duration' => 120,
                    'geometry' => ['type' => 'LineString', 'coordinates' => [[106.8271, -6.3605], [106.8295, -6.3595]]],
                ]],
            ], 200),
        ]);

        $service = new MapboxService();
        $result = $service->getDirections(-6.3605, 106.8271, -6.3595, 106.8295);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/directions/v5/mapbox/driving-traffic/');
        });

        $this->assertEquals(500, $result['distance_meters']);
        $this->assertEquals(2, $result['duration_minutes']); // ceil(120/60)
        $this->assertFalse($result['estimated']);
    }

    public function test_get_directions_does_not_use_plain_driving_profile(): void
    {
        Http::fake([
            '*' => Http::response([
                'routes' => [[
                    'distance' => 500,
                    'duration' => 60,
                    'geometry' => ['type' => 'LineString', 'coordinates' => []],
                ]],
            ], 200),
        ]);

        $service = new MapboxService();
        $service->getDirections(-6.3605, 106.8271, -6.3595, 106.8295);

        // Capture recorded request and assert URL uses driving-traffic, not plain driving
        $recorded = Http::recorded();
        $this->assertNotEmpty($recorded);
        [$request] = $recorded[0];
        // Remove 'driving-traffic' so a plain '/driving/' substring check is unambiguous
        $urlWithoutTraffic = str_replace('driving-traffic', '', $request->url());
        $this->assertStringNotContainsString('/mapbox/driving/', $urlWithoutTraffic);
    }

    public function test_get_directions_returns_geojson_geometry(): void
    {
        $geometry = ['type' => 'LineString', 'coordinates' => [[106.8271, -6.3605], [106.8295, -6.3595]]];

        Http::fake([
            'api.mapbox.com/*' => Http::response([
                'routes' => [[
                    'distance' => 270,
                    'duration' => 60,
                    'geometry' => $geometry,
                ]],
            ], 200),
        ]);

        $service = new MapboxService();
        $result = $service->getDirections(-6.3605, 106.8271, -6.3595, 106.8295);

        $this->assertEquals($geometry, $result['geometry']);
        $this->assertFalse($result['estimated']);
    }

    public function test_get_directions_falls_back_to_straight_line_on_api_failure(): void
    {
        Http::fake([
            'api.mapbox.com/*' => Http::response(null, 500),
        ]);

        $service = new MapboxService();
        $result = $service->getDirections(-6.3605, 106.8271, -6.3595, 106.8295);

        $this->assertTrue($result['estimated']);
        $this->assertGreaterThan(0, $result['distance_meters']);
        $this->assertNull($result['geometry']);
    }

    public function test_get_directions_falls_back_when_routes_array_is_empty(): void
    {
        Http::fake([
            'api.mapbox.com/*' => Http::response(['routes' => [], 'code' => 'Ok'], 200),
        ]);

        $service = new MapboxService();
        $result = $service->getDirections(-6.3605, 106.8271, -6.3595, 106.8295);

        $this->assertTrue($result['estimated']);
        $this->assertNull($result['geometry']);
    }
}
