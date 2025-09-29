<?php

namespace Tests\Feature\Api;

use App\Models\Location;
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
 * Test RequestController for concurrent requests, validation, and edge cases
 */
class RequestControllerTest extends TestCase
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
     * Test unauthorized user cannot access request endpoints
     */
    public function test_unauthorized_user_cannot_access_requests()
    {
        $response = $this->getJson('/api/v1/requests');
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);

        $response = $this->postJson('/api/v1/requests');
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);

        $response = $this->getJson('/api/v1/requests/estimates');
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Test driver cannot access rider-only endpoints
     */
    public function test_driver_cannot_access_rider_endpoints()
    {
        $driver = User::factory()->driver()->create();
        $token = $driver->createToken('mobile-app', ['driver:go-online'])->plainTextToken;

        $response = $this->withToken($token)
                        ->getJson('/api/v1/requests');

        $response->assertStatus(Response::HTTP_FORBIDDEN)
                ->assertJson([
                    'success' => false,
                    'message' => 'Unauthorized: Rider permissions required'
                ]);
    }

    /**
     * Test rider cannot create duplicate active ride requests
     */
    public function test_rider_cannot_create_duplicate_active_requests()
    {
        $rider = User::factory()->rider()->create();
        $token = $rider->createToken('mobile-app', ['rider:request-ride'])->plainTextToken;
        $pickup = $this->createTestLocation('Test Pickup', true);
        $destination = $this->createTestLocation('Test Destination', false);

        // Create an active ride request
        $activeRequest = $this->createTestRideRequest([
            'rider_id' => $rider->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $destination->id,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30)
        ]);

        $response = $this->withToken($token)
                        ->postJson('/api/v1/requests', [
                            'pickup_location_id' => $pickup->id,
                            'destination_location_id' => $destination->id,
                            'passenger_count' => 1,
                        ]);

        $response->assertStatus(Response::HTTP_BAD_REQUEST)
                ->assertJson([
                    'success' => false,
                    'message' => 'You already have an active ride request'
                ]);
    }

    /**
     * Test ride request creation with invalid locations
     */
    public function test_create_request_with_invalid_locations()
    {
        $rider = User::factory()->rider()->create();

        // Test with same pickup and destination
        $location = $this->createTestLocation('Same Location', true);

        $response = $this->actingAs($rider, 'sanctum')
                        ->postJson('/api/v1/requests', [
                            'pickup_location_id' => $location->id,
                            'destination_location_id' => $location->id,
                            'passenger_count' => 1,
                        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Test with non-existent locations
        $response = $this->actingAs($rider, 'sanctum')
                        ->postJson('/api/v1/requests', [
                            'pickup_location_id' => 99999,
                            'destination_location_id' => 99998,
                            'passenger_count' => 1,
                        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Test ride request creation with invalid passenger count
     */
    public function test_create_request_with_invalid_passenger_count()
    {
        $rider = User::factory()->rider()->create();
        $pickup = $this->createTestLocation('Test Pickup', true);
        $destination = $this->createTestLocation('Test Destination', false);

        // Test with 0 passengers
        $response = $this->actingAs($rider, 'sanctum')
                        ->postJson('/api/v1/requests', [
                            'pickup_location_id' => $pickup->id,
                            'destination_location_id' => $destination->id,
                            'passenger_count' => 0,
                        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Test with too many passengers
        $response = $this->actingAs($rider, 'sanctum')
                        ->postJson('/api/v1/requests', [
                            'pickup_location_id' => $pickup->id,
                            'destination_location_id' => $destination->id,
                            'passenger_count' => 5,
                        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Test ride service failure during request creation
     */
    public function test_ride_service_failure_during_creation()
    {
        $rider = User::factory()->rider()->create();
        $pickup = $this->createTestLocation('Test Pickup', true);
        $destination = $this->createTestLocation('Test Destination', false);

        $this->mockRideService
            ->shouldReceive('createRideRequest')
            ->once()
            ->andReturn(null);

        $response = $this->actingAs($rider, 'sanctum')
                        ->postJson('/api/v1/requests', [
                            'pickup_location_id' => $pickup->id,
                            'destination_location_id' => $destination->id,
                            'passenger_count' => 1,
                        ]);

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)
                ->assertJson([
                    'success' => false,
                    'message' => 'Unable to create ride request'
                ]);
    }

    /**
     * Test rider cannot view other users' ride requests
     */
    public function test_rider_cannot_view_other_users_requests()
    {
        $rider = User::factory()->rider()->create();
        $otherRider = User::factory()->rider()->create();

        $otherRequest = $this->createTestRideRequest(['rider_id' => $otherRider->id]);

        $response = $this->actingAs($rider, 'sanctum')
                        ->getJson("/api/v1/requests/{$otherRequest->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN)
                ->assertJson([
                    'success' => false,
                    'message' => 'Unauthorized to view this ride request'
                ]);
    }

    /**
     * Test cancelling request without proper permissions
     */
    public function test_cancel_request_without_permissions()
    {
        $rider = User::factory()->rider()->create();
        $rideRequest = $this->createTestRideRequest(['rider_id' => $rider->id]);

        // Create token without cancel permissions
        $token = $rider->createToken('mobile-app', ['rider:request-ride'])->plainTextToken;

        $response = $this->withToken($token)
                        ->patchJson("/api/v1/requests/{$rideRequest->id}/cancel");

        $response->assertStatus(Response::HTTP_FORBIDDEN)
                ->assertJson([
                    'success' => false,
                    'message' => 'Unauthorized: Cancel ride permissions required'
                ]);
    }

    /**
     * Test cancelling request in non-cancellable status
     */
    public function test_cancel_request_in_non_cancellable_status()
    {
        $rider = User::factory()->rider()->create();
        $rideRequest = $this->createTestRideRequest([
            'rider_id' => $rider->id,
            'status' => 'completed'
        ]);

        $response = $this->actingAs($rider, 'sanctum')
                        ->patchJson("/api/v1/requests/{$rideRequest->id}/cancel");

        $response->assertStatus(Response::HTTP_BAD_REQUEST)
                ->assertJson([
                    'success' => false,
                    'message' => 'Cannot cancel ride request in current status',
                    'current_status' => 'completed'
                ]);
    }

    /**
     * Test cancellation service failure
     */
    public function test_cancel_request_service_failure()
    {
        $rider = User::factory()->rider()->create();
        $rideRequest = $this->createTestRideRequest([
            'rider_id' => $rider->id,
            'status' => 'pending'
        ]);

        $this->mockRideService
            ->shouldReceive('cancelRideRequest')
            ->once()
            ->with($rideRequest->id, $rider->id)
            ->andReturn(false);

        $response = $this->actingAs($rider, 'sanctum')
                        ->patchJson("/api/v1/requests/{$rideRequest->id}/cancel");

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)
                ->assertJson([
                    'success' => false,
                    'message' => 'Unable to cancel ride request'
                ]);
    }

    /**
     * Test getting estimates with invalid data
     */
    public function test_get_estimates_with_invalid_data()
    {
        $rider = User::factory()->rider()->create();

        // Missing required fields
        $response = $this->actingAs($rider, 'sanctum')
                        ->getJson('/api/v1/requests/estimates');

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Invalid location IDs
        $response = $this->actingAs($rider, 'sanctum')
                        ->getJson('/api/v1/requests/estimates?' . http_build_query([
                            'pickup_location_id' => 99999,
                            'destination_location_id' => 99998,
                            'passenger_count' => 1,
                        ]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Test estimates service failure
     */
    public function test_estimates_service_failure()
    {
        $rider = User::factory()->rider()->create();
        $pickup = $this->createTestLocation('Test Pickup', true);
        $destination = $this->createTestLocation('Test Destination', false);

        $this->mockRideService
            ->shouldReceive('getRideEstimates')
            ->once()
            ->with($pickup->id, $destination->id, 1)
            ->andReturn(null);

        $response = $this->actingAs($rider, 'sanctum')
                        ->getJson('/api/v1/requests/estimates?' . http_build_query([
                            'pickup_location_id' => $pickup->id,
                            'destination_location_id' => $destination->id,
                            'passenger_count' => 1,
                        ]));

        $response->assertStatus(Response::HTTP_BAD_REQUEST)
                ->assertJson([
                    'success' => false,
                    'message' => 'Unable to calculate estimates'
                ]);
    }

    /**
     * Test concurrent request creation attempts
     */
    public function test_concurrent_request_creation()
    {
        $rider = User::factory()->rider()->create();
        $pickup = $this->createTestLocation('Test Pickup', true);
        $destination = $this->createTestLocation('Test Destination', false);

        // Create first request that should succeed
        $this->mockRideService
            ->shouldReceive('createRideRequest')
            ->once()
            ->andReturn($this->createTestRideRequest([
                'rider_id' => $rider->id,
                'pickup_location_id' => $pickup->id,
                'destination_location_id' => $destination->id,
            ]));

        $response1 = $this->actingAs($rider, 'sanctum')
                         ->postJson('/api/v1/requests', [
                             'pickup_location_id' => $pickup->id,
                             'destination_location_id' => $destination->id,
                             'passenger_count' => 1,
                         ]);

        $response1->assertStatus(Response::HTTP_CREATED);

        // Second request should fail due to existing active request
        $response2 = $this->actingAs($rider, 'sanctum')
                         ->postJson('/api/v1/requests', [
                             'pickup_location_id' => $pickup->id,
                             'destination_location_id' => $destination->id,
                             'passenger_count' => 1,
                         ]);

        $response2->assertStatus(Response::HTTP_BAD_REQUEST)
                  ->assertJson([
                      'success' => false,
                      'message' => 'You already have an active ride request'
                  ]);
    }

    // Helper methods

    private function createTestLocation(string $name, bool $isBeacon): Location
    {
        return Location::create([
            'name' => $name,
            'coordinates' => new Point(-6.3605 + rand(-100, 100) / 10000, 106.8271 + rand(-100, 100) / 10000, 4326),
            'is_beacon' => $isBeacon,
            'is_active' => true,
        ]);
    }

    private function createTestRideRequest(array $overrides = []): RideRequest
    {
        $pickup = $this->createTestLocation('Default Pickup', true);
        $destination = $this->createTestLocation('Default Destination', false);
        $rider = User::factory()->rider()->create();

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