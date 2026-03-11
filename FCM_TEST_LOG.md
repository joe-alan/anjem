# Device Test Log — FCM Push Notifications (`feat/fcm-wiring`)

> **Branch:** `feat/fcm-wiring`
> **Date:** \_\_\_\_\_\_\_\_\_\_\_
> **Tester:** \_\_\_\_\_\_\_\_\_\_\_
> **Rider device:** \_\_\_\_\_\_\_\_\_\_\_ (Android \_\_\_)
> **Driver device:** \_\_\_\_\_\_\_\_\_\_\_ (Android \_\_\_)

---

## Status Summary

| Test | Area | Status |
|------|------|--------|
| [FCM-1 Permission & Token Registration](#fcm-1-permission--token-registration) | Setup | ⬜ Not tested |
| [FCM-2 Token Refresh](#fcm-2-token-refresh) | Setup | ⬜ Not tested |
| [FCM-3 Token Cleared on Logout](#fcm-3-token-cleared-on-logout) | Setup | ⬜ Not tested |
| [FCM-4 New Ride Request — Driver (Foreground)](#fcm-4-new-ride-request--driver-foreground) | Ride flow | ⬜ Not tested |
| [FCM-5 New Ride Request — Driver (Background)](#fcm-5-new-ride-request--driver-background) | Ride flow | ⬜ Not tested |
| [FCM-6 New Ride Request — Driver (Terminated)](#fcm-6-new-ride-request--driver-terminated) | Ride flow | ⬜ Not tested |
| [FCM-7 Ride Accepted — Rider](#fcm-7-ride-accepted--rider) | Ride flow | ⬜ Not tested |
| [FCM-8 Driver Arrived — Rider](#fcm-8-driver-arrived--rider) | Ride flow | ⬜ Not tested |
| [FCM-9 Ride Started — Rider](#fcm-9-ride-started--rider) | Ride flow | ⬜ Not tested |
| [FCM-10 Ride Completed — Both](#fcm-10-ride-completed--both) | Ride flow | ⬜ Not tested |
| [FCM-11 Ride Cancelled — Both](#fcm-11-ride-cancelled--both) | Ride flow | ⬜ Not tested |
| [FCM-12 No Drivers Available — Rider](#fcm-12-no-drivers-available--rider) | Ride flow | ⬜ Not tested |
| [FCM-13 KYC Approved — Driver](#fcm-13-kyc-approved--driver) | KYC | ⬜ Not tested |
| [FCM-14 KYC Rejected — Driver](#fcm-14-kyc-rejected--driver) | KYC | ⬜ Not tested |
| [FCM-15 Null FCM Token — Graceful Skip](#fcm-15-null-fcm-token--graceful-skip) | Edge case | ⬜ Not tested |
| [FCM-16 Multiple Rapid Notifications](#fcm-16-multiple-rapid-notifications) | Edge case | ⬜ Not tested |
| [FCM-17 Sign Out then Sign In — Token Rotation](#fcm-17-sign-out-then-sign-in--token-rotation) | Edge case | ⬜ Not tested |
| [FCM-18 Permission Denied — App Still Works](#fcm-18-permission-denied--app-still-works) | Edge case | ⬜ Not tested |
| [FCM-19 Network Offline During Token Send](#fcm-19-network-offline-during-token-send) | Edge case | ⬜ Not tested |
| [FCM-E2E Full Happy Path with FCM Active](#fcm-e2e-full-happy-path-with-fcm-active) | E2E | ⬜ Not tested |

> **Status key:** ⬜ Not tested · ✅ Pass · ❌ Fail · ⚠️ Partial · ⏭️ Deferred

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

| # | Step | Expected | Result |
|---|------|----------|--------|
| 1 | Clear app data on both rider and driver devices | Fresh state | |
| 2 | Launch rider app and sign in with Google | Permission dialog appears | |
| 3 | Tap **Allow** | Dialog dismissed | |
| 4 | Check backend log | `[FCM] Token sent to backend` or equivalent | |
| 5 | Check DB: `SELECT fcm_token IS NOT NULL FROM users WHERE id = <rider_id>` | `true` | |
| 6 | Repeat steps 2–5 for driver app | Same result | |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

### FCM-2: Token Refresh

> When Firebase rotates the FCM token, the app must re-send the new token to the backend.
>
> **Requires:** Backend log access

| # | Step | Expected | Result |
|---|------|----------|--------|
| 1 | Simulate token refresh via Firebase Console → Cloud Messaging → send a test message that forces rotation, OR delete and reinstall the app | Token refresh event fires | |
| 2 | Watch Flutter log | `[FCM] Refreshed token sent to backend` | |
| 3 | Check DB `fcm_token` for the user | Updated to a new value | |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

### FCM-3: Token Cleared on Logout

> After sign-out, the FCM token must be deleted from the device and nulled in the DB
> so the signed-out user does not receive further notifications.
>
> **Requires:** Logged-in rider or driver device

| # | Step | Expected | Result |
|---|------|----------|--------|
| 1 | Note current `fcm_token` value in DB for the user | Non-null | |
| 2 | Sign out from the app | Returns to login screen | |
| 3 | Check DB: `SELECT fcm_token IS NULL FROM users WHERE id = <id>` | `true` — token cleared | |
| 4 | Trigger a ride event that would normally send a push to this user | No push received | |
| 5 | Sign back in | New token registered | |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

## Ride Flow Tests

### FCM-4: New Ride Request — Driver (Foreground)

> When driver app is in foreground and a ride is dispatched, the WebSocket sheet
> should appear — **no duplicate local notification** should show.
>
> **Requires:** Rider device + Driver device (driver app open and visible)

| # | Step | Expected | Result |
|---|------|----------|--------|
| 1 | Driver goes Online | Queue position shows | |
| 2 | Rider submits a ride request | Matching begins | |
| 3 | Observe driver device | WebSocket full-screen request sheet appears | |
| 4 | Check that **no system tray notification** appears simultaneously | No duplicate — FCM foreground handler suppresses it | |
| 5 | Check Flutter log | `[FCM] Foreground message: ... new_ride_request` then no `showNotification` call | |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

### FCM-5: New Ride Request — Driver (Background)

> Driver app is backgrounded. FCM should wake the device and show a system tray notification.
>
> **Requires:** Driver device with app backgrounded

| # | Step | Expected | Result |
|---|------|----------|--------|
| 1 | Driver goes Online, then presses Home (app backgrounded) | App still running in background | |
| 2 | Rider submits a ride request | | |
| 3 | System tray shows notification: **"New Ride Request"** | Notification visible | |
| 4 | Tap the notification | App comes to foreground | |
| 5 | Driver home screen is shown | WebSocket may show the ride request sheet if still within timeout | |
| 6 | Check backend log | `FCM push sent to driver` or similar | |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

### FCM-6: New Ride Request — Driver (Terminated)

> Driver app is killed. FCM should still deliver and show a system tray notification.
> When tapped, app launches, session resumes, and driver home is shown.
>
> **Requires:** Driver device with app fully killed (swiped away)

| # | Step | Expected | Result |
|---|------|----------|--------|
| 1 | Driver goes Online, then kill the app entirely | App not in recents | |
| 2 | Rider submits a ride request | | |
| 3 | System tray shows notification | **"New Ride Request"** visible | |
| 4 | Tap the notification | App launches from cold start | |
| 5 | Session resumes automatically | Driver home screen shown | |
| 6 | Flutter log | `[FCM] App launched from terminated notification` | |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

### FCM-7: Ride Accepted — Rider

> When driver accepts the ride, rider should get a push notification regardless of app state.

| # | Step | Foreground expected | Background expected | Terminated expected | Result |
|---|------|--------------------|--------------------|--------------------|----|
| 1 | Rider has active request | | | | |
| 2 | Driver accepts | Local notification: **"Ride Accepted"** | System tray: **"Ride Accepted"** | System tray, tap → Active Ride screen | |
| 3 | App state after tap/notification | Active ride screen already shown | Navigate to active ride screen | Navigate to active ride screen | |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

### FCM-8: Driver Arrived — Rider

> Rider is notified when driver marks arrived at pickup.

| # | Step | Foreground expected | Background expected | Terminated expected | Result |
|---|------|--------------------|--------------------|--------------------|----|
| 1 | Active ride in progress (`accepted` status) | | | | |
| 2 | Driver taps **Mark Arrived** | Local notification: **"Driver Arrived"** | System tray: **"Driver Arrived"** | System tray, tap → Active Ride screen | |
| 3 | Notification body | Contains driver name | Same | Same | |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

### FCM-9: Ride Started — Rider

> Rider is notified when driver starts the ride.

| # | Step | Foreground expected | Background expected | Terminated expected | Result |
|---|------|--------------------|--------------------|--------------------|----|
| 1 | Driver at pickup (`driverArrived` status) | | | | |
| 2 | Driver taps **Start Ride** | Local notification: **"Ride Started"** | System tray | System tray, tap → Active Ride screen | |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

### FCM-10: Ride Completed — Both

> Both rider and driver get a completion notification.

| # | Step | Foreground expected | Background expected | Terminated expected | Result |
|---|------|--------------------|--------------------|--------------------|----|
| 1 | Driver taps **Complete Ride** | | | | |
| 2 | Rider receives push | Local notif: **"Ride Completed"** | System tray | System tray, tap → home | |
| 3 | Driver receives push | Local notif: **"Ride Completed"** with fare | System tray | System tray, tap → driver home | |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

### FCM-11: Ride Cancelled — Both

> Both parties get a cancellation notification.

| # | Step | Foreground expected | Background expected | Terminated expected | Result |
|---|------|--------------------|--------------------|--------------------|----|
| 1 | Rider cancels an accepted ride | | | | |
| 2 | Driver receives push | Local notif: **"Ride Cancelled"** | System tray | System tray, tap → driver home | |
| 3 | Driver cancels a ride | | | | |
| 4 | Rider receives push | Local notif: **"Ride Cancelled"** | System tray | System tray, tap → rider home | |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

### FCM-12: No Drivers Available — Rider

> Rider is notified when request times out with no driver match.

| # | Step | Foreground expected | Background expected | Terminated expected | Result |
|---|------|--------------------|--------------------|--------------------|----|
| 1 | No drivers online; rider submits a request | | | | |
| 2 | Wait for timeout (35 s) | Local notif: **"No Drivers Available"** | System tray | System tray, tap → rider home | |
| 3 | Rider app state | Already on home or waiting screen | Navigates to rider home | Navigates to rider home | |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

## KYC Tests

### FCM-13: KYC Approved — Driver

> Driver is notified when admin approves their KYC.
>
> **Requires:** Driver with pending KYC, admin panel access

| # | Step | Foreground expected | Background expected | Terminated expected | Result |
|---|------|--------------------|--------------------|--------------------|----|
| 1 | Driver has submitted KYC (pending admin approval) | | | | |
| 2 | Admin approves KYC in Filament panel | | | | |
| 3 | Driver receives push | Local notif: **"KYC Approved!"** | System tray | System tray, tap → driver home | |
| 4 | Driver can now go Online | Go Online button enabled | Same | Same | |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

### FCM-14: KYC Rejected — Driver

> Driver is notified when admin rejects their KYC. Tapping the notification
> must navigate to the KYC form screen so they can resubmit.

| # | Step | Foreground expected | Background expected | Terminated expected | Result |
|---|------|--------------------|--------------------|--------------------|----|
| 1 | Admin rejects KYC with a reason | | | | |
| 2 | Driver receives push | Local notif: **"KYC Rejected"** | System tray | System tray, tap → **KYC form screen** | |
| 3 | Navigation destination | KYC form shown in foreground | KYC form after tap | KYC form after cold launch | |
| 4 | Rejection reason visible | Shown in KYC form or prior screen | Same | Same | |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

## Edge Case Tests

### FCM-15: Null FCM Token — Graceful Skip

> If a user has no FCM token (e.g. denied permission, token not yet registered),
> the backend must silently skip the notification without crashing.
>
> **Requires:** DB access

| # | Step | Expected | Result |
|---|------|----------|--------|
| 1 | Manually null a user's token: `UPDATE users SET fcm_token = NULL WHERE id = <id>;` | | |
| 2 | Trigger a ride event that sends a push to that user | No crash, no error 500 | |
| 3 | Check backend log | Warning logged: `Skipped FCM — no token` or similar | |
| 4 | WebSocket still delivers event to that user | Real-time updates still work | |
| 5 | Other users with valid tokens still receive their pushes | Not affected | |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

### FCM-16: Multiple Rapid Notifications

> Multiple notifications in quick succession must all display without crashes or drops.
>
> **Requires:** Admin panel (to trigger state changes rapidly)

| # | Step | Expected | Result |
|---|------|----------|--------|
| 1 | Create a ride and advance it through statuses rapidly (accept → arrive → start → complete) | | |
| 2 | Rider device (backgrounded) | All 4 notifications appear in system tray | |
| 3 | No crashes or ANR on either device | App remains stable | |
| 4 | Notification order is correct | Accepted → Arrived → Started → Completed | |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

### FCM-17: Sign Out then Sign In — Token Rotation

> After signing out and back in, a fresh FCM token is registered and the old one
> is cleared — a second device signed in previously should not receive notifications.

| # | Step | Expected | Result |
|---|------|----------|--------|
| 1 | Note `fcm_token` in DB for user before sign-out | Token A recorded | |
| 2 | Sign out from rider app | `fcm_token` set to NULL in DB | |
| 3 | Sign back in on rider app | New token (Token B) registered | |
| 4 | Check DB | `fcm_token = Token B` (different from Token A) | |
| 5 | Trigger a notification to that user | Only current device (Token B) receives it | |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

### FCM-18: Permission Denied — App Still Works

> If the user denies notification permission, the app must continue to function
> normally. WebSocket real-time updates still work; only push is unavailable.

| # | Step | Expected | Result |
|---|------|----------|--------|
| 1 | Clear app data, launch, and **deny** notification permission | Dialog dismissed | |
| 2 | Sign in | App proceeds normally, no crash | |
| 3 | Flutter log | `[FCM] Permission: denied` (no exception thrown) | |
| 4 | Go through a full ride flow | WebSocket events still fire correctly on both devices | |
| 5 | Backgrounded rider device | No push notification received (expected) — WebSocket not affected | |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

### FCM-19: Network Offline During Token Send

> If the backend is unreachable when the app tries to register the FCM token,
> it must fail silently and retry on next launch.

| # | Step | Expected | Result |
|---|------|----------|--------|
| 1 | Stop `php artisan serve` | Backend offline | |
| 2 | Clear app data and launch rider app | | |
| 3 | Sign in | App launches normally despite token send failure | |
| 4 | Flutter log | `[FCM] Failed to send token: ...` (no crash) | |
| 5 | Restart backend, restart app | Token send retried on `initialize()` call | |
| 6 | Check DB | `fcm_token` now populated | |

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

| # | Step | Expected | Result |
|---|------|----------|--------|
| 1 | Confirm both rider and driver have FCM tokens in DB | Both `has_token = true` | |
| 2 | Driver goes Online, backgrounds the app | | |
| 3 | Rider submits a ride request, backgrounds the app | | |
| 4 | Driver receives **"New Ride Request"** push in system tray | ✅ | |
| 5 | Driver taps notification → opens app → accepts ride | | |
| 6 | Rider receives **"Ride Accepted"** push | ✅ | |
| 7 | Driver marks arrived | Rider receives **"Driver Arrived"** push | ✅ | |
| 8 | Driver starts ride | Rider receives **"Ride Started"** push | ✅ | |
| 9 | Driver completes ride | Both receive **"Ride Completed"** push | ✅ | |
| 10 | Check backend log | All 5 FCM sends logged, no errors | |
| 11 | Check DB `fcm_token` unchanged | Tokens not rotated mid-ride | |
| 12 | No crashes on either device throughout | App stable | |

**Notes / Observations:**

```

```

**Test Result:** ⬜

---

## Notification Event Matrix

> Quick-reference: expected behaviour for every event across all app states.

| Event | Foreground | Background | Terminated | Null token | Token refresh |
|-------|-----------|-----------|-----------|-----------|--------------|
| New ride request (driver) | WebSocket sheet shows, **no** local notif | System tray shows, tap → driver home | System tray, tap → cold launch, session resumes | No crash, WS still works | Token re-sent to backend |
| Ride accepted (rider) | Local notif shows | System tray shows | Tap → active ride screen | Silently skipped | — |
| Driver arrived (rider) | Local notif shows | System tray shows | Tap → active ride screen | Silently skipped | — |
| Ride started (rider) | Local notif shows | System tray shows | Tap → active ride screen | Silently skipped | — |
| Ride completed (rider) | Local notif shows | System tray shows | Tap → rider home | Silently skipped | — |
| Ride completed (driver) | Local notif shows | System tray shows | Tap → driver home | Silently skipped | — |
| Ride cancelled (rider) | Local notif shows | System tray shows | Tap → rider home | Silently skipped | — |
| Ride cancelled (driver) | Local notif shows | System tray shows | Tap → driver home | Silently skipped | — |
| No drivers available (rider) | Local notif shows | System tray shows | Tap → rider home | Silently skipped | — |
| KYC approved (driver) | Local notif shows | System tray shows | Tap → driver home | Silently skipped | — |
| KYC rejected (driver) | Local notif shows | System tray shows | Tap → **KYC form** | Silently skipped | — |
| Queue position update (driver) | No notification (info only) | No notification | — | — | — |

---

## Bugs Found During Testing

| # | Test | Description | Severity | Fixed? |
|---|------|-------------|----------|--------|
| | | | | |

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
- [ ] `feat/fcm-wiring` ready to merge → `dev`

**Tested by:** \_\_\_\_\_\_\_\_\_\_\_
**Date:** \_\_\_\_\_\_\_\_\_\_\_
