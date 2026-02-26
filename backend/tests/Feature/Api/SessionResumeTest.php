<?php

namespace Tests\Feature\Api;

use App\Models\Location;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

class SessionResumeTest extends TestCase
{
    use RefreshDatabase;

    public function test_resume_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/session/resume')
            ->assertUnauthorized();
    }

    public function test_resume_returns_active_request_for_rider(): void
    {
        $rider = User::factory()->rider()->create();
        $request = $this->createRideRequestFor($rider);

        $response = $this->actingAs($rider, 'sanctum')
            ->getJson('/api/v1/session/resume');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'state' => 'request_pending',
                    'active_request' => ['id' => $request->id],
                    'active_ride' => null,
                ],
            ]);
    }

    public function test_resume_returns_active_ride_for_rider(): void
    {
        $ride = $this->createRideWithStatus('accepted');

        $response = $this->actingAs($ride->rider, 'sanctum')
            ->getJson('/api/v1/session/resume');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'state' => 'ride_active',
                    'ride_role' => 'rider',
                    'active_ride' => ['id' => $ride->id],
                ],
            ]);
    }

    public function test_resume_returns_driver_context_when_driver_has_active_ride(): void
    {
        $ride = $this->createRideWithStatus('driver_arrived');
        $driver = $ride->driver;
        $driver->driverProfile()->create([
            'student_email' => 'driver@example.com',
            'student_id' => 'A12345',
            'student_name' => $driver->name,
            'vehicle_type' => 'motorcycle',
            'vehicle_plate' => 'B1234XYZ',
            'vehicle_color' => 'Black',
            'is_verified' => true,
            'went_online_at' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($driver, 'sanctum')
            ->getJson('/api/v1/session/resume');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'state' => 'ride_active',
                    'ride_role' => 'driver',
                    'driver_context' => [
                        'is_online' => true,
                        'active_ride_id' => $ride->id,
                    ],
                ],
            ]);
    }

    public function test_resume_returns_error_when_token_user_missing(): void
    {
        $user = User::factory()->rider()->create();
        $token = $user->createToken('mobile-app')->plainTextToken;

        $user->delete();

        $response = $this->withToken($token)
            ->getJson('/api/v1/session/resume');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'AUTH_USER_NOT_FOUND',
            ]);
    }

    public function test_resume_returns_error_when_token_record_missing(): void
    {
        $user = User::factory()->rider()->create();
        $token = $user->createToken('mobile-app')->plainTextToken;

        $user->tokens()->delete();

        $response = $this->withToken($token)
            ->getJson('/api/v1/session/resume');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'AUTH_USER_NOT_FOUND',
            ]);
    }

    // ========================================
    // Expired Token Scenarios
    // ========================================

    public function test_resume_with_expired_token_returns_401_for_rider(): void
    {
        $rider = User::factory()->rider()->create();

        // Create token with past expiry
        $tokenModel = $rider->createToken('mobile-app', ['*'], now()->subDay());
        $token = $tokenModel->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/session/resume');

        $response->assertStatus(401);
    }

    public function test_resume_with_expired_token_returns_401_for_driver(): void
    {
        $driver = User::factory()->driver()->create();

        // Create token with past expiry
        $tokenModel = $driver->createToken('mobile-app', ['*'], now()->subHours(2));
        $token = $tokenModel->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/session/resume');

        $response->assertStatus(401);
    }

    public function test_resume_with_valid_token_after_expiry_threshold(): void
    {
        $rider = User::factory()->rider()->create();
        $request = $this->createRideRequestFor($rider);

        // Create token that expires in the future (valid)
        $tokenModel = $rider->createToken('mobile-app', ['*'], now()->addDays(7));

        $response = $this->actingAs($rider, 'sanctum')
            ->getJson('/api/v1/session/resume');

        // Should work fine with valid token
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'state' => 'request_pending',
                    'active_request' => ['id' => $request->id],
                ],
            ]);
    }

    // ========================================
    // Desync Scenarios
    // ========================================

    public function test_resume_handles_completed_ride_desync(): void
    {
        // Scenario: Client thinks ride is active, but server shows completed
        $ride = $this->createRideWithStatus('accepted');
        $rider = $ride->rider;

        // Complete the ride and its request on server
        $ride->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Also update the request status to prevent it from being returned
        $ride->rideRequest->update(['status' => 'completed']);

        $response = $this->actingAs($rider, 'sanctum')
            ->getJson('/api/v1/session/resume');

        // Should return idle state (no active ride or request)
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'state' => 'idle',
                    'active_ride' => null,
                    'active_request' => null,
                ],
            ]);
    }

    public function test_resume_handles_cancelled_ride_desync(): void
    {
        // Scenario: Client has ride reference, but server shows cancelled
        $ride = $this->createRideWithStatus('in_progress');
        $driver = $ride->driver;

        // Cancel the ride on server
        $ride->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => 'Rider cancelled',
        ]);

        $response = $this->actingAs($driver, 'sanctum')
            ->getJson('/api/v1/session/resume');

        // Should return idle state
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'state' => 'idle',
                    'active_ride' => null,
                ],
            ]);
    }

    public function test_resume_handles_expired_request_desync(): void
    {
        // Scenario: Client has request ID, but it expired on server
        $rider = User::factory()->rider()->create();
        $pickup = $this->createLocation('Expired Pickup');
        $destination = $this->createLocation('Expired Destination', false);

        // Create already-expired request
        $request = RideRequest::create([
            'rider_id' => $rider->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $destination->id,
            'estimated_distance_km' => 1.5,
            'estimated_duration_minutes' => 7,
            'estimated_fare_rp' => 9000,
            'passenger_count' => 1,
            'status' => 'pending',
            'expires_at' => now()->subMinutes(5), // Expired 5 minutes ago
        ]);

        $response = $this->actingAs($rider, 'sanctum')
            ->getJson('/api/v1/session/resume');

        // Should return idle (expired request not returned)
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'state' => 'idle',
                    'active_request' => null,
                ],
            ]);
    }

    public function test_resume_returns_most_recent_active_ride_if_multiple_exist(): void
    {
        // Edge case: Multiple rides in ACTIVE_STATUSES (shouldn't happen, but test it)
        $rider = User::factory()->rider()->create(['is_active' => true]);
        $driver1 = User::factory()->driver()->create(['is_active' => true]);
        $driver2 = User::factory()->driver()->create(['is_active' => true]);
        $pickup = $this->createLocation('Multi Pickup');
        $destination = $this->createLocation('Multi Destination', false);

        // Create first ride (older)
        $request1 = RideRequest::create([
            'rider_id' => $rider->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $destination->id,
            'estimated_distance_km' => 2.0,
            'estimated_duration_minutes' => 10,
            'estimated_fare_rp' => 10000,
            'passenger_count' => 1,
            'status' => 'matched',
            'expires_at' => now()->addMinutes(30),
        ]);

        $ride1 = Ride::create([
            'ride_request_id' => $request1->id,
            'rider_id' => $rider->id,
            'driver_id' => $driver1->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $destination->id,
            'status' => 'accepted',
            'passenger_count' => 1,
            'estimated_fare_rp' => 10000,
        ]);

        // Manually set older timestamp
        \DB::table('rides')->where('id', $ride1->id)->update([
            'updated_at' => now()->subMinutes(10),
        ]);

        // Wait a moment to ensure different timestamp
        sleep(1);

        // Create second ride (more recent)
        $request2 = RideRequest::create([
            'rider_id' => $rider->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $destination->id,
            'estimated_distance_km' => 2.0,
            'estimated_duration_minutes' => 10,
            'estimated_fare_rp' => 10000,
            'passenger_count' => 1,
            'status' => 'matched',
            'expires_at' => now()->addMinutes(30),
        ]);

        $ride2 = Ride::create([
            'ride_request_id' => $request2->id,
            'rider_id' => $rider->id,
            'driver_id' => $driver2->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $destination->id,
            'status' => 'in_progress',
            'passenger_count' => 1,
            'estimated_fare_rp' => 10000,
        ]);

        $response = $this->actingAs($rider, 'sanctum')
            ->getJson('/api/v1/session/resume');

        // Should return the most recent ride (ride2) based on updated_at
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'state' => 'ride_active',
                    'ride_role' => 'rider',
                ],
            ]);

        // Verify it's the most recent one by checking it's ride2
        $returnedRideId = $response->json('data.active_ride.id');
        $this->assertEquals($ride2->id, $returnedRideId,
            "Expected most recent ride (id: {$ride2->id}), but got ride id: {$returnedRideId}");
    }

    private function createRideRequestFor(User $rider): RideRequest
    {
        $pickup = $this->createLocation('Resume Pickup');
        $destination = $this->createLocation('Resume Destination', false);

        return RideRequest::create([
            'rider_id' => $rider->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $destination->id,
            'estimated_distance_km' => 1.5,
            'estimated_duration_minutes' => 7,
            'estimated_fare_rp' => 9000,
            'passenger_count' => 1,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(20),
        ]);
    }

    private function createRideWithStatus(string $status): Ride
    {
        $rider = User::factory()->rider()->create(['is_active' => true]);
        $driver = User::factory()->driver()->create(['is_active' => true]);
        $pickup = $this->createLocation('Active Pickup');
        $destination = $this->createLocation('Active Destination', false);

        $request = RideRequest::create([
            'rider_id' => $rider->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $destination->id,
            'estimated_distance_km' => 2.4,
            'estimated_duration_minutes' => 10,
            'estimated_fare_rp' => 12000,
            'passenger_count' => 1,
            'status' => 'matched',
            'expires_at' => now()->addMinutes(30),
        ]);

        $ride = Ride::create([
            'ride_request_id' => $request->id,
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $destination->id,
            'status' => 'accepted',
            'passenger_count' => 1,
            'estimated_fare_rp' => 12000,
        ]);

        $ride->update(['status' => $status]);

        return $ride->fresh(['rider', 'driver']);
    }

    private function createLocation(string $name, bool $isBeacon = true): Location
    {
        static $offset = 0;
        $offset += 0.001;

        return Location::create([
            'name' => $name,
            'coordinates' => new Point(-6.3 - $offset, 106.8 + $offset, 4326),
            'is_beacon' => $isBeacon,
            'is_active' => true,
        ]);
    }
}
