# PR #46 — Pre-Release Hotfixes Test Checklist

**PR:** [joe-alan/anjem#46](https://github.com/joe-alan/anjem/pull/46)
**Branch:** `fix/pre-release-hotfixes` @ `2e31e97`
**Staging:** `origin/staging` is synced to the same tip — test on staging build
**Created:** 2026-04-12

> Given 12 commits touching critical ride flows, map rendering, session resume, matching queue, FCM, and the Play Store overheating blocker, this is a **full edge-case pass**, not just smoke. Tiered so you can abort early if a P0 fails.

---

## Pre-test setup

- [ ] Sideload both rider + driver APKs built from `fix/pre-release-hotfixes` tip (`2e31e97`)
- [ ] Both apps point at `staging-api.anjem.me`
- [ ] Device **fully charged**, note ambient temp + device temp (same value initially)
- [ ] Rider + driver accounts logged in, driver is KYC-approved
- [ ] Second Android device (or emulator) for the driver if testing rider-side alone

---

## P0 — Smoke (must pass before merge to main)

Core flow. If any fail, stop and fix — don't proceed to P1.

- [x] **Happy path end-to-end ride** — rider requests → driver accepts → driver reaches pickup → rider starts trip → driver marks completed → rider sees rating/completed screen
  - Guards: every commit
- [x] **FCM push to backgrounded driver** — kill driver app (swipe from recents). Rider requests ride. Driver phone should vibrate/show push within ~5s. Tap it → opens driver app to the request sheet
  - Guards: `8674d22` firebase file-path fix, `38464bf` FIREBASE_CREDENTIALS
- [x] **Driver goes online → shows queue position** — driver taps "Go Online" on home screen. Within ~3s should show "Position N in queue"
  - Guards: `dff5f60` backgrounded-driver keepalive
- [x] **Driver permissions gathered upfront** — fresh install driver → login → home screen. Should prompt for location + notifications + background location in one sequence, not piecemeal mid-ride. Deny location once and retry → works
  - Guards: `17067cc`

## P1 — Edge cases targeting this branch's risky changes

### Map widget smart diff (`400b425`)

- [ ] **Driver marker tracks smoothly on rider map** during active ride — the driver pin updates every ~1-3s as the driver moves. **Pickup and destination pins must not flicker or disappear** when the driver pin moves
  - Previous impl used `deleteAll` + recreate. Failure = marker flicker or pins vanish
- [ ] **Route polyline updates as driver moves** — during `accepted` status, blue polyline from driver → pickup should redraw every ~15s to track the driver's actual path
  - Failure = polyline frozen pointing to old driver location for >30s
- [ ] **Polyline detects shape change** — as driver makes a turn, polyline should reflect the new path (not just shift the endpoint)
  - Guards `MapPolyline.==` now compares points so `didUpdateWidget` detects shape changes

### Route refetch debounce 15s / 50m (`400b425`)

- [ ] **No Mapbox Directions thrashing** — if DevTools/proxy access: verify `GET directions/v5/...` calls fire at most once per 15s from rider during active ride. Without DevTools: confirm the route polyline doesn't visibly update faster than 15s
- [ ] **Polyline lag is acceptable** — polyline may lag up to 15s behind live driver position. Driver marker itself should be live

### Waiting-screen pin state memoization (`2e31e97`)

**⚠️ This is the regression I caught in code review. Test carefully.**

- [ ] **Camera pans to frame notified driver** — rider taps request → waiting screen shows green (active) driver pins nearby. When backend dispatches a ride to one driver, that pin should turn **blue** (notified state) and the **camera should animate to frame rider + blue pin within 15-20s** (next poll cycle)
- [ ] **If camera doesn't pan when a pin turns blue, the fix is still broken.** Force the scenario: have driver test account near the rider pickup so dispatch is deterministic. You should see the camera zoom/pan ~15s after dispatch
- [ ] **Nearby pin refresh still works** — waiting screen polls every 15s now (was 5s). Over 60s, pin set should update as drivers come/go

### Session resume after screen-off (`837ba8f`)

- [ ] **Rider active ride survives screen-off** — during active ride, lock phone for 30s. Unlock. Ride screen shows correct status (not stuck on old status, not re-navigated to home). No duplicate "driver arrived" dialogs
- [ ] **Driver active ride survives screen-off** — same test on driver side. Action slider still in correct state (pickup vs in-progress vs complete), not reset
- [ ] **Rider backgrounds during waiting → comes back** — tap request → home button → wait 30s → return. Should resume on waiting screen

### Background location + queue reliability (`9ac9d8f`, `dff5f60`)

- [ ] **Driver stays in queue while backgrounded** — driver goes online → backgrounds app for 2 min → returns. Still online, still in queue, position on admin panel shows recent
- [ ] **Driver position still updates on rider side during pickup** — driver accepts ride → backgrounds driver app → rider should still see the driver marker move on their screen
- [ ] **Foreground-service notification visible** — when driver is online, Android should show the persistent "Anjem Driver / Your location is being shared while online" notification
  - ⚠️ It'll be in **English** — that's the outstanding i18n issue from the code review (Finding D). Not a blocker but flag it
- [ ] **Driver app suspended/killed → queue leaves** — force-stop driver app (settings → apps → force stop). Admin panel should reflect driver leaves the queue

### Location picker / search (`3a79e69`)

- [ ] **Location search still returns results** — rider picks destination → search box → type "Undip" → results appear
  - Guards against session_token regression in Mapbox Search Box

---

## P2 — Performance verification (the whole point)

**Blocker for Play Store submission.**

- [ ] **Baseline run** (optional — if you kept an old APK around) — note time until device noticeably warm during a 5-min active ride on the unfixed build
- [ ] **Post-fix 5-min active ride** — run a full ride on the new build. At the end:
  - [ ] Device temp near ambient (≤5°C above starting temp)
  - [ ] No performance degradation, UI stays smooth
- [ ] **Battery usage check** — Android Settings → Battery → Battery usage. Anjem Rider and Anjem Driver should **not** be in top 5 consumers after ~15-min test session
- [ ] **(Optional) Flutter DevTools GPU overlay** — run with `flutter run --profile` and watch Performance overlay. GPU thread should be low/idle when map is stationary

---

## Abort conditions — stop testing and fix

If any of these happen, stop the session and file a bug:

1. Camera doesn't pan to notified driver on waiting screen within 20s → pin state memoization fix broken
2. Driver marker missing/flickering on rider map → smart marker diff broken
3. Route polyline stuck for >30s → debounce too aggressive or `MapPolyline.==` broken
4. Device gets noticeably hot during a 5-min ride → overheating fix insufficient → Play Store still blocked
5. FCM push doesn't wake backgrounded driver → `ad45924`/`8674d22`/`38464bf` firebase chain broken
6. Driver kicked from queue when backgrounded → `dff5f60` keepalive regression
7. Duplicate navigation on ride cancel → pre-existing race condition (not from this PR) — note but don't block merge

---

## Known issues (NOT blockers)

From the code review (PR joe-alan/anjem#46 comment):

1. **Hardcoded English notification strings** in `DriverLocationService.start()` — Android foreground-service notification renders in English regardless of locale. ARB keys exist but are not wired to the service call sites. Fix separately.
2. **Pre-existing cancel-state race** in `rider_active_ride_screen.dart` — WebSocket cancel + poll cancel can fire concurrently and stack navigation pushes. Flagged in PR #29 CodeRabbit review, not touched by this PR. Separate bug.

---

## Test results

**Tester:** **\*\***\_\_\_**\*\***
**Device:** **\*\***\_\_\_**\*\***
**Date:** **\*\***\_\_\_**\*\***

- P0: ☐ PASS ☐ FAIL (reason: **\*\***\_\_\_**\*\***)
- P1: ☐ PASS ☐ FAIL (reason: **\*\***\_\_\_**\*\***)
- P2: ☐ PASS ☐ FAIL (reason: **\*\***\_\_\_**\*\***)

**Merge decision:** ☐ Merge to main ☐ Block — needs more fixes
