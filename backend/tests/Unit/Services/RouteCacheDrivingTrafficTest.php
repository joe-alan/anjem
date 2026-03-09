<?php

namespace Tests\Unit\Services;

use App\Models\Location;
use App\Models\RouteCache;
use App\Services\MapboxService;
use App\Services\RouteCacheService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Mockery;
use Tests\TestCase;

/**
 * Tests for Phase 3 of mapbox_optimisation.md
 * Verifies RouteCacheService uses driving-traffic as default profile
 */
class RouteCacheDrivingTrafficTest extends TestCase
{
    use RefreshDatabase;

    private RouteCacheService $service;

    private $mapboxMock;

    private Location $origin;

    private Location $destination;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapboxMock = Mockery::mock(MapboxService::class);
        $this->service = new RouteCacheService($this->mapboxMock);

        $this->origin = Location::create([
            'name' => 'Library Gate',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $this->destination = Location::create([
            'name' => 'Engineering Faculty',
            'coordinates' => new Point(-6.3595, 106.8295, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_default_profile_is_driving_traffic(): void
    {
        $this->mapboxMock
            ->shouldReceive('getDirections')
            ->once()
            ->andReturn([
                'distance_meters' => 300,
                'duration_minutes' => 3,
                'geometry' => ['type' => 'LineString', 'coordinates' => []],
                'estimated' => false,
            ]);

        $this->service->getOrFetchRoute(
            $this->origin->id,
            $this->destination->id,
            -6.3605, 106.8271,
            -6.3595, 106.8295
        );

        // Verify the stored cache entry uses driving-traffic
        $cached = RouteCache::where('origin_location_id', $this->origin->id)
            ->where('destination_location_id', $this->destination->id)
            ->first();

        $this->assertNotNull($cached);
        $this->assertEquals('driving-traffic', $cached->profile);
    }

    public function test_old_driving_cache_entry_is_bypassed_for_driving_traffic_request(): void
    {
        // Seed a cache entry with the OLD 'driving' profile
        RouteCache::create([
            'origin_location_id' => $this->origin->id,
            'destination_location_id' => $this->destination->id,
            'profile' => 'driving', // Old profile
            'distance_meters' => 270,
            'duration_minutes' => 2,
            'route_geometry' => '{}',
            'fetch_count' => 5,
            'last_fetched_at' => Carbon::now(),
        ]);

        // Mapbox MUST be called — old 'driving' entry is not a hit for 'driving-traffic'
        $this->mapboxMock
            ->shouldReceive('getDirections')
            ->once()
            ->andReturn([
                'distance_meters' => 310,
                'duration_minutes' => 3,
                'geometry' => ['type' => 'LineString', 'coordinates' => []],
                'estimated' => false,
            ]);

        $result = $this->service->getOrFetchRoute(
            $this->origin->id,
            $this->destination->id,
            -6.3605, 106.8271,
            -6.3595, 106.8295
        );

        $this->assertFalse($result['cached']);
        $this->assertEquals(310, $result['distance_meters']);
    }

    public function test_driving_traffic_cache_entry_is_served_on_second_call(): void
    {
        $this->mapboxMock
            ->shouldReceive('getDirections')
            ->once() // Only called once — second call hits cache
            ->andReturn([
                'distance_meters' => 300,
                'duration_minutes' => 3,
                'geometry' => ['type' => 'LineString', 'coordinates' => []],
                'estimated' => false,
            ]);

        // First call — cache miss, fetches from Mapbox
        $this->service->getOrFetchRoute(
            $this->origin->id, $this->destination->id,
            -6.3605, 106.8271, -6.3595, 106.8295
        );

        // Second call — should hit cache stored with driving-traffic profile
        $result = $this->service->getOrFetchRoute(
            $this->origin->id, $this->destination->id,
            -6.3605, 106.8271, -6.3595, 106.8295
        );

        $this->assertTrue($result['cached']);
        $this->assertEquals(300, $result['distance_meters']);
    }

    public function test_refresh_route_default_profile_is_driving_traffic(): void
    {
        $this->mapboxMock
            ->shouldReceive('getDirections')
            ->once()
            ->andReturn([
                'distance_meters' => 320,
                'duration_minutes' => 4,
                'geometry' => ['type' => 'LineString', 'coordinates' => []],
                'estimated' => false,
            ]);

        $this->service->refreshRoute(
            $this->origin->id,
            $this->destination->id,
            -6.3605, 106.8271,
            -6.3595, 106.8295
        );

        $cached = RouteCache::where('origin_location_id', $this->origin->id)
            ->where('destination_location_id', $this->destination->id)
            ->first();

        $this->assertNotNull($cached);
        $this->assertEquals('driving-traffic', $cached->profile);
    }

    public function test_cleanup_removes_entries_older_than_threshold_days(): void
    {
        // Fresh entry
        RouteCache::create([
            'origin_location_id' => $this->origin->id,
            'destination_location_id' => $this->destination->id,
            'profile' => 'driving-traffic',
            'distance_meters' => 300,
            'duration_minutes' => 3,
            'route_geometry' => '{}',
            'fetch_count' => 1,
            'last_fetched_at' => Carbon::now(),
        ]);

        // Stale entry (31 days old — exceeds 30-day threshold)
        $staleEntry = RouteCache::create([
            'origin_location_id' => $this->destination->id,
            'destination_location_id' => $this->origin->id,
            'profile' => 'driving-traffic',
            'distance_meters' => 300,
            'duration_minutes' => 3,
            'route_geometry' => '{}',
            'fetch_count' => 1,
            'last_fetched_at' => Carbon::now()->subDays(31),
        ]);

        $deletedCount = $this->service->cleanupStaleRoutes(30);

        $this->assertEquals(1, $deletedCount);
        $this->assertEquals(1, RouteCache::count());
        $this->assertNull(RouteCache::find($staleEntry->id));
    }
}
