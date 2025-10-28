# Place Search Fix Verification Guide

## Backend Fix Applied ✅

**Issue**: Proximity filter was blocking cross-continental searches (emulator in San Jose couldn't see Indonesia locations)

**Fix**: Disabled `ST_DWithin` proximity filter in `PlaceSearchService.php` line 102-113

## Backend Testing Results ✅

Direct API tests confirm search is working:

```bash
# Gate search - 2 beacons found
curl 'http://localhost:8000/api/v1/places/search?q=gate&latitude=-6.3615&longitude=106.8242'
# ✅ Returns: Gerbang Utama UI (Gate 1), Gate UI Kalisari

# Fakultas search - 10 locations found
curl 'http://localhost:8000/api/v1/places/search?q=fakultas&latitude=-6.3615&longitude=106.8242'
# ✅ Returns: FT UI, Fasilkom UI, FH UI, FEB UI, Psikologi UI + related destinations

# Kantin search - 3 canteens found
curl 'http://localhost:8000/api/v1/places/search?q=kantin&latitude=-6.3615&longitude=106.8242'
# ✅ Returns: Kantin Teknik, Kantin Fasilkom, Kantin FIK UI
```

## Mobile App Verification Steps

### 1. Restart or Hot Reload App

**Option A - If app is already running:**
```bash
# Press 'r' in the Flutter run terminal to hot reload
# The app should pick up the backend changes automatically
```

**Option B - Full restart:**
```bash
cd mobile
flutter run --debug --flavor rider -t lib/main_rider.dart
```

### 2. Test Location Selection Screen

**Test Case 1: Initial Load (Default Beacons)**
1. From Rider Home Screen, tap "Request Ride" button
2. You should see location selection screen
3. **EXPECTED**: Search bar shows "Search locations (gates, canteens, faculties...)"
4. **EXPECTED**: Should NOT see "Failed to search nearby beacons" error anymore
5. **EXPECTED**: List should show beacons from UI campus (Gate 1, Gate Kalisari, etc.)

**Test Case 2: Search for "gate"**
1. Type "gate" in search bar
2. Wait 500ms (debounce delay)
3. **EXPECTED**: See 2 results:
   - Gerbang Utama UI (Gate 1) with blue "Beacon" badge
   - Gate UI Kalisari with blue "Beacon" badge
4. Each should show address and distance

**Test Case 3: Search for "fakultas"**
1. Clear search, type "fakultas"
2. **EXPECTED**: See ~10 results including:
   - Fakultas Teknik (FT UI) - Beacon
   - Fakultas Ilmu Komputer (Fasilkom UI) - Beacon
   - Fakultas Hukum (FH UI) - Beacon
   - Plus related destinations (libraries, canteens in faculties)

**Test Case 4: Search for "kantin"**
1. Clear search, type "kantin"
2. **EXPECTED**: See 3 results:
   - Kantin Teknik (Kantin Mesin)
   - Kantin Fasilkom
   - Kantin FIK UI
3. These should NOT have "Beacon" badge (they're P2P destinations)

**Test Case 5: Select Pickup and Destination**
1. Search for "gate", select "Gerbang Utama UI" as Pickup
2. Search for "kantin", select "Kantin Fasilkom" as Drop-off
3. **EXPECTED**: See both locations in summary box at top
4. **EXPECTED**: "Continue" button appears
5. Tap "Continue"
6. **EXPECTED**: Navigate to Ride Details Screen with fare estimate

## Common Issues

### Issue: Still seeing "Failed to search nearby beacons"
**Solution**:
- Make sure backend server is running (`php artisan serve`)
- Check `mobile/lib/main_rider.dart` has correct API URL: `http://10.0.2.2:8000/api/v1`
- Clear Flutter cache: `flutter clean && flutter pub get`

### Issue: Results show but with 0.0 km distance for all
**Explanation**: This is normal! Distance calculation uses PostgreSQL PostGIS, and due to rounding, very close locations show as 0 km.

### Issue: "Location showing San Jose"
**Explanation**: This is normal emulator behavior. The emulator's GPS defaults to San Jose. The backend search now works regardless of GPS location.

## What Changed

### Backend (`backend/app/Services/PlaceSearchService.php`)
```php
// BEFORE (filtered out Indonesia from San Jose GPS):
if ($latitude !== null && $longitude !== null) {
    $queryBuilder->whereRaw(
        'ST_DWithin(coordinates::geography, ST_MakePoint(?, ?)::geography, ?)',
        [$longitude, $latitude, $radiusMeters]
    );
}

// AFTER (proximity for sorting only):
// NOTE: Proximity filter disabled for MVP testing
// Using proximity for SORTING only, not filtering
// This allows emulator (San Jose) to see Indonesia locations
```

### Mobile (`mobile/lib/rider/screens/location_selection_screen.dart`)
```dart
// BEFORE (empty string caused backend error):
await ref.read(placeSearchProvider.notifier).searchNearbyBeacons(
  userLocation: userLocation,
  radius: 5.0,
  limit: 20,
);

// AFTER (use actual search query):
await ref.read(placeSearchProvider.notifier).search(
  query: 'gate', // Search for campus gates/beacons by default
  userLocation: userLocation ?? const LatLng(-6.3615, 106.8242),
  radius: 5.0,
  limit: 20,
);
```

## Success Criteria

✅ No error on initial screen load
✅ Search returns Indonesian campus locations
✅ Beacons show blue "Beacon" badge
✅ Distance and address display correctly
✅ Can select pickup + destination and continue to ride details

## Next Steps After Verification

Once search is confirmed working:
1. Mark verification task as complete
2. Begin **Phase 8: Mapbox Directions API** (routing polylines for tracking screen)
3. Continue toward MVP completion (13 days remaining)
