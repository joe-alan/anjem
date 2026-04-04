<?php

namespace Tests\Unit\Jobs;

use App\Events\RideRequestCancelled;
use App\Jobs\ExpireRideRequest;
use App\Models\DriverProfile;
use App\Models\Location;
use App\Models\RideRequest;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Mockery;
use Tests\TestCase;

class ExpireRideRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        Bus::fake();

        $notificationMock = Mockery::mock(NotificationService::class);
        $notificationMock->shouldReceive('sendRideRequestTimeoutToRider')
            ->andReturn(true)
            ->byDefault();
        $this->app->instance(NotificationService::class, $notificationMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Generation counter
    // -------------------------------------------------------------------------

    public function test_skips_when_generation_is_stale(): void
    {
        $rideRequest = $this->createRideRequest([
            'status' => 'pending',
            'expiry_generation' => 3, // Current generation is 3
        ]);

        // Job was dispatched with generation 1 (stale)
        $job = new ExpireRideRequest($rideRequest->id, 1);
        $job->handle(app(NotificationService::class));

        $rideRequest->refresh();
        $this->assertSame('pending', $rideRequest->status);
    }

    public function test_expires_when_generation_matches(): void
    {
        $rideRequest = $this->createRideRequest([
            'status' => 'pending',
            'expiry_generation' => 2,
        ]);

        $job = new ExpireRideRequest($rideRequest->id, 2);
        $job->handle(app(NotificationService::class));

        $rideRequest->refresh();
        $this->assertSame('expired', $rideRequest->status);
    }

    public function test_expires_with_default_zero_generation(): void
    {
        $rideRequest = $this->createRideRequest([
            'status' => 'pending',
            'expiry_generation' => 0,
        ]);

        // Default expectedGeneration = 0
        $job = new ExpireRideRequest($rideRequest->id);
        $job->handle(app(NotificationService::class));

        $rideRequest->refresh();
        $this->assertSame('expired', $rideRequest->status);
    }

    // -------------------------------------------------------------------------
    // Existing behavior preserved
    // -------------------------------------------------------------------------

    public function test_skips_when_request_already_matched(): void
    {
        $rideRequest = $this->createRideRequest([
            'status' => 'matched',
            'expiry_generation' => 0,
        ]);

        $job = new ExpireRideRequest($rideRequest->id, 0);
        $job->handle(app(NotificationService::class));

        $rideRequest->refresh();
        $this->assertSame('matched', $rideRequest->status);
    }

    public function test_defers_when_driver_dispatched_during_countdown(): void
    {
        $driver = $this->createDriver();
        $rideRequest = $this->createRideRequest([
            'status' => 'pending',
            'current_driver_id' => $driver->id,
            'expiry_generation' => 1,
        ]);

        $job = new ExpireRideRequest($rideRequest->id, 1);
        $job->handle(app(NotificationService::class));

        // Request should still be pending (deferred, not expired)
        $rideRequest->refresh();
        $this->assertSame('pending', $rideRequest->status);

        // A new ExpireRideRequest job should have been dispatched
        Bus::assertDispatched(ExpireRideRequest::class);
    }

    public function test_skips_when_request_not_found(): void
    {
        $job = new ExpireRideRequest(999999, 0);
        $job->handle(app(NotificationService::class));

        // No exception = pass
        $this->assertTrue(true);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createDriver(): User
    {
        $user = User::factory()->driver()->create(['is_active' => true]);

        DriverProfile::create([
            'user_id' => $user->id,
            'is_verified' => true,
            'went_online_at' => now()->subHour(),
            'queue_joined_at' => now()->subMinutes(30),
            'max_pickup_radius_km' => 5.00,
            'decline_count' => 0,
            'total_rides_given' => 10,
        ]);

        return $user;
    }

    private function createRideRequest(array $overrides = []): RideRequest
    {
        $rider = User::factory()->rider()->create();
        $pickup = Location::create([
            'name' => 'Test Pickup',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);
        $destination = Location::create([
            'name' => 'Test Destination',
            'coordinates' => new Point(-6.3650, 106.8300, 4326),
            'is_beacon' => false,
            'is_active' => true,
        ]);

        $rideRequest = new RideRequest([
            'rider_id' => $rider->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $destination->id,
            'estimated_distance_km' => 2.50,
            'estimated_duration_minutes' => 8,
            'estimated_fare_rp' => 10000,
            'passenger_count' => 1,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);

        $rideRequest->forceFill(array_filter([
            'current_driver_id' => $overrides['current_driver_id'] ?? null,
            'expiry_generation' => $overrides['expiry_generation'] ?? 0,
        ], fn ($v) => $v !== null));

        unset($overrides['current_driver_id'], $overrides['expiry_generation']);
        if (! empty($overrides)) {
            $rideRequest->forceFill($overrides);
        }

        $rideRequest->save();

        return $rideRequest;
    }
}
