<?php

namespace Tests\Feature\Api;

use App\Events\DriverLocationUpdated;
use App\Events\QueuePositionChanged;
use App\Events\RideStatusUpdated;
use App\Models\Location;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\User;
use App\Services\QueueService;
use App\Services\RideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Mockery;
use Tests\TestCase;

/**
 * Test real-time data integrity and race condition scenarios
 */
class RealTimeDataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private $mockQueueService;
    private $mockRideService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockQueueService = Mockery::mock(QueueService::class);
        $this->mockRideService = Mockery::mock(RideService::class);

        $this->app->instance(QueueService::class, $this->mockQueueService);
        $this->app->instance(RideService::class, $this->mockRideService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test concurrent ride status updates
     */
    public function test_concurrent_ride_status_updates()
    {
        Event::fake([RideStatusUpdated::class]);

        $ride = $this->createTestRide(['status' => 'accepted']);

        // Simulate concurrent status updates
        $updates = [
            ['status' => 'in_progress', 'user' => 'driver'],
            ['status' => 'completed', 'user' => 'driver'],
            ['status' => 'cancelled', 'user' => 'rider'],
        ];

        $broadcastedEvents = [];

        foreach ($updates as $update) {
            // Simulate race condition - all updates happen "simultaneously"
            $event = new RideStatusUpdated($ride, $ride->status, $update['user']);
            $broadcastedEvents[] = $event->broadcastWith();

            // Update ride status for next iteration
            $ride->status = $update['status'];
        }

        // Verify all events have consistent data structure
        foreach ($broadcastedEvents as $eventData) {
            $this->assertArrayHasKey('ride_id', $eventData);
            $this->assertArrayHasKey('status', $eventData);
            $this->assertArrayHasKey('previous_status', $eventData);
            $this->assertArrayHasKey('updated_by', $eventData);
            $this->assertArrayHasKey('timestamp', $eventData);
        }

        // Verify events maintain referential integrity
        $rideIds = array_unique(array_column($broadcastedEvents, 'ride_id'));
        $this->assertCount(1, $rideIds, 'All events should reference the same ride');
    }

    /**
     * Test concurrent driver location updates
     */
    public function test_concurrent_driver_location_updates()
    {
        Event::fake([DriverLocationUpdated::class]);

        $driver = User::factory()->driver()->create();
        $ride = $this->createTestRide(['driver_id' => $driver->id, 'status' => 'in_progress']);

        // Simulate rapid location updates (GPS updates every second)
        $locations = [
            [-6.3605, 106.8271, 0, 45.0],    // lat, lng, speed, heading
            [-6.3606, 106.8272, 10, 46.0],
            [-6.3607, 106.8273, 20, 47.0],
            [-6.3608, 106.8274, 25, 48.0],
            [-6.3609, 106.8275, 30, 49.0],
        ];

        $broadcastedEvents = [];

        foreach ($locations as [$lat, $lng, $speed, $heading]) {
            $event = new DriverLocationUpdated($driver, $lat, $lng, $ride->id, $heading, $speed);
            $broadcastedEvents[] = $event->broadcastWith();
        }

        // Verify location sequence integrity
        for ($i = 0; $i < count($broadcastedEvents); $i++) {
            $eventData = $broadcastedEvents[$i];
            [$expectedLat, $expectedLng, $expectedSpeed, $expectedHeading] = $locations[$i];

            $this->assertEquals($expectedLat, $eventData['latitude']);
            $this->assertEquals($expectedLng, $eventData['longitude']);
            $this->assertEquals($expectedSpeed, $eventData['speed']);
            $this->assertEquals($expectedHeading, $eventData['heading']);
            $this->assertEquals($driver->id, $eventData['driver_id']);
            $this->assertEquals($ride->id, $eventData['active_ride_id']);
        }
    }

    /**
     * Test queue position consistency during rapid changes
     */
    public function test_queue_position_consistency_during_rapid_changes()
    {
        Event::fake([QueuePositionChanged::class]);

        $beacon = $this->createTestBeacon();
        $drivers = User::factory()->count(5)->driver()->create();

        $broadcastedEvents = [];

        // Simulate drivers joining queue rapidly
        foreach ($drivers as $index => $driver) {
            $position = $index + 1;
            $totalDrivers = $position;

            $event = new QueuePositionChanged(
                $driver,
                $beacon,
                $position,
                $totalDrivers,
                $position * 5, // Estimated wait time
                'joined'
            );

            $broadcastedEvents[] = $event->broadcastWith();
        }

        // Verify queue position consistency
        for ($i = 0; $i < count($broadcastedEvents); $i++) {
            $eventData = $broadcastedEvents[$i];
            $expectedPosition = $i + 1;

            $this->assertEquals($expectedPosition, $eventData['position']);
            $this->assertEquals($expectedPosition, $eventData['total_drivers']);
            $this->assertEquals($beacon->id, $eventData['beacon_id']);
            $this->assertEquals('joined', $eventData['action']);
        }

        // Simulate driver leaving queue (first driver)
        $leavingEvent = new QueuePositionChanged(
            $drivers[0],
            $beacon,
            0, // Position 0 means left queue
            4, // One less driver
            0,
            'left'
        );

        $leaveEventData = $leavingEvent->broadcastWith();

        $this->assertEquals(0, $leaveEventData['position']);
        $this->assertEquals(4, $leaveEventData['total_drivers']);
        $this->assertEquals('left', $leaveEventData['action']);
    }

    /**
     * Test data integrity during database rollbacks
     */
    public function test_data_integrity_during_database_rollbacks()
    {
        Event::fake([RideStatusUpdated::class]);

        $ride = $this->createTestRide(['status' => 'accepted']);
        $originalStatus = $ride->status;

        // Simulate database transaction that fails
        try {
            DB::transaction(function () use ($ride) {
                // Update ride status
                $ride->update(['status' => 'in_progress']);

                // Broadcast event with new status
                $event = new RideStatusUpdated($ride, 'accepted', 'driver');
                $broadcastData = $event->broadcastWith();

                // Verify event contains updated status
                $this->assertEquals('in_progress', $broadcastData['status']);
                $this->assertEquals('accepted', $broadcastData['previous_status']);

                // Force transaction rollback
                throw new \Exception('Simulated failure');
            });
        } catch (\Exception $e) {
            // Transaction rolled back
        }

        // Verify ride status rolled back
        $ride->refresh();
        $this->assertEquals($originalStatus, $ride->status);

        // In real implementation, events should not broadcast if transaction fails
        // This test demonstrates the importance of broadcasting after transaction commits
    }

    /**
     * Test event ordering with rapid sequential updates
     */
    public function test_event_ordering_with_rapid_sequential_updates()
    {
        Event::fake([RideStatusUpdated::class]);

        $ride = $this->createTestRide(['status' => 'pending']);

        // Simulate rapid status progression
        $statusProgression = [
            'accepted',
            'in_progress',
            'completed'
        ];

        $timestamps = [];
        $broadcastedEvents = [];

        foreach ($statusProgression as $newStatus) {
            $previousStatus = $ride->status;

            // Small delay to ensure timestamp differences
            usleep(1000); // 1ms delay

            $event = new RideStatusUpdated($ride, $previousStatus, 'driver');
            $broadcastData = $event->broadcastWith();

            $timestamps[] = $broadcastData['timestamp'];
            $broadcastedEvents[] = $broadcastData;

            // Update ride for next iteration
            $ride->status = $newStatus;
        }

        // Verify chronological ordering of timestamps
        for ($i = 1; $i < count($timestamps); $i++) {
            $previousTime = strtotime($timestamps[$i - 1]);
            $currentTime = strtotime($timestamps[$i]);

            $this->assertGreaterThanOrEqual(
                $previousTime,
                $currentTime,
                'Events should be chronologically ordered'
            );
        }

        // Verify status progression integrity
        $expectedPrevious = ['pending', 'accepted', 'in_progress'];
        for ($i = 0; $i < count($broadcastedEvents); $i++) {
            $this->assertEquals(
                $expectedPrevious[$i],
                $broadcastedEvents[$i]['previous_status']
            );
        }
    }

    /**
     * Test location data precision and consistency
     */
    public function test_location_data_precision_and_consistency()
    {
        Event::fake([DriverLocationUpdated::class]);

        $driver = User::factory()->driver()->create();

        // Test with high-precision coordinates
        $preciseLocations = [
            [-6.360512345, 106.827189123],
            [-6.360512346, 106.827189124], // Very small difference
            [-6.360512347, 106.827189125],
        ];

        $broadcastedEvents = [];

        foreach ($preciseLocations as [$lat, $lng]) {
            $event = new DriverLocationUpdated($driver, $lat, $lng);
            $broadcastedEvents[] = $event->broadcastWith();
        }

        // Verify precision is maintained
        for ($i = 0; $i < count($preciseLocations); $i++) {
            [$expectedLat, $expectedLng] = $preciseLocations[$i];
            $eventData = $broadcastedEvents[$i];

            $this->assertEquals($expectedLat, $eventData['latitude'], 'Latitude precision lost');
            $this->assertEquals($expectedLng, $eventData['longitude'], 'Longitude precision lost');
        }
    }

    /**
     * Test memory usage during intensive broadcasting
     */
    public function test_memory_usage_during_intensive_broadcasting()
    {
        Event::fake([DriverLocationUpdated::class]);

        $driver = User::factory()->driver()->create();
        $initialMemory = memory_get_usage();

        // Generate many location updates
        for ($i = 0; $i < 1000; $i++) {
            $lat = -6.3605 + ($i * 0.0001);
            $lng = 106.8271 + ($i * 0.0001);

            $event = new DriverLocationUpdated($driver, $lat, $lng);
            $event->broadcastWith(); // Generate broadcast data
        }

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be reasonable (less than 10MB for 1000 events)
        $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease,
            'Memory usage increased too much: ' . ($memoryIncrease / 1024 / 1024) . 'MB');
    }

    /**
     * Test event data immutability
     */
    public function test_event_data_immutability()
    {
        Event::fake([RideStatusUpdated::class]);

        $ride = $this->createTestRide();
        $event = new RideStatusUpdated($ride, 'pending', 'driver');

        // Get broadcast data twice
        $firstBroadcast = $event->broadcastWith();
        $secondBroadcast = $event->broadcastWith();

        // Data should be identical
        $this->assertEquals($firstBroadcast, $secondBroadcast);

        // Modify ride after event creation
        $ride->update(['status' => 'cancelled']);

        // Event data should remain unchanged
        $thirdBroadcast = $event->broadcastWith();
        $this->assertEquals($firstBroadcast, $thirdBroadcast);
    }

    /**
     * Test timestamp consistency across different timezones
     */
    public function test_timestamp_consistency_across_timezones()
    {
        Event::fake([RideStatusUpdated::class]);

        $ride = $this->createTestRide();

        // Test with different timezone settings
        $timezones = ['UTC', 'Asia/Jakarta', 'America/New_York', 'Europe/London'];
        $timestamps = [];

        foreach ($timezones as $timezone) {
            $originalTimezone = config('app.timezone');
            config(['app.timezone' => $timezone]);

            try {
                $event = new RideStatusUpdated($ride, 'pending', 'driver');
                $broadcastData = $event->broadcastWith();
                $timestamps[$timezone] = $broadcastData['timestamp'];

            } finally {
                config(['app.timezone' => $originalTimezone]);
            }
        }

        // All timestamps should be in consistent format (ISO 8601)
        foreach ($timestamps as $timezone => $timestamp) {
            $this->assertMatchesRegularExpression(
                '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
                $timestamp,
                "Timestamp format inconsistent for timezone: $timezone"
            );
        }
    }

    /**
     * Test concurrent queue operations integrity
     */
    public function test_concurrent_queue_operations_integrity()
    {
        Event::fake([QueuePositionChanged::class]);

        $beacon = $this->createTestBeacon();
        $drivers = User::factory()->count(3)->driver()->create();

        // Mock queue service to simulate concurrent operations
        $this->mockQueueService
            ->shouldReceive('getDriverQueueStatus')
            ->andReturn([
                'in_queue' => true,
                'position' => 1,
                'total_drivers' => 3,
                'estimated_wait_minutes' => 15,
                'beacon_id' => $beacon->id,
            ]);

        // Simulate concurrent queue events
        $events = [];
        foreach ($drivers as $index => $driver) {
            $event = new QueuePositionChanged(
                $driver,
                $beacon,
                $index + 1,
                count($drivers),
                ($index + 1) * 5,
                'joined'
            );
            $events[] = $event->broadcastWith();
        }

        // Verify queue consistency
        $positions = array_column($events, 'position');
        $uniquePositions = array_unique($positions);

        $this->assertCount(3, $uniquePositions, 'All drivers should have unique positions');
        $this->assertEquals([1, 2, 3], sort($positions), 'Positions should be sequential');

        // Verify beacon consistency
        $beaconIds = array_unique(array_column($events, 'beacon_id'));
        $this->assertCount(1, $beaconIds, 'All events should reference same beacon');
        $this->assertEquals($beacon->id, $beaconIds[0]);
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