# CONTINUE_HERE — Session Context Dump

> **Branch:** `fix/todo-issues` (created from `feat/queue-implementation`)
> **Last updated:** 2026-03-01
> **Goal:** All tasks + CodeRabbit review + tests complete. Physical device testing in progress.

---

## Status: F-1 HAPPY PATH PASSING ✅ — REMAINING DEVICE TESTS PENDING

---

## What was done — full session summary

### #1 — B1/B2: AdminController missing broadcasts
- Created `backend/app/Events/RideRequestCancelled.php` — broadcasts on `private-user.{rider_id}` AND `private-driver.{current_driver_id}`
- Created `backend/app/Events/MatchingQueuePositionChanged.php` — broadcasts on `private-driver.{driverId}`, event name `queue.position.changed`
- Modified `AdminController.php`: `cancelRequest()` now broadcasts `RideRequestCancelled` + sends push notification; `cancelRide()` and `completeRide()` now broadcast `RideStatusUpdated` and call `rejoinAfterRide()`

### #2 — Emit queue.position.changed from MatchingQueueService
- Modified `MatchingQueueService.php`: added `broadcastQueuePosition(int $driverId)` private method; called in `addToQueue()`, `removeFromQueue()`, `rejoinAfterRide()`, and `applyDeclinePenalty()`

### #3 — B3: Intermittent 400 on ride status update (RideController)
- Root cause: `updateStatus()` switch/case left `$success = false` with empty `$message` when token ability checks failed — returned silent 400
- Fix: Each status case now returns explicit 403 with descriptive message + `Log::warning()` for all permission failures

### #4 — M4 + M2: Stale screens after cancellation
- `RequestController.php`: captures `$assignedDriverId` before cancel, broadcasts `RideRequestCancelled`
- `websocket_service.dart`: added `onRequestCancelled` callback to `subscribeToDriverChannel()`
- `driver_status_provider.dart`: passes `onRequestCancelled` → calls `driverIncomingRequestProvider.notifier.clear()`
- `ride_request_screen.dart`: `ref.listen` on `driverIncomingRequestProvider` auto-dismisses when cleared
- `active_ride_screen.dart`: fixed `ref.listen` to detect `cancelled`/`completed` and call `_handleRideCompletion()`

### #5 — M6: Cancellation flow on Flutter side
- `rider_active_ride_screen.dart`: added cancel button for `RideStatus.accepted` state with confirmation dialog + `_cancelRide()` method
- `waiting_screen.dart` was already fully implemented — no changes needed

### #6 — M1: Info popup after ride cancels
- `active_ride_screen.dart` (driver): `_showCancellationInfo()` shows AlertDialog with admin reason before completing
- `rider_active_ride_screen.dart` (rider): same popup shown only for external cancellations (not when rider self-cancels via `_isCancelling` flag)

### #7 — M7: Fix driver online/offline state desync on app reopen ✅
- **Root cause:** `session_check_wrapper.dart` returned early when `sessionState.isIdle` without calling `syncFromBackend()`. A driver who is online with no active ride has state=`idle` but `driverContext.isOnline=true` — the UI stayed "Offline".
- **Fix 1 (`_checkSession`):** Added driver status sync inside the `isIdle` early-return branch for driver apps.
- **Fix 2 (`_handleAppResume`):** Also syncs driver status on app foreground resume, even when session is idle.
- Files changed: `mobile/lib/core/widgets/session_check_wrapper.dart`

### #8 — M5: Refresh driver home screen stats after ride completes
- `driver_home_screen.dart`: added `_driverStatusSub` (`ProviderSubscription<DriverStatusState>`) in `initState`
- Detects `inActiveRide → online` transition (`previous.hasActiveRide == true && !next.hasActiveRide`)
- Calls `ref.invalidate(driverStatisticsProvider)` via `Future.microtask` to trigger fresh fetch

### #9 — M3: Global 401 handler mid-session ✅
- **Problem:** Token invalidated mid-session → API returns 401 → app showed generic errors, never redirected to login.
- **Fix:** Added `void Function()? onUnauthorized` setter on `ApiService`. Called after token refresh also fails in `_createInterceptor()`. Wired in `auth_provider.dart`'s `authStateProvider` (not `api_provider.dart` to avoid circular import) — sets `onUnauthorized = notifier.signOut`.
- Files changed: `mobile/lib/core/services/api/api_service.dart`, `mobile/lib/core/providers/auth_provider.dart`

### #10 — M8: Enforce single active session per driver ✅
- **Problem:** Same driver could be active on two devices simultaneously.
- **Backend:** Created `backend/app/Events/SessionReplaced.php` — broadcasts `session.replaced` on `private-driver.{id}`. Modified `DriverController.goOnline()` to detect other active tokens, broadcast `SessionReplaced` first (so old device can receive it before token is revoked), then `DELETE` the stale tokens.
- **Mobile (`websocket_service.dart`):** Added `onSessionReplaced` callback to `subscribeToDriverChannel()`.
- **Mobile (`driver_status_provider.dart`):** Passes `onSessionReplaced` → calls `authStateProvider.notifier.signOut()`.
- Files changed: `backend/app/Events/SessionReplaced.php` (new), `backend/app/Http/Controllers/Api/DriverController.php`, `mobile/lib/core/services/websocket/websocket_service.dart`, `mobile/lib/core/providers/driver_status_provider.dart`

### #11 — B5: Investigate driver_sessions and failed_jobs tables ✅
- **`failed_jobs`:** Standard Laravel scaffold table referenced by `config/queue.php`. **Keep — do not drop.** Harmless even when Redis is the queue driver.
- **`driver_sessions`:** Model (`DriverSession.php`) and migration exist, `User` has `driverSessions()` relation, BUT no controller or service ever calls it. The model columns (`started_at`/`ended_at`) don't match the migration columns (`went_online_at`/`went_offline_at`) — it's a planned analytics feature with a schema mismatch that was never wired up. **Do not drop yet — add to backlog to either fix and integrate or remove cleanly.**

### CodeRabbit review (2026-02-26)
- 5 findings applied: driver screen `mounted` guard, Flutter CI `build_runner` fallback, rider screen cancel spinner timeout, rider screen avatar crash (`name[0]`), `RideRequestCancelled` race condition (capture `currentDriverId` at construction time).

### Tests written + fixed (2026-02-28) ✅
- **85/85 tests passing** across 4 new test files.
- Files: `tests/Unit/Services/MatchingQueueServiceTest.php`, `tests/Unit/Jobs/HandleRequestTimeoutTest.php`, `tests/Feature/Api/FifoQueueDriverControllerTest.php`, `tests/Feature/Api/FifoQueueRideFlowTest.php`

### Production bugs caught by tests (fixed in same session)
1. `HandleRequestTimeout` missing `Dispatchable` trait — `::dispatch()` would have failed at runtime.
2. `RideRequest::$fillable` missing `current_driver_id` + `rider_cooldown_until` — `update()` silently dropped these fields everywhere (cancels, timeouts, cooldowns).
3. `RideRequest::$casts` missing same fields — timestamps read back as raw strings.
4. `MatchingQueueService::getRiderCooldown()` — called `->toISOString()` on a raw string from `->value()` (not a Carbon instance). Fixed with `Carbon::parse()`.
5. `MatchingQueueService::handleDeclineOrTimeout()` — never recorded the declining driver in `tried_drivers` before calling `findTopDriver()`, so the same driver was immediately re-dispatched to themselves. Fixed by adding `recordDispatchAttempt($rideRequest, $driverId)` before `findTopDriver()`.

---

### Device testing session (2026-03-01) — F-1 bugs found and fixed

Six bugs surfaced during F-1 (Full Happy Path) testing. All fixed.

**Bug 1 — Critical: `QUEUE_CONNECTION=sync` killed every ride request instantly**
- `.env` had `QUEUE_CONNECTION=sync`. Laravel's sync driver ignores `->delay()`, so `HandleRequestTimeout` fired immediately on every `dispatchToDriver()` call. The request expired before the driver could see it.
- Fix: `.env` → `QUEUE_CONNECTION=redis`, `REDIS_CLIENT=predis` (phpredis extension not installed; predis package already present).
- Files: `backend/.env`

**Bug 2 — High: Both drivers showed the ride request; wrong driver could accept**
- On re-dispatch (timeout or decline), the previous driver's screen was never dismissed — no `ride.request.cancelled` was sent to them. Both screens showed the request simultaneously.
- `acceptRideRequest()` had no `current_driver_id` guard, so the wrong driver could accept a request that had already been re-dispatched to someone else.
- Fix: `handleDeclineOrTimeout()` now broadcasts `RideRequestCancelled(cancelledBy:'system', currentDriverId:$driverId)` after the transaction (dismisses previous driver's screen). `acceptRideRequest()` throws 403 if `current_driver_id` is set and doesn't match.
- Files: `backend/app/Services/MatchingQueueService.php`, `backend/app/Services/RideService.php`

**Bug 3 — High: Black screen after Accept or Decline**
- `_clearIncomingRequest()` set Riverpod provider to null, triggering `ref.listen` → `Navigator.pop()`. The method then also called `Navigator.pop()` or `pushReplacement()`. Double navigation emptied the Navigator stack → black screen.
- `_declineRide()` had no try/catch, so a network error left `_isProcessing = true` with `canPop: false` → frozen screen with no escape.
- Fix: added `_isDismissing` bool flag; set it before any navigation that clears the provider; `ref.listen` checks `!_isDismissing`. Added try/catch to `_declineRide()` and `_autoDecline()`.
- Files: `mobile/lib/driver/screens/ride_request_screen.dart`

**Bug 4 — High: Rider couldn't submit new request after request expired**
- `_fetchMatchViaApi()` only cleared state for `cancelled`, not `expired`. Expired request stayed in state → `hasActiveRequest = true` → submit button permanently disabled.
- `RideRequestStatus` enum was also missing `completed` — the backend does set `status = completed` on ride requests when a ride finishes. `_parseStatus('completed')` fell through to `default: pending`, so completed requests were treated as still-pending.
- Fix: added `expired` and `completed` to `RideRequestStatus` enum and `_parseStatus()`; updated `_fetchMatchViaApi()` to use `isCancelled || isExpired || isCompleted` (proper enum getters, replacing broken string comparisons).
- Files: `mobile/lib/core/models/ride_request.dart`, `mobile/lib/core/providers/ride_request_provider.dart`

**Bug 5 — Medium: Queue positions didn't cascade to other drivers**
- `broadcastQueuePosition($driverId)` only notified the driver whose status changed. When driver A rejoined, driver B's rank shifted but their UI never updated.
- Fix: added `broadcastAllQueuePositions()` private method (iterates all in-queue drivers); called in `addToQueue()`, `rejoinAfterRide()`. `removeFromQueue()` broadcasts 0 to the leaving driver then calls `broadcastAllQueuePositions()` for the rest.
- Files: `backend/app/Services/MatchingQueueService.php`

**Bug 6 — Medium: Accepting driver held position 1 for the entire ride**
- `acceptRideRequest()` never removed the driver from the FIFO queue. They kept their `queue_joined_at` timestamp and stayed at position 1 until ride completion triggered `rejoinAfterRide()`. Other drivers couldn't advance.
- Fix: `RideController::accept()` calls `matchingQueueService->removeFromQueue($driver->id)` immediately after the ride is created.
- Files: `backend/app/Http/Controllers/Api/RideController.php`

---

### Environment notes (as of 2026-03-01)
- `QUEUE_CONNECTION=redis` + `REDIS_CLIENT=predis` required in `backend/.env`
- Queue worker must be running: `php artisan queue:work redis --sleep=3 --tries=3 --timeout=60`
- Reverb must be running: `php artisan reverb:start`
- All three backend processes (serve, reverb, queue:work) must be restarted after `.env` changes

---

## Device Testing Status

See `DEVICE_TEST_LOG.md` for full step-by-step test cases.

| Test | Status | Notes |
|---|---|---|
| F-1 Full Happy Path | ✅ Pass | Required 6 bug fixes — see session below |
| H-1 FIFO Re-dispatch After Decline | ⬜ Not tested | |
| H-2 Re-dispatch After App Kill (Timeout) | ⬜ Not tested | |
| H-3 Rider Cancel Cooldown | ⬜ Not tested | |
| M-1 Driver State Desync on Reopen | ⬜ Not tested | |
| M-2 Single Session Enforcement | ⬜ Not tested | |
| L-1 Global 401 Handler | ⬜ Not tested | |
| L-2 Stale Screen After Rider Cancels | ⬜ Not tested | |
| L-3 Info Popup on Admin Cancel | ⬜ Not tested | |

## After device testing passes
1. Merge `fix/todo-issues` → `dev`
2. Eventually promote `dev` → `main` (pending PRs #27 and #28 also need review)

---

## Key Architecture Reminders

- Two separate queue systems (DO NOT confuse):
  1. `QueueService.php` / `QueuePositionChanged` event — **beacon** physical driver queue
  2. `MatchingQueueService.php` / `MatchingQueuePositionChanged` event — **FIFO matching** queue
- Driver subscribes to `private-driver.{id}` (NOT `private-user.{id}`) for: `ride.request.new`, `queue.position.changed`, `ride.request.cancelled`, `session.replaced`
- Rider subscribes to `private-user.{id}` for: `ride.request.matched`
- Both subscribe to `private-ride.{id}` for: `ride.status.updated`, `driver.location.updated`
- PRs #27 and #28 are open but NOT merged into main — `fix/todo-issues` is based on `feat/queue-implementation`
- `driver_sessions` table: schema mismatch between model and migration — planned analytics feature, not yet integrated, do not drop
