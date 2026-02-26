# Session Resumption Guide

_Last updated: December 2, 2025_

This document explains how the **ride session resumption** feature works inside Anjem. The goal is to ensure that when a rider or driver quits the app and later returns, they can immediately continue the current ride, matched request, or queue state without re-creating anything.

---

## High-level Flow

1. **App launch** → call `GET /api/v1/session/resume` using the latest Sanctum token.
2. Backend inspects the authenticated user for:
   - Active ride (driver or rider) via `RideService::getActiveRide()`
   - Active ride request (pending/matched/in_progress) via `RideService::getActiveRideRequest()`
3. Backend responds with a normalized payload containing the current state, role, ride/request details, and driver context.
4. Mobile decides where to navigate:
   - Show **Resume Ride** button / auto-open tracking screen when `state === 'ride_active'`
   - Show **Waiting for Driver** UI when `state === 'request_matched'`
   - Resume beacon selection when `state === 'request_pending'`
   - Default home when `state === 'idle'`

---

## API Contract: `GET /api/v1/session/resume`

| Field | Type | Description |
|-------|------|-------------|
| `state` | string | `idle`, `request_pending`, `request_matched`, `request_in_progress`, `ride_active` |
| `ride_role` | string\|null | `rider`, `driver`, or `null` when no active ride |
| `active_ride` | object\|null | Full `RideResource` including rider/driver info, pickup & destination, parent request |
| `active_request` | object\|null | `RideRequestResource` with pickup/destination when no ride exists yet |
| `driver_context.is_driver` | bool | Whether the user has driver capability (`role` in `driver/both/admin`) |
| `driver_context.is_online` | bool | Driver profile `went_online_at !== null` |
| `driver_context.went_online_at` | ISO datetime\|null | Timestamp driver went online |
| `driver_context.active_ride_id` | int\|null | Active ride ID if the user is currently the driver |

### Sample responses

#### Rider returning to an active ride
```json
{
  "success": true,
  "data": {
    "state": "ride_active",
    "ride_role": "rider",
    "active_ride": { "id": 482, "status": "driver_arrived", ... },
    "active_request": null,
    "driver_context": {
      "is_driver": false,
      "is_online": false,
      "went_online_at": null,
      "active_ride_id": null
    }
  }
}
```

#### Driver still waiting for a rider (matched request)
```json
{
  "success": true,
  "data": {
    "state": "request_matched",
    "ride_role": null,
    "active_ride": null,
    "active_request": {
      "id": 991,
      "status": "matched",
      "pickup_location": { ... },
      "destination_location": { ... }
    },
    "driver_context": {
      "is_driver": true,
      "is_online": true,
      "went_online_at": "2025-12-02T02:21:00.000000Z",
      "active_ride_id": null
    }
  }
}
```

---

## Backend Components Involved

| File | Purpose |
|------|---------|
| `backend/app/Models/Ride.php` | Defines `ACTIVE_STATUSES = ['matched','accepted','driver_arrived','in_progress']` and scope helpers. |
| `backend/app/Services/RideService.php` | Implements `getActiveRide()` and `getActiveRideRequest()` used by the session controller. |
| `backend/app/Http/Controllers/Api/SessionController.php` | New controller that assembles the response payload. |
| `backend/routes/api.php` | Registers `GET /api/v1/session/resume` behind Sanctum auth + rate limiting. |
| `docs/api/API_DOCUMENTATION.md` | Describes the endpoint contract for mobile/web clients. |
| Tests (`backend/tests/*`) | `SessionResumeTest` covers feature-level scenarios; unit tests ensure RideService helpers work. |

---

## Mobile Integration Notes

1. **Call on cold start** (after Firebase/Sanctum login) and when the app returns from background to foreground.
2. Use `state` to decide navigation:
   - `ride_active` → rehydrate `activeRideProvider` with `active_ride`.
   - `request_matched` / `request_in_progress` → show the matched/waiting screens and resubscribe to the right WebSocket channels.
   - `request_pending` → show pending request overlay with countdown.
   - `idle` → simply show the normal home screen.
3. Cache `driver_context.active_ride_id` for drivers so they can publish location updates immediately after a relaunch.
4. Handle `active_request == null` + `active_ride == null` gracefully; this means no action is needed.

---

## Edge Cases & Considerations

- **Token Expiry**: If Sanctum tokens are invalid, the endpoint returns `401` like other protected routes; mobile should re-authenticate.
- **Multiple Roles**: A `both` user can have a ride as rider or driver; `ride_role` clarifies which UI should be resumed.
- **Stale Requests**: `getActiveRideRequest()` automatically ignores expired requests; if the rider relaunches after expiration they will land on `idle`.
- **Admin Overrides**: Active rides include the full `RideResource`, so admin-forced cancellations or completions propagate immediately.

---

## Future Enhancements

- Persist additional metadata (`last_screen`, `last_known_step`) if mobile needs even finer resume control.
- Add push notification deep links that open the app and immediately navigate based on the same endpoint payload.
- Consider caching the response for a few seconds (Redis) if resume calls become hot after reconnect storms.
