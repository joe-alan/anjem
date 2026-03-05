<?php

namespace Tests\Feature\Api;

use App\Models\Location;
use App\Models\User;
use App\Services\LocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Mockery;
use Tests\TestCase;

/**
 * Test DriverController for auth, location, and statistics endpoints.
 *
 * Note: beacon-queue methods (getQueue, getAvailableBeacons) were removed
 * when the legacy driver_queue table was retired. The FIFO matching queue
 * (MatchingQueueService / queue_joined_at on driver_profiles) is now the
 * only queue system tested here.
 */
class DriverControllerTest extends TestCase
{
    use RefreshDatabase;

    private $mockLocationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockLocationService = Mockery::mock(LocationService::class);
        $this->app->instance(LocationService::class, $this->mockLocationService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Unauthenticated requests to driver endpoints return 401.
     */
    public function test_unauthorized_user_cannot_access_driver_endpoints()
    {
        $this->postJson('/api/v1/driver/online')->assertStatus(Response::HTTP_UNAUTHORIZED);
        $this->postJson('/api/v1/driver/offline')->assertStatus(Response::HTTP_UNAUTHORIZED);
        $this->getJson('/api/v1/driver/queue-position')->assertStatus(Response::HTTP_UNAUTHORIZED);
        $this->getJson('/api/v1/driver/statistics')->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * A rider token lacks driver abilities and must be rejected with 403.
     */
    public function test_rider_cannot_access_driver_endpoints()
    {
        $rider = User::factory()->rider()->create();
        $token = $rider->createToken('mobile-app', ['rider:request-ride'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/driver/online')
            ->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized: Driver permissions required',
            ]);
    }

    /**
     * Test going offline when not in an active ride succeeds.
     */
    public function test_going_offline_succeeds()
    {
        $driver = User::factory()->driver()->create();
        $token = $driver->createToken('mobile-app', ['driver:go-online'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/driver/offline')
            ->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'success' => true,
                'message' => 'Successfully went offline',
            ]);
    }

    /**
     * Test location update rejects coordinates outside valid range.
     */
    public function test_location_update_with_invalid_coordinates()
    {
        $driver = User::factory()->driver()->create();
        $token = $driver->createToken('mobile-app', ['driver:update-location'])->plainTextToken;

        // Latitude out of range
        $this->withToken($token)
            ->postJson('/api/v1/driver/location', [
                'latitude'  => 91.0,
                'longitude' => 106.8271,
                'heading'   => 0,
                'speed'     => 0,
            ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Longitude out of range
        $this->withToken($token)
            ->postJson('/api/v1/driver/location', [
                'latitude'  => -6.3605,
                'longitude' => 181.0,
                'heading'   => 0,
                'speed'     => 0,
            ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Test location update returns 500 when the location service fails.
     */
    public function test_location_update_service_failure()
    {
        $driver = User::factory()->driver()->create();
        $token = $driver->createToken('mobile-app', ['driver:update-location'])->plainTextToken;

        $this->mockLocationService
            ->shouldReceive('updateDriverLocation')
            ->once()
            ->with($driver->id, -6.3605, 106.8271, 0, 0)
            ->andReturn(false);

        $this->withToken($token)
            ->postJson('/api/v1/driver/location', [
                'latitude'  => -6.3605,
                'longitude' => 106.8271,
                'heading'   => 0,
                'speed'     => 0,
            ])
            ->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to update location',
            ]);
    }

    /**
     * Test statistics endpoint returns 404 when driver profile is missing.
     */
    public function test_statistics_without_driver_profile()
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $token = $driver->createToken('mobile-app', ['driver:go-online'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/driver/statistics')
            ->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJson([
                'success' => false,
                'message' => 'Driver profile not found',
            ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createTestBeacon(): Location
    {
        return Location::create([
            'name'        => 'Test Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon'   => true,
            'is_active'   => true,
        ]);
    }
}
