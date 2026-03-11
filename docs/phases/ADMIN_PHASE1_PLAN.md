# Admin Dashboard — Phase 1: Backend API Endpoints

## Context

The Anjem backend has significant admin infrastructure already built (`AdminController`, `AdminAuditLog`, `AdminOnly` middleware, `CreditService`, admin routes). However, several critical mutating operations are missing, and most existing admin mutations do not write to `AdminAuditLog`. Phase 1 closes these gaps before any UI work begins.

---

## Current State

### Already exists (do not rebuild)
- `AdminController` — listDrivers, getDriver, suspendDriver, listRiders, getRider, suspendRider, analytics, monitoring, ride management (stuck, force-status, cancel, complete)
- `AdminAuditLog` model — polymorphic target, JSON changes/metadata; already used in `forceUpdateStatus()`
- `AdminOnly` middleware — checks `isAdmin()` + token ability `admin:view-users`
- `CreditService::addCredits()` — handles full grant flow including `CreditTransaction`
- `NotificationService::sendToUser()` — generic FCM sender
- `AdminControllerTest` — 627-line feature test covering all existing endpoints

### Gaps to fill

| Gap | Current state |
|---|---|
| KYC approve/reject by admin | No endpoint — self-service only |
| Credit grant route | `CreditService::addCredits()` exists but no admin route |
| Credit deduct by admin | No `adminDeductCredits()` method exists |
| KTM document viewer | `ktm_url` stored but no secure serve endpoint |
| Audit log reader | `AdminAuditLog` exists but no GET endpoint |
| Audit gaps in existing mutations | `suspendDriver/Rider`, `cancelRequest/Ride`, `completeRide` all use `Log::info()` instead of `AdminAuditLog` |
| KYC-specific FCM | No `sendKycApprovedToDriver()` / `sendKycRejectedToDriver()` methods |

---

## Implementation Plan

### Step 1 — `NotificationService.php`
**File:** `backend/app/Services/NotificationService.php`

Add two methods following the exact same pattern as existing notification methods:

**`sendKycApprovedToDriver(User $driver): void`**
- Title: `"KYC Approved!"`
- Body: `"Your driver verification has been approved. You can now go online and start accepting rides."`
- Data: `['type' => 'kyc_approved']`
- Calls existing private `sendNotification()` method

**`sendKycRejectedToDriver(User $driver, string $reason): void`**
- Title: `"KYC Verification Rejected"`
- Body: `"Your verification was rejected: {$reason}. Please resubmit with updated documents."`
- Data: `['type' => 'kyc_rejected', 'reason' => $reason]`
- Calls existing private `sendNotification()` method

---

### Step 2 — `CreditService.php`
**File:** `backend/app/Services/CreditService.php`

Add one method:

**`adminDeductCredits(DriverProfile $profile, int $amount, string $reason): array`**
- Must run inside a DB transaction (method is self-contained or caller wraps it)
- Use `lockForUpdate()` on the DriverProfile row
- Guard: throw exception if `credits_balance - amount < 0` (no negative balance allowed)
- Decrement `credits_balance`, increment `credits_total_spent`
- Create `CreditTransaction` with:
  - `type: 'admin_deduction'`
  - `amount: -$amount` (negative)
  - `balance_before`, `balance_after`
  - `description: $reason`
- Return `['balance_before' => ..., 'balance_after' => ...]`

Note: Do NOT modify the existing `addCredits()` method.

---

### Step 3 — `AdminController.php` — New Methods
**File:** `backend/app/Http/Controllers/Api/AdminController.php`

Add 6 new public methods:

**`approveKyc(Request $request, int $id): JsonResponse`**
- Validate: `reason` (optional, nullable string, max 500)
- Load: `User::whereHas('driverProfile')->findOrFail($id)`
- Wrap in `DB::transaction()`
- Set `driverProfile->is_verified = true` (leave `email_verified_at` unchanged — self-service)
- Write `AdminAuditLog::create()`:
  ```php
  [
      'admin_id'    => $request->user()->id,
      'action_type' => 'kyc_approve',
      'target_type' => DriverProfile::class,
      'target_id'   => $driver->driverProfile->id,
      'changes'     => ['is_verified' => ['old' => false, 'new' => true]],
      'reason'      => $request->reason,
      'ip_address'  => $request->ip(),
      'user_agent'  => $request->userAgent(),
  ]
  ```
- Call `app(NotificationService::class)->sendKycApprovedToDriver($driver)` (wrapped in try/catch)
- Return: `200 + formatDriverResponse()`

**`rejectKyc(Request $request, int $id): JsonResponse`**
- Validate: `reason` (required, string, min:10, max:500)
- Wrap in `DB::transaction()`
- Set `driverProfile->is_verified = false`, clear `email_verified_at = null` (full reset)
- Write `AdminAuditLog` with `action_type: 'kyc_reject'`, `changes: ['reason' => $reason]`
- Call `app(NotificationService::class)->sendKycRejectedToDriver($driver, $reason)`
- Return: `200`

**`grantCredits(Request $request, int $id): JsonResponse`**
- Validate: `amount` (required, integer, min:1, max:100), `reason` (required, string, min:5)
- Load driver profile
- Call `app(CreditService::class)->addCredits($driverProfile, $request->amount, $request->reason)`
- Write `AdminAuditLog` with:
  - `action_type: 'credit_grant'`
  - `changes: ['amount' => $amount, 'new_balance' => $newBalance]`
- Return: `200 + ['new_balance' => ...]`

**`deductCredits(Request $request, int $id): JsonResponse`**
- Validate: `amount` (required, integer, min:1), `reason` (required, string, min:5)
- Call `app(CreditService::class)->adminDeductCredits($driverProfile, $amount, $reason)` inside `DB::transaction()`
- Write `AdminAuditLog` with `action_type: 'credit_deduct'`
- Return: `200 + ['new_balance' => ...]`
- Return `422` if balance would go negative (catches exception from service)

**`getDriverDocument(int $id): JsonResponse`**
- Load driver and `driverProfile->ktm_url`
- If no document: return `404`
- Return: `200 + ['ktm_url' => $ktmUrl, 'driver_name' => $driver->name]`

**`getAuditLogs(Request $request): JsonResponse`**
- Query `AdminAuditLog::with('admin')` paginated (default 30/page)
- Filters: `action_type`, `admin_id`, `date_from` / `date_to`
- Order: `created_at DESC`
- Return: paginated list with admin name, action_type, target, reason, ip_address, created_at

---

### Step 4 — `AdminController.php` — Audit Log Retrofits

For each existing mutation, remove `Log::info()` and replace with `DB::transaction()` + `AdminAuditLog::create()`:

**`suspendDriver()`**
```php
AdminAuditLog::create([
    'admin_id'    => $request->user()->id,
    'action_type' => $request->suspended ? 'driver_suspend' : 'driver_unsuspend',
    'target_type' => User::class,
    'target_id'   => $id,
    'changes'     => ['is_active' => ['old' => !$request->suspended, 'new' => !$request->suspended ? false : true]],
    'reason'      => $request->reason,
    'ip_address'  => $request->ip(),
    'user_agent'  => $request->userAgent(),
]);
```

**`suspendRider()`** — same pattern, `action_type: 'rider_suspend'` or `'rider_unsuspend'`

**`cancelRequest()`** — add `action_type: 'request_cancel'`, target: `RideRequest::class`

**`cancelRide()`** — add `action_type: 'ride_cancel'`, target: `Ride::class`, changes: previous status

**`completeRide()`** — add `action_type: 'ride_force_complete'`, target: `Ride::class`, changes: previous status

---

### Step 5 — `routes/api.php`
**File:** `backend/routes/api.php`

Add to the existing `Route::prefix('admin')` group:
```php
// KYC Management
Route::post('drivers/{id}/kyc/approve',  [AdminController::class, 'approveKyc']);
Route::post('drivers/{id}/kyc/reject',   [AdminController::class, 'rejectKyc']);

// Credit Management
Route::post('drivers/{id}/credits/grant',  [AdminController::class, 'grantCredits']);
Route::post('drivers/{id}/credits/deduct', [AdminController::class, 'deductCredits']);

// Document Viewer
Route::get('drivers/{id}/document', [AdminController::class, 'getDriverDocument']);

// Audit Log
Route::get('audit-logs', [AdminController::class, 'getAuditLogs']);
```

No new middleware needed — all admin routes are already protected by `auth:sanctum + admin`.

Token abilities: KYC and credit endpoints require `admin:manage-users` — already issued for `role = admin` in `createTokenWithAbilities()`. No changes needed.

---

### Step 6 — `AdminKycCreditTest.php`
**File:** `backend/tests/Feature/Api/AdminKycCreditTest.php`

Tests to write:
- `test_admin_can_approve_kyc` — 200, `is_verified` becomes true, `AdminAuditLog` record created, FCM mock called
- `test_admin_can_reject_kyc_with_reason` — 200, `is_verified` false, `email_verified_at` cleared
- `test_kyc_reject_requires_reason` — 422 when reason missing
- `test_admin_can_grant_credits` — 200, balance incremented, `CreditTransaction` created
- `test_admin_can_deduct_credits` — 200, balance decremented, `CreditTransaction` created
- `test_credit_deduct_cannot_go_negative` — 422 when amount > balance
- `test_non_admin_cannot_approve_kyc` — 403 for rider/driver tokens
- `test_unauthenticated_cannot_approve_kyc` — 401
- `test_suspend_creates_audit_log` — existing suspend endpoint now writes `AdminAuditLog`
- `test_kyc_actions_create_audit_log` — approve/reject each create `AdminAuditLog` record
- `test_audit_logs_endpoint_returns_paginated_results` — GET `/admin/audit-logs` with filter

---

## Verification

```bash
# Run new tests
cd backend && php artisan test --filter AdminKycCreditTest

# Ensure existing tests still pass
cd backend && php artisan test --filter AdminControllerTest

# Full suite
cd backend && php artisan test

# Static analysis
cd backend && ./vendor/bin/phpstan analyse app/Http/Controllers/Api/AdminController.php
cd backend && ./vendor/bin/phpstan analyse app/Services/CreditService.php
cd backend && ./vendor/bin/phpstan analyse app/Services/NotificationService.php
```

---

## Completion Criteria

- [ ] All 6 new routes registered and responding correctly
- [ ] `approveKyc` / `rejectKyc` set correct DB fields + write `AdminAuditLog` + trigger FCM
- [ ] `grantCredits` uses existing `CreditService::addCredits()` unchanged
- [ ] `deductCredits` prevents negative balance
- [ ] All 5 existing mutations now write to `AdminAuditLog`
- [ ] `AdminKycCreditTest` — all tests green
- [ ] `AdminControllerTest` — all existing tests still green
- [ ] PHPStan passes with no new errors

**Do not start Phase 2 until all criteria above are met and reviewed.**
