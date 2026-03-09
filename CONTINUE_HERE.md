# Continue Here

## Current State

**Branch:** `feat/admin-dashboard-phase1`
**Next action:** Continue manual testing of the admin dashboard (`ADMIN_DASHBOARD_TEST_LOG.md`). Resume at **A-8** (View KTM Document). A-1 through A-7 passed.

---

## This Session (2026-03-08) — KYC Resource + Rider Suspend + WS Race Fix

### Commits this session
No commits yet — all changes are unstaged. Commit before starting next session.

### Changed files this session
| File | What changed |
|---|---|
| `backend/app/Filament/Resources/KycResource.php` | **NEW** — dedicated KYC review section in admin panel |
| `backend/app/Filament/Resources/KycResource/Pages/ListKyc.php` | **NEW** — Filament list page for KYC resource |
| `backend/app/Filament/Resources/DriverResource.php` | Removed approve/reject KYC and view_document actions; now only credits + suspend |
| `backend/app/Filament/Widgets/KycPendingWidget.php` | Dashboard KYC badge now links to new KYC section |
| `backend/app/Providers/Filament/AdminPanelProvider.php` | Registered `KycResource` |
| `backend/app/Events/UserAccountStatusChanged.php` | Broadcasts to BOTH `private-driver.{id}` AND `private-user.{id}` (was either/or) |
| `backend/app/Http/Controllers/Api/AdminController.php` | `rating_average ?? 5.0` → `?? 0.0` (3 spots) |
| `backend/app/Http/Resources/UserResource.php` | `rating_average ?? 5.0` → `?? 0.0` |
| `backend/routes/api.php` | Auth throttle: `5,1` → `10,1` |
| `mobile/lib/core/providers/auth_provider.dart` | `_initializeWebSocket()` now runs **before** `isAuthenticated: true` (race fix) |
| `mobile/lib/core/providers/kyc_provider.dart` | Removed `if (state.isLoading) return` guard in `refreshKycStatus()` |
| `mobile/lib/core/providers/ride_request_provider.dart` | Always subscribes to user channel on login (not just when pending); added `onAccountSuspended` callback |
| `mobile/lib/rider/screens/rider_home_screen.dart` | Suspended banner + disabled button; refresh button calls `refreshUser()` |

---

## KYC Section — How It Works Now

**New "KYC" nav item** under Users group (sort: 2), with a red badge showing pending count.

- Shows **all drivers** by default, filtered to "Pending Review" (email verified, not approved)
- Filter options: Pending Review / Approved / Not Ready
- **Review action** opens a `4xl` modal:
  - Left: KTM photo
  - Right: student name, ID, email, vehicle plate, color
  - Toggle buttons: **Approve** (green) / **Reject** (red)
  - Reject shows conditional reason textarea (min 10 chars, required)
- Approve/reject both delete the KTM photo from disk, write `AdminAuditLog`, send FCM, broadcast WebSocket
- Visible for drivers with `email_verified_at` set OR `is_verified = true` (allows rejecting false approvals)

**Drivers section** now only has: Grant Credits, Deduct Credits, Suspend, Unsuspend.

---

## Rider Suspend — How It Works Now

**Backend:** `UserAccountStatusChanged` broadcasts to both `private-driver.{id}` and `private-user.{id}` — fixes the case where a user has both roles or where the channel routing was wrong.

**Mobile:**
- `RideRequestProvider` always subscribes to `private-user.{id}` at login (previously only when a request was pending)
- `onAccountStatusChanged` → clears ride request state + calls `refreshUser()` via `onAccountSuspended` callback
- `refreshUser()` updates `authState.user.isActive` → rider home screen shows red suspended banner, Request Ride button disabled
- Refresh button also calls `refreshUser()` as a fallback

**Race condition fix:** `_initializeWebSocket()` now completes before `isAuthenticated: true` is set. This ensures `_pusher` is non-null when `rideRequestProvider` first subscribes to the user channel.

---

## Admin Test Log Status

| Test | Status |
|---|---|
| A-1 Admin Login & Panel Access | ✅ Pass |
| A-2 Dashboard KPIs & Widgets | ✅ Pass |
| A-3 Driver List — Filters & Columns | ✅ Pass |
| A-4 KYC Approve | ✅ Pass |
| A-5 KYC Reject | ✅ Pass |
| A-6 Grant Credits | ✅ Pass |
| A-7 Deduct Credits | ✅ Pass |
| A-8 View KTM Document | ⬜ Not tested (moved to KYC section) |
| A-9 through A-18 | ⬜ Not tested |

> Note: A-8 "View Document" no longer exists as a standalone action in Drivers. KTM review is now inside the KYC section's Review modal. Update the test log accordingly before testing.

---

## Known Bugs / Deferred Items

| # | Description | Severity | File |
|---|---|---|---|
| 1 | Pull-to-refresh while backend is down shows Flutter error screen instead of silently hiding the credit chip. | Low | `mobile/lib/driver/screens/driver_home_screen.dart` |
| 2 | After session-restore (app kill mid-ride + reopen), completing the ride leaves the driver stuck on `ActiveRideScreen`. | Medium | `mobile/lib/driver/screens/active_ride_screen.dart` |

---

## Starting the Dev Server

```bash
# From backend/
php artisan serve          # http://127.0.0.1:8000
php artisan reverb:start   # WebSocket on :8080
php artisan queue:work     # Jobs + FCM
php artisan schedule:work  # Stale driver kick + cleanup

# Admin panel: http://localhost:8000/admin
# Credentials: see database/seeders/AdminUserSeeder.php

# Mobile (driver flavor):
flutter run --flavor driver -t lib/main_driver.dart

# Mobile (rider flavor):
flutter run --flavor rider -t lib/main_rider.dart
```

---

## Uncommitted Changes (pre-existing, not our work)

- `CREDIT_SYSTEM_DEVICE_TEST_LOG.md` — deleted (unstaged)
- `docs/DEVICE_TEST_LOG.md` — deleted (unstaged)
- `backend/app/Exceptions/Handler.php` — redirects unauthenticated to Filament login (unstaged, pre-existing)
