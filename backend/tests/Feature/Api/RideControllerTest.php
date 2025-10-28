<?php

namespace Tests\Feature\Api;

use App\Models\Location;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\RideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Mockery;
use Tests\TestCase;

/**
 * Test RideController for edge cases, unauthorized access, and invalid states
 */
class RideControllerTest extends TestCase
{
    use RefreshDatabase;

    private $mockRideService;

    private $mockNotificationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRideService = Mockery::mock(RideService::class);
        $this->mockNotificationService = Mockery::mock(NotificationService::class);

        $this->app->instance(RideService::class, $this->mockRideService);
        $this->app->instance(NotificationService::class, $this->mockNotificationService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test unauthorized user cannot view rides
     */
    public function test_unauthorized_user_cannot_view_rides()
    {
        $response = $this->getJson('/api/v1/rides');

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Test rider cannot view other users' rides
     */
    public function test_rider_cannot_view_other_users_rides()
    {
        $rider = User::factory()->rider()->create();
        $driver = User::factory()->driver()->create();
        $otherRider = User::factory()->rider()->create();

        // Create a ride between driver and otherRider (not involving $rider)
        $otherRide = $this->createTestRide(['rider_id' => $otherRider->id, 'driver_id' => $driver->id]);

        $response = $this->actingAs($rider, 'sanctum')
            ->getJson("/api/v1/rides/{$otherRide->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized to view this ride',
            ]);
    }

    /**
     * Test driver cannot accept ride without proper permissions
     */
    public function test_driver_cannot_accept_ride_without_permissions()
    {
        $rider = User::factory()->rider()->create();
        $rideRequest = $this->createTestRideRequest(['rider_id' => $rider->id]);

        // Create token without driver permissions
        $token = $rider->createToken('mobile-app', ['profile:read']);

        $response = $this->withToken($token->plainTextToken)
            ->postJson("/api/v1/rides/{$rideRequest->id}/accept");

        $response->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized: Driver permissions required',
            ]);
    }

    /**
     * Test unauthorized ride status updates
     */
    public function test_unauthorized_ride_status_updates()
    {
        $rider = User::factory()->rider()->create();
        $driver = User::factory()->driver()->create();
        $otherUser = User::factory()->rider()->create();

        $ride = $this->createTestRide([
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'status' => 'accepted',
        ]);

        $token = $otherUser->createToken('mobile-app', ['driver:complete-ride']);

        $response = $this->withToken($token->plainTextToken)
            ->patchJson("/api/v1/rides/{$ride->id}/status", [
                'status' => 'completed',
            ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized to update this ride',
            ]);
    }

    // Helper methods

    private function createTestRide(array $overrides = []): Ride
    {
        $rider = User::factory()->rider()->create();
        $driver = User::factory()->driver()->create();

        $pickup = Location::create([
            'name' => 'Test Pickup',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $destination = Location::create([
            'name' => 'Test Destination',
            'coordinates' => new Point(-6.3650, 106.8300, 4326),
            'is_beacon' => false,
            'is_active' => true,
        ]);

        $rideRequest = RideRequest::create([
            'rider_id' => $rider->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $destination->id,
            'estimated_distance_km' => 1.0,
            'estimated_duration_minutes' => 5,
            'estimated_fare_rp' => 8000,
            'passenger_count' => 1,
            'status' => 'matched',
            'expires_at' => now()->addMinutes(30),
        ]);

        return Ride::create(array_merge([
            'ride_request_id' => $rideRequest->id,
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $destination->id,
            'status' => 'matched',
            'passenger_count' => 1,
            'estimated_fare_rp' => 8000,
        ], $overrides));
    }

    private function createTestRideRequest(array $overrides = []): RideRequest
    {
        $rider = User::factory()->rider()->create();

        $pickup = Location::create([
            'name' => 'Test Pickup Location',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $destination = Location::create([
            'name' => 'Test Destination Location',
            'coordinates' => new Point(-6.3650, 106.8300, 4326),
            'is_beacon' => false,
            'is_active' => true,
        ]);

        return RideRequest::create(array_merge([
            'rider_id' => $rider->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $destination->id,
            'estimated_distance_km' => 1.0,
            'estimated_duration_minutes' => 5,
            'estimated_fare_rp' => 8000,
            'passenger_count' => 1,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ], $overrides));
    }
}
