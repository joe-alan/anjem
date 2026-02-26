# Session Resumption - Mobile Integration Guide

## Overview

The session resumption feature allows users to seamlessly continue active rides or ride requests when they restart the app or return from the background.

**Status**: ✅ Models & Services Created | ⏳ App Integration Pending

---

## What's Been Implemented

### 1. Models ✅
- **`SessionState`** - Main model containing state, role, active ride/request, and driver context
- **`DriverContext`** - Driver-specific data (online status, active ride ID)
- **`SessionStateType`** enum - State types (idle, requestPending, requestMatched, rideActive)
- **`RideRole`** enum - User's role in the session (rider, driver)

**File**: `mobile/lib/core/models/session_state.dart`

### 2. Services ✅
- **`ApiService.getSessionState()`** - API call to `/v1/session/resume`
- **`SessionService`** - High-level service for session operations
- **`SessionProvider`** - Riverpod provider for state management

**Files**:
- `mobile/lib/core/services/api/api_service.dart` (updated)
- `mobile/lib/core/services/session/session_service.dart` (new)
- `mobile/lib/core/providers/session_provider.dart` (new)

---

## Integration Steps

### Step 1: Import the SessionProvider

In your main app files, import the session provider:

```dart
import 'package:anjem/core/providers/session_provider.dart';
import 'package:anjem/core/models/session_state.dart';
```

### Step 2: Check Session on App Launch

Add session check in your app initialization (after authentication):

**Example for Rider App** (`mobile/lib/main_rider.dart` or initialization widget):

```dart
class AppInitializationWidget extends ConsumerStatefulWidget {
  @override
  ConsumerState<AppInitializationWidget> createState() => _AppInitializationWidgetState();
}

class _AppInitializationWidgetState extends ConsumerState<AppInitializationWidget> {
  @override
  void initState() {
    super.initState();
    _checkSession();
  }

  Future<void> _checkSession() async {
    // Wait for authentication to complete
    final authState = ref.read(authStateProvider);
    if (!authState.isAuthenticated) {
      return;
    }

    // Check session state
    final sessionState = await ref.read(sessionStateProvider.notifier).checkSession();

    if (sessionState == null) {
      // No active session or error - go to home
      _navigateToHome();
      return;
    }

    // Navigate based on session state
    _handleSessionState(sessionState);
  }

  void _handleSessionState(SessionState sessionState) {
    switch (sessionState.state) {
      case SessionStateType.rideActive:
        // Resume active ride
        if (sessionState.activeRide != null) {
          _navigateToTrackingScreen(sessionState.activeRide!);
        }
        break;

      case SessionStateType.requestMatched:
      case SessionStateType.requestInProgress:
        // Show waiting for driver screen
        if (sessionState.activeRequest != null) {
          _navigateToWaitingScreen(sessionState.activeRequest!);
        }
        break;

      case SessionStateType.requestPending:
        // Show pending request overlay
        if (sessionState.activeRequest != null) {
          _navigateToPendingRequest(sessionState.activeRequest!);
        }
        break;

      case SessionStateType.idle:
      default:
        // Normal home screen
        _navigateToHome();
        break;
    }
  }

  void _navigateToTrackingScreen(Ride ride) {
    // Update active ride provider
    ref.read(activeRideProvider.notifier).setActiveRide(ride);

    // Navigate
    Navigator.of(context).pushReplacementNamed(
      '/rider/tracking',
      arguments: ride,
    );
  }

  void _navigateToWaitingScreen(RideRequest request) {
    Navigator.of(context).pushReplacementNamed(
      '/rider/waiting',
      arguments: request,
    );
  }

  void _navigateToPendingRequest(RideRequest request) {
    Navigator.of(context).pushReplacementNamed(
      '/rider/home',
      arguments: {'showPendingRequest': true, 'request': request},
    );
  }

  void _navigateToHome() {
    Navigator.of(context).pushReplacementNamed('/rider/home');
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(
        child: CircularProgressIndicator(),
      ),
    );
  }
}
```

**Example for Driver App** (`mobile/lib/main_driver.dart`):

```dart
void _handleSessionState(SessionState sessionState) {
  switch (sessionState.state) {
    case SessionStateType.rideActive:
      // Driver has active ride
      if (sessionState.activeRide != null &&
          sessionState.rideRole == RideRole.driver) {
        _navigateToActiveRideScreen(sessionState.activeRide!);
      }
      break;

    case SessionStateType.requestMatched:
      // Driver accepted a request, waiting to start
      if (sessionState.activeRequest != null) {
        _navigateToRideRequestScreen(sessionState.activeRequest!);
      }
      break;

    case SessionStateType.idle:
    default:
      // Check if driver was online
      if (sessionState.driverContext.isOnline) {
        // Driver was online, go to driver home (will show online status)
        _navigateToDriverHome(resumeOnlineStatus: true);
      } else {
        // Normal home
        _navigateToDriverHome(resumeOnlineStatus: false);
      }
      break;
  }
}
```

### Step 3: Check Session on App Resume from Background

Add lifecycle observer to check session when app returns from background:

```dart
class _AppStateObserver extends WidgetsBindingObserver {
  final WidgetRef ref;

  _AppStateObserver(this.ref);

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      // App returned to foreground
      final sessionNotifier = ref.read(sessionStateProvider.notifier);

      // Only refresh if enough time has passed
      if (sessionNotifier.shouldRefresh()) {
        sessionNotifier.refreshSession();
      }
    }
  }
}

// Register in your app initialization
@override
void initState() {
  super.initState();
  WidgetsBinding.instance.addObserver(_AppStateObserver(ref));
}

@override
void dispose() {
  WidgetsBinding.instance.removeObserver(_observer);
  super.dispose();
}
```

### Step 4: Update Active Ride Provider

When session state contains an active ride, update the active ride provider:

```dart
if (sessionState.activeRide != null) {
  ref.read(activeRideProvider.notifier).setActiveRide(sessionState.activeRide!);
}
```

### Step 5: Reconnect WebSocket

After resuming session, ensure WebSocket reconnects to the correct channels:

```dart
if (sessionState.activeRide != null) {
  final wsService = ref.read(websocketServiceProvider);
  await wsService.subscribeToRide(sessionState.activeRide!.id);
}
```

---

## Navigation Decision Tree

```
App Launch
    │
    ├─ Not Authenticated ──> Login Screen
    │
    └─ Authenticated ──> Check Session
                            │
                            ├─ SessionStateType.rideActive
                            │   └─> Navigate to TrackingScreen (Rider)
                            │       or ActiveRideScreen (Driver)
                            │
                            ├─ SessionStateType.requestMatched/requestInProgress
                            │   └─> Navigate to WaitingScreen (Rider)
                            │       or RideRequestScreen (Driver)
                            │
                            ├─ SessionStateType.requestPending
                            │   └─> Navigate to Home with Pending Request Overlay
                            │
                            └─ SessionStateType.idle
                                └─> Navigate to Home
```

---

## Example: Complete App Initialization

```dart
class RiderApp extends ConsumerStatefulWidget {
  @override
  ConsumerState<RiderApp> createState() => _RiderAppState();
}

class _RiderAppState extends ConsumerState<RiderApp>
    with WidgetsBindingObserver {

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _initializeApp();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _handleAppResume();
    }
  }

  Future<void> _initializeApp() async {
    // Wait for auth
    final authState = ref.read(authStateProvider);
    if (!authState.isAuthenticated) {
      return;
    }

    // Check session
    await _checkAndResumeSession();
  }

  Future<void> _handleAppResume() async {
    final sessionNotifier = ref.read(sessionStateProvider.notifier);
    if (sessionNotifier.shouldRefresh()) {
      final sessionState = await sessionNotifier.refreshSession();
      if (sessionState != null && !sessionState.isIdle) {
        // Show dialog asking if user wants to resume
        _showResumeDialog(sessionState);
      }
    }
  }

  Future<void> _checkAndResumeSession() async {
    final sessionState = await ref.read(sessionStateProvider.notifier).checkSession();
    if (sessionState != null && !sessionState.isIdle) {
      _navigateBasedOnSession(sessionState);
    }
  }

  void _navigateBasedOnSession(SessionState sessionState) {
    // Implementation from Step 2
  }

  void _showResumeDialog(SessionState sessionState) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Continue Previous Session?'),
        content: Text(
          sessionState.needsToResumeRide
              ? 'You have an active ride. Would you like to continue?'
              : 'You have a pending ride request. Would you like to continue?',
        ),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.pop(context);
              _navigateBasedOnSession(sessionState);
            },
            child: const Text('Yes'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('No'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      // Your app configuration
    );
  }
}
```

---

## Testing Checklist

### Rider App
- [ ] Cold start with no active session → Goes to home
- [ ] Cold start with pending request → Shows pending request
- [ ] Cold start with matched request → Shows waiting screen
- [ ] Cold start with active ride → Opens tracking screen
- [ ] App resume with active ride → Shows resume dialog
- [ ] App resume after ride completed → Goes to home

### Driver App
- [ ] Cold start offline → Goes to home (offline)
- [ ] Cold start online, no rides → Goes to home (online)
- [ ] Cold start with active ride → Opens active ride screen
- [ ] Cold start with matched request → Shows ride request screen
- [ ] App resume with active ride → Shows resume dialog
- [ ] App resume after going offline → Goes to home (offline)

### Both Apps
- [ ] WebSocket reconnects correctly after session resume
- [ ] Active ride provider updates correctly
- [ ] Location updates resume (driver)
- [ ] UI reflects correct state immediately
- [ ] Error handling when session check fails
- [ ] Handles 401 (expired token) gracefully

---

## Edge Cases to Handle

1. **Expired Token**: If session check returns 401, clear session and redirect to login
2. **Network Error**: Show error, allow retry, or default to home screen
3. **Stale Data**: Session state is cached for 5 minutes (see `shouldRefresh()`)
4. **Multiple Roles**: User with 'both' role needs correct navigation based on `rideRole`
5. **Admin Override**: Active ride may have been force-completed by admin

---

## Next Steps

1. **Integrate into rider app** (`main_rider.dart`)
2. **Integrate into driver app** (`main_driver.dart`)
3. **Test all scenarios** using the checklist above
4. **Add resume dialog** for background return
5. **Handle edge cases** (network errors, expired tokens, etc.)

---

## Files to Modify

### Rider App
- `mobile/lib/main_rider.dart` - Add session check on launch
- `mobile/lib/rider/screens/home_screen.dart` - Handle pending request overlay

### Driver App
- `mobile/lib/main_driver.dart` - Add session check on launch
- `mobile/lib/driver/screens/driver_home_screen.dart` - Restore online status

### Both
- Add lifecycle observer for app resume
- Update navigation logic
- Test thoroughly

---

**Estimated Time**: 2-3 hours for full integration + testing

**Priority**: HIGH - Essential for MVP

**Dependencies**: None (all services implemented)
