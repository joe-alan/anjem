# Session Resumption Guide

**Status**: TESTED AND COMPLETE
**Last Updated**: December 5, 2025

---

## Overview

Session resumption ensures that when a rider or driver quits the app and returns, they can immediately continue their current ride, matched request, or pending state without re-creating anything.

---

## Test Results

| Test Scenario | Status |
|--------------|--------|
| Cold start with no active session | PASS |
| Cold start with pending request (rider) | PASS |
| Cold start with active ride (rider) | PASS |
| Cold start with active ride (driver) | PASS |
| Force quit during ride - both roles | PASS |
| Driver accepts while rider app closed | PASS |
| Ride completed while rider app closed | PASS |
| Duplicate request prevention | PASS |
| WebSocket resubscription on resume | PASS |

---

## API Endpoint

### `GET /api/v1/session/resume`

**Authentication**: Bearer token (Sanctum)

**Response**:

| Field | Type | Description |
|-------|------|-------------|
| `state` | string | `idle`, `request_pending`, `request_matched`, `request_in_progress`, `ride_active` |
| `ride_role` | string\|null | `rider`, `driver`, or `null` |
| `active_ride` | object\|null | Full RideResource if ride exists |
| `active_request` | object\|null | RideRequestResource if request exists |
| `driver_context.is_driver` | bool | User has driver capability |
| `driver_context.is_online` | bool | Driver is currently online |
| `driver_context.active_ride_id` | int\|null | Active ride ID for driver |

**Example - Rider with Active Ride**:
```json
{
  "success": true,
  "data": {
    "state": "ride_active",
    "ride_role": "rider",
    "active_ride": { "id": 482, "status": "in_progress", ... },
    "active_request": null,
    "driver_context": {
      "is_driver": false,
      "is_online": false,
      "active_ride_id": null
    }
  }
}
```

---

## Mobile Implementation

### Key Files

| File | Purpose |
|------|---------|
| `mobile/lib/core/models/session_state.dart` | Session data models |
| `mobile/lib/core/services/session/session_service.dart` | Session API calls |
| `mobile/lib/core/providers/session_provider.dart` | Riverpod state management |
| `mobile/lib/core/widgets/session_check_wrapper.dart` | Handles session check on launch |

### Navigation Logic

**Rider App**:
| State | Destination |
|-------|-------------|
| `idle` | RiderHomeScreen |
| `request_pending` | WaitingScreen |
| `request_matched` | WaitingScreen |
| `ride_active` | RiderActiveRideScreen |

**Driver App**:
| State | Destination |
|-------|-------------|
| `idle` | DriverHomeScreen |
| `request_matched` | DriverHomeScreen |
| `ride_active` | ActiveRideScreen |

### App Launch Flow

```
App Start
    |
    +-- Authentication Check
    |       +-- Not authenticated --> Login Screen
    |       +-- Authenticated ↓
    |
    +-- Session Check (SessionCheckWrapper)
            +-- Calls GET /api/v1/session/resume
            |
            +-- state: idle --> Home Screen
            +-- state: request_pending --> WaitingScreen (rider)
            +-- state: request_matched --> WaitingScreen (rider)
            +-- state: ride_active --> Active Ride Screen
```

---

## Backend Implementation

### Key Files

| File | Purpose |
|------|---------|
| `backend/app/Http/Controllers/Api/SessionController.php` | Session resume endpoint |
| `backend/app/Services/RideService.php` | `getActiveRide()` and `getActiveRideRequest()` |
| `backend/tests/Feature/Api/SessionResumeTest.php` | Backend tests |

### Active Ride Query

```php
// RideService.php
public function getActiveRide(int $userId): ?Ride
{
    return Ride::where(function ($query) use ($userId) {
        $query->where('rider_id', $userId)
            ->orWhere('driver_id', $userId);
    })
    ->whereIn('status', Ride::ACTIVE_STATUSES)
    ->latest('updated_at')
    ->first();
}
```

---

## Bug Fixes Applied (December 5, 2025)

### 1. Pending Request Navigation
**Problem**: Rider with pending request was sent to HomeScreen instead of WaitingScreen.
**Fix**: Updated `SessionCheckWrapper` to navigate `requestPending` to WaitingScreen.

### 2. WebSocket Resubscription
**Problem**: Driver WebSocket not resubscribed after session resume.
**Fix**: `DriverStatusProvider.syncFromBackend()` now calls `_subscribeToRideRequests()`.

### 3. Duplicate Request Prevention
**Problem**: Rider could create new request while one was pending.
**Fix**: RiderHomeScreen now disables "Request Ride" button if active request exists.

### 4. Ride Completion Polling
**Problem**: Rider not notified when driver completes ride after app resume.
**Fix**: Added 5-second polling fallback in RiderActiveRideScreen.

---

## Edge Cases Handled

| Edge Case | Behavior |
|-----------|----------|
| Token expired | Returns 401, redirects to login |
| Network error | Shows error, defaults to home |
| Request expired (30min TTL) | Backend returns `idle` |
| Ride cancelled during offline | Backend returns `idle` |
| Wrong app for role | Navigates to home (role mismatch) |

---

## Performance

- **Cold start**: 1 API call
- **App resume**: 1 API call (only if >5 min since last check)
- **Polling fallback**: Every 5 seconds (only on active ride screen)
- **Cache duration**: 5 minutes

---

## Testing Instructions

### Manual Test Cases

1. **Rider Cold Start with Pending Request**
   - Create ride request
   - Force quit app
   - Reopen app
   - Expected: Shows WaitingScreen

2. **Rider Resume After Driver Accepts**
   - Create ride request
   - Force quit rider app
   - Accept request on driver app
   - Reopen rider app
   - Expected: Shows RiderActiveRideScreen

3. **Driver Resume with Active Ride**
   - Accept ride as driver
   - Force quit app
   - Reopen app
   - Expected: Shows ActiveRideScreen

4. **Ride Completion Detection**
   - Both apps on active ride
   - Force quit rider app
   - Complete ride on driver
   - Reopen rider app (within 5 seconds poll cycle)
   - Expected: Shows CompletedScreen

---

## Related Files

- Backend tests: `backend/tests/Feature/Api/SessionResumeTest.php`
- API docs: `docs/api/API_DOCUMENTATION.md`
- Route: `backend/routes/api.php` - `/api/v1/session/resume`
