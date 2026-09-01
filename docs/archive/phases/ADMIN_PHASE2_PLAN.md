# Admin Dashboard — Phase 2: Filament UI

## Context

With Phase 1 complete, all backend admin endpoints exist and every mutation writes to `AdminAuditLog`. Phase 2 installs Filament 3 in the same `backend/` repo and builds a polished, operable dashboard for non-technical internal partners — hosted at `admin.anjem.app`, protected by auth + 2FA.

**Prerequisite:** Phase 1 must be merged, all tests passing, and backend reviewed before starting this phase.

---

## Tech Decisions

| Decision | Choice | Rationale |
|---|---|---|
| UI framework | **Filament 3** | Same Laravel repo, zero SPA overhead, rich built-in components for CRUD, actions, charts |
| Auth | Existing `User` model + `isAdmin()` / `canAccessAdmin()` | No new auth layer needed |
| 2FA | Filament's built-in TOTP (`requiresTwoFactorAuthentication()`) | Built-in, no extra package |
| Subdomain | `admin.anjem.app` handled at nginx/Caddy | Laravel path stays `/admin` |
| Chart data | Direct Eloquent queries in widget classes | Same logic as existing `getOverview` / `getRideAnalytics` endpoints |

---

## Installation

```bash
cd backend

# Install Filament
composer require filament/filament:"^3.2"
php artisan filament:install --panels
# Creates: app/Providers/Filament/AdminPanelProvider.php

# 2FA migration (adds two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at)
php artisan make:migration add_two_factor_columns_to_users_table
php artisan migrate

# Publish assets
php artisan filament:assets
```

---

## Files to Modify

### `backend/app/Models/User.php`
Implement the `FilamentUser` interface so Filament knows who can access the panel:

```php
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->canAccessAdmin(); // existing method — role === 'admin' && is_active
    }
}
```

Also add the `TwoFactorAuthenticatable` trait from Laravel Fortify (pulled in by Filament).

---

## Files to Create

### `app/Providers/Filament/AdminPanelProvider.php`

```php
->id('admin')
->path('/admin')
->login()
->colors(['primary' => Color::Indigo])  // Anjem brand
->requiresTwoFactorAuthentication()
->navigationGroups(['Users', 'Rides', 'Analytics', 'System'])
->resources([
    DriverResource::class,
    RiderResource::class,
    RideResource::class,
    AuditLogResource::class,
])
->pages([Dashboard::class, LiveMonitoringPage::class])
->widgets([StatsOverviewWidget::class, DailyRidesChartWidget::class, KycPendingWidget::class])
```

---

### Resource 1: `app/Filament/Resources/DriverResource.php`

**Table columns:**
- `name`, `email`, `phone_number`
- `kyc_status` — badge (green = approved, yellow = email_verified, red = pending)
- `is_verified` — toggle icon
- `credits_balance` — numeric with IDR icon
- `rating_average` — star icon + number
- `is_online` — green/gray dot badge
- `created_at` — date

**Filters:**
- KYC status select: `approved / email_verified / pending`
- Online status boolean
- Registration date range

**Row actions (all require confirmation modal):**

| Action | Endpoint | Input |
|---|---|---|
| Approve KYC | `POST /admin/drivers/{id}/kyc/approve` | Optional reason |
| Reject KYC | `POST /admin/drivers/{id}/kyc/reject` | Required reason (min 10 chars) |
| Grant Credits | `POST /admin/drivers/{id}/credits/grant` | Amount (1–100) + reason |
| Deduct Credits | `POST /admin/drivers/{id}/credits/deduct` | Amount + reason |
| Suspend / Unsuspend | `POST /admin/drivers/{id}/suspend` | Toggle + reason |
| View KTM Document | `GET /admin/drivers/{id}/document` | Opens image in modal lightbox |

**View page** (read-only detail):
- Driver profile section: name, email, phone, vehicle info, student info
- KYC status card with KTM image preview
- Credit balance card + paginated `CreditTransaction` table
- Rating card with average + count
- Recent rides table (last 10, from `getDriver` stats)

---

### Resource 2: `app/Filament/Resources/RiderResource.php`

**Table columns:** name, email, phone, role badge, is_active toggle, total_rides, created_at

**Row actions:**
- Suspend / Unsuspend (with reason field)

**View page:** ride history table, total amount spent

---

### Resource 3: `app/Filament/Resources/RideResource.php`

**Table columns:** id, rider name, driver name, pickup → destination (arrow), status badge (color-coded by status), fare (IDR), duration (min), created_at

**Status badge colors:**
- `matched` → gray
- `accepted` → blue
- `driver_arrived` → yellow
- `in_progress` → indigo
- `completed` → green
- `cancelled` → red

**Filters:** status multi-select, date range, driver name search, rider name search

**Tabs:**
- All Rides
- Active (matched/accepted/driver_arrived/in_progress)
- Stuck (active + `updated_at < now - 2h`)
- Completed
- Cancelled

**Row actions (only shown for active statuses):**
- `Force Complete` — requires reason (min 10 chars), confirmation modal, calls `POST /admin/rides/{id}/force-status` with `status: completed`
- `Force Cancel` — requires reason, same endpoint with `status: cancelled`

**View page:** full ride details, rider/driver cards, fare breakdown

---

### Resource 4: `app/Filament/Resources/AuditLogResource.php`

**Read-only** — override:
```php
public static function canCreate(): bool { return false; }
public static function canEdit(Model $record): bool { return false; }
public static function canDelete(Model $record): bool { return false; }
```

**Table columns:** admin name, action_type badge, target (type + id), reason (truncated), ip_address, created_at

**Action type badge colors:**
- `kyc_approve` → green
- `kyc_reject` → red
- `driver_suspend` / `rider_suspend` → orange
- `credit_grant` → blue
- `credit_deduct` → yellow
- `force_ride_status` / `ride_cancel` / `ride_force_complete` → purple

**Filters:** action_type select, admin select, date range

**View page:** full record with `changes` JSON rendered as before/after diff table

---

### Page 1: `app/Filament/Pages/Dashboard.php`

Overrides Filament's default dashboard. Layout:

**Row 1 — KPI Stats** (`StatsOverviewWidget`):
- Total Users (riders + drivers)
- Online Drivers right now
- Active Rides right now
- Revenue last 30 days (IDR formatted)

**Row 2 — Charts + Alerts:**
- `DailyRidesChartWidget` — line chart of rides per day (30 days), uses same query as `getRideAnalytics`
- `KycPendingWidget` — count of `is_verified = false` drivers + quick link to Drivers list filtered to pending

---

### Page 2: `app/Filament/Pages/LiveMonitoringPage.php`

Custom full-width page (navigation group: Rides):

**Section 1 — Active Rides table:**
- Columns: ride id, rider, driver, pickup→destination, status, minutes elapsed
- Auto-refresh: Livewire `wire:poll.10s`
- Inline actions: Force Complete, Force Cancel

**Section 2 — Online Drivers table:**
- Columns: name, online since, minutes online, has active ride badge, coordinates
- Auto-refresh: Livewire `wire:poll.15s`

---

### Widgets

**`app/Filament/Widgets/StatsOverviewWidget.php`**
Extends `Filament\Widgets\StatsOverviewWidget`. Four stat cards pulling from direct Eloquent queries (mirrors `getOverview` logic — do not call the API endpoint, query DB directly for speed).

**`app/Filament/Widgets/DailyRidesChartWidget.php`**
Extends `Filament\Widgets\ChartWidget`. Line chart, 30-day window, uses `Ride::selectRaw('DATE(created_at)...')` query.

**`app/Filament/Widgets/KycPendingWidget.php`**
Extends `Filament\Widgets\StatsOverviewWidget` (single card). Shows count of drivers with `is_verified = false` — link navigates to `DriverResource` list with `kyc_status=pending` filter pre-applied.

---

## Navigation Structure

```
Dashboard
├── Users
│   ├── Drivers       (DriverResource — KYC, credits, suspend)
│   └── Riders        (RiderResource — suspend)
├── Rides
│   ├── All Rides     (RideResource — history, force actions)
│   └── Live Monitor  (LiveMonitoringPage — real-time polling)
└── System
    └── Audit Logs    (AuditLogResource — read-only)
```

---

## Subdomain & Auth Configuration

### nginx config (on server — outside repo)
```nginx
server {
    server_name admin.anjem.app;
    location / {
        proxy_pass http://127.0.0.1:8000;
    }
}
```

### Filament already handles:
- Login page at `/admin/login`
- 2FA setup + enforcement (users prompted to configure TOTP on first login)
- Session-based auth (Sanctum API tokens are not used for Filament — it uses web session guard)

---

## Verification

```bash
# Install and migrate
cd backend
composer require filament/filament:"^3.2"
php artisan filament:install --panels
php artisan migrate

# Serve
php artisan serve

# Smoke tests (manual)
# 1. Visit http://localhost:8000/admin
# 2. Log in with credentials from AdminUserSeeder (development only — change before production)
# 3. Set up TOTP 2FA (required on first login)
# 4. Verify Dashboard loads with KPI stats
# 5. Go to Drivers → approve a KYC → confirm audit log created
# 6. Go to Rides → force-complete a stuck ride → confirm audit log
# 7. Go to System → Audit Logs → verify entries visible

# Static analysis
cd backend && ./vendor/bin/phpstan analyse app/Filament/

# Flutter mobile tests should still pass (Filament does not touch API routes)
cd backend && php artisan test
```

---

## Completion Criteria

- [ ] Filament panel accessible at `/admin` with login + 2FA enforced
- [ ] `DriverResource` — all 6 row actions (KYC approve/reject, credit grant/deduct, suspend, document view) working end-to-end with confirmation modals
- [ ] `RiderResource` — suspend/unsuspend working
- [ ] `RideResource` — stuck tab populated, force-complete/cancel writing to audit log
- [ ] `AuditLogResource` — read-only, filterable, shows all admin actions including Phase 1 retrofits
- [ ] Dashboard — 4 KPI cards + daily rides chart + KYC pending widget rendering
- [ ] `LiveMonitoringPage` — auto-refreshing every 10–15 seconds
- [ ] PHPStan passes with no new errors
- [ ] All existing `php artisan test` pass (Filament does not touch API routes)
