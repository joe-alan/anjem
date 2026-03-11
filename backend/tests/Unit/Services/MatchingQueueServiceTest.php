<?php

namespace Tests\Unit\Services;

use App\Events\MatchingQueuePositionChanged;
use App\Events\NewRideRequest;
use App\Jobs\HandleRequestTimeout;
use App\Models\DriverProfile;
use App\Models\Location;
use App\Models\RideRequest;
use App\Models\User;
use App\Services\MatchingQueueService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Mockery;
use Tests\TestCase;

class MatchingQueueServiceTest extends TestCase
{
    use RefreshDatabase;

    private MatchingQueueService $service;

    // Pickup coordinates used as the reference location in most tests
    private const PICKUP_LAT = -6.3605;
    private const PICKUP_LNG = 106.8271;

    // Driver coordinates close enough (< 10 km) to satisfy the radius filter
    private const NEARBY_LAT = -6.3620;
    private const NEARBY_LNG = 106.8280;

    // Driver coordinates far away (> 10 km) to fail the radius filter
    private const FAR_LAT = -6.9000;
    private const FAR_LNG = 107.6000;

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent real WebSocket broadcasts and real job dispatching
        Event::fake([
            NewRideRequest::class,
            MatchingQueuePositionChanged::class,
        ]);
        Bus::fake([
            HandleRequestTimeout::class,
        ]);

        // Bind a mock NotificationService to avoid real FCM calls
        $notificationMock = Mockery::mock(NotificationService::class);
        $notificationMock->shouldReceive('sendRideRequestTimeoutToRider')
            ->andReturn(true)
            ->byDefault();
        $this->app->instance(NotificationService::class, $notificationMock);

        $this->service = new MatchingQueueService;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // addToQueue
    // -------------------------------------------------------------------------

    public function test_add_to_queue_sets_queue_joined_at(): void
    {
        $driver = User::factory()->driver()->create();
        $profile = $this->createDriverProfile($driver);

        $this->assertNull($profile->queue_joined_at);

        $before = Carbon::now()->subSecond();
        $this->service->addToQueue($driver->id);
        $after = Carbon::now()->addSecond();

        $profile->refresh();

        $this->assertNotNull($profile->queue_joined_at);
        $this->assertTrue(
            $profile->queue_joined_at->between($before, $after),
            'queue_joined_at should be set to approximately now'
        );
    }

    public function test_add_to_queue_broadcasts_queue_position_event(): void
    {
        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver);

        $this->service->addToQueue($driver->id);

        Event::assertDispatched(MatchingQueuePositionChanged::class, function ($event) use ($driver) {
            return $event->driverId === $driver->id;
        });
    }

    public function test_add_to_queue_does_nothing_when_driver_profile_missing(): void
    {
        $driver = User::factory()->driver()->create();
        // No driver profile created — should not throw
        $this->service->addToQueue($driver->id);

        // No exception = pass; queue position broadcast should NOT fire for missing profile
        Event::assertNotDispatched(MatchingQueuePositionChanged::class);
    }

    // -------------------------------------------------------------------------
    // removeFromQueue
    // -------------------------------------------------------------------------

    public function test_remove_from_queue_clears_queue_joined_at(): void
    {
        $driver = User::factory()->driver()->create();
        $profile = $this->createDriverProfile($driver, ['queue_joined_at' => now()]);

        $this->assertNotNull($profile->queue_joined_at);

        $this->service->removeFromQueue($driver->id);

        $profile->refresh();
        $this->assertNull($profile->queue_joined_at);
    }

    public function test_remove_from_queue_broadcasts_queue_position_event(): void
    {
        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver, ['queue_joined_at' => now()]);

        $this->service->removeFromQueue($driver->id);

        Event::assertDispatched(MatchingQueuePositionChanged::class, function ($event) use ($driver) {
            return $event->driverId === $driver->id;
        });
    }

    // -------------------------------------------------------------------------
    // rejoinAfterRide
    // -------------------------------------------------------------------------

    public function test_rejoin_after_ride_sets_fresh_queue_joined_at(): void
    {
        $driver = User::factory()->driver()->create();
        $originalTime = Carbon::now()->subHours(2);
        $profile = $this->createDriverProfile($driver, ['queue_joined_at' => $originalTime]);

        $before = Carbon::now()->subSecond();
        $this->service->rejoinAfterRide($driver->id);
        $after = Carbon::now()->addSecond();

        $profile->refresh();

        $this->assertNotNull($profile->queue_joined_at);
        $this->assertTrue(
            $profile->queue_joined_at->between($before, $after),
            'rejoinAfterRide should set queue_joined_at to a fresh timestamp near now'
        );
        $this->assertFalse(
            $profile->queue_joined_at->equalTo($originalTime),
            'rejoinAfterRide should use a different timestamp than the original join time'
        );
    }

    public function test_rejoin_after_ride_broadcasts_queue_position_event(): void
    {
        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver, ['queue_joined_at' => now()->subHour()]);

        $this->service->rejoinAfterRide($driver->id);

        Event::assertDispatched(MatchingQueuePositionChanged::class, function ($event) use ($driver) {
            return $event->driverId === $driver->id;
        });
    }

    // -------------------------------------------------------------------------
    // getQueuePosition
    // -------------------------------------------------------------------------

    public function test_get_queue_position_returns_zero_when_driver_not_in_queue(): void
    {
        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver, ['queue_joined_at' => null]);

        $position = $this->service->getQueuePosition($driver->id);

        $this->assertSame(0, $position);
    }

    public function test_get_queue_position_returns_zero_when_driver_profile_missing(): void
    {
        $driver = User::factory()->driver()->create();
        // No profile created

        $position = $this->service->getQueuePosition($driver->id);

        $this->assertSame(0, $position);
    }

    public function test_get_queue_position_returns_zero_when_driver_in_cooldown(): void
    {
        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver, [
            'queue_joined_at' => now()->subMinutes(5),
            'queue_cooldown_until' => now()->addMinutes(10),
        ]);

        $position = $this->service->getQueuePosition($driver->id);

        $this->assertSame(0, $position);
    }

    public function test_get_queue_position_returns_one_for_sole_queued_driver(): void
    {
        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver, ['queue_joined_at' => now()]);

        $position = $this->service->getQueuePosition($driver->id);

        $this->assertSame(1, $position);
    }

    public function test_get_queue_position_fifo_ordering_three_drivers(): void
    {
        $driverA = User::factory()->driver()->create();
        $driverB = User::factory()->driver()->create();
        $driverC = User::factory()->driver()->create();

        // driverA joined earliest, driverC joined latest
        $this->createDriverProfile($driverA, ['queue_joined_at' => now()->subMinutes(30)]);
        $this->createDriverProfile($driverB, ['queue_joined_at' => now()->subMinutes(15)]);
        $this->createDriverProfile($driverC, ['queue_joined_at' => now()->subMinutes(5)]);

        $this->assertSame(1, $this->service->getQueuePosition($driverA->id));
        $this->assertSame(2, $this->service->getQueuePosition($driverB->id));
        $this->assertSame(3, $this->service->getQueuePosition($driverC->id));
    }

    public function test_get_queue_position_excludes_drivers_in_cooldown_from_count(): void
    {
        $driverCooldown = User::factory()->driver()->create();
        $driverActive   = User::factory()->driver()->create();

        // Cooldown driver joined earlier but is suspended
        $this->createDriverProfile($driverCooldown, [
            'queue_joined_at'    => now()->subHours(1),
            'queue_cooldown_until' => now()->addMinutes(5),
        ]);
        $this->createDriverProfile($driverActive, [
            'queue_joined_at' => now()->subMinutes(10),
        ]);

        // driverCooldown is excluded by notInCooldown() scope → driverActive is position 1
        $this->assertSame(0, $this->service->getQueuePosition($driverCooldown->id));
        $this->assertSame(1, $this->service->getQueuePosition($driverActive->id));
    }

    // -------------------------------------------------------------------------
    // applyDeclinePenalty
    // -------------------------------------------------------------------------

    public function test_apply_decline_penalty_first_decline_starts_new_window(): void
    {
        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver, [
            'queue_joined_at'      => now()->subMinutes(10),
            'decline_count'        => 0,
            'decline_window_start' => null,
        ]);

        $before = Carbon::now()->subSecond();
        $this->service->applyDeclinePenalty($driver->id);
        $after = Carbon::now()->addSecond();

        $profile = DriverProfile::where('user_id', $driver->id)->first();

        $this->assertSame(1, $profile->decline_count);
        $this->assertNotNull($profile->decline_window_start);
        $this->assertTrue(
            $profile->decline_window_start->between($before, $after),
            'decline_window_start should be set to approximately now'
        );
    }

    public function test_apply_decline_penalty_second_decline_increments_count_only(): void
    {
        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver, [
            'queue_joined_at'      => now()->subMinutes(10),
            'decline_count'        => 1,
            'decline_window_start' => now()->subMinutes(2), // within the 15-min window
        ]);

        $this->service->applyDeclinePenalty($driver->id);

        $profile = DriverProfile::where('user_id', $driver->id)->first();

        $this->assertSame(2, $profile->decline_count);
        $this->assertNull($profile->queue_cooldown_until);
    }

    public function test_apply_decline_penalty_fourth_decline_increments_count_only(): void
    {
        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver, [
            'queue_joined_at'      => now()->subMinutes(10),
            'decline_count'        => 3,
            'decline_window_start' => now()->subMinutes(5),
        ]);

        $this->service->applyDeclinePenalty($driver->id);

        $profile = DriverProfile::where('user_id', $driver->id)->first();

        $this->assertSame(4, $profile->decline_count);
        $this->assertNull($profile->queue_cooldown_until);
    }

    public function test_apply_decline_penalty_fifth_decline_moves_driver_to_back_of_queue(): void
    {
        $driver = User::factory()->driver()->create();
        $originalJoinTime = now()->subHours(2);
        $this->createDriverProfile($driver, [
            'queue_joined_at'      => $originalJoinTime,
            'decline_count'        => 4,
            'decline_window_start' => now()->subMinutes(5),
        ]);

        $before = Carbon::now()->subSecond();
        $this->service->applyDeclinePenalty($driver->id);
        $after = Carbon::now()->addSecond();

        $profile = DriverProfile::where('user_id', $driver->id)->first();

        $this->assertSame(5, $profile->decline_count);
        $this->assertNotNull($profile->queue_joined_at);
        // queue_joined_at must have been refreshed to a new "now" timestamp
        $this->assertTrue(
            $profile->queue_joined_at->between($before, $after),
            'Fifth decline should move driver to back of queue by resetting queue_joined_at to now'
        );
        // No cooldown should be applied yet
        $this->assertNull($profile->queue_cooldown_until);
    }

    public function test_apply_decline_penalty_sixth_decline_suspends_driver_from_queue(): void
    {
        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver, [
            'queue_joined_at'      => now()->subMinutes(10),
            'decline_count'        => 5,
            'decline_window_start' => now()->subMinutes(5),
        ]);

        $before = Carbon::now()->subSecond();
        $this->service->applyDeclinePenalty($driver->id);
        $after = Carbon::now()->addMinutes(16); // 15-min cooldown + slack

        $profile = DriverProfile::where('user_id', $driver->id)->first();

        $this->assertSame(6, $profile->decline_count);
        // Driver must be removed from queue
        $this->assertNull($profile->queue_joined_at);
        // Cooldown must be set ~15 minutes in the future
        $this->assertNotNull($profile->queue_cooldown_until);
        $this->assertTrue(
            $profile->queue_cooldown_until->between($before->addMinutes(14), $after),
            'Cooldown should be set approximately 15 minutes from now'
        );
    }

    public function test_apply_decline_penalty_beyond_threshold_also_suspends(): void
    {
        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver, [
            'queue_joined_at'      => now()->subMinutes(5),
            'decline_count'        => 7,
            'decline_window_start' => now()->subMinutes(8),
        ]);

        $this->service->applyDeclinePenalty($driver->id);

        $profile = DriverProfile::where('user_id', $driver->id)->first();

        $this->assertSame(8, $profile->decline_count);
        $this->assertNull($profile->queue_joined_at);
        $this->assertNotNull($profile->queue_cooldown_until);
    }

    public function test_apply_decline_penalty_resets_count_when_window_expired(): void
    {
        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver, [
            'queue_joined_at'      => now()->subMinutes(10),
            'decline_count'        => 4,
            'decline_window_start' => now()->subMinutes(20), // 20 min ago > 15-min window
        ]);

        $before = Carbon::now()->subSecond();
        $this->service->applyDeclinePenalty($driver->id);
        $after = Carbon::now()->addSecond();

        $profile = DriverProfile::where('user_id', $driver->id)->first();

        // Window expired → count resets to 1, no cooldown, no queue change
        $this->assertSame(1, $profile->decline_count);
        $this->assertNotNull($profile->decline_window_start);
        $this->assertTrue(
            $profile->decline_window_start->between($before, $after),
            'decline_window_start should be reset to now when penalty window expires'
        );
        $this->assertNull($profile->queue_cooldown_until);
    }

    public function test_apply_decline_penalty_resets_count_when_window_is_null(): void
    {
        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver, [
            'queue_joined_at'      => now()->subMinutes(10),
            'decline_count'        => 3,
            'decline_window_start' => null,
        ]);

        $this->service->applyDeclinePenalty($driver->id);

        $profile = DriverProfile::where('user_id', $driver->id)->first();

        $this->assertSame(1, $profile->decline_count);
        $this->assertNull($profile->queue_cooldown_until);
    }

    public function test_apply_decline_penalty_does_nothing_when_profile_missing(): void
    {
        $driver = User::factory()->driver()->create();
        // No profile

        // Should not throw
        $this->service->applyDeclinePenalty($driver->id);

        $this->assertDatabaseMissing('driver_profiles', ['user_id' => $driver->id]);
    }

    // -------------------------------------------------------------------------
    // handleDeclineOrTimeout — idempotency guards
    // -------------------------------------------------------------------------

    public function test_handle_decline_does_nothing_when_ride_request_not_found(): void
    {
        // Should not throw and should not modify anything
        $this->service->handleDeclineOrTimeout(999, 999_999);

        // If we get here without exception, the guard worked
        $this->assertTrue(true);
    }

    public function test_handle_decline_does_nothing_when_current_driver_id_mismatch(): void
    {
        $driver     = User::factory()->driver()->create();
        $otherDriver = User::factory()->driver()->create();
        $this->createDriverProfile($driver, ['queue_joined_at' => now(), 'decline_count' => 0]);

        $rideRequest = $this->createTestRideRequest([
            'status'            => 'pending',
            'current_driver_id' => $otherDriver->id,
        ]);

        $this->service->handleDeclineOrTimeout($driver->id, $rideRequest->id);

        // Penalty must NOT have been applied
        $profile = DriverProfile::where('user_id', $driver->id)->first();
        $this->assertSame(0, $profile->decline_count);

        // Status must remain pending
        $rideRequest->refresh();
        $this->assertSame('pending', $rideRequest->status);
    }

    public function test_handle_decline_does_nothing_when_status_is_in_progress(): void
    {
        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver, ['queue_joined_at' => now(), 'decline_count' => 0]);

        $rideRequest = $this->createTestRideRequest([
            'status'            => 'in_progress',
            'current_driver_id' => $driver->id,
        ]);

        $this->service->handleDeclineOrTimeout($driver->id, $rideRequest->id);

        $profile = DriverProfile::where('user_id', $driver->id)->first();
        $this->assertSame(0, $profile->decline_count);
    }

    public function test_handle_decline_does_nothing_when_status_is_completed(): void
    {
        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver, ['queue_joined_at' => now(), 'decline_count' => 0]);

        $rideRequest = $this->createTestRideRequest([
            'status'            => 'completed',
            'current_driver_id' => $driver->id,
        ]);

        $this->service->handleDeclineOrTimeout($driver->id, $rideRequest->id);

        $profile = DriverProfile::where('user_id', $driver->id)->first();
        $this->assertSame(0, $profile->decline_count);
    }

    // -------------------------------------------------------------------------
    // handleDeclineOrTimeout — happy paths
    // -------------------------------------------------------------------------

    public function test_handle_decline_applies_penalty_and_clears_current_driver_id(): void
    {
        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver, [
            'queue_joined_at'      => now()->subMinutes(10),
            'decline_count'        => 0,
            'decline_window_start' => null,
        ]);

        $rideRequest = $this->createTestRideRequest([
            'status'            => 'pending',
            'current_driver_id' => $driver->id,
        ]);

        $this->service->handleDeclineOrTimeout($driver->id, $rideRequest->id);

        // Penalty applied
        $profile = DriverProfile::where('user_id', $driver->id)->first();
        $this->assertSame(1, $profile->decline_count);

        // current_driver_id cleared
        $rideRequest->refresh();
        $this->assertNull($rideRequest->current_driver_id);
    }

    public function test_handle_decline_dispatches_to_next_driver_when_available(): void
    {
        $decliningDriver = User::factory()->driver()->create();
        $this->createDriverProfile($decliningDriver, [
            'queue_joined_at'      => now()->subMinutes(30),
            'decline_count'        => 0,
            'decline_window_start' => null,
        ]);

        // A second driver eligible to receive the request
        $nextDriver = User::factory()->driver()->create();
        $this->createVerifiedDriverInQueue($nextDriver, now()->subMinutes(20));

        $rideRequest = $this->createTestRideRequest([
            'status'            => 'pending',
            'current_driver_id' => $decliningDriver->id,
        ]);

        $this->service->handleDeclineOrTimeout($decliningDriver->id, $rideRequest->id);

        // NewRideRequest broadcast should fire for the next driver
        Event::assertDispatched(NewRideRequest::class);

        // HandleRequestTimeout job should be queued for the next driver
        Bus::assertDispatched(HandleRequestTimeout::class);

        // Request should still be pending (not expired) since a next driver was found
        $rideRequest->refresh();
        $this->assertNotSame('expired', $rideRequest->status);
        $this->assertSame($nextDriver->id, $rideRequest->current_driver_id);
    }

    public function test_handle_decline_expires_request_and_sets_rider_cooldown_when_no_next_driver(): void
    {
        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver, [
            'queue_joined_at'      => now()->subMinutes(10),
            'decline_count'        => 0,
            'decline_window_start' => null,
        ]);

        // No other drivers in the queue
        $rideRequest = $this->createTestRideRequest([
            'status'            => 'pending',
            'current_driver_id' => $driver->id,
        ]);

        $before = Carbon::now()->subSecond();
        $this->service->handleDeclineOrTimeout($driver->id, $rideRequest->id);
        $after = Carbon::now()->addSeconds(62);

        $rideRequest->refresh();

        $this->assertSame('expired', $rideRequest->status);
        $this->assertNotNull($rideRequest->rider_cooldown_until);
        $this->assertTrue(
            $rideRequest->rider_cooldown_until->between($before->addSeconds(58), $after),
            'rider_cooldown_until should be ~60 s in the future'
        );
    }

    public function test_handle_decline_processes_matched_status_requests(): void
    {
        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver, [
            'queue_joined_at'      => now()->subMinutes(5),
            'decline_count'        => 0,
            'decline_window_start' => null,
        ]);

        $rideRequest = $this->createTestRideRequest([
            'status'            => 'matched',
            'current_driver_id' => $driver->id,
        ]);

        $this->service->handleDeclineOrTimeout($driver->id, $rideRequest->id);

        // Penalty must have been applied (confirming the method ran)
        $profile = DriverProfile::where('user_id', $driver->id)->first();
        $this->assertSame(1, $profile->decline_count);
    }

    // -------------------------------------------------------------------------
    // cleanupTimedOutRequests
    // -------------------------------------------------------------------------

    public function test_cleanup_timed_out_requests_expires_stale_pending_requests(): void
    {
        $driver = User::factory()->driver()->create();

        $staleRequest = $this->createTestRideRequest([
            'status'            => 'pending',
            'current_driver_id' => $driver->id,
            'expires_at'        => now()->subMinutes(5),
        ]);

        $this->service->cleanupTimedOutRequests();

        $staleRequest->refresh();
        $this->assertSame('expired', $staleRequest->status);
        $this->assertNull($staleRequest->current_driver_id);
    }

    public function test_cleanup_timed_out_requests_does_not_affect_non_expired_requests(): void
    {
        $driver = User::factory()->driver()->create();

        $activeRequest = $this->createTestRideRequest([
            'status'            => 'pending',
            'current_driver_id' => $driver->id,
            'expires_at'        => now()->addMinutes(10),
        ]);

        $this->service->cleanupTimedOutRequests();

        $activeRequest->refresh();
        $this->assertSame('pending', $activeRequest->status);
        $this->assertSame($driver->id, $activeRequest->current_driver_id);
    }

    public function test_cleanup_timed_out_requests_ignores_requests_without_current_driver(): void
    {
        // Expired but no current_driver_id — should be ignored by cleanupTimedOutRequests
        $requestWithoutDriver = $this->createTestRideRequest([
            'status'            => 'pending',
            'current_driver_id' => null,
            'expires_at'        => now()->subMinutes(10),
        ]);

        $this->service->cleanupTimedOutRequests();

        // The request should not have been touched because the WHERE requires current_driver_id NOT NULL
        $requestWithoutDriver->refresh();
        $this->assertSame('pending', $requestWithoutDriver->status);
    }

    public function test_cleanup_timed_out_requests_handles_multiple_stale_requests(): void
    {
        $driverA = User::factory()->driver()->create();
        $driverB = User::factory()->driver()->create();

        $staleA = $this->createTestRideRequest([
            'status'            => 'pending',
            'current_driver_id' => $driverA->id,
            'expires_at'        => now()->subMinutes(3),
        ]);
        $staleB = $this->createTestRideRequest([
            'status'            => 'pending',
            'current_driver_id' => $driverB->id,
            'expires_at'        => now()->subMinutes(1),
        ]);

        $this->service->cleanupTimedOutRequests();

        $staleA->refresh();
        $staleB->refresh();

        $this->assertSame('expired', $staleA->status);
        $this->assertSame('expired', $staleB->status);
    }

    // -------------------------------------------------------------------------
    // applyRiderCooldown
    // -------------------------------------------------------------------------

    public function test_apply_rider_cooldown_sets_rider_cooldown_until_60_seconds_from_now(): void
    {
        $rideRequest = $this->createTestRideRequest();

        $before = Carbon::now()->subSecond();
        $this->service->applyRiderCooldown($rideRequest);
        $after = Carbon::now()->addSeconds(62);

        $rideRequest->refresh();

        $this->assertNotNull($rideRequest->rider_cooldown_until);
        $this->assertTrue(
            $rideRequest->rider_cooldown_until->between($before->addSeconds(58), $after),
            'rider_cooldown_until should be set approximately 60 seconds from now'
        );
    }

    // -------------------------------------------------------------------------
    // getRiderCooldown
    // -------------------------------------------------------------------------

    public function test_get_rider_cooldown_returns_null_when_no_active_cooldown(): void
    {
        $rider = User::factory()->rider()->create();

        $result = $this->service->getRiderCooldown($rider->id);

        $this->assertNull($result);
    }

    public function test_get_rider_cooldown_returns_null_when_cooldown_has_expired(): void
    {
        $rider = User::factory()->rider()->create();

        $this->createTestRideRequest([
            'rider_id'           => $rider->id,
            'rider_cooldown_until' => now()->subMinutes(5),
        ]);

        $result = $this->service->getRiderCooldown($rider->id);

        $this->assertNull($result);
    }

    public function test_get_rider_cooldown_returns_iso_string_when_cooldown_is_active(): void
    {
        $rider = User::factory()->rider()->create();
        $cooldownUntil = now()->addSeconds(45);

        $this->createTestRideRequest([
            'rider_id'           => $rider->id,
            'rider_cooldown_until' => $cooldownUntil,
        ]);

        $result = $this->service->getRiderCooldown($rider->id);

        $this->assertNotNull($result);
        $this->assertIsString($result);
        // ISO 8601 format check (e.g. "2026-02-27T12:34:56.000000Z")
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $result
        );
    }

    public function test_get_rider_cooldown_returns_the_latest_cooldown_when_multiple_requests(): void
    {
        $rider = User::factory()->rider()->create();
        $earlyExpiry  = now()->addSeconds(20);
        $laterExpiry  = now()->addSeconds(55);

        $this->createTestRideRequest(['rider_id' => $rider->id, 'rider_cooldown_until' => $earlyExpiry]);
        $this->createTestRideRequest(['rider_id' => $rider->id, 'rider_cooldown_until' => $laterExpiry]);

        $result = $this->service->getRiderCooldown($rider->id);

        $this->assertNotNull($result);
        // The returned ISO string should correspond to the latest cooldown
        $returnedTime = Carbon::parse($result);
        $this->assertTrue(
            $returnedTime->greaterThanOrEqualTo($earlyExpiry),
            'Should return the latest (maximum) cooldown_until value'
        );
    }

    // -------------------------------------------------------------------------
    // findTopDriver — eligibility & FIFO ordering
    // -------------------------------------------------------------------------

    public function test_find_top_driver_returns_null_when_no_drivers_in_queue(): void
    {
        $rideRequest = $this->createTestRideRequest();

        $result = $this->service->findTopDriver($rideRequest);

        $this->assertNull($result);
    }

    public function test_find_top_driver_returns_null_for_unverified_driver(): void
    {
        $rideRequest = $this->createTestRideRequest();

        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver, [
            'is_verified'     => false,
            'went_online_at'  => now(),
            'queue_joined_at' => now(),
            'current_location' => new Point(self::NEARBY_LAT, self::NEARBY_LNG, 4326),
            'max_pickup_radius_km' => 10,
        ]);

        $result = $this->service->findTopDriver($rideRequest);

        $this->assertNull($result);
    }

    public function test_find_top_driver_returns_null_for_offline_driver(): void
    {
        $rideRequest = $this->createTestRideRequest();

        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver, [
            'is_verified'     => true,
            'went_online_at'  => null,   // offline
            'queue_joined_at' => now(),
            'current_location' => new Point(self::NEARBY_LAT, self::NEARBY_LNG, 4326),
            'max_pickup_radius_km' => 10,
        ]);

        $result = $this->service->findTopDriver($rideRequest);

        $this->assertNull($result);
    }

    public function test_find_top_driver_returns_null_when_driver_outside_radius(): void
    {
        $rideRequest = $this->createTestRideRequest();

        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver, [
            'is_verified'          => true,
            'went_online_at'       => now(),
            'queue_joined_at'      => now(),
            'current_location'     => new Point(self::FAR_LAT, self::FAR_LNG, 4326),
            'max_pickup_radius_km' => 10,
        ]);

        $result = $this->service->findTopDriver($rideRequest);

        $this->assertNull($result);
    }

    public function test_find_top_driver_returns_null_when_driver_in_cooldown(): void
    {
        $rideRequest = $this->createTestRideRequest();

        $driver = User::factory()->driver()->create();
        $this->createDriverProfile($driver, [
            'is_verified'          => true,
            'went_online_at'       => now(),
            'queue_joined_at'      => now(),
            'queue_cooldown_until' => now()->addMinutes(10),
            'current_location'     => new Point(self::NEARBY_LAT, self::NEARBY_LNG, 4326),
            'max_pickup_radius_km' => 10,
        ]);

        $result = $this->service->findTopDriver($rideRequest);

        $this->assertNull($result);
    }

    public function test_find_top_driver_selects_eligible_nearby_driver(): void
    {
        $rideRequest = $this->createTestRideRequest();

        $driver = User::factory()->driver()->create();
        $this->createVerifiedDriverInQueue($driver, now()->subMinutes(5));

        $result = $this->service->findTopDriver($rideRequest);

        $this->assertNotNull($result);
        $this->assertInstanceOf(DriverProfile::class, $result);
        $this->assertSame($driver->id, $result->user_id);
    }

    public function test_find_top_driver_returns_driver_with_oldest_queue_joined_at(): void
    {
        $rideRequest = $this->createTestRideRequest();

        $driverEarly = User::factory()->driver()->create();
        $driverLate  = User::factory()->driver()->create();

        $this->createVerifiedDriverInQueue($driverEarly, now()->subMinutes(30));
        $this->createVerifiedDriverInQueue($driverLate,  now()->subMinutes(5));

        $result = $this->service->findTopDriver($rideRequest);

        $this->assertNotNull($result);
        $this->assertSame($driverEarly->id, $result->user_id);
    }

    // -------------------------------------------------------------------------
    // dispatchToDriver
    // -------------------------------------------------------------------------

    public function test_dispatch_to_driver_sets_current_driver_id_on_request(): void
    {
        $driver = User::factory()->driver()->create();
        $profile = $this->createVerifiedDriverInQueue($driver, now()->subMinutes(5));

        $rideRequest = $this->createTestRideRequest(['status' => 'pending']);

        $this->service->dispatchToDriver($rideRequest, $profile);

        $rideRequest->refresh();
        $this->assertSame($driver->id, $rideRequest->current_driver_id);
    }

    public function test_dispatch_to_driver_broadcasts_new_ride_request_event(): void
    {
        $driver = User::factory()->driver()->create();
        $profile = $this->createVerifiedDriverInQueue($driver, now()->subMinutes(5));

        $rideRequest = $this->createTestRideRequest(['status' => 'pending']);

        $this->service->dispatchToDriver($rideRequest, $profile);

        Event::assertDispatched(NewRideRequest::class, function ($event) use ($rideRequest, $driver) {
            return $event->rideRequest->id === $rideRequest->id
                && in_array($driver->id, $event->driverIds, true);
        });
    }

    public function test_dispatch_to_driver_queues_handle_request_timeout_job(): void
    {
        $driver = User::factory()->driver()->create();
        $profile = $this->createVerifiedDriverInQueue($driver, now()->subMinutes(5));

        $rideRequest = $this->createTestRideRequest(['status' => 'pending']);

        $this->service->dispatchToDriver($rideRequest, $profile);

        Bus::assertDispatched(HandleRequestTimeout::class, function ($job) use ($rideRequest, $driver) {
            return $job->rideRequestId === $rideRequest->id
                && $job->driverId === $driver->id;
        });
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Create a User + DriverProfile that satisfies all findTopDriver eligibility criteria.
     * The driver is placed at NEARBY_LAT/LNG, which is within 10 km of PICKUP_LAT/LNG.
     */
    private function createVerifiedDriverInQueue(User $user, Carbon $queueJoinedAt): DriverProfile
    {
        return $this->createDriverProfile($user, [
            'is_verified'          => true,
            'went_online_at'       => now(),
            'queue_joined_at'      => $queueJoinedAt,
            'current_location'     => new Point(self::NEARBY_LAT, self::NEARBY_LNG, 4326),
            'max_pickup_radius_km' => 10,
            'queue_cooldown_until' => null,
            'decline_count'        => 0,
            'decline_window_start' => null,
        ]);
    }

    /**
     * Create a DriverProfile for the given User, merging any field overrides.
     */
    private function createDriverProfile(User $user, array $overrides = []): DriverProfile
    {
        return DriverProfile::create(array_merge([
            'user_id'              => $user->id,
            'is_verified'          => true,
            'went_online_at'       => now(),
            'queue_joined_at'      => null,
            'queue_cooldown_until' => null,
            'max_pickup_radius_km' => 5,
            'decline_count'        => 0,
            'decline_window_start' => null,
            'current_location'     => new Point(self::NEARBY_LAT, self::NEARBY_LNG, 4326),
            'total_rides_given'    => 0,
            'rating_average'       => 5.00,
            'rating_count'         => 0,
        ], $overrides));
    }

    /**
     * Create a RideRequest with a pickup location at PICKUP_LAT/LNG.
     * Any field can be overridden; rider_id is auto-created if not supplied.
     *
     * Note: current_driver_id and rider_cooldown_until are applied via
     * forceFill() + save() because the RideRequest $fillable list predates
     * the queue migration and does not yet include those columns.
     */
    private function createTestRideRequest(array $overrides = []): RideRequest
    {
        $rider = isset($overrides['rider_id'])
            ? User::find($overrides['rider_id'])
            : User::factory()->rider()->create();

        $riderId = $rider->id;
        unset($overrides['rider_id']);

        // Separate queue-specific fields that are not in $fillable
        $extraFields = [];
        foreach (['current_driver_id', 'rider_cooldown_until'] as $field) {
            if (array_key_exists($field, $overrides)) {
                $extraFields[$field] = $overrides[$field];
                unset($overrides[$field]);
            }
        }

        $pickup      = $this->createTestPickupLocation();
        $destination = $this->createTestLocation();

        $rideRequest = RideRequest::create(array_merge([
            'rider_id'                   => $riderId,
            'pickup_location_id'         => $pickup->id,
            'destination_location_id'    => $destination->id,
            'estimated_distance_km'      => 1.5,
            'estimated_duration_minutes' => 7,
            'estimated_fare_rp'          => 10000,
            'passenger_count'            => 1,
            'status'                     => 'pending',
            'expires_at'                 => now()->addMinutes(30),
        ], $overrides));

        if (! empty($extraFields)) {
            $rideRequest->forceFill($extraFields)->save();
        }

        return $rideRequest->fresh();
    }

    /**
     * Pickup beacon at the reference coordinates used by createVerifiedDriverInQueue.
     */
    private function createTestPickupLocation(): Location
    {
        return Location::create([
            'name'        => 'Test Pickup Beacon',
            'coordinates' => new Point(self::PICKUP_LAT, self::PICKUP_LNG, 4326),
            'is_beacon'   => true,
            'is_active'   => true,
        ]);
    }

    private function createTestLocation(): Location
    {
        return Location::create([
            'name'        => 'Test Destination',
            'coordinates' => new Point(-6.3650, 106.8300, 4326),
            'is_beacon'   => false,
            'is_active'   => true,
        ]);
    }
}
