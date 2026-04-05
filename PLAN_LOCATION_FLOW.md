# Plan: Gojek-Style Map-First Location Selection Flow

## Context

The current location selection is a search-only screen where the rider types to find both pickup and dropoff. This is being replaced with a Gojek-style flow: search screen with auto-filled pickup + recent trips → map confirmation for pickup (drag-to-pin) → map confirmation for dropoff → ride details/confirm. The system is moving from beacon-only to full P2P support.

## New Flow

```
Home → "Request Ride" →
  1. LocationSearchScreen (pickup auto-filled, dropoff focused)
     - Empty input → recent trip destinations
     - Typing → search results (reuse existing PlaceSearchProvider)
     - Both filled → navigate to map confirm
  2. MapConfirmScreen — PICKUP (drag map, fixed center pin, reverse geocode)
     - Confirm →
  3. MapConfirmScreen — DROPOFF (same widget)
     - Confirm →
  4. RideDetailsScreen (existing, updated for lat/lng)
```

---

## Step 1: Backend — P2P Estimates & Request Support

### 1a. Update getEstimates to accept lat/lng

**File:** `backend/app/Http/Controllers/Api/RequestController.php` — `getEstimates()`

Add validation for optional lat/lng fields alongside existing beacon ID fields:
- `pickup_latitude`, `pickup_longitude` (alternative to `pickup_beacon_id`)
- `destination_latitude`, `destination_longitude` (alternative to `destination_beacon_id`)

When lat/lng provided: call `RideService` directly with coordinates (skip route cache, use Mapbox Directions).

### 1b. Update CreateRideRequestRequest validation

**File:** `backend/app/Http/Requests/CreateRideRequestRequest.php`

Add rules for lat/lng + name/address as alternatives to location IDs:
```
pickup_latitude, pickup_longitude, pickup_name (required_without pickup_location_id)
destination_latitude, destination_longitude, destination_name (required_without destination_location_id)
```

### 1c. Update resolvePickupLocation for P2P

**File:** `backend/app/Services/RideService.php` — `resolvePickupLocation()`

Currently calls `getClosestBeacon()` for lat/lng pickups (snaps to nearest beacon). Change to call `findOrCreateDestination()` instead — same as destination path. This enables arbitrary pickup locations.

Keep backward compat: `pickup_location_id` path unchanged.

---

## Step 2: Mobile — Reverse Geocoding Service

### 2a. Create MapboxReverseGeocodingService

**New file:** `mobile/lib/core/services/mapbox/mapbox_reverse_geocoding_service.dart`

Follow `mapbox_directions_service.dart` pattern (same directory).

```dart
class ReverseGeocodeResult {
  final String name;
  final String? address;
  final LatLng coordinates;
}

class MapboxReverseGeocodingService {
  Future<ReverseGeocodeResult> reverseGeocode({
    required double latitude, required double longitude,
    String language = 'id',
  }) async { ... }
}
```

Endpoint: `https://api.mapbox.com/search/geocode/v6/reverse?longitude={lng}&latitude={lat}&access_token={token}&language=id&limit=1`

Fallback on error: return `ReverseGeocodeResult(name: 'Dropped Pin', coordinates: ...)`.

### 2b. Add provider

**New file:** `mobile/lib/core/providers/reverse_geocoding_provider.dart`

Simple `Provider<MapboxReverseGeocodingService>` — stateless, each call independent.

---

## Step 3: Fix MapboxMapWidget onCameraIdle

**File:** `mobile/lib/core/widgets/mapbox_map_widget.dart`

`onCameraIdle` is declared but never invoked. Add debounce-based idle detection in `_onCameraChange()`:

```dart
Timer? _idleTimer;

void _onCameraChange(...) {
  widget.onCameraMove?.call(position);
  _idleTimer?.cancel();
  _idleTimer = Timer(const Duration(milliseconds: 300), () {
    widget.onCameraIdle?.call(position);
  });
}
```

Cancel `_idleTimer` in `dispose()`.

---

## Step 4: Recent Destinations Provider

**New file:** `mobile/lib/core/providers/recent_destinations_provider.dart`

Derives from `rideHistoryProvider` — extracts unique destination locations from completed rides, maps to `PlaceSearchResult`, deduplicates by location ID, limits to 10.

---

## Step 5: Update RideRequestService/Provider for lat/lng

**File:** `mobile/lib/core/services/ride/ride_request_service.dart`

Add methods:
- `getEstimateByCoordinates(pickupLat, pickupLng, destLat, destLng, passengerCount)`
- `createRequestByCoordinates(pickupLat, pickupLng, pickupName, destLat, destLng, destName, ...)`

**File:** `mobile/lib/core/providers/ride_request_provider.dart`

Add corresponding notifier methods that delegate to the new service methods.

---

## Step 6: New Screens

### 6a. LocationSearchScreen

**New file:** `mobile/lib/rider/screens/location_search_screen.dart`

Replaces `LocationSelectionScreen` as entry from home.

- Two input fields at top: pickup (pre-filled) + dropoff (focused)
- Pickup auto-detection: `PlaceSearchService.searchNearbyBeacons()` for nearest named place, fallback to reverse geocode of GPS position
- Empty dropoff input → show recent destinations from `recentDestinationsProvider`
- Typing → show search results from `placeSearchProvider`
- Tapping pickup field → switch to pickup search mode
- Both filled → navigate to `MapConfirmScreen(mode: pickup, ...)`

### 6b. MapConfirmScreen

**New file:** `mobile/lib/rider/screens/map_confirm_screen.dart`

Reusable for both pickup and dropoff.

**Parameters:** `mode` (pickup/dropoff), `initialCenter` (LatLng), `initialName`, confirmed pickup/dropoff `PlaceSearchResult`s

**Layout:**
- Full-screen interactive `MapboxMapWidget` centered on `initialCenter`
- Fixed pin overlay at exact screen center (Flutter widget, not map marker)
- Bottom sheet: resolved place name + address + "Confirm Pickup/Dropoff" button

**Behavior:**
- `onCameraIdle` → reverse geocode center coords → update bottom sheet name
- Debounce reverse geocode (500ms generation counter to discard stale responses)
- Loading state while geocoding; block confirm until resolved
- On confirm:
  - Pickup mode → navigate to `MapConfirmScreen(mode: dropoff, ...)`
  - Dropoff mode → call `getEstimateByCoordinates()` → navigate to `RideDetailsScreen`

### 6c. Update RiderHomeScreen navigation

**File:** `mobile/lib/rider/screens/rider_home_screen.dart`

Change "Request Ride" `onPressed` to navigate to `LocationSearchScreen` instead of `LocationSelectionScreen`.

### 6d. Update RideDetailsScreen confirm action

**File:** `mobile/lib/rider/screens/ride_details_screen.dart`

On confirm: if either location has null ID (P2P), use `createRequestByCoordinates()`. Otherwise use existing `createRequest()` with IDs (backward compat).

---

## Step 7: i18n

**Files:** `mobile/lib/l10n/app_en.arb`, `mobile/lib/l10n/app_id.arb`

New keys: `whereToTitle`, `pickupFieldHint`, `dropoffFieldHint`, `recentDestinations`, `noRecentDestinations`, `confirmPickup`, `confirmDropoff`, `adjustPinPickup`, `adjustPinDropoff`, `resolvingLocation`, `droppedPin`

---

## Files Summary

| File | Action | ~Lines |
|------|--------|--------|
| `backend/app/Http/Controllers/Api/RequestController.php` | Edit getEstimates | ~30 |
| `backend/app/Http/Requests/CreateRideRequestRequest.php` | Edit validation | ~20 |
| `backend/app/Services/RideService.php` | Edit resolvePickupLocation | ~10 |
| `mobile/lib/core/services/mapbox/mapbox_reverse_geocoding_service.dart` | **New** | ~60 |
| `mobile/lib/core/providers/reverse_geocoding_provider.dart` | **New** | ~10 |
| `mobile/lib/core/widgets/mapbox_map_widget.dart` | Edit onCameraIdle | ~10 |
| `mobile/lib/core/providers/recent_destinations_provider.dart` | **New** | ~30 |
| `mobile/lib/core/services/ride/ride_request_service.dart` | Edit add methods | ~40 |
| `mobile/lib/core/providers/ride_request_provider.dart` | Edit add methods | ~30 |
| `mobile/lib/rider/screens/location_search_screen.dart` | **New** | ~250 |
| `mobile/lib/rider/screens/map_confirm_screen.dart` | **New** | ~300 |
| `mobile/lib/rider/screens/rider_home_screen.dart` | Edit navigation | ~5 |
| `mobile/lib/rider/screens/ride_details_screen.dart` | Edit confirm | ~15 |
| `mobile/lib/l10n/app_en.arb` + `app_id.arb` | Edit add keys | ~20 |

## Implementation Order

1. Backend (Steps 1a-1c) — independent, can be done first
2. Reverse geocoding service + provider (Steps 2a-2b)
3. Fix onCameraIdle (Step 3)
4. i18n strings (Step 7)
5. Recent destinations provider (Step 4)
6. RideRequestService/Provider lat/lng methods (Step 5)
7. MapConfirmScreen (Step 6b) — depends on 2, 3
8. LocationSearchScreen (Step 6a) — depends on 4, 5
9. Wire up: HomeScreen nav + RideDetailsScreen confirm (Steps 6c-6d)

## Verification

1. **Backend**: `curl` getEstimates with lat/lng params, verify fare returned
2. **Reverse geocode**: Unit test the service with known Undip coordinates
3. **Full flow**: Request ride → search screen (check recent trips, search) → map confirm pickup (drag, verify name updates) → map confirm dropoff → ride details → confirm → waiting screen
4. **Backward compat**: Old beacon-based flows still work if LocationSelectionScreen is used
5. **Edge cases**: No GPS → falls back to campus center; no ride history → empty recent list with hint; reverse geocode fails → shows "Dropped Pin"
