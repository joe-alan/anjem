# Continue Here

## Current State

**Branch:** `fcm-wiring2`
**Next action:** Device-test FCM using `docs/test-logs/FCM_TEST_LOG.md`, then open PR → `main`

---

## This Session (2026-03-11)

### What was done

#### 1. Completed Mapbox Optimisation (`feat/mapbox-optimisation`)
- Finished device testing: MB-7 through MB-11 and MB-E2E all ✅ Pass
- MB-10 (adaptive intervals) ⚠️ Partial — stationary confirmed, speed deferred (emulator only)
- Updated `docs/test-logs/MAPBOX_OPTIMISATION_TEST_LOG.md` sign-off
- Cleaned up `mapbox_config.dart` (removed hardcoded token example from comment)
- PR #32 opened: `feat/mapbox-optimisation` → `main`

#### 2. FCM Push Notifications (`feat/fcm-wiring`)
Full implementation complete — Android only. Branch created from `main`.

**Backend (Phase A):**
| File | Change |
|------|--------|
| `backend/app/Services/NotificationService.php` | New `sendNewRideRequestToDriver(RideRequest, User)` with high-priority Android config; `sendNotification()` updated to support `$highPriority` param |
| `backend/app/Services/MatchingQueueService.php` | FCM push call added in `dispatchToDriver()` after WS broadcast — wrapped in try/catch, failure never blocks dispatch |
| `backend/app/Http/Controllers/Api/AuthController.php` | Clears `fcm_token` on logout |

**Flutter (Phases B + C):**
| File | Change |
|------|--------|
| `mobile/pubspec.yaml` | Added `flutter_local_notifications: ^18.0.1` |
| `mobile/lib/core/services/fcm/local_notification_service.dart` | New — two Android channels: `anjem_rides` (max importance) + `anjem_general` (default) |
| `mobile/lib/core/services/fcm/fcm_service.dart` | New — permission, token send/refresh, foreground/background/terminated handlers; suppresses local notif for `new_ride_request` (WS shows full sheet); `handleNotificationNavigation` routes per event type + flavor |
| `mobile/lib/core/providers/fcm_provider.dart` | New — `fcmServiceProvider` Riverpod provider |
| `mobile/lib/core/navigation/navigator_key.dart` | New — global `navigatorKey` for context-free notification tap navigation |
| `mobile/lib/core/app.dart` | Added `navigatorKey` to `MaterialApp`; wrapped `AuthenticationWrapper` with `FcmInitializer` (initializes FCM on auth, deletes token on sign-out) |
| `mobile/lib/main_rider.dart` | Background handler registered |
| `mobile/lib/main_driver.dart` | Background handler registered |

**Platform (Phase D1):**
| File | Change |
|------|--------|
| `mobile/android/app/src/main/AndroidManifest.xml` | FCM default channel (`anjem_rides`) + icon meta-data |

**Key design decisions:**
- `FcmInitializer` widget (in `app.dart`) drives FCM lifecycle via `ref.listen` on `authStateProvider` — no changes to `AuthStateNotifier` needed
- `new_ride_request` foreground notification intentionally suppressed — WebSocket shows the full-screen request sheet
- Navigation on tap: `kyc_rejected` → KYC form; all others → `popUntil(isFirst)` (session wrapper handles routing)
- iOS intentionally skipped — Android-only open beta

**Test log:** `docs/test-logs/FCM_TEST_LOG.md` — 20 tests: FCM-1 through FCM-E2E

#### 3. Created `docs/STAGING_LAUNCH_CHECKLIST.md`
Full checklist tracking all remaining work to Android open beta launch (9 sprints, ~60 tasks).

---

## Commits This Session

| Commit | Branch | Description |
|--------|--------|-------------|
| `1ff049f` | feat/mapbox-optimisation | chore: remove google_place_id, mark MB-4/5 pass |
| `dd2206c` | feat/mapbox-optimisation | docs: mark MB-7–11 and E2E pass, clean token comment |
| `ec83d26` | feat/fcm-wiring | feat(fcm): wire FCM push notifications — Android, all ride events |
| `7d44ad3` | feat/fcm-wiring | docs(fcm): add FCM device test log |

---

## Open PRs

| PR | Branch | Target | Status |
|----|--------|--------|--------|
| #32 | `feat/mapbox-optimisation` | `main` | Open — awaiting review/merge |

---

## What to Do Next

### Immediate: Device-test FCM
Run through `docs/test-logs/FCM_TEST_LOG.md`. Key tests to do first:
1. **FCM-1** — permission dialog appears on first launch, token stored in DB
2. **FCM-4** — foreground driver: WS sheet shows, NO duplicate local notif
3. **FCM-5/6** — background/terminated driver: system tray push appears
4. **FCM-3** — logout: token nulled in DB

After tests pass, open PR `feat/fcm-wiring` → `dev`.

### After FCM PR: Admin Panel Phase 3
Branch: `feat/admin-dashboard-phase3` (to be created from `dev`)

Three sub-features (see `docs/STAGING_LAUNCH_CHECKLIST.md` §2):
- **2a** Enhanced DB viewer — Filament Resources for `Location`, `RouteCache`, `RideRequest`, `DriverProfile`
- **2b** Live map view — custom Filament page with Mapbox GL JS showing active rides + driver positions
- **2c** Real-time WS monitor — Livewire/Alpine.js widget showing dispatch events, queue depth, active connections

### Then: UI/UX Polish → Pre-launch bugs → Sentry → Staging deploy → Android release

Full roadmap in `docs/STAGING_LAUNCH_CHECKLIST.md`.

---

## Known Bugs (Deferred)

| # | Description | Severity | File |
|---|-------------|----------|------|
| 1 | Pull-to-refresh while backend down shows Flutter error instead of hiding credit chip | Low | `mobile/lib/driver/screens/driver_home_screen.dart` |
| 2 | Session restore after mid-ride app kill leaves driver stuck on `ActiveRideScreen` | Medium | `mobile/lib/driver/screens/active_ride_screen.dart` |
| 3 | MB-10 speed-based adaptive intervals not tested — emulator only | Low | `mobile/lib/driver/screens/active_ride_screen.dart` |
| 4 | Admin tests A-17/A-18 not yet verified | Low | — |

---

## Dev Server Commands

```bash
# From backend/
php artisan serve          # http://127.0.0.1:8000
php artisan reverb:start   # WebSocket on :8080
php artisan queue:work     # Jobs + FCM sends ← MUST be running for FCM
php artisan schedule:work  # Stale driver kick + route cache cleanup

# Mobile — always pass Mapbox token:
flutter run --flavor rider -t lib/main_rider.dart \
  --dart-define=MAPBOX_ACCESS_TOKEN=pk.eyJ1...

flutter run --flavor driver -t lib/main_driver.dart \
  --dart-define=MAPBOX_ACCESS_TOKEN=pk.eyJ1...
```

> **Note:** `queue:work` MUST be running for FCM notifications to fire.
> FCM sends happen via `NotificationService` called directly from `MatchingQueueService`
> (synchronous, not queued) — but other queued jobs (ExpireRideRequest etc.) also trigger FCM.
