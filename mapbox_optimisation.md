# Mapbox Optimisation Plan

**Date:** 2026-03-09
**Branch:** `feat/mapbox-optimisation`
**Status:** Planned

---

## Context

Anjem currently uses 4 Mapbox APIs: Directions v5, Map Tiles (SDK), Search Box Suggest, and Search Box Retrieve. However, the Search Box APIs are disabled (`MIN_RESULTS_THRESHOLD = 0`), and mobile apps make redundant Directions API calls that the backend already caches. This plan addresses:

1. **Wasted API calls** — mobile calls Mapbox Directions directly for every active ride, duplicating what the backend already fetched and cached
2. **Search Box disabled** — riders can only select pre-seeded campus locations; off-campus or lesser-known destinations are unreachable
3. **Missing infrastructure** — route cache cleanup never runs, no traffic-aware routing, hardcoded tokens

---

## Phase 1: Eliminate Duplicate Mapbox Directions Calls

> **Impact:** Removes 2 Mapbox API calls per ride (rider + driver screens). Fixes a bug where `createRideRequest()` bypasses route cache.

### Problem

- Backend fetches route geometry during ride creation (`RideService::calculateRideEstimates`, line 646 returns `routeGeometry`) but **never stores or returns it**
- `createRideRequest()` (RideService.php:62-68) calls `calculateRideEstimates()` **without location IDs**, so `RouteCacheService` is bypassed every time
- Mobile `rider_active_ride_screen.dart` (line 143) and `driver/active_ride_screen.dart` (line 232) each make their own Mapbox Directions call

### Changes

#### Backend

**1. New migration: add `route_geometry` to `ride_requests`**
```
backend/database/migrations/YYYY_MM_DD_add_route_geometry_to_ride_requests.php
```
- Add `route_geometry` JSON nullable column after `estimated_fare_rp`

**2. RideRequest model** — `backend/app/Models/RideRequest.php`
- Add `'route_geometry'` to `$fillable` (line 25-39)
- Add `'route_geometry' => 'json'` to `$casts` (line 46-58)

**3. RideService::createRideRequest** — `backend/app/Services/RideService.php:62-68`
- Pass `$pickupLocation->id` and `$destinationLocation->id` to `calculateRideEstimates()` (enables RouteCacheService)
- Store `$estimates['routeGeometry']` in the `RideRequest::create` call (line 71-82)

**4. RideRequestResource** — `backend/app/Http/Resources/RideRequestResource.php:17-39`
- Add `'route_geometry' => $this->route_geometry` to response

**5. RideResource** — `backend/app/Http/Resources/RideResource.php:17-60`
- Add route_geometry from the ride_request relation:
  ```php
  'route_geometry' => $this->when(
      $this->relationLoaded('rideRequest'),
      fn() => $this->rideRequest?->route_geometry
  ),
  ```

#### Mobile

**6. Ride model** — `mobile/lib/core/models/ride.dart`
- Add `final List<LatLng>? routeCoordinates` field
- Parse `route_geometry['coordinates']` in `fromJson()` via `_parseRouteGeometry()` (null-safe, type-guarded)
- Add to constructor, `copyWith()`, `props`, `toJson()`

**7. rider_active_ride_screen.dart** — `_fetchAndDisplayRoute()` (line 121-173)
- Try `ride.routeCoordinates` first → convert to `List<LatLng>`
- Fallback to `MapboxDirectionsService.getRoute()` only if null/empty

**8. driver/active_ride_screen.dart** — `_fetchAndDisplayRoute()` (line 177-200+)
- For `in_progress` status: use backend geometry (pickup→destination is fixed)
- For `accepted` status: keep direct Mapbox call (driver→pickup is dynamic)

---

## Phase 2: Enable Search Box API for Production

> **Impact:** Riders can search for any location, not just pre-seeded beacons. Mapbox results auto-cache into local DB.

### Problem

- `PlaceSearchService.php` line 25: `MIN_RESULTS_THRESHOLD = 0` — Mapbox fallback never triggers
- No session token — each suggest + retrieve is billed separately
- No geographic bounding box — results could be anywhere in the world

### Changes

#### Backend

**1. PlaceSearchService.php** — `backend/app/Services/PlaceSearchService.php`

- **Line 25:** Change `MIN_RESULTS_THRESHOLD` from `0` to `3`
- **`searchMapboxAPI()` (line 153-196):**
  - Generate session token: `$sessionToken = (string) Str::uuid();`
  - Add `'session_token' => $sessionToken` to suggest request (line 162-168)
  - Add `'bbox' => '106.80,-6.39,106.86,-6.33'` to scope results to UI campus area
  - Pass `$sessionToken` to `retrieveMapboxPlace()` calls
- **`retrieveMapboxPlace()` (line 201-233):**
  - Add `string $sessionToken` parameter
  - Add `'session_token' => $sessionToken` to retrieve request

**2. Make campus bbox configurable** — `backend/config/services.php`
- Add `'search_bbox'` to mapbox config (defaults to UI campus bounding box)
- PlaceSearchService reads from config instead of hardcoding

#### Mobile

**3. LocationSelectionScreen** — `mobile/lib/rider/screens/location_selection_screen.dart`
- Verify cached Mapbox results (which have IDs after `PlaceSearchService::cacheAPIResult`) pass the `id != null` validation at line 376-388
- Current logic should already work since `cacheAPIResult()` calls `Location::create()` which generates an ID — but verify and add a test

---

## Phase 3: Switch to `driving-traffic` Profile

> **Impact:** Better ETAs at zero additional cost. Same Mapbox pricing tier.

### Changes

**1. Backend** — `backend/app/Services/MapboxService.php:45`
- Change `mapbox/driving/` to `mapbox/driving-traffic/`

**2. Mobile** — `mobile/lib/core/config/mapbox_config.dart:36`
- Change `defaultDirectionsProfile` from `'driving'` to `'driving-traffic'`

**3. Clear stale cache after deploy**
- The `route_caches` table has routes cached with the `driving` profile
- Either: run `RouteCache::truncate()` once, or add profile column check
- `RouteCacheService` already stores `profile` in cache key (line ~60), so new `driving-traffic` requests will naturally miss the old `driving` cache entries and re-fetch

---

## Phase 4: Schedule Route Cache Cleanup

> **Impact:** Prevents unbounded `route_caches` table growth.

### Changes

**1. Kernel.php** — `backend/app/Console/Kernel.php`
- Add after line 29:
  ```php
  $schedule->call(function () {
      app(\App\Services\RouteCacheService::class)->cleanupStaleRoutes(30);
  })->daily()->at('03:00')->name('cleanup-stale-routes')->withoutOverlapping();
  ```

**2. (Optional) Cache warming** — same file
- Call `RouteCacheService::warmCache()` for top 10 popular routes weekly
- Low priority; the cache-aside pattern already handles this organically

---

## Phase 5: Adaptive Driver Location Updates

> **Impact:** Reduces battery drain and backend load when driver is stationary.

### Changes

**1. driver/active_ride_screen.dart** — location timer (line 92-130)
- Replace `Timer.periodic(Duration(seconds: 10), ...)` with adaptive logic:
  - Speed > 15 km/h → 5s interval
  - Speed 2-15 km/h → 10s interval (current default)
  - Speed < 2 km/h → 30s interval
- Use `position.speed` from Geolocator (already available)
- Implement via recursive `Future.delayed` instead of fixed `Timer.periodic`

**2. No change to idle updates** — `driver_home_screen.dart` (30s) is already appropriate

---

## Phase 6: Security — Move Hardcoded Token

> **Impact:** Removes Mapbox public token from source control.

### Changes

**1. mapbox_config.dart** — `mobile/lib/core/config/mapbox_config.dart:11-12`
- Replace hardcoded token with:
  ```dart
  static const String accessToken = String.fromEnvironment(
    'MAPBOX_ACCESS_TOKEN',
    defaultValue: '',
  );
  ```

**2. environment.dart** — `mobile/lib/core/config/environment.dart`
- Add `MAPBOX_ACCESS_TOKEN` to environment config documentation

**3. Update run commands** in docs:
  ```
  flutter run --flavor rider -t lib/main_rider.dart \
    --dart-define=MAPBOX_ACCESS_TOKEN=pk.eyJ1...
  ```

**4. gradle.properties** — `mobile/android/gradle.properties`
- `MAPBOX_DOWNLOADS_TOKEN` is needed for Maven artifact download (build-time only, not runtime) — this is acceptable to keep but should use a CI secret in production builds

---

## Phase 7: Update Outdated Documentation

**1. `docs/optimization/ROUTE_API_CACHING_PLAN.md`**
- Change status from "Planned (Not Yet Implemented)" to "Implemented"
- Note actual TTL is 7 days (not 2 hours as proposed)
- Note geometry is now stored on `ride_requests` (after Phase 1)

---

## Implementation Order & Dependencies

```
Phase 1 (Eliminate Duplicates)  ← Do first, highest ROI
Phase 2 (Search Box)            ← Independent, can parallel with Phase 1
Phase 3 (driving-traffic)       ← After Phase 1 deploy (cache profile issue)
Phase 4 (Cache Cleanup)         ← Independent, quick win
Phase 5 (Adaptive Updates)      ← Independent
Phase 6 (Security)              ← Last (requires build pipeline changes)
Phase 7 (Docs)                  ← Alongside any phase
```

Phases 1, 2, 4, 5 are independently deployable. Phase 3 should follow Phase 1.

---

## Files Modified (Summary)

| File | Phases |
|------|--------|
| `backend/database/migrations/new_migration.php` | 1 |
| `backend/app/Models/RideRequest.php` | 1 |
| `backend/app/Services/RideService.php` | 1 |
| `backend/app/Http/Resources/RideRequestResource.php` | 1 |
| `backend/app/Http/Resources/RideResource.php` | 1 |
| `backend/app/Services/PlaceSearchService.php` | 2 |
| `backend/config/services.php` | 2 |
| `backend/app/Services/MapboxService.php` | 3 |
| `backend/app/Console/Kernel.php` | 4 |
| `mobile/lib/core/models/ride.dart` | 1 |
| `mobile/lib/rider/screens/rider_active_ride_screen.dart` | 1 |
| `mobile/lib/driver/screens/active_ride_screen.dart` | 1, 5 |
| `mobile/lib/core/config/mapbox_config.dart` | 3, 6 |
| `mobile/lib/rider/screens/location_selection_screen.dart` | 2 (verify) |
| `mobile/lib/core/config/environment.dart` | 6 |
| `docs/optimization/ROUTE_API_CACHING_PLAN.md` | 7 |

---

## Verification

### Phase 1
- Run `php artisan migrate` — verify `route_geometry` column added
- Create a ride request via API → check `ride_requests` table has `route_geometry` populated
- Fetch active ride via `GET /rides/{id}` → verify `route_geometry` in JSON response
- Mobile: open rider active ride screen → confirm route draws without Mapbox network call (check logs)
- Mobile: open driver active ride screen (in_progress) → same check
- Run `php artisan test` — ensure no regressions

### Phase 2
- Seed DB with < 3 locations matching "coffee"
- Search "coffee" via `GET /places/search?q=coffee&latitude=-6.36&longitude=106.82`
- Verify Mapbox Search Box is called (check logs) and results include Mapbox-sourced places
- Verify cached results have `id` (not null) in response
- Mobile: search in LocationSelectionScreen → select a Mapbox-sourced result → confirm ride request succeeds

### Phase 3
- Check MapboxService logs show `driving-traffic` in URL
- Verify new ride request ETAs reflect traffic conditions
- Confirm old `driving`-profile cache entries are not served (profile mismatch)

### Phase 4
- Run `php artisan schedule:test` or wait for 03:00 daily run
- Check `route_caches` table — entries older than 30 days should be deleted

### Phase 5
- Start a ride as driver, remain stationary → verify location updates slow to 30s (check backend logs)
- Start driving > 15 km/h → verify updates increase to 5s

### Phase 6
- Run app without `--dart-define=MAPBOX_ACCESS_TOKEN` → verify map fails gracefully
- Run with token → verify map works
