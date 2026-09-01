# Known follow-ups

Originally an audit from **2026-03-05**; reviewed **2026-09-01** during open-source prep and
re-checked against current code. Resolved items are struck through and kept for context.

---

## Bugs / correctness

### #2 — Credit deduction race on concurrent ride acceptance
**File:** `backend/app/Services/RideService.php` (`acceptRide`) + `CreditService.php` (`deductCredit`)
Credit is deducted inside the DB transaction that creates the ride. If two drivers accept the
same request simultaneously, `lockForUpdate` prevents a double-ride, but the second driver still
hits `deductCredit` before the 409 rolls back. Verify `deductCredit` is inside the same
transaction savepoint and rolls back on 409, or move the deduction to after the ride row commits.

### ~~#3 — `url_launcher` missing from pubspec~~
**Resolved.** `url_launcher: ^6.3.1` is now a declared dependency.

---

## Missing features

### ~~#4 — FCM push not sent on new ride dispatch~~
**Resolved.** `MatchingQueueService::dispatchToDriver()` now calls
`NotificationService::sendNewRideRequestToDriver()` as an FCM supplement to the WebSocket event.

### #5 — Rider map shows no real-time driver marker during ride
**File:** `mobile/lib/rider/screens/rider_active_ride_screen.dart`
The screen subscribes to `driver.location.updated` events but the map renders only a static pin
at the pickup coordinate. Wire the location stream into a provider/`setState` update that moves
the driver marker live.

### ~~#7 — No in-app rating flow after ride completion~~
**Resolved.** `Rating` model + table, `RideController::rate()` / `RideService::rateRide()`, an
observer that updates `driver_profiles.rating_average`, and the star UI in `completed_screen.dart`.

### ~~#8 — Driver earnings / trip-history screen unimplemented~~
**Resolved.** `driver_ride_history_screen.dart`, `earnings_history_screen.dart`, and
`rating_history_screen.dart` now exist, backed by `GET /driver/statistics`.

### ~~#9 — No admin credit top-up UI~~
**Resolved.** `POST /admin/drivers/{id}/credits/{grant,deduct}` with validation,
`CreditTransaction` + `AdminAuditLog` records, and Filament actions.

---

## Tech debt / code quality

### #10 — `print()` / `debugPrint()` throughout the mobile app
**Scope:** ~102 `print()` + ~314 `debugPrint()` calls across ~29 files under `mobile/lib/`.
`mobile/lib/core/utils/logger.dart` already defines `appLogger` (the `logger` package) but nothing
uses it. Migrate call sites to `appLogger`, gate it on `kDebugMode`, and turn on the `avoid_print`
lint. Verified 2026-09-01: none of these statements log a secret or token (closest is logging a
token's length), so this is tidiness, not a disclosure risk.

### ~~#11 — Dead code: `MatchingService.php`~~
**Resolved** (2026-09-01). Removed the old proximity matcher and its test;
`MatchingQueueService` is the sole dispatcher.

### #12 — Double reconnect on WebSocket drop
**File:** `mobile/lib/core/services/websocket/websocket_service.dart`
`onConnectionError` and `onDisconnected` both call `_scheduleReconnect()`; depending on the
Pusher client version both may fire for one network drop, producing two concurrent reconnect
timers. Guard with an `_isReconnecting` flag or make the timer restart idempotent.

### #13 — `expires_at` not indexed on `ride_requests`
**File:** `backend/database/migrations/`
Active-request checks and the `ExpireRideRequest` job filter on `ride_requests.expires_at`. Add a
partial index (`WHERE status IN ('pending','matched')`) before the table grows. (Verify current
migrations first — a later migration may already cover this.)

### #14 — WS channel not unsubscribed when leaving WaitingScreen
**File:** `mobile/lib/rider/screens/waiting_screen.dart` (+ `ride_request_provider.dart`)
On cancel → navigate to `RiderHomeScreen`, the `private-user.{id}` subscription stays alive in
`WebSocketService._channels`, leaking the binding and risking stale events on the next request.
Unsubscribe in the `cancelRequest()` flow or `WaitingScreen.dispose()`.

### #15 — Remove the legacy beacon-based `QueueService`
**File:** `backend/app/Services/QueueService.php` (+ `DriverQueue` model, `create_driver_queue_table`
migration, `DriverQueueResource`, `DriverQueuePage`, `QueuePositionChanged` event, ~9 tests)
`QueueService` (429 lines) implements the old fixed-pickup-point ("beacon") queue that the
[beacon → standard pivot](architecture/ARCHITECTURE_CHANGE_BEACON_TO_STANDARD.md) replaced.
`MatchingQueueService` is the active FIFO dispatcher, but `QueueService` is still injected into
`RideService` for one call (`markDriverServed()` on ride completion) and referenced by a job, a
console command, and three Filament pages. Removing it cleanly means rerouting that call,
untangling the DI, dropping the model/migration/Filament page/event, and rewriting the tests —
a self-contained refactor. The abandoned `refactor/remove-legacy-driver-queue` branch attempted
this. `QueueService` is marked `@deprecated` in the meantime.

### #16 — GitHub Actions CI is broken (both workflows, since ~April 2026)
**Files:** `.github/workflows/laravel-ci.yml`, `.github/workflows/flutter-ci.yml`
Every CI run has failed for months; the analyze/test/lint steps never actually execute.

- **Laravel CI** fails in "Setup app": `php artisan key:generate` → *"Please provide a valid
  cache path."* The repo is missing `storage/framework/{cache,sessions,views}/` (and `data/`)
  placeholder dirs, so the framework can't boot on a fresh checkout. Add the standard
  `.gitkeep`/`.gitignore` files. Behind that: PHPStan reports 8 errors at level 1 — mostly
  Larastan false positives on `JsonResource` (`relationLoaded()`, `ratings()`, `hasCapacity()`,
  `getEstimatedWaitTimeMinutes()` called via `__call` forwarding) plus two real smells
  (`DriverController.php:360` dead `?? `, `KycVerificationService.php:243` always-false
  `empty()`). Fix or add targeted `ignoreErrors`. Also verify `pint --test` and `composer audit`.
- **Flutter CI** fails at `flutter pub get`: `font_awesome_flutter ^11.0.0` needs Dart ≥3.9 /
  Flutter ≥3.35, but the workflow pins `FLUTTER_VERSION: 3.24.x`. Bump `FLUTTER_VERSION` and
  `pubspec.yaml`'s `environment.sdk` (`^3.5.4` → `^3.9.0`) to match the dev toolchain, then
  expect first-run `flutter analyze` / `flutter test` failures to work through.
- Consider trimming the aspirational jobs (APK build, integration tests, perf analysis,
  security scan) that need secrets and won't run on a fork.
