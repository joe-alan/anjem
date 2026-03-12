# Device Test Log — FCM Push Notifications (`fcm-wiring2`)

> **Branch:** `fcm-wiring2`
> **Date:** \_\_\_\_\_\_\_\_\_\_\_
> **Tester:** \_\_\_\_\_\_\_\_\_\_\_
> **Rider device:** \_\_\_\_\_\_\_\_\_\_\_ (Android \_\_\_)
> **Driver device:** \_\_\_\_\_\_\_\_\_\_\_ (Android \_\_\_)

---

## Status Summary

| Test                                              | Area       | Status        |
| ------------------------------------------------- | ---------- | ------------- |
| FCM-1  Permission & Token Registration            | Setup      | ✅ Pass       |
| FCM-2  Token Refresh                              | Setup      | ⏭️ Deferred   |
| FCM-3  Token Cleared on Logout                    | Setup      | ✅ Pass       |
| FCM-4  New Ride Request — Driver (Foreground)     | Ride flow  | ✅ Pass       |
| FCM-5  New Ride Request — Driver (Background)     | Ride flow  | ✅ Pass       |
| FCM-6  New Ride Request — Driver (Terminated)     | Ride flow  | 🚫 N/A        |
| FCM-7  Ride Accepted — Rider                      | Ride flow  | ✅ Pass       |
| FCM-8  Driver Arrived — Rider                     | Ride flow  | ✅ Pass       |
| FCM-9  Ride Started — Rider                       | Ride flow  | ✅ Pass       |
| FCM-10 Ride Completed — Both                      | Ride flow  | ⚠️ Partial    |
| FCM-11 Ride Cancelled — Both                      | Ride flow  | ✅ Pass       |
| FCM-12 No Drivers Available — Rider               | Ride flow  | ✅ Pass       |
| FCM-13 KYC Approved — Driver                      | KYC        | ✅ Pass       |
| FCM-14 KYC Rejected — Driver                      | KYC        | ✅ Pass       |
| FCM-15 Null FCM Token — Graceful Skip             | Edge case  | ⬜ Not tested |
| FCM-16 Multiple Rapid Notifications               | Edge case  | ⬜ Not tested |
| FCM-17 Sign Out then Sign In — Token Rotation     | Edge case  | ⬜ Not tested |
| FCM-18 Permission Denied — App Still Works        | Edge case  | ⬜ Not tested |
| FCM-19 Network Offline During Token Send          | Edge case  | ⬜ Not tested |
| FCM-E2E Full Happy Path with FCM Active           | E2E        | ⬜ Not tested |

> **Status key:** ⬜ Not tested · ✅ Pass · ❌ Fail · ⚠️ Partial · ⏭️ Deferred · 🚫 N/A

---

## Pre-Test Setup

```bash
# Backend (4 terminals)
php artisan serve
php artisan reverb:start
php artisan queue:work
php artisan schedule:work

# Confirm FCM token column exists
psql anjemme -c "\d users" | grep fcm_token
# Expected: fcm_token | character varying(500) | nullable

# Confirm Firebase credentials are set in .env
grep FIREBASE .env
```

**Flutter builds (always include MAPBOX token):**

```bash
flutter run --flavor rider -t lib/main_rider.dart \
  --dart-define=MAPBOX_ACCESS_TOKEN=pk.eyJ1...

flutter run --flavor driver -t lib/main_driver.dart \
  --dart-define=MAPBOX_ACCESS_TOKEN=pk.eyJ1...
```

**Check FCM token was stored (after login):**

```sql
SELECT id, name, fcm_token IS NOT NULL AS has_token
FROM users
ORDER BY id DESC
LIMIT 5;
-- Rider and driver rows should have has_token = true
```

**Watch backend logs for FCM sends:**

```bash
grep "FCM\|fcm\|Notification" storage/logs/laravel.log | tail -30
```

---

## Setup Tests

### FCM-1: Permission & Token Registration

> Confirms that on first launch after login, the app requests notification permission and
> registers the FCM token with the backend.
>
> **Requires:** Fresh install or cleared app data

| #  | Step                                                                      | Expected                                    | Result |
| -- | ------------------------------------------------------------------------- | ------------------------------------------- | ------ |
| 1  | Clear app data on both rider and driver devices                           | Fresh state                                 |        |
| 2  | Launch rider app and sign in with Google                                  | Permission dialog appears                   |        |
| 3  | Tap **Allow**                                                             | Dialog dismissed                            |        |
| 4  | Check backend log                                                         | `FCM token updated` logged                  |        |
| 5  | Check DB: `SELECT fcm_token IS NOT NULL FROM users WHERE id = <rider_id>` | `true`                                      |        |
| 6  | Repeat steps 2–5 for driver app                                           | Same result                                 |        |

**Notes / Observations:**

```
Tokens registered for users 17, 18, 19. Backend log confirms FCM token updated events.
DB has_token = true for all active users.
```

**Test Result:** ✅ Pass

---

### FCM-2: Token Refresh

> When Firebase rotates the FCM token, the app must re-send the new token to the backend.
>
> **Requires:** Backend log access

| #  | Step                                                                                          | Expected                               | Result |
| -- | --------------------------------------------------------------------------------------------- | -------------------------------------- | ------ |
| 1  | Simulate token refresh (delete + reinstall app, or Firebase Console test message)            | Token refresh event fires              |        |
| 2  | Watch Flutter log                                                                             | `[FCM] Refreshed token sent to backend`|        |
| 3  | Check DB `fcm_token` for the user                                                             | Updated to a new value                 |        |

**Notes / Observations:**

```

```

**Test Result:** ⏭️ Deferred — requires reinstall or Firebase Console access

---

### FCM-3: Token Cleared on Logout

> After sign-out, the FCM token must be deleted from the device and nulled in the DB
> so the signed-out user does not receive further notifications.
>
> **Requires:** Logged-in rider or driver device

| #  | Step                                                                  | Expected              | Result |
| -- | --------------------------------------------------------------------- | --------------------- | ------ |
| 1  | Note current `fcm_token` value in DB for the user                    | Non-null              |        |
| 2  | Sign out from the app                                                 | Returns to login      |        |
| 3  | Check DB: `SELECT fcm_token IS NULL FROM users WHERE id = <id>`       | `true` — token cleared|        |
| 4  | Trigger a ride event that would normally send a push to this user     | No push received      |        |
| 5  | Sign back in                                                          | New token registered  |        |

**Notes / Observations:**

```
User 17 (Jonathan Alano) fcm_token = NULL confirmed in DB immediately after logout.
```

**Test Result:** ✅ Pass

---

## Ride Flow Tests

### FCM-4: New Ride Request — Driver (Foreground)

> When driver app is in foreground and a ride is dispatched, the WebSocket sheet
> should appear — **no duplicate local notification** should show.
>
> **Requires:** Rider device + Driver device (driver app open and visible)

| #  | Step                                                        | Expected                                                    | Result |
| -- | ----------------------------------------------------------- | ----------------------------------------------------------- | ------ |
| 1  | Driver goes Online                                          | Queue position shows                                        |        |
| 2  | Rider submits a ride request                                | Matching begins                                             |        |
| 3  | Observe driver device                                       | WebSocket full-screen request sheet appears                 |        |
| 4  | Check that **no system tray notification** appears          | No duplicate — FCM foreground handler suppresses it         |        |
| 5  | Check Flutter log                                           | `[FCM] Foreground message: new_ride_request`, no local notif|        |

**Notes / Observations:**

```

```

**Test Result:** ✅ Pass

---

### FCM-5: New Ride Request — Driver (Background)

> Driver app is backgrounded. FCM should wake the device and show a system tray notification.
>
> **Requires:** Physical driver device with app backgrounded

| #  | Step                                                    | Expected                                                  | Result |
| -- | ------------------------------------------------------- | --------------------------------------------------------- | ------ |
| 1  | Driver goes Online, then presses Home (app backgrounded)| App still running in background                           |        |
| 2  | Rider submits a ride request                            |                                                           |        |
| 3  | System tray shows notification                          | **"New Ride Request"** visible                            |        |
| 4  | Tap the notification                                    | App comes to foreground                                   |        |
| 5  | Driver home screen is shown                             | WS may show request sheet if still within timeout         |        |
| 6  | Check backend log                                       | `New ride request notification sent to driver` logged     |        |

**Notes / Observations:**

```
Tray notification WAI (physical device, 3s delay). Three bugs found and fixed:
1. With 10s+ delay, rider saw no-drivers screen despite driver being online — goOnline() was
   not sending GPS coords, so current_location was null and ST_Distance check excluded driver.
   Fixed: goOnline() now fetches GPS and includes coords in the API call.
2. Tapping notification showed home screen but not the request sheet — WS event was missed
   while backgrounded, driverIncomingRequestProvider was empty.
   Fixed: GET /driver/current-request endpoint + checkPendingDispatch() on app resume.
3. Driver auto-kicked offline ~15s after backgrounding (zero credits log, credits were fine) —
   KickStaleDrivers treated null last_location_update as immediately stale, fired at next
   minute tick (could be <60s after going online).
   Fixed: null heartbeat only stale if went_online_at is also older than threshold.
```

**Test Result:** ✅ Pass

---

### FCM-6: New Ride Request — Driver (Terminated)

> Driver app is killed. FCM should still deliver and show a system tray notification.
> When tapped, app launches, session resumes, and driver home is shown.
>
> **Requires:** Physical driver device with app fully killed (swiped away)

| #  | Step                                           | Expected                                           | Result |
| -- | ---------------------------------------------- | -------------------------------------------------- | ------ |
| 1  | Driver goes Online, then kill the app entirely | App not in recents                                 |        |
| 2  | Rider submits a ride request                   |                                                    |        |
| 3  | System tray shows notification                 | **"New Ride Request"** visible                     |        |
| 4  | Tap the notification                           | App launches from cold start                       |        |
| 5  | Session resumes automatically                  | Driver home screen shown                           |        |
| 6  | Flutter log                                    | `[FCM] App launched from terminated notification`  |        |

**Notes / Observations:**

```

```

**Test Result:** 🚫 N/A — by design, force-killing the app removes the driver from the queue (KickStaleDrivers). No active dispatch exists to restore, so terminated-state notification tap is not a supported flow.

---

### FCM-7: Ride Accepted — Rider

> When driver accepts the ride, rider should get a push notification regardless of app state.

| #  | Step                        | Foreground                       | Background                  | Terminated                       | Result |
| -- | --------------------------- | -------------------------------- | --------------------------- | -------------------------------- | ------ |
| 1  | Rider has active request    |                                  |                             |                                  |        |
| 2  | Driver accepts              | Local notif: **"Ride Accepted"** | System tray: "Ride Accepted"| System tray, tap → Active Ride   |        |
| 3  | App state after tap         | Active ride screen already shown | Navigate to active ride     | Navigate to active ride          |        |

**Notes / Observations:**

```

```

**Test Result:** ✅ Pass

---

### FCM-8: Driver Arrived — Rider

> Rider is notified when driver marks arrived at pickup.

| #  | Step                                    | Foreground                       | Background                   | Terminated                     | Result |
| -- | --------------------------------------- | -------------------------------- | ---------------------------- | ------------------------------ | ------ |
| 1  | Active ride in progress (`accepted`)    |                                  |                              |                                |        |
| 2  | Driver taps **Mark Arrived**            | Local notif: **"Driver Arrived"**| System tray: "Driver Arrived"| System tray, tap → Active Ride |        |
| 3  | Notification body                       | Contains driver name             | Same                         | Same                           |        |

**Notes / Observations:**

```

```

**Test Result:** ✅ Pass

---

### FCM-9: Ride Started — Rider

> Rider is notified when driver starts the ride.

| #  | Step                                    | Foreground                      | Background   | Terminated                     | Result |
| -- | --------------------------------------- | ------------------------------- | ------------ | ------------------------------ | ------ |
| 1  | Driver at pickup (`driverArrived`)      |                                 |              |                                |        |
| 2  | Driver taps **Start Ride**              | Local notif: **"Ride Started"** | System tray  | System tray, tap → Active Ride |        |

**Notes / Observations:**

```

```

**Test Result:** ✅ Pass

---

### FCM-10: Ride Completed — Both

> Both rider and driver get a completion notification.

| #  | Step                        | Foreground                               | Background   | Terminated                  | Result |
| -- | --------------------------- | ---------------------------------------- | ------------ | --------------------------- | ------ |
| 1  | Driver taps **Complete Ride**|                                         |              |                             |        |
| 2  | Rider receives push         | Local notif: **"Ride Completed"**        | System tray  | System tray, tap → home     |        |
| 3  | Driver receives push        | Local notif: **"Ride Completed"** + fare | System tray  | System tray, tap → home     |        |

**Notes / Observations:**

```
Rider foreground: ✅ heads-up banner received.
Driver foreground: ❌ banner did not appear — likely overlapped by WS completion screen transition.
Background/terminated: deferred (needs physical driver device).
```

**Test Result:** ⚠️ Partial

---

### FCM-11: Ride Cancelled — Both

> Both parties get a cancellation notification.

| #  | Step                            | Foreground                         | Background   | Terminated                    | Result |
| -- | ------------------------------- | ---------------------------------- | ------------ | ----------------------------- | ------ |
| 1  | Rider cancels an accepted ride  |                                    |              |                               |        |
| 2  | Driver receives push            | Local notif: **"Ride Cancelled"**  | System tray  | System tray, tap → driver home|        |
| 3  | Driver cancels a ride           |                                    |              |                               |        |
| 4  | Rider receives push             | Local notif: **"Ride Cancelled"**  | System tray  | System tray, tap → rider home |        |

**Notes / Observations:**

```
Round A (rider cancels → driver push): ✅ System tray notification received on physical driver device.
Round B (driver cancels → rider push): ✅ System tray notification received on physical rider device.
Tapping notification caused crash (Mapbox PointAnnotationMessenger channel error) — fixed with mounted guard + try-catch in MapboxMapWidget.
```

**Test Result:** ✅ Pass

---

### FCM-12: No Drivers Available — Rider

> Rider is notified when request times out with no driver match.

| #  | Step                                        | Foreground                              | Background   | Terminated                   | Result |
| -- | ------------------------------------------- | --------------------------------------- | ------------ | ---------------------------- | ------ |
| 1  | No drivers online; rider submits a request  |                                         |              |                              |        |
| 2  | Wait for timeout (35 s)                     | Local notif: **"No Drivers Available"** | System tray  | System tray, tap → rider home|        |
| 3  | Rider app state                             | Already on home or waiting screen       | → rider home | → rider home                 |        |

**Notes / Observations:**

```
WAI. Foreground local notification and background system tray both delivered correctly after timeout.
```

**Test Result:** ✅ Pass

---

## KYC Tests

### FCM-13: KYC Approved — Driver

> Driver is notified when admin approves their KYC.
>
> **Requires:** Driver with pending KYC, admin panel access

| #  | Step                                            | Foreground                      | Background   | Terminated                    | Result |
| -- | ----------------------------------------------- | ------------------------------- | ------------ | ----------------------------- | ------ |
| 1  | Driver has submitted KYC (pending approval)     |                                 |              |                               |        |
| 2  | Admin approves KYC in Filament panel            |                                 |              |                               |        |
| 3  | Driver receives push                            | Local notif: **"KYC Approved"** | System tray  | System tray, tap → driver home|        |
| 4  | Driver can now go Online                        | Go Online button enabled        | Same         | Same                          |        |

**Notes / Observations:**

```
WAI across all three app states (foreground, background, terminated).
```

**Test Result:** ✅ Pass

---

### FCM-14: KYC Rejected — Driver

> Driver is notified when admin rejects their KYC. Tapping the notification
> must navigate to the KYC form screen so they can resubmit.

| #  | Step                            | Foreground                       | Background   | Terminated                        | Result |
| -- | ------------------------------- | -------------------------------- | ------------ | --------------------------------- | ------ |
| 1  | Admin rejects KYC with a reason |                                  |              |                                   |        |
| 2  | Driver receives push            | Local notif: **"KYC Rejected"**  | System tray  | System tray, tap → **KYC form**   |        |
| 3  | Navigation destination          | KYC form shown in foreground     | KYC form     | KYC form after cold launch        |        |
| 4  | Rejection reason visible        | Shown in KYC form or prior screen| Same         | Same                              |        |

**Notes / Observations:**

```
WAI across all three app states. Navigation to KYC form confirmed on notification tap.
```

**Test Result:** ✅ Pass

---

## Edge Case Tests

### FCM-15: Null FCM Token — Graceful Skip

> If a user has no FCM token (e.g. denied permission, token not yet registered),
> the backend must silently skip the notification without crashing.
>
> **Requires:** DB access

| #  | Step                                                                  | Expected                                  | Result |
| -- | --------------------------------------------------------------------- | ----------------------------------------- | ------ |
| 1  | Manually null a user's token: `UPDATE users SET fcm_token = NULL WHERE id = <id>` |                             |        |
| 2  | Trigger a ride event that sends a push to that user                   | No crash, no error 500                    |        |
| 3  | Check backend log                                                     | `Skipped FCM — no token` or similar       |        |
| 4  | WebSocket still delivers event to that user                           | Real-time updates still work              |        |
| 5  | Other users with valid tokens still receive their pushes              | Not affected                              |        |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

### FCM-16: Multiple Rapid Notifications

> Multiple notifications in quick succession must all display without crashes or drops.
>
> **Requires:** Admin panel (to trigger state changes rapidly)

| #  | Step                                                                              | Expected                               | Result |
| -- | --------------------------------------------------------------------------------- | -------------------------------------- | ------ |
| 1  | Advance a ride rapidly: accept → arrive → start → complete                        |                                        |        |
| 2  | Rider device (backgrounded)                                                       | All 4 notifications appear in tray     |        |
| 3  | No crashes or ANR on either device                                                | App remains stable                     |        |
| 4  | Notification order is correct                                                     | Accepted → Arrived → Started → Completed|       |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

### FCM-17: Sign Out then Sign In — Token Rotation

> After signing out and back in, a fresh FCM token is registered and the old one
> is cleared — a second device signed in previously should not receive notifications.

| #  | Step                                             | Expected                              | Result |
| -- | ------------------------------------------------ | ------------------------------------- | ------ |
| 1  | Note `fcm_token` in DB for user before sign-out  | Token A recorded                      |        |
| 2  | Sign out from rider app                          | `fcm_token` set to NULL in DB         |        |
| 3  | Sign back in on rider app                        | New token (Token B) registered        |        |
| 4  | Check DB                                         | `fcm_token = Token B` (≠ Token A)     |        |
| 5  | Trigger a notification to that user              | Only current device (Token B) receives|        |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

### FCM-18: Permission Denied — App Still Works

> If the user denies notification permission, the app must continue to function
> normally. WebSocket real-time updates still work; only push is unavailable.

| #  | Step                                              | Expected                                       | Result |
| -- | ------------------------------------------------- | ---------------------------------------------- | ------ |
| 1  | Clear app data, launch, and **deny** permission   | Dialog dismissed                               |        |
| 2  | Sign in                                           | App proceeds normally, no crash                |        |
| 3  | Flutter log                                       | `[FCM] Permission: denied` (no exception)      |        |
| 4  | Go through a full ride flow                       | WebSocket events still fire on both devices    |        |
| 5  | Backgrounded rider device                         | No push received (expected) — WS not affected  |        |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

### FCM-19: Network Offline During Token Send

> If the backend is unreachable when the app tries to register the FCM token,
> it must fail silently and retry on next launch.

| #  | Step                                     | Expected                                   | Result |
| -- | ---------------------------------------- | ------------------------------------------ | ------ |
| 1  | Stop `php artisan serve`                 | Backend offline                            |        |
| 2  | Clear app data and launch rider app      |                                            |        |
| 3  | Sign in                                  | App launches normally despite failure      |        |
| 4  | Flutter log                              | `[FCM] Failed to send token: ...` no crash |        |
| 5  | Restart backend, restart app             | Token send retried on `initialize()` call  |        |
| 6  | Check DB                                 | `fcm_token` now populated                  |        |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

## End-to-End Regression

### FCM-E2E: Full Happy Path with FCM Active

> Complete ride from request to completion, verifying FCM fires at every stage
> alongside WebSocket — no interference, no duplicates.
>
> **Requires:** Rider device + Driver device, both apps backgrounded for push verification

| #  | Step                                                     | Expected                                  | Result |
| -- | -------------------------------------------------------- | ----------------------------------------- | ------ |
| 1  | Confirm both rider and driver have FCM tokens in DB      | Both `has_token = true`                   |        |
| 2  | Driver goes Online, backgrounds the app                  |                                           |        |
| 3  | Rider submits a ride request, backgrounds the app        |                                           |        |
| 4  | Driver receives **"New Ride Request"** push in tray      | ✅                                        |        |
| 5  | Driver taps notification → opens app → accepts ride      |                                           |        |
| 6  | Rider receives **"Ride Accepted"** push                  | ✅                                        |        |
| 7  | Driver marks arrived                                     | Rider receives **"Driver Arrived"** push  |        |
| 8  | Driver starts ride                                       | Rider receives **"Ride Started"** push    |        |
| 9  | Driver completes ride                                    | Both receive **"Ride Completed"** push    |        |
| 10 | Check backend log                                        | All 5 FCM sends logged, no errors         |        |
| 11 | Check DB `fcm_token` unchanged                           | Tokens not rotated mid-ride               |        |
| 12 | No crashes on either device throughout                   | App stable                                |        |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

## Notification Event Matrix

> Quick-reference: expected behaviour for every event across all app states.

| Event                          | Foreground                        | Background    | Terminated                      | Null token       |
| ------------------------------ | --------------------------------- | ------------- | ------------------------------- | ---------------- |
| New ride request (driver)      | WS sheet shows, no local notif    | System tray   | System tray, cold launch        | No crash, WS ok  |
| Ride accepted (rider)          | Local notif shows                 | System tray   | Tap → active ride screen        | Silently skipped |
| Driver arrived (rider)         | Local notif shows                 | System tray   | Tap → active ride screen        | Silently skipped |
| Ride started (rider)           | Local notif shows                 | System tray   | Tap → active ride screen        | Silently skipped |
| Ride completed (rider)         | Local notif shows                 | System tray   | Tap → rider home                | Silently skipped |
| Ride completed (driver)        | Local notif shows                 | System tray   | Tap → driver home               | Silently skipped |
| Ride cancelled (rider)         | Local notif shows                 | System tray   | Tap → rider home                | Silently skipped |
| Ride cancelled (driver)        | Local notif shows                 | System tray   | Tap → driver home               | Silently skipped |
| No drivers available (rider)   | Local notif shows                 | System tray   | Tap → rider home                | Silently skipped |
| KYC approved (driver)          | Local notif shows                 | System tray   | Tap → driver home               | Silently skipped |
| KYC rejected (driver)          | Local notif shows                 | System tray   | Tap → KYC form                  | Silently skipped |
| Queue position update (driver) | No notification (info only)       | No notif      | —                               | —                |

---

## Bugs Found During Testing

| #  | Test   | Description                                                              | Severity | Fixed?    |
| -- | ------ | ------------------------------------------------------------------------ | -------- | --------- |
| 1  | FCM-4  | Decline crash — double pop race condition in RideRequestScreen           | High     | ✅ Fixed  |
| 2  | FCM-5  | Timer always shows 30s when app resumes from background                  | Medium   | ✅ Fixed  |
| 3  | FCM-10 | Driver foreground completion banner missing (WS screen overlap)          | Low      | ⬜ Open   |
| 4  | FCM-11 | Mapbox PointAnnotationMessenger crash on notification tap resume          | Medium   | ✅ Fixed  |
| 5  | FCM-4  | Driver timer shows consumed time when dispatched during no-drivers wait   | Medium   | ✅ Fixed  |
| 6  | FCM-5  | goOnline() sent no GPS — null current_location excluded driver from match | High     | ✅ Fixed  |
| 7  | FCM-5  | Notification tap showed home screen, not request sheet (missed WS event)  | High     | ✅ Fixed  |
| 8  | FCM-5  | KickStaleDrivers kicked driver ~15s after going online (null heartbeat)   | High     | ✅ Fixed  |

---

## Sign-off

- [ ] FCM token registered on login for both rider and driver
- [ ] FCM token cleared on logout
- [ ] New ride request push delivered to driver (background + terminated)
- [ ] No duplicate notification when driver app is in foreground
- [ ] All ride lifecycle pushes delivered to rider
- [ ] KYC rejected tap navigates to KYC form
- [ ] Null token: no crash, WebSocket unaffected
- [ ] Permission denied: app functions normally via WebSocket
- [ ] E2E full happy path: all 5 FCM events fire cleanly
- [ ] `fcm-wiring2` ready to merge → `main`

**Tested by:** \_\_\_\_\_\_\_\_\_\_\_
**Date:** \_\_\_\_\_\_\_\_\_\_\_
