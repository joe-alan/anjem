# Device Test Log — Admin Dashboard (`feat/admin-dashboard-phase1`)

> **Branch:** `feat/admin-dashboard-phase1`
> **Date:** 2026-03-08
> **Tester:** Jonathan
> **Access:** Browser at `http://localhost:8000/admin`
> **Login:** credentials from `database/seeders/AdminUserSeeder.php`

---

## Status Summary

| Test                                                                                 | Status        |
| ------------------------------------------------------------------------------------ | ------------- |
| [A-1 Admin Login & Panel Access](#a-1-admin-login--panel-access)                     | ✅ Pass       |
| [A-2 Dashboard KPIs & Widgets](#a-2-dashboard-kpis--widgets)                         | ✅ Pass       |
| [A-3 Driver List — Filters & Columns](#a-3-driver-list--filters--columns)            | ✅ Pass       |
| [A-4 KYC Approve](#a-4-kyc-approve)                                                  | ✅ Pass       |
| [A-5 KYC Reject](#a-5-kyc-reject)                                                    | ✅ Pass       |
| [A-6 Grant Credits (Filament Action)](#a-6-grant-credits-filament-action)            | ✅ Pass       |
| [A-7 Deduct Credits (Filament Action)](#a-7-deduct-credits-filament-action)          | ✅ Pass       |
| [A-8 View KTM Document](#a-8-view-ktm-document)                                      | ✅ Pass       |
| [A-9 Suspend / Unsuspend Driver](#a-9-suspend--unsuspend-driver)                     | ✅ Pass       |
| [A-10 Rider List — Suspend / Unsuspend](#a-10-rider-list--suspend--unsuspend)        | ✅ Pass       |
| [A-11 Ride List — Force Complete](#a-11-ride-list--force-complete)                   | ✅ Pass       |
| [A-12 Ride List — Force Cancel](#a-12-ride-list--force-cancel)                       | ✅ Pass       |
| [A-13 Stuck Rides Filter](#a-13-stuck-rides-filter)                                  | ✅ Pass       |
| [A-14 Audit Log — List & Filter](#a-14-audit-log--list--filter)                      | ✅ Pass       |
| [A-15 Live Monitoring Page](#a-15-live-monitoring-page)                              | ✅ Pass       |
| [A-16 Non-Admin Access Blocked](#a-16-non-admin-access-blocked)                      | ⚠️ Partial    |
| [A-17 Phase 1 API — KYC & Credits (direct)](#a-17-phase-1-api--kyc--credits-direct)  | ⬜ Not tested |
| [A-18 KYC Pending Screen — Session Resume](#a-18-kyc-pending-screen--session-resume) | ⬜ Not tested |

> **Status key:** ⬜ Not tested · ✅ Pass · ❌ Fail · ⚠️ Partial

---

## Pre-Test Setup

Run from `backend/` before testing.

```bash
# Ensure backend services are running
php artisan serve          # terminal 1
php artisan reverb:start   # terminal 2
php artisan queue:work     # terminal 3

# Run migrations (includes 2FA columns + audit log nullable)
php artisan migrate

# Seed admin user
php artisan db:seed --class=AdminUserSeeder

# Confirm admin exists
php artisan tinker --execute="echo App\Models\User::where('role','admin')->first()->email;"

# ── KYC flow note ──────────────────────────────────────────────────────────────
# driver_profiles rows are NOT seeded at registration. They are created when a
# driver submits their KYC form in-app (POST /api/v1/driver/kyc/submit).
# After email OTP verification, is_verified remains false — admin approval is now
# a required second step before the driver can go online.
#
# To get a driver into each state for testing:
#
# State A — KYC submitted, email not yet verified:
#   php artisan tinker --execute="App\Models\DriverProfile::where('user_id',{id})->update(['email_verified_at'=>null,'is_verified'=>false]);"
#
# State B — Email verified, awaiting admin approval (normal state after OTP):
#   php artisan tinker --execute="App\Models\DriverProfile::where('user_id',{id})->update(['email_verified_at'=>now(),'is_verified'=>false]);"
#
# State C — Fully approved (admin approved):
#   php artisan tinker --execute="App\Models\DriverProfile::where('user_id',{id})->update(['is_verified'=>true]);"
```

---

## A-1: Admin Login & Panel Access

> Filament panel is at `/admin`. Only users with `role = admin` and `is_active = true` can log in.

| #   | Step                                         | Expected Output                                                                                   | Result |
| --- | -------------------------------------------- | ------------------------------------------------------------------------------------------------- | ------ |
| 1   | Navigate to `http://localhost:8000/admin`    | Redirected to `/admin/login`                                                                      |        |
| 2   | Enter admin credentials from AdminUserSeeder | Login succeeds                                                                                    |        |
| 3   | Observe landing page                         | Dashboard loads — KPI cards and charts visible                                                    |        |
| 4   | Observe navigation sidebar                   | Groups: **Users** (Drivers, Riders), **Rides** (All Rides, Live Monitor), **System** (Audit Logs) |        |

**Edge case — non-admin user:**

| #   | Step                                                | Expected Output                | Result |
| --- | --------------------------------------------------- | ------------------------------ | ------ |
| E1  | Attempt login with a rider/driver account           | Login fails or redirected back | ✅     |
| E2  | Attempt direct URL `/admin/drivers` with no session | Redirected to `/admin/login`   | ✅     |

**Notes / Observations:**

```
All steps and edge cases passed as expected.
```

**Test Result:** ✅ Pass

---

## A-2: Dashboard KPIs & Widgets

> Dashboard shows 3 widgets: StatsOverviewWidget (KPIs), DailyRidesChartWidget (30-day line chart), KycPendingWidget (unverified driver count).

| #   | Step                              | Expected Output                                                                                                              | Result |
| --- | --------------------------------- | ---------------------------------------------------------------------------------------------------------------------------- | ------ |
| 1   | Log in and observe the Dashboard  | Page loads without errors                                                                                                    | ✅     |
| 2   | Observe **StatsOverviewWidget**   | 4 stat cards: Total Users, Online Drivers, Active Rides, Revenue (30d) formatted as Rp                                       | ✅     |
| 3   | Observe **DailyRidesChartWidget** | Line chart rendered — 30-day window; values match completed ride count in DB                                                 | ✅     |
| 4   | Observe **KycPendingWidget**      | Shows count of drivers with `is_verified = false`; badge is **red** if count > 0, **green** if zero                          | ✅     |
| 5   | Click the KycPendingWidget stat   | Navigates to Drivers list filtered to pending KYC                                                                            | ✅     |
| 6   | Confirm Revenue stat via tinker   | `Ride::where('status','completed')->whereDate('dropoff_time','>=',now()->subDays(30))->sum('actual_fare_rp')` matches widget | ✅     |

**Notes / Observations:**

```
All steps passed as expected.
```

**Test Result:** ✅ Pass

---

## A-3: Driver List — Filters & Columns

> DriverResource lists all users with a driver profile. Key columns: name, email, KYC badge, credits balance, rating, online status.

| #   | Step                                           | Expected Output                                                                                     | Result |
| --- | ---------------------------------------------- | --------------------------------------------------------------------------------------------------- | ------ |
| 1   | Navigate to **Drivers** in sidebar             | Table loads — one row per driver with driver profile                                                | ✅     |
| 2   | Observe **KYC** badge column                   | Shows **Approved** (green), **Email Verified** (yellow), or **Pending** (red) per driver            | ✅     |
| 3   | Observe **Credits** and **Rating** columns     | Numbers match DB values in `driver_profiles`                                                        | ✅     |
| 4   | Apply **KYC Status** filter → select "Pending" | Table filters to drivers where `is_verified = false` and no `email_verified_at`                     | ✅     |
| 5   | Apply **Online** filter → select "Yes"         | Table filters to drivers where `went_online_at IS NOT NULL`                                         | ✅     |
| 6   | Search by driver name                          | Results narrow correctly                                                                            | ✅     |
| 7   | Click a driver row                             | Navigates to driver view page — infolist sections visible (Personal, KYC, Vehicle, Credits, Rating) | ✅     |

**Notes / Observations:**

```
All steps passed as expected.
```

**Test Result:** ✅ Pass

---

## A-4: KYC Approve

> Approves a driver's KYC. Sets `is_verified = true`, sends FCM notification, writes audit log.
> Admin approval is now a **required second step** — after email OTP the driver is in "Email Verified"
> state (`email_verified_at` set, `is_verified = false`) and cannot go online until admin approves.

**Setup:**

```bash
# A driver who completed email OTP is already in the correct state (is_verified=false).
# If you need to reset a driver to "Email Verified, awaiting approval":
php artisan tinker --execute="App\Models\DriverProfile::where('user_id',{id})->update(['email_verified_at'=>now(),'is_verified'=>false]);"

# Confirm state
php artisan tinker --execute="App\Models\DriverProfile::where('user_id',{id})->first(['email_verified_at','is_verified']);"
```

| #   | Step                                                                             | Expected Output                                                                                                                                                                                                       | Result |
| --- | -------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------ |
| 1   | Find a driver with KYC badge **Email Verified** (yellow)                         | Row visible in Drivers table — `email_verified_at` set, `is_verified = false`                                                                                                                                         | ✅     |
| 2   | **[Driver app]** Confirm driver is on home screen                                | Screen shows "Unverified" on the status                                                                                                                                                                               | ✅     |
| 3   | **[Driver app]** Confirm driver cannot go online yet                             | Tapping Go Online (if accessible) returns 403 "pending admin approval"                                                                                                                                                | ✅     |
| 4   | Click **Approve KYC** action on that row                                         | Confirmation modal opens — optional reason field shown                                                                                                                                                                | ✅     |
| 5   | Leave reason blank; confirm                                                      | Success notification: "KYC approved"                                                                                                                                                                                  | ✅     |
| 6   | Observe KYC badge on that row                                                    | Badge changes to **Approved** (green)                                                                                                                                                                                 | ✅     |
| 7   | Confirm via tinker: `DriverProfile::where('user_id',{id})->value('is_verified')` | Returns `true`                                                                                                                                                                                                        | ✅     |
| 8   | Confirm audit log written                                                        | `AdminAuditLog::where('action_type','kyc_approve')->where('target_id',{dp_id})->exists()` returns `true`                                                                                                              | ✅     |
| 9   | Verify **Approve KYC** action is now **hidden** on that row                      | Action button disappears (driver is already approved)                                                                                                                                                                 | ✅     |
| 10  | **[Driver app]** Check driver device                                             | System push notification received: "Your KYC has been approved" (or similar from `NotificationService::sendKycApprovedToDriver`)                                                                                      | ✅     |
| 11  | **[Driver app]** Observe KYC status in-app (no restart)                          | `driver.kyc.updated` WebSocket event received; 30s poll or WebSocket triggers `kycStateProvider` refresh; `AuthenticationWrapper` reroutes driver from `KycPendingApprovalScreen` to `DriverHomeScreen` automatically | ✅     |

**Edge case — approve an already-approved driver:**

| #   | Step                                    | Expected Output                                          | Result |
| --- | --------------------------------------- | -------------------------------------------------------- | ------ |
| E1  | Find driver with KYC badge **Approved** | **Approve KYC** action is hidden — cannot double-approve | ✅     |

**Notes / Observations:**

```
All steps and edge cases passed as expected.
```

**Test Result:** ✅ Pass

---

## A-5: KYC Reject

> Rejects KYC. Sets `is_verified = false`, clears `email_verified_at`, sends FCM push, writes audit log. Reason is required (min 10 chars).
> Because `email_verified_at` is cleared, the driver is routed back to the **email verification screen**
> (not just blocked from going online) when they next open the app.

| #   | Step                                                                                                   | Expected Output                                                                                                                                                                                                             | Result |
| --- | ------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------ |
| 1   | Click **Reject KYC** on a driver with badge **Email Verified** or **Approved**                         | Modal opens — **Reason** field required                                                                                                                                                                                     | ✅     |
| 2   | Submit with reason blank                                                                               | Validation error: reason required                                                                                                                                                                                           | ✅     |
| 3   | Submit with reason < 10 chars (e.g., "bad docs")                                                       | Validation error: min 10 chars                                                                                                                                                                                              | ✅     |
| 4   | Submit with valid reason ("Documents are blurry, please resubmit.")                                    | Success notification; KYC badge reverts to **Pending** (red)                                                                                                                                                                | ✅     |
| 5   | Confirm via tinker: `DriverProfile::where('user_id',{id})->first(['is_verified','email_verified_at'])` | `is_verified = false`, `email_verified_at = null`                                                                                                                                                                           | ✅     |
| 6   | Confirm audit log action_type = `kyc_reject` with reason stored                                        |                                                                                                                                                                                                                             | ✅     |
| 7   | Confirm FCM notification sent (check backend queue log or device)                                      | Driver device receives: "Your verification was not approved..."                                                                                                                                                             | ✅     |
| 8   | **[Driver app]** Check driver device                                                                   | System push notification received with rejection reason visible in notification body; **no raw reason in FCM data payload** (security fix applied in `ae69898`)                                                             | ✅     |
| 9   | **[Driver app]** Observe KYC status in-app (no restart)                                                | `driver.kyc.updated` WebSocket event received; `kycStateProvider` refreshes; `AuthenticationWrapper` reroutes driver from `KycPendingApprovalScreen` to `EmailVerificationScreen` (because `email_verified_at` is now null) | ✅     |
| 10  | **[Driver app]** Force-kill and reopen app after rejection                                             | App resumes at `EmailVerificationScreen` (KycStatus.submitted), not home — session resume works correctly                                                                                                                   | ✅     |

**Notes / Observations:**

```
All steps passed as expected. Note: KYC reject previously hit a NOT NULL constraint on vehicle_type —
fixed by removing vehicle_type from the reject clear list (it always defaults to 'motorcycle').
```

**Test Result:** ✅ Pass

---

## A-6: Grant Credits (Filament Action)

> Grants credits to a driver. Calls `CreditService::addCredits()`, writes audit log. Amount: 1–100 integer, reason required.

**Setup:**

```bash
# Note driver's current balance before testing
php artisan tinker --execute="App\Models\DriverProfile::where('user_id',{id})->value('credits_balance');"
```

| #   | Step                                                                         | Expected Output                                                                                          | Result |
| --- | ---------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------- | ------ |
| 1   | Click **Grant Credits** on a driver row                                      | Modal opens — Amount (integer, 1–100) and Reason fields                                                  | ✅     |
| 2   | Submit with amount = 0                                                       | Validation error: min 1                                                                                  | ✅     |
| 3   | Submit with amount = 101                                                     | Validation error: max 100                                                                                | ✅     |
| 4   | Submit amount = 5, reason = "Beta bonus"                                     | Success notification: "Credits granted"                                                                  | ✅     |
| 5   | Confirm Credits column in the driver row                                     | Balance increased by 5                                                                                   | ✅     |
| 6   | Confirm via tinker: `getBalance({id})`                                       | Returns previous balance + 5                                                                             | ✅     |
| 7   | Confirm CreditTransaction created                                            | `CreditTransaction::where('driver_id',{dp_id})->where('type','admin_grant')->latest()->first()` exists   | ✅     |
| 8   | Confirm audit log: `action_type = credit_grant`, `changes.amount = 5`        |                                                                                                          | ✅     |
| 9   | **[Driver app]** Observe credits chip on driver home screen (no interaction) | `driver.credits.updated` WebSocket event received; balance chip updates in real-time without any refresh | ✅     |

**Notes / Observations:**

```
All steps passed as expected.
```

**Test Result:** ✅ Pass

---

## A-7: Deduct Credits (Filament Action)

> Deducts credits from a driver. Guards negative balance (cannot go below 0). Writes audit log.

**Setup:**

```bash
# Grant enough credits to deduct from
php artisan tinker --execute="(new App\Services\CreditService())->addCredits({id}, 10, 'Test setup');"
```

| #   | Step                                                                         | Expected Output                                                                                          | Result |
| --- | ---------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------- | ------ |
| 1   | Click **Deduct Credits** on a driver with balance = 10                       | Modal opens — Amount and Reason fields                                                                   | ✅     |
| 2   | Submit amount = 3, reason = "Penalty for misconduct"                         | Success notification; Credits column shows 7                                                             | ✅     |
| 3   | Confirm via tinker: balance = 7                                              |                                                                                                          | ✅     |
| 4   | Confirm CreditTransaction: `type = admin_deduction`, `amount = -3`           |                                                                                                          | ✅     |
| 5   | Now attempt to deduct 10 (more than remaining balance of 7)                  | Error notification: "Insufficient credits" (cannot go negative)                                          | ✅     |
| 6   | Confirm via tinker: balance still = 7 (no partial deduction)                 |                                                                                                          | ✅     |
| 7   | **[Driver app]** Observe credits chip on driver home screen (no interaction) | `driver.credits.updated` WebSocket event received; balance chip updates in real-time without any refresh | ✅     |

**Notes / Observations:**

```
All steps passed as expected.
```

**Test Result:** ✅ Pass

---

## A-8: View KTM Document

> Opens the driver's `ktm_url` in a modal lightbox. Action only visible when `ktm_url` is set.

**Setup:**

```bash
# Set a test ktm_url on a driver profile (use a real storage path or a placeholder)
php artisan tinker --execute="App\Models\DriverProfile::where('user_id',{id})->update(['ktm_url'=>'documents/sample.jpg']);"
```

| #   | Step                                                  | Expected Output                                       | Result |
| --- | ----------------------------------------------------- | ----------------------------------------------------- | ------ |
| 1   | Find driver with a KTM document (`ktm_url` not null)  | **View Document** action is **visible** on that row   | ✅     |
| 2   | Find driver without a KTM document (`ktm_url = null`) | **View Document** action is **hidden**                | ✅     |
| 3   | Click **View Document**                               | Modal opens — image rendered from `storage/{ktm_url}` | ✅     |
| 4   | Confirm URL in image src is properly escaped (no XSS) | URL is HTML-escaped via `e()` helper                  | ✅     |
| 5   | Click **Close**                                       | Modal closes; driver list is still visible            | ✅     |

**Notes / Observations:**

```
All steps passed as expected.
```

**Test Result:** ✅ Pass

---

## A-9: Suspend / Unsuspend Driver

> Toggles driver's `is_active`. Suspend action visible on active drivers; Unsuspend on suspended ones. Both write audit log.

| #   | Step                                                    | Expected Output                                             | Result |
| --- | ------------------------------------------------------- | ----------------------------------------------------------- | ------ |
| 1   | Find an **active** driver (Status: Active)              | **Suspend** action visible; **Unsuspend** action hidden     | ✅     |
| 2   | Click **Suspend**; confirm without reason               | Success notification; Status badge changes to **Suspended** | ✅     |
| 3   | Confirm via tinker: `User::find({id})->is_active`       | Returns `false`                                             | ✅     |
| 4   | Confirm audit log: `action_type = driver_suspend`       |                                                             | ✅     |
| 5   | Now **Suspend** action is hidden; **Unsuspend** visible |                                                             | ✅     |
| 6   | Click **Unsuspend**; confirm                            | Status badge returns to **Active**                          | ✅     |
| 7   | Confirm audit log: `action_type = driver_unsuspend`     |                                                             | ✅     |
| 8   | Confirm via tinker: `is_active = true`                  |                                                             | ✅     |

**Edge case — suspended driver tries to go online:**

| #   | Step                                                         | Expected Output                                                                                                                                                     | Result |
| --- | ------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------ |
| E1  | Suspend a driver via admin panel                             |                                                                                                                                                                     | ✅     |
| E2  | **[Driver app]** Tap Go Online                               | 403 or error returned — driver cannot go online; app shows an error toast                                                                                           | ✅     |
| E3  | **[Driver app]** If driver was already online when suspended | `account.status.changed` WebSocket event received immediately; driver is signed out in real-time regardless of current state (online, waiting for request, or idle) | ✅     |

**Notes / Observations:**

```
All steps and edge cases passed as expected.
```

**Test Result:** ✅ Pass

---

## A-10: Rider List — Suspend / Unsuspend

> Same suspend pattern as drivers but on the Riders resource. Riders scoped to `role IN ('rider', 'both')`.

| #   | Step                                          | Expected Output                                        | Result |
| --- | --------------------------------------------- | ------------------------------------------------------ | ------ |
| 1   | Navigate to **Riders** in sidebar             | Table shows riders only (no pure drivers)              | ✅     |
| 2   | Click **Suspend** on an active rider          | Status changes to Suspended; audit log `rider_suspend` | ✅     |
| 3   | Confirm via tinker: rider `is_active = false` |                                                        | ✅     |
| 4   | Click **Unsuspend**; confirm                  | Status Active; audit log `rider_unsuspend`             | ✅     |
| 5   | Confirm the rider has a **View** page         | Click row → infolist shows name, email, role, status   | ✅     |

**Edge case — suspended rider tries to book a ride:**

| #   | Step                                                           | Expected Output                                                                                                       | Result |
| --- | -------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- | ------ |
| E1  | Suspend a rider via admin panel                                |                                                                                                                       | ✅     |
| E2  | **[Rider app]** Attempt to request a ride                      | 403 or error returned — rider cannot book; app shows an error                                                         | ✅     |
| E3  | **[Rider app]** If rider was actively searching when suspended | `account.status.changed` WebSocket event received; waiting screen clears and ride request state is reset in real-time | ✅     |

**Notes / Observations:**

```
All steps and edge cases passed as expected.

FUTURE REDESIGN NOTE: When a rider is suspended, the app should fully sever all
real-time connections (WebSocket, location streams, map tile fetches) — not just
block the booking button. The desired behaviour mirrors "no internet connection":
routes, live driver locations, and map previews should all become unavailable.
This ensures a suspended rider cannot passively monitor driver positions or routes.
Deferred to a future frontend redesign pass.
```

**Test Result:** ✅ Pass

---

## A-11: Ride List — Force Complete

> Force-completes an active ride. Sets `status = completed`, `dropoff_time = now()`. Reason required (min 10 chars). Only visible on active statuses.

**Setup:**

```bash
# Find an active ride (matched/accepted/driver_arrived/in_progress)
php artisan tinker --execute="App\Models\Ride::whereIn('status',['matched','accepted','driver_arrived','in_progress'])->first();"
```

| #   | Step                                                    | Expected Output                                                                                                                                    | Result |
| --- | ------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- | ------ |
| 1   | Navigate to **Rides** in sidebar                        | Table loads with all rides                                                                                                                         | ✅     |
| 2   | Find a ride with active status                          | **Force Complete** and **Force Cancel** actions visible                                                                                            | ✅     |
| 3   | Find a ride with `status = completed` or `cancelled`    | Neither action is visible (read-only for terminal states)                                                                                          | ✅     |
| 4   | Click **Force Complete**; submit with reason < 10 chars | Validation error                                                                                                                                   | ✅     |
| 5   | Submit reason "Admin completing stuck ride."            | Success; status badge changes to **Completed** (green)                                                                                             | ✅     |
| 6   | Confirm via tinker: `Ride::find({id})->status`          | Returns `completed`                                                                                                                                | ✅     |
| 7   | Confirm `dropoff_time` is set                           | Not null                                                                                                                                           | ✅     |
| 8   | Confirm audit log: `action_type = ride_force_complete`  |                                                                                                                                                    | ✅     |
| 9   | **[Driver app]** Observe active ride screen             | `ride.status.updated` WebSocket event received; completion flow triggers: credits deducted, "Ride completed! 🎉" snackbar shown, navigates to home | ✅     |
| 10  | **[Rider app]** Observe active ride screen              | `ride.status.updated` WebSocket event received; navigates directly to `CompletedScreen` (no admin-specific dialog for completion)                  | ✅     |

**Notes / Observations:**

```
All steps passed as expected.
```

**Test Result:** ✅ Pass

---

## A-12: Ride List — Force Cancel

> Force-cancels an active ride. Sets `status = cancelled`, `dropoff_time = now()`. Reason required.

| #   | Step                                                | Expected Output                                                                                                                                                    | Result |
| --- | --------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------ |
| 1   | Click **Force Cancel** on an active ride            | Modal opens — reason required (min 10 chars)                                                                                                                       | ✅     |
| 2   | Submit with valid reason                            | Status badge changes to **Cancelled** (red/danger)                                                                                                                 | ✅     |
| 3   | Confirm via tinker: `Ride::find({id})->status`      | Returns `cancelled`                                                                                                                                                | ✅     |
| 4   | Confirm audit log: `action_type = ride_cancel`      |                                                                                                                                                                    | ✅     |
| 5   | Confirm neither action appears on the cancelled row | Force Complete and Force Cancel both hidden                                                                                                                        | ✅     |
| 6   | **[Driver app]** Observe active ride screen         | `ride.status.updated` event received with `admin_override=true`; alert dialog shown: "Ride Cancelled — Admin reason: {reason}"; after OK, driver navigates to home | ✅     |
| 7   | **[Rider app]** Observe active ride screen          | Same `ride.status.updated` event; alert dialog shown: "Ride Cancelled — Admin reason: {reason}"; after OK, rider navigates to `RiderHomeScreen`                    | ✅     |

**Notes / Observations:**

```
All steps passed as expected.
```

**Test Result:** ✅ Pass

---

## A-13: Stuck Rides Filter

> "Stuck Rides (> 2h)" filter shows rides where `updated_at < 2 hours ago` and status is still active.

**Setup:**

```bash
# Simulate a stuck ride by back-dating updated_at
php artisan tinker --execute="App\Models\Ride::whereIn('status',['matched','accepted','in_progress'])->first()->update(['updated_at'=>now()->subHours(3)]);"
```

| #   | Step                                                     | Expected Output                                                     | Result |
| --- | -------------------------------------------------------- | ------------------------------------------------------------------- | ------ |
| 1   | Navigate to **Rides**                                    |                                                                     | ✅     |
| 2   | Apply **Stuck Rides (> 2h)** filter                      | Table shows only rides with `updated_at < 2h ago` and active status | ✅     |
| 3   | The back-dated ride from setup is in the results         | Ride appears in filtered view                                       | ✅     |
| 4   | A fresh active ride (just created) is NOT in the results |                                                                     | ✅     |

**Notes / Observations:**

```
All steps passed as expected.
```

**Test Result:** ✅ Pass

---

## A-14: Audit Log — List & Filter

> AuditLogResource is read-only. Shows all admin actions with action_type, admin name, target, ip, timestamp. Filterable by action_type and date range.

| #   | Step                                                            | Expected Output                                                                    | Result |
| --- | --------------------------------------------------------------- | ---------------------------------------------------------------------------------- | ------ |
| 1   | Navigate to **Audit Logs** in sidebar                           | Table loads — rows from previous tests visible                                     | ✅     |
| 2   | Observe columns                                                 | Admin name, action_type badge (color-coded), target type/id, reason, ip, timestamp | ✅     |
| 3   | Apply **Action Type** filter → select "kyc_approve"             | Only KYC approve entries shown                                                     | ✅     |
| 4   | Apply **Date Range** filter → today only                        | Only today's entries shown                                                         | ✅     |
| 5   | Click a log entry row                                           | View page opens — "Changes" section shows JSON diff in monospace                   | ✅     |
| 6   | Confirm no **Create** or **Edit** buttons exist                 | AuditLogResource is strictly read-only                                             | ✅     |
| 7   | Confirm entries exist for each action taken in A-4 through A-12 | All mutations audited                                                              | ✅     |

**Notes / Observations:**

```
All steps passed as expected.
```

**Test Result:** ✅ Pass

---

## A-15: Live Monitoring Page

> Shows active rides and online drivers with auto-refresh every 10–15 seconds. Access via Rides → Live Monitor in nav.

| #   | Step                                                        | Expected Output                                                                      | Result |
| --- | ----------------------------------------------------------- | ------------------------------------------------------------------------------------ | ------ |
| 1   | Navigate to **Live Monitor** in sidebar                     | Page loads with two tables: Active Rides, Online Drivers                             | ✅     |
| 2   | Observe **Active Rides** table                              | Columns: ID, Rider, Driver, Route (pickup → destination), status badge, elapsed time | ✅     |
| 3   | Observe **Online Drivers** table                            | Columns: Name, Online Since, Minutes Online, Credits Balance, Rating                 | ✅     |
| 4   | Wait 10–15 seconds without any interaction                  | Tables refresh automatically (wire:poll); new data appears if state changed          | ✅     |
| 5   | Accept a ride (via rider app) while monitoring page is open | New active ride appears in Active Rides table on next poll                           | ✅     |
| 6   | Complete/cancel the ride                                    | Ride disappears from Active Rides on next poll                                       | ✅     |
| 7   | Driver goes offline                                         | Driver disappears from Online Drivers on next poll                                   | ✅     |

**Notes / Observations:**

```
All steps passed as expected.
```

**Test Result:** ✅ Pass

---

## A-16: Non-Admin Access Blocked

> Ensures drivers and riders cannot access the Filament panel. Ensures inactive admins are blocked.

| #   | Step                                                     | Expected Output                     | Result |
| --- | -------------------------------------------------------- | ----------------------------------- | ------ |
| 1   | Try logging in as a driver account                       | Filament login rejects access       | ⬜     |
| 2   | Try logging in as a rider account                        | Filament login rejects access       | ⬜     |
| 3   | Suspend the admin account via tinker, then try to log in | Access denied (`is_active = false`) | ⬜     |
| 4   | Restore admin `is_active = true`                         | Login succeeds again                | ⬜     |

```bash
# Suspend admin for test
php artisan tinker --execute="App\Models\User::where('role','admin')->update(['is_active'=>false]);"
# Restore
php artisan tinker --execute="App\Models\User::where('role','admin')->update(['is_active'=>true]);"
```

**Notes / Observations:**

```
Skipped — test accounts (driver and rider) use Google OAuth and do not have a password stored in the database. Cannot test Filament login rejection with OAuth-only accounts. Steps 1–4 deferred until a password-based test account is set up.
```

**Test Result:** ⚠️ Partial

---

## Bugs Found During Testing

| #   | Test | Description | Severity | Fixed? |
| --- | ---- | ----------- | -------- | ------ |
|     |      |             |          |        |

---

## Sign-off

- [ ] Admin login works; non-admin access blocked (A-1, A-16)
- [ ] Dashboard KPIs and chart render correctly (A-2)
- [ ] Driver list filters (KYC status, online) work (A-3)
- [ ] KYC approve sets is_verified; driver on pending screen auto-navigates to home on next poll; audit log written; **FCM push received** (A-4)
- [ ] KYC reject requires reason ≥10 chars, clears email_verified_at; driver on pending screen auto-navigates back to email verification; **FCM push received** (A-5)
- [ ] Grant credits: amount validated, balance updated, CreditTransaction created, **driver app balance updates in real-time** (A-6)
- [ ] Deduct credits: negative balance blocked, **driver app balance updates in real-time** (A-7)
- [ ] KTM document opens in modal, URL is escaped (A-8)
- [ ] Suspend/unsuspend driver with audit log; **suspended driver signed out in real-time via WebSocket** (A-9)
- [ ] Suspend/unsuspend rider with audit log; **suspended rider blocked from booking** (A-10)
- [ ] Force complete ride: reason required, status updated, audit log written; **driver + rider apps show completion** (A-11)
- [ ] Force cancel ride: reason required, status updated, audit log written; **driver + rider apps show admin cancellation dialog with reason** (A-12)
- [ ] Stuck rides filter works (A-13)
- [ ] Audit log: read-only, all mutations logged, JSON diff readable (A-14)
- [ ] Live monitor auto-refreshes without manual interaction (A-15)
- [ ] Phase 1 API: all endpoints return correct status codes (A-17)
- [ ] KYC pending screen: session resume lands at correct screen after force-kill; back button blocked; auto-routes on approval/rejection (A-18)

**Tested by:** \***\*\_\_\*\***
**Date:** \***\*\_\_\*\***
