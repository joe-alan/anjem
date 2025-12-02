# Critical Fixes Required - Priority Order

**Date**: October 31, 2025
**Status**: 🔴 BLOCKS PRODUCTION
**Estimated Fix Time**: 6 hours total

---

## 🚨 P0 - CRITICAL (2 hours) - MUST FIX BEFORE ANY DEPLOYMENT

### Fix #1: Race Condition in Concurrent Acceptance (30 minutes)

**Files to Modify**:
1. `backend/app/Services/RideService.php`
2. `backend/app/Http/Controllers/Api/RideController.php`

**Current Code (VULNERABLE)**:

```php
// backend/app/Services/RideService.php:176-193
public function acceptRideRequest(int $rideRequestId, int $driverId): ?Ride
{
    try {
        DB::beginTransaction();

        // ⚠️ NO ROW LOCKING - RACE CONDITION!
        $rideRequest = RideRequest::with(['pickupLocation', 'destinationLocation', 'rider'])
            ->find($rideRequestId);

        if (! $rideRequest || ! $rideRequest->isActive()) {
            DB::rollBack();
            return null;  // ⚠️ Returns null, not exception
        }

        // ... rest of logic
    }
}
```

**Fixed Code**:

```php
// backend/app/Services/RideService.php:176-263
public function acceptRideRequest(int $rideRequestId, int $driverId): ?Ride
{
    try {
        DB::beginTransaction();

        // ✅ ADD ROW LOCKING WITH STATUS FILTER
        $rideRequest = RideRequest::lockForUpdate()
            ->where('id', $rideRequestId)
            ->where('status', 'pending')  // Only lock if pending
            ->with(['pickupLocation', 'destinationLocation', 'rider'])
            ->first();

        // ✅ THROW SPECIFIC EXCEPTIONS INSTEAD OF RETURNING NULL
        if (! $rideRequest) {
            DB::rollBack();
            throw new \Exception('This ride request has already been accepted by another driver', 409);
        }

        if (! $rideRequest->isActive()) {
            DB::rollBack();
            throw new \Exception('This ride request is no longer available', 404);
        }

        // Verify driver is valid and online
        $driver = User::with('driverProfile')->find($driverId);
        if (! $driver || ! $driver->is_active || $driver->user_type !== 'driver') {
            DB::rollBack();
            throw new \Exception('Invalid driver credentials', 403);
        }

        // Check if driver already has an active ride
        $existingRide = $this->getActiveRide($driverId);
        if ($existingRide) {
            DB::rollBack();
            throw new \Exception('You already have an active ride. Complete it before accepting another.', 400);
        }

        // Create the ride (existing logic)
        $ride = Ride::create([
            'ride_request_id' => $rideRequest->id,
            'rider_id' => $rideRequest->rider_id,
            'driver_id' => $driver->id,
            'pickup_location_id' => $rideRequest->pickup_location_id,
            'destination_location_id' => $rideRequest->destination_location_id,
            'status' => 'accepted',
            'passenger_count' => $rideRequest->passenger_count,
            'estimated_fare_rp' => $rideRequest->estimated_fare_rp,
            'special_requests' => $rideRequest->special_requests,
        ]);

        $ride->markAsAccepted();
        $rideRequest->markAsMatched();
        $this->removeActiveRequestCache($rideRequest->id);

        DB::commit();

        Log::info('Ride request accepted by driver', [
            'ride_id' => $ride->id,
            'ride_request_id' => $rideRequestId,
            'driver_id' => $driverId,
            'rider_id' => $rideRequest->rider_id,
        ]);

        return $ride;

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Failed to accept ride request', [
            'ride_request_id' => $rideRequestId,
            'driver_id' => $driverId,
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
        ]);

        throw $e;  // ✅ Re-throw exception for controller
    }
}
```

**Controller Fix**:

```php
// backend/app/Http/Controllers/Api/RideController.php:92-129
public function accept(Request $request, RideRequest $rideRequest): JsonResponse
{
    $driver = $request->user();

    // Validate driver permissions
    if (! $driver->tokenCan('driver:accept-ride')) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized: Driver permissions required',
        ], 403);
    }

    try {
        $ride = $this->rideService->acceptRideRequest($rideRequest->id, $driver->id);

        $ride->load(['rider', 'driver', 'pickupLocation', 'destinationLocation']);

        // Broadcast ride request matched event
        broadcast(new RideRequestMatched($rideRequest, $ride));
        broadcast(new RideStatusUpdated($ride, 'pending', 'driver'));

        // Send notification to rider
        $this->notificationService->sendRideAcceptedToRider($ride);

        return response()->json([
            'success' => true,
            'message' => 'Ride accepted successfully',
            'data' => new RideResource($ride),
        ]);

    } catch (\Exception $e) {
        // ✅ MAP EXCEPTION CODES TO HTTP STATUS CODES
        $statusCode = match ($e->getCode()) {
            409 => 409,  // Conflict (already accepted)
            404 => 404,  // Not found (expired/cancelled)
            403 => 403,  // Forbidden (invalid driver)
            400 => 400,  // Bad request (has active ride)
            default => 500,  // Internal server error
        };

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], $statusCode);
    }
}
```

**Test**:
```bash
./scripts/test-edge-cases.sh
# Or manually:
curl -X POST http://localhost:8000/api/v1/rides/{id}/accept \
  -H "Authorization: Bearer {driver1_token}" &
curl -X POST http://localhost:8000/api/v1/rides/{id}/accept \
  -H "Authorization: Bearer {driver2_token}" &
wait
# Expected: One 200 OK, one 409 Conflict
```

---

### Fix #2: Improve Error Response Codes (30 minutes)

**Problem**: All RideService failures return `null`, masking the actual error

**Files to Update**:
- `backend/app/Services/RideService.php` (all methods)
- `backend/app/Http/Controllers/Api/RideController.php` (updateStatus method)

**Pattern to Apply**:

```php
// BEFORE (all over RideService)
if (something_wrong) {
    return null;
}

// AFTER
if (something_wrong) {
    throw new \Exception('Specific error message', HTTP_STATUS_CODE);
}
```

**Apply to These Methods**:
1. `startRide()` - Line 300-327
2. `completeRide()` - Line 332-368
3. `cancelRide()` - Line 406-438
4. `cancelRideRequest()` - Line 373-401

---

### Fix #3: Driver Going Offline During Active Ride (10 minutes)

**File**: `mobile/lib/core/providers/driver_status_provider.dart`

**Current Code**:

```dart
// Line 100-131
Future<void> goOffline() async {
  // ❌ NO VALIDATION
  state = state.copyWith(isLoading: true, error: null);

  try {
    await _apiService.post('/driver/offline');

    await _wsService.unsubscribeFromChannel('driver.$_driverId');

    state = state.copyWith(
      status: DriverStatusEnum.offline,
      activeRideId: null,  // ⚠️ Clears active ride!
      isLoading: false,
    );
  } catch (e) {
    // ...
  }
}
```

**Fixed Code**:

```dart
// Line 100-131
Future<void> goOffline() async {
  if (_driverId == null) {
    print('DriverStatusProvider: Cannot go offline - driver ID is null');
    return;
  }

  // ✅ CHECK FOR ACTIVE RIDE FIRST
  if (state.hasActiveRide) {
    print('DriverStatusProvider: Cannot go offline - active ride in progress');
    state = state.copyWith(
      error: 'Please complete your current ride before going offline',
    );
    return;
  }

  state = state.copyWith(isLoading: true, error: null);

  try {
    print('DriverStatusProvider: Going offline for driver $_driverId');

    // Call backend endpoint
    await _apiService.post('/driver/offline');

    // Unsubscribe from driver channel
    await _wsService.unsubscribeFromChannel('driver.$_driverId');

    state = state.copyWith(
      status: DriverStatusEnum.offline,
      activeRideId: null,  // Safe to clear now
      isLoading: false,
    );

    print('DriverStatusProvider: Successfully went offline');
  } catch (e) {
    print('DriverStatusProvider: Error going offline - $e');
    state = state.copyWith(
      isLoading: false,
      error: e.toString(),
    );
  }
}
```

**Also Update UI**:

```dart
// mobile/lib/driver/screens/driver_home_screen.dart
// In FloatingActionButton onPressed:
floatingActionButton: FloatingActionButton.extended(
  onPressed: driverStatus.hasActiveRide
      ? null  // ✅ Disable button if active ride
      : () async {
          if (driverStatus.isOnline) {
            await ref.read(driverStatusProvider.notifier).goOffline();
          } else {
            await ref.read(driverStatusProvider.notifier).goOnline();
          }
        },
  // ... rest
)
```

---

### Fix #4: Deprecated WillPopScope (5 minutes)

**File**: `mobile/lib/driver/screens/ride_request_screen.dart`

**Current Code**:

```dart
// Line 173-177
return WillPopScope(  // ⚠️ DEPRECATED
  onWillPop: () async {
    return !_isProcessing;
  },
  child: Scaffold(
    // ...
  ),
);
```

**Fixed Code**:

```dart
// Use PopScope instead (Flutter 3.12+)
return PopScope(
  canPop: !_isProcessing,
  onPopInvoked: (bool didPop) {
    if (didPop) {
      print('RideRequestScreen: User popped screen');
    }
  },
  child: Scaffold(
    // ...
  ),
);
```

---

### Fix #5: Location Permission Handling (30 minutes)

**File**: `mobile/lib/driver/screens/active_ride_screen.dart`

**Current Code**:

```dart
// ⚠️ NO PERMISSION CHECKS
void _startLocationUpdates() {
  _locationTimer = Timer.periodic(const Duration(seconds: 10), (_) async {
    final position = await Geolocator.getCurrentPosition(
      desiredAccuracy: LocationAccuracy.high,  // ⚠️ DEPRECATED
    );
    await _updateDriverLocation(position);
  });
}
```

**Fixed Code**:

```dart
Future<void> _startLocationUpdates() async {
  // ✅ CHECK PERMISSIONS FIRST
  final hasPermission = await _checkLocationPermission();
  if (!hasPermission) {
    _showLocationPermissionDialog();
    return;
  }

  _locationTimer = Timer.periodic(const Duration(seconds: 10), (_) async {
    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: LocationSettings(  // ✅ NEW API
          accuracy: LocationAccuracy.high,
          distanceFilter: 10,  // Only update if moved 10m
        ),
      );
      await _updateDriverLocation(position);
    } catch (e) {
      print('❌ Location update failed: $e');
      // Don't stop timer, just skip this update
    }
  });
}

Future<bool> _checkLocationPermission() async {
  LocationPermission permission = await Geolocator.checkPermission();

  if (permission == LocationPermission.denied) {
    permission = await Geolocator.requestPermission();
  }

  if (permission == LocationPermission.deniedForever) {
    return false;
  }

  return permission == LocationPermission.whileInUse ||
         permission == LocationPermission.always;
}

void _showLocationPermissionDialog() {
  showDialog(
    context: context,
    builder: (context) => AlertDialog(
      title: const Text('Location Permission Required'),
      content: const Text(
        'This app needs location permission to track your ride. '
        'Please enable location access in Settings.',
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        ElevatedButton(
          onPressed: () {
            Navigator.pop(context);
            Geolocator.openLocationSettings();
          },
          child: const Text('Open Settings'),
        ),
      ],
    ),
  );
}
```

---

## 🔴 P1 - HIGH PRIORITY (4 hours) - NEEDED FOR BETA

### Fix #6: WebSocket Reconnection (2 hours)

**File**: `mobile/lib/core/services/websocket/websocket_service.dart`

**Add These Fields**:

```dart
class WebSocketService {
  // ... existing fields

  // ✅ ADD RECONNECTION LOGIC
  int _reconnectAttempts = 0;
  static const int MAX_RECONNECT_ATTEMPTS = 5;
  static const int RECONNECT_BASE_DELAY_MS = 1000;  // 1 second
  Timer? _reconnectTimer;
  bool _isReconnecting = false;
}
```

**Add Reconnection Method**:

```dart
Future<void> _handleDisconnection() async {
  if (_isReconnecting || _reconnectAttempts >= MAX_RECONNECT_ATTEMPTS) {
    return;
  }

  _isReconnecting = true;
  _reconnectAttempts++;

  // Exponential backoff: 1s, 2s, 4s, 8s, 16s
  final delay = Duration(
    milliseconds: RECONNECT_BASE_DELAY_MS * pow(2, _reconnectAttempts - 1) as int,
  );

  print('⚠️  WebSocket disconnected. Reconnecting in ${delay.inSeconds}s... (Attempt $_reconnectAttempts/$MAX_RECONNECT_ATTEMPTS)');

  _reconnectTimer = Timer(delay, () async {
    try {
      await connect();
      print('✅ WebSocket reconnected successfully!');
      _reconnectAttempts = 0;  // Reset on success
      _isReconnecting = false;

      // Resubscribe to channels
      // (Implement channel resubscription logic)

    } catch (e) {
      print('❌ Reconnection attempt $_reconnectAttempts failed: $e');
      _isReconnecting = false;

      if (_reconnectAttempts < MAX_RECONNECT_ATTEMPTS) {
        _handleDisconnection();  // Try again
      } else {
        print('❌ Max reconnection attempts reached. Giving up.');
        // Show user notification
      }
    }
  });
}
```

**Hook Into Disconnection Events**:

```dart
// In connect() method, add disconnection handler
_pusher.onConnectionStateChange((state) {
  print('Connection state changed: ${state?.currentState}');

  if (state?.currentState == 'disconnected' || state?.currentState == 'failed') {
    _handleDisconnection();
  } else if (state?.currentState == 'connected') {
    _reconnectAttempts = 0;  // Reset counter on successful connection
  }
});
```

---

### Fix #7: Foreground Service for Active Rides (2 hours)

**Dependencies to Add** (`mobile/pubspec.yaml`):

```yaml
dependencies:
  flutter_foreground_task: ^8.0.0
  flutter_local_notifications: ^17.0.0
```

**Implementation**:

Create new file: `mobile/lib/core/services/foreground_service.dart`

```dart
import 'package:flutter_foreground_task/flutter_foreground_task.dart';

class RideForegroundService {
  static void initForegroundTask() {
    FlutterForegroundTask.init(
      androidNotificationOptions: AndroidNotificationOptions(
        channelId: 'ride_tracking_channel',
        channelName: 'Ride Tracking',
        channelDescription: 'Active ride tracking notification',
        channelImportance: NotificationChannelImportance.LOW,
        priority: NotificationPriority.LOW,
        iconData: const NotificationIconData(
          resType: ResourceType.mipmap,
          resPrefix: ResourcePrefix.ic,
          name: 'launcher',
        ),
      ),
      iosNotificationOptions: const IOSNotificationOptions(
        showNotification: true,
        playSound: false,
      ),
      foregroundTaskOptions: const ForegroundTaskOptions(
        interval: 10000,  // 10 seconds
        isOnceEvent: false,
        autoRunOnBoot: false,
        allowWakeLock: true,
        allowWifiLock: true,
      ),
    );
  }

  static Future<bool> startForegroundService(int rideId) async {
    return await FlutterForegroundTask.startService(
      notificationTitle: 'Ride in Progress',
      notificationText: 'Ride #$rideId is active',
      callback: _startCallback,
    );
  }

  static Future<bool> stopForegroundService() async {
    return await FlutterForegroundTask.stopService();
  }

  @pragma('vm:entry-point')
  static void _startCallback() {
    FlutterForegroundTask.setTaskHandler(RideTaskHandler());
  }
}

class RideTaskHandler extends TaskHandler {
  @override
  Future<void> onStart(DateTime timestamp, SendPort? sendPort) async {
    print('Foreground task started');
  }

  @override
  Future<void> onEvent(DateTime timestamp, SendPort? sendPort) async {
    // Update driver location every 10 seconds
    print('Updating location in background...');

    // Call location update logic
    // (Access shared state or use platform channels)
  }

  @override
  Future<void> onDestroy(DateTime timestamp, SendPort? sendPort) async {
    print('Foreground task destroyed');
  }
}
```

**Use in ActiveRideScreen**:

```dart
@override
void initState() {
  super.initState();
  _loadRideData();
  _startLocationUpdates();

  // ✅ START FOREGROUND SERVICE
  RideForegroundService.startForegroundService(widget.rideId);
}

@override
void dispose() {
  _locationTimer?.cancel();

  // ✅ STOP FOREGROUND SERVICE
  RideForegroundService.stopForegroundService();

  super.dispose();
}
```

---

## 📊 Fix Implementation Checklist

### P0 - Must Do (2 hours)
- [ ] Add row locking to `acceptRideRequest()` (30 min)
- [ ] Convert null returns to exceptions in RideService (30 min)
- [ ] Update RideController error handling (10 min)
- [ ] Fix driver going offline with active ride (10 min)
- [ ] Replace WillPopScope with PopScope (5 min)
- [ ] Fix location permission handling (30 min)
- [ ] Test all fixes (20 min)

### P1 - Should Do (4 hours)
- [ ] Implement WebSocket reconnection (2 hours)
- [ ] Add foreground service for location tracking (2 hours)
- [ ] Test reconnection scenarios (30 min)
- [ ] Test backgrounding scenarios (30 min)

### Total Time: 6 hours

---

## ✅ Verification Tests

After fixes, run:

1. **Automated Tests**:
   ```bash
   ./scripts/test-edge-cases.sh
   ```

2. **Manual Tests**:
   - Concurrent acceptance (2 drivers, 1 request)
   - Network disconnect during ride
   - App backgrounding during ride
   - Location permission denial
   - Driver going offline with active ride

3. **Expected Results**:
   - ✅ Only one driver accepts each request
   - ✅ WebSocket auto-reconnects
   - ✅ Location updates continue in background
   - ✅ Graceful error messages for all failures
   - ✅ Cannot go offline during active ride

---

**Document Created**: October 31, 2025
**Next Action**: Implement P0 fixes immediately
