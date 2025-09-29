<?php

namespace Tests\Unit\Services;

use App\Models\DriverQueue;
use App\Models\Location;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\User;
use App\Services\LocationService;
use App\Services\QueueService;
use App\Services\RideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

class RideServiceTest extends TestCase
{
    use RefreshDatabase;

    private RideService $rideService;
    private LocationService $locationService;
    private QueueService $queueService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->locationService = new LocationService();
        $this->queueService = new QueueService();
        $this->rideService = new RideService($this->locationService, $this->queueService);
    }

    /**
     * Test creating ride request with valid data
     */
    public function test_can_create_ride_request_with_valid_data()
    {
        $rider = User::factory()->rider()->create(['is_active' => true]);
        $pickup = Location::create([
            'name' => 'Library Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $requestData = [
            'rider_id' => $rider->id,
            'pickup_location_id' => $pickup->id,
            'destination_latitude' => -6.3650,
            'destination_longitude' => 106.8300,
            'destination_name' => 'Student Dormitory',
            'destination_address' => 'UI Student Housing',
            'passenger_count' => 1,
        ];

        $rideRequest = $this->rideService->createRideRequest($requestData);

        $this->assertNotNull($rideRequest);
        $this->assertEquals($rider->id, $rideRequest->rider_id);
        $this->assertEquals($pickup->id, $rideRequest->pickup_location_id);
        $this->assertEquals('pending', $rideRequest->status);
        $this->assertEquals(1, $rideRequest->passenger_count);
        $this->assertNotNull($rideRequest->destination_location_id);
        $this->assertGreaterThan(0, $rideRequest->estimated_fare_rp);
        $this->assertGreaterThan(0, $rideRequest->estimated_distance_km);
    }

    /**
     * Test creating ride request with coordinates instead of location IDs
     */
    public function test_can_create_ride_request_with_coordinates()
    {
        $rider = User::factory()->rider()->create(['is_active' => true]);

        // Create a beacon for pickup resolution
        Location::create([
            'name' => 'Closest Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $requestData = [
            'rider_id' => $rider->id,
            'pickup_latitude' => -6.3605,
            'pickup_longitude' => 106.8271,
            'destination_latitude' => -6.3650,
            'destination_longitude' => 106.8300,
            'destination_name' => 'Custom Destination',
            'destination_address' => 'User Location',
            'passenger_count' => 2,
        ];

        $rideRequest = $this->rideService->createRideRequest($requestData);

        $this->assertNotNull($rideRequest);
        $this->assertEquals(2, $rideRequest->passenger_count);
        $this->assertNotNull($rideRequest->pickup_location_id);
        $this->assertNotNull($rideRequest->destination_location_id);
    }

    /**
     * Test ride request creation fails with inactive rider
     */
    public function test_ride_request_fails_with_inactive_rider()
    {
        $rider = User::factory()->rider()->create(['is_active' => false]);
        $pickup = Location::create([
            'name' => 'Library Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $requestData = [
            'rider_id' => $rider->id,
            'pickup_location_id' => $pickup->id,
            'destination_latitude' => -6.3650,
            'destination_longitude' => 106.8300,
        ];

        $rideRequest = $this->rideService->createRideRequest($requestData);

        $this->assertNull($rideRequest);
    }

    /**
     * Test ride request creation fails with non-existent rider
     */
    public function test_ride_request_fails_with_non_existent_rider()
    {
        $pickup = Location::create([
            'name' => 'Library Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $requestData = [
            'rider_id' => 999,
            'pickup_location_id' => $pickup->id,
            'destination_latitude' => -6.3650,
            'destination_longitude' => 106.8300,
        ];

        $rideRequest = $this->rideService->createRideRequest($requestData);

        $this->assertNull($rideRequest);
    }

    /**
     * Test ride request creation fails with invalid pickup location
     */
    public function test_ride_request_fails_with_invalid_pickup_location()
    {
        $rider = User::factory()->rider()->create(['is_active' => true]);

        $requestData = [
            'rider_id' => $rider->id,
            'pickup_location_id' => 999,
            'destination_latitude' => -6.3650,
            'destination_longitude' => 106.8300,
        ];

        $rideRequest = $this->rideService->createRideRequest($requestData);

        $this->assertNull($rideRequest);
    }

    /**
     * Test fare calculation for different scenarios
     */
    public function test_fare_calculation_scenarios()
    {
        // Test minimum fare
        $estimates1 = $this->rideService->calculateRideEstimates(-6.3605, 106.8271, -6.3606, 106.8272, 1);
        $this->assertEquals(5000, $estimates1['fare_rp']); // Minimum fare

        // Test distance-based fare (1km+ distance)
        $estimates2 = $this->rideService->calculateRideEstimates(-6.3605, 106.8271, -6.3700, 106.8400, 1);
        $this->assertGreaterThan(5000, $estimates2['fare_rp']);
        $this->assertLessThanOrEqual(50000, $estimates2['fare_rp']); // Maximum fare

        // Test passenger multiplier (same coordinates as estimates2)
        $estimates3 = $this->rideService->calculateRideEstimates(-6.3605, 106.8271, -6.3700, 106.8400, 3);
        $this->assertGreaterThan($estimates2['fare_rp'], $estimates3['fare_rp']);

        // Test maximum fare cap
        $estimates4 = $this->rideService->calculateRideEstimates(-6.3605, 106.8271, -6.5000, 107.0000, 1);
        $this->assertEquals(50000, $estimates4['fare_rp']); // Capped at maximum
    }

    /**
     * Test driver matching for ride request
     */
    public function test_can_match_driver_to_ride_request()
    {
        $rider = User::factory()->rider()->create(['is_active' => true]);
        $driver = User::factory()->driver()->create(['is_active' => true]);

        $beacon = Location::create([
            'name' => 'Library Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        // Driver joins queue
        $this->queueService->joinQueue($driver->id, $beacon->id);

        // Create ride request
        $rideRequest = RideRequest::create([
            'rider_id' => $rider->id,
            'pickup_location_id' => $beacon->id,
            'destination_location_id' => $beacon->id, // For simplicity
            'estimated_distance_km' => 1.0,
            'estimated_duration_minutes' => 5,
            'estimated_fare_rp' => 8000,
            'passenger_count' => 1,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);

        $ride = $this->rideService->matchDriver($rideRequest->id);

        $this->assertNotNull($ride);
        $this->assertEquals($rider->id, $ride->rider_id);
        $this->assertEquals($driver->id, $ride->driver_id);
        $this->assertEquals('matched', $ride->status);

        // Check that ride request is marked as matched
        $rideRequest->refresh();
        $this->assertEquals('matched', $rideRequest->status);
    }

    /**
     * Test driver matching fails when no drivers available
     */
    public function test_driver_matching_fails_when_no_drivers_available()
    {
        $rider = User::factory()->rider()->create(['is_active' => true]);
        $beacon = Location::create([
            'name' => 'Library Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $rideRequest = RideRequest::create([
            'rider_id' => $rider->id,
            'pickup_location_id' => $beacon->id,
            'destination_location_id' => $beacon->id,
            'estimated_distance_km' => 1.0,
            'estimated_duration_minutes' => 5,
            'estimated_fare_rp' => 8000,
            'passenger_count' => 1,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);

        $ride = $this->rideService->matchDriver($rideRequest->id);

        $this->assertNull($ride);

        // Check that ride request is still pending
        $rideRequest->refresh();
        $this->assertEquals('pending', $rideRequest->status);
    }

    /**
     * Test driver matching fails with expired ride request
     */
    public function test_driver_matching_fails_with_expired_ride_request()
    {
        $rider = User::factory()->rider()->create(['is_active' => true]);
        $driver = User::factory()->driver()->create(['is_active' => true]);

        $beacon = Location::create([
            'name' => 'Library Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $this->queueService->joinQueue($driver->id, $beacon->id);

        $rideRequest = RideRequest::create([
            'rider_id' => $rider->id,
            'pickup_location_id' => $beacon->id,
            'destination_location_id' => $beacon->id,
            'estimated_distance_km' => 1.0,
            'estimated_duration_minutes' => 5,
            'estimated_fare_rp' => 8000,
            'passenger_count' => 1,
            'status' => 'pending',
            'expires_at' => now()->subMinutes(10), // Expired
        ]);

        $ride = $this->rideService->matchDriver($rideRequest->id);

        $this->assertNull($ride);
    }

    /**
     * Test accepting a ride by driver
     */
    public function test_driver_can_accept_ride()
    {
        $ride = $this->createMatchedRide();

        $result = $this->rideService->acceptRide($ride->id, $ride->driver_id);

        $this->assertTrue($result);
        $ride->refresh();
        $this->assertEquals('accepted', $ride->status);
        $this->assertNotNull($ride->driver_accepted_at);
    }

    /**
     * Test accepting ride fails with wrong driver
     */
    public function test_accept_ride_fails_with_wrong_driver()
    {
        $ride = $this->createMatchedRide();
        $wrongDriver = User::factory()->driver()->create();

        $result = $this->rideService->acceptRide($ride->id, $wrongDriver->id);

        $this->assertFalse($result);
        $ride->refresh();
        $this->assertEquals('matched', $ride->status);
    }

    /**
     * Test accepting ride fails with wrong status
     */
    public function test_accept_ride_fails_with_wrong_status()
    {
        $ride = $this->createMatchedRide();
        $ride->update(['status' => 'completed']);

        $result = $this->rideService->acceptRide($ride->id, $ride->driver_id);

        $this->assertFalse($result);
    }

    /**
     * Test starting a ride
     */
    public function test_driver_can_start_ride()
    {
        $ride = $this->createAcceptedRide();

        $result = $this->rideService->startRide($ride->id, $ride->driver_id);

        $this->assertTrue($result);
        $ride->refresh();
        $this->assertEquals('in_progress', $ride->status);
        $this->assertNotNull($ride->pickup_time);
    }

    /**
     * Test starting ride fails with wrong status
     */
    public function test_start_ride_fails_with_wrong_status()
    {
        $ride = $this->createMatchedRide(); // Not accepted

        $result = $this->rideService->startRide($ride->id, $ride->driver_id);

        $this->assertFalse($result);
    }

    /**
     * Test completing a ride
     */
    public function test_driver_can_complete_ride()
    {
        $ride = $this->createInProgressRide();

        $result = $this->rideService->completeRide($ride->id, $ride->driver_id, [
            'actual_distance_km' => 2.5,
            'actual_fare_rp' => 9000,
        ]);

        $this->assertTrue($result);
        $ride->refresh();
        $this->assertEquals('completed', $ride->status);
        $this->assertNotNull($ride->dropoff_time);
        $this->assertEquals(2.5, $ride->actual_distance_km);
        $this->assertEquals(9000, $ride->actual_fare_rp);
        $this->assertNotNull($ride->actual_duration_minutes);
    }

    /**
     * Test completing ride without actual data uses estimates
     */
    public function test_complete_ride_uses_estimates_when_no_actual_data()
    {
        $ride = $this->createInProgressRide();

        $result = $this->rideService->completeRide($ride->id, $ride->driver_id);

        $this->assertTrue($result);
        $ride->refresh();
        $this->assertEquals('completed', $ride->status);
        $this->assertEquals($ride->estimated_fare_rp, $ride->actual_fare_rp);
    }

    /**
     * Test cancelling ride request by rider
     */
    public function test_rider_can_cancel_ride_request()
    {
        $rider = User::factory()->rider()->create(['is_active' => true]);
        $beacon = Location::create([
            'name' => 'Library Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $rideRequest = RideRequest::create([
            'rider_id' => $rider->id,
            'pickup_location_id' => $beacon->id,
            'destination_location_id' => $beacon->id,
            'estimated_distance_km' => 1.0,
            'estimated_duration_minutes' => 5,
            'estimated_fare_rp' => 8000,
            'passenger_count' => 1,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);

        $result = $this->rideService->cancelRideRequest($rideRequest->id, $rider->id);

        $this->assertTrue($result);
        $rideRequest->refresh();
        $this->assertEquals('cancelled', $rideRequest->status);
    }

    /**
     * Test cancelling ride request fails with wrong rider
     */
    public function test_cancel_ride_request_fails_with_wrong_rider()
    {
        $rider = User::factory()->rider()->create(['is_active' => true]);
        $wrongRider = User::factory()->rider()->create();
        $beacon = Location::create([
            'name' => 'Library Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $rideRequest = RideRequest::create([
            'rider_id' => $rider->id,
            'pickup_location_id' => $beacon->id,
            'destination_location_id' => $beacon->id,
            'estimated_distance_km' => 1.0,
            'estimated_duration_minutes' => 5,
            'estimated_fare_rp' => 8000,
            'passenger_count' => 1,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);

        $result = $this->rideService->cancelRideRequest($rideRequest->id, $wrongRider->id);

        $this->assertFalse($result);
    }

    /**
     * Test cancelling active ride
     */
    public function test_can_cancel_active_ride()
    {
        $ride = $this->createAcceptedRide();

        $result = $this->rideService->cancelRide($ride->id, $ride->rider_id, 'Changed my mind');

        $this->assertTrue($result);
        $ride->refresh();
        $this->assertEquals('cancelled', $ride->status);
    }

    /**
     * Test cancelling completed ride fails
     */
    public function test_cannot_cancel_completed_ride()
    {
        $ride = $this->createCompletedRide();

        $result = $this->rideService->cancelRide($ride->id, $ride->rider_id);

        $this->assertFalse($result);
    }

    /**
     * Test getting active ride for user
     */
    public function test_can_get_active_ride_for_user()
    {
        $ride = $this->createAcceptedRide();

        $activeRideForRider = $this->rideService->getActiveRide($ride->rider_id);
        $activeRideForDriver = $this->rideService->getActiveRide($ride->driver_id);

        $this->assertNotNull($activeRideForRider);
        $this->assertNotNull($activeRideForDriver);
        $this->assertEquals($ride->id, $activeRideForRider->id);
        $this->assertEquals($ride->id, $activeRideForDriver->id);
    }

    /**
     * Test getting active ride returns null when no active ride
     */
    public function test_get_active_ride_returns_null_when_none()
    {
        $user = User::factory()->rider()->create();

        $activeRide = $this->rideService->getActiveRide($user->id);

        $this->assertNull($activeRide);
    }

    /**
     * Test getting ride history
     */
    public function test_can_get_ride_history()
    {
        $user = User::factory()->rider()->create();
        $completedRide = $this->createCompletedRide(['rider_id' => $user->id]);
        $cancelledRide = $this->createCancelledRide(['rider_id' => $user->id]);

        $history = $this->rideService->getRideHistory($user->id);

        $this->assertCount(2, $history);
        $this->assertTrue($history->contains('id', $completedRide->id));
        $this->assertTrue($history->contains('id', $cancelledRide->id));
    }

    /**
     * Test cleaning up expired ride requests
     */
    public function test_can_cleanup_expired_ride_requests()
    {
        $rider = User::factory()->rider()->create(['is_active' => true]);
        $beacon = Location::create([
            'name' => 'Library Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        // Create expired request
        $expiredRequest = RideRequest::create([
            'rider_id' => $rider->id,
            'pickup_location_id' => $beacon->id,
            'destination_location_id' => $beacon->id,
            'estimated_distance_km' => 1.0,
            'estimated_duration_minutes' => 5,
            'estimated_fare_rp' => 8000,
            'passenger_count' => 1,
            'status' => 'pending',
            'expires_at' => now()->subMinutes(10),
        ]);

        // Create active request
        $activeRequest = RideRequest::create([
            'rider_id' => $rider->id,
            'pickup_location_id' => $beacon->id,
            'destination_location_id' => $beacon->id,
            'estimated_distance_km' => 1.0,
            'estimated_duration_minutes' => 5,
            'estimated_fare_rp' => 8000,
            'passenger_count' => 1,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);

        $expiredCount = $this->rideService->cleanupExpiredRequests();

        $this->assertEquals(1, $expiredCount);
        $expiredRequest->refresh();
        $activeRequest->refresh();
        $this->assertEquals('expired', $expiredRequest->status);
        $this->assertEquals('pending', $activeRequest->status);
    }

    /**
     * Test getting ride statistics
     */
    public function test_can_get_ride_statistics()
    {
        // Create some rides for today
        $completedRide = $this->createCompletedRide(['created_at' => now()]);
        $cancelledRide = $this->createCancelledRide(['created_at' => now()]);
        $activeRide = $this->createAcceptedRide(['created_at' => now()]);

        // Create pending request
        $rider = User::factory()->rider()->create(['is_active' => true]);
        $beacon = Location::create([
            'name' => 'Library Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        RideRequest::create([
            'rider_id' => $rider->id,
            'pickup_location_id' => $beacon->id,
            'destination_location_id' => $beacon->id,
            'estimated_distance_km' => 1.0,
            'estimated_duration_minutes' => 5,
            'estimated_fare_rp' => 8000,
            'passenger_count' => 1,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);

        $stats = $this->rideService->getRideStatistics();

        $this->assertArrayHasKey('today', $stats);
        $this->assertArrayHasKey('all_time', $stats);
        $this->assertEquals(1, $stats['today']['completed_rides']);
        $this->assertEquals(1, $stats['today']['cancelled_rides']);
        $this->assertEquals(1, $stats['today']['active_rides']);
        $this->assertEquals(1, $stats['today']['pending_requests']);
    }

    /**
     * Test edge case: matching driver with non-beacon pickup
     */
    public function test_driver_matching_fails_with_non_beacon_pickup()
    {
        $rider = User::factory()->rider()->create(['is_active' => true]);
        $driver = User::factory()->driver()->create(['is_active' => true]);

        $destination = Location::create([
            'name' => 'P2P Destination',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => false, // Not a beacon
            'is_active' => true,
        ]);

        $rideRequest = RideRequest::create([
            'rider_id' => $rider->id,
            'pickup_location_id' => $destination->id, // Non-beacon pickup
            'destination_location_id' => $destination->id,
            'estimated_distance_km' => 1.0,
            'estimated_duration_minutes' => 5,
            'estimated_fare_rp' => 8000,
            'passenger_count' => 1,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);

        $ride = $this->rideService->matchDriver($rideRequest->id);

        $this->assertNull($ride); // Should fail for MVP
    }

    /**
     * Test edge case: extreme passenger count
     */
    public function test_fare_calculation_with_extreme_passenger_count()
    {
        $estimates = $this->rideService->calculateRideEstimates(-6.3605, 106.8271, -6.3650, 106.8300, 10);

        // For 10 passengers, should apply 2.8x multiplier
        $this->assertGreaterThan(8000, $estimates['fare_rp']);
        $this->assertEquals(12723, $estimates['fare_rp']); // Actual calculated value
    }

    /**
     * Test that fare is capped at maximum
     */
    public function test_fare_calculation_maximum_cap()
    {
        // Use a very long distance with many passengers to hit the cap
        $estimates = $this->rideService->calculateRideEstimates(-6.3605, 106.8271, -6.4500, 106.9500, 10);

        // Should be capped at maximum fare
        $this->assertEquals(50000, $estimates['fare_rp']);
    }

    /**
     * Test edge case: zero distance ride
     */
    public function test_fare_calculation_with_zero_distance()
    {
        $estimates = $this->rideService->calculateRideEstimates(-6.3605, 106.8271, -6.3605, 106.8271, 1);

        // Should still charge minimum fare
        $this->assertEquals(5000, $estimates['fare_rp']);
    }

    // Helper methods

    private function createMatchedRide(array $overrides = []): Ride
    {
        $rider = User::factory()->rider()->create(['is_active' => true]);
        $driver = User::factory()->driver()->create(['is_active' => true]);
        $beacon = Location::create([
            'name' => 'Test Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        // Create a ride request first
        $rideRequest = RideRequest::create([
            'rider_id' => $rider->id,
            'pickup_location_id' => $beacon->id,
            'destination_location_id' => $beacon->id,
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
            'pickup_location_id' => $beacon->id,
            'destination_location_id' => $beacon->id,
            'status' => 'matched',
            'passenger_count' => 1,
            'estimated_fare_rp' => 8000,
        ], $overrides));
    }

    private function createAcceptedRide(array $overrides = []): Ride
    {
        $ride = $this->createMatchedRide($overrides);
        $ride->update([
            'status' => 'accepted',
            'driver_accepted_at' => now(),
        ]);
        return $ride;
    }

    private function createInProgressRide(array $overrides = []): Ride
    {
        $ride = $this->createAcceptedRide($overrides);
        $ride->update([
            'status' => 'in_progress',
            'pickup_time' => now(),
        ]);
        return $ride;
    }

    private function createCompletedRide(array $overrides = []): Ride
    {
        $ride = $this->createInProgressRide($overrides);
        $ride->update([
            'status' => 'completed',
            'dropoff_time' => now(),
            'actual_fare_rp' => 8000,
        ]);
        return $ride;
    }

    private function createCancelledRide(array $overrides = []): Ride
    {
        $ride = $this->createAcceptedRide($overrides);
        $ride->update(['status' => 'cancelled']);
        return $ride;
    }
}