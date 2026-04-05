# Anjem Admin Panel — Technical Reference

**Stack**: Filament 3.2.123 on Laravel 11
**URL**: `/admin`
**Package**: `filament/filament ^3.2.123`

---

## Table of Contents

1. [Overview](#1-overview)
2. [Panel Bootstrap & Configuration](#2-panel-bootstrap--configuration)
3. [Authentication & Authorization](#3-authentication--authorization)
4. [Resources](#4-resources)
   - [DriverResource](#41-driverresource)
   - [KycResource](#42-kycresource)
   - [RiderResource](#43-riderresource)
   - [RideResource](#44-rideresource)
   - [AuditLogResource](#45-auditlogresource)
5. [Pages](#5-pages)
   - [Dashboard](#51-dashboard)
   - [LiveMonitoringPage](#52-livemonitoringpage)
6. [Widgets](#6-widgets)
   - [StatsOverviewWidget](#61-statsoverviewwidget)
   - [DailyRidesChartWidget](#62-dailyrideschartwidget)
   - [KycPendingWidget](#63-kycpendingwidget)
7. [Audit Logging System](#7-audit-logging-system)
8. [Backend Service Integrations](#8-backend-service-integrations)
9. [WebSocket / Broadcasting Integration](#9-websocket--broadcasting-integration)
10. [Data Model Reference](#10-data-model-reference)
11. [File Map](#11-file-map)

---

## 1. Overview

The admin panel is a server-rendered web interface built with Filament 3 that gives Anjem operators full visibility and control over the platform. It is entirely separate from the mobile API — it uses Filament's own session-based authentication and renders its own HTML views via Blade/Livewire. No Sanctum tokens or API routes are involved.

### Capabilities at a Glance

| Area | What You Can Do |
|---|---|
| Drivers | View profiles, credits, ratings, online status; grant/deduct credits; suspend/unsuspend accounts |
| KYC | Review pending driver applications with KTM photo; approve or reject with reason; auto-notifies driver via FCM |
| Riders | View accounts; suspend/unsuspend |
| Rides | Browse all rides with full lifecycle; force-complete or force-cancel stuck rides |
| Live Monitoring | Real-time view of pending requests + active rides + online drivers, with per-row override actions (10–15s auto-poll) |
| Audit Log | Immutable, searchable record of every admin action with IP, user-agent, reason, and before/after changes |
| Dashboard | KPI stats, 30-day revenue, daily rides line chart, and KYC pending count |

---

## 2. Panel Bootstrap & Configuration

**Provider**: `app/Providers/Filament/AdminPanelProvider.php`

This is a standard Filament `PanelProvider`. It is registered in `bootstrap/providers.php` and called during the Laravel service-provider boot phase.

```php
$panel
    ->id('admin')
    ->path('admin')
    ->login()
    ->colors(['primary' => Color::Indigo])
    ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
    ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
    ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
    ->navigationGroups(['Users', 'Rides', 'System'])
    ->authMiddleware([Authenticate::class]);
```

### Middleware Stack (applied to all `/admin` routes)

```
EncryptCookies
AddQueuedCookiesToResponse
StartSession
AuthenticateSession
ShareErrorsFromSession
VerifyCsrfToken
SubstituteBindings
DisableBladeIconComponents
DispatchServingFilamentEvent
Authenticate        ← redirects to /admin/login if unauthenticated
```

The `Authenticate` class here is Filament's own variant; on 401 it renders the login page, not a JSON response. This is entirely different from the `AdminOnly` API middleware used by REST endpoints.

### Navigation Groups & Sort Order

```
Users  (group)
  ├── Drivers          sort=1
  ├── KYC Review       sort=2
  └── Riders           sort=2

Rides  (group)
  ├── Rides            sort=1
  └── Live Monitoring  sort=2

System (group)
  └── Audit Log

Dashboard              sort=-2  (always first)
```

---

## 3. Authentication & Authorization

### Who Can Log In

The Filament login form at `/admin/login` uses standard Laravel `Auth::attempt()`. After authenticating, Filament calls `canAccessPanel(Panel $panel)` on the authenticated `User` model:

```php
// app/Models/User.php
public function canAccessPanel(Panel $panel): bool
{
    return $this->canAccessAdmin() && $this->is_active;
}

public function canAccessAdmin(): bool
{
    return $this->role === 'admin';
}
```

Any account where `role !== 'admin'` or `is_active = false` is redirected back to the login page immediately after credential check.

### Seeded Admin Accounts

Provisioned by `database/seeders/AdminUserSeeder.php`:

| Email | Password | Role |
|---|---|---|
| admin@anjem.app | admin123 | admin |
| test@anjem.app | test123 | admin |

Run with: `php artisan db:seed --class=AdminUserSeeder`

### Resource-Level Access Control

All five resources disable mutative operations for the Filament form layer:

```php
public static function canCreate(): bool  { return false; }
public static function canEdit($record): bool  { return false; }
public static function canDelete($record): bool { return false; }
```

Mutations happen exclusively through **Table Actions** (modals with confirmation). This prevents accidental bulk edits via the default edit form and forces every change through the audit-logged action pipeline.

### API vs Panel Auth

The mobile app's `AdminOnly` middleware (`app/Http/Middleware/AdminOnly.php`) is **not** involved in the Filament panel. It only guards REST API routes under `routes/api.php`. The two auth paths are completely independent.

---

## 4. Resources

### 4.1 DriverResource

**File**: `app/Filament/Resources/DriverResource.php`
**Model**: `User` (eager-loaded with `driverProfile`)
**Scope**: `whereHas('driverProfile')`
**Nav group**: Users | **Icon**: truck | **Sort**: 1

#### Table

| Column | Source | Notes |
|---|---|---|
| name | `users.name` | searchable, sortable |
| email | `users.email` | searchable, copyable |
| phone_number | `users.phone_number` | — |
| KYC Status | `driverProfile.is_verified` | 3-state badge: Approved (success) / Email Verified (warning) / Pending (gray) |
| Credits | `driverProfile.credits_balance` | numeric, sortable |
| Rating | `driverProfile.rating_average` | 1 dp, ★ suffix |
| Online | `driverProfile.went_online_at` | badge: Online (success) / Offline (gray) |
| Joined | `users.created_at` | since format, sortable |

**Filters**:
- `kyc_status` (SelectFilter): `approved` / `email_verified` / `pending` — translates to composite WHERE on `driverProfile.is_verified` and `driverProfile.email_verified_at`
- `online` (TernaryFilter): filters on `went_online_at IS NOT NULL / IS NULL`

#### Actions

##### Grant Credits
```
Icon: plus-circle  Color: success
Form:
  - amount (numeric, 1–100, required)
  - reason (text, required)
Logic:
  1. CreditService::addCredits($driver, $amount, $reason)
  2. AdminAuditLog::create([action_type: 'credit_grant', ...])
  3. broadcast(new DriverCreditsUpdated($driver))
```

##### Deduct Credits
```
Icon: minus-circle  Color: warning
Form:
  - amount (numeric, ≥1, required)
  - reason (text, required)
Logic:
  1. CreditService::adminDeductCredits($driver, $amount, $reason)
     └── throws RuntimeException on insufficient balance
  2. AdminAuditLog::create([action_type: 'credit_deduct', ...])
  3. broadcast(new DriverCreditsUpdated($driver))
```

##### Suspend / Unsuspend
```
Suspend  (visible if is_active=true):
  Form: reason (text, optional)
  Logic:
    1. $user->update(['is_active' => false, 'went_online_at' => null])
    2. AdminAuditLog::create([action_type: 'driver_suspend', ...])
    3. broadcast(new UserAccountStatusChanged($user))

Unsuspend  (visible if is_active=false):
  Logic:
    1. $user->update(['is_active' => true])
    2. AdminAuditLog::create([action_type: 'driver_unsuspend', ...])
    3. broadcast(new UserAccountStatusChanged($user))
```

#### View Page (Infolist)

Four sections: **Personal Info** (name, email, phone, role, is_active), **KYC & Verification** (student details, KTM path), **Vehicle** (type, plate, color), **Credits** (balance, total earned, total spent), **Rating** (average, count).

---

### 4.2 KycResource

**File**: `app/Filament/Resources/KycResource.php`
**Model**: `User` (with `driverProfile`)
**Nav group**: Users | **Icon**: identification | **Sort**: 2
**Model label**: "KYC Review"
**Slug**: `kyc` → URL `/admin/kyc`

#### Navigation Badge

A red badge shows the count of drivers whose email is verified but KYC is not yet approved:

```php
public static function getNavigationBadge(): ?string
{
    return (string) DriverProfile::whereNotNull('email_verified_at')
        ->where('is_verified', false)
        ->count();
}
// Color: 'danger' if count > 0, else 'success'
```

This badge updates on every Livewire page load.

#### Table

| Column | Notes |
|---|---|
| name | searchable |
| email | searchable, copyable |
| KYC Status | 4-state badge: Approved / Pending Review / Email Unverified / Not Submitted |
| student_name | from driverProfile |
| student_id | from driverProfile |
| vehicle_plate | from driverProfile |
| Email Verified | `driverProfile.email_verified_at` since format |

**Default filter**: `pending` — shows only `email_verified_at IS NOT NULL AND is_verified = false`.

#### Review KYC Action

This is the most complex single action in the panel.

```
Visible: only if (email_verified_at IS NOT NULL OR is_verified IS TRUE)
Modal width: 4xl

Display (custom HTML):
  - KTM photo (img tag pointing to public storage URL, or placeholder)
  - 2-column grid: student_name, student_id, student_email, vehicle_plate, vehicle_color

Form:
  - decision (ToggleButtons, inline, live):  approve | reject
  - reason (Textarea, min 10 chars, required ONLY if decision=reject, hidden if approve)

On Approve:
  1. DriverProfile::update(['is_verified' => true, 'ktm_url' => null])
  2. AdminAuditLog::create([action_type: 'kyc_approve', ...])
  3. Storage::disk('public')->delete($ktm_url)    ← removes the file
  4. NotificationService::sendKycApprovedToDriver($driver)   ← FCM push
  5. broadcast(new DriverKycStatusChanged($driver))

On Reject:
  1. DriverProfile::update([
       'is_verified' => false,
       'email_verified_at' => null,     ← forces re-verification
       'student_email' => null,
       'student_name' => null,
       'student_id' => null,
       'ktm_url' => null,
       'vehicle_plate' => null,
       'vehicle_color' => null,
       'vehicle_type' => null,
     ])
  2. AdminAuditLog::create([action_type: 'kyc_reject', reason: $reason, ...])
  3. Storage::disk('public')->delete($ktm_url)
  4. NotificationService::sendKycRejectedToDriver($driver)   ← FCM push
  5. broadcast(new DriverKycStatusChanged($driver))
```

A rejection fully resets the driver's KYC state — they must re-submit all documents and re-verify their student email from scratch.

---

### 4.3 RiderResource

**File**: `app/Filament/Resources/RiderResource.php`
**Model**: `User` (filtered: `role IN ('rider', 'both')`)
**Nav group**: Users | **Icon**: users | **Sort**: 2

#### Table

| Column | Notes |
|---|---|
| name | searchable, sortable |
| email | searchable, copyable |
| phone_number | — |
| role | badge: 'both' → warning, else → info |
| is_active | badge: Active (success) / Suspended (danger) |
| created_at | since, sortable |

**Actions**: Suspend / Unsuspend — same logic as DriverResource but logs `rider_suspend` / `rider_unsuspend`.

**View Page**: One section showing name, email, phone, role badge, is_active badge, created_at.

---

### 4.4 RideResource

**File**: `app/Filament/Resources/RideResource.php`
**Model**: `Ride`
**Nav group**: Rides | **Icon**: map | **Sort**: 1
**Default sort**: `created_at DESC`

#### Table

| Column | Notes |
|---|---|
| id | sortable |
| rider.name | searchable |
| driver.name | searchable |
| Route | computed: `"{pickup_location} → {destination_location}"` |
| status | badge with 6 colors: matched(info), accepted(primary), driver_arrived(warning), in_progress(success), completed(gray), cancelled(danger) |
| Fare | `actual_fare_rp` formatted as `Rp X.XXX` |
| Duration | `actual_duration_minutes` with `min` suffix |
| created_at | since, sortable |

**Filters**:
- `status` (SelectFilter, multiple): any combination of the 6 statuses
- `stuck` (Filter): `status IN (active statuses) AND updated_at < now() - 2h` — surfaces rides the system hasn't progressed
- `date_range`: `from` / `until` DatePickers with `whereDate` query

#### Force Complete Action

```
Visible: status IN [matched, accepted, driver_arrived, in_progress]
Form: reason (text, required, min 10 chars)
Logic:
  1. $ride->update(['status' => 'completed', 'dropoff_time' => now()])
  2. $ride->rideRequest->markAsCompleted()
  3. AdminAuditLog::create([action_type: 'ride_force_complete', ...])
  4. broadcast(new RideStatusUpdated($ride, admin: true))
  5. MatchingQueueService::rejoinAfterRide($ride->driver_id)
     └── re-inserts driver into FIFO matching queue
```

#### Force Cancel Action

```
Visible: status IN active statuses
Form: reason (text, required, min 10 chars)
Logic:
  1. $ride->update(['status' => 'cancelled', 'dropoff_time' => now()])
  2. $ride->rideRequest->markAsCancelled()
  3. AdminAuditLog::create([action_type: 'ride_cancel', ...])
  4. broadcast(new RideStatusUpdated($ride, admin: true))
  5. MatchingQueueService::rejoinAfterRide($ride->driver_id)
```

**View Page**: Shows all ride details — IDs, rider/driver names, pickup/destination, fares, timestamps.

---

### 4.5 AuditLogResource

**File**: `app/Filament/Resources/AuditLogResource.php`
**Model**: `AdminAuditLog`
**Nav group**: System | **Icon**: clipboard
**All mutations disabled** (read-only resource)
**Default sort**: `created_at DESC`

#### Table

| Column | Notes |
|---|---|
| admin.name | who performed the action |
| action_type | badge with semantic colors (see below) |
| target_type | `class_basename($value)` — e.g. `User`, `Ride` |
| target_id | the affected record's PK |
| reason | truncated to 50 chars, `—` if null |
| ip_address | `—` if null |
| created_at | since, sortable |

**Action type → badge color**:

| Color | Action Types |
|---|---|
| success | `kyc_approve`, `driver_unsuspend`, `rider_unsuspend` |
| danger | `kyc_reject` |
| warning | `driver_suspend`, `rider_suspend`, `credit_deduct` |
| info | `credit_grant` |
| primary | `force_ride_status`, `ride_cancel`, `ride_force_complete`, `request_cancel` |
| gray | everything else |

**Filters**:
- `action_type` (SelectFilter): all 12 defined action types with human-readable labels
- `date_range`: `from` / `until` DatePickers

**View Page (Infolist)**:
- **Audit Entry section**: admin name, action_type badge, target_type, target_id, reason (full width), ip_address, user_agent (full width), created_at
- **Changes section**: `changes` JSON column rendered full-width in monospace

The `changes` column stores a JSON diff of before/after values for the mutation — e.g. `{"is_active": {"from": true, "to": false}}`. This enables full audit reconstruction without relying on external packages.

---

## 5. Pages

### 5.1 Dashboard

**File**: `app/Filament/Pages/Dashboard.php`
**Icon**: home | **Sort**: -2 (always first in nav)
**Layout**: 1 column

Renders three widgets stacked vertically:
1. `StatsOverviewWidget` (full-width)
2. `DailyRidesChartWidget` (full-width)
3. `KycPendingWidget`

This is a thin wrapper — all logic lives in the widgets.

---

### 5.2 LiveMonitoringPage

**File**: `app/Filament/Pages/LiveMonitoringPage.php`
**View**: `resources/views/filament/pages/live-monitoring.blade.php`
**Nav group**: Rides | **Icon**: signal | **Sort**: 2
**Traits**: `InteractsWithActions`, `InteractsWithForms`

This is the operational nerve center. It uses Livewire's `wire:poll` to auto-refresh three data sets without a full page reload.

#### Data Methods

```php
// Pending + matched ride requests (not yet expired)
public function getPendingRequests(): Collection
{
    return RideRequest::whereIn('status', ['pending', 'matched'])
        ->where('expires_at', '>', now())
        ->orderBy('created_at')
        ->get();
}

// Rides currently in progress
public function getActiveRides(): Collection
{
    return Ride::whereIn('status', ['matched', 'accepted', 'driver_arrived', 'in_progress'])
        ->orderBy('updated_at')
        ->get();
}

// All drivers currently online
public function getOnlineDrivers(): Collection
{
    return User::with('driverProfile')
        ->whereHas('driverProfile', fn($q) => $q->whereNotNull('went_online_at'))
        ->get();
}
```

#### Blade View Structure

```
<div wire:poll.10000ms>   ← refreshes every 10s
    Active Ride Requests table
    Columns: ID | Rider | Route | Status | Dispatched To | Waiting Time | Actions

<div wire:poll.10000ms>   ← refreshes every 10s
    Active Rides table
    Columns: ID | Rider | Driver | Route | Status | Elapsed | Actions

<div wire:poll.15000ms>   ← refreshes every 15s
    Online Drivers table
    Columns: Name | Online Since | Minutes Online | Credits | Rating
```

The "Actions" column in the first two tables renders Filament action trigger buttons inline. Clicking them opens a Filament modal without leaving the page.

#### Actions

##### Force Cancel Ride Request
```
Label: Cancel Request  Icon: x-circle  Color: danger
Form: reason (optional)
Logic:
  1. Guard: request.status must be in [pending, matched]
  2. $request->update(['status' => 'cancelled', 'current_driver_id' => null])
  3. AdminAuditLog::create([action_type: 'ride_request_cancel', ...])
  4. broadcast(new RideRequestCancelled($request))
```

##### Force Complete (active ride)
```
Form: reason (required, min 10 chars)
Logic: identical to RideResource::force_complete
```

##### Force Cancel (active ride)
```
Form: reason (required, min 10 chars)
Logic: identical to RideResource::force_cancel
```

**Important**: These actions are defined on the Page class, not a Resource. They operate on raw model IDs passed through the Blade template's action buttons. Both `forceCompleteAction` and `forceCancelAction` call `MatchingQueueService::rejoinAfterRide()` to restore the driver's queue position after intervention.

---

## 6. Widgets

### 6.1 StatsOverviewWidget

**File**: `app/Filament/Widgets/StatsOverviewWidget.php`
**Type**: `StatsOverview` (Filament built-in)
**Sort**: 1 | **Span**: full width

Four stat cards:

| Card | Query | Icon | Color |
|---|---|---|---|
| Total Users | `User::count()` | users | gray |
| Online Drivers | `DriverProfile::whereNotNull('went_online_at')->count()` | truck | success |
| Active Rides | `Ride::whereIn('status', [matched,accepted,driver_arrived,in_progress])->count()` | map-pin | warning |
| Revenue (30d) | `Ride::completed()->whereBetween(created_at, [now-30d, now])->sum('actual_fare_rp')` formatted as `Rp X.XXX` | banknotes | success |

These stats are computed on every page load (no caching). For high-traffic deployments, consider adding `protected static ?string $pollingInterval = '30s';` or wrapping queries in cache.

---

### 6.2 DailyRidesChartWidget

**File**: `app/Filament/Widgets/DailyRidesChartWidget.php`
**Type**: `ChartWidget` → line chart
**Sort**: 2 | **Span**: full width
**Heading**: "Daily Completed Rides (30 days)"

Data construction:

```php
$data = Ride::selectRaw('DATE(created_at) as date, COUNT(*) as count')
    ->where('status', 'completed')
    ->where('created_at', '>=', now()->subDays(30))
    ->groupBy('date')
    ->orderBy('date')
    ->pluck('count', 'date');

// Fills in zeros for days with no completed rides
$labels = [];
$counts = [];
for ($i = 29; $i >= 0; $i--) {
    $date = now()->subDays($i)->format('Y-m-d');
    $labels[] = now()->subDays($i)->format('M d');
    $counts[] = $data[$date] ?? 0;
}
```

Chart configuration: indigo line with `fill: true`, `tension: 0.3`, semi-transparent fill.

---

### 6.3 KycPendingWidget

**File**: `app/Filament/Widgets/KycPendingWidget.php`
**Type**: `StatsOverview` (single stat)
**Sort**: 3

```php
$count = DriverProfile::where('is_verified', false)->count();

Stat::make('KYC Pending', $count)
    ->description('Drivers awaiting admin approval')
    ->icon('heroicon-o-identification')
    ->color($count > 0 ? 'danger' : 'success')
    ->url(route('filament.admin.resources.kyc.index'))
```

Clicking the card navigates directly to the KYC resource index. Color turns red whenever any driver is waiting.

---

## 7. Audit Logging System

Every destructive or sensitive admin action creates an `AdminAuditLog` record before or after the mutation.

### Model: `AdminAuditLog`

**Table**: `admin_audit_logs`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | — |
| admin_id | FK → users | cascade delete |
| action_type | varchar | see enum below |
| target_type | varchar | PHP class name |
| target_id | bigint | PK of affected record |
| changes | json | nullable — before/after diff |
| reason | text | nullable (made nullable in migration `2026_03_05`) |
| metadata | json | nullable — extra context |
| ip_address | varchar(45) | nullable — supports IPv6 |
| user_agent | text | nullable |
| created_at / updated_at | timestamp | — |

**Indexes**:
```sql
INDEX (admin_id, created_at)
INDEX (action_type, created_at)
INDEX (target_type, target_id)
```

### Action Types

| action_type | Triggered By |
|---|---|
| `credit_grant` | DriverResource: grant_credits |
| `credit_deduct` | DriverResource: deduct_credits |
| `driver_suspend` | DriverResource: suspend |
| `driver_unsuspend` | DriverResource: unsuspend |
| `rider_suspend` | RiderResource: suspend |
| `rider_unsuspend` | RiderResource: unsuspend |
| `kyc_approve` | KycResource: review_kyc (approve) |
| `kyc_reject` | KycResource: review_kyc (reject) |
| `ride_force_complete` | RideResource / LiveMonitoringPage |
| `ride_cancel` | RideResource / LiveMonitoringPage |
| `ride_request_cancel` | LiveMonitoringPage |
| `force_ride_status` | generic override (legacy) |

### How Entries Are Created

All action handlers call `AdminAuditLog::create()` inline with the authenticated admin's ID captured via `auth()->id()`. The `target_type` stores the fully-qualified class name (e.g. `App\Models\User`) which is displayed as `class_basename()` in the UI.

---

## 8. Backend Service Integrations

The admin panel is the only UI layer that directly calls these application services — the mobile API also calls them but through controller methods, whereas Filament calls them inline in action closures.

### CreditService

```php
// Grant
CreditService::addCredits(User $driver, int $amount, string $reason): void

// Deduct (throws RuntimeException on insufficient balance)
CreditService::adminDeductCredits(User $driver, int $amount, string $reason): void
```

### MatchingQueueService

Called after every force-complete or force-cancel to restore the driver's queue state:

```php
MatchingQueueService::rejoinAfterRide(int $driverId): void
// Clears driver_profiles.current_ride_id and re-inserts into FIFO queue
// Uses driver_profiles.queue_joined_at to track position
```

### NotificationService

Called after KYC decisions to push FCM notifications to the driver's mobile device:

```php
NotificationService::sendKycApprovedToDriver(User $driver): void
NotificationService::sendKycRejectedToDriver(User $driver): void
```

### Storage (KTM Files)

KTM (Kartu Tanda Mahasiswa — student ID card) photos are stored on the `public` disk. On any KYC resolution (approve or reject), the file is deleted:

```php
Storage::disk('public')->delete($driver->driverProfile->ktm_url);
```

---

## 9. WebSocket / Broadcasting Integration

Every admin action that changes user-visible state broadcasts a Laravel event via Reverb. The `admin: true` flag in the payload tells mobile clients to display an "admin action" message rather than a standard status change notification.

| Event Class | Channel | Trigger |
|---|---|---|
| `DriverCreditsUpdated` | `private-driver.{id}` | credit grant / deduct |
| `UserAccountStatusChanged` | `private-user.{id}` | suspend / unsuspend |
| `DriverKycStatusChanged` | `private-driver.{id}` | KYC approve / reject |
| `RideStatusUpdated` | `private-ride.{id}` | force complete / cancel |
| `RideRequestCancelled` | `private-user.{id}` | request cancel |

All broadcasts go through Laravel's queue (`QUEUE_CONNECTION=redis`). If the queue worker is not running, broadcasts will be delayed. The Reverb WebSocket server must also be running (`php artisan reverb:start`).

---

## 10. Data Model Reference

### User Model (FilamentUser)

```php
implements FilamentUser, MustVerifyEmail, Authenticatable

// Filament gate
canAccessPanel(Panel $panel): bool
  └── role === 'admin' && is_active === true

// Relationships used by admin panel
driverProfile(): HasOne(DriverProfile)
driverRides(): HasMany(Ride, 'driver_id')
riderRides(): HasMany(Ride, 'rider_id')
```

### DriverProfile (accessed via User)

Key columns read/written by the panel:

| Column | Used In |
|---|---|
| is_verified | KycResource (approve/reject), DriverResource (display) |
| email_verified_at | KycResource (filter, reject clears) |
| ktm_url | KycResource (display photo, delete on decision) |
| student_name/id/email | KycResource (display in modal) |
| vehicle_plate/color/type | KycResource (display), DriverResource (view) |
| credits_balance | DriverResource (display, CreditService updates) |
| credits_total_earned | DriverResource (view) |
| credits_total_spent | DriverResource (view) |
| rating_average | DriverResource (display) |
| rating_count | DriverResource (view) |
| went_online_at | DriverResource (online badge, cleared on suspend) |
| queue_joined_at | MatchingQueueService (rejoin after override) |

### Ride

Key columns used by RideResource and LiveMonitoringPage:

| Column | Notes |
|---|---|
| status | 6-state: matched/accepted/driver_arrived/in_progress/completed/cancelled |
| rider_id | FK → users |
| driver_id | FK → users |
| pickup_location_id | FK → locations |
| destination_location_id | FK → locations |
| actual_fare_rp | set when ride completes |
| actual_duration_minutes | set when ride completes |
| dropoff_time | set to `now()` on force-complete/cancel |

---

## 11. File Map

```
backend/
├── app/
│   ├── Filament/
│   │   ├── Pages/
│   │   │   ├── Dashboard.php
│   │   │   └── LiveMonitoringPage.php
│   │   ├── Resources/
│   │   │   ├── AuditLogResource.php
│   │   │   │   └── Pages/
│   │   │   │       ├── ListAuditLogs.php
│   │   │   │       └── ViewAuditLog.php
│   │   │   ├── DriverResource.php
│   │   │   │   └── Pages/
│   │   │   │       ├── ListDrivers.php
│   │   │   │       └── ViewDriver.php
│   │   │   ├── KycResource.php
│   │   │   │   └── Pages/
│   │   │   │       └── ListKyc.php
│   │   │   ├── RideResource.php
│   │   │   │   └── Pages/
│   │   │   │       ├── ListRides.php
│   │   │   │       └── ViewRide.php
│   │   │   └── RiderResource.php
│   │   │       └── Pages/
│   │   │           ├── ListRiders.php
│   │   │           └── ViewRider.php
│   │   └── Widgets/
│   │       ├── DailyRidesChartWidget.php
│   │       ├── KycPendingWidget.php
│   │       └── StatsOverviewWidget.php
│   ├── Models/
│   │   └── AdminAuditLog.php
│   └── Providers/
│       └── Filament/
│           └── AdminPanelProvider.php
├── database/
│   ├── migrations/
│   │   ├── 2025_12_01_110925_create_admin_audit_logs_table.php
│   │   └── 2026_03_05_132210_make_reason_nullable_on_admin_audit_logs.php
│   └── seeders/
│       └── AdminUserSeeder.php
└── resources/
    └── views/
        └── filament/
            └── pages/
                └── live-monitoring.blade.php
```
