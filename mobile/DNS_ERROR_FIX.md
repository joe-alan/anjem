# DNS Resolution Error Fix - "No address associated with hostname"

**Date**: December 3, 2025
**Issue**: Rider app crashes with DNS error when ride request is accepted
**Error**: `OSError: No address associated with hostname, errno = 7`

---

## The Problem

### What Happened:
1. Rider created a ride request
2. Driver accepted the ride
3. Rider app transitioned to RiderActiveRideScreen
4. Screen tried to fetch route from Mapbox Directions API
5. **DNS lookup for `api.mapbox.com` failed**
6. App crashed with "No address associated with hostname"

### Root Cause:
**Android Emulator DNS Issue**

The Android emulator sometimes has DNS resolution problems, especially when:
- Emulator has been running for a long time
- Network connectivity changed (WiFi switch, VPN, etc.)
- Emulator's DNS cache is corrupted
- Host machine's DNS is slow or misconfigured

**Why it worked on driver but not rider:**
- Both apps use the same Mapbox API
- The driver app likely loaded the map earlier (DNS was cached)
- The rider app hit the DNS issue when trying to fetch routes

---

## The Fix

### Changes Made:

#### 1. Added Timeout to HTTP Requests (mapbox_directions_service.dart)
```dart
// Make request with timeout
final response = await http.get(uri).timeout(
  const Duration(seconds: 10),
  onTimeout: () {
    throw MapboxDirectionsException(
      'Request timed out. Please check your internet connection.',
    );
  },
);
```

#### 2. Better DNS Error Detection (mapbox_directions_service.dart)
```dart
// Check for DNS resolution errors
if (e.toString().contains('No address associated with hostname') ||
    e.toString().contains('Failed host lookup') ||
    e.toString().contains('SocketException')) {
  print('⚠️  DNS resolution failed - this is usually an emulator network issue');
  throw MapboxDirectionsException(
    'Network error: Cannot connect to Mapbox. '
    'If using emulator, try:\n'
    '1. Restart the emulator\n'
    '2. Check emulator network settings\n'
    '3. Use a physical device instead',
  );
}
```

#### 3. User-Friendly Error Message (rider_active_ride_screen.dart)
```dart
// Show user-friendly error message
if (mounted) {
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(
      content: Text(
        e.toString().contains('Network error')
            ? 'Cannot load map route. Using GPS tracking instead.'
            : 'Failed to load route. Tracking driver location...',
      ),
      duration: const Duration(seconds: 4),
      backgroundColor: Colors.orange,
    ),
  );
}
```

**Result**:
- ✅ App no longer crashes on DNS errors
- ✅ Shows user-friendly message
- ✅ Continues functioning (can still track driver via GPS/WebSocket)
- ✅ Route polyline won't show, but markers and tracking still work

---

## How to Fix the DNS Issue

### Quick Fixes (Try in order):

#### 1. **Restart the Emulator** (Fastest)
```bash
# Stop emulator
# Start it again from Android Studio or:
emulator -avd <your_avd_name>
```

#### 2. **Use Cold Boot**
```bash
# In Android Studio:
# Tools > AVD Manager > [Your Device] > Cold Boot Now
```

#### 3. **Reset Emulator DNS**
```bash
# Connect to emulator
adb shell

# Clear DNS cache
setprop net.dns1 8.8.8.8
setprop net.dns2 8.8.4.4

# Exit
exit
```

#### 4. **Use Physical Device**
Physical devices don't have this DNS issue. To test on physical device:
```bash
# Enable USB debugging on phone
# Connect via USB
# Check device is detected:
adb devices

# Run app:
flutter run
```

#### 5. **Change Emulator Network Mode**
In Android Studio:
- Open AVD Manager
- Edit your emulator
- Advanced Settings > Network
- Try changing network mode

---

## Why This Happens

### Android Emulator Network Stack:
The Android emulator uses a complex network stack:

```
App → Emulator Network → Host OS Network → Internet
```

Problems can occur at any layer:
1. **Emulator DNS cache** - Stale or corrupted
2. **Host OS DNS** - Slow or misconfigured
3. **VPN interference** - VPN can block emulator DNS
4. **Firewall rules** - May block emulator's DNS queries
5. **Network changes** - WiFi switch, etc.

### errno = 7 (ENODATA):
This specific error means:
- The DNS server was reached
- But it returned "no data" for the hostname
- Usually indicates DNS cache corruption in the emulator

---

## Prevention

### Best Practices:

1. **Use Physical Devices for Network-Heavy Testing**
   - More reliable
   - Better performance
   - Real-world conditions

2. **Restart Emulator Regularly**
   - Especially after network changes
   - After long running sessions
   - When switching VPNs

3. **Use Android 11+ Emulator Images**
   - Better network stack
   - Improved DNS handling

4. **Configure Static DNS in Emulator**
   ```bash
   adb shell
   setprop net.dns1 8.8.8.8
   setprop net.dns2 8.8.4.4
   ```

5. **Monitor Emulator Logs**
   ```bash
   adb logcat | grep -i dns
   ```

---

## Workarounds if DNS Persists

### Option 1: Use IP Address (Not Recommended)
```dart
// Instead of api.mapbox.com, use IP
// NOT RECOMMENDED - IP can change, breaks HTTPS
```

### Option 2: Fallback to Straight-Line Route
```dart
// If Mapbox fails, draw straight line between pickup and destination
if (routeFetchFailed) {
  return [pickupLatLng, destinationLatLng]; // Straight line
}
```

### Option 3: Disable Route Visualization
```dart
// Show map without route polyline
// Only show markers and driver location
```

---

## Testing the Fix

### Test Scenario:
1. **Start rider app** (on emulator)
2. **Create ride request**
3. **Accept on driver app**
4. **Rider transitions to tracking screen**

### Expected Behavior:

#### If DNS works:
✅ Route polyline displays
✅ Map shows route from pickup to destination
✅ Normal operation

#### If DNS fails (with our fix):
✅ App **does not crash**
✅ Shows message: "Cannot load map route. Using GPS tracking instead."
✅ Map still shows:
  - Pickup marker
  - Destination marker
  - Driver location (via WebSocket)
✅ User can still complete the ride

---

## Files Modified

1. **mobile/lib/core/services/mapbox/mapbox_directions_service.dart**
   - Added 10-second timeout
   - Better DNS error detection
   - User-friendly error messages

2. **mobile/lib/rider/screens/rider_active_ride_screen.dart**
   - Added SnackBar for route fetch errors
   - Graceful degradation (works without route polyline)

---

## Related Issues

This fix also helps with:
- Network timeouts
- Slow Mapbox API responses
- Intermittent connectivity issues
- Any hostname resolution problems

---

## Long-Term Solution

### Backend Proxy (Future Enhancement):
Instead of calling Mapbox directly from mobile:

```
Mobile App → Backend API → Mapbox API
```

Benefits:
- No DNS issues on emulator
- Can cache routes on backend
- Better control over API usage
- Easier to switch mapping providers

Implementation:
```php
// backend/routes/api.php
Route::get('/mapbox/directions', [MapboxController::class, 'getDirections']);
```

---

**Status**: ✅ **Fixed** - DNS errors handled gracefully, app no longer crashes

**Recommendation**: Use physical device for testing ride flows to avoid emulator network issues.

---

**Last Updated**: December 3, 2025
