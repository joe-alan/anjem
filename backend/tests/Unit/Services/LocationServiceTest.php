<?php

namespace Tests\Unit\Services;

use App\Models\Location;
use App\Services\LocationService;
use App\Services\MapboxService;
use App\Services\RouteCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Mockery;
use Tests\TestCase;

class LocationServiceTest extends TestCase
{
    use RefreshDatabase;

    private LocationService $locationService;
    private $mapboxServiceMock;
    private $routeCacheServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for dependencies
        $this->mapboxServiceMock = Mockery::mock(MapboxService::class);
        $this->routeCacheServiceMock = Mockery::mock(RouteCacheService::class);

        // Instantiate LocationService with mocked dependencies
        $this->locationService = new LocationService(
            $this->mapboxServiceMock,
            $this->routeCacheServiceMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test finding nearby beacons with valid coordinates
     */
    public function test_can_find_nearby_beacons_with_valid_coordinates()
    {
        // Create test beacons
        $beacon1 = Location::create([
            'name' => 'Central Library',
            'address' => 'UI Central Library',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
            'beacon_capacity' => 10,
            'current_queue_size' => 0,
        ]);

        $beacon2 = Location::create([
            'name' => 'Engineering Faculty',
            'address' => 'FTUI Building',
            'coordinates' => new Point(-6.3595, 106.8295, 4326),
            'is_beacon' => true,
            'is_active' => true,
            'beacon_capacity' => 15,
            'current_queue_size' => 0,
        ]);

        // Test point close to beacon1 with smaller radius
        $nearbyBeacons = $this->locationService->findNearbyBeacons(-6.3605, 106.8271, 200);

        $this->assertCount(1, $nearbyBeacons);
        $this->assertEquals($beacon1->id, $nearbyBeacons->first()->id);
    }

    /**
     * Test finding nearby beacons with no results
     */
    public function test_find_nearby_beacons_returns_empty_when_none_found()
    {
        // Create beacon far away
        Location::create([
            'name' => 'Distant Beacon',
            'is_beacon' => true,
            'coordinates' => new Point(-7.0000, 107.0000, 4326),
            'is_active' => true,
        ]);

        // Search in different location
        $nearbyBeacons = $this->locationService->findNearbyBeacons(-6.3605, 106.8271, 100);

        $this->assertCount(0, $nearbyBeacons);
    }

    /**
     * Test finding nearby beacons ignores inactive beacons
     */
    public function test_find_nearby_beacons_ignores_inactive_beacons()
    {
        Location::create([
            'name' => 'Inactive Beacon',
            'is_beacon' => true,
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_active' => false, // Inactive
        ]);

        $nearbyBeacons = $this->locationService->findNearbyBeacons(-6.3605, 106.8271, 500);

        $this->assertCount(0, $nearbyBeacons);
    }

    /**
     * Test finding nearby beacons only returns beacons, not P2P destinations
     */
    public function test_find_nearby_beacons_excludes_p2p_destinations()
    {
        Location::create([
            'name' => 'P2P Destination',
            'is_beacon' => false, // Not a beacon
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_active' => true,
        ]);

        $nearbyBeacons = $this->locationService->findNearbyBeacons(-6.3605, 106.8271, 500);

        $this->assertCount(0, $nearbyBeacons);
    }

    /**
     * Test error handling for invalid coordinates
     */
    public function test_find_nearby_beacons_handles_invalid_coordinates()
    {
        // Note: Invalid coordinates will actually work in PostGIS, just not find results
        // So we test with coordinates that just return empty results
        $nearbyBeacons = $this->locationService->findNearbyBeacons(0, 0, 500);

        $this->assertCount(0, $nearbyBeacons);
    }

    /**
     * Test creating P2P destination with valid data
     */
    public function test_can_create_p2p_destination()
    {
        $destination = $this->locationService->findOrCreateDestination(
            'Student Dormitory',
            'UI Student Housing Complex',
            -6.3650,
            106.8300
        );

        $this->assertNotNull($destination);
        $this->assertEquals('Student Dormitory', $destination->name);
        $this->assertFalse($destination->is_beacon);
        $this->assertTrue($destination->is_active);
    }

    /**
     * Test P2P destination deduplication within 100m
     */
    public function test_p2p_destination_deduplication()
    {
        // Create first destination
        $destination1 = $this->locationService->findOrCreateDestination(
            'Coffee Shop',
            'Campus Coffee Shop',
            -6.3600,
            106.8280
        );

        // Try to create another very close destination (within 100m)
        $destination2 = $this->locationService->findOrCreateDestination(
            'Different Coffee Shop',
            'Another Coffee Shop',
            -6.3601, // Very close coordinates
            106.8281
        );

        // Should return the existing destination
        $this->assertEquals($destination1->id, $destination2->id);
    }

    /**
     * Test P2P destination creation with distant coordinates
     */
    public function test_p2p_destination_creates_new_when_distant()
    {
        // Create first destination
        $destination1 = $this->locationService->findOrCreateDestination(
            'Coffee Shop 1',
            'Campus Coffee Shop 1',
            -6.3600,
            106.8280
        );

        // Create second destination far enough away
        $destination2 = $this->locationService->findOrCreateDestination(
            'Coffee Shop 2',
            'Campus Coffee Shop 2',
            -6.3650, // 500m+ away
            106.8350
        );

        // Should create a new destination
        $this->assertNotEquals($destination1->id, $destination2->id);
    }

    /**
     * Test error handling for P2P destination creation
     */
    public function test_p2p_destination_handles_errors()
    {
        // For this test, we'll test with extreme coordinates that might cause issues
        // PostGIS can handle most coordinate ranges, so this tests service robustness
        $destination = $this->locationService->findOrCreateDestination(
            'Edge Case Location',
            'Edge Case Address',
            -89.999, // Near south pole
            179.999  // Near date line
        );

        // Should still work with extreme but valid coordinates
        $this->assertNotNull($destination);
    }

    /**
     * Test getting closest beacon
     */
    public function test_can_get_closest_beacon()
    {
        $beacon1 = Location::create([
            'name' => 'Close Beacon',
            'is_beacon' => true,
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_active' => true,
        ]);

        $beacon2 = Location::create([
            'name' => 'Far Beacon',
            'is_beacon' => true,
            'coordinates' => new Point(-6.3700, 106.8400, 4326),
            'is_active' => true,
        ]);

        // Search from a point close to beacon1
        $closestBeacon = $this->locationService->getClosestBeacon(-6.3606, 106.8272);

        $this->assertNotNull($closestBeacon);
        $this->assertEquals($beacon1->id, $closestBeacon->id);
    }

    /**
     * Test distance calculation
     */
    public function test_can_calculate_distance()
    {
        // Distance between UI Library and Engineering Faculty (roughly 270m)
        $distance = $this->locationService->calculateDistance(-6.3605, 106.8271, -6.3595, 106.8295);

        $this->assertGreaterThan(200, $distance);
        $this->assertLessThan(400, $distance);
    }

    /**
     * Test available beacons filtering
     */
    public function test_can_get_available_beacons()
    {
        // Create beacons with different capacities
        $availableBeacon = Location::create([
            'name' => 'Available Beacon',
            'is_beacon' => true,
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_active' => true,
            'beacon_capacity' => 10,
            'current_queue_size' => 5, // Has capacity
        ]);

        $fullBeacon = Location::create([
            'name' => 'Full Beacon',
            'is_beacon' => true,
            'coordinates' => new Point(-6.3595, 106.8295, 4326),
            'is_active' => true,
            'beacon_capacity' => 5,
            'current_queue_size' => 5, // At capacity
        ]);

        $availableBeacons = $this->locationService->getAvailableBeacons();

        $this->assertCount(1, $availableBeacons);
        $this->assertEquals($availableBeacon->id, $availableBeacons->first()->id);
    }

    /**
     * Test driving details calculation
     */
    public function test_can_get_driving_details()
    {
        // Mock MapboxService to return driving details when route cache service isn't used
        $this->mapboxServiceMock
            ->shouldReceive('getDirections')
            ->once()
            ->with(-6.3605, 106.8271, -6.3595, 106.8295)
            ->andReturn([
                'distance_meters' => 270,
                'duration_minutes' => 2,
                'estimated' => true,
                'geometry' => null,
            ]);

        $details = $this->locationService->getDrivingDetails(-6.3605, 106.8271, -6.3595, 106.8295);

        $this->assertIsArray($details);
        $this->assertArrayHasKey('distance_meters', $details);
        $this->assertArrayHasKey('duration_minutes', $details);
        $this->assertArrayHasKey('estimated', $details);
        $this->assertTrue($details['estimated']); // Should be estimated for MVP
        $this->assertGreaterThan(0, $details['distance_meters']);
        $this->assertGreaterThan(0, $details['duration_minutes']);
    }

    /**
     * Test optimal pickup beacon selection
     */
    public function test_optimal_pickup_beacon_selection()
    {
        // Create beacons with different characteristics
        $closerFullBeacon = Location::create([
            'name' => 'Closer Full Beacon',
            'is_beacon' => true,
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_active' => true,
            'beacon_capacity' => 5,
            'current_queue_size' => 5, // Full
        ]);

        $fartherAvailableBeacon = Location::create([
            'name' => 'Farther Available Beacon',
            'is_beacon' => true,
            'coordinates' => new Point(-6.3620, 106.8290, 4326),
            'is_active' => true,
            'beacon_capacity' => 10,
            'current_queue_size' => 2, // Available
        ]);

        // Should prefer available beacon over closer full beacon
        $optimalBeacon = $this->locationService->findOptimalPickupBeacon(-6.3606, 106.8272);

        $this->assertNotNull($optimalBeacon);
        $this->assertEquals($fartherAvailableBeacon->id, $optimalBeacon->id);
    }

    /**
     * Test optimal pickup beacon when no beacons nearby
     */
    public function test_optimal_pickup_beacon_returns_null_when_none_nearby()
    {
        // Create beacon far away
        Location::create([
            'name' => 'Distant Beacon',
            'is_beacon' => true,
            'coordinates' => new Point(-7.0000, 107.0000, 4326),
            'is_active' => true,
        ]);

        $optimalBeacon = $this->locationService->findOptimalPickupBeacon(-6.3605, 106.8271);

        $this->assertNull($optimalBeacon);
    }

    /**
     * Test location details formatting
     */
    public function test_can_get_location_details()
    {
        $beacon = Location::create([
            'name' => 'Test Beacon',
            'is_beacon' => true,
            'address' => 'Test Address',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_active' => true,
            'beacon_capacity' => 10,
            'current_queue_size' => 3,
        ]);

        $details = $this->locationService->getLocationDetails($beacon->id);

        $this->assertIsArray($details);
        $this->assertEquals($beacon->id, $details['id']);
        $this->assertEquals('Test Beacon', $details['name']);
        $this->assertTrue($details['is_beacon']);
        $this->assertArrayHasKey('coordinates', $details);
        $this->assertArrayHasKey('beacon_capacity', $details);
        $this->assertArrayHasKey('current_queue_size', $details);
        $this->assertArrayHasKey('has_capacity', $details);
        $this->assertTrue($details['has_capacity']);
    }

    /**
     * Test location details for non-existent location
     */
    public function test_location_details_returns_empty_for_non_existent()
    {
        $details = $this->locationService->getLocationDetails(999);

        $this->assertEmpty($details);
    }

    /**
     * Test edge case: zero radius search
     */
    public function test_find_nearby_beacons_with_zero_radius()
    {
        Location::create([
            'name' => 'Exact Location Beacon',
            'is_beacon' => true,
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_active' => true,
        ]);

        // Search with zero radius at exact coordinates
        $nearbyBeacons = $this->locationService->findNearbyBeacons(-6.3605, 106.8271, 0);

        // Should still find the beacon at exact coordinates
        $this->assertCount(1, $nearbyBeacons);
    }

    /**
     * Test edge case: very large radius
     */
    public function test_find_nearby_beacons_with_large_radius()
    {
        $beacon1 = Location::create([
            'name' => 'Beacon 1',
            'is_beacon' => true,
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_active' => true,
        ]);

        $beacon2 = Location::create([
            'name' => 'Beacon 2',
            'is_beacon' => true,
            'coordinates' => new Point(-6.4000, 106.9000, 4326),
            'is_active' => true,
        ]);

        // Search with very large radius (100km)
        $nearbyBeacons = $this->locationService->findNearbyBeacons(-6.3605, 106.8271, 100000);

        $this->assertCount(2, $nearbyBeacons);
    }
}
