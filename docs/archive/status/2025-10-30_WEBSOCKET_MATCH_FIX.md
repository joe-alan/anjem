# WebSocket Ride Match Fix — October 30, 2025

## Context
- **Issue**: Rider app remained on `WaitingScreen` even after a driver accepted the ride request via backend/test script.
- **Root Cause**: The WebSocket payload delivered minimal `pickup_location`, `destination_location`, `rider`, and `driver` objects. Our Flutter models (`Location.fromJson`, `User.fromJson`) expect full records with fields such as `type`, `created_at`, `firebase_uid`, and `email`. Missing values triggered runtime parsing errors, so `RideRequestNotifier` never stored `matchedRide` and the UI listener never navigated to `DriverMatchedScreen`.
- **Secondary Risk**: WebSocket subscriptions were set up before this fix (previous blockers: client never actually subscribed, and notifier cached a `null` user id). These were resolved earlier in the same session for completeness.

## Changes
1. **WebSocket subscription reliability** (`mobile/lib/core/services/websocket/websocket_service.dart`)
   - Call `channel.subscribe()` for every private/presence channel helper so Pusher Reverb events reach the client.
   - Log when subscriptions occur while the socket is reconnecting for easier debugging.

2. **Auth-aware ride request notifier** (`mobile/lib/core/providers/ride_request_provider.dart`)
   - Track the active user id and resubscribe when `currentUserProvider` updates.
   - Unsubscribe from stale channels on logout/switch.
   - Re-check pending ride requests for the authenticated user before subscribing.

3. **Payload normalization for ride match events** (`mobile/lib/core/providers/ride_request_provider.dart`)
   - Normalize `pickup_location` / `destination_location` hashes to include `type`, `is_active`, and timestamps.
   - Synthesize lightweight `User` payloads (placeholder `firebase_uid`/`email`) so `User.fromJson` succeeds even when the event omits those fields.
   - Preserve incoming timestamps (`matched_at`, `timestamp`) for state consistency.

## Verification
- `flutter run --flavor rider -t lib/main_rider.dart`
- Triggered a ride request, then executed `./scripts/test-rider-flow.sh accept <request_id>`.
- Observed logs:
  - WebSocket event received.
  - `RideRequestNotifier` successfully parsed the event and set `matchedRide`.
  - UI navigated to `DriverMatchedScreen`, confirming the waiting-state unblock.

## Follow-up / Notes
- Placeholder user/location fields are intentionally minimal until the backend sends richer payloads. If the API schema changes, update the normalization helpers accordingly.
- Consider backfilling complete user/location records in the broadcast event to remove the need for synthesized values on the client.
- WebSocket service now exposes `unsubscribeFromChannel`; reuse it if we add more channel lifecycle management.

## Files Modified
- `mobile/lib/core/services/websocket/websocket_service.dart`
- `mobile/lib/core/providers/ride_request_provider.dart`

