# Staging & Android Open Beta Launch Checklist

**Target:** Android open beta via Google Play Console
**Backend:** Single-server VPS (Nginx + Supervisor + PostgreSQL + Redis)
**Status:** In progress

---

## Progress Overview

| Sprint | Area | Status |
|--------|------|--------|
| [1. FCM Push Notifications](#1-fcm-push-notifications) | Feature | ⬜ Not started |
| [2. Admin Panel Phase 3](#2-admin-panel-phase-3) | Feature | ⬜ Not started |
| [3. UI/UX Polish](#3-uiux-polish) | Polish | ⬜ Not started |
| [4. Pre-Launch Bug Fixes](#4-pre-launch-bug-fixes) | Bugs | ⬜ Not started |
| [5. Backend Hardening](#5-backend-hardening) | Security | ⬜ Not started |
| [6. Error Tracking (Sentry)](#6-error-tracking-sentry) | Ops | ⬜ Not started |
| [7. Staging Deploy](#7-staging-deploy) | Infra | ⬜ Not started |
| [8. Android Release Build](#8-android-release-build) | Mobile | ⬜ Not started |
| [9. Play Store Submission](#9-play-store-submission) | Launch | ⬜ Not started |

> **Status key:** ⬜ Not started · 🔄 In progress · ✅ Done · ⏭️ Deferred

---

## 1. FCM Push Notifications

> Plan: `FCM_IMPLEMENTATION_PLAN.md`
> Branch: `feat/fcm-notifications` (to be created)

| # | Task | Status |
|---|------|--------|
| 1.1 | Fix `sendRideRequestToDriver` signature — add `RideRequest`-based overload in `NotificationService.php` | ⬜ |
| 1.2 | Call FCM notification from `MatchingQueueService::dispatchToDriver()` | ⬜ |
| 1.3 | Flutter: request notification permission on app launch (rider + driver) | ⬜ |
| 1.4 | Flutter: retrieve FCM token and POST to `PATCH /v1/auth/fcm-token` after login | ⬜ |
| 1.5 | Flutter: handle foreground messages (`FirebaseMessaging.onMessage`) | ⬜ |
| 1.6 | Flutter: handle background messages (`onBackgroundMessage` top-level function) | ⬜ |
| 1.7 | Flutter: handle terminated-state tap (`getInitialMessage`) | ⬜ |
| 1.8 | Flutter: add `flutter_local_notifications` for foreground display | ⬜ |
| 1.9 | Flutter: navigation routing on notification tap (deep link to correct screen) | ⬜ |
| 1.10 | Android: verify `google-services.json` present for both rider + driver flavors | ⬜ |
| 1.11 | Android: high-priority config for driver ride request notifications | ⬜ |
| 1.12 | Device test: rider receives push when app backgrounded/killed | ⬜ |
| 1.13 | Device test: driver receives ride request push when app backgrounded/killed | ⬜ |

---

## 2. Admin Panel Phase 3

> Branch: `feat/admin-dashboard-phase3` (to be created)

### 2a. Enhanced DB Viewer

| # | Task | Status |
|---|------|--------|
| 2.1 | Filament Resource: `Location` — table with coordinates, beacon flag, usage count | ⬜ |
| 2.2 | Filament Resource: `RouteCache` — table with profile, distance, duration, age | ⬜ |
| 2.3 | Filament Resource: `RideRequest` — with relation to rider, driver, status timeline | ⬜ |
| 2.4 | Filament Resource: `DriverProfile` — queue position, status, credit balance | ⬜ |
| 2.5 | Filament widgets: summary stats (active rides, online drivers, queue depth) | ⬜ |

### 2b. Live Map View

| # | Task | Status |
|---|------|--------|
| 2.6 | Custom Filament page with embedded Mapbox GL JS | ⬜ |
| 2.7 | Show all online drivers as markers (polling or WS) | ⬜ |
| 2.8 | Show active rides with pickup/destination markers | ⬜ |
| 2.9 | Draw route polylines for in-progress rides (from `route_geometry`) | ⬜ |
| 2.10 | Click a ride/driver to see detail panel | ⬜ |

### 2c. Real-Time WebSocket Monitor

| # | Task | Status |
|---|------|--------|
| 2.11 | Livewire/Alpine.js widget: live event log (dispatch, accept, complete, cancel) | ⬜ |
| 2.12 | Queue depth widget — how many drivers in queue, wait times | ⬜ |
| 2.13 | Active WebSocket connections count (from Reverb) | ⬜ |
| 2.14 | Dispatch timeline — which driver was offered which ride and when | ⬜ |

---

## 3. UI/UX Polish

> Branch: `feat/ui-polish` (to be created)

| # | Task | Status |
|---|------|--------|
| 3.1 | Audit and fix all known visual inconsistencies (fonts, colours, spacing) | ⬜ |
| 3.2 | Loading states — skeletons or shimmer instead of spinners where appropriate | ⬜ |
| 3.3 | Empty states — meaningful illustrations/messages instead of blank screens | ⬜ |
| 3.4 | Error states — user-friendly messages instead of raw exception text | ⬜ |
| 3.5 | Ride history screen polish (rider + driver) | ⬜ |
| 3.6 | Onboarding flow review (KYC, first-launch experience) | ⬜ |
| 3.7 | Pull-to-refresh: hide credit chip error when backend is down (known bug) | ⬜ |

---

## 4. Pre-Launch Bug Fixes

> Known bugs from `CONTINUE_HERE.md`

| # | Bug | Severity | Status |
|---|-----|----------|--------|
| 4.1 | Driver stuck on `ActiveRideScreen` after mid-ride app kill + session restore | Medium | ⬜ |
| 4.2 | Admin tests A-17 / A-18 not yet verified | Low | ⬜ |
| 4.3 | Audit 73 pre-existing test failures — identify real bugs vs env issues | Medium | ⬜ |

---

## 5. Backend Hardening

| # | Task | Notes | Status |
|---|------|-------|--------|
| 5.1 | API rate limiting on auth endpoints (`/login`, `/register`, `/fcm-token`) | Laravel `throttle` middleware | ⬜ |
| 5.2 | API rate limiting on ride request endpoint (`POST /requests`) | Prevent spam requests | ⬜ |
| 5.3 | Verify all user inputs validated via Form Requests | Spot-check controllers | ⬜ |
| 5.4 | Ensure KYC document storage uses S3-compatible bucket (not local disk) | Files lost on server wipe | ⬜ |
| 5.5 | Review and tighten CORS config for production domain | `config/cors.php` | ⬜ |

---

## 6. Error Tracking (Sentry)

| # | Task | Status |
|---|------|--------|
| 6.1 | Create Sentry project (Laravel + Flutter) | ⬜ |
| 6.2 | Install `sentry/sentry-laravel` — configure DSN in `.env` | ⬜ |
| 6.3 | Install `sentry_flutter` — init in `main_rider.dart` + `main_driver.dart` | ⬜ |
| 6.4 | Verify errors and crashes surface in Sentry dashboard | ⬜ |
| 6.5 | Set up Sentry alerts for critical errors (unhandled exceptions, queue failures) | ⬜ |

---

## 7. Staging Deploy

> Target: cheap VPS (DigitalOcean Droplet, Hetzner CX21, or similar)

| # | Task | Status |
|---|------|--------|
| 7.1 | Provision VPS — Ubuntu 22.04, min 2 vCPU / 4 GB RAM | ⬜ |
| 7.2 | Install: Nginx, PHP 8.3, PostgreSQL + PostGIS extension, Redis | ⬜ |
| 7.3 | Clone repo, configure `.env` (DB, Redis, Firebase, Mapbox, Sentry DSNs) | ⬜ |
| 7.4 | Run `php artisan migrate --force` | ⬜ |
| 7.5 | Configure Supervisor: `queue:work`, `reverb:start`, `schedule:work` | ⬜ |
| 7.6 | Configure Nginx: reverse proxy to `php artisan serve` or PHP-FPM | ⬜ |
| 7.7 | SSL: Let's Encrypt via Certbot | ⬜ |
| 7.8 | Point staging domain at server | ⬜ |
| 7.9 | Run seed: `php artisan db:seed` (admin user, campus beacons) | ⬜ |
| 7.10 | Smoke test all major endpoints from Postman | ⬜ |
| 7.11 | Smoke test mobile apps pointed at staging URL | ⬜ |
| 7.12 | Set up daily DB backups (pg_dump → S3 or remote storage) | ⬜ |

---

## 8. Android Release Build

| # | Task | Status |
|---|------|--------|
| 8.1 | Generate release keystore (`keytool -genkey ...`) | ⬜ |
| 8.2 | Configure `android/key.properties` + `build.gradle` signing config | ⬜ |
| 8.3 | Store keystore securely (NOT in git — use password manager) | ⬜ |
| 8.4 | Build rider release AAB: `flutter build appbundle --flavor rider -t lib/main_rider.dart --dart-define=MAPBOX_ACCESS_TOKEN=...` | ⬜ |
| 8.5 | Build driver release AAB: same for driver flavor | ⬜ |
| 8.6 | Install and smoke test release builds on physical devices | ⬜ |
| 8.7 | Verify Mapbox token restrictions (bundle ID whitelist on Mapbox Dashboard) | ⬜ |

---

## 9. Play Store Submission

| # | Task | Status |
|---|------|--------|
| 9.1 | Create Google Play Console developer account (if not already) | ⬜ |
| 9.2 | Create two app listings: Anjem Rider + Anjem Driver | ⬜ |
| 9.3 | Write store descriptions, upload screenshots, feature graphic | ⬜ |
| 9.4 | Privacy policy URL (can host on GitHub Pages or simple static site) | ⬜ |
| 9.5 | Complete Play Store content rating questionnaire | ⬜ |
| 9.6 | Upload AABs to internal test track first → promote to open beta | ⬜ |
| 9.7 | Set up Firebase Crashlytics for post-launch crash reporting (optional but recommended) | ⬜ |

---

## Known Deferred Items (Post-Beta)

| Item | Reason deferred |
|------|----------------|
| iOS support | Android-only open beta |
| MB-10 speed-based adaptive intervals | Emulator only — test with physical driving |
| Rider suspend: full WS/location severance | Low priority for beta |
| Rating/review system | Post-beta feature |
| Surge pricing | Post-beta feature |
| In-app driver/rider chat | Post-beta feature |
| CI/CD pipeline | Manual deploys acceptable for beta scale |
| Horizontal Reverb scaling | Single server sufficient for campus scale |
