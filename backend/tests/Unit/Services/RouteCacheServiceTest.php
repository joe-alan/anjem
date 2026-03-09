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

class RouteCacheServiceTest extends TestCase
{
    use RefreshDatabase;

    private RouteCacheService $routeCacheService;
    private $mapboxServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock for MapboxService
        $this->mapboxServiceMock = Mockery::mock(MapboxService::class);

        // Instantiate RouteCacheService with mocked MapboxService
        $this->routeCacheService = new RouteCacheService($this->mapboxServiceMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test cache miss - first time fetching a route
     */
    public function test_cache_miss_fetches_from_mapbox_and_caches()
    {
        // Create test locations
        $origin = Location::create([
            'name' => 'Origin',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $destination = Location::create([
            'name' => 'Destination',
            'coordinates' => new Point(-6.3595, 106.8295, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        // Mock Mapbox API response
        $this->mapboxServiceMock
            ->shouldReceive('getDirections')
            ->once()
            ->with(-6.3605, 106.8271, -6.3595, 106.8295)
            ->andReturn([
                'distance_meters' => 270,
                'duration_minutes' => 2,
                'geometry' => '{"type":"LineString","coordinates":[[106.8271,-6.3605],[106.8295,-6.3595]]}',
                'estimated' => false,
            ]);

        // First call should be cache miss
        $result = $this->routeCacheService->getOrFetchRoute(
            $origin->id,
            $destination->id,
            -6.3605,
            106.8271,
            -6.3595,
            106.8295
        );

        // Verify response
        $this->assertIsArray($result);
        $this->assertEquals(270, $result['distance_meters']);
        $this->assertEquals(2, $result['duration_minutes']);
        $this->assertFalse($result['cached']);
        $this->assertEquals(0, $result['cache_age_days']);

        // Verify route was cached in database
        $cachedRoute = RouteCache::where('origin_location_id', $origin->id)
            ->where('destination_location_id', $destination->id)
            ->first();

        $this->assertNotNull($cachedRoute);
        $this->assertEquals(270, $cachedRoute->distance_meters);
        $this->assertEquals(2, $cachedRoute->duration_minutes);
        $this->assertEquals(1, $cachedRoute->fetch_count);
    }

    /**
     * Test cache hit - subsequent fetch from cache
     */
    public function test_cache_hit_returns_cached_data()
    {
        // Create test locations
        $origin = Location::create([
            'name' => 'Origin',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $destination = Location::create([
            'name' => 'Destination',
            'coordinates' => new Point(-6.3595, 106.8295, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        // Create cached route
        $cachedRoute = RouteCache::create([
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
            'profile' => 'driving-traffic',
            'distance_meters' => 270,
            'duration_minutes' => 2,
            'route_geometry' => '{"type":"LineString","coordinates":[[106.8271,-6.3605],[106.8295,-6.3595]]}',
            'fetch_count' => 1,
            'last_fetched_at' => Carbon::now(),
        ]);

        // MapboxService should NOT be called (cache hit)
        $this->mapboxServiceMock
            ->shouldReceive('getDirections')
            ->never();

        // Fetch route (should hit cache)
        $result = $this->routeCacheService->getOrFetchRoute(
            $origin->id,
            $destination->id,
            -6.3605,
            106.8271,
            -6.3595,
            106.8295
        );

        // Verify response indicates cache hit
        $this->assertIsArray($result);
        $this->assertEquals(270, $result['distance_meters']);
        $this->assertEquals(2, $result['duration_minutes']);
        $this->assertTrue($result['cached']);
        $this->assertEquals(0, $result['cache_age_days']);

        // Verify fetch count was incremented
        $cachedRoute->refresh();
        $this->assertEquals(2, $cachedRoute->fetch_count);
    }

    /**
     * Test cache expiry - stale routes are refreshed
     */
    public function test_stale_cache_is_refreshed()
    {
        // Create test locations
        $origin = Location::create([
            'name' => 'Origin',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $destination = Location::create([
            'name' => 'Destination',
            'coordinates' => new Point(-6.3595, 106.8295, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        // Create stale cached route (8 days old - exceeds 7 day TTL)
        RouteCache::create([
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
            'profile' => 'driving-traffic',
            'distance_meters' => 270,
            'duration_minutes' => 2,
            'route_geometry' => '{"type":"LineString","coordinates":[[106.8271,-6.3605],[106.8295,-6.3595]]}',
            'fetch_count' => 5,
            'last_fetched_at' => Carbon::now()->subDays(8), // Stale
        ]);

        // Mock Mapbox API to return updated route data
        $this->mapboxServiceMock
            ->shouldReceive('getDirections')
            ->once()
            ->with(-6.3605, 106.8271, -6.3595, 106.8295)
            ->andReturn([
                'distance_meters' => 280, // Slightly different
                'duration_minutes' => 3,
                'geometry' => '{"type":"LineString","coordinates":[[106.8271,-6.3605],[106.8295,-6.3595]]}',
                'estimated' => false,
            ]);

        // Fetch route (should refresh stale cache)
        $result = $this->routeCacheService->getOrFetchRoute(
            $origin->id,
            $destination->id,
            -6.3605,
            106.8271,
            -6.3595,
            106.8295
        );

        // Verify response has updated data
        $this->assertIsArray($result);
        $this->assertEquals(280, $result['distance_meters']); // Updated
        $this->assertEquals(3, $result['duration_minutes']); // Updated
        $this->assertFalse($result['cached']); // Fresh fetch

        // Verify cache was updated
        $cachedRoute = RouteCache::where('origin_location_id', $origin->id)
            ->where('destination_location_id', $destination->id)
            ->first();

        $this->assertNotNull($cachedRoute);
        $this->assertEquals(280, $cachedRoute->distance_meters);
        $this->assertTrue(Carbon::now()->diffInMinutes($cachedRoute->last_fetched_at) < 1);
    }

    /**
     * Test cache statistics
     */
    public function test_cache_statistics()
    {
        // Create test locations
        $origin1 = Location::create([
            'name' => 'Origin 1',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $destination1 = Location::create([
            'name' => 'Destination 1',
            'coordinates' => new Point(-6.3595, 106.8295, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $origin2 = Location::create([
            'name' => 'Origin 2',
            'coordinates' => new Point(-6.3615, 106.8281, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        // Create cached routes with different fetch counts
        RouteCache::create([
            'origin_location_id' => $origin1->id,
            'destination_location_id' => $destination1->id,
            'profile' => 'driving-traffic',
            'distance_meters' => 270,
            'duration_minutes' => 2,
            'route_geometry' => '{}',
            'fetch_count' => 10,
            'last_fetched_at' => Carbon::now(),
        ]);

        RouteCache::create([
            'origin_location_id' => $origin2->id,
            'destination_location_id' => $destination1->id,
            'profile' => 'driving-traffic',
            'distance_meters' => 350,
            'duration_minutes' => 3,
            'route_geometry' => '{}',
            'fetch_count' => 5,
            'last_fetched_at' => Carbon::now()->subDays(3),
        ]);

        // Get cache statistics
        $stats = $this->routeCacheService->getCacheStats();

        // Verify statistics
        $this->assertIsArray($stats);
        $this->assertEquals(2, $stats['total_cached_routes']);
        $this->assertEquals(15, $stats['total_cache_hits']); // 10 + 5
        $this->assertArrayHasKey('cache_hit_rate', $stats);
        $this->assertArrayHasKey('fresh_routes', $stats);
        $this->assertArrayHasKey('stale_routes', $stats);
    }

    /**
     * Test popular routes retrieval
     */
    public function test_get_popular_routes()
    {
        // Create test locations
        $origin = Location::create([
            'name' => 'Library',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $destination1 = Location::create([
            'name' => 'Engineering',
            'coordinates' => new Point(-6.3595, 106.8295, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $destination2 = Location::create([
            'name' => 'Dorm',
            'coordinates' => new Point(-6.3650, 106.8300, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        // Create routes with different popularity
        RouteCache::create([
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination1->id,
            'profile' => 'driving-traffic',
            'distance_meters' => 270,
            'duration_minutes' => 2,
            'route_geometry' => '{}',
            'fetch_count' => 50, // Very popular
            'last_fetched_at' => Carbon::now(),
        ]);

        RouteCache::create([
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination2->id,
            'profile' => 'driving-traffic',
            'distance_meters' => 550,
            'duration_minutes' => 5,
            'route_geometry' => '{}',
            'fetch_count' => 10, // Less popular
            'last_fetched_at' => Carbon::now(),
        ]);

        // Get popular routes
        $popularRoutes = $this->routeCacheService->getPopularRoutes(5);

        // Verify results
        $this->assertIsArray($popularRoutes);
        $this->assertCount(2, $popularRoutes);

        // Most popular should be first
        $this->assertEquals('Library', $popularRoutes[0]['origin']);
        $this->assertEquals('Engineering', $popularRoutes[0]['destination']);
        $this->assertEquals(50, $popularRoutes[0]['fetch_count']);
    }

    /**
     * Test cleanup of stale routes
     */
    public function test_cleanup_stale_routes()
    {
        // Create test locations
        $origin = Location::create([
            'name' => 'Origin',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $destination = Location::create([
            'name' => 'Destination',
            'coordinates' => new Point(-6.3595, 106.8295, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        // Create fresh route
        RouteCache::create([
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
            'profile' => 'driving-traffic',
            'distance_meters' => 270,
            'duration_minutes' => 2,
            'route_geometry' => '{}',
            'fetch_count' => 1,
            'last_fetched_at' => Carbon::now(),
        ]);

        // Create very stale route (31 days old)
        $staleRoute = RouteCache::create([
            'origin_location_id' => $destination->id,
            'destination_location_id' => $origin->id,
            'profile' => 'driving-traffic',
            'distance_meters' => 270,
            'duration_minutes' => 2,
            'route_geometry' => '{}',
            'fetch_count' => 1,
            'last_fetched_at' => Carbon::now()->subDays(31),
        ]);

        // Cleanup routes older than 30 days
        $deletedCount = $this->routeCacheService->cleanupStaleRoutes(30);

        // Verify only stale route was deleted
        $this->assertEquals(1, $deletedCount);
        $this->assertEquals(1, RouteCache::count());
        $this->assertNull(RouteCache::find($staleRoute->id));
    }

    /**
     * Test cache clearing
     */
    public function test_clear_cache()
    {
        // Create test locations
        $origin = Location::create([
            'name' => 'Origin',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $destination = Location::create([
            'name' => 'Destination',
            'coordinates' => new Point(-6.3595, 106.8295, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        // Create multiple cached routes
        RouteCache::create([
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
            'profile' => 'driving-traffic',
            'distance_meters' => 270,
            'duration_minutes' => 2,
            'route_geometry' => '{}',
            'fetch_count' => 1,
            'last_fetched_at' => Carbon::now(),
        ]);

        RouteCache::create([
            'origin_location_id' => $destination->id,
            'destination_location_id' => $origin->id,
            'profile' => 'driving-traffic',
            'distance_meters' => 270,
            'duration_minutes' => 2,
            'route_geometry' => '{}',
            'fetch_count' => 1,
            'last_fetched_at' => Carbon::now(),
        ]);

        $this->assertEquals(2, RouteCache::count());

        // Clear all cache
        $clearedCount = $this->routeCacheService->clearCache();

        // Verify all routes were cleared
        $this->assertEquals(2, $clearedCount);
        $this->assertEquals(0, RouteCache::count());
    }

    /**
     * Test manual route refresh
     */
    public function test_manual_route_refresh()
    {
        // Create test locations
        $origin = Location::create([
            'name' => 'Origin',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $destination = Location::create([
            'name' => 'Destination',
            'coordinates' => new Point(-6.3595, 106.8295, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        // Create existing cached route
        $cachedRoute = RouteCache::create([
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
            'profile' => 'driving-traffic',
            'distance_meters' => 270,
            'duration_minutes' => 2,
            'route_geometry' => '{}',
            'fetch_count' => 5,
            'last_fetched_at' => Carbon::now()->subDays(3),
        ]);

        // Mock Mapbox API to return updated data
        $this->mapboxServiceMock
            ->shouldReceive('getDirections')
            ->once()
            ->with(-6.3605, 106.8271, -6.3595, 106.8295)
            ->andReturn([
                'distance_meters' => 290, // Updated
                'duration_minutes' => 3,   // Updated
                'geometry' => '{"updated": true}',
                'estimated' => false,
            ]);

        // Manually refresh the route
        $result = $this->routeCacheService->refreshRoute(
            $origin->id,
            $destination->id,
            -6.3605,
            106.8271,
            -6.3595,
            106.8295
        );

        // Verify response has updated data
        $this->assertEquals(290, $result['distance_meters']);
        $this->assertEquals(3, $result['duration_minutes']);

        // Verify cache was updated
        $cachedRoute->refresh();
        $this->assertEquals(290, $cachedRoute->distance_meters);
        $this->assertEquals(3, $cachedRoute->duration_minutes);
        $this->assertTrue(Carbon::now()->diffInMinutes($cachedRoute->last_fetched_at) < 1);
    }
}
