# Phase 9 Implementation Plan: Complete Ride Flow & Driver App

**Created**: October 29, 2025
**Status**: 📋 **PLANNING**
**Estimated Duration**: 4-5 days
**Target Completion**: November 2, 2025

---

## Executive Summary

This phase completes the end-to-end ride experience by implementing:

1. **Rider waiting/matching screens** with real-time WebSocket updates
2. **Active ride tracking** with live driver location
3. **Minimal driver app** for accepting and completing rides
4. **Backend API testing tools** to simulate driver actions

### Strategic Approach

**Two-Phase Strategy:**

- **Phase A (Days 1-2)**: Build all rider UI screens, test with backend API simulation
- **Phase B (Days 3-4)**: Build minimal driver app, test end-to-end flow
- **Phase C (Day 5)**: Integration testing, bug fixes, polish

This approach allows us to:

- ✅ Complete rider experience first (most critical for MVP)
- ✅ Test WebSocket integration without building full driver UI
- ✅ Validate business logic before investing in driver UX
- ✅ Reduce context switching between apps
- ✅ Maintain fast iteration speed

---

## Timeline & Milestones

### Phase A: Complete Rider UI (1.5-2 days)

**Objective**: Build all rider screens and test with simulated driver actions

| Day          | Tasks                                  | Deliverables                      |
| ------------ | -------------------------------------- | --------------------------------- |
| **Day 1 AM** | WaitingScreen + WebSocket integration  | Rider sees queue position updates |
| **Day 1 PM** | DriverMatchedScreen with driver info   | Rider sees matched driver details |
| **Day 2 AM** | ActiveRideTrackingScreen with live map | Real-time driver location on map  |
| **Day 2 PM** | RatingScreen + API testing script      | Complete flow tested via curl     |

### Phase B: Build Minimal Driver Flow (1.5-2 days)

**Objective**: Essential driver features to enable end-to-end testing

| Day          | Tasks                                 | Deliverables                      |
| ------------ | ------------------------------------- | --------------------------------- |
| **Day 3 AM** | DriverHomeScreen + go online/offline  | Driver can join beacon queue      |
| **Day 3 PM** | RideRequestScreen with accept/decline | Driver receives and accepts rides |
| **Day 4 AM** | DriverNavigationScreen (basic)        | Driver can complete rides         |
| **Day 4 PM** | End-to-end flow testing               | Both apps working together        |

### Phase C: Integration & Polish (1 day)

**Objective**: Bug fixes, edge cases, and UX improvements

| Day       | Tasks                                 | Deliverables          |
| --------- | ------------------------------------- | --------------------- |
| **Day 5** | Bug fixes, error handling, animations | Production-ready flow |

---

## Phase A: Complete Rider UI with API Testing

### A1. WaitingScreen (3-4 hours)

**Location**: `mobile/lib/rider/screens/waiting_screen.dart`

**Features:**

- Show ride request summary (pickup → destination)
- Display estimated wait time
- Real-time queue position updates via WebSocket
- "Cancel Request" button with confirmation dialog
- Loading states and animations

**WebSocket Integration:**

```dart
@override
void initState() {
  super.initState();

  // Subscribe to user channel for ride matching
  ref.read(webSocketServiceProvider).subscribeToUserChannel(
    userId: currentUser.id,
    onRideMatched: (data) {
      // Navigate to DriverMatchedScreen
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => DriverMatchedScreen(rideId: data['ride_id']),
        ),
      );
    },
  );
}
```

**UI Layout:**

```
┌─────────────────────────┐
│  ← Cancel              │
├─────────────────────────┤
│  🔍 Finding a driver... │
│                         │
│  📍 Engineering Faculty │
│  📍 Student Center      │
│                         │
│  ⏱ Estimated wait:     │
│      ~5 minutes         │
│                         │
│  🚗 Queue position: 3   │
│                         │
│  [Loading animation]    │
│                         │
│  [Cancel Request]       │
└─────────────────────────┘
```

**State Management:**

```dart
@riverpod
class WaitingState extends _$WaitingState {
  @override
  Future<RideRequest> build(int requestId) async {
    return _fetchRequestStatus();
  }

  Future<void> cancelRequest() async {
    state = AsyncValue.loading();
    try {
      await ref.read(apiServiceProvider).patch(
        '/requests/$requestId/cancel'
      );
      state = AsyncValue.data(/* cancelled request */);
    } catch (e) {
      state = AsyncValue.error(e, StackTrace.current);
    }
  }
}
```

**Testing via Backend API:**

```bash
# Simulate ride matching
curl -X POST http://localhost:8000/api/v1/rides/{request_id}/accept \
  -H "Authorization: Bearer {driver_token}" \
  -H "Content-Type: application/json"
```

---

### A2. DriverMatchedScreen (2-3 hours)

**Location**: `mobile/lib/rider/screens/driver_matched_screen.dart`

**Features:**

- Driver profile (photo, name, rating)
- Vehicle details (make, model, color, plate number)
- ETA to pickup location
- Call driver button (phone dialer)
- "Cancel Ride" button (with penalty warning)
- Auto-transition when driver arrives

**WebSocket Integration:**

```dart
@override
void initState() {
  super.initState();

  // Subscribe to ride channel for status updates
  ref.read(webSocketServiceProvider).subscribeToRideChannel(
    rideId: widget.rideId,
    onStatusUpdate: (data) {
      if (data['status'] == 'arrived') {
        // Show "Driver has arrived" dialog
        _showDriverArrivedDialog();
      } else if (data['status'] == 'in_progress') {
        // Navigate to tracking screen
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(
            builder: (_) => ActiveRideTrackingScreen(rideId: widget.rideId),
          ),
        );
      }
    },
    onLocationUpdate: (lat, lng) {
      // Update driver ETA based on new location
      _updateDriverETA(lat, lng);
    },
  );
}
```

**UI Layout:**

```
┌─────────────────────────┐
│  ← Back                │
├─────────────────────────┤
│  ✅ Driver Matched!     │
│                         │
│  ┌─────────────────┐   │
│  │  [Driver Photo]  │   │
│  │  Budi Santoso    │   │
│  │  ⭐ 4.8 (120)    │   │
│  └─────────────────┘   │
│                         │
│  🚗 Honda Beat 2020     │
│     Black • B 1234 XYZ  │
│                         │
│  📍 ETA: 5 minutes      │
│     1.2 km away         │
│                         │
│  [📞 Call Driver]       │
│                         │
│  [Cancel Ride]          │
└─────────────────────────┘
```

**Models:**

```dart
@JsonSerializable()
class MatchedRide {
  final int id;
  final Driver driver;
  final Vehicle vehicle;
  final Location pickup;
  final Location destination;
  final double fare;
  final String status; // 'matched', 'arrived', 'in_progress'
  final int? etaMinutes;

  // Parse from WebSocket event
  factory MatchedRide.fromJson(Map<String, dynamic> json) =>
      _$MatchedRideFromJson(json);
}

@JsonSerializable()
class Driver {
  final int id;
  final String name;
  final String? photoUrl;
  final double rating;
  final int totalRides;
  final String phoneNumber;
}

@JsonSerializable()
class Vehicle {
  final String make;
  final String model;
  final int year;
  final String color;
  final String plateNumber;
}
```

---

### A3. ActiveRideTrackingScreen (3-4 hours)

**Location**: `mobile/lib/rider/screens/active_ride_tracking_screen.dart`

**Features:**

- Full-screen Mapbox map
- Driver's live location (updates every 10s via WebSocket)
- Route polyline (pickup → destination)
- Driver marker with bearing/rotation
- Ride status indicator (en route to pickup / in progress)
- ETA updates
- "Emergency Contact" button
- Auto-complete when driver marks ride done

**WebSocket Integration:**

```dart
@override
void initState() {
  super.initState();

  _subscribeToDriverLocation();
  _subscribeToRideStatus();
}

void _subscribeToDriverLocation() {
  ref.read(webSocketServiceProvider).subscribeToRideChannel(
    rideId: widget.rideId,
    onLocationUpdate: (lat, lng) {
      setState(() {
        _driverLocation = LatLng(lat, lng);
      });
      _animateDriverMarker(lat, lng);
      _updateETA();
    },
    onStatusUpdate: (data) {
      final newStatus = data['status'];
      if (newStatus == 'completed') {
        // Navigate to rating screen
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(
            builder: (_) => RatingScreen(rideId: widget.rideId),
          ),
        );
      }
    },
  );
}
```

**Map Implementation:**

```dart
class ActiveRideTrackingScreen extends ConsumerStatefulWidget {
  final int rideId;

  @override
  _ActiveRideTrackingScreenState createState() => _ActiveRideTrackingScreenState();
}

class _ActiveRideTrackingScreenState extends ConsumerState<ActiveRideTrackingScreen> {
  MapboxMap? _mapboxMap;
  LatLng? _driverLocation;
  List<LatLng> _routePoints = [];

  @override
  Widget build(BuildContext context) {
    final ride = ref.watch(activeRideProvider(widget.rideId));

    return Scaffold(
      body: Stack(
        children: [
          // Full-screen map
          MapWidget(
            onMapCreated: _onMapCreated,
          ),

          // Status card at top
          Positioned(
            top: 60,
            left: 16,
            right: 16,
            child: Card(
              child: Padding(
                padding: EdgeInsets.all(16),
                child: Column(
                  children: [
                    Text(
                      ride.status == 'arrived'
                          ? 'Driver has arrived'
                          : 'Driver on the way',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    SizedBox(height: 8),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text('${ride.driver.name}'),
                        Text('ETA: ${ride.etaMinutes} min'),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),

          // Emergency button
          Positioned(
            bottom: 32,
            right: 16,
            child: FloatingActionButton(
              onPressed: _showEmergencyDialog,
              backgroundColor: Colors.red,
              child: Icon(Icons.phone),
            ),
          ),
        ],
      ),
    );
  }

  void _onMapCreated(MapboxMap map) {
    _mapboxMap = map;
    _setupMap();
  }

  Future<void> _setupMap() async {
    // Add route polyline
    await _mapboxMap!.style.addSource(/* route source */);
    await _mapboxMap!.style.addLayer(/* polyline layer */);

    // Add driver marker
    await _mapboxMap!.annotations.createPointAnnotationManager();
  }

  void _animateDriverMarker(double lat, double lng) {
    // Smooth animation of driver marker movement
    // Calculate bearing for car rotation
  }
}
```

**UI Layout:**

```
┌─────────────────────────┐
│                         │
│  ┌───────────────────┐ │
│  │ Driver on the way │ │
│  │ Budi • ETA 5 min  │ │
│  └───────────────────┘ │
│                         │
│                         │
│      [MAP VIEW]         │
│   🚗 → (route) → 📍    │
│                         │
│                         │
│                         │
│                         │
│                    [🚨] │
└─────────────────────────┘
```

---

### A4. RatingScreen (2 hours)

**Location**: `mobile/lib/rider/screens/rating_screen.dart`

**Features:**

- Ride summary (route, fare, duration)
- Star rating (1-5)
- Predefined tags (Clean Vehicle, Safe Driving, Friendly, Punctual)
- Optional text feedback
- "Skip" button (no rating)
- Submit → Back to home screen

**UI Layout:**

```
┌─────────────────────────┐
│  Ride Completed ✅      │
├─────────────────────────┤
│                         │
│  Rp 25,730              │
│  6.6 km • 23 minutes    │
│                         │
│  Rate your driver       │
│  ⭐⭐⭐⭐⭐           │
│                         │
│  [Clean] [Friendly]     │
│  [Safe]  [Punctual]     │
│                         │
│  Additional feedback    │
│  ┌─────────────────┐   │
│  │                 │   │
│  └─────────────────┘   │
│                         │
│  [Submit Rating]        │
│  [Skip]                 │
└─────────────────────────┘
```

**Implementation:**

```dart
@riverpod
class RatingSubmission extends _$RatingSubmission {
  @override
  FutureOr<void> build() {}

  Future<void> submitRating({
    required int rideId,
    required int rating,
    List<String>? tags,
    String? feedback,
  }) async {
    state = AsyncValue.loading();

    try {
      await ref.read(apiServiceProvider).post(
        '/rides/$rideId/rate',
        data: {
          'rating': rating,
          'tags': tags ?? [],
          'feedback': feedback,
        },
      );

      state = AsyncValue.data(null);
    } catch (e, st) {
      state = AsyncValue.error(e, st);
    }
  }
}
```

---

### A5. Backend API Testing Script (1 hour)

**Location**: `scripts/test-rider-flow.sh`

**Purpose**: Simulate driver actions to test rider flow without building driver app

**Script Features:**

```bash
#!/bin/bash

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

API_URL="http://localhost:8000/api/v1"
DRIVER_TOKEN=""

# Function to get driver token
get_driver_token() {
  echo -e "${BLUE}Getting driver token...${NC}"
  # Login as driver and extract token
  # Store in DRIVER_TOKEN variable
}

# Function to accept ride request
accept_ride() {
  local REQUEST_ID=$1
  echo -e "${BLUE}Accepting ride request $REQUEST_ID...${NC}"

  RESPONSE=$(curl -s -X POST "$API_URL/rides/$REQUEST_ID/accept" \
    -H "Authorization: Bearer $DRIVER_TOKEN" \
    -H "Content-Type: application/json")

  RIDE_ID=$(echo $RESPONSE | jq -r '.data.id')
  echo -e "${GREEN}✓ Ride accepted! Ride ID: $RIDE_ID${NC}"
  echo $RIDE_ID
}

# Function to simulate driver location updates
update_location() {
  local LAT=$1
  local LNG=$2

  curl -s -X POST "$API_URL/driver/location" \
    -H "Authorization: Bearer $DRIVER_TOKEN" \
    -H "Content-Type: application/json" \
    -d "{\"latitude\": $LAT, \"longitude\": $LNG}" > /dev/null

  echo -e "${GREEN}✓ Location updated: ($LAT, $LNG)${NC}"
}

# Function to update ride status
update_ride_status() {
  local RIDE_ID=$1
  local STATUS=$2

  curl -s -X PATCH "$API_URL/rides/$RIDE_ID/status" \
    -H "Authorization: Bearer $DRIVER_TOKEN" \
    -H "Content-Type: application/json" \
    -d "{\"status\": \"$STATUS\"}" > /dev/null

  echo -e "${GREEN}✓ Ride status updated: $STATUS${NC}"
}

# Function to simulate complete ride flow
simulate_ride() {
  local REQUEST_ID=$1

  echo -e "${BLUE}========================================${NC}"
  echo -e "${BLUE}Simulating Driver Flow for Request $REQUEST_ID${NC}"
  echo -e "${BLUE}========================================${NC}"

  # Step 1: Accept ride
  RIDE_ID=$(accept_ride $REQUEST_ID)
  sleep 2

  # Step 2: Simulate driving to pickup
  echo -e "${BLUE}Driving to pickup location...${NC}"
  for i in {1..5}; do
    update_location "-6.361$i" "106.824$i"
    sleep 2
  done

  # Step 3: Mark as arrived
  echo -e "${BLUE}Arriving at pickup...${NC}"
  update_ride_status $RIDE_ID "arrived"
  sleep 3

  # Step 4: Start ride
  echo -e "${BLUE}Starting ride...${NC}"
  update_ride_status $RIDE_ID "in_progress"
  sleep 2

  # Step 5: Simulate driving to destination
  echo -e "${BLUE}Driving to destination...${NC}"
  for i in {5..10}; do
    update_location "-6.361$i" "106.824$i"
    sleep 2
  done

  # Step 6: Complete ride
  echo -e "${BLUE}Completing ride...${NC}"
  update_ride_status $RIDE_ID "completed"

  echo -e "${GREEN}========================================${NC}"
  echo -e "${GREEN}✓ Ride flow completed!${NC}"
  echo -e "${GREEN}========================================${NC}"
}

# Main script
main() {
  if [ -z "$1" ]; then
    echo -e "${RED}Usage: ./test-rider-flow.sh <request_id>${NC}"
    exit 1
  fi

  get_driver_token
  simulate_ride $1
}

main "$@"
```

**Usage:**

```bash
# 1. Start backend server
cd backend && php artisan serve

# 2. Start Laravel Reverb
php artisan reverb:start

# 3. Run rider app
cd mobile && flutter run --flavor rider

# 4. Create a ride request in the app
# Note the request ID from the console

# 5. Run simulation script
./scripts/test-rider-flow.sh 123
```

---

## Phase B: Build Minimal Driver Flow

### B1. DriverHomeScreen (2-3 hours)

**Location**: `mobile/lib/driver/screens/driver_home_screen.dart`

**Features:**

- Online/Offline toggle
- Today's earnings summary
- Current status display
- "Select Beacon" button when offline
- Queue position when online
- Active ride card when matched

**UI Layout:**

```
┌─────────────────────────┐
│  Anjem Driver    [OFF]  │
├─────────────────────────┤
│  Today's Earnings       │
│  Rp 125,000             │
│  8 rides completed      │
├─────────────────────────┤
│  Status: Offline        │
│                         │
│  [Go Online]            │
└─────────────────────────┘
```

**State Management:**

```dart
@riverpod
class DriverStatus extends _$DriverStatus {
  @override
  DriverStatusState build() => DriverStatusState.offline();

  Future<void> goOnline(int beaconId) async {
    state = DriverStatusState.loading();

    try {
      final response = await ref.read(apiServiceProvider).post(
        '/driver/online',
        data: {'beacon_location_id': beaconId},
      );

      state = DriverStatusState.online(
        beaconId: beaconId,
        queuePosition: response.data['queue_position'],
      );

      // Subscribe to driver channel for ride requests
      _subscribeToRideRequests();
    } catch (e, st) {
      state = DriverStatusState.error(e.toString());
    }
  }

  Future<void> goOffline() async {
    await ref.read(apiServiceProvider).post('/driver/offline');
    state = DriverStatusState.offline();
  }
}
```

---

### B2. BeaconSelectionScreen (1 hour)

**Location**: `mobile/lib/driver/screens/beacon_selection_screen.dart`

**Features:**

- List of available beacons
- Queue size at each beacon
- Distance from current location
- Select beacon → go online

**UI Layout:**

```
┌─────────────────────────┐
│  ← Select Beacon        │
├─────────────────────────┤
│  📍 Engineering Faculty │
│     1.2 km • 3 in queue │
│                         │
│  📍 Student Center      │
│     0.8 km • 5 in queue │
│                         │
│  📍 Library             │
│     2.1 km • 1 in queue │
└─────────────────────────┘
```

---

### B3. RideRequestScreen (2-3 hours)

**Location**: `mobile/lib/driver/screens/ride_request_screen.dart`

**Features:**

- Show ride request details
- Pickup and destination
- Estimated fare
- 30-second countdown timer
- Accept/Decline buttons
- Auto-decline on timeout

**UI Layout:**

```
┌─────────────────────────┐
│  New Ride Request       │
│  ⏱ 0:28                 │
├─────────────────────────┤
│  📍 Engineering Faculty │
│  📍 Student Center      │
│                         │
│  💰 Rp 25,730           │
│  🚗 6.6 km • 23 min     │
│                         │
│  👤 1 passenger         │
│                         │
│  [Accept Ride]          │
│  [Decline]              │
└─────────────────────────┘
```

**WebSocket Integration:**

```dart
@override
void initState() {
  super.initState();

  // Start countdown timer
  _timer = Timer.periodic(Duration(seconds: 1), (timer) {
    setState(() {
      _secondsRemaining--;
      if (_secondsRemaining <= 0) {
        _autoDecline();
      }
    });
  });
}

Future<void> acceptRide() async {
  _timer?.cancel();

  try {
    await ref.read(apiServiceProvider).post(
      '/rides/${widget.requestId}/accept',
    );

    // Navigate to navigation screen
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(
        builder: (_) => DriverNavigationScreen(rideId: /* ... */),
      ),
    );
  } catch (e) {
    // Show error, request may have been taken by another driver
  }
}
```

---

### B4. DriverNavigationScreen (Basic) (2-3 hours)

**Location**: `mobile/lib/driver/screens/driver_navigation_screen.dart`

**Features (MVP Version - Minimal):**

- Map with route to pickup/destination
- Current status indicator
- "Mark as Arrived" button (when near pickup)
- "Start Ride" button (after arrived)
- "Complete Ride" button (when near destination)
- Background location updates every 10s

**UI Layout:**

```
┌─────────────────────────┐
│  ← Back                │
│                         │
│  [MAP VIEW]             │
│  🚗 → route → 📍       │
│                         │
│  ┌───────────────────┐ │
│  │ En route to pickup│ │
│  │ 1.2 km • 5 min    │ │
│  │                   │ │
│  │ [Mark as Arrived] │ │
│  └───────────────────┘ │
└─────────────────────────┘
```

**Background Location Service:**

```dart
class DriverLocationService {
  Timer? _locationTimer;

  void startLocationUpdates() {
    _locationTimer = Timer.periodic(Duration(seconds: 10), (timer) async {
      final position = await Geolocator.getCurrentPosition();

      try {
        await ref.read(apiServiceProvider).post(
          '/driver/location',
          data: {
            'latitude': position.latitude,
            'longitude': position.longitude,
          },
        );
      } catch (e) {
        print('Failed to update location: $e');
      }
    });
  }

  void stopLocationUpdates() {
    _locationTimer?.cancel();
  }
}
```

---

## Phase C: Integration & Polish (1 day)

### Testing Checklist

**End-to-End Flow Testing:**

- [ ] Rider creates request → appears in database
- [ ] Driver receives notification via WebSocket
- [ ] Driver accepts → rider sees matched screen
- [ ] Driver location updates → rider sees live tracking
- [ ] Driver completes → rider sees rating screen
- [ ] Rider submits rating → appears in database

**Edge Cases:**

- [ ] Network disconnection during ride
- [ ] Driver cancels after accepting
- [ ] Rider cancels while driver en route
- [ ] Multiple drivers try to accept same request
- [ ] App backgrounded during active ride
- [ ] Phone call interrupts app

**Performance:**

- [ ] WebSocket reconnection works
- [ ] Location updates are smooth (not jerky)
- [ ] Map animations are smooth (60 FPS)
- [ ] Battery usage is acceptable

### Bug Fixes & Polish

- Fix any UI glitches
- Add proper loading states
- Improve error messages
- Add haptic feedback
- Optimize map performance

---

## Testing Strategy

### Unit Tests

```dart
// Test WebSocket event handling
test('onRideMatched navigates to driver matched screen', () {
  // Arrange
  final service = WebSocketService();

  // Act
  service.subscribeToUserChannel(
    userId: 1,
    onRideMatched: (data) {
      expect(data['ride_id'], 123);
    },
  );

  // Simulate event
  service._handleEvent('RideRequestMatched', {'ride_id': 123});
});

// Test ride cancellation
test('cancelRequest marks request as cancelled', () async {
  // Arrange
  final provider = RideRequestProvider();

  // Act
  await provider.cancelRequest(123);

  // Assert
  expect(provider.state.status, 'cancelled');
});
```

### Integration Tests

```dart
testWidgets('Complete rider flow', (tester) async {
  // 1. Launch app
  await tester.pumpWidget(MyApp());

  // 2. Create ride request
  await tester.tap(find.text('Request Ride'));
  await tester.pumpAndSettle();

  // 3. Simulate driver acceptance via backend
  await simulateDriverAcceptance();

  // 4. Verify navigation to matched screen
  expect(find.byType(DriverMatchedScreen), findsOneWidget);
});
```

---

## Deliverables

### Code Artifacts

- [ ] 4 new rider screens (Waiting, DriverMatched, Tracking, Rating)
- [ ] 4 new driver screens (Home, BeaconSelection, RideRequest, Navigation)
- [ ] WebSocket integration in both apps
- [ ] Background location service for driver
- [ ] Test automation script
- [ ] Unit tests for critical flows
- [ ] Integration tests for E2E flow

### Documentation

- [ ] API testing guide
- [ ] WebSocket event documentation
- [ ] Testing strategy document
- [ ] Phase 9 completion report
- [ ] Updated CLAUDE.md

### Working Features

- [ ] Rider can create and cancel requests
- [ ] Rider sees real-time driver location
- [ ] Rider can rate completed rides
- [ ] Driver can go online/offline
- [ ] Driver can accept/decline requests
- [ ] Driver can complete rides
- [ ] WebSocket events work reliably
- [ ] Both apps tested end-to-end

---

## Risk Mitigation

### Technical Risks

| Risk                                  | Probability | Impact   | Mitigation                                        |
| ------------------------------------- | ----------- | -------- | ------------------------------------------------- |
| WebSocket connection drops            | High        | High     | Implement auto-reconnect with exponential backoff |
| Location updates fail                 | Medium      | High     | Cache last known location, show warning to user   |
| Multiple drivers accept same request  | Low         | High     | Backend validation, optimistic locking            |
| App crashes during active ride        | Medium      | Critical | Persist ride state, restore on app restart        |
| Background location permission denied | Medium      | Critical | Show educational dialog, graceful degradation     |

### Process Risks

| Risk                               | Probability | Impact | Mitigation                               |
| ---------------------------------- | ----------- | ------ | ---------------------------------------- |
| Scope creep                        | Medium      | Medium | Stick to MVP features only, defer polish |
| Context switching overhead         | High        | Medium | Complete rider flow first, then driver   |
| Testing takes longer than expected | High        | Medium | Use backend API script, parallel testing |
| Bugs discovered late               | Medium      | High   | Test frequently, use integration tests   |

---

## Success Criteria

### Minimum Viable Product (MVP)

- ✅ Rider can request, track, and complete a ride
- ✅ Driver can accept and complete rides
- ✅ Real-time updates work via WebSocket
- ✅ Core happy path tested end-to-end
- ✅ No critical bugs blocking user flow

### Stretch Goals (if time permits)

- 🎯 Smooth animations and transitions
- 🎯 Proper error handling for all edge cases
- 🎯 Offline support for ride history
- 🎯 Push notifications for ride events
- 🎯 In-app messaging between rider and driver

---

## Timeline Visualization

```
Day 1 (Nov 29)          Day 2 (Nov 30)          Day 3 (Nov 31)
├─────────────┤         ├─────────────┤         ├─────────────┤
│WaitingScreen│         │TrackingScr  │         │DriverHome   │
│MatchedScr  │         │RatingScreen │         │BeaconSelect │
└─────────────┘         └─────────────┘         └─────────────┘
     PHASE A                PHASE A                 PHASE B

Day 4 (Nov 1)           Day 5 (Nov 2)
├─────────────┤         ├─────────────┤
│RideRequest  │         │ Integration │
│DriverNav    │         │Bug Fixes    │
└─────────────┘         └─────────────┘
    PHASE B                 PHASE C
```

---

## Next Steps After Phase 9

Once Phase 9 is complete, remaining MVP work:

1. **Phase 10**: Polish & UX improvements (animations, error states)
2. **Phase 11**: Push notifications (Firebase Cloud Messaging)
3. **Phase 12**: Testing & bug fixes
4. **Phase 13**: Production deployment (Play Store)

**Estimated Time to MVP**: 8-10 days from now

---

## Approval Checklist

Before starting implementation:

- [ ] User reviewed and approved plan
- [ ] Timeline is realistic (4-5 days)
- [ ] Testing strategy is clear
- [ ] Deliverables are well-defined
- [ ] Risk mitigation plans are in place
- [ ] Success criteria are agreed upon

**Status**: ⏳ **AWAITING APPROVAL**

---

**Plan Author**: Claude
**Plan Version**: 1.0
**Last Updated**: October 29, 2025
