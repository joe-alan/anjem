<?php

namespace Tests\Feature\Api;

use App\Models\Location;
use App\Models\User;
use App\Services\LocationService;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Mockery;
use Tests\TestCase;

/**
 * Test DriverController for queue conflicts, location issues, and edge cases
 */
class DriverControllerTest extends TestCase
{
    use RefreshDatabase;

    private $mockQueueService;

    private $mockLocationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockQueueService = Mockery::mock(QueueService::class);
        $this->mockLocationService = Mockery::mock(LocationService::class);

        $this->app->instance(QueueService::class, $this->mockQueueService);
        $this->app->instance(LocationService::class, $this->mockLocationService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test unauthorized user cannot access driver endpoints
     */
    public function test_unauthorized_user_cannot_access_driver_endpoints()
    {
        $response = $this->postJson('/api/v1/driver/online');
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);

        $response = $this->postJson('/api/v1/driver/offline');
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);

        $response = $this->getJson('/api/v1/driver/queue');
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Test rider cannot access driver-only endpoints
     */
    public function test_rider_cannot_access_driver_endpoints()
    {
        $rider = User::factory()->rider()->create();
        $token = $rider->createToken('mobile-app', ['rider:request-ride'])->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/driver/online', [
                'beacon_id' => 1,
                'current_latitude' => -6.3605,
                'current_longitude' => 106.8271,
            ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJson([
                'message' => 'This action is unauthorized.',
            ]);
    }

    /**
     * Test driver cannot go online when already in queue
     */
    public function test_driver_cannot_go_online_when_already_in_queue()
    {
        $driver = User::factory()->driver()->create();
        $beacon = $this->createTestBeacon();

        // Create token with proper driver permissions
        $token = $driver->createToken('mobile-app', ['driver:go-online'])->plainTextToken;

        $this->mockQueueService
            ->shouldReceive('getDriverQueueStatus')
            ->once()
            ->with($driver->id)
            ->andReturn(['in_queue' => true, 'beacon_id' => $beacon->id, 'position' => 1]);

        $response = $this->withToken($token)
            ->postJson('/api/v1/driver/online', [
                'beacon_id' => $beacon->id,
                'current_latitude' => -6.3605,
                'current_longitude' => 106.8271,
            ]);

        $response->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJson([
                'success' => false,
                'message' => 'Already in queue',
            ]);
    }

    /**
     * Test driver cannot join beacon queue when not eligible
     */
    public function test_driver_cannot_join_ineligible_beacon()
    {
        $driver = User::factory()->driver()->create();
        $beacon = $this->createTestBeacon();
        $token = $driver->createToken('mobile-app', ['driver:go-online'])->plainTextToken;

        $this->mockQueueService
            ->shouldReceive('getDriverQueueStatus')
            ->once()
            ->with($driver->id)
            ->andReturn(['in_queue' => false]);

        $this->mockQueueService
            ->shouldReceive('canDriverJoinBeacon')
            ->once()
            ->with($driver->id, $beacon->id)
            ->andReturn([
                'can_join' => false,
                'reasons' => ['Driver not verified', 'Too far from beacon'],
            ]);

        $response = $this->withToken($token)
            ->postJson('/api/v1/driver/online', [
                'beacon_id' => $beacon->id,
                'current_latitude' => -6.4000,
                'current_longitude' => 106.9000,
            ]);

        $response->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot join beacon queue',
                'reasons' => ['Driver not verified', 'Too far from beacon'],
            ]);
    }

    /**
     * Test going offline when not in queue
     */
    public function test_going_offline_when_not_in_queue()
    {
        $driver = User::factory()->driver()->create();
        $token = $driver->createToken('mobile-app', ['driver:go-online'])->plainTextToken;

        $this->mockQueueService
            ->shouldReceive('leaveQueue')
            ->once()
            ->with($driver->id)
            ->andReturn(false);

        $response = $this->withToken($token)
            ->postJson('/api/v1/driver/offline');

        $response->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJson([
                'success' => false,
                'message' => 'Not currently in queue or failed to leave',
            ]);
    }

    /**
     * Test location update with invalid coordinates
     */
    public function test_location_update_with_invalid_coordinates()
    {
        $driver = User::factory()->driver()->create();
        $token = $driver->createToken('mobile-app', ['driver:update-location'])->plainTextToken;

        // Test invalid latitude (outside -90 to 90 range)
        $response = $this->withToken($token)
            ->postJson('/api/v1/driver/location', [
                'latitude' => 91.0,
                'longitude' => 106.8271,
                'heading' => 0,
                'speed' => 0,
            ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Test invalid longitude (outside -180 to 180 range)
        $response = $this->withToken($token)
            ->postJson('/api/v1/driver/location', [
                'latitude' => -6.3605,
                'longitude' => 181.0,
                'heading' => 0,
                'speed' => 0,
            ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Test location update service failure
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

        $response = $this->withToken($token)
            ->postJson('/api/v1/driver/location', [
                'latitude' => -6.3605,
                'longitude' => 106.8271,
                'heading' => 0,
                'speed' => 0,
            ]);

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to update location',
            ]);
    }

    /**
     * Test getting statistics without driver profile
     */
    public function test_statistics_without_driver_profile()
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $token = $driver->createToken('mobile-app', ['driver:go-online'])->plainTextToken;
        // Don't create driver profile - method returns early without calling queue service

        $response = $this->withToken($token)
            ->getJson('/api/v1/driver/statistics');

        $response->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJson([
                'success' => false,
                'message' => 'Driver profile not found',
            ]);
    }

    /**
     * Test concurrent queue join attempts
     */
    public function test_concurrent_queue_join_attempts()
    {
        $driver = User::factory()->driver()->create();
        $beacon = $this->createTestBeacon();
        $token = $driver->createToken('mobile-app', ['driver:go-online'])->plainTextToken;

        // Simulate race condition where queue status changes between checks
        $this->mockQueueService
            ->shouldReceive('getDriverQueueStatus')
            ->once()
            ->with($driver->id)
            ->andReturn(['in_queue' => false]);

        $this->mockQueueService
            ->shouldReceive('canDriverJoinBeacon')
            ->once()
            ->with($driver->id, $beacon->id)
            ->andReturn(['can_join' => true]);

        $this->mockLocationService
            ->shouldReceive('updateDriverLocation')
            ->once()
            ->with($driver->id, -6.3605, 106.8271);

        // Simulate queue join failure (e.g., queue became full)
        $this->mockQueueService
            ->shouldReceive('joinQueue')
            ->once()
            ->with($driver->id, $beacon->id)
            ->andReturn(null);

        $response = $this->withToken($token)
            ->postJson('/api/v1/driver/online', [
                'beacon_id' => $beacon->id,
                'current_latitude' => -6.3605,
                'current_longitude' => 106.8271,
            ]);

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to join queue',
            ]);
    }

    /**
     * Test missing required fields for going online
     */
    public function test_going_online_with_missing_fields()
    {
        $driver = User::factory()->driver()->create();
        $token = $driver->createToken('mobile-app', ['driver:go-online'])->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/driver/online', [
                'beacon_id' => 1,
                // Missing current_latitude and current_longitude
            ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Test invalid beacon ID
     */
    public function test_going_online_with_invalid_beacon()
    {
        $driver = User::factory()->driver()->create();
        $token = $driver->createToken('mobile-app', ['driver:go-online'])->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/driver/online', [
                'beacon_id' => 99999, // Non-existent beacon
                'current_latitude' => -6.3605,
                'current_longitude' => 106.8271,
            ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // Helper methods

    private function createTestBeacon(): Location
    {
        return Location::create([
            'name' => 'Test Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);
    }
}
