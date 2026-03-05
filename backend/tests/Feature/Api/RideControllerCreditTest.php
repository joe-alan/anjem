<?php

namespace Tests\Feature\Api;

use App\Models\CreditTransaction;
use App\Models\DriverProfile;
use App\Models\Location;
use App\Models\RideRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

class RideControllerCreditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Bus::fake();
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    private function createVerifiedDriver(int $credits = 5): User
    {
        $user = User::factory()->driver()->create([
            'is_active' => true,
            'fcm_token' => null,
        ]);

        DriverProfile::create([
            'user_id'              => $user->id,
            'vehicle_type'         => 'motorcycle',
            'vehicle_plate'        => 'B' . rand(1000, 9999) . 'XYZ',
            'is_verified'          => true,
            'went_online_at'       => now(),
            'queue_joined_at'      => now(),
            'max_pickup_radius_km' => 5.0,
            'credits_balance'      => $credits,
            'credits_total_earned' => 0,
            'credits_total_spent'  => 0,
            'current_location'     => new Point(-6.3605, 106.8271, 4326),
        ]);

        return $user;
    }

    private function createRider(): User
    {
        return User::factory()->rider()->create([
            'is_active' => true,
            'fcm_token' => null,
        ]);
    }

    private function createRideRequest(User $rider): RideRequest
    {
        $pickup = Location::create([
            'name'        => 'Pickup ' . uniqid(),
            'coordinates' => new Point(-6.3605, 106.8271, 4326),
            'is_beacon'   => true,
            'is_active'   => true,
        ]);

        $destination = Location::create([
            'name'        => 'Destination ' . uniqid(),
            'coordinates' => new Point(-6.3595, 106.8295, 4326),
            'is_beacon'   => true,
            'is_active'   => true,
        ]);

        $rideRequest = new RideRequest();
        $rideRequest->fill([
            'rider_id'               => $rider->id,
            'pickup_location_id'     => $pickup->id,
            'destination_location_id' => $destination->id,
            'passenger_count'        => 1,
            'estimated_fare_rp'      => 10000,
            'status'                 => 'pending',
            'expires_at'             => now()->addMinutes(10),
        ]);
        $rideRequest->save();

        return $rideRequest;
    }

    private function authHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ];
    }

    // -------------------------------------------------------------------------
    // Credit deduction on accept
    // -------------------------------------------------------------------------

    public function test_accept_ride_deducts_one_credit(): void
    {
        $driver = $this->createVerifiedDriver(5);
        $token  = $driver->createTokenWithAbilities(true);
        $rider  = $this->createRider();
        $rideRequest = $this->createRideRequest($rider);

        $this->withHeaders($this->authHeaders($token))
            ->postJson("/api/v1/rides/{$rideRequest->id}/accept")
            ->assertStatus(200);

        $profile = DriverProfile::where('user_id', $driver->id)->first();
        $this->assertSame(4, $profile->credits_balance);
    }

    public function test_accept_ride_creates_deduction_transaction(): void
    {
        $driver = $this->createVerifiedDriver(1);
        $token  = $driver->createTokenWithAbilities(true);
        $rider  = $this->createRider();
        $rideRequest = $this->createRideRequest($rider);

        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson("/api/v1/rides/{$rideRequest->id}/accept")
            ->assertStatus(200);

        $rideId = $response->json('data.id');

        $tx = CreditTransaction::where('driver_id', $driver->id)->first();
        $this->assertNotNull($tx);
        $this->assertSame('deduction', $tx->type);
        $this->assertSame(-1, $tx->amount);
        $this->assertSame($rideId, $tx->ride_id);
    }

    public function test_returns_402_when_driver_has_zero_credits(): void
    {
        $driver = $this->createVerifiedDriver(0);
        $token  = $driver->createTokenWithAbilities(true);
        $rider  = $this->createRider();
        $rideRequest = $this->createRideRequest($rider);

        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson("/api/v1/rides/{$rideRequest->id}/accept")
            ->assertStatus(402)
            ->assertJsonPath('success', false);

        $this->assertStringContainsString('credits', strtolower($response->json('message')));
    }

    public function test_credits_not_deducted_if_ride_creation_fails(): void
    {
        $driver = $this->createVerifiedDriver(1);
        $token  = $driver->createTokenWithAbilities(true);
        $rider  = $this->createRider();
        $rideRequest = $this->createRideRequest($rider);

        // Simulate another driver having already accepted the request
        DB::table('ride_requests')
            ->where('id', $rideRequest->id)
            ->update(['status' => 'matched']);

        $this->withHeaders($this->authHeaders($token))
            ->postJson("/api/v1/rides/{$rideRequest->id}/accept")
            ->assertStatus(409);

        // Credit must NOT have been deducted
        $profile = DriverProfile::where('user_id', $driver->id)->first();
        $this->assertSame(1, $profile->credits_balance);
        $this->assertSame(0, CreditTransaction::count());
    }
}
