# Device Test Log — `fix/todo-issues`

> **Branch:** `fix/todo-issues`
> **Date:** 2026-03-01 (F-1 tested); remaining tests pending
> **Devices:** Pixel 6 emulator (Driver 1), Pixel 6 emulator (Driver 2), Realme 13+ (Rider)
> **Build flavors:** `flutter run --flavor driver` / `flutter run --flavor rider`

---

## Status Summary

| Test                                                                                                 | Status        |
| ---------------------------------------------------------------------------------------------------- | ------------- |
| [F-1 Full Happy Path](#f-1-full-happy-path)                                                          | ✅ Pass       |
| [H-1 FIFO Re-dispatch After Decline](#h-1-fifo-re-dispatch-after-decline)                            | ✅ Pass       |
| [H-2 Re-dispatch After App Kill (Timeout)](#h-2-re-dispatch-after-app-kill-timeout)                  | ✅ Pass       |
| [H-3 Rider Cancel Cooldown](#h-3-rider-cancel-cooldown)                                              | ✅ Pass       |
| [H-4 No-Drivers Countdown on Waiting Screen](#h-4-no-drivers-countdown-on-waiting-screen)            | ✅ Pass       |
| [M-1 Driver Kicked Offline on Crash/Force-Quit](#m-1-driver-kicked-offline-on-app-crash--force-quit) | ⚠️ Partial    |
| [M-2 Single Session Enforcement (Kick at Login)](#m-2-single-session-enforcement-kick-at-login-time) | ✅ Pass       |
| [L-1 Global 401 Handler](#l-1-global-401-handler-mid-session)                                        | ✅ Pass       |
| [L-2 Stale Screen After Rider Cancels](#l-2-stale-screen-after-rider-cancels)                        | ✅ Pass        |
| [L-3 Info Popup on Admin Cancel](#l-3-info-popup-on-admin-cancel)                                    | ⏭️ Deferred   |

> **Status key:** ⬜ Not tested · ✅ Pass · ❌ Fail · ⚠️ Partial

---

## Key Numbers

| Value         | What                                                                  |
| ------------- | --------------------------------------------------------------------- |
| 35s           | Server-side timeout (`HandleRequestTimeout` job)                      |
| ~30s          | Mobile UI countdown timer on driver's incoming request screen         |
| 60s           | No-drivers countdown before request expires (shown on waiting screen) |
| 60s           | Rider cooldown after cancel or request expiry                         |
| 5             | FIFO candidate pool size (nearest-first selection picks from top 5)   |
| 50 m          | Distance tiebreaker bucket — same bucket → longer-waiting wins        |
| 15 min        | Driver penalty cooldown (6+ declines in window)                       |
| 5 declines    | Driver moved to bottom of queue                                       |
| 6 declines    | Driver suspended for 15 min                                           |
| 15 min window | Decline count resets if no new decline for 15 min                     |

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

| #   | Step                                     | Expected Output                                                                                                           | Result |
| --- | ---------------------------------------- | ------------------------------------------------------------------------------------------------------------------------- | ------ |
| E1  | Only Driver A online; Driver A declines  | No next driver available                                                                                                  |        |
| E2  | Rider's waiting screen                   | Title stays "Finding Driver"; icon greys out; text changes to "No drivers available right now" + "Retrying in Ns…" inline |        |
| E3  | Countdown reaches 0 (or WS expiry fires) | Rider auto-navigated to home screen                                                                                       |        |
| E4  | Rider tries to submit again immediately  | **60s cooldown error** shown (cooldown delivered via WS payload + API)                                                    |        |

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

| #   | Step                                                               | Expected Output                               | Result |
| --- | ------------------------------------------------------------------ | --------------------------------------------- | ------ |
| 1   | Rider submits a request                                            | Waiting screen shows "Finding Driver"         |        |
| 2   | Rider taps **Cancel** → **confirms** in the dialog ("Yes, Cancel") | Cancel succeeds; rider auto-navigated to home |        |
| 3   | Rider tries to submit a new request immediately                    | **Error shown: ~60 second cooldown**          |        |
| 4   | Wait 60 seconds, try again                                         | Request goes through normally                 |        |

**Edge case — expiry-based cooldown:**

| #   | Step                                     | Expected Output                                                               | Result |
| --- | ---------------------------------------- | ----------------------------------------------------------------------------- | ------ |
| E1  | No drivers online; Rider submits request | Waiting screen eventually shows "No Drivers Available" countdown (~60s)       |        |
| E2  | Countdown hits 0                         | Rider auto-navigated to home (either countdown safety-net or WS expiry event) |        |
| E3  | Rider tries to submit again immediately  | Same **60s cooldown error** (delivered via WS `rider_cooldown_until` or API)  |        |

**Notes / Observations:**

```
Passed. Cancel cooldown enforced correctly on both paths (rider cancel + expiry).
Cooldown delivered via WS payload (rider_cooldown_until) and confirmed via API.

UX observation (not yet implemented): cooldown error currently shows a static message
with no indication of how long remains. A countdown timer showing seconds until the
rider can request again would improve the experience.
```

**Test Result:** ✅

---

## H-4: No-Drivers Countdown on Waiting Screen

> When all drivers decline or none are online, the rider's waiting screen shows a countdown
> inline — no separate screen. Also tests re-dispatch when a driver joins mid-countdown.
> **Requires:** Rider only (ensure no drivers are online OR all online drivers will decline)

| #   | Step                                                                       | Expected Output                                                                                                            | Result |
| --- | -------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- | ------ |
| 1   | No drivers online; Rider submits a request                                 | Waiting screen shows "Finding Driver" + spinning icon                                                                      |        |
| 2   | Server broadcasts `ride.no_drivers_available` (fires on decline/no-match)  | Title stays "Finding Driver"; icon greys out; text changes to **"No drivers available right now"** + **"Retrying in Ns…"** |        |
| 3   | Countdown ticks                                                            | Inline "Retrying in Ns…" text decrements every second                                                                      |        |
| 4   | Countdown reaches 0                                                        | Rider is **auto-navigated to home screen** (safety-net path)                                                               |        |
| 5   | Alternatively: WS `ride.request.cancelled` arrives before countdown hits 0 | Rider is navigated home immediately; countdown cancelled                                                                   |        |
| 6   | **New:** Driver comes online mid-countdown                                 | Countdown clears; icon becomes active; text reverts to "Finding a driver for you…"; driver receives dispatch               |        |

**Edge case — WS event missed (no network at countdown end):**

| #   | Step                           | Expected Output                                          | Result |
| --- | ------------------------------ | -------------------------------------------------------- | ------ |
| E1  | Airplane mode during countdown | Countdown still ticks (local timer is independent of WS) |        |
| E2  | Countdown hits 0 while offline | Rider navigated to home via safety-net; no crash         |        |

**Log to watch (queue worker terminal):**

```
[broadcast] ride.no_drivers_available → {ride_request_id, countdown_seconds: 60}
Processing job: ExpireRideRequest (fires 60s after no-drivers broadcast)
[broadcast] ride.request.cancelled on private-user.{rider_id} with rider_cooldown_until
```

**Notes / Observations:**

```
Passed. All main steps confirmed as expected.
- Waiting screen stayed on unified "Finding Driver" title throughout.
- Icon greyed out and "No drivers available right now" + "Retrying in Ns…" appeared inline on no-driver broadcast.
- Countdown ticked down correctly each second.
- Countdown reached 0; rider auto-navigated to home via safety-net.
- Driver joining mid-countdown: countdown cleared, icon became active, text reverted to
  "Finding a driver for you…", and driver received dispatch correctly.

Edge case (airplane mode / no network at countdown end): NOT tested — airplane mode on
device does not cut connection to local dev server, so this edge case is not reproducible
in the current test environment. Deferred.
```

**Test Result:** ✅

---

## M-1: Driver Kicked Offline on App Crash / Force-Quit

> When a driver force-quits (or crashes), the backend should remove them from the
> queue and mark them offline.
>
> **Primary mechanism — `KickStaleDrivers` heartbeat (~90–150 s):**
> The idle-location timer in `driver_home_screen.dart` fires every 30 s while the
> driver is online and not in an active ride, updating `last_location_update`.
> `KickStaleDrivers` (scheduled every 1 min) kicks any queued driver whose
> `last_location_update` is older than 90 s.
>
> **Mobile safety net — `kickOfflineOnLaunch()` (immediate on reopen):**
> On cold app launch, if the backend still shows the driver online with no active ride,
> the app calls `POST /driver/offline` immediately and shows the driver as Offline.
> Driver must tap Go Online again. Closes the UX gap where a driver reopens before
> KickStaleDrivers has run.
>
> **Note — Reverb `channel_vacated` webhook (investigated and removed):**
> A webhook-based instant-kick was implemented and investigated on physical device.
> Reverb v1.6.0 source inspection confirmed: zero occurrences of "webhook" or
> "channel_vacated" in vendor source — Reverb does not implement Pusher-style webhook
> delivery. The `webhooks` config key is silently ignored. 0/10 webhook kicks observed.
> All webhook code removed. Deferred — revisit if a future Reverb version adds support.
>
> **Requires:** Driver A

| #   | Step                                            | Expected Output                                                              | Result |
| --- | ----------------------------------------------- | ---------------------------------------------------------------------------- | ------ |
| 1   | Driver goes **Online**                          | Queue position visible; UI shows "Online"                                    |        |
| 2   | **Force-kill** the driver app                   | Backend still shows driver online; KickStaleDrivers clears within ~90–150 s  |        |
| 3   | Reopen the driver app **before** kick runs      | `kickOfflineOnLaunch()` fires → UI shows **Offline** immediately             |        |
| 4   | Driver taps **Go Online** again                 | Rejoins queue at the back; queue position assigned                           |        |
| 5   | Force-kill and wait **>150 s** before reopening | KickStaleDrivers log shows kick; backend shows offline on reopen             |        |

**Edge case — driver in active ride when app crashes:**

| #   | Step                                                 | Expected Output                                                              | Result |
| --- | ---------------------------------------------------- | ---------------------------------------------------------------------------- | ------ |
| E4  | Driver accepts a ride, then force-kills app mid-ride | `kickOfflineOnLaunch()` skips kick (active ride detected); ride continues    |        |
| E5  | Reopen the app                                       | `syncFromBackend()` restores active ride state; ride continues               |        |

**Log to watch (scheduler terminal):**

```
[Illuminate\Console\Scheduling\Event] Running scheduled command: drivers:kick-stale --threshold=90
[KICKING] driver_id=X  last_heartbeat=NNs ago
KickStaleDrivers: driver force-kicked offline {"driver_id":X, ...}
```

**Notes / Observations:**

```
Tested 2026-03-03 on physical device. 10 force-kill + reopen cycles.

Reverb channel_vacated webhook (investigated and removed):
- Webhook was implemented but had never fired. Two root causes found during investigation:
  1. APP_URL=http://localhost (no port) → webhook POSTed to port 80 → conn refused.
  2. After URL fix, still 0/10 webhook kicks. Reverb v1.6.0 source inspection confirmed:
     zero occurrences of "webhook" or "channel_vacated" in vendor source.
     Reverb simply does not implement Pusher-style webhook delivery.
- The 1/10 apparent success observed was kickOfflineOnLaunch() (mobile fix), not webhook.
- All webhook code removed: ReverbWebhookController.php, route, config block, .env key.

KickStaleDrivers heartbeat:
- Consistent ~90–150s detection across all 10 runs. Kick log confirmed every run.
- Sole reliable backend detection mechanism.

kickOfflineOnLaunch() (mobile):
- On cold launch, if backend shows online with no active ride: calls POST /driver/offline,
  sets local state to offline immediately. Driver must tap Go Online again.
- Closes the gap between force-quit and the KickStaleDrivers detection window.
```

**Test Result:** ⚠️ Partial — no instant backend kick on force-quit (Reverb webhooks unsupported). KickStaleDrivers clears within ~90–150 s consistently. `kickOfflineOnLaunch()` ensures correct UI on reopen. Acceptable for current phase.

---

## M-2: Single Session Enforcement (Kick at Login Time)

> Same driver account logged in on 2 devices simultaneously is not allowed.
> Session kick now happens at **login**, not at "Go Online".
> When Device A2 authenticates, Device A1 is immediately kicked offline and signed out.
> Device A2 starts in the **Offline** state — driver must tap "Go Online" explicitly.
> **Requires:** Driver A on 2 physical devices (Device A1, Device A2)

| #   | Step                                                 | Expected Output                                                                                                            | Result |
| --- | ---------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- | ------ |
| 1   | Driver logs into **Device A1**, taps **Go Online**   | Device A1 online; queue position shows                                                                                     |        |
| 2   | Driver logs into **Device A2** with same credentials | Login API call kicks Device A1: `removeFromQueue`, `went_online_at = null`, `SessionReplaced` broadcast, old token deleted |        |
| 3   | **Device A1** (while still open)                     | Receives `session.replaced` WS event → **auto signs out** → redirected to login screen                                     |        |
| 4   | **Device A2** immediately after login                | Starts as **Offline** — no queue position shown; must tap "Go Online"                                                      |        |
| 5   | Driver taps **Go Online** on Device A2               | Goes online normally; queue position assigned from back of queue                                                           |        |

**Edge case — Device A1 has no network when A2 logs in:**

| #   | Step                                                   | Expected Output                                                                                         | Result |
| --- | ------------------------------------------------------ | ------------------------------------------------------------------------------------------------------- | ------ |
| E1  | Device A1 has no network; Driver A logs into Device A2 | `SessionReplaced` broadcast fires but A1 can't receive it; token revoked server-side | Skipped (local server) |
| E2  | Device A1 comes back online and makes any API call     | Gets **401** → global 401 handler redirects to login                                                    |        |

**Edge case — Driver A1 was offline (idle on home screen) when A2 logs in:**

| #   | Step                                                         | Expected Output                                                                                 | Result |
| --- | ------------------------------------------------------------ | ----------------------------------------------------------------------------------------------- | ------ |
| E3  | Driver A1 is offline (idle on home screen); Driver A2 logs in | `SessionReplaced` always broadcast now (not gated on isOnline); A1 receives it → auto signs out | ✅     |
| E4  | Device A2 starts as **Offline**, taps "Go Online"            | Goes online normally                                                                            | ✅     |

**Log to watch (backend):**

```
AuthController: old driver sessions revoked on new login  {"driver_id": X}
AuthController: driver kicked offline on new login  {"driver_id": X}  ← only if was online
```

**Notes / Observations:**

```
Main test (steps 1–5): passed as expected.
E1: skipped — local dev server, cannot isolate device network.
E3/E4: passed. Required two fixes:
  1. Backend (AuthController): moved SessionReplaced broadcast outside the isOnline
     guard — it now always fires when a new login invalidates old tokens, regardless
     of whether the old device was online or idle.
  2. Mobile (driver_status_provider): syncFromBackend(offline) now also calls
     _subscribeToRideRequests(), so the driver channel is subscribed as soon as
     the app loads — not only when the driver taps Go Online. Without this,
     session.replaced had no listener on A1 while it was idle/offline.
     WebSocketService already guards against double-subscription, so the channel
     is only opened once and stays alive through offline↔online transitions.
Note: if A1's app is fully closed, the WS event won't reach it — it will be redirected
  to login on next API call via the 401 handler (existing behavior, confirmed working).
```

**Test Result:** ✅

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
Tested 2026-03-03 on physical device. Passed as expected.
Deleted token from personal_access_tokens where tokenable_id = {user_id}.
Next API action in app triggered 401 → global handler redirected to login screen.
No generic error shown, no crash.
```

**Test Result:** ✅

---

## L-2: Stale Screen After Rider Cancels

> Driver's incoming request screen should auto-dismiss when rider cancels.
> **Requires:** Driver A, Rider

| #   | Step                                    | Expected Output                                                    | Result |
| --- | --------------------------------------- | ------------------------------------------------------------------ | ------ |
| 1   | Rider submits request                   | Driver A receives incoming request card                            |        |
| 2   | Rider taps **Cancel** → confirms dialog |                                                                    |        |
| 3   | Driver A's screen                       | Incoming request card **auto-dismisses** (no manual action needed) |        |

**Edge case — race condition:**

| #   | Step                                                          | Expected Output                                                                                                | Result |
| --- | ------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------- | ------ |
| E1  | Rider cancels at exactly the same moment Driver A taps Accept | One wins; if Accept resolves first, rider is notified of match; if Cancel resolves first, driver gets an error |        |

**Notes / Observations:**

```
Tested 2026-03-03 on physical device.

Main test (steps 1–3): passed. Incoming request card auto-dismissed on driver's screen
when rider cancelled. No manual action required.

Edge case — race condition (rider cancels + driver taps Accept simultaneously):
Two bugs found and fixed before pass:
  1. Wrong error message: driver saw "another driver already accepted" instead of
     "cancelled by the rider". Root cause: RideService used a single lockForUpdate()
     query that returned null for both cancelled AND accepted-by-another — no distinction.
     Fix: added secondary status lookup after null result → throws 410 for cancelled,
     409 for accepted-by-another. RideController mapped 410 → HTTP 410.
  2. Black screen after error: Future.delayed(2s, Navigator.pop) fired against the
     wrong route when the WS event popped RideRequestScreen before the API response
     returned. Fix: removed delayed pop entirely; pop immediately in catch block after
     setting _isDismissing = true synchronously. _parseError now uses ApiException
     type directly (statusCode field) rather than string matching on toString().
After fixes: cancel wins → driver sees correct snackbar "cancelled by the rider" and
returns to home screen cleanly. Accept wins → rider notified of match as expected.
```

**Test Result:** ✅

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
Deferred — all admin-facing tests are being grouped and will run once admin
functionality is fully implemented.
```

**Test Result:** ⏭️ Deferred

---

## Bugs Found During Testing

| #   | Test | Description                                                                                                                                                                                                                                   | Severity | Fixed?                                                                                                                                                       |
| --- | ---- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| 1   | F-1  | `QUEUE_CONNECTION=sync` caused `HandleRequestTimeout` to fire immediately (delay ignored), expiring every ride request on creation                                                                                                            | Critical | ✅ Changed to `redis`, added `REDIS_CLIENT=predis`                                                                                                           |
| 2   | F-1  | Both drivers received the ride request simultaneously — stale screen not dismissed on re-dispatch. `acceptRideRequest()` also had no `current_driver_id` guard, letting any driver accept                                                     | High     | ✅ Added `current_driver_id` check in `RideService`; `handleDeclineOrTimeout()` now broadcasts `ride.request.cancelled` to previous driver after re-dispatch |
| 3   | F-1  | Black screen after tapping Accept or Decline — double `Navigator.pop()` from `_clearIncomingRequest()` triggering `ref.listen` while screen also called pop/pushReplacement. Decline had no try/catch, leaving screen frozen on network error | High     | ✅ Added `_isDismissing` flag to `ride_request_screen.dart`; added try/catch to `_declineRide()` and `_autoDecline()`                                        |
| 4   | F-1  | Rider could not submit new request after request expired — `_fetchMatchViaApi()` only cleared state for `cancelled`, not `expired`. Also `RideRequestStatus` enum was missing `completed` case (parsed silently as `pending`)                 | High     | ✅ Added `expired` + `completed` to terminal status check; added `completed` to `RideRequestStatus` enum and `_parseStatus()`                                |
| 5   | F-1  | Queue position not updating for other drivers when a driver joins/leaves — `broadcastQueuePosition()` only notified the driver whose status changed                                                                                           | Medium   | ✅ Added `broadcastAllQueuePositions()` to `MatchingQueueService`; called on all queue mutations                                                             |
| 6   | F-1  | Accepting driver stayed at position 1 throughout entire ride — other drivers could not move up until the ride completed                                                                                                                       | Medium   | ✅ `RideController::accept()` now calls `removeFromQueue()` immediately after ride is created                                                                |
| 7   | H-1  | Nearest-first matching not working — driver home screen never sent location updates while idle; all drivers had stale coordinates from last active ride                                                                                       | High     | ✅ Added 30s periodic idle location update timer to `driver_home_screen.dart`                                                                                |
| 8   | H-1  | Rider kicked to home screen when driver declined (re-dispatch) — `RideRequestCancelled` always broadcast to rider channel even for re-dispatch dismissals                                                                                     | High     | ✅ Added `notifyRider` flag to `RideRequestCancelled`; `handleDeclineOrTimeout` passes `notifyRider: false`                                                  |
| 9   | H-4  | No countdown shown when rider submits request with zero drivers online — `RequestController` only logged, never called `handleNoDriversFound()`                                                                                               | High     | ✅ Added `handleNoDriversFound()` call to `RequestController` else branch                                                                                    |
| 10  | M-1  | Force-quit left driver online for up to 2–3 minutes — `KickStaleDrivers` was the only removal path (90 s threshold + scheduler lag). Reverb webhook attempted but Reverb v1.6.0 does not implement webhook delivery (source confirmed, 0/10 on physical device). Webhook code removed. | High | ✅ `kickOfflineOnLaunch()` added to mobile — driver always starts offline on cold launch, calls `POST /driver/offline` immediately. KickStaleDrivers remains sole backend path (~90–150 s). |
| 11  | —    | Two drivers joining the queue at the same millisecond both showed queue position #1 — `getQueuePosition()` used strict `<` on `queue_joined_at`; same-timestamp entries counted zero drivers ahead of themselves                            | Medium   | ✅ Added `user_id` tiebreaker to `getQueuePosition()` — same-timestamp drivers are ordered deterministically by ID                                           |
| 12  | L-2  | Race condition: rider cancels while driver taps Accept — driver saw "already accepted by another driver" instead of "cancelled by the rider". Root cause: `lockForUpdate().where('status','pending')` returns null for both cancelled and accepted-by-another with no distinction | High | ✅ Added secondary status lookup in `RideService::acceptRideRequest()` — throws 410 for cancelled/expired, 409 for accepted-by-another. `RideController` maps 410 → HTTP 410 |
| 13  | L-2  | Black screen after race condition error — `Future.delayed(2s, Navigator.pop)` fired against `DriverHomeScreen` when WS event had already popped `RideRequestScreen` before the API response returned, and `mounted` was still true on the disposed widget | High | ✅ Removed delayed pop entirely; pop immediately in catch block. `_isDismissing = true` set synchronously before any async work. `_parseError` now uses `ApiException.statusCode` directly |

---

## Changes Committed This Session (2026-03-02)

| File                         | Change                                                                                                                         | Affects                                       |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------- |
| `MatchingQueueService.php`   | Nearest-first matching (top-5 FIFO pool, closest wins). `TIEBREAKER_DISTANCE_METERS=50`                                        | H-1                                           |
| `MatchingQueueService.php`   | `addToQueue()` calls `tryDispatchPendingRequest()` — re-dispatches unmatched requests to newly-joined driver                   | H-4 step 6                                    |
| `MatchingQueueService.php`   | Extracted `handleNoDriversFound()` public method; called from both `handleDeclineOrTimeout` and `RequestController`            | H-4 step 1 (zero-driver immediate countdown)  |
| `RequestController.php`      | `else` branch on no driver found now calls `handleNoDriversFound()` — rider sees countdown immediately on zero-driver requests | H-4, H-3 edge case                            |
| `ExpireRideRequest.php`      | Defers expiry by 60s if `current_driver_id` is set (new driver dispatched during countdown)                                    | H-4 step 6                                    |
| `RideSearchResumed.php`      | New event — broadcasts `ride.search.resumed` to rider when search restarts                                                     | H-4 step 6                                    |
| `RideRequestCancelled.php`   | `notifyRider` flag — re-dispatch does not broadcast to rider channel                                                           | H-1 edge case (rider stays on waiting screen) |
| `waiting_screen.dart`        | Unified screen — no separate "No Drivers Available" view; countdown shown inline; title always "Finding Driver"                | H-3, H-4                                      |
| `ride_request_provider.dart` | `noDriversAvailableUntil` state; `resumeSearch()` clears it; `onSearchResumed` WS callback                                     | H-4                                           |
| `websocket_service.dart`     | `onNoDriversAvailable`, `onRequestExpired`, `onSearchResumed` callbacks in `subscribeToUserChannel`                            | H-4                                           |
| `driver_home_screen.dart`    | 30s idle location update timer — drivers send location while online and not in active ride                                     | Nearest-first accuracy                        |
| `driver_status_provider.dart` | Added `kickOfflineOnLaunch()` — on cold launch with no active ride, calls `POST /driver/offline` and sets local state to offline | M-1 (UX fix on reopen)   |
| `session_check_wrapper.dart`  | `_checkSession()` calls `kickOfflineOnLaunch()` instead of `syncFromBackend(online)` when driver is online with no active ride  | M-1 (UX fix on reopen)   |
| `MatchingQueueService.php`   | `getQueuePosition()`: added `user_id` tiebreaker for same-timestamp entries — prevents two drivers from both seeing #1         | Bug #11                                       |

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
