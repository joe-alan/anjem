# Driver Credit System — Implementation Plan

**Last updated:** 2026-03-03
**Phase:** Post-MVP / Open Beta Feature
**Priority:** Medium
**Original plan date:** November 30, 2025 (significantly revised — see Change Log)

---

## Executive Summary

Prepaid credit system for drivers during open beta. Drivers consume 1 credit per ride
accepted. Credits are managed directly by the admin (via database) for now — no
self-service claim or request flows in this phase.

### Key Decisions

| Decision | Value |
|---|---|
| Credit deduction | 1 credit per ride accepted |
| Minimum to go online | >= 1 credit required |
| Credit top-up method | Admin sets balance directly in DB (this phase) |
| Daily claim | ❌ Deferred |
| Driver credit requests | ❌ Deferred |
| Admin dashboard | ❌ Deferred — `admin-dashboard.html` being revoked; admin API may be reused after refactor |
| Payment integration | ❌ Deferred to post-beta |

---

## Revision Notes (vs. November 2025 plan)

The following were **removed or deferred** from the original plan:

- **Daily claim system** — removed. Admin manages balances via DB directly.
- **Credit request workflow** (`credit_requests` table, `RequestCreditsRequest`, driver request form, admin approve/reject) — removed.
- **`AdminCreditController`** — removed for this phase.
- **Admin dashboard HTML sections** — removed. `admin-dashboard.html` is being fully revoked; credit admin UI will be rebuilt with the future admin panel.
- **`credit_requests` table** — not creating this migration.

The following **architecture changes** since November 2025 affect file locations and approach:

- No `mobile/lib/driver/providers/ride_provider.dart` exists — accept logic lives directly in `ride_request_screen.dart`. Credit error handling goes there.
- Mobile services convention is `core/services/`, not `driver/services/`.
- `ride_request_screen.dart` catch block was recently refactored — `_parseError` uses `ApiException.statusCode` directly. Adding 402 is straightforward.
- `driver_status_provider.dart` has `kickOfflineOnLaunch`, `goOnline`, `goOffline`, and WebSocket subscription logic — credit gate on `goOnline()` must be integrated here.

---

## Scope: What Gets Built

### Backend
1. Migration: add credit columns to `driver_profiles`
2. Migration: create `credit_transactions` table (audit log)
3. `CreditTransaction` model
4. `CreditService` — `getBalance`, `canAcceptRide`, `deductCredit`
5. `InsufficientCreditsException`
6. `CreditController` — `GET /driver/credits/balance`, `GET /driver/credits/transactions`
7. Integrate into `RideService::acceptRideRequest()` — check + deduct credits
8. Integrate into `RideController::accept()` — handle 402
9. Update `DriverProfile` model — new fields + `creditTransactions()` relation
10. Update `UserResource` — expose `credits_balance` in driver_profile block
11. Update `routes/api.php` — add driver credit routes

### Mobile
1. `core/services/credit_service.dart` — API wrapper
2. `core/providers/credits_provider.dart` — balance state
3. `driver/screens/credits_screen.dart` — balance + transaction history
4. Modify `driver_status_provider.dart` — block `goOnline()` if credits < 1
5. Modify `driver_home_screen.dart` — show balance, low-credit warning
6. Modify `ride_request_screen.dart` — disable accept button if credits = 0; handle 402

---

## Database Schema

### `driver_profiles` — New Columns

```sql
ALTER TABLE driver_profiles
    ADD COLUMN credits_balance       INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN credits_total_earned  INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN credits_total_spent   INTEGER NOT NULL DEFAULT 0;
```

No `last_daily_claim_at` column — daily claim is deferred.

### `credit_transactions` Table

```sql
CREATE TABLE credit_transactions (
    id             BIGSERIAL PRIMARY KEY,
    driver_id      BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type           VARCHAR(50) NOT NULL,  -- 'admin_grant', 'deduction', 'refund'
    amount         INTEGER NOT NULL,      -- negative for deductions
    balance_before INTEGER NOT NULL,
    balance_after  INTEGER NOT NULL,
    ride_id        BIGINT NULLABLE REFERENCES rides(id) ON DELETE SET NULL,
    description    TEXT,
    created_at     TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at     TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_driver_transactions ON credit_transactions (driver_id, created_at DESC);
CREATE INDEX idx_ride_deductions      ON credit_transactions (ride_id);
```

Note: `credit_requests` table is **not** being created in this phase.

---

## Backend Implementation

### Files to Create

#### `app/Models/CreditTransaction.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditTransaction extends Model
{
    protected $fillable = [
        'driver_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'ride_id',
        'description',
    ];

    protected $casts = [
        'amount'         => 'integer',
        'balance_before' => 'integer',
        'balance_after'  => 'integer',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }
}
```

#### `app/Exceptions/InsufficientCreditsException.php`

```php
<?php

namespace App\Exceptions;

class InsufficientCreditsException extends \RuntimeException
{
    public function __construct(int $currentBalance)
    {
        parent::__construct(
            "Insufficient credits. Current balance: {$currentBalance}",
            402
        );
    }
}
```

#### `app/Services/CreditService.php`

```php
<?php

namespace App\Services;

use App\Exceptions\InsufficientCreditsException;
use App\Models\CreditTransaction;
use App\Models\DriverProfile;
use Illuminate\Support\Facades\DB;

class CreditService
{
    public function getBalance(int $driverId): int
    {
        return DriverProfile::where('user_id', $driverId)->value('credits_balance') ?? 0;
    }

    public function canAcceptRide(int $driverId): bool
    {
        return $this->getBalance($driverId) >= 1;
    }

    public function canGoOnline(int $driverId): bool
    {
        return $this->getBalance($driverId) >= 1;
    }

    /**
     * Deduct 1 credit atomically. Must be called inside an existing DB transaction.
     */
    public function deductCredit(int $driverId, int $rideId): void
    {
        $profile = DriverProfile::where('user_id', $driverId)->lockForUpdate()->first();

        if ($profile->credits_balance < 1) {
            throw new InsufficientCreditsException($profile->credits_balance);
        }

        $balanceBefore = $profile->credits_balance;
        $profile->credits_balance      -= 1;
        $profile->credits_total_spent  += 1;
        $profile->save();

        CreditTransaction::create([
            'driver_id'      => $driverId,
            'type'           => 'deduction',
            'amount'         => -1,
            'balance_before' => $balanceBefore,
            'balance_after'  => $profile->credits_balance,
            'ride_id'        => $rideId,
            'description'    => 'Ride request accepted',
        ]);
    }

    /**
     * Add credits to a driver (admin grant). Wraps in its own transaction.
     */
    public function addCredits(int $driverId, int $amount, string $description = 'Admin grant'): void
    {
        DB::transaction(function () use ($driverId, $amount, $description) {
            $profile = DriverProfile::where('user_id', $driverId)->lockForUpdate()->first();

            $balanceBefore = $profile->credits_balance;
            $profile->credits_balance     += $amount;
            $profile->credits_total_earned += $amount;
            $profile->save();

            CreditTransaction::create([
                'driver_id'      => $driverId,
                'type'           => 'admin_grant',
                'amount'         => $amount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $profile->credits_balance,
                'description'    => $description,
            ]);
        });
    }

    public function getTransactions(int $driverId, int $limit = 50)
    {
        return CreditTransaction::where('driver_id', $driverId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
```

#### `app/Http/Controllers/Api/CreditController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    public function __construct(private CreditService $creditService) {}

    public function getBalance(Request $request): JsonResponse
    {
        $driverId = $request->user()->id;

        return response()->json([
            'success' => true,
            'data'    => [
                'balance' => $this->creditService->getBalance($driverId),
            ],
        ]);
    }

    public function getTransactions(Request $request): JsonResponse
    {
        $driverId = $request->user()->id;

        $transactions = $this->creditService->getTransactions($driverId)
            ->map(fn ($t) => [
                'id'             => $t->id,
                'type'           => $t->type,
                'amount'         => $t->amount,
                'balance_before' => $t->balance_before,
                'balance_after'  => $t->balance_after,
                'description'    => $t->description,
                'ride_id'        => $t->ride_id,
                'created_at'     => $t->created_at->toISOString(),
            ]);

        return response()->json([
            'success' => true,
            'data'    => $transactions,
        ]);
    }
}
```

### Files to Modify

#### `app/Services/RideService.php` — `acceptRideRequest()`

Inside the existing `DB::transaction()` block, add credit check + deduct **before** ride creation:

```php
// At the top of the transaction, after the lockForUpdate ride request check:
$this->creditService->deductCredit($driverId, $rideRequestId);

// ... existing ride creation code follows
```

If the ride creation fails, the transaction rolls back and the credit is automatically restored.

#### `app/Http/Controllers/Api/RideController.php` — `accept()`

Add `InsufficientCreditsException` to the status code match:

```php
$statusCode = match ($e->getCode()) {
    402 => 402,  // Insufficient credits
    410 => 410,
    409 => 409,
    404 => 404,
    403 => 403,
    400 => 400,
    default => 500,
];
```

#### `app/Models/DriverProfile.php`

Add to `$fillable`:
```php
'credits_balance',
'credits_total_earned',
'credits_total_spent',
```

Add to `$casts`:
```php
'credits_balance'      => 'integer',
'credits_total_earned' => 'integer',
'credits_total_spent'  => 'integer',
```

Add relation:
```php
public function creditTransactions()
{
    return $this->hasMany(CreditTransaction::class, 'driver_id', 'user_id');
}
```

#### `app/Http/Resources/UserResource.php`

Add to the `driver_profile` block:
```php
'credits_balance' => $this->driverProfile->credits_balance ?? 0,
```

#### `routes/api.php`

Add inside the `auth:sanctum` middleware group, under the driver routes:
```php
Route::prefix('driver/credits')->group(function () {
    Route::get('balance',      [CreditController::class, 'getBalance']);
    Route::get('transactions', [CreditController::class, 'getTransactions']);
});
```

Admin grant endpoint goes inside the existing `admin` prefix group:
```php
Route::post('drivers/{id}/credits/grant', [AdminCreditController::class, 'grantCredits']);
```

---

## Mobile Implementation

### Files to Create

#### `core/services/credit_service.dart`

API wrapper — `getBalance()`, `getTransactions()`.

#### `core/providers/credits_provider.dart`

```dart
class CreditsState {
  final int balance;
  final List<CreditTransaction> transactions;
  final bool isLoading;
  final String? error;
}

class CreditsNotifier extends StateNotifier<CreditsState> {
  Future<void> fetchBalance() async { }
  Future<void> fetchTransactions() async { }
}
```

#### `driver/screens/credits_screen.dart`

Two tabs: **Balance** (shows balance + claim/request UI — disabled/hidden for now) and
**History** (transaction list).

### Files to Modify

#### `core/providers/driver_status_provider.dart` — `goOnline()`

Before calling the online API:

```dart
final credits = await _creditService.getBalance();
if (credits < 1) {
  state = state.copyWith(error: 'insufficient_credits');
  return;
}
```

#### `driver/screens/driver_home_screen.dart`

- Show credit balance chip near online/offline toggle
- Show low-credit warning card when balance < 5
- Tap navigates to credits screen

#### `driver/screens/ride_request_screen.dart`

Add to `_parseError`:
```dart
} else if (error.statusCode == 402) {
  return 'Insufficient credits to accept this ride';
}
```

Disable Accept button when `ref.watch(creditsProvider).balance < 1`.

---

## What's NOT in Scope (This Phase)

| Feature | Status |
|---|---|
| Daily credit claim | ❌ Deferred |
| Driver credit request form | ❌ Deferred |
| Admin approve/reject workflow | ❌ Deferred |
| `credit_requests` table | ❌ Not creating |
| Admin dashboard credit UI | ❌ Deferred — `admin-dashboard.html` being revoked |
| Payment/purchase flow | ❌ Post-beta |
| Credit refunds (automated) | ❌ Admin manual only via DB |

---

## Migration to Paid Credits (Future)

When payment is added:
1. Keep `credit_transactions` table — it's the audit log
2. Add `credit_packages` table + Midtrans/Xendit integration
3. Add `type = 'purchase'` to transaction types
4. Daily claim / request system can be re-evaluated then

---

## Open Questions for Future Phases

1. **Daily claim amount and cadence** — deferred
2. **Credit request limits per day** — deferred
3. **Low-credit threshold for warning** — suggest 5, finalize later
4. **Admin dashboard** — full redesign planned; credit UI will be built there

---

**Status:** 📋 Ready for Implementation (revised scope)
