# CONTINUE_HERE — Session Context Dump

> **Branch:** `fix/todo-issues` (created from `feat/queue-implementation`)
> **Last updated:** 2026-02-26
> **Goal:** Fix all open issues from TODO.md before first full test run, then run CodeRabbit review.

---

## Status: ALL 11 TASKS COMPLETE ✅

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

---

## Next: Run CodeRabbit review

User explicitly requested CodeRabbit review on `fix/todo-issues` branch after all tasks complete.

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
