<?php

namespace Tests\Feature\Api;

use App\Events\RideRequestCancelled;
use App\Models\DriverProfile;
use App\Models\Location;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\User;
use App\Services\MatchingQueueService;
use App\Services\RideService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

class FifoQueueRideFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $rider;
    private User $driver;
    private string $adminToken;
    private string $driverToken;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        Bus::fake();

        // Admin user
        $this->admin = User::factory()->create([
            'email' => 'admin@fifo-test.com',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->adminToken = $this->admin->createTokenWithAbilities(false, true);

        // Rider user
        $this->rider = User::factory()->create([
            'email' => 'rider@fifo-test.com',
            'role' => 'rider',
            'is_active' => true,
        ]);

        // Driver user with a queue-ready profile
        $this->driver = $this->createVerifiedDriverWithQueue();
        $this->driverToken = $this->driver->createTokenWithAbilities(true);
    }

    // ==========================================================================
    // HELPERS
    // ==========================================================================

    /**
     * Create a verified driver User + DriverProfile that is in the FIFO queue.
     */
    private function createVerifiedDriverWithQueue(array $userOverrides = [], array $profileOverrides = []): User
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
            'email_verified_at' => now(),
            'went_online_at' => now(),
            'queue_joined_at' => now(),
            'max_pickup_radius_km' => 5.0,
            'current_location' => new Point(-6.3605, 106.8271, 4326),
        ], $profileOverrides));

        return $user;
    }

    /**
     * Create test Location rows and a RideRequest.
     */
    private function createTestRideRequest(array $overrides = []): RideRequest
    {
        $pickup = Location::create([
            'name' => 'Test Pickup ' . uniqid(),
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $destination = Location::create([
            'name' => 'Test Destination ' . uniqid(),
            'coordinates' => new Point(-6.3595, 106.8295, 4326),
            'is_beacon' => true,
            'is_active' => true,
        ]);

        $attributes = array_merge([
            'rider_id' => $this->rider->id,
            'pickup_location_id' => $pickup->id,
            'destination_location_id' => $destination->id,
            'passenger_count' => 1,
            'estimated_fare_rp' => 10000,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
        ], $overrides);

        $rideRequest = new RideRequest();
        $rideRequest->fill(array_intersect_key($attributes, array_flip($rideRequest->getFillable())));
        $rideRequest->save();

        // Set non-fillable queue fields via the query builder
        $queueFields = array_intersect_key($attributes, array_flip(['current_driver_id', 'rider_cooldown_until']));
        if (! empty($queueFields)) {
            DB::table('ride_requests')->where('id', $rideRequest->id)->update($queueFields);
            $rideRequest->refresh();
        }

        return $rideRequest;
    }

    /**
     * Create a RideRequest and an associated Ride.
     */
    private function createTestRide(array $overrides = []): array
    {
        // 'status' and 'driver_id' apply to the Ride, not the RideRequest
        $rideRequestOverrides = array_intersect_key($overrides, array_flip([
            'rider_id', 'pickup_location_id', 'destination_location_id',
            'passenger_count', 'expires_at', 'current_driver_id', 'rider_cooldown_until',
        ]));

        $rideRequest = $this->createTestRideRequest($rideRequestOverrides);

        $rideOverrides = array_diff_key($overrides, $rideRequestOverrides);

        $ride = Ride::create(array_merge([
            'ride_request_id' => $rideRequest->id,
            'rider_id' => $this->rider->id,
            'driver_id' => $this->driver->id,
            'pickup_location_id' => $rideRequest->pickup_location_id,
            'destination_location_id' => $rideRequest->destination_location_id,
            'status' => 'accepted',
            'estimated_fare_rp' => 10000,
            'passenger_count' => 1,
            'driver_accepted_at' => now(),
        ], $rideOverrides));

        return [$rideRequest, $ride];
    }

    /**
     * Build an Authorization header array.
     */
    private function authHeader(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ];
    }

    // ==========================================================================
    // DECLINE TESTS  (POST /api/v1/rides/{rideRequestId}/decline)
    // ==========================================================================

    /**
     * Test 1: Driver assigned as current_driver_id can decline a pending request.
     * MatchingQueueService::handleDeclineOrTimeout should be invoked.
     */
    public function test_driver_can_decline_assigned_ride_request(): void
    {
        $this->mock(MatchingQueueService::class, function ($mock) {
            $mock->shouldReceive('handleDeclineOrTimeout')
                ->once()
                ->andReturn(null);
        });

        $rideRequest = $this->createTestRideRequest([
            'status' => 'pending',
            'current_driver_id' => $this->driver->id,
        ]);

        $response = $this->withHeaders($this->authHeader($this->driverToken))
            ->postJson("/api/v1/rides/{$rideRequest->id}/decline");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Ride request declined',
            ]);
    }

    /**
     * Test 2: A driver who is NOT the current_driver_id receives 403.
     */
    public function test_driver_cannot_decline_request_not_assigned_to_them(): void
    {
        $anotherDriver = $this->createVerifiedDriverWithQueue();
        $anotherToken = $anotherDriver->createTokenWithAbilities(true);

        $rideRequest = $this->createTestRideRequest([
            'status' => 'pending',
            'current_driver_id' => $this->driver->id,
        ]);

        $response = $this->withHeaders($this->authHeader($anotherToken))
            ->postJson("/api/v1/rides/{$rideRequest->id}/decline");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You are not the current driver for this request',
            ]);
    }

    /**
     * Test 3: A driver cannot decline a request that is already accepted.
     */
    public function test_driver_cannot_decline_already_accepted_request(): void
    {
        $rideRequest = $this->createTestRideRequest([
            'status' => 'pending',
            'current_driver_id' => $this->driver->id,
        ]);

        // Force the status to 'in_progress' (outside pending/matched) via query builder
        DB::table('ride_requests')->where('id', $rideRequest->id)->update(['status' => 'in_progress']);
        $rideRequest->refresh();

        $response = $this->withHeaders($this->authHeader($this->driverToken))
            ->postJson("/api/v1/rides/{$rideRequest->id}/decline");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'This ride request is no longer active',
            ]);
    }

    /**
     * Test 4: A token without the driver:accept-ride ability gets 403 on decline.
     */
    public function test_decline_requires_driver_accept_ride_token(): void
    {
        // Rider token has no driver:accept-ride ability
        $riderToken = $this->rider->createToken('mobile', ['rider:cancel-ride'])->plainTextToken;

        $rideRequest = $this->createTestRideRequest([
            'status' => 'pending',
            'current_driver_id' => $this->driver->id,
        ]);

        $response = $this->withHeaders($this->authHeader($riderToken))
            ->postJson("/api/v1/rides/{$rideRequest->id}/decline");

        $response->assertStatus(403);
    }

    // ==========================================================================
    // RIDER CANCEL TESTS  (PATCH /api/v1/requests/{ride_request}/cancel)
    // ==========================================================================

    /**
     * Test 5: Cancelling a pending request applies the rider cooldown.
     */
    public function test_rider_cancel_applies_cooldown(): void
    {
        $riderToken = $this->rider->createToken('mobile', ['rider:cancel-ride'])->plainTextToken;

        $rideRequest = $this->createTestRideRequest([
            'rider_id' => $this->rider->id,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($this->authHeader($riderToken))
            ->patchJson("/api/v1/requests/{$rideRequest->id}/cancel");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // meta.cooldown_until must be present and non-null
        $cooldownUntil = $response->json('meta.cooldown_until');
        $this->assertNotNull($cooldownUntil, 'meta.cooldown_until should be set after cancellation');

        // Verify DB has the cooldown timestamp
        $this->assertNotNull(
            DB::table('ride_requests')->where('id', $rideRequest->id)->value('rider_cooldown_until'),
            'rider_cooldown_until should be stored in the database'
        );
    }

    /**
     * Test 6: Cancelling a request with a current driver broadcasts RideRequestCancelled.
     */
    public function test_rider_cancel_broadcasts_cancellation_to_driver(): void
    {
        $riderToken = $this->rider->createToken('mobile', ['rider:cancel-ride'])->plainTextToken;

        $rideRequest = $this->createTestRideRequest([
            'rider_id' => $this->rider->id,
            'status' => 'pending',
            'current_driver_id' => $this->driver->id,
        ]);

        $response = $this->withHeaders($this->authHeader($riderToken))
            ->patchJson("/api/v1/requests/{$rideRequest->id}/cancel");

        $response->assertStatus(200);

        Event::assertDispatched(RideRequestCancelled::class, function ($event) use ($rideRequest) {
            return $event->rideRequest->id === $rideRequest->id
                && $event->cancelledBy === 'rider';
        });
    }

    /**
     * Test 7: Cancelling a request without an assigned driver does NOT broadcast.
     */
    public function test_rider_cancel_does_not_broadcast_if_no_assigned_driver(): void
    {
        $riderToken = $this->rider->createToken('mobile', ['rider:cancel-ride'])->plainTextToken;

        $rideRequest = $this->createTestRideRequest([
            'rider_id' => $this->rider->id,
            'status' => 'pending',
            // No current_driver_id
        ]);

        $response = $this->withHeaders($this->authHeader($riderToken))
            ->patchJson("/api/v1/requests/{$rideRequest->id}/cancel");

        $response->assertStatus(200);

        Event::assertNotDispatched(RideRequestCancelled::class);
    }

    /**
     * Test 8: Another rider cannot cancel someone else's request.
     */
    public function test_other_rider_cannot_cancel_request(): void
    {
        $otherRider = User::factory()->create([
            'role' => 'rider',
            'is_active' => true,
        ]);
        $otherToken = $otherRider->createToken('mobile', ['rider:cancel-ride'])->plainTextToken;

        $rideRequest = $this->createTestRideRequest([
            'rider_id' => $this->rider->id,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($this->authHeader($otherToken))
            ->patchJson("/api/v1/requests/{$rideRequest->id}/cancel");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized to cancel this ride request',
            ]);
    }

    /**
     * Test 9: A rider cannot cancel a request that is already accepted.
     */
    public function test_rider_cannot_cancel_accepted_request(): void
    {
        $riderToken = $this->rider->createToken('mobile', ['rider:cancel-ride'])->plainTextToken;

        $rideRequest = $this->createTestRideRequest([
            'rider_id' => $this->rider->id,
            'status' => 'pending',
        ]);

        // Force status to 'in_progress' (outside cancellable pending/matched) via query builder
        DB::table('ride_requests')->where('id', $rideRequest->id)->update(['status' => 'in_progress']);

        $response = $this->withHeaders($this->authHeader($riderToken))
            ->patchJson("/api/v1/requests/{$rideRequest->id}/cancel");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot cancel ride request in current status',
            ]);
    }

    // ==========================================================================
    // ADMIN CANCEL REQUEST TESTS  (DELETE /api/admin/monitoring/requests/{id})
    // ==========================================================================

    /**
     * Test 10: Admin can cancel a pending ride request.
     */
    public function test_admin_can_cancel_pending_request(): void
    {
        $rideRequest = $this->createTestRideRequest([
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($this->authHeader($this->adminToken))
            ->deleteJson("/api/admin/monitoring/requests/{$rideRequest->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Ride request cancelled by admin.',
            ]);

        $this->assertDatabaseHas('ride_requests', [
            'id' => $rideRequest->id,
            'status' => 'cancelled',
        ]);

        Event::assertDispatched(RideRequestCancelled::class, function ($event) use ($rideRequest) {
            return $event->rideRequest->id === $rideRequest->id
                && $event->cancelledBy === 'admin';
        });
    }

    /**
     * Test 11: Admin cannot cancel a request that is not in 'pending' status.
     */
    public function test_admin_cannot_cancel_non_pending_request(): void
    {
        $rideRequest = $this->createTestRideRequest([
            'status' => 'pending',
        ]);

        // Manually flip the status to 'matched'
        DB::table('ride_requests')->where('id', $rideRequest->id)->update(['status' => 'matched']);

        $response = $this->withHeaders($this->authHeader($this->adminToken))
            ->deleteJson("/api/admin/monitoring/requests/{$rideRequest->id}");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Can only cancel pending requests.',
            ]);
    }

    /**
     * Test 12: Admin gets 404 for a non-existent ride request.
     */
    public function test_admin_cancel_request_not_found(): void
    {
        $response = $this->withHeaders($this->authHeader($this->adminToken))
            ->deleteJson('/api/admin/monitoring/requests/999999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Ride request not found.',
            ]);
    }

    // ==========================================================================
    // ADMIN CANCEL RIDE TESTS  (POST /api/admin/monitoring/rides/{id}/cancel)
    // ==========================================================================

    /**
     * Test 13: Admin cancelling an active ride causes the driver to rejoin the FIFO queue.
     */
    public function test_admin_cancel_ride_calls_rejoin_after_ride(): void
    {
        [, $ride] = $this->createTestRide([
            'driver_id' => $this->driver->id,
            'status' => 'in_progress',
        ]);

        // Ensure driver is in queue before
        DB::table('driver_profiles')
            ->where('user_id', $this->driver->id)
            ->update(['queue_joined_at' => null]);

        $response = $this->withHeaders($this->authHeader($this->adminToken))
            ->postJson("/api/admin/monitoring/rides/{$ride->id}/cancel");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Ride cancelled by admin.',
            ]);

        // The driver should have re-joined the queue (queue_joined_at is now set)
        $profile = DB::table('driver_profiles')->where('user_id', $this->driver->id)->first();
        $this->assertNotNull(
            $profile->queue_joined_at,
            'Driver queue_joined_at should be set after admin cancels the ride (rejoinAfterRide)'
        );
    }

    /**
     * Test 14: Admin cannot cancel a completed ride.
     */
    public function test_admin_cancel_ride_rejects_completed_ride(): void
    {
        [, $ride] = $this->createTestRide([
            'driver_id' => $this->driver->id,
            'status' => 'accepted',
        ]);

        // Force status to completed
        DB::table('rides')->where('id', $ride->id)->update(['status' => 'completed']);

        $response = $this->withHeaders($this->authHeader($this->adminToken))
            ->postJson("/api/admin/monitoring/rides/{$ride->id}/cancel");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Can only cancel active rides.',
            ]);
    }

    // ==========================================================================
    // ADMIN COMPLETE RIDE TESTS  (POST /api/admin/monitoring/rides/{id}/complete)
    // ==========================================================================

    /**
     * Test 15: Admin force-completing an active ride causes the driver to rejoin the FIFO queue.
     */
    public function test_admin_complete_ride_calls_rejoin_after_ride(): void
    {
        [, $ride] = $this->createTestRide([
            'driver_id' => $this->driver->id,
            'status' => 'in_progress',
        ]);

        // Clear the queue position so we can verify it gets re-set
        DB::table('driver_profiles')
            ->where('user_id', $this->driver->id)
            ->update(['queue_joined_at' => null]);

        $response = $this->withHeaders($this->authHeader($this->adminToken))
            ->postJson("/api/admin/monitoring/rides/{$ride->id}/complete");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Ride marked as completed by admin.',
            ]);

        // Driver should have rejoined the queue
        $profile = DB::table('driver_profiles')->where('user_id', $this->driver->id)->first();
        $this->assertNotNull(
            $profile->queue_joined_at,
            'Driver queue_joined_at should be set after admin completes the ride (rejoinAfterRide)'
        );
    }

    /**
     * Test 16: Admin cannot complete a ride that is already completed.
     */
    public function test_admin_complete_ride_rejects_already_completed_ride(): void
    {
        [, $ride] = $this->createTestRide([
            'driver_id' => $this->driver->id,
            'status' => 'accepted',
        ]);

        // Force status to completed
        DB::table('rides')->where('id', $ride->id)->update(['status' => 'completed']);

        $response = $this->withHeaders($this->authHeader($this->adminToken))
            ->postJson("/api/admin/monitoring/rides/{$ride->id}/complete");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Can only complete active rides.',
            ]);
    }

    // ==========================================================================
    // RIDE COMPLETION VIA DRIVER  (PATCH /api/v1/rides/{ride}/status)
    // ==========================================================================

    /**
     * Test 17: When a driver marks a ride as completed, they rejoin the FIFO queue.
     *
     * RideService and MatchingQueueService are mocked to isolate the controller
     * behaviour without needing a fully progressed ride in-progress state.
     */
    public function test_driver_completing_ride_rejoins_fifo_queue(): void
    {
        // Grant the driver token the driver:complete-ride ability as well
        $driverWithComplete = $this->driver;
        $completeToken = $driverWithComplete->createToken('driver-token', [
            'driver:accept-ride',
            'driver:complete-ride',
        ])->plainTextToken;

        // Mock RideService to return true for completeRide
        $this->mock(RideService::class, function ($mock) {
            $mock->shouldReceive('completeRide')
                ->once()
                ->andReturn(true);

            // Allow any other calls through without side effects
            $mock->shouldReceive('acceptRideRequest')->andReturn(null);
            $mock->shouldReceive('cancelRide')->andReturn(false);
            $mock->shouldReceive('markDriverArrived')->andReturn(false);
            $mock->shouldReceive('startRide')->andReturn(false);
            $mock->shouldReceive('rateRide')->andReturn(null);
        });

        // Mock MatchingQueueService to capture the rejoinAfterRide call
        $rejoined = false;
        $this->mock(MatchingQueueService::class, function ($mock) use (&$rejoined, $driverWithComplete) {
            $mock->shouldReceive('rejoinAfterRide')
                ->once()
                ->with($driverWithComplete->id)
                ->andReturnUsing(function (int $driverId) use (&$rejoined, $driverWithComplete) {
                    $rejoined = true;
                    // Simulate the actual service: set queue_joined_at
                    DB::table('driver_profiles')
                        ->where('user_id', $driverId)
                        ->update(['queue_joined_at' => now()]);
                });
        });

        // Clear queue_joined_at so we can verify it is re-set
        DB::table('driver_profiles')
            ->where('user_id', $this->driver->id)
            ->update(['queue_joined_at' => null]);

        [, $ride] = $this->createTestRide([
            'driver_id' => $this->driver->id,
            'status' => 'in_progress',
        ]);

        // Patch the ride status to 'completed'
        $response = $this->withHeaders($this->authHeader($completeToken))
            ->patchJson("/api/v1/rides/{$ride->id}/status", [
                'status' => 'completed',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertTrue($rejoined, 'MatchingQueueService::rejoinAfterRide should have been called');

        // Verify the driver now has a queue_joined_at value
        $profile = DB::table('driver_profiles')->where('user_id', $this->driver->id)->first();
        $this->assertNotNull(
            $profile->queue_joined_at,
            'Driver queue_joined_at should be set after ride completion'
        );
    }
}
