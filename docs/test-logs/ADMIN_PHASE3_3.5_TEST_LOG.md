# Device Test Log — Admin Panel Phase 3 & 3.5 (`feat/admin-phase3`)

> **Branch:** `feat/admin-phase3`
> **Date:** ___________
> **Tester:** ___________
> **Access:** Browser at `http://localhost:8000/admin`
> **Login:** credentials from `database/seeders/AdminUserSeeder.php`

---

## Status Summary

### Phase 3 — Operational Tooling

| Test                                                                                         | Status |
| -------------------------------------------------------------------------------------------- | ------ |
| [P3-1 Failed Jobs Resource](#p3-1-failed-jobs-resource)                                      | ⬜     |
| [P3-2 Laravel Horizon](#p3-2-laravel-horizon)                                                | ⬜     |
| [P3-3 System Health Page](#p3-3-system-health-page)                                          | ⬜     |
| [P3-4 Real-time Live Monitor — WebSocket Push](#p3-4-real-time-live-monitor--websocket-push)  | ⬜     |
| [P3-5 Driver Queue Visualizer](#p3-5-driver-queue-visualizer)                                | ⬜     |

### Phase 3.5 — Soft-Launch Readiness

| Test                                                                                                   | Status |
| ------------------------------------------------------------------------------------------------------ | ------ |
| [P35-1 KYC Archival — Approve Preserves Documents](#p35-1-kyc-archival--approve-preserves-documents)   | ⬜     |
| [P35-2 KYC Archival — Reject Preserves Documents](#p35-2-kyc-archival--reject-preserves-documents)     | ⬜     |
| [P35-3 Dashboard Operational KPIs](#p35-3-dashboard-operational-kpis)                                  | ⬜     |
| [P35-4 CSV Export — Rides](#p35-4-csv-export--rides)                                                   | ⬜     |
| [P35-5 CSV Export — Drivers](#p35-5-csv-export--drivers)                                               | ⬜     |
| [P35-6 CSV Export — Audit Logs](#p35-6-csv-export--audit-logs)                                         | ⬜     |

> **Status key:** ⬜ Not tested · ✅ Pass · ❌ Fail · ⚠️ Partial

---

## Pre-Test Setup

```bash
# Backend (4 terminals)
php artisan serve          # terminal 1
php artisan reverb:start   # terminal 2
php artisan queue:work     # terminal 3
php artisan schedule:work  # terminal 4

# Seed admin + migrate
php artisan migrate
php artisan db:seed --class=AdminUserSeeder

# Create a failing job for P3-1
php artisan tinker --execute="dispatch(new App\Jobs\HandleRequestTimeout('00000000-0000-0000-0000-000000000000'));"

# Ensure at least one driver has full KYC data (for P35-1/P35-2)
php artisan tinker --execute="
\$dp = App\Models\DriverProfile::first();
echo 'student_email: '.\$dp->student_email.PHP_EOL;
echo 'ktm_url: '.\$dp->ktm_url.PHP_EOL;
echo 'is_verified: '.\$dp->is_verified.PHP_EOL;
"

# Put drivers in queue for P3-5
php artisan tinker --execute="
App\Models\DriverProfile::take(3)->get()->each(fn(\$dp) => \$dp->update([
    'went_online_at' => now(),
    'queue_joined_at' => now()->subMinutes(rand(1,15)),
]));
"
```

---

## Phase 3 Tests

### P3-1: Failed Jobs Resource

> **System → Failed Jobs.** Lists failed queue jobs with retry and delete actions.

| #  | Step                                             | Expected                                                                             | Result |
| -- | ------------------------------------------------ | ------------------------------------------------------------------------------------ | ------ |
| 1  | Navigate to **System → Failed Jobs**             | Table loads with at least one row from setup                                         |        |
| 2  | Observe columns                                  | ID, UUID (copyable), Queue (badge), Job name, Exception, Failed At (desc)           |        |
| 3  | Click a row to view it                           | Full exception trace (monospace) + Payload as pretty JSON                            |        |
| 4  | Click **Retry** on a failed job                  | Row disappears; `DB::table('jobs')->count()` increases                               |        |
| 5  | Click **Delete** on a failed job                 | Row disappears; `DB::table('failed_jobs')->count()` decreases                        |        |
| 6  | Select 2 jobs → **Retry Selected**               | Both rows removed from table; two new entries in jobs table                           |        |
| 7  | Select 2 jobs → **Delete Selected**              | Both rows gone from failed_jobs                                                      |        |
| 8  | Confirm no **New** button exists                 | Resource is read-only — no create button                                             |        |

**Notes / Observations:**

```

```

**Test Result:**

---

### P3-2: Laravel Horizon

> Queue dashboard at `/horizon`. Gate restricts to admin users only.

```bash
# Use Horizon instead of plain queue:work for this test
php artisan horizon
```

| #  | Step                                                     | Expected                                       | Result |
| -- | -------------------------------------------------------- | ---------------------------------------------- | ------ |
| 1  | Visit `/horizon` while **not logged in**                 | 403 or redirect to login                       |        |
| 2  | Log in as admin → visit `/horizon`                       | Horizon dashboard loads with job throughput     |        |
| 3  | Log in as rider/driver → visit `/horizon`                | 403 Forbidden                                  |        |
| 4  | Log out → visit `/horizon`                               | 403 or redirect                                |        |

**Test Result:**

---

### P3-3: System Health Page

> **System → System Health.** Reverb channel status + application metrics. Polls every 30s.

| #  | Step                                          | Expected                                                                 | Result |
| -- | --------------------------------------------- | ------------------------------------------------------------------------ | ------ |
| 1  | Navigate to **System → System Health**        | Two cards: Reverb WebSockets, Application Stats                          |        |
| 2  | Observe Reverb card (Reverb running)          | Channel count ≥1 if mobile clients connected; channel names listed       |        |
| 3  | Observe Application Stats card                | Pending Requests, Active Rides, Online Drivers — match DB values         |        |
| 4  | Wait 30s                                      | Auto-refresh; counts update if state changed                             |        |
| 5  | Kill Reverb (`ctrl+c`)                        | Card shows **"Unavailable"** gracefully — no exception on page           |        |
| 6  | Restart Reverb                                | Next poll shows channel data again                                       |        |

**Test Result:**

---

### P3-4: Real-time Live Monitor — WebSocket Push

> Upgraded from polling to WebSocket push via `admin.live` channel. Green/red dot indicates connection status.

| #  | Step                                                         | Expected                                                                              | Result |
| -- | ------------------------------------------------------------ | ------------------------------------------------------------------------------------- | ------ |
| 1  | Navigate to **Live Monitor**                                 | **Green dot** + **"Live"** label visible                                              |        |
| 2  | Observe polling labels                                       | "(live updates + 60s fallback)" — not old 10s/15s                                    |        |
| 3  | **[Rider app]** Submit a ride request                        | New row appears in Requests table within ~1s; toast: **"New Ride Request"**           |        |
| 4  | **[Driver app]** Accept the ride                             | Row moves from Requests to Rides, status → "accepted" — no page refresh              |        |
| 5  | **[Driver app]** Go online / offline                         | Driver appears/disappears in Online Drivers table in real-time                        |        |
| 6  | Kill Reverb                                                  | **Red dot** + **"Reconnecting..."**; 60s fallback polling continues                  |        |
| 7  | Restart Reverb                                               | Green dot returns; real-time updates resume                                           |        |

**Test Result:**

---

### P3-5: Driver Queue Visualizer

> **Rides → Driver Queue.** FIFO queue snapshot with real-time updates.

**Setup:**

```bash
# Put one driver in cooldown + give declines
php artisan tinker --execute="App\Models\DriverProfile::first()->update(['queue_cooldown_until' => now()->addMinutes(5), 'decline_count' => 3]);"
```

| #  | Step                                                 | Expected                                                                      | Result |
| -- | ---------------------------------------------------- | ----------------------------------------------------------------------------- | ------ |
| 1  | Navigate to **Rides → Driver Queue**                 | Stats bar: Total, Average Wait, In Cooldown; table rows in FIFO order         |        |
| 2  | Observe Declines column (driver with 3)              | Number in **red** (≥3 = red, 1–2 = yellow)                                   |        |
| 3  | Observe Status column on cooldown driver             | **"Cooldown until HH:MM"** (orange); others show **"Waiting"** (green)       |        |
| 4  | **[Rider app]** Submit a ride request                | First driver row **pulses indigo** for ~8s then fades                         |        |
| 5  | **[Driver app]** Complete a ride                     | Driver row disappears then reappears at **bottom** of queue                   |        |
| 6  | **[Driver app]** Go offline                          | Driver row disappears in real-time                                            |        |

**Test Result:**

---

## Phase 3.5 Tests

### P35-1: KYC Archival — Approve Preserves Documents

> After approval, all KYC fields (student_email, student_id, student_name, vehicle_plate, vehicle_color, ktm_url) must remain intact. Previously these were deleted.

**Setup:**

```bash
# Reset a driver to pending-review state with all KYC fields populated
php artisan tinker --execute="
\$dp = App\Models\DriverProfile::first();
\$dp->update([
    'is_verified' => false,
    'email_verified_at' => now(),
    'student_email' => 'test@student.ac.id',
    'student_id' => 'STU-12345',
    'student_name' => 'Test Student',
    'vehicle_plate' => 'B1234XY',
    'vehicle_color' => 'Merah',
    'ktm_url' => '/storage/driver-documents/ktm_test.jpg',
]);
echo 'Ready — driver_profile id: '.\$dp->id;
"
```

| #  | Step                                                          | Expected                                                            | Result |
| -- | ------------------------------------------------------------- | ------------------------------------------------------------------- | ------ |
| 1  | Navigate to **KYC** → find the driver (badge: Pending Review) | Driver row visible with KTM photo and all fields in review modal    |        |
| 2  | Click **Review** → select **Approve** → submit                | Success notification: "KYC approved"                                |        |
| 3  | Verify fields via tinker                                      | `student_email`, `student_id`, `student_name`, `vehicle_plate`, `vehicle_color`, `ktm_url` — all **non-null** |        |
| 4  | Verify KTM file still on disk                                 | `php artisan tinker --execute="echo Storage::disk('public')->exists('driver-documents/ktm_test.jpg');"` → true |        |
| 5  | Click **Review** on the same driver (now approved)             | Modal still shows all KYC data — photo visible, fields populated    |        |

```bash
# Verify step 3
php artisan tinker --execute="
\$dp = App\Models\DriverProfile::first();
echo 'is_verified: '.(\$dp->is_verified ? 'true' : 'false').PHP_EOL;
echo 'student_email: '.\$dp->student_email.PHP_EOL;
echo 'ktm_url: '.\$dp->ktm_url.PHP_EOL;
"
# All fields should be non-null
```

**Test Result:**

---

### P35-2: KYC Archival — Reject Preserves Documents

> After rejection, only `is_verified` and `email_verified_at` change. All other fields remain for audit/re-review.

**Setup:**

```bash
# Set driver to approved state with all KYC fields
php artisan tinker --execute="
\$dp = App\Models\DriverProfile::first();
\$dp->update([
    'is_verified' => true,
    'email_verified_at' => now(),
    'student_email' => 'test@student.ac.id',
    'student_id' => 'STU-12345',
    'student_name' => 'Test Student',
    'vehicle_plate' => 'B1234XY',
    'vehicle_color' => 'Merah',
    'ktm_url' => '/storage/driver-documents/ktm_test.jpg',
]);
"
```

| #  | Step                                                           | Expected                                                               | Result |
| -- | -------------------------------------------------------------- | ---------------------------------------------------------------------- | ------ |
| 1  | Navigate to **KYC** → find the approved driver                 | Badge shows **Approved** (green)                                       |        |
| 2  | Click **Review** → select **Reject** → enter reason → submit   | Success notification: "KYC rejected"                                   |        |
| 3  | Verify only `is_verified` and `email_verified_at` changed       | `is_verified = false`, `email_verified_at = null`                      |        |
| 4  | Verify all other fields preserved                               | `student_email`, `student_id`, `student_name`, `vehicle_plate`, `vehicle_color`, `ktm_url` — all **non-null** |        |
| 5  | Verify KTM file still on disk                                   | File exists at original path                                           |        |

```bash
# Verify steps 3-4
php artisan tinker --execute="
\$dp = App\Models\DriverProfile::first();
echo 'is_verified: '.(\$dp->is_verified ? 'true' : 'false').PHP_EOL;
echo 'email_verified_at: '.(\$dp->email_verified_at ?? 'NULL').PHP_EOL;
echo 'student_email: '.(\$dp->student_email ?? 'NULL').PHP_EOL;
echo 'ktm_url: '.(\$dp->ktm_url ?? 'NULL').PHP_EOL;
"
# is_verified=false, email_verified_at=NULL, rest non-null
```

**Test Result:**

---

### P35-3: Dashboard Operational KPIs

> 5 new stats added to StatsOverviewWidget: Acceptance Rate, Cancellation Rate, Avg Wait Time, Completed Rides, Avg Fare — all scoped to last 7 days.

**Setup:**

```bash
# Ensure varied ride data exists. If using fresh DB:
php artisan tinker --execute="
echo 'Completed (7d): '.App\Models\Ride::where('status','completed')->where('dropoff_time','>=',now()->subDays(7))->count().PHP_EOL;
echo 'Cancelled (7d): '.App\Models\Ride::where('status','cancelled')->where('created_at','>=',now()->subDays(7))->count().PHP_EOL;
echo 'Total (7d): '.App\Models\Ride::where('created_at','>=',now()->subDays(7))->count().PHP_EOL;
"
```

| #  | Step                                             | Expected                                                                        | Result |
| -- | ------------------------------------------------ | ------------------------------------------------------------------------------- | ------ |
| 1  | Navigate to **Dashboard**                        | 9 stat cards visible (4 original + 5 new)                                       |        |
| 2  | Observe **Acceptance Rate (7d)**                 | Percentage; green badge                                                         |        |
| 3  | Observe **Cancellation Rate (7d)**               | Percentage; **red** if >20%, green otherwise                                    |        |
| 4  | Observe **Avg Wait Time (7d)**                   | Minutes; **red** if >10min, **yellow** if >5min, green otherwise                |        |
| 5  | Observe **Completed Rides (7d)**                 | Integer count; green                                                            |        |
| 6  | Observe **Avg Fare (7d)**                        | Rp formatted; gray                                                              |        |
| 7  | Cross-check acceptance rate with DB              | `(accepted+arrived+in_progress+completed) / total * 100` matches widget         |        |
| 8  | Cross-check completed count with DB              | `Ride::where('status','completed')->where('dropoff_time','>=',now()->subDays(7))->count()` matches |        |
| 9  | With 0 rides in last 7 days, all KPIs show 0    | No division-by-zero errors; all display "0" or "0.0%" or "Rp 0"                |        |

**Test Result:**

---

### P35-4: CSV Export — Rides

> "Export CSV" header action on the Rides list page. Respects active filters.

| #  | Step                                                | Expected                                                                      | Result |
| -- | --------------------------------------------------- | ----------------------------------------------------------------------------- | ------ |
| 1  | Navigate to **Rides**                               | **Export CSV** button visible in page header                                  |        |
| 2  | Click **Export CSV** (no filters)                    | CSV downloads; filename: `Ride_YYYY-MM-DD_HHmmss.csv`                        |        |
| 3  | Open CSV — check header row                          | Columns: Ride ID, Rider Name, Driver Name, Pickup, Destination, Status, Fare (Rp), Duration (min), Created At |        |
| 4  | Verify data rows match table                         | Row count matches total ride count; values are correct                        |        |
| 5  | Apply **Status** filter → Completed only             | Click **Export CSV** again — downloaded CSV contains only completed rides     |        |
| 6  | Open CSV in Excel/Google Sheets                      | Indonesian characters (diacritics, location names) render correctly (UTF-8 BOM) |        |

**Test Result:**

---

### P35-5: CSV Export — Drivers

| #  | Step                                                | Expected                                                              | Result |
| -- | --------------------------------------------------- | --------------------------------------------------------------------- | ------ |
| 1  | Navigate to **Drivers**                             | **Export CSV** button visible in page header                          |        |
| 2  | Click **Export CSV**                                 | CSV downloads with columns: Name, Email, Phone, KYC Status, Credits Balance, Rating, Online Status, Joined Date |        |
| 3  | Apply **KYC Status** filter → Approved               | Export contains only approved drivers                                 |        |

**Test Result:**

---

### P35-6: CSV Export — Audit Logs

| #  | Step                                                | Expected                                                              | Result |
| -- | --------------------------------------------------- | --------------------------------------------------------------------- | ------ |
| 1  | Navigate to **Audit Logs**                          | **Export CSV** button visible in page header                          |        |
| 2  | Click **Export CSV**                                 | CSV downloads with columns: Admin, Action, Target Type, Target ID, Reason, IP, Date |        |
| 3  | Apply **Action Type** filter → kyc_approve           | Export contains only KYC approve entries                              |        |
| 4  | Apply **Date Range** filter → last 7 days            | Export scoped to date range                                           |        |

**Test Result:**

---

## Bugs Found During Testing

| #  | Test   | Description | Severity | Fixed? |
| -- | ------ | ----------- | -------- | ------ |
|    |        |             |          |        |

---

## Sign-off

### Phase 3

- [ ] Failed Jobs: table, view, retry, delete, bulk actions all work; no create button (P3-1)
- [ ] Horizon: admin-only gate works; non-admin and unauthenticated users blocked (P3-2)
- [ ] System Health: Reverb card shows channels or "Unavailable" gracefully; app stats match DB (P3-3)
- [ ] Live Monitor: green dot on load; ride/driver events arrive in real-time; red dot + 60s fallback when Reverb dies (P3-4)
- [ ] Driver Queue: FIFO order correct; cooldown/decline badges render; dispatched driver pulses indigo; real-time updates work (P3-5)

### Phase 3.5

- [ ] KYC Approve no longer deletes document files or nullifies KYC fields (P35-1)
- [ ] KYC Reject only clears `is_verified` + `email_verified_at`; all other fields and files preserved (P35-2)
- [ ] Dashboard shows 5 operational KPIs with correct values and color thresholds; zero-data handled gracefully (P35-3)
- [ ] CSV Export on Rides: correct columns, respects filters, UTF-8/Excel compatible (P35-4)
- [ ] CSV Export on Drivers: correct columns, respects filters (P35-5)
- [ ] CSV Export on Audit Logs: correct columns, respects filters (P35-6)

**Tested by:** ___________
**Date:** ___________
