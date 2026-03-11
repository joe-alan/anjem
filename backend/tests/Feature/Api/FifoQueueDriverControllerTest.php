<?php

namespace Tests\Feature\Api;

use App\Events\DriverOnlineStatusChanged;
use App\Events\MatchingQueuePositionChanged;
use App\Events\SessionReplaced;
use App\Models\DriverProfile;
use App\Models\Location;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

class FifoQueueDriverControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        Bus::fake();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create a verified driver User with an associated DriverProfile.
     *
     * The returned User has role=driver, is_active=true, is_verified=true,
     * and a DriverProfile with the minimum columns needed to pass the goOnline
     * checks. went_online_at is intentionally left null so that each test
     * controls the online state itself.
     */
    private function createVerifiedDriver(array $userOverrides = [], array $profileOverrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'role' => 'driver',
            'is_active' => true,
        ], $userOverrides));

        DriverProfile::create(array_merge([
            'user_id' => $user->id,
            'vehicle_type' => 'motorcycle',
            'vehicle_plate' => 'B' . rand(1000, 9999) . 'XYZ',
            'is_verified' => true,
            'email_verified_at' => Carbon::now(),
            'went_online_at' => null,
            'queue_joined_at' => null,
            'max_pickup_radius_km' => 5.0,
        ], $profileOverrides));

        return $user;
    }

    /**
     * Build an Authorization header array for Sanctum token auth.
     */
    private function driverHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ];
    }

    /**
     * Create a minimal RideRequest (with required Location rows) for tests
     * that only need a ride_request_id to satisfy the rides.ride_request_id FK.
     */
    private function createMinimalRideRequest(User $rider, array $overrides = []): RideRequest
    {
        $pickup = Location::create([
            'name' => 'Test Pickup',
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $destination = Location::create([
            'name' => 'Test Destination',
            'coordinates' => new Point(-6.3595, 106.8295, 4326),
            'is_beacon' => false,
            'is_active' => true,
        ]);

        return RideRequest::create(array_merge([
            'rider_id' => $rider->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $destination->id,
            'estimated_distance_km' => 2.0,
            'estimated_duration_minutes' => 8,
            'estimated_fare_rp' => 8000,
            'passenger_count' => 1,
            'status' => 'pending',
            'expires_at' => Carbon::now()->addMinutes(30),
        ], $overrides));
    }

    // =========================================================================
    // goOnline() tests
    // =========================================================================

    /**
     * Test 1: A verified driver who has no active rides goes online.
     * queue_joined_at must be set and the response must contain queue_position.
     */
    public function test_driver_goes_online_and_is_added_to_fifo_queue(): void
    {
        $driver = $this->createVerifiedDriver();
        $token = $driver->createTokenWithAbilities(true);

        $response = $this->withHeaders($this->driverHeaders($token))
            ->postJson('/api/v1/driver/online');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'status',
                    'is_available',
                    'driver_id',
                    'queue_position',
                ],
            ]);

        $this->assertEquals('online', $response->json('data.status'));
        $this->assertTrue($response->json('data.is_available'));

        // queue_joined_at must be set in the database
        $driver->refresh();
        $this->assertNotNull($driver->driverProfile->queue_joined_at);
    }

    /**
     * Test 2: Two drivers are already in the queue. A third driver joins and
     * should receive queue_position = 3.
     */
    public function test_go_online_returns_correct_queue_position(): void
    {
        // Two drivers that joined the queue earlier
        $driver1 = $this->createVerifiedDriver([], [
            'queue_joined_at' => Carbon::now()->subMinutes(10),
            'went_online_at' => Carbon::now()->subMinutes(10),
        ]);
        $driver2 = $this->createVerifiedDriver([], [
            'queue_joined_at' => Carbon::now()->subMinutes(5),
            'went_online_at' => Carbon::now()->subMinutes(5),
        ]);

        // Third driver goes online now
        $driver3 = $this->createVerifiedDriver();
        $token3 = $driver3->createTokenWithAbilities(true);

        $response = $this->withHeaders($this->driverHeaders($token3))
            ->postJson('/api/v1/driver/online');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Position must be 3 (behind the two already-queued drivers)
        $this->assertEquals(3, $response->json('data.queue_position'));
    }

    /**
     * Test 3: An unverified driver (is_verified=false) cannot go online → 403.
     */
    public function test_unverified_driver_cannot_go_online(): void
    {
        $driver = $this->createVerifiedDriver([], ['is_verified' => false]);
        $token = $driver->createTokenWithAbilities(true);

        $response = $this->withHeaders($this->driverHeaders($token))
            ->postJson('/api/v1/driver/online');

        $response->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    /**
     * Test 4: A driver who already has an active ride as a driver cannot go
     * online → 400.
     *
     * ACTIVE_STATUSES = ['matched', 'accepted', 'driver_arrived', 'in_progress'].
     */
    public function test_driver_with_active_driver_ride_cannot_go_online(): void
    {
        $driver = $this->createVerifiedDriver();
        $token = $driver->createTokenWithAbilities(true);

        // Create an accepted ride where this user is the driver
        $rider = User::factory()->create(['role' => 'rider']);
        $rideRequest = $this->createMinimalRideRequest($rider);

        Ride::create([
            'ride_request_id' => $rideRequest->id,
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'status' => 'accepted',
            'estimated_fare_rp' => 5000,
        ]);

        $response = $this->withHeaders($this->driverHeaders($token))
            ->postJson('/api/v1/driver/online');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'You already have an active ride',
            ]);
    }

    /**
     * Test 5: A driver who has an active rider-side ride (status = matched)
     * cannot go online → 400.
     */
    public function test_driver_with_active_rider_ride_cannot_go_online(): void
    {
        // This user is both a rider and a driver (role=both covers canBeDriver())
        $user = User::factory()->create(['role' => 'both', 'is_active' => true]);

        DriverProfile::create([
            'user_id' => $user->id,
            'vehicle_type' => 'motorcycle',
            'vehicle_plate' => 'B9999RDR',
            'is_verified' => true,
            'email_verified_at' => Carbon::now(),
            'went_online_at' => null,
            'queue_joined_at' => null,
            'max_pickup_radius_km' => 5.0,
        ]);

        $token = $user->createTokenWithAbilities(true);

        // Create a ride where this user is the rider (status in matched/accepted/etc.)
        $otherDriver = $this->createVerifiedDriver();
        // Use an already-expired RideRequest so the "active ride request" check passes
        // and only the "active ride as rider" check fires.
        $rideRequest = $this->createMinimalRideRequest($user, [
            'status' => 'expired',
            'expires_at' => Carbon::now()->subMinutes(5),
        ]);

        Ride::create([
            'ride_request_id' => $rideRequest->id,
            'rider_id' => $user->id,
            'driver_id' => $otherDriver->id,
            'status' => 'matched',
            'estimated_fare_rp' => 5000,
        ]);

        $response = $this->withHeaders($this->driverHeaders($token))
            ->postJson('/api/v1/driver/online');

        $response->assertStatus(400)
            ->assertJson(['success' => false]);

        $this->assertStringContainsString(
            'active ride as a rider',
            $response->json('message')
        );
    }

    /**
     * Test 6: A driver who has a pending ride request as a rider (not yet
     * expired) cannot go online → 400.
     */
    public function test_driver_with_pending_rider_request_cannot_go_online(): void
    {
        $user = User::factory()->create(['role' => 'both', 'is_active' => true]);

        DriverProfile::create([
            'user_id' => $user->id,
            'vehicle_type' => 'motorcycle',
            'vehicle_plate' => 'B8888RDR',
            'is_verified' => true,
            'email_verified_at' => Carbon::now(),
            'went_online_at' => null,
            'queue_joined_at' => null,
            'max_pickup_radius_km' => 5.0,
        ]);

        $token = $user->createTokenWithAbilities(true);

        // Create a pending ride request that has not yet expired
        $this->createMinimalRideRequest($user, [
            'status' => 'pending',
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        $response = $this->withHeaders($this->driverHeaders($token))
            ->postJson('/api/v1/driver/online');

        $response->assertStatus(400)
            ->assertJson(['success' => false]);

        $this->assertStringContainsString(
            'active ride request',
            $response->json('message')
        );
    }

    /**
     * Test 7: Going online with only a single existing session must NOT
     * broadcast SessionReplaced.
     */
    public function test_go_online_with_single_session_does_not_broadcast_session_replaced(): void
    {
        $driver = $this->createVerifiedDriver();
        // Only one token — no other sessions exist
        $token = $driver->createTokenWithAbilities(true);

        $response = $this->withHeaders($this->driverHeaders($token))
            ->postJson('/api/v1/driver/online');

        $response->assertStatus(200);

        Event::assertNotDispatched(SessionReplaced::class);
    }

    /**
     * Test 8: Going online when two other sessions exist must revoke those
     * tokens and broadcast SessionReplaced exactly once.
     */
    public function test_go_online_revokes_old_tokens_and_broadcasts_session_replaced_on_multiple_sessions(): void
    {
        $driver = $this->createVerifiedDriver();

        // Create two "stale" tokens (old sessions on other devices)
        $driver->createTokenWithAbilities(true); // stale token 1
        $driver->createTokenWithAbilities(true); // stale token 2

        // The "current" token — the one used to call goOnline
        $currentToken = $driver->createTokenWithAbilities(true);

        // Sanity: 3 tokens exist before the request
        $this->assertCount(3, $driver->tokens()->get());

        $response = $this->withHeaders($this->driverHeaders($currentToken))
            ->postJson('/api/v1/driver/online');

        $response->assertStatus(200);

        // SessionReplaced must have been broadcast once
        Event::assertDispatched(SessionReplaced::class, function (SessionReplaced $event) use ($driver) {
            // SessionReplaced stores driverId as a private property; verify via
            // broadcastWith() which exposes it.
            $payload = $event->broadcastWith();
            return $payload['driver_id'] === $driver->id;
        });

        // The two stale tokens must have been deleted; only the current one remains
        $driver->refresh();
        $this->assertCount(1, $driver->tokens()->get());
    }

    // =========================================================================
    // goOffline() tests
    // =========================================================================

    /**
     * Test 9: A driver currently in the queue goes offline. Afterwards both
     * queue_joined_at and went_online_at must be null.
     */
    public function test_driver_goes_offline_and_is_removed_from_queue(): void
    {
        $driver = $this->createVerifiedDriver([], [
            'went_online_at' => Carbon::now()->subMinutes(30),
            'queue_joined_at' => Carbon::now()->subMinutes(30),
        ]);
        $token = $driver->createTokenWithAbilities(true);

        $response = $this->withHeaders($this->driverHeaders($token))
            ->postJson('/api/v1/driver/offline');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Successfully went offline',
            ]);

        $driver->refresh();
        $this->assertNull($driver->driverProfile->queue_joined_at);
        $this->assertNull($driver->driverProfile->went_online_at);
    }

    /**
     * Test 10: A driver with an active ride (status = accepted) cannot go
     * offline → 400.
     */
    public function test_driver_with_active_ride_cannot_go_offline(): void
    {
        $driver = $this->createVerifiedDriver([], [
            'went_online_at' => Carbon::now()->subMinutes(15),
            'queue_joined_at' => Carbon::now()->subMinutes(15),
        ]);
        $token = $driver->createTokenWithAbilities(true);

        $rider = User::factory()->create(['role' => 'rider']);
        $rideRequest = $this->createMinimalRideRequest($rider);

        Ride::create([
            'ride_request_id' => $rideRequest->id,
            'rider_id' => $rider->id,
            'driver_id' => $driver->id,
            'status' => 'accepted',
            'estimated_fare_rp' => 8000,
        ]);

        $response = $this->withHeaders($this->driverHeaders($token))
            ->postJson('/api/v1/driver/offline');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot go offline while you have an active ride',
            ]);

        // The driver must still be in the queue
        $driver->refresh();
        $this->assertNotNull($driver->driverProfile->queue_joined_at);
    }

    // =========================================================================
    // getQueuePosition() tests
    // =========================================================================

    /**
     * Test 11: A driver who is in the queue gets a well-formed response that
     * includes in_queue, queue_position, is_in_cooldown, cooldown_until, and
     * max_pickup_radius_km.
     */
    public function test_get_queue_position_returns_position_and_cooldown_status(): void
    {
        $driver = $this->createVerifiedDriver([], [
            'went_online_at' => Carbon::now()->subMinutes(5),
            'queue_joined_at' => Carbon::now()->subMinutes(5),
            'max_pickup_radius_km' => 7.5,
            'queue_cooldown_until' => null,
        ]);
        $token = $driver->createTokenWithAbilities(true);

        $response = $this->withHeaders($this->driverHeaders($token))
            ->getJson('/api/v1/driver/queue-position');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'in_queue',
                    'queue_position',
                    'is_in_cooldown',
                    'cooldown_until',
                    'max_pickup_radius_km',
                ],
            ]);

        $data = $response->json('data');

        $this->assertTrue($data['in_queue']);
        $this->assertGreaterThanOrEqual(1, $data['queue_position']);
        $this->assertFalse($data['is_in_cooldown']);
        $this->assertNull($data['cooldown_until']);
        $this->assertEquals(7.5, $data['max_pickup_radius_km']);
    }

    /**
     * Test 12: A driver who has never joined the queue (queue_joined_at = null)
     * gets queue_position = 0 and in_queue = false.
     */
    public function test_get_queue_position_returns_zero_when_not_in_queue(): void
    {
        $driver = $this->createVerifiedDriver([], [
            'went_online_at' => null,
            'queue_joined_at' => null,
        ]);
        $token = $driver->createTokenWithAbilities(true);

        $response = $this->withHeaders($this->driverHeaders($token))
            ->getJson('/api/v1/driver/queue-position');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $response->json('data');

        $this->assertFalse($data['in_queue']);
        $this->assertEquals(0, $data['queue_position']);
    }
}
