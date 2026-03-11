# Continue Here

## Current State

**Branch:** `feat/mapbox-optimisation`
**Next action:** Run physical device tests using `MAPBOX_OPTIMISATION_TEST_LOG.md`, then open PR → `dev`

---

## Mapbox Optimisation — All 7 Phases Complete ✅

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

## This Session (2026-03-09) — Mapbox Optimisation

### Commits this session

| Commit | Description |
|---|---|
| `38350ad` | feat(mapbox): persist route geometry on ride_requests, serve to mobile (Phase 1) |
| `7735bb4` | fix(mapbox): add type guards in _parseRouteGeometry; fix plan doc type |
| `d3bd092` | feat(mapbox): enable Search Box API with session token + campus bbox (Phase 2) |
| `f7f04a1` | docs: fix markdown table spacing in CONTINUE_HERE (CodeRabbit MD058) |
| `036a5dd` | feat(mapbox): switch to driving-traffic profile for better ETAs (Phase 3) |
| `b85062f` | feat(mapbox): route cache cleanup, adaptive location updates, secure token (Phases 4-6) |
| `13ed395` | docs(mapbox): update ROUTE_API_CACHING_PLAN to reflect actual implementation (Phase 7) |
| `cefdfd8` | docs(mapbox): fix CodeRabbit findings in ROUTE_API_CACHING_PLAN |
| `6cf9457` | test(mapbox): add unit tests for Phases 1–3 + fix estimated route caching bug |
| `fcf1a8b` | docs(test-log): add physical device test log for mapbox optimisation (Phases 1–6) |

All on `feat/mapbox-optimisation`. Not yet pushed or PR'd.

---

## Changed Files This Session

| File | Change |
|---|---|
| `backend/database/migrations/2026_03_09_084105_add_route_geometry_to_ride_requests_table.php` | New migration: `route_geometry JSON NULL` on `ride_requests` |
| `backend/app/Models/RideRequest.php` | `route_geometry` added to `$fillable` + `$casts` |
| `backend/app/Services/RideService.php` | `createRideRequest()` passes location IDs (enables cache); stores `route_geometry` |
| `backend/app/Services/RouteCacheService.php` | Default profile `driving-traffic`; skip caching estimated (null geometry) results |
| `backend/app/Services/MapboxService.php` | URL `mapbox/driving/` → `mapbox/driving-traffic/` |
| `backend/app/Services/PlaceSearchService.php` | `MIN_RESULTS_THRESHOLD` 0→3; session token; campus bbox |
| `backend/app/Console/Kernel.php` | Daily 03:00 cleanup job: `cleanupStaleRoutes(30)` |
| `backend/app/Http/Resources/RideRequestResource.php` | Expose `route_geometry` |
| `backend/app/Http/Resources/RideResource.php` | Expose `route_geometry` via `rideRequest` relation |
| `backend/app/Http/Controllers/Api/RideController.php` | Add `rideRequest` to eager-load |
| `backend/app/Http/Controllers/Api/AdminController.php` | Add `rideRequest` to eager-load |
| `backend/config/services.php` | Add `search_bbox` to mapbox config |
| `backend/tests/Unit/Services/MapboxServiceTest.php` | New: 5 tests for driving-traffic profile |
| `backend/tests/Unit/Services/PlaceSearchServiceTest.php` | New: 8 tests for Search Box (threshold, bbox, session token, caching) |
| `backend/tests/Unit/Services/RouteCacheDrivingTrafficTest.php` | New: 5 tests for driving-traffic default |
| `backend/tests/Unit/Services/RideRequestRouteGeometryTest.php` | New: 5 tests for route_geometry storage |
| `backend/tests/Unit/Services/RouteCacheServiceTest.php` | Updated: `'driving'` → `'driving-traffic'` in all cache fixtures |
| `mobile/lib/core/models/ride.dart` | `routeCoordinates: List<LatLng>?`; `_parseRouteGeometry()` with type guards |
| `mobile/lib/core/config/mapbox_config.dart` | `defaultDirectionsProfile` `driving` → `driving-traffic`; token → `String.fromEnvironment` |
| `mobile/lib/rider/screens/rider_active_ride_screen.dart` | Use backend geometry first; fallback to Mapbox only if null |
| `mobile/lib/driver/screens/active_ride_screen.dart` | Same for `inProgress`; adaptive location timer (5/10/30s by speed) |
| `mobile/lib/rider/screens/location_selection_screen.dart` | Fixed misleading null-ID error message |
| `mobile/test/core/models/ride_test.dart` | New: 13 Flutter tests for `_parseRouteGeometry` + `copyWith` + equality |
| `docs/optimization/ROUTE_API_CACHING_PLAN.md` | Rewritten: Planned → Implemented; actual architecture documented |
| `MAPBOX_OPTIMISATION_TEST_LOG.md` | New: physical device test log (MB-1 through MB-E2E) |

---

## Bug Found & Fixed (by unit tests)

| Bug | Fix |
|---|---|
| `RouteCacheService::getOrFetchRoute` tried to INSERT straight-line fallback estimates (null geometry) into `route_caches.route_geometry NOT NULL` → exception propagated → `createRideRequest` returned null whenever Mapbox was unavailable | Added `if (! $routeData['estimated'])` guard — estimated routes not cached |

---

## Test Results

| Suite | Result |
|---|---|
| `php artisan test` (full backend) | 317 passed, 73 pre-existing failures (unchanged) |
| New unit tests (31 tests, 97 assertions) | ✅ All pass |
| `flutter test test/core/models/ride_test.dart` (13 tests) | ✅ All pass |

---

## Known Bugs / Deferred Items

| # | Description | Severity | File |
|---|---|---|---|
| 1 | Pull-to-refresh while backend is down shows Flutter error screen instead of silently hiding the credit chip. | Low | `mobile/lib/driver/screens/driver_home_screen.dart` |
| 2 | After session-restore (app kill mid-ride + reopen), completing the ride leaves the driver stuck on `ActiveRideScreen`. | Medium | `mobile/lib/driver/screens/active_ride_screen.dart` |
| 3 | A-16 (admin test) cannot be fully tested — test driver/rider accounts use Google OAuth only. Needs a password-based test account. | Low | — |
| 4 | Rider suspend: future redesign should fully sever WS/location connections on suspend. Deferred. | Low | `mobile/lib/rider/` |
| 5 | Admin test log A-17 / A-18 not yet tested. | Low | `docs/test-logs/ADMIN_DASHBOARD_TEST_LOG.md` |

---

## Starting the Dev Server

```bash
# From backend/
php artisan serve          # http://127.0.0.1:8000
php artisan reverb:start   # WebSocket on :8080
php artisan queue:work     # Jobs + FCM  ← MUST be running
php artisan schedule:work  # Stale driver kick + cleanup + route cache GC

# Admin panel: http://localhost:8000/admin
# Credentials: see database/seeders/AdminUserSeeder.php

# Mobile — always pass token:
flutter run --flavor rider -t lib/main_rider.dart \
  --dart-define=MAPBOX_ACCESS_TOKEN=pk.eyJ1...

flutter run --flavor driver -t lib/main_driver.dart \
  --dart-define=MAPBOX_ACCESS_TOKEN=pk.eyJ1...
```

> **Note:** `queue:work` can die silently. If riders get stuck on "Finding Driver", check the queue worker first.
> **Note (Phase 6):** App will not render maps without `--dart-define=MAPBOX_ACCESS_TOKEN=...`.
