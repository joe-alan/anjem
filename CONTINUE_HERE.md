# Continue Here

## Current State

**Branch:** `feat/mapbox-optimisation`
**Next action:** Phase 2 — Enable Search Box API — backend done, run `php artisan test` then CodeRabbit review

---

## Mapbox Optimisation Progress

| Phase | Title | Status |
|---|---|---|
| 1 | Eliminate duplicate Directions calls | ✅ Done — committed `38350ad`, `7735bb4` |
| 2 | Enable Search Box API for production | 🔄 In progress |
| 3 | Switch to `driving-traffic` profile | ⬜ Pending |
| 4 | Schedule route cache cleanup | ⬜ Pending |
| 5 | Adaptive driver location updates | ⬜ Pending |
| 6 | Security — move hardcoded token | ⬜ Pending |
| 7 | Update outdated docs | ⬜ Pending |

Full plan: `mapbox_optimisation.md`

---

## Phase 2 Changes (this session)

### What changed

| File | Change |
|---|---|
| `backend/app/Services/PlaceSearchService.php` | `MIN_RESULTS_THRESHOLD` 0→3; session token; campus bbox |
| `backend/config/services.php` | Added `search_bbox` to mapbox config |
| `mobile/lib/rider/screens/location_selection_screen.dart` | Fixed misleading error message for null-ID locations |

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
