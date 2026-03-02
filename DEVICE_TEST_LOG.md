# Device Test Log — `fix/todo-issues`

> **Branch:** `fix/todo-issues`
> **Date:** 2026-03-01 (F-1 tested); remaining tests pending
> **Devices:** Pixel 6 emulator (Driver 1), Pixel 6 emulator (Driver 2), Realme 13+ (Rider)
> **Build flavors:** `flutter run --flavor driver` / `flutter run --flavor rider`

---

## Status Summary

| Test                                                                                  | Status        |
| ------------------------------------------------------------------------------------- | ------------- |
| [F-1 Full Happy Path](#f-1-full-happy-path)                                           | ✅ Pass       |
| [H-1 FIFO Re-dispatch After Decline](#h-1-fifo-re-dispatch-after-decline)             | ✅ Pass       |
| [H-2 Re-dispatch After App Kill (Timeout)](#h-2-re-dispatch-after-app-kill-timeout)   | ✅ Pass       |
| [H-3 Rider Cancel Cooldown](#h-3-rider-cancel-cooldown)                               | ⬜ Not tested |
| [H-4 No-Drivers Countdown on Waiting Screen](#h-4-no-drivers-countdown-on-waiting-screen) | ⬜ Not tested |
| [M-1 Driver State Desync on Reopen](#m-1-driver-state-desync-on-app-reopen)           | ⬜ Not tested |
| [M-2 Single Session Enforcement](#m-2-single-session-enforcement)                     | ⬜ Not tested |
| [L-1 Global 401 Handler](#l-1-global-401-handler-mid-session)                         | ⬜ Not tested |
| [L-2 Stale Screen After Rider Cancels](#l-2-stale-screen-after-rider-cancels)         | ⬜ Not tested |
| [L-3 Info Popup on Admin Cancel](#l-3-info-popup-on-admin-cancel)                     | ⬜ Not tested |

> **Status key:** ⬜ Not tested · ✅ Pass · ❌ Fail · ⚠️ Partial

---

## Key Numbers

| Value         | What                                                                 |
| ------------- | -------------------------------------------------------------------- |
| 35s           | Server-side timeout (`HandleRequestTimeout` job)                     |
| ~30s          | Mobile UI countdown timer on driver's incoming request screen        |
| 60s           | No-drivers countdown before request expires (shown on waiting screen)|
| 60s           | Rider cooldown after cancel or request expiry                        |
| 5             | FIFO candidate pool size (nearest-first selection picks from top 5)  |
| 50 m          | Distance tiebreaker bucket — same bucket → longer-waiting wins       |
| 15 min        | Driver penalty cooldown (6+ declines in window)                      |
| 5 declines    | Driver moved to bottom of queue                                      |
| 6 declines    | Driver suspended for 15 min                                          |
| 15 min window | Decline count resets if no new decline for 15 min                   |

---

## Matching Algorithm (current)

> **Nearest-first within FIFO pool** — implemented in `MatchingQueueService::findTopDriver()` (uncommitted as of 2026-03-02).

1. All standard eligibility filters apply (verified, online, no active ride, within `max_pickup_radius_km`, not in cooldown, not already tried).
2. Of eligible drivers, take the **5 longest-waiting** (`queue_joined_at ASC LIMIT 5`).
3. Among those 5, pick the **closest** to the rider's pickup.
4. Tiebreaker: drivers within the same **50 m** distance bucket → **longer-waiting wins**.

This means a driver can only be skipped if up to 4 others have been waiting longer AND are meaningfully closer.

---

## F-1: Full Happy Path

> Run this first to confirm the baseline works before edge cases.
> **Requires:** Driver A, Driver B, Rider

| #   | Step                                | Expected Output                                                                 | Result |
| --- | ----------------------------------- | ------------------------------------------------------------------------------- | ------ |
| 1   | Driver A goes **Online**            | Queue position = 1 shown in UI                                                  |        |
| 2   | Driver B goes **Online**            | Driver B queue position = 2                                                     |        |
| 3   | Rider submits a request             | Waiting screen shows "Finding Driver" title + spinning search icon              |        |
| 4   | Driver A receives the request       | `ride.request.new` fires; incoming request card appears with pickup/destination |        |
| 5   | Driver A taps **Accept**            | Rider screen changes to "Driver matched" with driver info                       |        |
| 6   | Driver A taps **Arrived at Pickup** | Rider screen changes to "Driver arrived"                                        |        |
| 7   | Driver A taps **Start Ride**        | Rider screen changes to "Ride in progress"                                      |        |
| 8   | Driver A taps **Complete Ride**     | Rider sees receipt / completion screen                                          |        |
| 9   | Check Driver A's queue position     | Driver A rejoins queue at **back** — position 2 (behind Driver B who is now 1)  |        |

**Notes / Observations:**

```
Passed after 6 bugs found and fixed during this session (see Bugs Found table below).
Requires QUEUE_CONNECTION=redis in .env — sync driver ignores job delays and fires
the 35s timeout immediately, expiring every request on creation.

NOTE: Nearest-first matching is now active (top-5 FIFO pool, closest wins).
With only 2 drivers the happy path is unaffected; it becomes observable at 3+ drivers.

NOTE: Waiting screen Cancel button now shows a confirmation dialog ("Are you sure?")
before cancelling. Step 3 cancellation flow requires two taps.
```

**Test Result:** ✅

---

## H-1: FIFO Re-dispatch After Decline

> Previously silently broken — same driver was re-dispatched to themselves after declining.
> **Requires:** Driver A, Driver B, Rider

| #   | Step                                                    | Expected Output                                        | Result |
| --- | ------------------------------------------------------- | ------------------------------------------------------ | ------ |
| 1   | Driver A + Driver B online, Driver A joined queue first | Driver A = position 1, Driver B = position 2           |        |
| 2   | Rider submits request                                   | Driver A receives `ride.request.new`                   |        |
| 3   | Driver A taps **Decline**                               | Incoming request card dismisses on Driver A's screen   |        |
| 4   | Check Driver B                                          | Driver B receives the **same request** (re-dispatched) |        |
| 5   | Check Driver A                                          | Driver A does **NOT** receive the request again        |        |
| 6   | Driver B accepts                                        | Rider sees "Driver matched" — matched to Driver B      |        |

> With nearest-first matching active: re-dispatch goes to the best candidate from the remaining pool (not the raw next-in-FIFO), but with only 2 drivers the result is the same.

**Edge case — no next driver:**

| #   | Step                                    | Expected Output                                                           | Result |
| --- | --------------------------------------- | ------------------------------------------------------------------------- | ------ |
| E1  | Only Driver A online; Driver A declines | No next driver available                                                  |        |
| E2  | Rider's waiting screen                  | Title stays "Finding Driver"; icon greys out; text changes to "No drivers available right now" + "Retrying in Ns…" inline |        |
| E3  | Countdown reaches 0 (or WS expiry fires) | Rider auto-navigated to home screen                                      |        |
| E4  | Rider tries to submit again immediately  | **60s cooldown error** shown (cooldown delivered via WS payload + API)   |        |

**Log to watch (queue worker terminal):**

```
Processing job: HandleRequestTimeout
[should show dispatch to Driver B's ID, not Driver A's]

ride.no_drivers_available broadcast → [ride_request_id, countdown_seconds=60]
ExpireRideRequest job fires after 60s
ride.request.cancelled broadcast on private-user.{rider_id} with rider_cooldown_until set
```

**Notes / Observations:**

```
Passed. Re-dispatch to Driver B confirmed after Driver A declines.
Rider remained on the waiting screen during re-dispatch (notifyRider:false fix confirmed).
Driver B accepts; rider navigated to active ride screen correctly.

Edge case (no next driver):
- Waiting screen stayed on unified "Finding Driver" title throughout.
- Icon greyed out and "No drivers available right now" + "Retrying in Ns…" appeared inline.
- Countdown ran to zero; rider auto-navigated to home via safety-net.
- 60s cooldown enforced on next request attempt.
```

**Test Result:** ✅

---

## H-2: Re-dispatch After App Kill (Timeout)

> Safety-net job fires at 35s server-side when driver doesn't respond.
> **Requires:** Driver A, Driver B, Rider

| #   | Step                                                                | Expected Output                                      | Result |
| --- | ------------------------------------------------------------------- | ---------------------------------------------------- | ------ |
| 1   | Driver A + Driver B online; Rider submits request                   | Driver A receives request                            |        |
| 2   | **Force-kill** Driver A's app immediately (don't accept or decline) |                                                      |        |
| 3   | Wait **35 seconds**                                                 | `HandleRequestTimeout` job fires in queue worker log |        |
| 4   | Driver B                                                            | Receives the request (re-dispatched)                 |        |
| 5   | Driver A reopens app                                                | No stale incoming request visible                    |        |
| 6   | Driver B accepts                                                    | Rider sees "Driver matched"                          |        |

**Edge case — Driver A reconnects within 35s:**

| #   | Step                                              | Expected Output                                         | Result |
| --- | ------------------------------------------------- | ------------------------------------------------------- | ------ |
| E1  | Driver A's app is killed then reopened before 35s | Request may still be visible; Driver A can still accept |        |
| E2  | Driver A accepts before timeout fires             | Normal acceptance flow                                  |        |

**Log to watch (queue worker terminal):**

```
Processing job: HandleRequestTimeout
[job should only act if current_driver_id still matches Driver A]
```

**Notes / Observations:**

```
Passed. HandleRequestTimeout fired at ~35s as expected.
Driver B received re-dispatched request; Driver A had no stale request on reopen.
Driver B accepted; rider saw "Driver matched" correctly.

Edge case (Driver A reconnects within 35s):
- App killed and reopened before 35s — incoming request was still visible on reopen.
- Driver A accepted before timeout fired; normal acceptance flow completed.
```

**Test Result:** ✅

---

## H-3: Rider Cancel Cooldown

> Previously silently broken — `rider_cooldown_until` was dropped by `update()`.
> **Requires:** Rider (Driver optional)
>
> **Note (2026-03-02):** `rider_cooldown_until` is now included in the `ride.request.cancelled`
> WebSocket broadcast payload, so the mobile client receives the cooldown timestamp via WS
> (not just via API response). Both paths should surface the same cooldown. Test both.

| #   | Step                                                                                    | Expected Output                                       | Result |
| --- | --------------------------------------------------------------------------------------- | ----------------------------------------------------- | ------ |
| 1   | Rider submits a request                                                                 | Waiting screen shows "Finding Driver"                 |        |
| 2   | Rider taps **Cancel** → **confirms** in the dialog ("Yes, Cancel")                     | Cancel succeeds; rider auto-navigated to home         |        |
| 3   | Rider tries to submit a new request immediately                                         | **Error shown: ~60 second cooldown**                  |        |
| 4   | Wait 60 seconds, try again                                                              | Request goes through normally                         |        |

**Edge case — expiry-based cooldown:**

| #   | Step                                     | Expected Output                                                                      | Result |
| --- | ---------------------------------------- | ------------------------------------------------------------------------------------ | ------ |
| E1  | No drivers online; Rider submits request | Waiting screen eventually shows "No Drivers Available" countdown (~60s)              |        |
| E2  | Countdown hits 0                         | Rider auto-navigated to home (either countdown safety-net or WS expiry event)        |        |
| E3  | Rider tries to submit again immediately  | Same **60s cooldown error** (delivered via WS `rider_cooldown_until` or API)         |        |

**Notes / Observations:**

```
(write here)
```

**Test Result:** ⬜

---

## H-4: No-Drivers Countdown on Waiting Screen

> When all drivers decline or none are online, the rider's waiting screen shows a countdown
> inline — no separate screen.  Also tests re-dispatch when a driver joins mid-countdown.
> **Requires:** Rider only (ensure no drivers are online OR all online drivers will decline)

| #   | Step                                                            | Expected Output                                                           | Result |
| --- | --------------------------------------------------------------- | ------------------------------------------------------------------------- | ------ |
| 1   | No drivers online; Rider submits a request                      | Waiting screen shows "Finding Driver" + spinning icon                     |        |
| 2   | Server broadcasts `ride.no_drivers_available` (fires on decline/no-match) | Title stays "Finding Driver"; icon greys out; text changes to **"No drivers available right now"** + **"Retrying in Ns…"** |        |
| 3   | Countdown ticks                                                 | Inline "Retrying in Ns…" text decrements every second                    |        |
| 4   | Countdown reaches 0                                             | Rider is **auto-navigated to home screen** (safety-net path)              |        |
| 5   | Alternatively: WS `ride.request.cancelled` arrives before countdown hits 0 | Rider is navigated home immediately; countdown cancelled | |
| 6   | **New:** Driver comes online mid-countdown                      | Countdown clears; icon becomes active; text reverts to "Finding a driver for you…"; driver receives dispatch | |

**Edge case — WS event missed (no network at countdown end):**

| #   | Step                                                  | Expected Output                                                           | Result |
| --- | ----------------------------------------------------- | ------------------------------------------------------------------------- | ------ |
| E1  | Airplane mode during countdown                        | Countdown still ticks (local timer is independent of WS)                  |        |
| E2  | Countdown hits 0 while offline                        | Rider navigated to home via safety-net; no crash                          |        |

**Log to watch (queue worker terminal):**

```
[broadcast] ride.no_drivers_available → {ride_request_id, countdown_seconds: 60}
Processing job: ExpireRideRequest (fires 60s after no-drivers broadcast)
[broadcast] ride.request.cancelled on private-user.{rider_id} with rider_cooldown_until
```

**Notes / Observations:**

```
(write here)
```

**Test Result:** ⬜

---

## M-1: Driver State Desync on App Reopen

> Bug: driver was online on backend, but UI showed "Offline" after app reopen.
> **Requires:** Driver A

| #   | Step                                                               | Expected Output                                 | Result |
| --- | ------------------------------------------------------------------ | ----------------------------------------------- | ------ |
| 1   | Driver goes **Online**                                             | Queue position visible; UI shows "Online"       |        |
| 2   | **Force-kill** the driver app                                      |                                                 |        |
| 3   | Reopen the app                                                     | UI immediately shows **Online** (not "Offline") |        |
| 4   | Put app in background for **5+ minutes**, then bring to foreground | UI still shows **Online** after resume          |        |

**Edge case — offline on reopen:**

| #   | Step                                          | Expected Output                | Result |
| --- | --------------------------------------------- | ------------------------------ | ------ |
| E1  | Driver goes **Offline**, then force-kills app |                                |        |
| E2  | Reopen app                                    | UI correctly shows **Offline** |        |

**Notes / Observations:**

```
(write here)
```

**Test Result:** ⬜

---

## M-2: Single Session Enforcement

> Same driver account active on 2 devices simultaneously should not be allowed.
> **Requires:** Driver A on 2 physical devices (Device A1, Device A2)

| #   | Step                                                 | Expected Output                                                                     | Result |
| --- | ---------------------------------------------------- | ----------------------------------------------------------------------------------- | ------ |
| 1   | Driver logs into **Device A1**, goes **Online**      | Device A1 active                                                                    |        |
| 2   | Driver logs into **Device A2** with same credentials | Login succeeds                                                                      |        |
| 3   | Driver taps **Go Online** on **Device A2**           |                                                                                     |        |
| 4   | **Device A1**                                        | Receives `session.replaced` event → **auto signs out** → redirected to login screen |        |
| 5   | **Device A2**                                        | Goes online successfully; queue position shows                                      |        |

**Edge case — Device A1 has no network when A2 goes online:**

| #   | Step                                                          | Expected Output                               | Result |
| --- | ------------------------------------------------------------- | --------------------------------------------- | ------ |
| E1  | Device A1 goes offline (airplane mode), Device A2 goes online | Device A1 token revoked silently              |        |
| E2  | Device A1 comes back online and makes any API call            | Gets **401** → 401 handler redirects to login |        |

**Notes / Observations:**

```
(write here)
```

**Test Result:** ⬜

---

## L-1: Global 401 Handler Mid-Session

> Token invalidated mid-session should redirect to login, not show silent errors.
> **Requires:** Driver or Rider + DB access

| #   | Step                                                                                                  | Expected Output                                                  | Result |
| --- | ----------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------- | ------ |
| 1   | Driver is active in app (logged in, online)                                                           | Normal state                                                     |        |
| 2   | Manually delete the token in DB: `DELETE FROM personal_access_tokens WHERE tokenable_id = {user_id};` |                                                                  |        |
| 3   | Trigger any API action in the app (tap anything that calls the API)                                   | App redirects to **login screen** (not a generic error or crash) |        |

**Notes / Observations:**

```
(write here)
```

**Test Result:** ⬜

---

## L-2: Stale Screen After Rider Cancels

> Driver's incoming request screen should auto-dismiss when rider cancels.
> **Requires:** Driver A, Rider

| #   | Step                                   | Expected Output                                                    | Result |
| --- | -------------------------------------- | ------------------------------------------------------------------ | ------ |
| 1   | Rider submits request                  | Driver A receives incoming request card                            |        |
| 2   | Rider taps **Cancel** → confirms dialog |                                                                   |        |
| 3   | Driver A's screen                      | Incoming request card **auto-dismisses** (no manual action needed) |        |

**Edge case — race condition:**

| #   | Step                                                          | Expected Output                                                                                                | Result |
| --- | ------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------- | ------ |
| E1  | Rider cancels at exactly the same moment Driver A taps Accept | One wins; if Accept resolves first, rider is notified of match; if Cancel resolves first, driver gets an error |        |

**Notes / Observations:**

```
(write here)
```

**Test Result:** ⬜

---

## L-3: Info Popup on Admin Cancel

> Admin cancels a ride mid-trip → driver and rider should see an informational dialog with the reason.
> **Requires:** Driver A, Rider, Admin API access

| #   | Step                                                                                      | Expected Output                                        | Result |
| --- | ----------------------------------------------------------------------------------------- | ------------------------------------------------------ | ------ |
| 1   | Active ride in progress (in_progress status)                                              |                                                        |        |
| 2   | Admin calls `POST /api/admin/rides/{id}/cancel` with `{ "reason": "Test cancel reason" }` |                                                        |        |
| 3   | **Driver app**                                                                            | Shows **AlertDialog** with admin's cancellation reason |        |
| 4   | **Rider app**                                                                             | Shows **AlertDialog** with cancellation reason         |        |
| 5   | Both dismiss the dialog                                                                   | Both return to home screen / idle state                |        |

**Edge case — rider self-cancel (no popup expected):**

| #   | Step                                           | Expected Output                                                      | Result |
| --- | ---------------------------------------------- | -------------------------------------------------------------------- | ------ |
| E1  | Rider taps **Cancel** on their own active ride |                                                                      |        |
| E2  | Rider's screen                                 | Popup is **NOT shown** on rider's side (flag `_isCancelling = true`) |        |
| E3  | Driver's screen                                | Driver **does** see cancellation info from rider                     |        |

**Notes / Observations:**

```
(write here)
```

**Test Result:** ⬜

---

## Bugs Found During Testing

| #   | Test | Description | Severity | Fixed? |
| --- | ---- | ----------- | -------- | ------ |
| 1   | F-1  | `QUEUE_CONNECTION=sync` caused `HandleRequestTimeout` to fire immediately (delay ignored), expiring every ride request on creation | Critical | ✅ Changed to `redis`, added `REDIS_CLIENT=predis` |
| 2   | F-1  | Both drivers received the ride request simultaneously — stale screen not dismissed on re-dispatch. `acceptRideRequest()` also had no `current_driver_id` guard, letting any driver accept | High | ✅ Added `current_driver_id` check in `RideService`; `handleDeclineOrTimeout()` now broadcasts `ride.request.cancelled` to previous driver after re-dispatch |
| 3   | F-1  | Black screen after tapping Accept or Decline — double `Navigator.pop()` from `_clearIncomingRequest()` triggering `ref.listen` while screen also called pop/pushReplacement. Decline had no try/catch, leaving screen frozen on network error | High | ✅ Added `_isDismissing` flag to `ride_request_screen.dart`; added try/catch to `_declineRide()` and `_autoDecline()` |
| 4   | F-1  | Rider could not submit new request after request expired — `_fetchMatchViaApi()` only cleared state for `cancelled`, not `expired`. Also `RideRequestStatus` enum was missing `completed` case (parsed silently as `pending`) | High | ✅ Added `expired` + `completed` to terminal status check; added `completed` to `RideRequestStatus` enum and `_parseStatus()` |
| 5   | F-1  | Queue position not updating for other drivers when a driver joins/leaves — `broadcastQueuePosition()` only notified the driver whose status changed | Medium | ✅ Added `broadcastAllQueuePositions()` to `MatchingQueueService`; called on all queue mutations |
| 6   | F-1  | Accepting driver stayed at position 1 throughout entire ride — other drivers could not move up until the ride completed | Medium | ✅ `RideController::accept()` now calls `removeFromQueue()` immediately after ride is created |
| 7   | H-1  | Nearest-first matching not working — driver home screen never sent location updates while idle; all drivers had stale coordinates from last active ride | High | ✅ Added 30s periodic idle location update timer to `driver_home_screen.dart` |
| 8   | H-1  | Rider kicked to home screen when driver declined (re-dispatch) — `RideRequestCancelled` always broadcast to rider channel even for re-dispatch dismissals | High | ✅ Added `notifyRider` flag to `RideRequestCancelled`; `handleDeclineOrTimeout` passes `notifyRider: false` |
| 9   | H-4  | No countdown shown when rider submits request with zero drivers online — `RequestController` only logged, never called `handleNoDriversFound()` | High | ✅ Added `handleNoDriversFound()` call to `RequestController` else branch |

---

## Changes Committed This Session (2026-03-02)

| File | Change | Affects |
| ---- | ------ | ------- |
| `MatchingQueueService.php` | Nearest-first matching (top-5 FIFO pool, closest wins). `TIEBREAKER_DISTANCE_METERS=50` | H-1 |
| `MatchingQueueService.php` | `addToQueue()` calls `tryDispatchPendingRequest()` — re-dispatches unmatched requests to newly-joined driver | H-4 step 6 |
| `MatchingQueueService.php` | Extracted `handleNoDriversFound()` public method; called from both `handleDeclineOrTimeout` and `RequestController` | H-4 step 1 (zero-driver immediate countdown) |
| `RequestController.php` | `else` branch on no driver found now calls `handleNoDriversFound()` — rider sees countdown immediately on zero-driver requests | H-4, H-3 edge case |
| `ExpireRideRequest.php` | Defers expiry by 60s if `current_driver_id` is set (new driver dispatched during countdown) | H-4 step 6 |
| `RideSearchResumed.php` | New event — broadcasts `ride.search.resumed` to rider when search restarts | H-4 step 6 |
| `RideRequestCancelled.php` | `notifyRider` flag — re-dispatch does not broadcast to rider channel | H-1 edge case (rider stays on waiting screen) |
| `waiting_screen.dart` | Unified screen — no separate "No Drivers Available" view; countdown shown inline; title always "Finding Driver" | H-3, H-4 |
| `ride_request_provider.dart` | `noDriversAvailableUntil` state; `resumeSearch()` clears it; `onSearchResumed` WS callback | H-4 |
| `websocket_service.dart` | `onNoDriversAvailable`, `onRequestExpired`, `onSearchResumed` callbacks in `subscribeToUserChannel` | H-4 |
| `driver_home_screen.dart` | 30s idle location update timer — drivers send location while online and not in active ride | Nearest-first accuracy |

---

## Sign-off

- [ ] All HIGH priority tests pass
- [ ] All MEDIUM priority tests pass
- [ ] All LOW priority tests pass (or deferred with justification)
- [ ] No regressions observed on full happy path
- [ ] Pending uncommitted changes committed before merge
- [ ] Ready to merge `fix/todo-issues` → `dev`

**Tested by:** **\*\***\_\_\_**\*\***
**Date:** **\*\***\_\_\_**\*\***
