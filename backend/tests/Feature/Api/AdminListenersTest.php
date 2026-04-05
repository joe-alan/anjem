<?php

namespace Tests\Feature\Api;

use App\Events\AdminLiveUpdate;
use App\Events\DriverCreditsUpdated;
use App\Events\DriverKycStatusChanged;
use App\Events\DriverOnlineStatusChanged;
use App\Events\NewRideRequest;
use App\Events\RideRequestCancelled;
use App\Events\RideStatusUpdated;
use App\Listeners\BroadcastAdminCreditsUpdate;
use App\Listeners\BroadcastAdminDriverRejoined;
use App\Listeners\BroadcastAdminDriverStatusChange;
use App\Listeners\BroadcastAdminKycUpdate;
use App\Listeners\BroadcastAdminMatchingDispatch;
use App\Listeners\BroadcastAdminNewRideRequest;
use App\Listeners\BroadcastAdminRideRequestCancelled;
use App\Listeners\BroadcastAdminRideStatusUpdate;
use App\Models\DriverProfile;
use App\Models\Location;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

class AdminListenersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([AdminLiveUpdate::class]);
    }

    // -------------------------------------------------------------------------
    // BroadcastAdminRideStatusUpdate
    // -------------------------------------------------------------------------

    public function test_ride_status_listener_fires_admin_live_update_with_ride_status_type(): void
    {
        $ride = $this->createRide(['status' => 'accepted']);

        (new BroadcastAdminRideStatusUpdate)->handle(
            new RideStatusUpdated($ride, 'matched', 'driver', false)
        );

        Event::assertDispatched(AdminLiveUpdate::class, function (AdminLiveUpdate $e) use ($ride) {
            return $e->type === 'ride_status'
                && $e->payload['ride_id'] === $ride->id
                && $e->payload['status'] === 'accepted'
                && $e->payload['previous_status'] === 'matched'
                && $e->payload['admin_override'] === false;
        });
    }

    // -------------------------------------------------------------------------
    // BroadcastAdminNewRideRequest
    // -------------------------------------------------------------------------

    public function test_new_ride_request_listener_fires_with_new_request_type(): void
    {
        $rider = User::factory()->rider()->create(['name' => 'Alice']);
        $request = $this->createRideRequest(['rider_id' => $rider->id]);

        (new BroadcastAdminNewRideRequest)->handle(
            new NewRideRequest($request, [1, 2, 3])
        );

        Event::assertDispatched(AdminLiveUpdate::class, function (AdminLiveUpdate $e) use ($request) {
            return $e->type === 'new_request'
                && $e->payload['request_id'] === $request->id
                && $e->payload['rider_name'] === 'Alice'
                && $e->payload['driver_ids'] === [1, 2, 3];
        });
    }

    // -------------------------------------------------------------------------
    // BroadcastAdminRideRequestCancelled
    // -------------------------------------------------------------------------

    public function test_ride_request_cancelled_listener_fires_with_correct_payload(): void
    {
        $rider = User::factory()->rider()->create();
        $request = $this->createRideRequest(['rider_id' => $rider->id]);

        (new BroadcastAdminRideRequestCancelled)->handle(
            new RideRequestCancelled($request, 'admin', 'test cancellation')
        );

        Event::assertDispatched(AdminLiveUpdate::class, function (AdminLiveUpdate $e) use ($request) {
            return $e->type === 'request_cancelled'
                && $e->payload['request_id'] === $request->id
                && $e->payload['rider_id'] === $request->rider_id
                && $e->payload['reason'] === 'test cancellation';
        });
    }

    // -------------------------------------------------------------------------
    // BroadcastAdminDriverStatusChange
    // -------------------------------------------------------------------------

    public function test_driver_status_change_listener_fires_with_driver_status_type(): void
    {
        $driver = User::factory()->driver()->create(['name' => 'Bob']);
        $beacon = $this->createBeacon();

        (new BroadcastAdminDriverStatusChange)->handle(
            new DriverOnlineStatusChanged($driver, true, $beacon, 2)
        );

        Event::assertDispatched(AdminLiveUpdate::class, function (AdminLiveUpdate $e) use ($driver, $beacon) {
            return $e->type === 'driver_status'
                && $e->payload['driver_id'] === $driver->id
                && $e->payload['driver_name'] === 'Bob'
                && $e->payload['is_online'] === true
                && $e->payload['beacon_id'] === $beacon->id
                && $e->payload['queue_position'] === 2;
        });
    }

    public function test_driver_status_change_listener_handles_going_offline(): void
    {
        $driver = User::factory()->driver()->create();

        (new BroadcastAdminDriverStatusChange)->handle(
            new DriverOnlineStatusChanged($driver, false)
        );

        Event::assertDispatched(AdminLiveUpdate::class, function (AdminLiveUpdate $e) use ($driver) {
            return $e->type === 'driver_status'
                && $e->payload['is_online'] === false
                && $e->payload['beacon_id'] === null;
        });
    }

    // -------------------------------------------------------------------------
    // BroadcastAdminKycUpdate
    // -------------------------------------------------------------------------

    public function test_kyc_update_listener_fires_with_verified_status(): void
    {
        $driver = User::factory()->driver()->create(['name' => 'Charlie']);

        (new BroadcastAdminKycUpdate)->handle(
            new DriverKycStatusChanged($driver, true)
        );

        Event::assertDispatched(AdminLiveUpdate::class, function (AdminLiveUpdate $e) use ($driver) {
            return $e->type === 'kyc_updated'
                && $e->payload['driver_id'] === $driver->id
                && $e->payload['driver_name'] === 'Charlie'
                && $e->payload['kyc_status'] === 'verified';
        });
    }

    public function test_kyc_update_listener_fires_with_rejected_status(): void
    {
        $driver = User::factory()->driver()->create();

        (new BroadcastAdminKycUpdate)->handle(
            new DriverKycStatusChanged($driver, false)
        );

        Event::assertDispatched(AdminLiveUpdate::class, function (AdminLiveUpdate $e) {
            return $e->payload['kyc_status'] === 'rejected';
        });
    }

    // -------------------------------------------------------------------------
    // BroadcastAdminCreditsUpdate
    // -------------------------------------------------------------------------

    public function test_credits_update_listener_calculates_credits_before_for_grant(): void
    {
        $driver = User::factory()->driver()->create();

        // grant 5 credits, new balance = 15 → before = 10
        (new BroadcastAdminCreditsUpdate)->handle(
            new DriverCreditsUpdated($driver, 15, 5, 'grant')
        );

        Event::assertDispatched(AdminLiveUpdate::class, function (AdminLiveUpdate $e) use ($driver) {
            return $e->type === 'credits_updated'
                && $e->payload['driver_id'] === $driver->id
                && $e->payload['credits_before'] === 10
                && $e->payload['credits_after'] === 15
                && $e->payload['reason'] === 'grant';
        });
    }

    public function test_credits_update_listener_calculates_credits_before_for_deduct(): void
    {
        $driver = User::factory()->driver()->create();

        // deduct 3 credits, new balance = 7 → before = 10
        (new BroadcastAdminCreditsUpdate)->handle(
            new DriverCreditsUpdated($driver, 7, 3, 'deduct')
        );

        Event::assertDispatched(AdminLiveUpdate::class, function (AdminLiveUpdate $e) {
            return $e->payload['credits_before'] === 10
                && $e->payload['credits_after'] === 7;
        });
    }

    // -------------------------------------------------------------------------
    // BroadcastAdminMatchingDispatch
    // -------------------------------------------------------------------------

    public function test_matching_dispatch_listener_fires_with_full_driver_ids_array(): void
    {
        $rider = User::factory()->rider()->create();
        $request = $this->createRideRequest(['rider_id' => $rider->id]);

        (new BroadcastAdminMatchingDispatch)->handle(
            new NewRideRequest($request, [10, 11, 12])
        );

        Event::assertDispatched(AdminLiveUpdate::class, function (AdminLiveUpdate $e) use ($request) {
            return $e->type === 'matching_dispatch'
                && $e->payload['request_id'] === $request->id
                && $e->payload['driver_ids'] === [10, 11, 12];
        });
    }

    // -------------------------------------------------------------------------
    // BroadcastAdminDriverRejoined
    // -------------------------------------------------------------------------

    public function test_driver_rejoined_listener_fires_when_ride_completed(): void
    {
        $ride = $this->createRide(['status' => 'completed']);

        (new BroadcastAdminDriverRejoined)->handle(
            new RideStatusUpdated($ride, 'in_progress')
        );

        Event::assertDispatched(AdminLiveUpdate::class, function (AdminLiveUpdate $e) use ($ride) {
            return $e->type === 'driver_rejoined'
                && $e->payload['ride_id'] === $ride->id
                && $e->payload['driver_id'] === $ride->driver_id
                && $e->payload['completed_status'] === 'completed';
        });
    }

    public function test_driver_rejoined_listener_fires_when_ride_cancelled(): void
    {
        $ride = $this->createRide(['status' => 'cancelled']);

        (new BroadcastAdminDriverRejoined)->handle(
            new RideStatusUpdated($ride, 'accepted')
        );

        Event::assertDispatched(AdminLiveUpdate::class, function (AdminLiveUpdate $e) {
            return $e->type === 'driver_rejoined'
                && $e->payload['completed_status'] === 'cancelled';
        });
    }

    public function test_driver_rejoined_listener_does_not_fire_for_non_terminal_status(): void
    {
        $ride = $this->createRide(['status' => 'in_progress']);

        (new BroadcastAdminDriverRejoined)->handle(
            new RideStatusUpdated($ride, 'accepted')
        );

        Event::assertNotDispatched(AdminLiveUpdate::class);
    }

    public function test_driver_rejoined_listener_does_not_fire_when_no_driver(): void
    {
        $ride = $this->createRide(['status' => 'completed']);
        $ride->driver_id = null; // simulate ride with no driver (in-memory, avoids NOT NULL constraint)

        (new BroadcastAdminDriverRejoined)->handle(
            new RideStatusUpdated($ride, 'in_progress')
        );

        Event::assertNotDispatched(AdminLiveUpdate::class);
    }

    // -------------------------------------------------------------------------
    // EventServiceProvider wiring
    // -------------------------------------------------------------------------

    public function test_event_service_provider_wires_listeners_to_source_events(): void
    {
        $listeners = \Illuminate\Support\Facades\Event::getRawListeners();

        $this->assertContains(
            BroadcastAdminRideStatusUpdate::class,
            $listeners[RideStatusUpdated::class] ?? []
        );
        $this->assertContains(
            BroadcastAdminDriverRejoined::class,
            $listeners[RideStatusUpdated::class] ?? []
        );
        $this->assertContains(
            BroadcastAdminNewRideRequest::class,
            $listeners[NewRideRequest::class] ?? []
        );
        $this->assertContains(
            BroadcastAdminMatchingDispatch::class,
            $listeners[NewRideRequest::class] ?? []
        );
        $this->assertContains(
            BroadcastAdminRideRequestCancelled::class,
            $listeners[RideRequestCancelled::class] ?? []
        );
        $this->assertContains(
            BroadcastAdminDriverStatusChange::class,
            $listeners[DriverOnlineStatusChanged::class] ?? []
        );
        $this->assertContains(
            BroadcastAdminKycUpdate::class,
            $listeners[DriverKycStatusChanged::class] ?? []
        );
        $this->assertContains(
            BroadcastAdminCreditsUpdate::class,
            $listeners[DriverCreditsUpdated::class] ?? []
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createRide(array $overrides = []): Ride
    {
        $rider = User::factory()->rider()->create();
        $driver = User::factory()->driver()->create();
        $pickup = $this->createLocation();
        $dest = $this->createLocation();

        $request = RideRequest::create([
            'rider_id' => $rider->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $dest->id,
            'estimated_distance_km' => 1.0,
            'estimated_duration_minutes' => 5,
            'estimated_fare_rp' => 8000,
            'passenger_count' => 1,
            'status' => 'matched',
            'expires_at' => now()->addMinutes(30),
        ]);

        return Ride::create(array_merge([
            'ride_request_id' => $request->id,
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $dest->id,
            'passenger_count' => 1,
            'estimated_fare_rp' => 8000,
            'status' => 'accepted',
        ], $overrides));
    }

    private function createRideRequest(array $overrides = []): RideRequest
    {
        $rider = $overrides['rider_id'] ?? User::factory()->rider()->create()->id;
        $pickup = $this->createLocation();
        $dest = $this->createLocation();

        return RideRequest::create(array_merge([
            'rider_id' => $rider,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $dest->id,
            'estimated_distance_km' => 1.0,
            'estimated_duration_minutes' => 5,
            'estimated_fare_rp' => 8000,
            'passenger_count' => 1,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ], array_diff_key($overrides, ['rider_id' => true])));
    }

    private function createLocation(): Location
    {
        return Location::create([
            'name' => 'Test Location',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => false,
            'is_active' => true,
        ]);
    }

    private function createBeacon(): Location
    {
        return Location::create([
            'name' => 'Test Beacon',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);
    }
}
