# Driver Credit System - Implementation Plan

**Date**: November 30, 2025
**Phase**: Post-MVP / Open Beta Feature
**Priority**: Medium (After Phase 9 Mobile Critical Features)
**Estimated Duration**: 5-7 days
**Complexity**: Low-Medium ✅

---

## Executive Summary

Implementation of a prepaid credit system for drivers during open beta phase. Drivers consume 1 credit per ride request accepted, with credits provided through daily claims and admin-approved requests. Payment integration is deferred for post-beta.

### Key Metrics

| Metric | Value |
|--------|-------|
| **Credit Value** | 1 credit = Rp. 500 |
| **Deduction Rate** | 1 credit per ride accepted |
| **Daily Free Credits** | Configurable (default: 10) |
| **Implementation Time** | 5-7 days |
| **Files to Create** | 10-11 |
| **Files to Modify** | 9 |
| **Total LOC** | ~1,900 lines |

---

## System Overview

### How It Works

1. **Credit Acquisition** (Open Beta):
   - Daily claim: Drivers claim free credits once per 24 hours
   - Request credits: Drivers request credits with reason, admin approves
   - Admin grant: Admins manually grant credits to drivers

2. **Credit Deduction**:
   - Driver accepts ride request → 1 credit deducted
   - No refunds for cancellations (keeps beta simple)
   - Insufficient credits → Cannot accept rides

3. **Admin Controls**:
   - Set daily credit amount (configurable)
   - Enable/disable daily credits
   - Approve/reject credit requests
   - Manually grant credits to drivers
   - View credit statistics and transactions

### Beta Model Benefits

- ✅ No payment gateway complexity
- ✅ No PCI compliance requirements
- ✅ Flexible credit amounts for testing
- ✅ Collect usage data before building payment
- ✅ Becomes "free tier" when payment added later

---

## Database Schema

### New Tables

#### `credit_transactions` Table
```sql
CREATE TABLE credit_transactions (
    id BIGSERIAL PRIMARY KEY,
    driver_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL, -- 'daily_claim', 'admin_grant', 'deduction', 'refund'
    amount INTEGER NOT NULL, -- Negative for deductions
    balance_before INTEGER NOT NULL,
    balance_after INTEGER NOT NULL,
    ride_id BIGINT NULLABLE REFERENCES rides(id) ON DELETE SET NULL,
    description TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),

    INDEX idx_driver_transactions (driver_id, created_at DESC),
    INDEX idx_ride_deductions (ride_id)
);
```

#### `credit_requests` Table
```sql
CREATE TABLE credit_requests (
    id BIGSERIAL PRIMARY KEY,
    driver_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    amount_requested INTEGER NOT NULL,
    reason TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending', -- 'pending', 'approved', 'rejected'
    reviewed_by BIGINT NULLABLE REFERENCES users(id) ON DELETE SET NULL,
    reviewed_at TIMESTAMP NULLABLE,
    admin_notes TEXT NULLABLE,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),

    INDEX idx_driver_requests (driver_id, created_at DESC),
    INDEX idx_pending_requests (status, created_at DESC)
);
```

### Table Modifications

#### `driver_profiles` Table - Add Columns
```sql
ALTER TABLE driver_profiles ADD COLUMN:
    credits_balance INTEGER NOT NULL DEFAULT 0,
    last_daily_claim_at TIMESTAMP NULLABLE,
    credits_total_earned INTEGER NOT NULL DEFAULT 0,
    credits_total_spent INTEGER NOT NULL DEFAULT 0;

CREATE INDEX idx_daily_claims ON driver_profiles(last_daily_claim_at);
```

### Configuration
Add to `.env`:
```env
DAILY_CREDITS_ENABLED=true
DAILY_CREDITS_AMOUNT=10
```

---

## Backend Implementation

### Files to Create

#### 1. Models (2 files)

**`app/Models/CreditTransaction.php`** (~80 lines):
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
        'amount' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
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

**`app/Models/CreditRequest.php`** (~60 lines):
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditRequest extends Model
{
    protected $fillable = [
        'driver_id',
        'amount_requested',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_notes',
    ];

    protected $casts = [
        'amount_requested' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }
}
```

#### 2. Service (1 file)

**`app/Services/CreditService.php`** (~250 lines):
```php
<?php

namespace App\Services;

class CreditService
{
    /**
     * Get driver's current credit balance
     */
    public function getBalance(int $driverId): int
    {
        $profile = DriverProfile::where('user_id', $driverId)->first();
        return $profile->credits_balance ?? 0;
    }

    /**
     * Check if driver can accept ride (has >= 1 credit)
     */
    public function canAcceptRide(int $driverId): bool
    {
        return $this->getBalance($driverId) >= 1;
    }

    /**
     * Deduct 1 credit when driver accepts ride
     */
    public function deductCredit(int $driverId, int $rideId): void
    {
        $profile = DriverProfile::where('user_id', $driverId)->lockForUpdate()->first();

        if ($profile->credits_balance < 1) {
            throw new InsufficientCreditsException();
        }

        $balanceBefore = $profile->credits_balance;
        $profile->credits_balance -= 1;
        $profile->credits_total_spent += 1;
        $profile->save();

        CreditTransaction::create([
            'driver_id' => $driverId,
            'type' => 'deduction',
            'amount' => -1,
            'balance_before' => $balanceBefore,
            'balance_after' => $profile->credits_balance,
            'ride_id' => $rideId,
            'description' => 'Ride request accepted',
        ]);
    }

    /**
     * Add credits to driver (admin grant or daily claim)
     */
    public function addCredit(int $driverId, int $amount, string $type, string $description): void
    {
        $profile = DriverProfile::where('user_id', $driverId)->lockForUpdate()->first();

        $balanceBefore = $profile->credits_balance;
        $profile->credits_balance += $amount;
        $profile->credits_total_earned += $amount;
        $profile->save();

        CreditTransaction::create([
            'driver_id' => $driverId,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $profile->credits_balance,
            'description' => $description,
        ]);
    }

    /**
     * Claim daily credits
     */
    public function claimDailyCredits(int $driverId): void
    {
        if (!config('credits.daily_enabled', true)) {
            throw new DailyCreditsDisabledException();
        }

        $profile = DriverProfile::where('user_id', $driverId)->first();

        if ($profile->last_daily_claim_at?->isToday()) {
            throw new AlreadyClaimedException();
        }

        $amount = config('credits.daily_amount', 10);

        $this->addCredit($driverId, $amount, 'daily_claim', 'Daily credits claim');

        $profile->update(['last_daily_claim_at' => now()]);
    }

    /**
     * Check if driver can claim daily credits
     */
    public function canClaimDaily(int $driverId): bool
    {
        if (!config('credits.daily_enabled', true)) {
            return false;
        }

        $profile = DriverProfile::where('user_id', $driverId)->first();
        return !$profile->last_daily_claim_at?->isToday();
    }

    /**
     * Create credit request
     */
    public function requestCredits(int $driverId, int $amount, string $reason): CreditRequest
    {
        return CreditRequest::create([
            'driver_id' => $driverId,
            'amount_requested' => $amount,
            'reason' => $reason,
            'status' => 'pending',
        ]);
    }

    /**
     * Get driver's transaction history
     */
    public function getTransactions(int $driverId, int $limit = 50)
    {
        return CreditTransaction::where('driver_id', $driverId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
```

#### 3. Controllers (2 files)

**`app/Http/Controllers/Api/CreditController.php`** (~150 lines)
**`app/Http/Controllers/Api/AdminCreditController.php`** (~200 lines)

#### 4. Form Requests (1 file)

**`app/Http/Requests/RequestCreditsRequest.php`** (~40 lines)

#### 5. Resources (2 files)

**`app/Http/Resources/CreditTransactionResource.php`** (~40 lines)
**`app/Http/Resources/CreditRequestResource.php`** (~50 lines)

#### 6. Exceptions (1 file)

**`app/Exceptions/InsufficientCreditsException.php`** (~20 lines)

### Files to Modify

#### 1. `app/Services/RideService.php`

**Modify `acceptRideRequest()` method**:
```php
use App\Services\CreditService;
use App\Exceptions\InsufficientCreditsException;

public function acceptRideRequest($requestId, $driverId)
{
    // 1. CHECK CREDITS FIRST
    if (!$this->creditService->canAcceptRide($driverId)) {
        throw new InsufficientCreditsException(
            'Insufficient credits. Current balance: ' .
            $this->creditService->getBalance($driverId)
        );
    }

    // 2. Start database transaction
    DB::beginTransaction();
    try {
        // 3. Deduct credit BEFORE creating ride
        $this->creditService->deductCredit($driverId, $requestId);

        // 4. Existing ride creation logic
        $ride = // ... existing code ...

        DB::commit();
        return $ride;

    } catch (Exception $e) {
        DB::rollBack();
        throw $e;
    }
}
```

#### 2. `app/Http/Controllers/Api/RideController.php`

**Add error handling in `accept()` method**:
```php
use App\Exceptions\InsufficientCreditsException;

public function accept(Request $request, RideRequest $rideRequest): JsonResponse
{
    try {
        $ride = $this->rideService->acceptRideRequest($rideRequest->id, $driver->id);
        // ... existing success response

    } catch (InsufficientCreditsException $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'error_code' => 'INSUFFICIENT_CREDITS',
            'current_balance' => $this->creditService->getBalance($driver->id),
        ], 402); // 402 Payment Required
    }
    // ... other catches
}
```

#### 3. `app/Models/DriverProfile.php`

**Add credit fields**:
```php
protected $fillable = [
    // ... existing fields
    'credits_balance',
    'last_daily_claim_at',
    'credits_total_earned',
    'credits_total_spent',
];

protected $casts = [
    // ... existing casts
    'last_daily_claim_at' => 'datetime',
];

public function creditTransactions()
{
    return $this->hasMany(CreditTransaction::class, 'driver_id', 'user_id');
}

public function creditRequests()
{
    return $this->hasMany(CreditRequest::class, 'driver_id', 'user_id');
}
```

#### 4. `app/Http/Resources/UserResource.php`

**Add credits to driver_profile**:
```php
'driver_profile' => $this->when(
    in_array($this->role, ['driver', 'both', 'admin']),
    function () {
        return [
            // ... existing fields
            'credits_balance' => $this->driverProfile->credits_balance ?? 0,
            'can_claim_daily' => $this->driverProfile ?
                !$this->driverProfile->last_daily_claim_at?->isToday() : false,
        ];
    }
),
```

#### 5. `routes/api.php`

**Add routes**:
```php
// Driver credit routes
Route::prefix('driver/credits')->group(function () {
    Route::get('balance', [CreditController::class, 'getBalance']);
    Route::get('transactions', [CreditController::class, 'getTransactions']);
    Route::post('request', [CreditController::class, 'requestCredits']);
    Route::post('daily-claim', [CreditController::class, 'claimDailyCredits']);
});

// Admin credit routes
Route::prefix('admin/credits')->middleware(['admin'])->group(function () {
    Route::get('overview', [AdminCreditController::class, 'getOverview']);
    Route::get('requests', [AdminCreditController::class, 'getPendingRequests']);
    Route::post('requests/{id}/approve', [AdminCreditController::class, 'approveRequest']);
    Route::post('requests/{id}/reject', [AdminCreditController::class, 'rejectRequest']);
    Route::post('drivers/{id}/grant', [AdminCreditController::class, 'grantCredits']);
    Route::get('settings', [AdminCreditController::class, 'getSettings']);
    Route::put('settings', [AdminCreditController::class, 'updateSettings']);
});
```

---

## Mobile App Implementation

### Files to Create

#### 1. Screens (2 files)

**`mobile/lib/driver/screens/credits_screen.dart`** (~250 lines):
- Three tabs: Balance, Transactions, Request
- Daily claim button with countdown timer
- Transaction history list
- Request credits form (amount + reason)

**`mobile/lib/driver/widgets/credit_balance_widget.dart`** (~80 lines):
- Reusable widget to show balance
- Used in home screen and credits screen

#### 2. Providers (2 files)

**`mobile/lib/driver/providers/credits_provider.dart`** (~150 lines):
```dart
class CreditsProvider extends StateNotifier<CreditsState> {
  final CreditService _creditService;

  Future<void> getBalance() async { }
  Future<void> claimDailyCredits() async { }
  Future<void> requestCredits(int amount, String reason) async { }
  Future<void> loadTransactions() async { }
}
```

**`mobile/lib/driver/services/credit_service.dart`** (~100 lines):
```dart
class CreditService {
  Future<int> getBalance() async { }
  Future<void> claimDailyCredits() async { }
  Future<void> requestCredits(int amount, String reason) async { }
  Future<List<CreditTransaction>> getTransactions() async { }
}
```

### Files to Modify

#### 1. `mobile/lib/driver/screens/driver_home_screen.dart`

**Add credit balance display**:
```dart
// At top of screen
CreditBalanceWidget(
  balance: ref.watch(creditsProvider).balance,
  onTap: () => context.push('/credits'),
),

// Low credit warning
if (credits < 5)
  LowCreditWarning(
    currentBalance: credits,
    onTopUp: () => context.push('/credits'),
  ),
```

#### 2. `mobile/lib/driver/screens/ride_request_screen.dart`

**Check credits before showing accept button**:
```dart
// Check credits
final credits = ref.watch(creditsProvider).balance;

// Accept button
ElevatedButton(
  onPressed: credits >= 1 ? _acceptRide : null,
  child: Text(
    credits >= 1
      ? 'Accept Ride (1 credit)'
      : 'Insufficient Credits'
  ),
)
```

#### 3. `mobile/lib/driver/providers/ride_provider.dart`

**Handle insufficient credits error**:
```dart
try {
  await _rideService.acceptRide(rideId);
} on InsufficientCreditsException catch (e) {
  // Show dialog
  showDialog(
    context: context,
    builder: (_) => InsufficientCreditsDialog(
      currentBalance: e.balance,
      onTopUp: () => context.push('/credits'),
    ),
  );
}
```

---

## Admin Dashboard Implementation

### Add to `backend/public/admin-dashboard.html`

#### 1. Credit Settings Section
```html
<div id="creditSettingsSection" class="content-section">
  <h2>Credit System Settings</h2>

  <div class="settings-form">
    <label class="checkbox-label">
      <input type="checkbox" id="enableDailyCredits">
      <span>Enable Daily Credits</span>
    </label>

    <div class="form-group">
      <label>Daily Credits Amount</label>
      <input type="number" id="dailyCreditsAmount" min="1" max="100" value="10">
      <span class="help-text">Credits drivers can claim once per day</span>
    </div>

    <button class="btn-primary" onclick="saveCreditSettings()">
      Save Settings
    </button>
  </div>

  <div class="credit-stats">
    <h3>System Statistics</h3>
    <div class="stat-grid">
      <div class="stat-card">
        <h4>Total Credits Issued</h4>
        <p id="totalCreditsIssued">0</p>
      </div>
      <div class="stat-card">
        <h4>Total Credits Spent</h4>
        <p id="totalCreditsSpent">0</p>
      </div>
      <div class="stat-card">
        <h4>Pending Requests</h4>
        <p id="pendingRequests">0</p>
      </div>
    </div>
  </div>
</div>
```

#### 2. Credit Requests Section
```html
<div id="creditRequestsSection" class="content-section">
  <h2>Credit Requests</h2>

  <div class="filter-tabs">
    <button class="tab active" onclick="filterRequests('pending')">Pending</button>
    <button class="tab" onclick="filterRequests('approved')">Approved</button>
    <button class="tab" onclick="filterRequests('rejected')">Rejected</button>
  </div>

  <table id="creditRequestsTable">
    <thead>
      <tr>
        <th>Driver</th>
        <th>Amount</th>
        <th>Reason</th>
        <th>Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="creditRequestsBody">
      <!-- Populated via JavaScript -->
    </tbody>
  </table>
</div>

<script>
async function approveCreditRequest(requestId) {
  if (!confirm('Approve this credit request?')) return;

  const response = await fetch(`/api/admin/credits/requests/${requestId}/approve`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${localStorage.getItem('adminToken')}`,
      'Content-Type': 'application/json',
    },
  });

  if (response.ok) {
    showToast('Credit request approved');
    loadCreditRequests();
  }
}

async function rejectCreditRequest(requestId) {
  const reason = prompt('Reason for rejection (optional):');

  const response = await fetch(`/api/admin/credits/requests/${requestId}/reject`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${localStorage.getItem('adminToken')}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ admin_notes: reason }),
  });

  if (response.ok) {
    showToast('Credit request rejected');
    loadCreditRequests();
  }
}
</script>
```

#### 3. Add to Driver Detail Page
```html
<!-- On existing driver detail modal -->
<div class="credit-management">
  <h3>Credits</h3>
  <p class="balance">
    Balance: <strong id="driverCreditsBalance">0</strong> credits
  </p>

  <button class="btn-secondary" onclick="showGrantCreditsDialog()">
    Grant Credits
  </button>

  <h4>Recent Transactions</h4>
  <div id="driverCreditTransactions">
    <!-- Transaction list -->
  </div>
</div>

<!-- Grant Credits Dialog -->
<div id="grantCreditsDialog" class="modal" style="display: none;">
  <div class="modal-content">
    <h3>Grant Credits</h3>
    <input type="number" id="grantAmount" placeholder="Amount" min="1">
    <textarea id="grantReason" placeholder="Reason (optional)"></textarea>
    <button onclick="grantCredits()">Grant</button>
    <button onclick="closeGrantDialog()">Cancel</button>
  </div>
</div>
```

---

## Implementation Timeline

### Day 1: Database + Core Service
**Tasks**:
- [ ] Create migration file (`2025_11_30_create_credit_system.php`)
- [ ] Add columns to `driver_profiles` table
- [ ] Create `credit_transactions` table
- [ ] Create `credit_requests` table
- [ ] Run migrations
- [ ] Create `CreditTransaction` model
- [ ] Create `CreditRequest` model
- [ ] Create `CreditService` with all methods
- [ ] Write unit tests for `CreditService`

**Deliverable**: Database schema + core service ready

---

### Day 2: Backend API
**Tasks**:
- [ ] Create `CreditController` (4 endpoints)
- [ ] Create `AdminCreditController` (7 endpoints)
- [ ] Create `RequestCreditsRequest` validation
- [ ] Create `CreditTransactionResource`
- [ ] Create `CreditRequestResource`
- [ ] Create `InsufficientCreditsException`
- [ ] Modify `RideService::acceptRideRequest()`
- [ ] Modify `RideController::accept()` error handling
- [ ] Update `DriverProfile` model
- [ ] Update `UserResource`
- [ ] Add routes to `api.php`
- [ ] Test all endpoints with cURL

**Deliverable**: Complete backend API

---

### Day 3: Mobile UI
**Tasks**:
- [ ] Create `credits_screen.dart` (3 tabs)
- [ ] Create `credit_balance_widget.dart`
- [ ] Create `credits_provider.dart`
- [ ] Create `credit_service.dart`
- [ ] Modify `driver_home_screen.dart` (add balance)
- [ ] Modify `ride_request_screen.dart` (check credits)
- [ ] Modify `ride_provider.dart` (error handling)
- [ ] Create `insufficient_credits_dialog.dart`
- [ ] Create `low_credit_warning.dart`
- [ ] Add navigation routes
- [ ] Test mobile flow end-to-end

**Deliverable**: Complete mobile UI

---

### Day 4: Admin Dashboard
**Tasks**:
- [ ] Add credit settings section to HTML
- [ ] Add credit requests section to HTML
- [ ] Add grant credits to driver detail page
- [ ] Implement JavaScript functions:
  - [ ] `loadCreditSettings()`
  - [ ] `saveCreditSettings()`
  - [ ] `loadCreditRequests()`
  - [ ] `approveCreditRequest()`
  - [ ] `rejectCreditRequest()`
  - [ ] `grantCreditsToDriver()`
  - [ ] `loadDriverCreditTransactions()`
- [ ] Add CSS styling for credit sections
- [ ] Test admin dashboard flows

**Deliverable**: Complete admin dashboard

---

### Day 5: Testing & Polish
**Tasks**:
- [ ] Test credit deduction on ride accept
- [ ] Test race condition (2 drivers accept same ride)
- [ ] Test daily claim logic (can't claim twice)
- [ ] Test insufficient credits error flow
- [ ] Test request/approve/reject workflow
- [ ] Test admin grant credits
- [ ] Test credit settings changes
- [ ] Test transaction history pagination
- [ ] Performance test (1000 transactions)
- [ ] Edge case testing
- [ ] Bug fixes

**Deliverable**: Tested, production-ready system

---

### Day 6-7: Buffer & Documentation
**Tasks**:
- [ ] Update API documentation
- [ ] Create user guide for drivers
- [ ] Create admin guide for credit management
- [ ] Code review
- [ ] Final polish
- [ ] Deployment preparation

**Deliverable**: Fully documented system

---

## Business Logic Details

### Credit Deduction Rules

**When**: Driver clicks "Accept Ride"

**Process**:
1. Check if driver has >= 1 credit
2. If yes: Deduct 1 credit, create ride, create transaction
3. If no: Show error, block accept button

**No Refunds For**:
- Rider cancels after driver accepts
- Driver cancels after accepting
- Ride completed normally

**Refund Scenarios** (Admin manual only):
- System error during ride
- Admin intervention required
- Bug or glitch

### Daily Credits

**Amount**: Configurable (default: 10 credits/day)

**Claim Method**: Driver clicks "Claim Daily Credits" button

**Rules**:
- Can claim once per 24 hours
- Timer shows when next claim available
- Auto-resets at midnight
- Disabled drivers cannot claim

**UI**:
```
┌─────────────────────────────┐
│   Daily Credits Available   │
│                             │
│    [Claim 10 Credits]       │
│                             │
│  Next claim in: 18h 42m     │
└─────────────────────────────┘
```

### Credit Requests

**Driver Flow**:
1. Navigate to Credits screen
2. Click "Request Credits"
3. Enter amount (1-100)
4. Enter reason (required, min 10 chars)
5. Submit request
6. Wait for admin review

**Admin Flow**:
1. See notification badge (pending requests)
2. Review request (driver info, amount, reason)
3. Approve or reject with optional notes
4. Driver receives notification

**UI for Driver**:
```
Request Status: Pending
Amount: 50 credits
Reason: "Testing new routes in Depok area"
Submitted: Nov 30, 2025 10:30 AM
```

### Going Online Requirements

**Recommended Rule**: Require >= 1 credit to go online

**Implementation**:
```dart
// In go_online logic
if (credits < 1) {
  showDialog(
    'Cannot go online with 0 credits.
     Please claim daily credits or request more.'
  );
  return;
}
```

**Low Credit Warning**: Show warning when < 5 credits

---

## Testing Checklist

### Unit Tests
- [ ] `CreditService::getBalance()`
- [ ] `CreditService::canAcceptRide()`
- [ ] `CreditService::deductCredit()`
- [ ] `CreditService::addCredit()`
- [ ] `CreditService::claimDailyCredits()`
- [ ] `CreditService::canClaimDaily()`
- [ ] `CreditService::requestCredits()`

### Integration Tests
- [ ] Ride accept with sufficient credits (success)
- [ ] Ride accept with insufficient credits (error)
- [ ] Credit deduction creates transaction record
- [ ] Daily claim works once per day
- [ ] Daily claim fails if already claimed
- [ ] Credit request creation
- [ ] Credit request approval flow
- [ ] Credit request rejection flow
- [ ] Admin grant credits

### Edge Cases
- [ ] Race condition: 2 drivers accept same ride with 1 credit each
- [ ] Race condition: Driver accepts ride while admin deducts credits
- [ ] Daily claim exactly at midnight
- [ ] Driver tries to accept multiple rides with 1 credit
- [ ] Transaction rollback if ride creation fails
- [ ] Negative balance prevention
- [ ] Very large credit amounts (1000+)
- [ ] Request with empty reason
- [ ] Request with amount = 0

### Mobile UI Tests
- [ ] Balance displays correctly
- [ ] Transaction list pagination
- [ ] Daily claim button state (enabled/disabled)
- [ ] Countdown timer accuracy
- [ ] Request form validation
- [ ] Insufficient credits dialog
- [ ] Low credit warning threshold

### Admin Dashboard Tests
- [ ] Settings save/load
- [ ] Request list filters (pending/approved/rejected)
- [ ] Approve request updates list
- [ ] Reject request updates list
- [ ] Grant credits updates driver balance
- [ ] Statistics calculations

---

## Migration Path to Paid Credits

When ready to add payment (post-beta):

### Keep Existing Features
- ✅ Daily free credits (becomes free tier)
- ✅ Request credits (for special cases)
- ✅ Admin grant (for promotions/compensation)

### Add New Features
1. **Credit Packages**:
   - Small: 50 credits = Rp. 25,000
   - Medium: 100 credits = Rp. 45,000 (10% discount)
   - Large: 200 credits = Rp. 80,000 (20% discount)

2. **Payment Integration**:
   - Add Midtrans/Xendit SDK
   - Purchase endpoint
   - Payment verification webhook
   - Transaction receipts

3. **Mobile UI**:
   - Add "Buy Credits" button
   - Package selection screen
   - Payment status screen

### Backward Compatibility
All existing drivers keep:
- Current credit balance
- Transaction history
- Ability to claim daily credits
- Ability to request credits

**Implementation time for payment**: +1 week

---

## Success Metrics

### During Beta (Track These)

1. **Credit Usage**:
   - Average daily credits used per driver
   - Peak credit usage times
   - Percentage of drivers using daily claims
   - Percentage of drivers requesting credits

2. **Request Patterns**:
   - Average request amount
   - Most common request reasons
   - Approval rate
   - Time to admin review

3. **Balance Trends**:
   - Average driver balance
   - Number of drivers with 0 credits
   - Number of blocked ride accepts (insufficient credits)

### Use This Data To:
- Set optimal daily credit amount
- Determine credit package pricing
- Understand driver needs
- Identify abuse patterns

---

## Known Limitations (Beta)

1. **No Payment**: Manual top-up only via admin
2. **No Refunds**: Except manual admin intervention
3. **Simple Rules**: No complex pricing tiers
4. **Basic UI**: Functional but not polished
5. **No Analytics**: Basic stats only

**All acceptable for beta!** 🎯

---

## Dependencies

### Backend
- ✅ Laravel 11 (existing)
- ✅ PostgreSQL (existing)
- ✅ No new packages needed

### Mobile
- ✅ Flutter (existing)
- ✅ Riverpod (existing)
- ✅ No new packages needed

### External Services
- ❌ None (payment deferred)

---

## Rollback Plan

If issues arise, rollback is simple:

1. **Database**: Don't run down migration (keep tables)
2. **Backend**: Comment out credit check in `RideService`
3. **Mobile**: Hide credit UI sections
4. **Result**: System works as before, credits ignored

**Credit data preserved** for re-enabling later.

---

## Sign-Off Checklist

Before marking complete:

- [ ] All database migrations run successfully
- [ ] All API endpoints returning correct responses
- [ ] All mobile screens working without crashes
- [ ] Admin dashboard functional
- [ ] Daily claim works correctly
- [ ] Request/approve flow tested
- [ ] Ride accept deducts credit
- [ ] Insufficient credits error handled
- [ ] Tests passing (>90%)
- [ ] Documentation updated
- [ ] Code reviewed
- [ ] Ready for beta deployment

---

## Questions to Resolve Before Starting

1. **Daily Credits Amount**: Start with 10 or different amount?
2. **Minimum to Go Online**: Require 1 credit or allow 0?
3. **Low Credit Warning**: At what threshold? (Suggest: 5 credits)
4. **Request Limits**: Max request amount? (Suggest: 100 credits)
5. **Request Frequency**: Limit requests per day? (Suggest: 1 per day)

---

**Status**: 📋 Ready for Implementation
**Next Step**: Get approval and start Day 1 tasks
**Timeline**: 5-7 days from start to completion
