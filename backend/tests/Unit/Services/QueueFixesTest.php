<?php

namespace Tests\Unit\Services;

use App\Events\DriverOnlineStatusChanged;
use App\Events\MatchingQueuePositionChanged;
use App\Events\NewRideRequest;
use App\Events\RideNoDriversAvailable;
use App\Events\RideSearchResumed;
use App\Jobs\ExpireRideRequest;
use App\Jobs\HandleRequestTimeout;
use App\Models\CreditTransaction;
use App\Models\DriverProfile;
use App\Models\Location;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\User;
use App\Services\CreditService;
use App\Services\MatchingQueueService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Mockery;
use Tests\TestCase;

class QueueFixesTest extends TestCase
{
    use RefreshDatabase;

    private MatchingQueueService $service;
    private CreditService $creditService;

    private const PICKUP_LAT = -6.3605;
    private const PICKUP_LNG = 106.8271;
    private const NEARBY_LAT = -6.3620;
    private const NEARBY_LNG = 106.8280;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([
            NewRideRequest::class,
            MatchingQueuePositionChanged::class,
            RideNoDriversAvailable::class,
            RideSearchResumed::class,
            DriverOnlineStatusChanged::class,
        ]);
        Bus::fake([
            HandleRequestTimeout::class,
            ExpireRideRequest::class,
        ]);

        $notificationMock = Mockery::mock(NotificationService::class);
        $notificationMock->shouldReceive('sendRideRequestTimeoutToRider')->andReturn(true)->byDefault();
        $notificationMock->shouldReceive('sendNewRideRequestToDriver')->andReturn(true)->byDefault();
        $this->app->instance(NotificationService::class, $notificationMock);

        $this->service = new MatchingQueueService;
        $this->creditService = new CreditService;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // Fix 10 — 'matched' removed from Ride::ACTIVE_STATUSES
    // =========================================================================

    public function test_active_statuses_does_not_contain_matched(): void
    {
        $this->assertNotContains('matched', Ride::ACTIVE_STATUSES);
        $this->assertContains('accepted', Ride::ACTIVE_STATUSES);
        $this->assertContains('driver_arrived', Ride::ACTIVE_STATUSES);
        $this->assertContains('in_progress', Ride::ACTIVE_STATUSES);
    }

    // =========================================================================
    // Fix 1 — Busy-driver filter in findTopDriver()
    // =========================================================================

    public function test_find_top_driver_excludes_driver_with_pending_dispatch(): void
    {
        $driverA = $this->createQueuedDriver(minutesAgo: 30);
        $driverB = $this->createQueuedDriver(minutesAgo: 20);

        // driverA is currently dispatched to another request
        $otherRequest = $this->createTestRideRequest([
            'current_driver_id' => $driverA->id,
        ]);

        $newRequest = $this->createTestRideRequest();

        $topDriver = $this->service->findTopDriver($newRequest);

        // driverA should be excluded; driverB should be selected
        $this->assertNotNull($topDriver);
        $this->assertSame($driverB->id, $topDriver->user_id);
    }

    public function test_find_top_driver_returns_null_when_all_drivers_busy(): void
    {
        $driverA = $this->createQueuedDriver(minutesAgo: 30);

        // driverA is currently dispatched to another request
        $this->createTestRideRequest(['current_driver_id' => $driverA->id]);

        $newRequest = $this->createTestRideRequest();

        $topDriver = $this->service->findTopDriver($newRequest);

        $this->assertNull($topDriver);
    }

    public function test_find_top_driver_allows_driver_whose_previous_dispatch_resolved(): void
    {
        $driverA = $this->createQueuedDriver(minutesAgo: 30);

        // driverA was dispatched to a request that is now matched (resolved)
        $this->createTestRideRequest([
            'status' => 'matched',
            'current_driver_id' => $driverA->id,
        ]);

        $newRequest = $this->createTestRideRequest();

        $topDriver = $this->service->findTopDriver($newRequest);

        // The subquery only filters where status='pending', so driverA should be available
        $this->assertNotNull($topDriver);
        $this->assertSame($driverA->id, $topDriver->user_id);
    }

    // =========================================================================
    // Fix 3 — handleNoDriversFound increments generation
    // =========================================================================

    public function test_handle_no_drivers_found_increments_expiry_generation(): void
    {
        $rideRequest = $this->createTestRideRequest();
        $this->assertSame(0, $rideRequest->expiry_generation);

        $this->service->handleNoDriversFound($rideRequest);

        $rideRequest->refresh();
        $this->assertSame(1, $rideRequest->expiry_generation);

        // Call again — should increment to 2
        $this->service->handleNoDriversFound($rideRequest);

        $rideRequest->refresh();
        $this->assertSame(2, $rideRequest->expiry_generation);
    }

    public function test_handle_no_drivers_found_dispatches_expire_job_with_generation(): void
    {
        $rideRequest = $this->createTestRideRequest();

        $this->service->handleNoDriversFound($rideRequest);

        Bus::assertDispatched(ExpireRideRequest::class, function ($job) use ($rideRequest) {
            return $job->rideRequestId === $rideRequest->id
                && $job->expectedGeneration === 1;
        });
    }

    // =========================================================================
    // Fix 4 — sweepOrphanedRequests
    // =========================================================================

    public function test_sweep_dispatches_orphaned_request_to_available_driver(): void
    {
        $driver = $this->createQueuedDriver(minutesAgo: 10);

        // Orphaned: pending, no current_driver_id, not expired
        $orphaned = $this->createTestRideRequest([
            'current_driver_id' => null,
        ]);

        $this->service->sweepOrphanedRequests($driver->id);

        $orphaned->refresh();
        $this->assertSame($driver->id, $orphaned->current_driver_id);

        Event::assertDispatched(RideSearchResumed::class);
        Event::assertDispatched(NewRideRequest::class);

        // dispatchToDriver schedules a safety-net timeout job
        Bus::assertDispatched(HandleRequestTimeout::class, function ($job) use ($orphaned, $driver) {
            return $job->rideRequestId === $orphaned->id
                && $job->driverId === $driver->id;
        });
    }

    public function test_sweep_skips_expired_requests(): void
    {
        $driver = $this->createQueuedDriver(minutesAgo: 10);

        // Already expired
        $expired = $this->createTestRideRequest([
            'current_driver_id' => null,
            'expires_at' => now()->subMinutes(5),
        ]);

        $this->service->sweepOrphanedRequests($driver->id);

        $expired->refresh();
        $this->assertNull($expired->current_driver_id);

        Event::assertNotDispatched(RideSearchResumed::class);
    }

    public function test_sweep_skips_requests_with_driver_assigned(): void
    {
        $driverA = $this->createQueuedDriver(minutesAgo: 30);
        $driverB = $this->createQueuedDriver(minutesAgo: 10);

        // Already has a driver assigned — not orphaned
        $assigned = $this->createTestRideRequest([
            'current_driver_id' => $driverA->id,
        ]);

        $this->service->sweepOrphanedRequests($driverB->id);

        $assigned->refresh();
        // Should still be assigned to driverA, not changed to driverB
        $this->assertSame($driverA->id, $assigned->current_driver_id);
    }

    // =========================================================================
    // Fix 5 — tryDispatchPendingRequest filters expired
    // =========================================================================

    public function test_add_to_queue_does_not_dispatch_expired_pending_request(): void
    {
        // Expired pending request with no driver
        $this->createTestRideRequest([
            'current_driver_id' => null,
            'expires_at' => now()->subMinutes(5),
        ]);

        $driver = $this->createQueuedDriver(minutesAgo: 0, inQueue: false);

        // addToQueue triggers tryDispatchPendingRequest internally
        $this->service->addToQueue($driver->id);

        // No dispatch should have happened for the expired request
        Event::assertNotDispatched(NewRideRequest::class);
    }

    public function test_add_to_queue_dispatches_valid_pending_request(): void
    {
        // Valid pending request with no driver
        $pendingRequest = $this->createTestRideRequest([
            'current_driver_id' => null,
        ]);

        $driver = $this->createQueuedDriver(minutesAgo: 0, inQueue: false);

        $this->service->addToQueue($driver->id);

        // Verify the dispatch actually claimed the request
        $pendingRequest->refresh();
        $this->assertNotNull($pendingRequest->current_driver_id);

        Event::assertDispatched(RideSearchResumed::class);
    }

    // =========================================================================
    // Fix 6 — reactivateCooldownExpiredDrivers
    // =========================================================================

    public function test_reactivate_restores_queue_for_expired_cooldown_drivers(): void
    {
        $driver = User::factory()->driver()->create(['is_active' => true]);
        $profile = $this->createDriverProfile($driver, [
            'went_online_at' => now()->subHour(),
            'queue_joined_at' => null, // Was removed from queue
            'queue_cooldown_until' => now()->subMinutes(1), // Cooldown expired
        ]);

        $this->service->reactivateCooldownExpiredDrivers();

        $profile->refresh();
        $this->assertNotNull($profile->queue_joined_at);
    }

    public function test_reactivate_skips_drivers_still_in_cooldown(): void
    {
        $driver = User::factory()->driver()->create(['is_active' => true]);
        $profile = $this->createDriverProfile($driver, [
            'went_online_at' => now()->subHour(),
            'queue_joined_at' => null,
            'queue_cooldown_until' => now()->addMinutes(10), // Still in cooldown
        ]);

        $this->service->reactivateCooldownExpiredDrivers();

        $profile->refresh();
        $this->assertNull($profile->queue_joined_at);
    }

    public function test_reactivate_skips_offline_drivers(): void
    {
        $driver = User::factory()->driver()->create(['is_active' => true]);
        $profile = $this->createDriverProfile($driver, [
            'went_online_at' => null, // Offline
            'queue_joined_at' => null,
            'queue_cooldown_until' => now()->subMinutes(1),
        ]);

        $this->service->reactivateCooldownExpiredDrivers();

        $profile->refresh();
        $this->assertNull($profile->queue_joined_at);
    }

    // =========================================================================
    // Fix 7 — Credit refund on rider cancellation
    // =========================================================================

    public function test_refund_credit_increments_balance(): void
    {
        $driver = $this->createDriverWithCredits(5, totalSpent: 3);

        $ride = $this->createCompletedRide($driver->id);

        $this->creditService->refundCredit($driver->id, $ride->id);

        $profile = DriverProfile::where('user_id', $driver->id)->first();
        $this->assertSame(6, $profile->credits_balance);
        $this->assertSame(2, $profile->credits_total_spent);
    }

    public function test_refund_credit_creates_transaction_record(): void
    {
        $driver = $this->createDriverWithCredits(5);

        $ride = $this->createCompletedRide($driver->id);

        $this->creditService->refundCredit($driver->id, $ride->id);

        $transaction = CreditTransaction::where('ride_id', $ride->id)
            ->where('type', 'refund')
            ->first();

        $this->assertNotNull($transaction);
        $this->assertSame(1, $transaction->amount);
        $this->assertSame(5, $transaction->balance_before);
        $this->assertSame(6, $transaction->balance_after);
    }

    public function test_refund_credit_is_idempotent(): void
    {
        $driver = $this->createDriverWithCredits(5);

        $ride = $this->createCompletedRide($driver->id);

        $this->creditService->refundCredit($driver->id, $ride->id);
        $this->creditService->refundCredit($driver->id, $ride->id); // Second call

        $profile = DriverProfile::where('user_id', $driver->id)->first();
        // Should only be refunded once
        $this->assertSame(6, $profile->credits_balance);

        $refundCount = CreditTransaction::where('ride_id', $ride->id)
            ->where('type', 'refund')
            ->count();
        $this->assertSame(1, $refundCount);
    }

    public function test_refund_credit_total_spent_does_not_go_negative(): void
    {
        $driver = $this->createDriverWithCredits(5, totalSpent: 0);

        $ride = $this->createCompletedRide($driver->id);

        $this->creditService->refundCredit($driver->id, $ride->id);

        $profile = DriverProfile::where('user_id', $driver->id)->first();
        $this->assertSame(0, $profile->credits_total_spent);
    }

    // =========================================================================
    // Fix 8 — DriverOnlineStatusChanged event with reason
    // =========================================================================

    public function test_driver_status_event_includes_reason_when_offline(): void
    {
        $driver = User::factory()->driver()->create();

        $event = new DriverOnlineStatusChanged($driver, false, null, null, 'location_stale');

        $data = $event->broadcastWith();

        $this->assertFalse($data['is_online']);
        $this->assertSame('location_stale', $data['reason']);
    }

    public function test_driver_status_event_excludes_reason_when_online(): void
    {
        $driver = User::factory()->driver()->create();

        $event = new DriverOnlineStatusChanged($driver, true, null, null, 'location_stale');

        $data = $event->broadcastWith();

        $this->assertTrue($data['is_online']);
        $this->assertArrayNotHasKey('reason', $data);
    }

    public function test_driver_status_event_excludes_reason_when_null(): void
    {
        $driver = User::factory()->driver()->create();

        $event = new DriverOnlineStatusChanged($driver, false, null, null, null);

        $data = $event->broadcastWith();

        $this->assertArrayNotHasKey('reason', $data);
    }

    // =========================================================================
    // Fix 11 — expiry_generation column
    // =========================================================================

    public function test_ride_request_expiry_generation_defaults_to_zero(): void
    {
        $rideRequest = $this->createTestRideRequest();

        $this->assertSame(0, $rideRequest->expiry_generation);
    }

    public function test_ride_request_expiry_generation_is_fillable(): void
    {
        $rideRequest = $this->createTestRideRequest();

        $rideRequest->update(['expiry_generation' => 5]);
        $rideRequest->refresh();

        $this->assertSame(5, $rideRequest->expiry_generation);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createDriverProfile(User $user, array $overrides = []): DriverProfile
    {
        return DriverProfile::create(array_merge([
            'user_id' => $user->id,
            'is_verified' => true,
            'went_online_at' => now(),
            'queue_joined_at' => null,
            'queue_cooldown_until' => null,
            'max_pickup_radius_km' => 5,
            'decline_count' => 0,
            'decline_window_start' => null,
            'current_location' => new Point(self::NEARBY_LAT, self::NEARBY_LNG, 4326),
            'total_rides_given' => 0,
            'rating_average' => 5.00,
            'rating_count' => 0,
        ], $overrides));
    }

    private function createQueuedDriver(int $minutesAgo = 10, bool $inQueue = true): User
    {
        $user = User::factory()->driver()->create(['is_active' => true]);

        $this->createDriverProfile($user, [
            'went_online_at' => now()->subMinutes($minutesAgo + 5),
            'queue_joined_at' => $inQueue ? now()->subMinutes($minutesAgo) : null,
        ]);

        return $user;
    }

    private function createTestRideRequest(array $overrides = []): RideRequest
    {
        $rider = User::factory()->rider()->create();

        $pickup = Location::create([
            'name' => 'Test Pickup',
            'coordinates' => new Point(self::PICKUP_LAT, self::PICKUP_LNG, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $destination = Location::create([
            'name' => 'Test Destination',
            'coordinates' => new Point(-6.3650, 106.8300, 4326),
            'is_beacon' => false,
            'is_active' => true,
        ]);

        $extraFields = [];
        foreach (['current_driver_id', 'rider_cooldown_until', 'expiry_generation'] as $field) {
            if (array_key_exists($field, $overrides)) {
                $extraFields[$field] = $overrides[$field];
                unset($overrides[$field]);
            }
        }

        $rideRequest = RideRequest::create(array_merge([
            'rider_id' => $rider->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $destination->id,
            'estimated_distance_km' => 1.5,
            'estimated_duration_minutes' => 7,
            'estimated_fare_rp' => 10000,
            'passenger_count' => 1,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ], $overrides));

        if (! empty($extraFields)) {
            $rideRequest->forceFill($extraFields)->save();
        }

        return $rideRequest->fresh();
    }

    private function createDriverWithCredits(int $balance, int $totalSpent = 0): User
    {
        $user = User::factory()->driver()->create(['is_active' => true]);

        DriverProfile::create([
            'user_id' => $user->id,
            'vehicle_type' => 'motorcycle',
            'vehicle_plate' => 'B' . rand(1000, 9999) . 'XYZ',
            'is_verified' => true,
            'credits_balance' => $balance,
            'credits_total_earned' => $balance + $totalSpent,
            'credits_total_spent' => $totalSpent,
            'current_location' => new Point(self::NEARBY_LAT, self::NEARBY_LNG, 4326),
        ]);

        return $user;
    }

    private function createCompletedRide(int $driverId): Ride
    {
        $rider = User::factory()->rider()->create();
        $rideRequest = $this->createTestRideRequest(['rider_id' => $rider->id]);

        return Ride::create([
            'ride_request_id' => $rideRequest->id,
            'rider_id' => $rider->id,
            'driver_id' => $driverId,
            'pickup_location_id' => $rideRequest->pickup_location_id,
            'destination_location_id' => $rideRequest->destination_location_id,
            'status' => 'completed',
            'passenger_count' => 1,
            'estimated_fare_rp' => 10000,
        ]);
    }
}
