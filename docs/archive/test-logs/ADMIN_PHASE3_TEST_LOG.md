# Device Test Log — Admin Panel Phase 3 (`feat/admin-phase3`)

> **Branch:** `feat/admin-phase3`
> **Date:**
> **Tester:** Jonathan
> **Access:** Browser at `http://localhost:8000/admin`
> **Login:** credentials from `database/seeders/AdminUserSeeder.php`

---

## Status Summary

| Test                                                                                        | Status   |
| ------------------------------------------------------------------------------------------- | -------- |
| [P3-1 Failed Jobs Resource](#p3-1-failed-jobs-resource)                                     | ⬜       |
| [P3-2 Laravel Horizon](#p3-2-laravel-horizon)                                               | ⬜       |
| [P3-3 System Health Page](#p3-3-system-health-page)                                         | ⬜       |
| [P3-4 Real-time Live Monitor — WebSocket Push](#p3-4-real-time-live-monitor--websocket-push) | ⬜       |
| [P3-5 Driver Queue Visualizer](#p3-5-driver-queue-visualizer)                               | ⬜       |

> **Status key:** ⬜ Not tested · ✅ Pass · ❌ Fail · ⚠️ Partial

---

## Pre-Test Setup

Run from `backend/` before testing. All four processes must be running.

```bash
php artisan serve          # terminal 1
php artisan reverb:start   # terminal 2
php artisan queue:work     # terminal 3
php artisan schedule:work  # terminal 4

# Seed admin user if not already done
php artisan db:seed --class=AdminUserSeeder

# Dispatch a test failing job so Failed Jobs has data
php artisan tinker --execute="dispatch(new App\Jobs\HandleRequestTimeout('00000000-0000-0000-0000-000000000000'));"
# (The job will fail because the request doesn't exist — check failed_jobs table)
php artisan tinker --execute="DB::table('failed_jobs')->count();"
```

---

## P3-1: Failed Jobs Resource

> Lists all failed queue jobs. Retry re-queues a job and removes it from the table. Delete discards it permanently. Access via **System → Failed Jobs**.

| #  | Step                                                         | Expected Output                                                                   | Result |
| -- | ------------------------------------------------------------ | --------------------------------------------------------------------------------- | ------ |
| 1  | Navigate to **System → Failed Jobs**                         | Table loads — at least one row from setup                                         |        |
| 2  | Observe columns                                              | ID, UUID (copyable), Queue (badge), Job name (short class name), Exception, Failed At (sorted desc) |        |
| 3  | Click the UUID copy icon on any row                          | UUID copied to clipboard                                                          |        |
| 4  | Apply **Queue** filter                                       | Dropdown populated with distinct queue names; selecting one filters the table     |        |
| 5  | Apply **Date Range** filter (Failed At — from today)         | Only today's failures shown                                                       |        |
| 6  | Click a row to view it                                       | View page opens — full **Exception** trace in monospace; **Payload** rendered as pretty-printed JSON |        |
| 7  | Return to list; click **Retry** on a failed job              | Success notification "Job queued for retry"; row disappears from the table        |        |
| 8  | Confirm the job re-appeared in queue                         | `php artisan tinker --execute="DB::table('jobs')->count();"` — count increased by 1 |        |
| 9  | Dispatch another failing job; click **Delete** on its row    | Row disappears; `DB::table('failed_jobs')->count()` decreased by 1                |        |
| 10 | Dispatch two failing jobs; select both; click **Retry Selected** | Both rows disappear; two new jobs in the queue                                |        |
| 11 | Dispatch two failing jobs; select both; click **Delete Selected** | Both rows disappear from failed_jobs                                          |        |
| 12 | Confirm **New** button does not exist                        | No "New Failed Job" button in the top-right                                       |        |

**Notes / Observations:**

```

```

**Test Result:**

---

## P3-2: Laravel Horizon

> Queue dashboard at `/horizon`. Gate restricts access to admin users only.

```bash
# Ensure Horizon is running (replaces plain queue:work for Horizon monitoring)
php artisan horizon
```

| #  | Step                                                                 | Expected Output                                              | Result |
| -- | -------------------------------------------------------------------- | ------------------------------------------------------------ | ------ |
| 1  | Navigate to `http://localhost:8000/horizon` while **not logged in**  | Redirected to login or 403 Forbidden                         |        |
| 2  | Log in as admin, then navigate to `/horizon`                         | Horizon dashboard loads — job throughput chart visible        |        |
| 3  | Dispatch a slow job; observe Horizon dashboard                       | Job appears in "Recent Jobs" or "Pending" section            |        |
| 4  | Log out; attempt `/horizon` directly                                 | Redirected to login or 403                                   |        |

**Edge case — non-admin tries to access Horizon:**

| #  | Step                                            | Expected Output | Result |
| -- | ----------------------------------------------- | --------------- | ------ |
| E1 | Log in as a rider or driver account, visit `/horizon` | 403 Forbidden                        |        |

**Notes / Observations:**

```

```

**Test Result:**

---

## P3-3: System Health Page

> Shows Reverb WebSocket channel count and application metrics. Polls every 30s. Access via **System → System Health**.

```bash
# Ensure Reverb is running before this test
php artisan reverb:start

# Create live data: put a driver online, open a ride request
php artisan tinker --execute="App\Models\DriverProfile::first()->update(['went_online_at'=>now()]);"
```

| #  | Step                                                             | Expected Output                                                                     | Result |
| -- | ---------------------------------------------------------------- | ----------------------------------------------------------------------------------- | ------ |
| 1  | Navigate to **System → System Health**                           | Page loads without errors; two cards visible: Reverb WebSockets and Application Stats |        |
| 2  | Observe **Reverb WebSockets** card with Reverb running           | Channel count displayed (≥1 if any mobile client is connected); list shows channel names and subscriber counts |        |
| 3  | Observe **Application Stats** card                               | Three stat blocks: Pending Requests (yellow), Active Rides (indigo), Online Drivers (green) — values match DB |        |
| 4  | Confirm via tinker that driver count matches                      | `App\Models\DriverProfile::whereNotNull('went_online_at')->count()` equals the displayed value |        |
| 5  | Wait 30 seconds without interaction                              | Page refreshes automatically; updated counts visible if state changed               |        |
| 6  | **Kill Reverb** (`ctrl+c` in reverb terminal)                    | Reverb WebSockets card shows **"Unavailable — Reverb may be down or unreachable."** — no error or exception shown on page |        |
| 7  | Restart Reverb                                                   | On next 30s poll, Reverb card shows channel data again                              |        |

**Notes / Observations:**

```

```

**Test Result:**

---

## P3-4: Real-time Live Monitor — WebSocket Push

> Live Monitor upgrades from pure polling to WebSocket push. A green/red connection dot indicates Reverb status. Polling falls back to 60s when disconnected.

**Setup:**

```bash
# All four backend processes must be running (including Reverb)
# Have the rider app and driver app ready on separate devices

# Confirm admin.live channel is registered
php artisan tinker --execute="var_dump(app(Illuminate\Broadcasting\BroadcastManager::class)->getChannels());"
```

| #  | Step                                                                           | Expected Output                                                                                                      | Result |
| -- | ------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------- | ------ |
| 1  | Navigate to **Live Monitor**                                                   | Page loads; **green dot** and **"Live"** label visible in top-left of page                                           |        |
| 2  | Observe polling interval labels                                                | All three tables show "(live updates + 60s fallback)" — not the old 10s/15s labels                                  |        |
| 3  | **[Rider app]** Submit a ride request                                          | Active Ride Requests table updates **without a page refresh** — new row appears within ~1s                           |        |
| 4  | Observe in-browser Filament notification                                       | Toast notification appears: **"New Ride Request"** with rider's name                                                 |        |
| 5  | **[Driver app]** Accept the ride                                               | Active Rides table updates in real-time — row moves from Requests to Rides, status changes to "accepted"             |        |
| 6  | **[Rider app]** Cancel the ride request (while still in searching state)       | Row disappears from Active Ride Requests table in real-time                                                          |        |
| 7  | **[Driver app]** Go online                                                     | Online Drivers table updates in real-time — driver row appears                                                       |        |
| 8  | **[Driver app]** Go offline                                                    | Driver row disappears from Online Drivers table in real-time                                                         |        |
| 9  | **Kill Reverb** (`ctrl+c`)                                                     | **Red dot** and **"Reconnecting..."** label appear; tables continue to refresh every 60s via fallback polling         |        |
| 10 | Restart Reverb                                                                 | **Green dot** and **"Live"** return; real-time updates resume                                                        |        |
| 11 | **[Rider app]** Submit a ride request while Reverb was down then restarted     | After reconnect, next new request arrives in real-time again                                                         |        |

**Verify the admin.live auth endpoint is working:**

```bash
# Log in as admin in the browser, then check the auth endpoint resolves correctly
# (GET /admin/broadcasting/auth should not 404 or redirect to /login)
curl -b cookies.txt http://localhost:8000/admin/broadcasting/auth
# Expected: 403 (CSRF missing) not 404 — meaning the route is registered
```

**Notes / Observations:**

```

```

**Test Result:**

---

## P3-5: Driver Queue Visualizer

> FIFO queue snapshot. Shows queued drivers in join order with wait time, decline count, and cooldown status. Dispatched driver row pulses indigo. Access via **Rides → Driver Queue**.

**Setup:**

```bash
# Put 2–3 drivers in the queue by having them go online in the driver app
# OR manually via tinker:
php artisan tinker --execute="
App\Models\DriverProfile::whereIn('user_id', [2, 3, 4])->update([
    'went_online_at' => now(),
    'queue_joined_at' => now()->subMinutes(rand(1, 15)),
]);"

# Put one driver in cooldown
php artisan tinker --execute="App\Models\DriverProfile::first()->update(['queue_cooldown_until' => now()->addMinutes(5)]);"

# Give a driver some declines
php artisan tinker --execute="App\Models\DriverProfile::first()->update(['decline_count' => 3]);"
```

| #  | Step                                                                   | Expected Output                                                                                                       | Result |
| -- | ---------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- | ------ |
| 1  | Navigate to **Rides → Driver Queue**                                   | Page loads; stats bar shows Total, Average Wait, In Cooldown                                                          |        |
| 2  | Observe stats bar values                                               | Total = queued driver count; Average Wait ≈ average of `now - queue_joined_at` in minutes (integer); In Cooldown = 1 |        |
| 3  | Observe queue table rows                                               | Rows in FIFO order (longest-waiting first — Position #1 joined earliest)                                              |        |
| 4  | Observe **Wait Time** column                                           | Shows minutes since `queue_joined_at` for each driver                                                                 |        |
| 5  | Observe **Declines** column on driver with 3 declines                  | Number shows in **yellow** (1–2 = yellow, ≥3 = red)                                                                  |        |
| 6  | Observe **Status** column on driver in cooldown                        | **"Cooldown until HH:MM"** badge (orange); other drivers show **"Waiting"** badge (green)                             |        |
| 7  | Observe connection dot                                                 | **Green dot** and **"Live"** label visible (same as Live Monitor)                                                     |        |
| 8  | **[Rider app]** Submit a ride request                                  | Within ~1s, the first driver's row briefly **pulses indigo** (ring-2 ring-indigo-500 animate-pulse for 8s); pulse fades after 8s |        |
| 9  | **[Driver app]** Accept and complete the ride                          | Driver row disappears then reappears at the **bottom** of the queue (rejoined after ride)                             |        |
| 10 | **[Driver app — new driver]** Go online                                | New driver row appears in the table in real-time                                                                      |        |
| 11 | **[Driver app]** Go offline                                            | Driver row disappears from queue table in real-time                                                                   |        |
| 12 | Wait 60 seconds with Reverb running                                    | Table refreshes via fallback poll — counts remain accurate                                                            |        |
| 13 | **Kill Reverb**; observe                                               | Red dot shown; table continues refreshing every 60s                                                                   |        |

**Notes / Observations:**

```

```

**Test Result:**

---

## Bugs Found During Testing

| #  | Test | Description | Severity | Fixed? |
| -- | ---- | ----------- | -------- | ------ |
|    |      |             |          |        |

---

## Sign-off

- [ ] Failed Jobs table loads with correct columns; retry re-queues and removes row; delete discards; bulk actions work; no Create button (P3-1)
- [ ] Horizon dashboard accessible to admin only; non-admin and unauthenticated users get 403/redirect (P3-2)
- [ ] System Health: Reverb card shows channel count + subscriber list; "Unavailable" shown gracefully when Reverb is down; app stats match DB (P3-3)
- [ ] Live Monitor: green dot on load; new ride request appears in real-time + toast notification; ride accept/cancel updates without refresh; online/offline driver updates without refresh (P3-4)
- [ ] Live Monitor: killing Reverb → red dot + "Reconnecting..."; 60s polling continues; restarting Reverb → green dot returns (P3-4)
- [ ] Driver Queue: FIFO order correct; stats bar values accurate; cooldown badge shows until-time; decline count colour-coded; dispatched driver row pulses indigo for ~8s (P3-5)
- [ ] Driver Queue: driver going online appears in real-time; completing a ride re-inserts driver at bottom; killing Reverb → red dot + 60s fallback (P3-5)

**Tested by:** \_\_\_\_\_\_\_\_\_\_\_\_
**Date:** \_\_\_\_\_\_\_\_\_\_\_\_
