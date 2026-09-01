# Admin Panel Phase 3 — Implementation Plan

## Context

The Anjem admin panel (Filament 3) currently covers driver/rider management, KYC review, ride oversight, polling-based live monitoring (10-15s), and audit logs. Phase 3 adds operational tooling (failed jobs, queue visualizer, system health) and upgrades live monitoring from Livewire polling to real-time WebSocket push via Laravel Echo + Reverb.

**Key discovery**: Filament 3 bundles Echo+Pusher JS and has built-in broadcasting support via `config/filament.php`. We do NOT need to install `laravel-echo` or `pusher-js` via npm — just publish and configure the Filament config.

**Real-time intent**: The goal for all Phase 3 features is real-time visibility into system internals — not just database state. This includes active WebSocket connections, live queue worker activity, and matching dispatch flow. Polling is only acceptable as a fallback.

---

## Implementation Order

```
3a. Failed Jobs Resource        (standalone, ~30 min)
3c. Laravel Horizon             (standalone, ~15 min)
3d. Real-time Live Monitoring   (depends on broadcasting infra, ~2-3 hrs)
3b. Driver Queue Visualizer     (depends on 3d's admin.live infra, ~45 min)
```

Features 3a and 3c (Horizon) can be built in parallel — both are standalone. Feature 3d is the most complex and must come before 3b, since the queue visualizer now depends on the `admin.live` broadcasting infrastructure.

---

## Feature 3a: Failed Jobs Resource

### New Files

**`backend/app/Models/FailedJob.php`**

- Eloquent model for existing `failed_jobs` table
- `$timestamps = false`, cast `failed_at` as datetime
- Accessor `getJobNameAttribute()`: parse `payload` JSON → extract class basename from `data.commandName`

**`backend/app/Filament/Resources/FailedJobResource.php`**

- `$navigationGroup = 'System'`, icon: `heroicon-o-exclamation-triangle`
- `canCreate() = false`, `canEdit() = false`
- Table columns: id, uuid (copyable), queue (badge), job name (computed from payload), exception (truncated 100 chars), failed_at (sortable, default desc)
- Filters: SelectFilter on `queue`, date range on `failed_at`
- Row actions:
  - **Retry**: `Artisan::call('queue:retry', ['id' => [$record->uuid]])` → delete record → success notification
  - **Delete**: `Artisan::call('queue:forget', ['id' => [$record->uuid]])`
- Bulk actions: Retry Selected, Delete Selected

**`backend/app/Filament/Resources/FailedJobResource/Pages/ListFailedJobs.php`**

- Standard ListRecords page

**`backend/app/Filament/Resources/FailedJobResource/Pages/ViewFailedJob.php`**

- ViewRecord page showing full exception (mono, scrollable) and pretty-printed payload JSON

### Modified Files

**`backend/app/Providers/Filament/AdminPanelProvider.php`** (line 46-52)

- Add `FailedJobResource::class` to `resources([...])`

---

## Feature 3b: Driver Queue Visualizer

### New Files

**`backend/app/Filament/Pages/DriverQueuePage.php`**

- `$navigationGroup = 'Rides'`, `$navigationSort = 3`, icon: `heroicon-o-queue-list`
- `$view = 'filament.pages.driver-queue'`
- `getQueuedDrivers()`: `DriverProfile::with('user')->whereNotNull('queue_joined_at')->orderBy('queue_joined_at', 'asc')->get()`
- `getQueueStats()`: total count, avg wait time, cooldown count

**`backend/resources/views/filament/pages/driver-queue.blade.php`**

- Stats bar: Total in Queue, Average Wait, In Cooldown
- Table (`wire:poll.60s` fallback only): Position #, Driver Name, Joined At, Wait Time (minutes), Max Pickup Radius (km), Decline Count (color-coded), Cooldown Status (badge), Dispatch Status (highlighted row for currently dispatched driver)
- **Real-time via Alpine + Echo** — subscribe to `private-admin.live`, handle `admin.live.update` events:
  - `driver_status` (online) → append driver row to bottom of queue
  - `driver_status` (decline/timeout + requeue) → move driver to bottom of queue
  - `matching_dispatch` → highlight dispatched driver's row with a "Dispatched" badge (matches `current_driver_id` on the active pending request)
  - `driver_rejoined` → move driver to bottom after completing a ride
- Visual indicator: the row being actively dispatched to shows a pulsing indigo badge; all others are neutral
- Connection status dot (green/red) matching Live Monitoring pattern
- Follow same Alpine + Echo wiring pattern as `live-monitoring.blade.php` (established in 3d)

### Modified Files

**`backend/app/Providers/Filament/AdminPanelProvider.php`** (line 53-56)

- Add `DriverQueuePage::class` to `pages([...])`

**Note on 3b dependency**: Feature 3b depends on 3d being complete. The Alpine + Echo wiring in `driver-queue.blade.php` requires `admin.live` channel, the `AdminLiveUpdate` event, and the two new fan-out listeners (`BroadcastAdminMatchingDispatch`, `BroadcastAdminDriverRejoined`) to be in place first.

---

## Feature 3c: Laravel Horizon + Slim System Health

### Overview

Replace the originally planned full System Health Dashboard with Laravel Horizon as the primary queue observability tool. Horizon provides far more useful queue data out of the box than a custom page could, and it requires minimal setup. A slim System Health page is kept only for the gaps Horizon doesn't cover.

### 3c-i: Install Laravel Horizon

**Commands:**

```bash
composer require laravel/horizon
php artisan horizon:install
```

This publishes `config/horizon.php` and `app/Providers/HorizonServiceProvider.php`.

**What Horizon provides at `/horizon`:**

- Real-time queue worker status (running/paused/idle)
- Currently processing jobs (with class name, payload, runtime)
- Pending job counts per queue
- Failed jobs with full exception context and stack trace
- Job throughput metrics (jobs/min over time)
- Redis queue depths via memory panel

**Access gate — modify `backend/app/Providers/HorizonServiceProvider.php`:**

```php
protected function gate(): void
{
    Gate::define('viewHorizon', function ($user) {
        return $user->isAdmin();
    });
}
```

Horizon's dashboard at `/horizon` is then gated to admin users only.

**Note on 3a redundancy**: The Failed Jobs Filament Resource (3a) overlaps with Horizon's failed jobs panel. Keep the Filament resource — it's useful for inline access during ride operations without leaving the admin panel. Treat Horizon as the deep-dive tool, Filament resource as the quick-action shortcut.

### 3c-ii: Slim System Health Page (Horizon gaps only)

**`backend/app/Filament/Pages/SystemHealthPage.php`**

- `$navigationGroup = 'System'`, `$navigationSort = 10`, icon: `heroicon-o-server-stack`
- `$view = 'filament.pages.system-health'`
- Methods (scope strictly to what Horizon doesn't show):
  - `getReverbChannels()`: `GET /apps/{appId}/channels` on Reverb's internal HTTP API (port 8080) → returns active channel list and subscriber counts. `{appId}` from `REVERB_APP_ID` env var. 2s timeout, try/catch.
  - `getApplicationMetrics()`: active rides count, online drivers count, pending requests count — from Eloquent, not Redis

**`backend/resources/views/filament/pages/system-health.blade.php`**

- `wire:poll.30s`
- Grid of `<x-filament::section>` cards:
  - **Reverb WebSockets**: active channel count, channel list (name + subscriber count) — sourced from Reverb HTTP API
  - **Application Stats**: active rides, online drivers, pending requests

Deliberately omit Redis metrics and queue stats — those live in Horizon now.

### Modified Files

**`backend/app/Providers/Filament/AdminPanelProvider.php`**

- Add `SystemHealthPage::class` to `pages([...])`

---

## Feature 3d: Real-time Live Monitoring Upgrade

### Architecture: Listener Pattern (Option B)

Existing events remain untouched. New listeners catch each event and broadcast a normalized `AdminLiveUpdate` to a dedicated `admin.live` channel. This keeps admin concerns decoupled from the core event system.

### Step 1: Dual-Auth Broadcast Routes

**Modify: `backend/app/Providers/BroadcastServiceProvider.php`**

```php
public function boot(): void
{
    // Mobile API clients (bearer token auth)
    Broadcast::routes(['middleware' => ['auth:sanctum']]);

    // Admin panel (session-based auth)
    Broadcast::routes([
        'prefix' => 'admin',
        'middleware' => ['web', 'auth'],
    ]);

    require base_path('routes/channels.php');
}
```

This creates `/admin/broadcasting/auth` using the session guard for Filament, while keeping `/broadcasting/auth` with Sanctum for mobile.

### Step 2: Publish Filament Config with Echo/Reverb

**Create: `backend/config/filament.php`**

Copy from `vendor/filament/support/config/filament.php`, then uncomment and configure the `echo` block:

```php
'broadcasting' => [
    'echo' => [
        'broadcaster' => 'reverb',
        'key' => env('VITE_REVERB_APP_KEY'),
        'wsHost' => env('VITE_REVERB_HOST', '127.0.0.1'),
        'wsPort' => env('VITE_REVERB_PORT', 8080),
        'wssPort' => env('VITE_REVERB_PORT', 8080),
        'authEndpoint' => '/admin/broadcasting/auth',
        'forceTLS' => false,
        'disableStats' => true,
        'enabledTransports' => ['ws', 'wss'],
    ],
],
```

No npm install or Vite build needed — Filament bundles Echo JS.

### Step 3: Admin Broadcast Channel

**Modify: `backend/routes/channels.php`** — append:

```php
Broadcast::channel('admin.live', function ($user) {
    return $user->isAdmin();
});
```

### Step 4: AdminLiveUpdate Event

**Create: `backend/app/Events/AdminLiveUpdate.php`**

- Implements `ShouldBroadcastNow` (instant, no queue delay)
- Constructor: `string $type`, `array $payload`
- `broadcastOn()`: `[new PrivateChannel('admin.live')]`
- `broadcastAs()`: `'admin.live.update'`
- `broadcastWith()`: `['type' => $type, 'payload' => $payload, 'timestamp' => now()->toISOString()]`
- Types: `ride_status`, `new_request`, `request_cancelled`, `driver_status`, `kyc_updated`, `credits_updated`

### Step 5: Event Listeners (6 files)

All in `backend/app/Listeners/`:

| Listener                             | Listens To                  | AdminLiveUpdate Type | Used By          |
| ------------------------------------ | --------------------------- | -------------------- | ---------------- |
| `BroadcastAdminRideStatusUpdate`     | `RideStatusUpdated`         | `ride_status`        | Live Monitor     |
| `BroadcastAdminNewRideRequest`       | `NewRideRequest`            | `new_request`        | Live Monitor     |
| `BroadcastAdminRideRequestCancelled` | `RideRequestCancelled`      | `request_cancelled`  | Live Monitor     |
| `BroadcastAdminDriverStatusChange`   | `DriverOnlineStatusChanged` | `driver_status`      | Live Monitor, 3b |
| `BroadcastAdminKycUpdate`            | `DriverKycStatusChanged`    | `kyc_updated`        | Live Monitor     |
| `BroadcastAdminCreditsUpdate`        | `DriverCreditsUpdated`      | `credits_updated`    | Live Monitor     |
| `BroadcastAdminMatchingDispatch`     | `NewRideRequest`            | `matching_dispatch`  | 3b Queue Viz     |
| `BroadcastAdminDriverRejoined`       | `DriverOnlineStatusChanged` | `driver_rejoined`    | 3b Queue Viz     |

Each listener constructs and broadcasts an `AdminLiveUpdate` with normalized payload data (ids, names, statuses).

**Note on `BroadcastAdminMatchingDispatch`**: fires when a ride request enters matching — payload includes `request_id` and the `current_driver_id` being dispatched to. The queue visualizer uses this to highlight the dispatched driver row.

**Note on `BroadcastAdminDriverRejoined`**: fires on `DriverOnlineStatusChanged` events that occur after a completed ride (distinguished by checking if driver had an active ride in the recent past, or by a `rejoined: true` flag on the event payload). Moves driver to bottom of queue in the visualizer.

### Step 6: Register Listeners

**Modify: `backend/app/Providers/EventServiceProvider.php`** — add to `$listen`:

```php
\App\Events\RideStatusUpdated::class => [\App\Listeners\BroadcastAdminRideStatusUpdate::class],
\App\Events\NewRideRequest::class => [
    \App\Listeners\BroadcastAdminNewRideRequest::class,
    \App\Listeners\BroadcastAdminMatchingDispatch::class,   // queue visualizer dispatch highlight
],
\App\Events\RideRequestCancelled::class => [\App\Listeners\BroadcastAdminRideRequestCancelled::class],
\App\Events\DriverOnlineStatusChanged::class => [
    \App\Listeners\BroadcastAdminDriverStatusChange::class,
    \App\Listeners\BroadcastAdminDriverRejoined::class,     // queue visualizer post-ride rejoin
],
\App\Events\DriverKycStatusChanged::class => [\App\Listeners\BroadcastAdminKycUpdate::class],
\App\Events\DriverCreditsUpdated::class => [\App\Listeners\BroadcastAdminCreditsUpdate::class],
```

### Step 7: Upgrade Live Monitoring Blade Template

**Modify: `backend/resources/views/filament/pages/live-monitoring.blade.php`**

Changes:

1. **Reduce polling** from `wire:poll.10s`/`wire:poll.15s` to `wire:poll.60s` (fallback only)
2. **Add Alpine.js component** wrapping the page:
   - `x-data="liveMonitor()"` with `connected` state
   - `init()` subscribes to `private-admin.live` via `window.Echo`
   - `handleUpdate(data)` calls `@this.call('$refresh')` on relevant events
3. **Connection status indicator**: green/red dot + "Live connected" / "Reconnecting..." text
4. **Toast for new ride requests**: Use Filament's JS notification API (`new FilamentNotification()`)
5. **Row flash CSS animation**: `@keyframes flash-row` with indigo highlight fade

### Step 8: Update LiveMonitoringPage PHP (minor)

**Modify: `backend/app/Filament/Pages/LiveMonitoringPage.php`**

No major changes needed — `$refresh` via `@this.call` from Alpine already triggers full Livewire re-render which re-calls `getPendingRequests()`, `getActiveRides()`, `getOnlineDrivers()`.

---

## Complete File Inventory

### New Files (20)

| File                                                                        | Purpose                                           | Feature |
| --------------------------------------------------------------------------- | ------------------------------------------------- | ------- |
| `backend/app/Models/FailedJob.php`                                          | Model for failed_jobs table                       | 3a      |
| `backend/app/Filament/Resources/FailedJobResource.php`                      | Failed jobs CRUD resource                         | 3a      |
| `backend/app/Filament/Resources/FailedJobResource/Pages/ListFailedJobs.php` | List page                                         | 3a      |
| `backend/app/Filament/Resources/FailedJobResource/Pages/ViewFailedJob.php`  | View page                                         | 3a      |
| `backend/app/Filament/Pages/DriverQueuePage.php`                            | Queue visualizer page (Alpine + Echo, 60s fallback) | 3b    |
| `backend/resources/views/filament/pages/driver-queue.blade.php`             | Queue visualizer template                         | 3b      |
| `backend/app/Filament/Pages/SystemHealthPage.php`                           | Slim health page (Reverb channels + app stats)    | 3c      |
| `backend/resources/views/filament/pages/system-health.blade.php`            | System health template                            | 3c      |
| `backend/config/filament.php`                                               | Published config with Echo/Reverb                 | 3d      |
| `backend/app/Events/AdminLiveUpdate.php`                                    | Normalized admin broadcast event                  | 3d      |
| `backend/app/Listeners/BroadcastAdminRideStatusUpdate.php`                  | Fan-out listener                                  | 3d      |
| `backend/app/Listeners/BroadcastAdminNewRideRequest.php`                    | Fan-out listener                                  | 3d      |
| `backend/app/Listeners/BroadcastAdminRideRequestCancelled.php`              | Fan-out listener                                  | 3d      |
| `backend/app/Listeners/BroadcastAdminDriverStatusChange.php`                | Fan-out listener (also feeds 3b)                  | 3d      |
| `backend/app/Listeners/BroadcastAdminKycUpdate.php`                         | Fan-out listener                                  | 3d      |
| `backend/app/Listeners/BroadcastAdminCreditsUpdate.php`                     | Fan-out listener                                  | 3d      |
| `backend/app/Listeners/BroadcastAdminMatchingDispatch.php`                  | Dispatch highlight for queue visualizer           | 3b/3d   |
| `backend/app/Listeners/BroadcastAdminDriverRejoined.php`                    | Post-ride rejoin event for queue visualizer        | 3b/3d   |

**Horizon-installed files** (via `php artisan horizon:install` — not manually created):

| File                                              | Purpose                          | Feature |
| ------------------------------------------------- | -------------------------------- | ------- |
| `backend/config/horizon.php`                      | Horizon queue supervisor config  | 3c      |
| `backend/app/Providers/HorizonServiceProvider.php` | Auth gate (admin-only `/horizon`) | 3c     |

### Modified Files (5)

| File                                                               | Changes                                                                                          |
| ------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------ |
| `backend/app/Providers/Filament/AdminPanelProvider.php`            | Add FailedJobResource, DriverQueuePage, SystemHealthPage                                         |
| `backend/app/Providers/BroadcastServiceProvider.php`               | Add session-auth broadcast route for admin                                                       |
| `backend/app/Providers/EventServiceProvider.php`                   | Register 8 admin fan-out listeners (6 original + MatchingDispatch + DriverRejoined)              |
| `backend/routes/channels.php`                                      | Add `admin.live` channel                                                                         |
| `backend/resources/views/filament/pages/live-monitoring.blade.php` | Echo subscription, 60s fallback polling, connection indicator, flash animation, toast            |

---

## Key Risks & Mitigations

| Risk                                          | Mitigation                                                                                                   |
| --------------------------------------------- | ------------------------------------------------------------------------------------------------------------ |
| CSRF on `/admin/broadcasting/auth`            | Filament layout includes CSRF meta tag; Echo sends it automatically                                          |
| Filament Echo + Reverb compatibility          | Reverb speaks Pusher protocol; if `broadcaster: 'reverb'` fails, fall back to `'pusher'` with same host/port |
| High event volume to admin channel            | `ShouldBroadcastNow` is synchronous; 1-3 admin subscribers is trivial load; Livewire debounces `$refresh`    |
| Horizon composer dependency                   | `laravel/horizon` requires Redis driver; already in use — no additional infra needed                         |
| Horizon auth gate misconfiguration            | Gate in `HorizonServiceProvider` must use `$user->isAdmin()` — verify the method exists before deploying    |
| Reverb HTTP API unavailable                   | `SystemHealthPage::getReverbChannels()` must wrap in try/catch; show "Unavailable" if Reverb is down or port unreachable |
| `BroadcastAdminDriverRejoined` false positives | `DriverOnlineStatusChanged` fires for any online status change; listener must inspect payload to confirm post-ride context before broadcasting `driver_rejoined` |
| 3b depends on 3d infra                        | Do not build the queue visualizer's Alpine/Echo layer until `AdminLiveUpdate`, `admin.live` channel, and the two new listeners are wired up and tested |

---

## Verification

### Per-Feature Testing

1. **Failed Jobs**: Dispatch a job that throws → verify it appears in admin → retry → verify it disappears and re-runs
2. **Laravel Horizon**: Navigate to `/horizon` → confirm gated to admin only → go online with a driver → verify worker is processing → dispatch a failing job → confirm it appears in Horizon's failed jobs panel with full stack trace
3. **System Health (slim)**: Verify Reverb channels list loads (connect a mobile client first so at least one channel exists) → check active ride/driver counts match DB state → take Reverb down → verify "Unavailable" shows gracefully
4. **Queue Visualizer** (requires 3d complete first):
   - Go online with 2+ drivers → verify rows appear in FIFO order in real-time (no page refresh)
   - Create a ride request → verify the matched driver's row highlights as "Dispatched" instantly
   - Complete a ride → verify driver moves to bottom of queue in real-time
   - Kill Reverb → verify fallback 60s polling kicks in (red connection dot)
5. **Real-time Monitoring**:
   - Open Live Monitor in browser, check green connection dot
   - Create a ride request from mobile → verify row appears instantly (no 60s wait)
   - Accept/complete ride → verify status updates flash in real-time
   - Take a driver online/offline → verify Online Drivers table updates
   - Kill Reverb → verify red dot appears, page falls back to 60s polling
   - Restart Reverb → verify green dot returns, real-time resumes

### Integration Test

- `php artisan test` — ensure no regressions
- `./vendor/bin/phpstan analyse` — static analysis clean
