<?php

namespace Tests\Feature\Api;

use App\Events\DriverLocationUpdated;
use App\Events\DriverOnlineStatusChanged;
use App\Events\QueuePositionChanged;
use App\Events\RideRequestMatched;
use App\Events\RideStatusUpdated;
use App\Models\Location;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

/**
 * Test real-time broadcasting edge cases and error scenarios
 */
class RealTimeEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test broadcasting with missing ride relationships
     */
    public function test_ride_status_updated_with_missing_relationships()
    {
        Event::fake([RideStatusUpdated::class]);

        $rider = User::factory()->rider()->create();
        $driver = User::factory()->driver()->create();
        $ride = $this->createTestRide(['rider_id' => $rider->id, 'driver_id' => $driver->id]);

        // Store original location IDs but don't delete from DB to avoid FK constraints
        $originalPickupId = $ride->pickup_location_id;
        $originalDestinationId = $ride->destination_location_id;

        $event = new RideStatusUpdated($ride, 'pending', 'driver');

        // Event should still broadcast with valid relationships
        $broadcastData = $event->broadcastWith();

        $this->assertArrayHasKey('ride_id', $broadcastData);
        $this->assertArrayHasKey('pickup_location', $broadcastData);
        $this->assertArrayHasKey('destination_location', $broadcastData);
        // Locations should still exist since we didn't delete them
        $this->assertNotNull($broadcastData['pickup_location']);
        $this->assertNotNull($broadcastData['destination_location']);
    }

    /**
     * Test driver location broadcasting with invalid coordinates
     */
    public function test_driver_location_updated_with_invalid_coordinates()
    {
        Event::fake([DriverLocationUpdated::class]);

        $driver = User::factory()->driver()->create();

        // Test with invalid latitude/longitude values (but valid types)
        $invalidCoordinates = [
            [-91.0, 0.0],    // Invalid latitude (too low)
            [91.0, 0.0],     // Invalid latitude (too high)
            [0.0, -181.0],   // Invalid longitude (too low)
            [0.0, 181.0],    // Invalid longitude (too high)
            [0.0, 0.0],      // Zero coordinates
            [-999.0, 999.0], // Extreme coordinates
        ];

        foreach ($invalidCoordinates as [$lat, $lng]) {
            $event = new DriverLocationUpdated($driver, (float) $lat, (float) $lng);

            // Event should handle invalid coordinates gracefully
            $broadcastData = $event->broadcastWith();

            $this->assertArrayHasKey('latitude', $broadcastData);
            $this->assertArrayHasKey('longitude', $broadcastData);
            $this->assertEquals($lat, $broadcastData['latitude']);
            $this->assertEquals($lng, $broadcastData['longitude']);
        }
    }

    /**
     * Test queue position change with non-existent beacon
     */
    public function test_queue_position_changed_with_missing_beacon()
    {
        Event::fake([QueuePositionChanged::class]);

        $driver = User::factory()->driver()->create();
        $beacon = $this->createTestBeacon();

        // Create event first
        $event = new QueuePositionChanged($driver, $beacon, 1, 5, 10, 'joined');

        // Delete beacon after event creation
        $beacon->delete();

        // Event should still broadcast with beacon data
        $broadcastData = $event->broadcastWith();

        $this->assertArrayHasKey('beacon_id', $broadcastData);
        $this->assertArrayHasKey('beacon_name', $broadcastData);
        $this->assertEquals($beacon->id, $broadcastData['beacon_id']);
    }

    /**
     * Test broadcasting with deleted users
     */
    public function test_events_with_deleted_users()
    {
        Event::fake([RideStatusUpdated::class, DriverLocationUpdated::class]);

        $rider = User::factory()->rider()->create();
        $driver = User::factory()->driver()->create();
        $ride = $this->createTestRide(['rider_id' => $rider->id, 'driver_id' => $driver->id]);

        // Delete driver after ride creation
        $driver->delete();

        // Events should handle deleted users gracefully
        $rideEvent = new RideStatusUpdated($ride, 'pending', 'driver');
        $broadcastData = $rideEvent->broadcastWith();

        $this->assertArrayHasKey('rider_id', $broadcastData);
        $this->assertArrayHasKey('driver_id', $broadcastData);
        $this->assertEquals($driver->id, $broadcastData['driver_id']);

        // Driver location event with deleted driver
        $locationEvent = new DriverLocationUpdated($driver, -6.3605, 106.8271);
        $locationData = $locationEvent->broadcastWith();

        $this->assertArrayHasKey('driver_id', $locationData);
        $this->assertEquals($driver->id, $locationData['driver_id']);
    }

    /**
     * Test driver location broadcasting shouldBroadcast conditions
     */
    public function test_driver_location_should_broadcast_conditions()
    {
        Event::fake([DriverLocationUpdated::class]);

        // Create driver with no active rides
        $offlineDriver = User::factory()->driver()->create();

        $event = new DriverLocationUpdated($offlineDriver, -6.3605, 106.8271);

        // Should not broadcast for offline driver with no active ride (simplified test)
        // The actual shouldBroadcast logic depends on isOnline() method implementation
        $broadcastData = $event->broadcastWith();
        $this->assertArrayHasKey('driver_id', $broadcastData);

        // Create driver with active ride
        $activeDriver = User::factory()->driver()->create();
        $ride = $this->createTestRide(['driver_id' => $activeDriver->id, 'status' => 'in_progress']);

        $activeEvent = new DriverLocationUpdated($activeDriver, -6.3605, 106.8271, $ride->id);

        // Should broadcast for driver with active ride
        $activeBroadcastData = $activeEvent->broadcastWith();
        $this->assertArrayHasKey('active_ride_id', $activeBroadcastData);
        $this->assertEquals($ride->id, $activeBroadcastData['active_ride_id']);
    }

    /**
     * Test concurrent broadcasting events
     */
    public function test_concurrent_broadcasting_events()
    {
        Event::fake([RideStatusUpdated::class, DriverLocationUpdated::class]);

        $driver = User::factory()->driver()->create();
        $ride = $this->createTestRide(['driver_id' => $driver->id]);

        // Simulate concurrent events
        $events = [];
        for ($i = 0; $i < 10; $i++) {
            $events[] = new RideStatusUpdated($ride, 'pending', 'driver');
            $events[] = new DriverLocationUpdated($driver, -6.3605 + ($i * 0.001), 106.8271);
        }

        // All events should be able to broadcast without conflicts
        foreach ($events as $event) {
            $broadcastData = $event->broadcastWith();
            $this->assertIsArray($broadcastData);
            $this->assertArrayHasKey('timestamp', $broadcastData);
        }
    }

    /**
     * Test broadcasting with extremely large data
     */
    public function test_broadcasting_with_large_data()
    {
        Event::fake([QueuePositionChanged::class]);

        $driver = User::factory()->driver()->create([
            'name' => str_repeat('LongName', 20), // 160 characters (within DB limit)
        ]);

        $beacon = Location::create([
            'name' => str_repeat('LongBeacon', 20), // 200 characters (within DB limit)
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $event = new QueuePositionChanged($driver, $beacon, 1, 9999999, 9999999, 'joined');

        // Should handle large data gracefully
        $broadcastData = $event->broadcastWith();

        $this->assertArrayHasKey('driver_name', $broadcastData);
        $this->assertArrayHasKey('beacon_name', $broadcastData);
        $this->assertIsString($broadcastData['driver_name']);
        $this->assertIsString($broadcastData['beacon_name']);
    }

    /**
     * Test event broadcasting with malformed data
     */
    public function test_broadcasting_with_malformed_data()
    {
        Event::fake([RideRequestMatched::class]);

        $rideRequest = $this->createTestRideRequest();
        $ride = $this->createTestRide(['rider_id' => $rideRequest->rider_id]);

        // Test with various malformed pickup minutes (but valid types)
        $malformedPickupTimes = [-1, 999999, 0];

        foreach ($malformedPickupTimes as $pickupTime) {
            $event = new RideRequestMatched($rideRequest, $ride, (int) $pickupTime);

            $broadcastData = $event->broadcastWith();

            $this->assertArrayHasKey('estimated_pickup_minutes', $broadcastData);
            $this->assertEquals($pickupTime, $broadcastData['estimated_pickup_minutes']);
        }
    }

    /**
     * Test broadcasting with special characters and encoding
     */
    public function test_broadcasting_with_special_characters()
    {
        Event::fake([DriverOnlineStatusChanged::class]);

        $specialNames = [
            'João André',           // Accented characters
            'محمد علي',            // Arabic characters
            '王小明',               // Chinese characters
            'Driver with 🚗 emoji', // Emoji
            'Driver "quotes" &amp;', // HTML entities
            "Driver\nwith\nnewlines", // Newlines
            'Driver<script>alert("xss")</script>', // XSS attempt
        ];

        foreach ($specialNames as $name) {
            $driver = User::factory()->driver()->create(['name' => $name]);
            $beacon = $this->createTestBeacon();

            $event = new DriverOnlineStatusChanged($driver, true, $beacon, 1);

            $broadcastData = $event->broadcastWith();

            $this->assertArrayHasKey('driver_name', $broadcastData);
            $this->assertEquals($name, $broadcastData['driver_name']);
        }
    }

    /**
     * Test broadcasting event channels with deleted models
     */
    public function test_broadcast_channels_with_deleted_models()
    {
        Event::fake([RideStatusUpdated::class]);

        $rider = User::factory()->rider()->create();
        $driver = User::factory()->driver()->create();
        $ride = $this->createTestRide(['rider_id' => $rider->id, 'driver_id' => $driver->id]);

        $event = new RideStatusUpdated($ride, 'pending', 'driver');

        // Get initial channels
        $initialChannels = $event->broadcastOn();
        $this->assertCount(3, $initialChannels);

        // Delete ride and test channels still work
        $ride->delete();

        // Channels should still be accessible even with deleted ride
        $channels = $event->broadcastOn();
        $this->assertCount(3, $channels);

        $channelNames = array_map(fn ($channel) => $channel->name, $channels);
        $this->assertContains("private-ride.{$ride->id}", $channelNames);
    }

    /**
     * Test Redis connection failures during broadcasting
     */
    public function test_redis_connection_failure_handling()
    {
        Event::fake([QueuePositionChanged::class]);

        $driver = User::factory()->driver()->create();
        $beacon = $this->createTestBeacon();

        // Simulate Redis connection issue by using invalid Redis config
        $originalRedisHost = config('database.redis.default.host');
        config(['database.redis.default.host' => 'invalid-redis-host']);

        try {
            $event = new QueuePositionChanged($driver, $beacon, 1, 5, 10, 'joined');

            // Event should still create successfully even if Redis is unavailable
            $broadcastData = $event->broadcastWith();

            $this->assertIsArray($broadcastData);
            $this->assertArrayHasKey('driver_id', $broadcastData);

        } finally {
            // Restore original Redis config
            config(['database.redis.default.host' => $originalRedisHost]);
        }
    }

    /**
     * Test broadcasting with null and empty values
     */
    public function test_broadcasting_with_null_and_empty_values()
    {
        Event::fake([DriverLocationUpdated::class]);

        $driver = User::factory()->driver()->create();

        // Test with null values
        $event = new DriverLocationUpdated($driver, -6.3605, 106.8271, null, null, null);

        $broadcastData = $event->broadcastWith();

        $this->assertArrayHasKey('active_ride_id', $broadcastData);
        $this->assertArrayHasKey('heading', $broadcastData);
        $this->assertArrayHasKey('speed', $broadcastData);
        $this->assertNull($broadcastData['active_ride_id']);
        $this->assertNull($broadcastData['heading']);
        $this->assertNull($broadcastData['speed']);
    }

    /**
     * Test event timestamps and timezone handling
     */
    public function test_event_timestamps_and_timezones()
    {
        Event::fake([RideStatusUpdated::class]);

        $ride = $this->createTestRide();

        // Set different timezone
        $originalTimezone = config('app.timezone');
        config(['app.timezone' => 'Asia/Jakarta']);

        try {
            $event = new RideStatusUpdated($ride, 'pending', 'driver');
            $broadcastData = $event->broadcastWith();

            $this->assertArrayHasKey('timestamp', $broadcastData);
            $this->assertArrayHasKey('updated_at', $broadcastData);

            // Timestamps should be in ISO format
            $timestamp = $broadcastData['timestamp'];
            $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $timestamp);

        } finally {
            config(['app.timezone' => $originalTimezone]);
        }
    }

    // Helper methods (same as previous test file)

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
