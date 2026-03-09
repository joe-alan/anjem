# Non-Urgent TODOs (Post-Beta)

Audit findings from 2026-03-05. Items #1 (rating field bug) and #6 (WS reconnect) have already been fixed.

---

## Bugs / Correctness

### #2 — Credit deduction race condition on concurrent ride acceptance
**File:** `backend/app/Services/RideService.php` (acceptRide) + `CreditService.php` (deductCredit)
Credit is deducted inside the DB transaction that creates the ride. If two drivers simultaneously accept the same request, Postgres row-locking (`lockForUpdate`) prevents a double-ride, but the second driver still hits `deductCredit` before the 409 rolls back. Verify that `CreditService::deductCredit` is inside the same transaction savepoint and is rolled back on 409, or move the deduction to after the ride row is committed.

### #3 — `url_launcher` missing from pubspec.yaml (blocks "Call driver" feature)
**File:** `mobile/pubspec.yaml`
The `url_launcher` package is referenced in driver/rider screens for the "Call driver" button but is not listed as a dependency. Add `url_launcher: ^6.x` to `pubspec.yaml` and run `flutter pub get`.

---

## Missing Features

### #4 — FCM push notification not sent on new ride dispatch
**File:** `backend/app/Services/MatchingQueueService.php` (dispatchToDriver)
`NotificationService` exists and is used elsewhere, but `dispatchToDriver` only broadcasts the WebSocket event (`NewRideRequest`). If the driver app is backgrounded and the WS connection has been killed by the OS, the driver will never see the request. Add an FCM push call via `NotificationService::sendToUser()` alongside (or as fallback to) the broadcast.

### #5 — Rider map shows no real-time driver marker during ride
**File:** `mobile/lib/rider/screens/rider_active_ride_screen.dart`
The screen subscribes to `driver.location.updated` WebSocket events but the map widget only renders a static pin at the pickup coordinate. Wire the location stream into a `setState` / provider update that moves the driver marker on the map in real time.

### ~~#7 — No in-app rating / feedback flow after ride completion~~
**Already implemented.** `Rating` model + DB table exist, `RideController::rateRide()` + `RideService::rateRide()` write ratings, a model observer auto-updates `driver_profiles.rating_average`, and `completed_screen.dart` has the star-rating UI that submits via `rideService.rateRide()`.

### #8 — Driver earnings / trip history screen is empty / unimplemented
**File:** `mobile/lib/driver/screens/driver_home_screen.dart`
The statistics endpoint (`GET /driver/statistics`) already returns `today_rides` and `today_earnings`, but there is no screen that shows per-trip history or a weekly/monthly earnings summary. Implement a trip-history screen backed by a paginated `GET /driver/trips` endpoint.

### ~~#9 — No admin credit top-up UI (manual top-up only via Tinker)~~
**Already implemented.** `POST /admin/drivers/{id}/credits/grant` and `POST /admin/drivers/{id}/credits/deduct` endpoints exist in `AdminController`, with validation, `CreditTransaction` records, `AdminAuditLog` audit entries, and Filament UI actions (Grant Credits / Deduct Credits).

---

## Tech Debt / Code Quality

### #10 — 330+ `print()` calls throughout the mobile codebase
**Scope:** All files under `mobile/lib/`
Dart's `print()` is compiled into production builds and visible in device logs. Replace with a lightweight logging package (e.g., `logger`) gated on a `kDebugMode` flag, or strip them before the production release.

### #11 — Dead code: `MatchingService.php` (old non-FIFO matcher)
**File:** `backend/app/Services/MatchingService.php`
The old proximity-based matching service is no longer injected anywhere now that `MatchingQueueService` is the sole dispatcher. Delete the file (and any related tests) to avoid confusion.

### #12 — `onConnectionError` can fire before `onDisconnected` causing double reconnect
**File:** `mobile/lib/core/services/websocket/websocket_service.dart`
Both `onConnectionError` and `onDisconnected` call `_scheduleReconnect()`. Depending on the Pusher client library version, both may fire for a single network drop, resulting in two concurrent reconnect timers. Guard with an `_isReconnecting` flag, or cancel and restart the timer idempotently (the current `_reconnectTimer?.cancel()` at the top of `_scheduleReconnect` mostly handles this, but verify under flaky-network conditions).

### #13 — `expires_at` not indexed on `ride_requests`
**File:** `database/migrations/`
Several queries filter on `ride_requests.expires_at` (e.g., active request checks, the `ExpireRideRequest` cleanup job). Add a partial index `WHERE status IN ('pending','matched')` to keep these queries fast as the table grows.

### #14 — WS channel not unsubscribed when rider navigates away from WaitingScreen
**File:** `mobile/lib/rider/screens/waiting_screen.dart` (and `ride_request_provider.dart`)
If the rider cancels and the app navigates to `RiderHomeScreen`, the `private-user.{id}` channel subscription is left alive in `WebSocketService._channels`. This leaks the binding and may deliver stale events on the next request. Call `wsService.unsubscribeFromChannel('user.$userId')` inside the `cancelRequest()` flow or in `WaitingScreen.dispose()`.
