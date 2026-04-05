<?php

namespace Tests\Unit\Pages;

use App\Filament\Pages\DriverQueuePage;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

class DriverQueuePageTest extends TestCase
{
    use RefreshDatabase;

    private DriverQueuePage $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->page = new DriverQueuePage;
    }

    // -------------------------------------------------------------------------
    // getQueuedDrivers
    // -------------------------------------------------------------------------

    public function test_returns_only_drivers_with_queue_joined_at(): void
    {
        $this->createDriverProfile(['queue_joined_at' => now()->subMinutes(5)]);
        $this->createDriverProfile(['queue_joined_at' => null]); // not in queue

        $drivers = $this->page->getQueuedDrivers();

        $this->assertCount(1, $drivers);
    }

    public function test_returns_drivers_ordered_fifo(): void
    {
        $first = $this->createDriverProfile(['queue_joined_at' => now()->subMinutes(10)]);
        $second = $this->createDriverProfile(['queue_joined_at' => now()->subMinutes(5)]);
        $third = $this->createDriverProfile(['queue_joined_at' => now()->subMinutes(1)]);

        $drivers = $this->page->getQueuedDrivers();

        $this->assertEquals($first->user_id, $drivers[0]->user_id);
        $this->assertEquals($second->user_id, $drivers[1]->user_id);
        $this->assertEquals($third->user_id, $drivers[2]->user_id);
    }

    public function test_returns_empty_when_no_drivers_queued(): void
    {
        $this->createDriverProfile(['queue_joined_at' => null]);

        $drivers = $this->page->getQueuedDrivers();

        $this->assertCount(0, $drivers);
    }

    public function test_eager_loads_user_relation(): void
    {
        $this->createDriverProfile(['queue_joined_at' => now()]);

        $drivers = $this->page->getQueuedDrivers();

        $this->assertTrue($drivers->first()->relationLoaded('user'));
    }

    // -------------------------------------------------------------------------
    // getQueueStats
    // -------------------------------------------------------------------------

    public function test_stats_total_matches_queued_count(): void
    {
        $this->createDriverProfile(['queue_joined_at' => now()->subMinutes(3)]);
        $this->createDriverProfile(['queue_joined_at' => now()->subMinutes(7)]);

        $stats = $this->page->getQueueStats();

        $this->assertEquals(2, $stats['total']);
    }

    public function test_stats_total_is_zero_with_empty_queue(): void
    {
        $stats = $this->page->getQueueStats();

        $this->assertEquals(0, $stats['total']);
        $this->assertEquals(0, $stats['avg_wait_minutes']);
        $this->assertEquals(0, $stats['in_cooldown']);
    }

    public function test_stats_in_cooldown_counts_drivers_with_future_cooldown(): void
    {
        $this->createDriverProfile([
            'queue_joined_at' => now()->subMinutes(5),
            'queue_cooldown_until' => now()->addMinutes(10), // in cooldown
        ]);
        $this->createDriverProfile([
            'queue_joined_at' => now()->subMinutes(5),
            'queue_cooldown_until' => now()->subMinutes(1), // cooldown expired
        ]);
        $this->createDriverProfile([
            'queue_joined_at' => now()->subMinutes(5),
            'queue_cooldown_until' => null, // no cooldown
        ]);

        $stats = $this->page->getQueueStats();

        $this->assertEquals(1, $stats['in_cooldown']);
    }

    public function test_stats_avg_wait_is_rounded_integer(): void
    {
        $this->createDriverProfile(['queue_joined_at' => now()->subMinutes(10)]);
        $this->createDriverProfile(['queue_joined_at' => now()->subMinutes(20)]);

        $stats = $this->page->getQueueStats();

        $this->assertEquals(15, $stats['avg_wait_minutes']);
        $this->assertIsInt($stats['avg_wait_minutes']);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function createDriverProfile(array $overrides = []): DriverProfile
    {
        $user = User::factory()->driver()->create();

        return DriverProfile::create(array_merge([
            'user_id' => $user->id,
            'vehicle_type' => 'motorcycle',
            'vehicle_plate' => 'B' . rand(1000, 9999) . 'XYZ',
            'is_verified' => true,
            'credits_balance' => 0,
            'credits_total_earned' => 0,
            'credits_total_spent' => 0,
            'current_location' => new Point(-6.3605, 106.8271, 4326),
        ], $overrides));
    }
}
