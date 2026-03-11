<?php

namespace Tests\Feature\Api;

use App\Models\CreditTransaction;
use App\Models\DriverProfile;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

class CreditControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Bus::fake();
    }

    // -------------------------------------------------------------------------
    // HELPER
    // -------------------------------------------------------------------------

    /**
     * @return array{user: User, token: string}
     */
    private function createDriverWithCredits(int $balance): array
    {
        $user = User::factory()->driver()->create([
            'is_active'  => true,
            'fcm_token'  => null,
        ]);

        DriverProfile::create([
            'user_id'              => $user->id,
            'vehicle_type'         => 'motorcycle',
            'vehicle_plate'        => 'B' . rand(1000, 9999) . 'XYZ',
            'is_verified'          => true,
            'credits_balance'      => $balance,
            'credits_total_earned' => 0,
            'credits_total_spent'  => 0,
            'current_location'     => new Point(-6.3605, 106.8271, 4326),
        ]);

        $token = $user->createTokenWithAbilities(true);

        return ['user' => $user, 'token' => $token];
    }

    private function authHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ];
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/driver/credits/balance
    // -------------------------------------------------------------------------

    public function test_returns_balance_for_authenticated_driver(): void
    {
        ['token' => $token] = $this->createDriverWithCredits(7);

        $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/v1/driver/credits/balance')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.balance', 7);
    }

    public function test_returns_zero_balance_for_new_driver(): void
    {
        ['token' => $token] = $this->createDriverWithCredits(0);

        $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/v1/driver/credits/balance')
            ->assertStatus(200)
            ->assertJsonPath('data.balance', 0);
    }

    public function test_balance_requires_authentication(): void
    {
        $this->getJson('/api/v1/driver/credits/balance')
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/driver/credits/transactions
    // -------------------------------------------------------------------------

    public function test_returns_empty_array_when_no_transactions(): void
    {
        ['token' => $token] = $this->createDriverWithCredits(0);

        $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/v1/driver/credits/transactions')
            ->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_transactions_requires_authentication(): void
    {
        $this->getJson('/api/v1/driver/credits/transactions')
            ->assertStatus(401);
    }

    public function test_returns_transactions_with_correct_shape(): void
    {
        ['user' => $user, 'token' => $token] = $this->createDriverWithCredits(0);

        (new CreditService())->addCredits($user->id, 3, 'Test grant');

        $response = $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/v1/driver/credits/transactions')
            ->assertStatus(200);

        $tx = $response->json('data.0');

        foreach (['id', 'type', 'amount', 'balance_before', 'balance_after', 'description', 'ride_id', 'created_at'] as $key) {
            $this->assertArrayHasKey($key, $tx, "Transaction shape missing key: {$key}");
        }
    }

    public function test_transactions_returned_in_descending_order(): void
    {
        ['user' => $user, 'token' => $token] = $this->createDriverWithCredits(0);

        $service = new CreditService();
        $service->addCredits($user->id, 1, 'First');
        $service->addCredits($user->id, 2, 'Second');

        // Force distinct timestamps so ordering is deterministic
        $txs = CreditTransaction::where('driver_id', $user->id)->orderBy('id')->get();
        $txs[0]->forceFill(['created_at' => now()->subSeconds(10)])->save();
        $txs[1]->forceFill(['created_at' => now()])->save();

        $response = $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/v1/driver/credits/transactions')
            ->assertStatus(200);

        $results = $response->json('data');
        $this->assertSame($txs[1]->id, $results[0]['id']);
    }
}
