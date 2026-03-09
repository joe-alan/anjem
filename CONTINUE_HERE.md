# Continue Here

## Current State

**Branch:** `feat/mapbox-optimisation`
**Next action:** Open PR against `main` — all 7 phases complete ✅

---

## Mapbox Optimisation Progress

| Phase | Title | Status |
|---|---|---|
| 1 | Eliminate duplicate Directions calls | ✅ Done |
| 2 | Enable Search Box API for production | ✅ Done |
| 3 | Switch to `driving-traffic` profile | ✅ Done |
| 4 | Schedule route cache cleanup | ✅ Done |
| 5 | Adaptive driver location updates | ✅ Done |
| 6 | Security — move hardcoded token | ✅ Done |
| 7 | Update outdated docs | ✅ Done |

Full plan: `mapbox_optimisation.md` · Implementation reference: `docs/optimization/ROUTE_API_CACHING_PLAN.md`

---

## Previous work (admin dashboard — `feat/admin-dashboard-phase1`)

**PR:** https://github.com/joe-alan/anjem/pull/31
**Admin test log:** A-1 through A-15 ✅ Pass; A-16 ⚠️ Partial (OAuth); A-17, A-18 ⬜ Not tested

---

## Known Bugs / Deferred Items

| # | Description | Severity | File |
|---|---|---|---|
| 1 | Pull-to-refresh while backend is down shows Flutter error screen instead of silently hiding the credit chip. | Low | `mobile/lib/driver/screens/driver_home_screen.dart` |
| 2 | After session-restore (app kill mid-ride + reopen), completing the ride leaves the driver stuck on `ActiveRideScreen`. | Medium | `mobile/lib/driver/screens/active_ride_screen.dart` |
| 3 | A-16 cannot be fully tested — test driver/rider accounts use Google OAuth only (no password). Needs a password-based test account. | Low | — |
| 4 | Rider suspend: future redesign should fully sever WS/location connections on suspend (mirrors no-internet behaviour). Deferred. | Low | `mobile/lib/rider/` |

---

## Starting the Dev Server

```bash
# From backend/
php artisan serve          # http://127.0.0.1:8000
php artisan reverb:start   # WebSocket on :8080
php artisan queue:work     # Jobs + FCM  ← MUST be running; check with: ps aux | grep "php artisan"
php artisan schedule:work  # Stale driver kick + cleanup

# Admin panel: http://localhost:8000/admin
# Credentials: see database/seeders/AdminUserSeeder.php

# Mobile (driver flavor):
flutter run --flavor driver -t lib/main_driver.dart

# Mobile (rider flavor):
flutter run --flavor rider -t lib/main_rider.dart
```

> **Note:** `queue:work` can die silently. If rider gets stuck on "Finding Driver" with no driver notification or no-drivers screen, check the queue worker first.
