<?php

namespace Tests\Unit\Services;

use App\Exceptions\InsufficientCreditsException;
use App\Models\CreditTransaction;
use App\Models\DriverProfile;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

class CreditServiceTest extends TestCase
{
    use RefreshDatabase;

    private CreditService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Bus::fake();
        $this->service = new CreditService();
    }

    // -------------------------------------------------------------------------
    // HELPER
    // -------------------------------------------------------------------------

    private function createDriverWithProfile(int $balance = 0, array $overrides = []): User
    {
        $user = User::factory()->driver()->create(['is_active' => true]);

        DriverProfile::create(array_merge([
            'user_id'              => $user->id,
            'vehicle_type'         => 'motorcycle',
            'vehicle_plate'        => 'B' . rand(1000, 9999) . 'XYZ',
            'is_verified'          => true,
            'credits_balance'      => $balance,
            'credits_total_earned' => 0,
            'credits_total_spent'  => 0,
            'current_location'     => new Point(-6.3605, 106.8271, 4326),
        ], $overrides));

        return $user;
    }

    // -------------------------------------------------------------------------
    // getBalance
    // -------------------------------------------------------------------------

    public function test_returns_correct_balance(): void
    {
        $driver = $this->createDriverWithProfile(5);

        $this->assertSame(5, $this->service->getBalance($driver->id));
    }

    public function test_returns_zero_when_profile_not_found(): void
    {
        $this->assertSame(0, $this->service->getBalance(999999));
    }

    // -------------------------------------------------------------------------
    // canGoOnline / canAcceptRide
    // -------------------------------------------------------------------------

    public function test_can_go_online_true_when_balance_at_least_one(): void
    {
        $driver = $this->createDriverWithProfile(1);

        $this->assertTrue($this->service->canGoOnline($driver->id));
    }

    public function test_can_go_online_false_when_balance_zero(): void
    {
        $driver = $this->createDriverWithProfile(0);

        $this->assertFalse($this->service->canGoOnline($driver->id));
    }

    public function test_can_accept_ride_true_when_balance_at_least_one(): void
    {
        $driver = $this->createDriverWithProfile(1);

        $this->assertTrue($this->service->canAcceptRide($driver->id));
    }

    public function test_can_accept_ride_false_when_balance_zero(): void
    {
        $driver = $this->createDriverWithProfile(0);

        $this->assertFalse($this->service->canAcceptRide($driver->id));
    }

    // -------------------------------------------------------------------------
    // deductCredit
    // -------------------------------------------------------------------------

    public function test_throws_logic_exception_when_no_active_transaction(): void
    {
        // deductCredit guards against being called outside a DB transaction.
        // RefreshDatabase wraps each test in a transaction (level 1).  We
        // temporarily roll back to level 0, run the assertion, then restore.
        $level = DB::transactionLevel();
        for ($i = 0; $i < $level; $i++) {
            DB::rollBack();
        }

        $this->expectException(\LogicException::class);

        try {
            // Driver ID is irrelevant — the guard throws before any DB query.
            $this->service->deductCredit(999999);
        } finally {
            for ($i = 0; $i < $level; $i++) {
                DB::beginTransaction();
            }
        }
    }

    public function test_throws_insufficient_credits_when_balance_zero(): void
    {
        $driver = $this->createDriverWithProfile(0);

        $this->expectException(InsufficientCreditsException::class);

        DB::beginTransaction();
        try {
            $this->service->deductCredit($driver->id);
        } finally {
            DB::rollBack();
        }
    }

    public function test_decrements_balance_by_one(): void
    {
        $driver = $this->createDriverWithProfile(3);

        DB::beginTransaction();
        $this->service->deductCredit($driver->id);
        DB::commit();

        $profile = DriverProfile::where('user_id', $driver->id)->first();
        $this->assertSame(2, $profile->credits_balance);
    }

    public function test_increments_total_spent(): void
    {
        $driver = $this->createDriverWithProfile(1);

        DB::beginTransaction();
        $this->service->deductCredit($driver->id);
        DB::commit();

        $profile = DriverProfile::where('user_id', $driver->id)->first();
        $this->assertSame(1, $profile->credits_total_spent);
    }

    public function test_creates_credit_transaction_record(): void
    {
        $driver = $this->createDriverWithProfile(1);

        DB::beginTransaction();
        $this->service->deductCredit($driver->id);
        DB::commit();

        $this->assertSame(1, CreditTransaction::count());

        $tx = CreditTransaction::first();
        $this->assertSame('deduction', $tx->type);
        $this->assertSame(-1, $tx->amount);
        $this->assertSame(1, $tx->balance_before);
        $this->assertSame(0, $tx->balance_after);
        $this->assertNull($tx->ride_id); // no ride_id passed in unit test
    }

    public function test_deduct_credit_rolls_back_on_outer_transaction_failure(): void
    {
        $driver = $this->createDriverWithProfile(1);

        DB::beginTransaction();
        $this->service->deductCredit($driver->id);
        DB::rollBack();

        $profile = DriverProfile::where('user_id', $driver->id)->first();
        $this->assertSame(1, $profile->credits_balance);
        $this->assertSame(0, CreditTransaction::count());
    }

    // -------------------------------------------------------------------------
    // addCredits
    // -------------------------------------------------------------------------

    public function test_throws_invalid_argument_when_amount_is_zero(): void
    {
        $driver = $this->createDriverWithProfile(0);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->addCredits($driver->id, 0);
    }

    public function test_throws_invalid_argument_when_amount_is_negative(): void
    {
        $driver = $this->createDriverWithProfile(0);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->addCredits($driver->id, -5);
    }

    public function test_increments_balance_by_amount(): void
    {
        $driver = $this->createDriverWithProfile(0);

        $this->service->addCredits($driver->id, 5);

        $profile = DriverProfile::where('user_id', $driver->id)->first();
        $this->assertSame(5, $profile->credits_balance);
    }

    public function test_increments_total_earned(): void
    {
        $driver = $this->createDriverWithProfile(0);

        $this->service->addCredits($driver->id, 3);

        $profile = DriverProfile::where('user_id', $driver->id)->first();
        $this->assertSame(3, $profile->credits_total_earned);
    }

    public function test_creates_admin_grant_transaction_record(): void
    {
        $driver = $this->createDriverWithProfile(0);

        $this->service->addCredits($driver->id, 5);

        $this->assertSame(1, CreditTransaction::count());

        $tx = CreditTransaction::first();
        $this->assertSame('admin_grant', $tx->type);
        $this->assertSame(5, $tx->amount);
        $this->assertSame(0, $tx->balance_before);
        $this->assertSame(5, $tx->balance_after);
    }

    // -------------------------------------------------------------------------
    // getTransactions
    // -------------------------------------------------------------------------

    public function test_returns_transactions_in_descending_order(): void
    {
        $driver = $this->createDriverWithProfile(0);

        $this->service->addCredits($driver->id, 1, 'First');
        $this->service->addCredits($driver->id, 2, 'Second');
        $this->service->addCredits($driver->id, 3, 'Third');

        // Force distinct timestamps so ordering is deterministic
        $txs = CreditTransaction::where('driver_id', $driver->id)->orderBy('id')->get();
        $txs[0]->forceFill(['created_at' => now()->subSeconds(10)])->save();
        $txs[1]->forceFill(['created_at' => now()->subSeconds(5)])->save();
        $txs[2]->forceFill(['created_at' => now()])->save();

        $results = $this->service->getTransactions($driver->id);

        $this->assertSame($txs[2]->id, $results->first()->id);
    }

    public function test_returns_empty_collection_when_no_transactions(): void
    {
        $driver = $this->createDriverWithProfile(0);

        $results = $this->service->getTransactions($driver->id);

        $this->assertCount(0, $results);
    }
}
