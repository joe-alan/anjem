<?php

namespace Tests\Unit\Services;

use App\Models\DriverQueue;
use App\Models\Location;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\User;
use App\Models\DriverProfile;
use App\Services\LocationService;
use App\Services\QueueService;
use App\Services\RideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Mockery;
use Tests\TestCase;

class RideServiceEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private RideService $rideService;
    private $mockLocationService;
    private $mockQueueService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockLocationService = Mockery::mock(LocationService::class);
        $this->mockQueueService = Mockery::mock(QueueService::class);

        $this->rideService = new RideService($this->mockLocationService, $this->mockQueueService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_accept_ride_request_with_non_existent_request()
    {
        $nonExistentId = 999999;
        $driverId = User::factory()->driver()->create()->id;

        $result = $this->rideService->acceptRideRequest($nonExistentId, $driverId);

        $this->assertNull($result);
    }

    public function test_accept_ride_request_with_expired_request()
    {
        $rideRequest = $this->createTestRideRequest([
            'status' => 'expired',
            'expires_at' => now()->subHours(1),
        ]);

        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver);

        $this->mockQueueService
            ->shouldReceive('getNextDriverAtBeacon')
            ->with($rideRequest->pickup_location_id)
            ->andReturn(null);

        $result = $this->rideService->acceptRideRequest($rideRequest->id, $driver->id);

        $this->assertNull($result);
    }

    public function test_accept_ride_request_with_already_matched_request()
    {
        $rideRequest = $this->createTestRideRequest([
            'status' => 'matched',
            'matched_at' => now()->subMinutes(5),
        ]);

        $driver = User::factory()->driver()->create();

        $this->mockQueueService
            ->shouldReceive('getNextDriverAtBeacon')
            ->with($rideRequest->pickup_location_id)
            ->andReturn(null);

        $result = $this->rideService->acceptRideRequest($rideRequest->id, $driver->id);

        $this->assertNull($result);
    }

    public function test_accept_ride_request_when_driver_matching_fails()
    {
        $rideRequest = $this->createTestRideRequest();
        $driver = User::factory()->driver()->create();

        $this->mockQueueService
            ->shouldReceive('getNextDriverAtBeacon')
            ->with($rideRequest->pickup_location_id)
            ->andReturn(null);

        $result = $this->rideService->acceptRideRequest($rideRequest->id, $driver->id);

        $this->assertNull($result);
    }

    public function test_accept_ride_request_with_wrong_driver_id()
    {
        $rideRequest = $this->createTestRideRequest();
        $correctDriver = User::factory()->driver()->create();
        $wrongDriver = User::factory()->driver()->create();

        $this->createDriverProfile($correctDriver);

        $queueEntry = DriverQueue::factory()->make([
            'driver_id' => $correctDriver->id,
            'beacon_id' => $rideRequest->pickup_location_id,
        ]);
        $queueEntry->setRelation('driver', $correctDriver);

        $this->mockQueueService
            ->shouldReceive('getNextDriverAtBeacon')
            ->with($rideRequest->pickup_location_id)
            ->andReturn($queueEntry);

        $this->mockQueueService
            ->shouldReceive('markDriverServed')
            ->with($correctDriver->id)
            ->once();

        $result = $this->rideService->acceptRideRequest($rideRequest->id, $wrongDriver->id);

        $this->assertNull($result);
    }

    public function test_successful_accept_ride_request()
    {
        $rideRequest = $this->createTestRideRequest();
        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver);

        $queueEntry = DriverQueue::factory()->make([
            'driver_id' => $driver->id,
            'beacon_id' => $rideRequest->pickup_location_id,
        ]);
        $queueEntry->setRelation('driver', $driver);

        $this->mockQueueService
            ->shouldReceive('getNextDriverAtBeacon')
            ->with($rideRequest->pickup_location_id)
            ->andReturn($queueEntry);

        $this->mockQueueService
            ->shouldReceive('markDriverServed')
            ->with($driver->id)
            ->once();

        $result = $this->rideService->acceptRideRequest($rideRequest->id, $driver->id);

        $this->assertNotNull($result);
        $this->assertInstanceOf(Ride::class, $result);
        $this->assertEquals($driver->id, $result->driver_id);
        $this->assertEquals($rideRequest->rider_id, $result->rider_id);
        $this->assertEquals('accepted', $result->status);
        $this->assertNotNull($result->driver_accepted_at);

        $rideRequest->refresh();
        $this->assertEquals('matched', $rideRequest->status);
        $this->assertNotNull($rideRequest->matched_at);
    }

    public function test_accept_ride_request_transaction_rollback_on_exception()
    {
        $rideRequest = $this->createTestRideRequest();
        $driver = User::factory()->driver()->create();

        $this->mockQueueService
            ->shouldReceive('getNextDriverAtBeacon')
            ->andThrow(new \Exception('Queue service error'));

        $result = $this->rideService->acceptRideRequest($rideRequest->id, $driver->id);

        $this->assertNull($result);

        $this->assertDatabaseMissing('rides', [
            'ride_request_id' => $rideRequest->id,
            'driver_id' => $driver->id,
        ]);

        $rideRequest->refresh();
        $this->assertEquals('pending', $rideRequest->status);
        $this->assertNull($rideRequest->matched_at);
    }

    public function test_accept_ride_request_with_concurrent_requests()
    {
        $rideRequest = $this->createTestRideRequest();
        $driver1 = User::factory()->driver()->create();
        $driver2 = User::factory()->driver()->create();

        $this->createDriverProfile($driver1);
        $this->createDriverProfile($driver2);

        $queueEntry1 = DriverQueue::factory()->make([
            'driver_id' => $driver1->id,
            'beacon_id' => $rideRequest->pickup_location_id,
        ]);
        $queueEntry1->setRelation('driver', $driver1);

        $this->mockQueueService
            ->shouldReceive('getNextDriverAtBeacon')
            ->twice()
            ->with($rideRequest->pickup_location_id)
            ->andReturn($queueEntry1);

        $this->mockQueueService
            ->shouldReceive('markDriverServed')
            ->with($driver1->id)
            ->once();

        $result1 = $this->rideService->acceptRideRequest($rideRequest->id, $driver1->id);
        $this->assertNotNull($result1);

        $result2 = $this->rideService->acceptRideRequest($rideRequest->id, $driver2->id);
        $this->assertNull($result2);

        $this->assertDatabaseCount('rides', 1);
    }

    public function test_accept_ride_request_performance()
    {
        $startTime = microtime(true);

        $rideRequest = $this->createTestRideRequest();
        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver);

        $queueEntry = DriverQueue::factory()->make([
            'driver_id' => $driver->id,
            'beacon_id' => $rideRequest->pickup_location_id,
        ]);
        $queueEntry->setRelation('driver', $driver);

        $this->mockQueueService
            ->shouldReceive('getNextDriverAtBeacon')
            ->with($rideRequest->pickup_location_id)
            ->andReturn($queueEntry);

        $this->mockQueueService
            ->shouldReceive('markDriverServed')
            ->with($driver->id)
            ->once();

        $result = $this->rideService->acceptRideRequest($rideRequest->id, $driver->id);

        $executionTime = microtime(true) - $startTime;

        $this->assertNotNull($result);
        $this->assertLessThan(1.0, $executionTime, 'acceptRideRequest should complete in under 1 second');
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

    private function createDriverProfile(User $user, array $overrides = []): DriverProfile
    {
        return DriverProfile::create(array_merge([
            'user_id' => $user->id,
            'reliability_score' => 85,
            'on_time_rate' => 0.80,
            'experience_points' => 300,
            'total_rides_given' => 50,
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