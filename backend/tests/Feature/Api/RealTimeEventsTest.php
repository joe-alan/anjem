<?php

namespace Tests\Feature\Api;

use App\Events\DriverLocationUpdated;
use App\Events\DriverOnlineStatusChanged;
use App\Events\NewRideRequest;
use App\Events\QueuePositionChanged;
use App\Events\RideRequestMatched;
use App\Events\RideStatusUpdated;
use App\Models\Location;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

/**
 * Test real-time event broadcasting functionality
 */
class RealTimeEventsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that RideStatusUpdated event can be created and broadcasted
     */
    public function test_ride_status_updated_event_broadcasts()
    {
        Event::fake([RideStatusUpdated::class]);

        $rider = User::factory()->rider()->create();
        $driver = User::factory()->driver()->create();
        $ride = $this->createTestRide(['rider_id' => $rider->id, 'driver_id' => $driver->id]);

        $event = new RideStatusUpdated($ride, 'pending', 'driver');

        // Test event data structure
        $broadcastData = $event->broadcastWith();

        $this->assertArrayHasKey('ride_id', $broadcastData);
        $this->assertArrayHasKey('status', $broadcastData);
        $this->assertArrayHasKey('previous_status', $broadcastData);
        $this->assertArrayHasKey('updated_by', $broadcastData);
        $this->assertEquals($ride->id, $broadcastData['ride_id']);
        $this->assertEquals('pending', $broadcastData['previous_status']);
        $this->assertEquals('driver', $broadcastData['updated_by']);

        // Test broadcast channels
        $channels = $event->broadcastOn();
        $this->assertCount(3, $channels);

        // Verify channel names contain correct IDs
        $channelNames = array_map(fn ($channel) => $channel->name, $channels);
        $this->assertContains("private-ride.{$ride->id}", $channelNames);
        $this->assertContains("private-user.{$rider->id}", $channelNames);
        $this->assertContains("private-user.{$driver->id}", $channelNames);

        // Test broadcast name
        $this->assertEquals('ride.status.updated', $event->broadcastAs());
    }

    /**
     * Test that DriverLocationUpdated event broadcasts correctly
     */
    public function test_driver_location_updated_event_broadcasts()
    {
        Event::fake([DriverLocationUpdated::class]);

        $driver = User::factory()->driver()->create();
        $ride = $this->createTestRide(['driver_id' => $driver->id]);

        $event = new DriverLocationUpdated(
            $driver,
            -6.3605,
            106.8271,
            $ride->id,
            180.0,
            25.0
        );

        // Test event data structure
        $broadcastData = $event->broadcastWith();

        $this->assertArrayHasKey('driver_id', $broadcastData);
        $this->assertArrayHasKey('latitude', $broadcastData);
        $this->assertArrayHasKey('longitude', $broadcastData);
        $this->assertArrayHasKey('active_ride_id', $broadcastData);
        $this->assertEquals($driver->id, $broadcastData['driver_id']);
        $this->assertEquals(-6.3605, $broadcastData['latitude']);
        $this->assertEquals(106.8271, $broadcastData['longitude']);
        $this->assertEquals($ride->id, $broadcastData['active_ride_id']);

        // Test broadcast channels include ride channel for active ride
        $channels = $event->broadcastOn();
        $channelNames = array_map(fn ($channel) => $channel->name, $channels);
        $this->assertContains("private-driver.{$driver->id}", $channelNames);
        $this->assertContains("private-ride.{$ride->id}", $channelNames);

        // Test broadcast name
        $this->assertEquals('driver.location.updated', $event->broadcastAs());
    }

    /**
     * Test that QueuePositionChanged event broadcasts correctly
     */
    public function test_queue_position_changed_event_broadcasts()
    {
        Event::fake([QueuePositionChanged::class]);

        $driver = User::factory()->driver()->create();
        $beacon = $this->createTestBeacon();

        $event = new QueuePositionChanged(
            $driver,
            $beacon,
            2, // position
            5, // total drivers
            15, // estimated wait
            'joined'
        );

        // Test event data structure
        $broadcastData = $event->broadcastWith();

        $this->assertArrayHasKey('driver_id', $broadcastData);
        $this->assertArrayHasKey('beacon_id', $broadcastData);
        $this->assertArrayHasKey('position', $broadcastData);
        $this->assertArrayHasKey('total_drivers', $broadcastData);
        $this->assertArrayHasKey('action', $broadcastData);
        $this->assertEquals($driver->id, $broadcastData['driver_id']);
        $this->assertEquals($beacon->id, $broadcastData['beacon_id']);
        $this->assertEquals(2, $broadcastData['position']);
        $this->assertEquals('joined', $broadcastData['action']);

        // Test broadcast channels include both private and public channels
        $channels = $event->broadcastOn();
        $channelNames = array_map(fn ($channel) => $channel->name, $channels);
        $this->assertContains("private-user.{$driver->id}", $channelNames);
        $this->assertContains("private-driver.{$driver->id}", $channelNames);
        $this->assertContains("beacon.{$beacon->id}", $channelNames); // Public channel, no prefix

        // Test broadcast name
        $this->assertEquals('queue.position.changed', $event->broadcastAs());
    }

    /**
     * Test that RideRequestMatched event broadcasts correctly
     */
    public function test_ride_request_matched_event_broadcasts()
    {
        Event::fake([RideRequestMatched::class]);

        $rideRequest = $this->createTestRideRequest();
        $ride = $this->createTestRide(['rider_id' => $rideRequest->rider_id]);

        $event = new RideRequestMatched($rideRequest, $ride, 8);

        // Test event data structure
        $broadcastData = $event->broadcastWith();

        $this->assertArrayHasKey('ride_request_id', $broadcastData);
        $this->assertArrayHasKey('ride_id', $broadcastData);
        $this->assertArrayHasKey('rider_id', $broadcastData);
        $this->assertArrayHasKey('driver_id', $broadcastData);
        $this->assertArrayHasKey('pickup_location', $broadcastData);
        $this->assertArrayHasKey('destination_location', $broadcastData);
        $this->assertEquals($rideRequest->id, $broadcastData['ride_request_id']);
        $this->assertEquals($ride->id, $broadcastData['ride_id']);
        $this->assertEquals(8, $broadcastData['estimated_pickup_minutes']);

        // Test location data structure
        $this->assertArrayHasKey('coordinates', $broadcastData['pickup_location']);
        $this->assertArrayHasKey('latitude', $broadcastData['pickup_location']['coordinates']);
        $this->assertArrayHasKey('longitude', $broadcastData['pickup_location']['coordinates']);

        // Test broadcast name
        $this->assertEquals('ride.request.matched', $event->broadcastAs());
    }

    /**
     * Test that NewRideRequest event broadcasts correctly
     */
    public function test_new_ride_request_event_broadcasts()
    {
        Event::fake([NewRideRequest::class]);

        $rideRequest = $this->createTestRideRequest();
        $driver = \App\Models\User::factory()->driver()->create();

        $event = new NewRideRequest(
            $rideRequest,
            [$driver->id]
        );

        $broadcastData = $event->broadcastWith();

        $this->assertArrayHasKey('ride_request_id', $broadcastData);
        $this->assertArrayHasKey('pickup_location', $broadcastData);
        $this->assertArrayHasKey('destination_location', $broadcastData);
        $this->assertEquals($rideRequest->id, $broadcastData['ride_request_id']);
        $this->assertEquals('ride.request.new', $event->broadcastAs());

        $channels = $event->broadcastOn();
        $channelNames = array_map(fn ($channel) => $channel->name, $channels);
        $this->assertContains("private-driver.{$driver->id}", $channelNames);
    }

    /**
     * Test that DriverOnlineStatusChanged event broadcasts correctly
     */
    public function test_driver_online_status_changed_event_broadcasts()
    {
        Event::fake([DriverOnlineStatusChanged::class]);

        $driver = User::factory()->driver()->create();
        $beacon = $this->createTestBeacon();

        $event = new DriverOnlineStatusChanged($driver, true, $beacon, 3);

        // Test event data structure
        $broadcastData = $event->broadcastWith();

        $this->assertArrayHasKey('driver_id', $broadcastData);
        $this->assertArrayHasKey('is_online', $broadcastData);
        $this->assertArrayHasKey('status', $broadcastData);
        $this->assertArrayHasKey('beacon', $broadcastData);
        $this->assertArrayHasKey('queue_position', $broadcastData);
        $this->assertEquals($driver->id, $broadcastData['driver_id']);
        $this->assertTrue($broadcastData['is_online']);
        $this->assertEquals('online', $broadcastData['status']);
        $this->assertEquals(3, $broadcastData['queue_position']);

        // Test broadcast channels
        $channels = $event->broadcastOn();
        $channelNames = array_map(fn ($channel) => $channel->name, $channels);
        $this->assertContains("private-user.{$driver->id}", $channelNames);
        $this->assertContains("private-driver.{$driver->id}", $channelNames);
        $this->assertContains("beacon.{$beacon->id}", $channelNames); // Public channel, no prefix

        // Test broadcast name
        $this->assertEquals('driver.status.changed', $event->broadcastAs());
    }

    // Helper methods

    private function createTestRide(array $overrides = []): Ride
    {
        $rider = User::factory()->rider()->create();
        $driver = User::factory()->driver()->create();

        $pickup = $this->createTestBeacon();
        $destination = $this->createTestLocation();

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
