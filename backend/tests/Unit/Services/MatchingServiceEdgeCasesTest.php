<?php

namespace Tests\Unit\Services;

use App\Models\Location;
use App\Models\RideRequest;
use App\Models\User;
use App\Models\DriverProfile;
use App\Services\MatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

class MatchingServiceEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private MatchingService $matchingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matchingService = new MatchingService();
    }

    public function test_find_best_driver_with_no_available_drivers()
    {
        $rideRequest = $this->createTestRideRequest();

        $result = $this->matchingService->findBestDriver($rideRequest);

        $this->assertNull($result);
    }

    public function test_find_best_driver_with_inactive_users()
    {
        $rideRequest = $this->createTestRideRequest();

        $inactiveDriver = User::factory()->create([
            'user_type' => 'driver',
            'is_active' => false,
        ]);
        $this->createDriverProfile($inactiveDriver);

        $result = $this->matchingService->findBestDriver($rideRequest);

        $this->assertNull($result);
    }

    public function test_find_best_driver_with_users_without_driver_profiles()
    {
        $rideRequest = $this->createTestRideRequest();

        User::factory()->create([
            'user_type' => 'rider',
            'is_active' => true,
        ]);

        $result = $this->matchingService->findBestDriver($rideRequest);

        $this->assertNull($result);
    }

    public function test_find_best_driver_with_drivers_having_active_rides()
    {
        $rideRequest = $this->createTestRideRequest();

        $busyDriver = User::factory()->create([
            'user_type' => 'driver',
            'is_active' => true,
        ]);
        $this->createDriverProfile($busyDriver);

        $activeRide = $this->createTestRide(['driver_id' => $busyDriver->id, 'status' => 'in_progress']);

        $result = $this->matchingService->findBestDriver($rideRequest);

        $this->assertNull($result);
    }

    public function test_find_best_driver_with_available_driver()
    {
        $rideRequest = $this->createTestRideRequest();

        $availableDriver = User::factory()->driver()->create();
        $this->createDriverProfile($availableDriver, [
            'reliability_score' => 95,
            'on_time_rate' => 0.85,
            'experience_points' => 500,
            'went_online_at' => now()->subHours(2),
        ]);

        $result = $this->matchingService->findBestDriver($rideRequest);

        $this->assertNotNull($result);
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($availableDriver->id, $result->id);
        $this->assertTrue($result->isDriver());
    }

    public function test_calculate_driver_score_with_missing_profile_data()
    {
        $rideRequest = $this->createTestRideRequest();

        $driver = User::factory()->create([
            'user_type' => 'driver',
            'is_active' => true,
        ]);

        $this->createDriverProfile($driver, [
            'reliability_score' => null,
            'on_time_rate' => null,
            'experience_points' => null,
            'went_online_at' => null,
        ]);

        $result = $this->matchingService->findBestDriver($rideRequest);

        $this->assertNotNull($result);
        $this->assertEquals($driver->id, $result->id);
    }

    public function test_driver_scoring_algorithm_with_multiple_drivers()
    {
        $rideRequest = $this->createTestRideRequest();

        $highScoreDriver = User::factory()->create([
            'user_type' => 'driver',
            'is_active' => true,
        ]);
        $this->createDriverProfile($highScoreDriver, [
            'reliability_score' => 98,
            'on_time_rate' => 0.95,
            'experience_points' => 1000,
            'went_online_at' => now()->subMinutes(30),
        ]);

        $lowScoreDriver = User::factory()->create([
            'user_type' => 'driver',
            'is_active' => true,
        ]);
        $this->createDriverProfile($lowScoreDriver, [
            'reliability_score' => 60,
            'on_time_rate' => 0.70,
            'experience_points' => 100,
            'went_online_at' => now()->subHours(8),
        ]);

        $result = $this->matchingService->findBestDriver($rideRequest);

        $this->assertNotNull($result);
        $this->assertEquals($highScoreDriver->id, $result->id);
    }

    public function test_match_request_with_valid_ride_request()
    {
        $rideRequest = $this->createTestRideRequest(['status' => 'pending']);

        $driver = User::factory()->create([
            'user_type' => 'driver',
            'is_active' => true,
        ]);
        $this->createDriverProfile($driver);

        $result = $this->matchingService->matchRequest($rideRequest);

        $this->assertTrue($result);

        $rideRequest->refresh();
        $this->assertEquals('matched', $rideRequest->status);
        $this->assertEquals($driver->id, $rideRequest->matched_driver_id);
        $this->assertNotNull($rideRequest->matched_at);
    }

    public function test_match_request_with_expired_ride_request()
    {
        $rideRequest = $this->createTestRideRequest([
            'status' => 'expired',
            'expires_at' => now()->subHours(1),
        ]);

        $driver = User::factory()->create([
            'user_type' => 'driver',
            'is_active' => true,
        ]);
        $this->createDriverProfile($driver);

        $result = $this->matchingService->matchRequest($rideRequest);

        $this->assertFalse($result);

        $rideRequest->refresh();
        $this->assertEquals('expired', $rideRequest->status);
        $this->assertNull($rideRequest->matched_driver_id);
    }

    public function test_driver_score_with_very_high_experience_points()
    {
        $rideRequest = $this->createTestRideRequest();

        $driver = User::factory()->create([
            'user_type' => 'driver',
            'is_active' => true,
        ]);

        $this->createDriverProfile($driver, [
            'reliability_score' => 80,
            'on_time_rate' => 0.80,
            'experience_points' => 5000,
            'went_online_at' => now()->subHours(1),
        ]);

        $result = $this->matchingService->findBestDriver($rideRequest);

        $this->assertNotNull($result);
        $this->assertEquals($driver->id, $result->id);
    }

    private function createTestRideRequest(array $overrides = []): RideRequest
    {
        $rider = User::factory()->rider()->create();
        $pickup = $this->createTestBeacon();
        $destination = $this->createTestLocation();

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

    private function createTestRide(array $overrides = []): \App\Models\Ride
    {
        $rider = User::factory()->rider()->create();
        $driver = User::factory()->driver()->create();
        $pickup = $this->createTestBeacon();
        $destination = $this->createTestLocation();

        $rideRequest = $this->createTestRideRequest([
            'rider_id' => $rider->id,
        ]);

        return \App\Models\Ride::create(array_merge([
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

    private function createDriverProfile(User $user, array $overrides = []): DriverProfile
    {
        return DriverProfile::create(array_merge([
            'user_id' => $user->id,
            'reliability_score' => 85,
            'on_time_rate' => 0.80,
            'experience_points' => 300,
            'total_rides_given' => 50,
            'went_online_at' => now()->subHour(),
        ], $overrides));
    }

    private function createTestBeacon(): Location
    {
        return Location::create([
            'name' => 'Test Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);
    }

    private function createTestLocation(): Location
    {
        return Location::create([
            'name' => 'Test Destination',
            'coordinates' => new Point(-6.3650, 106.8300, 4326),
            'is_beacon' => false,
            'is_active' => true,
        ]);
    }
}