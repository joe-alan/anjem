# Anjem Mobile Testing Checklist - Mapbox Migration

**Build Status**: Ready for testing
**Phase**: 7/10 complete (Mapbox + Place Search)
**Last Updated**: October 11, 2025

---

## 🚀 Pre-Testing Setup

### 1. Build the App
```bash
# For Rider app
flutter run --flavor rider

# Or build APK
flutter build apk --debug --flavor rider

# For Driver app (if testing)
flutter run --flavor driver
```

### 2. Backend Requirements
**Ensure these are running:**
- ✅ Laravel backend: `php artisan serve` (port 8000)
- ✅ PostgreSQL database with locations seeded
- ✅ Laravel Reverb WebSocket: `php artisan reverb:start` (port 8080)

**Verify backend readiness:**
```bash
# Check API is accessible
curl http://localhost:8000/api/v1/health

# Check places endpoint
curl 'http://localhost:8000/api/v1/places/search?q=gate&limit=5'
```

### 3. Device/Emulator Setup
- **GPS/Location**: Enable location services
- **Internet**: WiFi or mobile data connected
- **Permissions**: Grant location permissions when prompted

---

## 📋 Critical Test Scenarios

### **Test 1: App Launch & Map Display**
**Priority**: 🔴 CRITICAL

**Steps:**
1. Launch the Anjem Rider app
2. Skip login/auth if possible (or use test account)
3. Observe the home screen

**✅ Expected Results:**
- [ ] Map displays successfully (not blank/white screen)
- [ ] Map shows UI campus area (Depok, Indonesia)
- [ ] Blue "user location" dot appears (if GPS enabled)
- [ ] Map is interactive (pinch to zoom, pan to move)
- [ ] No crash or error dialogs

**❌ Failure Signs:**
- Blank white screen
- "Failed to load map" error
- App crashes on startup
- Map tiles don't load

**Debug Notes:**
```
If map doesn't load:
- Check Mapbox token is in gradle.properties
- Verify AndroidManifest.xml has proper permissions
- Check Android logs: adb logcat | grep Mapbox
```

---

### **Test 2: Location Permissions**
**Priority**: 🔴 CRITICAL

**Steps:**
1. First app launch (fresh install)
2. App should request location permission
3. Grant permission

**✅ Expected Results:**
- [ ] Permission dialog appears
- [ ] After granting: blue dot appears on map
- [ ] Map centers on user's location
- [ ] "My Location" button works (centers map on user)

**❌ Failure Signs:**
- No permission request
- Permission granted but no blue dot
- App crashes after permission grant

---

### **Test 3: Place Search - Basic Functionality**
**Priority**: 🔴 CRITICAL

**Steps:**
1. From home screen, tap "Request Ride" button
2. Location Selection screen opens
3. Type "gate" in search box
4. Wait 500ms (debounce delay)

**✅ Expected Results:**
- [ ] Search box appears with placeholder text
- [ ] Loading indicator shows while searching
- [ ] Results appear within 1-2 seconds
- [ ] See at least 2-4 campus gates listed:
  - Gerbang Utama UI (Gate 1)
  - Gerbang UI Kukusan
  - Gerbang UI Pondok Cina
  - Gate UI Kalisari
- [ ] Each result shows:
  - Name
  - Address
  - Distance (e.g., "0.5 km")
  - "Beacon" badge (blue)
  - Category badge (e.g., "gate")

**❌ Failure Signs:**
- "No results found" for "gate"
- Infinite loading
- Network error message
- Crash when typing

**Debug Notes:**
```bash
# Check API is working
curl -s 'http://localhost:8000/api/v1/places/search?q=gate' | jq .

# Expected response:
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Gerbang Utama UI (Gate 1)",
      "is_beacon": true,
      ...
    }
  ]
}
```

---

### **Test 4: Place Search - Indonesian Language**
**Priority**: 🟡 HIGH

**Steps:**
1. In search box, clear previous search
2. Type "fakultas teknik"
3. Wait for results

**✅ Expected Results:**
- [ ] See Faculty of Engineering building
- [ ] See related locations (Kantin Teknik, Perpustakaan FT)
- [ ] Results are in Indonesian language
- [ ] Distance calculations work

**Test Variations:**
- [ ] "kantin" → Shows canteens (Fasilkom, FIK, etc.)
- [ ] "perpustakaan" → Shows libraries
- [ ] "asrama" → Shows dormitories

---

### **Test 5: Place Search - Proximity & Sorting**
**Priority**: 🟡 HIGH

**Steps:**
1. Enable GPS, ensure accurate location
2. Search for "ui" (generic query)
3. Observe result ordering

**✅ Expected Results:**
- [ ] Beacons appear first (have blue "Beacon" badge)
- [ ] Within beacons: sorted by distance (nearest first)
- [ ] Non-beacon locations appear after beacons
- [ ] Distance shown in km

**Example Expected Order:**
1. Gerbang Utama UI (Beacon, 0.5 km)
2. Fakultas Teknik (Beacon, 0.8 km)
3. Kantin Fasilkom (1.2 km)

---

### **Test 6: Pickup & Destination Selection**
**Priority**: 🔴 CRITICAL

**Steps:**
1. Search for "gate"
2. Tap "Pickup" button on "Gerbang Utama UI"
3. See selected pickup in summary card
4. Search for "kantin"
5. Tap "Drop-off" button on "Kantin Fasilkom"
6. Tap "Continue" button

**✅ Expected Results:**
- [ ] "Pickup" button disappears after selection
- [ ] Selected pickup shows in green summary card at top
- [ ] Can clear selection with X button
- [ ] After selecting destination, "Continue" button appears
- [ ] Can't select same location for pickup & destination
- [ ] Tapping "Continue" navigates to Ride Details screen

**❌ Failure Signs:**
- Can select same location twice
- "Continue" button doesn't appear
- Crash when tapping "Continue"
- Error: "Please select locations from campus list"

---

### **Test 7: Search Edge Cases**
**Priority**: 🟢 MEDIUM

**Test Scenarios:**

**A. Empty Search**
- Clear search box
- **Expected**: Shows nearby beacons by default

**B. No Results**
- Search for "xyz123nonexistent"
- **Expected**: "No results found" message

**C. Very Short Query**
- Type "a" (1 character)
- **Expected**: No search triggered (minimum 2 chars)

**D. Special Characters**
- Search for "fakultas & teknik"
- **Expected**: Results still appear (backend handles safely)

**E. Very Long Query**
- Type 100 characters
- **Expected**: Search still works, no crash

---

### **Test 8: Network Error Handling**
**Priority**: 🟢 MEDIUM

**Steps:**
1. Turn off WiFi/mobile data
2. Try to search for "gate"
3. Observe error handling

**✅ Expected Results:**
- [ ] Error message appears (red text)
- [ ] Message is user-friendly (not raw error)
- [ ] App doesn't crash
- [ ] Can retry when network restored

**Test Recovery:**
1. Turn WiFi back on
2. Search again
3. **Expected**: Works normally

---

### **Test 9: Map Markers Display**
**Priority**: 🟡 HIGH

**Steps:**
1. From home screen with map visible
2. Check if beacon markers appear on map
3. Pan around UI campus area

**✅ Expected Results:**
- [ ] Beacon markers visible as pins on map
- [ ] Markers appear in correct locations
- [ ] Multiple markers visible (gates, faculties, etc.)
- [ ] Markers don't overlap excessively
- [ ] Map performance is smooth (no lag)

**Note**: Marker taps currently not implemented (TODO for Phase 8)

---

### **Test 10: Location Permission Edge Cases**
**Priority**: 🟡 HIGH

**A. Permission Denied**
1. Deny location permission
2. **Expected**:
   - [ ] Map still loads
   - [ ] Centered on UI campus default location
   - [ ] No blue user dot
   - [ ] Search still works (uses default location)

**B. Permission "Don't Ask Again"**
1. Deny + check "Don't ask again"
2. **Expected**:
   - [ ] App provides button to open Settings
   - [ ] User can manually enable in Android settings

---

## 🐛 Common Issues & Solutions

### **Issue 1: Map is blank/white screen**
**Possible Causes:**
- Mapbox token not configured
- Missing AndroidManifest.xml permissions
- Network connectivity issues

**Solutions:**
```bash
# Check gradle.properties has token
cat android/gradle.properties | grep MAPBOX

# Verify AndroidManifest.xml permissions
grep -A5 "uses-permission" android/app/src/main/AndroidManifest.xml

# Check logs
adb logcat | grep -E "Mapbox|MapWidget"
```

---

### **Issue 2: Place search returns no results**
**Possible Causes:**
- Backend not running
- Database not seeded
- Network error

**Solutions:**
```bash
# Test API directly
curl 'http://localhost:8000/api/v1/places/search?q=gate'

# Check database has locations
psql anjem -c "SELECT COUNT(*) FROM locations;"
# Should show 27 (12 beacons + 15 destinations)

# Re-seed if needed
cd backend
php artisan db:seed --class=EssentialLocationsSeeder
```

---

### **Issue 3: App crashes on launch**
**Solutions:**
```bash
# Get crash logs
adb logcat | grep -E "FATAL|AndroidRuntime"

# Common fixes:
# 1. Clean build
flutter clean && flutter pub get

# 2. Rebuild
flutter build apk --debug --flavor rider

# 3. Check Firebase config
ls android/app/google-services.json
```

---

## 📊 Test Results Template

Use this to track your testing:

```
Date: ___________
Tester: ___________
Device: ___________ (e.g., Pixel 6, Android 13)

✅ = Pass  ❌ = Fail  ⚠️ = Partial

[ ] Test 1: App Launch & Map Display
[ ] Test 2: Location Permissions
[ ] Test 3: Place Search - Basic
[ ] Test 4: Place Search - Indonesian
[ ] Test 5: Place Search - Proximity
[ ] Test 6: Pickup & Destination Selection
[ ] Test 7: Search Edge Cases
[ ] Test 8: Network Error Handling
[ ] Test 9: Map Markers Display
[ ] Test 10: Location Permission Edge Cases

NOTES:
_________________________________________________
_________________________________________________
_________________________________________________
```

---

## 🎯 Success Criteria

**Minimum for Phase 7 Success:**
- ✅ App launches without crash
- ✅ Map displays UI campus
- ✅ Search for "gate" returns results
- ✅ Can select pickup + destination
- ✅ "Continue" button works

**Nice to Have:**
- ✅ GPS location working
- ✅ Markers visible on map
- ✅ Distance calculations accurate
- ✅ Indonesian search works
- ✅ Error handling graceful

---

## 📞 Reporting Issues

**If you find bugs, note:**
1. Device model & Android version
2. Exact steps to reproduce
3. Expected vs actual behavior
4. Screenshots/logs if possible
5. Network conditions (WiFi/mobile/offline)

**Log Collection:**
```bash
# Capture logs during testing
adb logcat > test_logs.txt

# Filter for errors only
adb logcat *:E > errors_only.txt

# Flutter-specific logs
flutter logs > flutter_test.log
```

---

## ⏭️ What's Next After Testing?

**If all tests pass:**
→ Proceed to Phase 8: Mapbox Directions API (routing polylines)

**If issues found:**
→ Report issues, we'll fix together, then re-test

**Testing completed successfully:**
→ Phase 9: Final end-to-end integration testing
→ Phase 10: Documentation updates

---

Good luck with testing! 🚀
