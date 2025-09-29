<?php

namespace Tests\Unit\Services;

use App\Models\DriverQueue;
use App\Models\Location;
use App\Models\User;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

class QueueServiceTest extends TestCase
{
    use RefreshDatabase;

    private QueueService $queueService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queueService = new QueueService();
    }

    /**
     * Test driver joining queue successfully
     */
    public function test_driver_can_join_queue_successfully()
    {
        $driver = User::factory()->driver()->create(['is_active' => true]);
        $beacon = Location::create([
            'name' => 'Test Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'beacon_capacity' => 10,
            'current_queue_size' => 0,
            'is_active' => true,
        ]);

        $queueEntry = $this->queueService->joinQueue($driver->id, $beacon->id);

        $this->assertNotNull($queueEntry);
        $this->assertEquals($driver->id, $queueEntry->driver_id);
        $this->assertEquals($beacon->id, $queueEntry->beacon_id);
        $this->assertEquals(1, $queueEntry->position);
        $this->assertEquals('waiting', $queueEntry->status);
    }

    /**
     * Test driver cannot join queue when beacon is at capacity
     */
    public function test_driver_cannot_join_full_beacon()
    {
        $driver = User::factory()->driver()->create(['is_active' => true]);
        $beacon = Location::create([
            'name' => 'Full Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'beacon_capacity' => 5,
            'current_queue_size' => 5, // At capacity
            'is_active' => true,
        ]);

        $queueEntry = $this->queueService->joinQueue($driver->id, $beacon->id);

        $this->assertNull($queueEntry);
    }

    /**
     * Test driver cannot join queue when not active
     */
    public function test_inactive_driver_cannot_join_queue()
    {
        $driver = User::factory()->driver()->create(['is_active' => false]);
        $beacon = Location::create([
            'name' => 'Test Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'beacon_capacity' => 10,
            'current_queue_size' => 0,
            'is_active' => true,
        ]);

        $queueEntry = $this->queueService->joinQueue($driver->id, $beacon->id);

        $this->assertNull($queueEntry);
    }

    /**
     * Test rider user type cannot join driver queue
     */
    public function test_rider_cannot_join_driver_queue()
    {
        $rider = User::factory()->rider()->create(['is_active' => true]);
        $beacon = Location::create([
            'name' => 'Test Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'beacon_capacity' => 10,
            'current_queue_size' => 0,
            'is_active' => true,
        ]);

        $queueEntry = $this->queueService->joinQueue($rider->id, $beacon->id);

        $this->assertNull($queueEntry);
    }

    /**
     * Test driver cannot join queue at non-existent beacon
     */
    public function test_cannot_join_queue_at_non_existent_beacon()
    {
        $driver = User::factory()->driver()->create(['is_active' => true]);

        $queueEntry = $this->queueService->joinQueue($driver->id, 999);

        $this->assertNull($queueEntry);
    }

    /**
     * Test driver cannot join queue when already in another queue
     */
    public function test_driver_cannot_join_multiple_queues()
    {
        $driver = User::factory()->driver()->create(['is_active' => true]);

        $beacon1 = Location::create([
            'name' => 'Beacon 1',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'beacon_capacity' => 10,
            'current_queue_size' => 0,
            'is_active' => true,
        ]);

        $beacon2 = Location::create([
            'name' => 'Beacon 2',
            'coordinates' => new Point(-6.3595, 106.8295, 4326),
            'is_beacon' => true,
            'beacon_capacity' => 10,
            'current_queue_size' => 0,
            'is_active' => true,
        ]);

        // Join first queue
        $queueEntry1 = $this->queueService->joinQueue($driver->id, $beacon1->id);
        $this->assertNotNull($queueEntry1);

        // Try to join second queue
        $queueEntry2 = $this->queueService->joinQueue($driver->id, $beacon2->id);
        $this->assertNull($queueEntry2);
    }

    /**
     * Test multiple drivers joining queue get correct positions
     */
    public function test_multiple_drivers_get_correct_queue_positions()
    {
        $beacon = Location::create([
            'name' => 'Test Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'beacon_capacity' => 10,
            'current_queue_size' => 0,
            'is_active' => true,
        ]);

        $drivers = User::factory()->driver()->count(3)->create(['is_active' => true]);

        $queueEntries = [];
        foreach ($drivers as $index => $driver) {
            $queueEntries[] = $this->queueService->joinQueue($driver->id, $beacon->id);
        }

        // Check positions
        $this->assertEquals(1, $queueEntries[0]->position);
        $this->assertEquals(2, $queueEntries[1]->position);
        $this->assertEquals(3, $queueEntries[2]->position);
    }

    /**
     * Test driver leaving queue successfully
     */
    public function test_driver_can_leave_queue_successfully()
    {
        $driver = User::factory()->driver()->create(['is_active' => true]);
        $beacon = Location::create([
            'name' => 'Test Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'beacon_capacity' => 10,
            'current_queue_size' => 0,
            'is_active' => true,
        ]);

        // Join queue
        $queueEntry = $this->queueService->joinQueue($driver->id, $beacon->id);
        $this->assertNotNull($queueEntry);

        // Leave queue
        $result = $this->queueService->leaveQueue($driver->id);
        $this->assertTrue($result);

        // Check status
        $queueEntry->refresh();
        $this->assertEquals('left', $queueEntry->status);
        $this->assertNotNull($queueEntry->left_at);
    }

    /**
     * Test cannot leave queue when not in any queue
     */
    public function test_cannot_leave_queue_when_not_in_queue()
    {
        $driver = User::factory()->driver()->create(['is_active' => true]);

        $result = $this->queueService->leaveQueue($driver->id);

        $this->assertFalse($result);
    }

    /**
     * Test queue positions update when driver leaves
     */
    public function test_queue_positions_update_when_driver_leaves()
    {
        $beacon = Location::create([
            'name' => 'Test Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'beacon_capacity' => 10,
            'current_queue_size' => 0,
            'is_active' => true,
        ]);

        $drivers = User::factory()->driver()->count(3)->create(['is_active' => true]);

        // All drivers join queue
        $queueEntries = [];
        foreach ($drivers as $driver) {
            $queueEntries[] = $this->queueService->joinQueue($driver->id, $beacon->id);
        }

        // First driver leaves
        $this->queueService->leaveQueue($drivers[0]->id);

        // Check remaining positions
        $queueEntries[1]->refresh();
        $queueEntries[2]->refresh();

        $this->assertEquals(1, $queueEntries[1]->position);
        $this->assertEquals(2, $queueEntries[2]->position);
    }

    /**
     * Test getting driver queue status when in queue
     */
    public function test_can_get_driver_queue_status_when_in_queue()
    {
        $driver = User::factory()->driver()->create(['is_active' => true]);
        $beacon = Location::create([
            'name' => 'Test Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'beacon_capacity' => 10,
            'current_queue_size' => 0,
            'is_active' => true,
        ]);

        // Join queue
        $this->queueService->joinQueue($driver->id, $beacon->id);

        $status = $this->queueService->getDriverQueueStatus($driver->id);

        $this->assertTrue($status['in_queue']);
        $this->assertEquals($beacon->id, $status['beacon_id']);
        $this->assertEquals('Test Beacon', $status['beacon_name']);
        $this->assertEquals(1, $status['position']);
        $this->assertEquals('waiting', $status['status']);
        $this->assertArrayHasKey('wait_time_minutes', $status);
        $this->assertArrayHasKey('estimated_wait_minutes', $status);
    }

    /**
     * Test getting driver queue status when not in queue
     */
    public function test_can_get_driver_queue_status_when_not_in_queue()
    {
        $driver = User::factory()->driver()->create(['is_active' => true]);

        $status = $this->queueService->getDriverQueueStatus($driver->id);

        $this->assertFalse($status['in_queue']);
        $this->assertNull($status['beacon_id']);
        $this->assertNull($status['position']);
        $this->assertEquals('offline', $status['status']);
        $this->assertEquals(0, $status['wait_time_minutes']);
        $this->assertEquals(0, $status['estimated_wait_minutes']);
    }

    /**
     * Test getting beacon queue status
     */
    public function test_can_get_beacon_queue_status()
    {
        $beacon = Location::create([
            'name' => 'Test Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'beacon_capacity' => 10,
            'current_queue_size' => 0,
            'is_active' => true,
        ]);

        $drivers = User::factory()->driver()->count(2)->create(['is_active' => true]);

        // Drivers join queue
        foreach ($drivers as $driver) {
            $this->queueService->joinQueue($driver->id, $beacon->id);
        }

        $queueStatus = $this->queueService->getBeaconQueueStatus($beacon->id);

        $this->assertEquals($beacon->id, $queueStatus['beacon_id']);
        $this->assertEquals(2, $queueStatus['total_waiting']);
        $this->assertCount(2, $queueStatus['drivers']);

        // Check first driver details
        $firstDriver = $queueStatus['drivers'][0];
        $this->assertEquals($drivers[0]->id, $firstDriver['driver_id']);
        $this->assertEquals($drivers[0]->name, $firstDriver['driver_name']);
        $this->assertEquals(1, $firstDriver['position']);
    }

    /**
     * Test getting next driver at beacon
     */
    public function test_can_get_next_driver_at_beacon()
    {
        $beacon = Location::create([
            'name' => 'Test Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'beacon_capacity' => 10,
            'current_queue_size' => 0,
            'is_active' => true,
        ]);

        $drivers = User::factory()->driver()->count(3)->create(['is_active' => true]);

        // Drivers join queue
        foreach ($drivers as $driver) {
            $this->queueService->joinQueue($driver->id, $beacon->id);
        }

        $nextDriver = $this->queueService->getNextDriverAtBeacon($beacon->id);

        $this->assertNotNull($nextDriver);
        $this->assertEquals($drivers[0]->id, $nextDriver->driver_id);
        $this->assertEquals(1, $nextDriver->position);
    }

    /**
     * Test calling driver for ride
     */
    public function test_can_call_driver_for_ride()
    {
        $driver = User::factory()->driver()->create(['is_active' => true]);
        $beacon = Location::create([
            'name' => 'Test Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'beacon_capacity' => 10,
            'current_queue_size' => 0,
            'is_active' => true,
        ]);

        // Join queue
        $queueEntry = $this->queueService->joinQueue($driver->id, $beacon->id);

        // Call driver
        $result = $this->queueService->callDriver($driver->id);

        $this->assertTrue($result);
        $queueEntry->refresh();
        $this->assertEquals('called', $queueEntry->status);
    }

    /**
     * Test cannot call driver not in queue
     */
    public function test_cannot_call_driver_not_in_queue()
    {
        $driver = User::factory()->driver()->create(['is_active' => true]);

        $result = $this->queueService->callDriver($driver->id);

        $this->assertFalse($result);
    }

    /**
     * Test marking driver as served
     */
    public function test_can_mark_driver_as_served()
    {
        $driver = User::factory()->driver()->create(['is_active' => true]);
        $beacon = Location::create([
            'name' => 'Test Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'beacon_capacity' => 10,
            'current_queue_size' => 0,
            'is_active' => true,
        ]);

        // Join queue and call driver
        $queueEntry = $this->queueService->joinQueue($driver->id, $beacon->id);
        $this->queueService->callDriver($driver->id);

        // Mark as served
        $result = $this->queueService->markDriverServed($driver->id);

        $this->assertTrue($result);
        $queueEntry->refresh();
        $this->assertEquals('served', $queueEntry->status);
        $this->assertNotNull($queueEntry->left_at);
    }

    /**
     * Test getting all beacon statistics
     */
    public function test_can_get_all_beacon_statistics()
    {
        $beacon1 = Location::create([
            'name' => 'Beacon 1',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'beacon_capacity' => 10,
            'current_queue_size' => 3,
            'is_active' => true,
        ]);

        $beacon2 = Location::create([
            'name' => 'Beacon 2',
            'coordinates' => new Point(-6.3595, 106.8295, 4326),
            'is_beacon' => true,
            'beacon_capacity' => 5,
            'current_queue_size' => 5,
            'is_active' => true,
        ]);

        $stats = $this->queueService->getAllBeaconStatistics();

        $this->assertCount(2, $stats);

        $beacon1Stats = $stats->firstWhere('beacon_id', $beacon1->id);
        $this->assertEquals('Beacon 1', $beacon1Stats['beacon_name']);
        $this->assertEquals(10, $beacon1Stats['capacity']);
        $this->assertEquals(3, $beacon1Stats['current_queue_size']);
        $this->assertEquals(30.0, $beacon1Stats['utilization_percent']);
        $this->assertTrue($beacon1Stats['has_capacity']);

        $beacon2Stats = $stats->firstWhere('beacon_id', $beacon2->id);
        $this->assertFalse($beacon2Stats['has_capacity']);
        $this->assertEquals(100.0, $beacon2Stats['utilization_percent']);
    }

    /**
     * Test checking if driver can join beacon
     */
    public function test_can_check_if_driver_can_join_beacon()
    {
        $driver = User::factory()->driver()->create(['is_active' => true]);
        $beacon = Location::create([
            'name' => 'Test Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'beacon_capacity' => 10,
            'current_queue_size' => 0,
            'is_active' => true,
        ]);

        $canJoin = $this->queueService->canDriverJoinBeacon($driver->id, $beacon->id);

        $this->assertTrue($canJoin['can_join']);
        $this->assertEmpty($canJoin['reasons']);
    }

    /**
     * Test checking if driver cannot join beacon (multiple reasons)
     */
    public function test_can_check_why_driver_cannot_join_beacon()
    {
        $rider = User::factory()->rider()->create(['is_active' => false]);
        $beacon = Location::create([
            'name' => 'Test Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'beacon_capacity' => 5,
            'current_queue_size' => 5, // Full
            'is_active' => false, // Inactive
        ]);

        $canJoin = $this->queueService->canDriverJoinBeacon($rider->id, $beacon->id);

        $this->assertFalse($canJoin['can_join']);
        $this->assertContains('Beacon is not active', $canJoin['reasons']);
        $this->assertContains('Beacon queue is full', $canJoin['reasons']);
        $this->assertContains('User cannot be a driver', $canJoin['reasons']);
        $this->assertContains('Driver account is not active', $canJoin['reasons']);
    }

    /**
     * Test edge case: non-existent driver and beacon
     */
    public function test_handles_non_existent_driver_and_beacon()
    {
        $canJoin = $this->queueService->canDriverJoinBeacon(999, 999);

        $this->assertFalse($canJoin['can_join']);
        $this->assertContains('Driver not found', $canJoin['reasons']);
        $this->assertContains('Beacon not found', $canJoin['reasons']);
    }

    /**
     * Test cache behavior for queue status
     */
    public function test_queue_status_uses_cache()
    {
        $driver = User::factory()->driver()->create(['is_active' => true]);
        $beacon = Location::create([
            'name' => 'Test Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $this->queueService->joinQueue($driver->id, $beacon->id);

        // First call should cache the result
        $status1 = $this->queueService->getDriverQueueStatus($driver->id);

        // Second call should use cache
        $status2 = $this->queueService->getDriverQueueStatus($driver->id);

        $this->assertEquals($status1, $status2);
    }

    /**
     * Test maintenance operations
     */
    public function test_can_perform_maintenance()
    {
        // Create old queue entries
        $driver = User::factory()->driver()->create(['is_active' => true]);
        $beacon = Location::create([
            'name' => 'Test Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $queueEntry = DriverQueue::create([
            'driver_id' => $driver->id,
            'beacon_id' => $beacon->id,
            'position' => 1,
            'status' => 'left',
            'joined_at' => now()->subDays(2),
            'left_at' => now()->subDays(2),
        ]);

        // Perform maintenance
        $this->queueService->performMaintenance();

        // Check if old entry was cleaned up
        $this->assertDatabaseMissing('driver_queue', ['id' => $queueEntry->id]);
    }

    /**
     * Test concurrent queue joining (simulation)
     */
    public function test_handles_concurrent_queue_operations()
    {
        $beacon = Location::create([
            'name' => 'Test Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'beacon_capacity' => 2, // Small capacity
            'current_queue_size' => 0,
            'is_active' => true,
        ]);

        $drivers = User::factory()->driver()->count(5)->create(['is_active' => true]);

        $successfulJoins = 0;
        foreach ($drivers as $driver) {
            $queueEntry = $this->queueService->joinQueue($driver->id, $beacon->id);
            if ($queueEntry) {
                $successfulJoins++;
            }
        }

        // Only 2 drivers should successfully join due to capacity
        $this->assertEquals(2, $successfulJoins);
        $this->assertEquals(2, DriverQueue::where('beacon_id', $beacon->id)->waiting()->count());
    }
}