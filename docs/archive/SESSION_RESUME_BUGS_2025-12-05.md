# Session Resume & Ride Request Bugs

## Bug Summary

| # | Bug | Severity | Status | Root Cause |
|---|-----|----------|--------|------------|
| 1 | Ride request doesn't consistently popup on drivers | High | FIXED | WebSocket not resubscribed on session resume |
| 2 | Rider not redirected when driver accepts after resume | Critical | FIXED | SessionCheckWrapper sends pending requests to HomeScreen |
| 3 | Rider with active request can create duplicate | Critical | FIXED | Mobile goes to HomeScreen, backend SHOULD block |
| 4 | Ride request cancelled unexpectedly | High | N/A | Request expiration (30min TTL) - by design |

---

## Bug 1: Inconsistent Ride Request Popup

**Symptom:** Rider makes request, but it doesn't consistently show up for online drivers.

**Root Cause:**
`DriverStatusProvider.syncFromBackend()` only updates the driver status state but does NOT re-subscribe to WebSocket for incoming ride requests.

**Location:** `mobile/lib/core/providers/driver_status_provider.dart:286-309`

```dart
void syncFromBackend({
  required bool isOnline,
  int? activeRideId,
}) {
  // BUG: Only updates state, does NOT call _subscribeToRideRequests()
  if (activeRideId != null) {
    state = state.copyWith(status: DriverStatusEnum.inActiveRide, ...);
  } else if (isOnline) {
    state = state.copyWith(status: DriverStatusEnum.online, ...);
  }
  // Missing: await _subscribeToRideRequests();
}
```

**Fix:** Call `_subscribeToRideRequests()` when syncing online status from backend.

---

## Bug 2: Session Resume Not Redirecting

**Symptom:** Rider quits app with pending request → reopens → driver accepts → rider NOT redirected (stuck on home screen).

**Root Cause:**
`SessionCheckWrapper._getScreenForSessionState()` handles `requestPending` by returning HomeScreen, not WaitingScreen.

**Location:** `mobile/lib/core/widgets/session_check_wrapper.dart:229-232`

```dart
case SessionStateType.requestPending:
  // BUG: Goes to HomeScreen instead of WaitingScreen
  return widget.defaultHomeScreen;
```

**Additional Issue:**
Even if we navigate to WaitingScreen, the `rideRequestProvider` is not updated with the pending request, so:
1. WaitingScreen won't show the correct request info
2. `ref.listen` for matching won't work correctly

**Fix:**
1. Navigate to WaitingScreen for `requestPending`
2. Update `rideRequestProvider` with the active request from session state
3. Trigger WebSocket subscription for matching

---

## Bug 3: Duplicate Request Creation

**Symptom:** Rider with active ride can create a NEW ride request. This request shows up for driver on active ride.

**Root Cause Chain:**
1. Rider has pending request → force quits app
2. Rider reopens → `SessionCheckWrapper` detects `requestPending` → goes to HomeScreen (Bug #2)
3. HomeScreen allows rider to create new request
4. Backend SHOULD reject (RequestController:86-97 checks for active requests)

**Backend Validation (should work):**
```php
// RequestController.php:86-97
$activeRequest = RideRequest::where('rider_id', $rider->id)
    ->whereIn('status', ['pending', 'matched'])
    ->where('expires_at', '>', now())
    ->first();

if ($activeRequest) {
    return response()->json([
        'success' => false,
        'message' => 'You already have an active ride request',
    ], 400);
}
```

**Potential Backend Issue:**
If the request expired (30 min TTL) but wasn't cleaned up, the old request might still be "active" in some views.

**Fix:**
1. Fix Bug #2 first (rider should be on WaitingScreen)
2. Add client-side check in RiderHomeScreen before allowing request
3. Verify backend validation is working correctly

---

## Bug 4: Unexpected Request Cancellation

**Symptom:** Ride request gets cancelled without rider explicitly cancelling.

**Confirmed Cancellation Triggers:**

1. **Explicit rider cancellation:** `RideService.cancelRideRequest()` → sets `status = 'cancelled'`
2. **Ride cancellation:** `RideService.cancelRide()` → also cancels associated request
3. **Request expiration:** Requests have 30-minute TTL (`expires_at`)
   - `RideService.cleanupExpiredRequests()` → sets `status = 'expired'`
   - This is a scheduled job, not real-time

**No auto-cancellation found for:**
- Session timeout
- Driver rejection
- Duplicate request creation

**Most Likely Cause:** Request expired (30 min) while rider was away from app.

---

## Fixes Required

### Fix 1: SessionCheckWrapper - Navigate pending requests to WaitingScreen

```dart
case SessionStateType.requestPending:
  if (sessionState.activeRequest == null) {
    return widget.defaultHomeScreen;
  }
  // For riders, show waiting screen for pending requests too
  if (!config.isDriverApp) {
    return const WaitingScreen();
  }
  return widget.defaultHomeScreen;
```

### Fix 2: SessionCheckWrapper - Update rideRequestProvider

After navigating, update the `rideRequestProvider` with the session's active request:

```dart
// After determining target screen, also sync ride request state
if (sessionState.activeRequest != null && !config.isDriverApp) {
  // Need to add method to set request in rideRequestProvider
  ref.read(rideRequestProvider.notifier).setRequest(sessionState.activeRequest!);
}
```

### Fix 3: DriverStatusProvider - Resubscribe to WebSocket

```dart
void syncFromBackend({
  required bool isOnline,
  int? activeRideId,
}) async {
  if (activeRideId != null) {
    state = state.copyWith(status: DriverStatusEnum.inActiveRide, activeRideId: activeRideId);
  } else if (isOnline) {
    state = state.copyWith(status: DriverStatusEnum.online, activeRideId: null);
    // FIX: Re-subscribe to ride requests when online
    await _subscribeToRideRequests();
  } else {
    state = state.copyWith(status: DriverStatusEnum.offline, activeRideId: null);
  }
}
```

### Fix 4: RiderHomeScreen - Check for existing requests (defense in depth)

Before showing "Request Ride" button, check session state:

```dart
final sessionState = ref.watch(sessionStateProvider);
final hasActiveRequest = sessionState.sessionState?.activeRequest != null;

// Disable button if active request exists
ElevatedButton(
  onPressed: hasActiveRequest ? null : () { ... },
  child: Text(hasActiveRequest ? 'Request in Progress' : 'Request Ride'),
)
```

---

## Test Cases After Fixes

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 1 | Driver popup after resume | Go online → Force quit → Reopen → New request | Popup shows |
| 2 | Rider resume pending | Create request → Force quit → Reopen | Shows WaitingScreen |
| 3 | Rider resume + match | Create request → Force quit → Driver accepts → Reopen | Shows ActiveRideScreen |
| 4 | No duplicate creation | Has pending request → Try new request | Blocked/shows existing |
| 5 | Request expiration | Create request → Wait 30 min → Check status | Shows as expired |
