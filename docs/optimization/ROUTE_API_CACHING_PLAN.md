# Mapbox Optimisation — Implementation Reference

**Status**: ✅ Implemented (`feat/mapbox-optimisation`)
**Implemented**: 2026-03-09
**Original plan**: `mapbox_optimisation.md` (repo root)

---

## What Was Implemented

All 6 phases landed in branch `feat/mapbox-optimisation`. Summary below.

---

### Phase 1 — Eliminate Duplicate Directions Calls

**Problem:** Both rider and driver screens called Mapbox Directions independently per ride. `createRideRequest()` also bypassed `RouteCacheService` by not passing location IDs.

**Solution:** Persist route geometry on `ride_requests` at creation time; serve it through existing API responses.

**Changes:**
- Migration: `route_geometry JSON NULLABLE` column added to `ride_requests`
- `RideService::createRideRequest()` now passes `$pickupLocation->id` / `$destinationLocation->id` to `calculateRideEstimates()` — enables `RouteCacheService` hit on creation
- `route_geometry` stored in `RideRequest::create()`
- Exposed in `RideRequestResource` and `RideResource` (via eager-loaded `rideRequest` relation)
- Mobile `Ride` model: `routeCoordinates: List<LatLng>?` field parsed from GeoJSON `route_geometry`
- Rider screen: uses `ride.routeCoordinates` first, falls back to Mapbox only if null
- Driver screen: same for `inProgress` status; `accepted` status keeps live Mapbox call (driver→pickup is dynamic)

**Note:** Route geometry is stored on `ride_requests`, not served via a dedicated `/rides/{id}/route` endpoint (original plan). This is simpler and avoids an extra round-trip.

---

### Phase 2 — Enable Search Box API

**Problem:** `MIN_RESULTS_THRESHOLD = 0` disabled the Mapbox Search Box fallback entirely. No session token meant each suggest+retrieve pair billed separately. No bbox meant results could come from anywhere.

**Changes:**
- `PlaceSearchService`: `MIN_RESULTS_THRESHOLD` `0 → 3`
- UUID session token generated per search, passed to both Suggest and Retrieve calls (1 billable unit per session)
- `bbox` parameter added to Suggest requests, scoped to UI campus: `106.80,-6.39,106.86,-6.33`
- `MAPBOX_SEARCH_BBOX` added to `config/services.php` (env-configurable)
- `LocationSelectionScreen`: error message updated — cached Mapbox results always have DB IDs after `cacheAPIResult()`

---

### Phase 3 — Switch to `driving-traffic` Profile

**Problem:** `MapboxService` and `RouteCacheService` used the `driving` profile, ignoring live traffic.

**Changes:**
- `MapboxService`: URL `mapbox/driving/` → `mapbox/driving-traffic/`
- `RouteCacheService`: default profile `'driving' → 'driving-traffic'` in `getOrFetchRoute()` and `refreshRoute()`
- `MapboxConfig` (mobile): `defaultDirectionsProfile` `'driving' → 'driving-traffic'`

**Cache note:** Profile is part of the DB cache key — old `driving` entries are naturally bypassed (treated as misses). They expire after the 7-day TTL or can be cleared with `RouteCache::truncate()` post-deploy.

**TTL note:** Actual cache TTL is **7 days** (set in `RouteCacheService::DEFAULT_TTL_DAYS`), not 2 hours as originally proposed.

---

### Phase 4 — Schedule Route Cache Cleanup

**Problem:** `route_caches` table would grow unboundedly with no cleanup job.

**Changes:**
- `Kernel.php`: daily 03:00 job calls `RouteCacheService::cleanupStaleRoutes(30)` — deletes entries older than 30 days

---

### Phase 5 — Adaptive Driver Location Updates

**Problem:** Fixed 10-second `Timer.periodic` wasted battery and backend load when driver was stationary.

**Changes:**
- `active_ride_screen.dart`: replaced `Timer.periodic` with recursive `Future.delayed`
- Interval adapts to `position.speed` (from Geolocator, zero added cost):

| Speed | Interval |
|---|---|
| > 15 km/h | 5 s |
| 2–15 km/h | 10 s |
| < 2 km/h | 30 s |

---

### Phase 6 — Remove Hardcoded Mapbox Token

**Problem:** Public access token was hardcoded in `MapboxConfig`.

**Changes:**
- `mapbox_config.dart`: `accessToken` now uses `String.fromEnvironment('MAPBOX_ACCESS_TOKEN', defaultValue: '')`
- Pass at build time:
  ```shell
  flutter run --flavor rider -t lib/main_rider.dart \
    --dart-define=MAPBOX_ACCESS_TOKEN=pk.eyJ1...

  flutter run --flavor driver -t lib/main_driver.dart \
    --dart-define=MAPBOX_ACCESS_TOKEN=pk.eyJ1...
  ```
- `SDK_REGISTRY_TOKEN` (Maven registry auth, build-time only) — do **not** store in tracked files; use the `ORG_GRADLE_PROJECT_SDK_REGISTRY_TOKEN` environment variable or a git-ignored `local.properties`; use a CI secret for production builds

---

## Architecture (After)

```text
POST /api/v1/requests
  └─ RideService::createRideRequest()
       └─ calculateRideEstimates(... pickupId, destId)   ← cache-enabled
            └─ RouteCacheService::getOrFetchRoute()       ← DB cache, 7-day TTL
                 └─ MapboxService (driving-traffic)        ← only on cache miss
       └─ RideRequest::create(route_geometry: ...)        ← stored once

GET /api/v1/rides/{id}   or   GET /api/v1/session/resume
  └─ RideResource (with rideRequest eager-loaded)
       └─ route_geometry → mobile Ride.routeCoordinates   ← served from DB, no Mapbox

Mobile rider/driver screen
  └─ ride.routeCoordinates != null  →  draw polyline directly
  └─ ride.routeCoordinates == null  →  fallback to MapboxDirectionsService
```

---

## Files Modified

| File | Phase |
|---|---|
| `backend/database/migrations/2026_03_09_*_add_route_geometry_to_ride_requests_table.php` | 1 |
| `backend/app/Models/RideRequest.php` | 1 |
| `backend/app/Services/RideService.php` | 1 |
| `backend/app/Http/Resources/RideRequestResource.php` | 1 |
| `backend/app/Http/Resources/RideResource.php` | 1 |
| `backend/app/Http/Controllers/Api/RideController.php` | 1 |
| `backend/app/Http/Controllers/Api/AdminController.php` | 1 |
| `mobile/lib/core/models/ride.dart` | 1 |
| `mobile/lib/rider/screens/rider_active_ride_screen.dart` | 1 |
| `mobile/lib/driver/screens/active_ride_screen.dart` | 1, 5 |
| `backend/app/Services/PlaceSearchService.php` | 2 |
| `backend/config/services.php` | 2 |
| `mobile/lib/rider/screens/location_selection_screen.dart` | 2 |
| `backend/app/Services/MapboxService.php` | 3 |
| `backend/app/Services/RouteCacheService.php` | 3 |
| `mobile/lib/core/config/mapbox_config.dart` | 3, 6 |
| `backend/app/Console/Kernel.php` | 4 |
