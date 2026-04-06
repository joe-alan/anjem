# Staging & Android Open Beta Launch Checklist

**Target:** Android open beta via Google Play Console
**Backend:** Laravel Forge managed server (Nginx + PHP-FPM + PostgreSQL + Redis)
**Status:** In progress

---

## Progress Overview

| Sprint                                                                          | Area     | Status         |
| ------------------------------------------------------------------------------- | -------- | -------------- |
| [1. FCM Push Notifications](#1-fcm-push-notifications)                          | Feature  | ✅ Done        |
| [2. Admin Panel Phase 3](#2-admin-panel-phase-3)                                | Feature  | ✅ Done        |
| [3. UI/UX Polish](#3-uiux-polish)                                               | Polish   | ✅ Done        |
| [4. Pre-Launch Bug Fixes](#4-pre-launch-bug-fixes)                              | Bugs     | ✅ Done        |
| [5. Backend Hardening](#5-backend-hardening)                                    | Security | 🔄 In progress |
| [6. Error Tracking (Sentry)](#6-error-tracking-sentry)                          | Ops      | ✅ Done        |
| [7. Staging Deploy (Forge)](#7-staging-deploy-forge)                            | Infra    | ⬜ Not started  |
| [8. Android Release Build](#8-android-release-build)                            | Mobile   | ✅ Done        |
| [9. Play Store Submission](#9-play-store-submission)                             | Launch   | ⬜ Not started  |
| [10. Secrets & Git Hygiene](#10-secrets--git-hygiene)                           | Security | ⬜ Not started  |
| [11. Admin Access (Cloudflare Zero Trust)](#11-admin-access-cloudflare-zero-trust) | Security | ⬜ Not started  |
| [12. Production Readiness](#12-production-readiness)                            | Ops      | ⬜ Not started  |

> **Status key:** ⬜ Not started · 🔄 In progress · ✅ Done · ⏭️ Deferred

---

## 1. FCM Push Notifications

> Plan: `archive/FCM_IMPLEMENTATION_PLAN.md`
> Branch: `feat/fcm-wiring` (merged to main)

| #    | Task                                                                             | Status |
| ---- | -------------------------------------------------------------------------------- | ------ |
| 1.1  | `sendNewRideRequestToDriver(RideRequest, User)` in `NotificationService.php`     | ✅     |
| 1.2  | Call FCM notification from `MatchingQueueService::dispatchToDriver()`             | ✅     |
| 1.3  | Flutter: request notification permission on app launch (rider + driver)           | ✅     |
| 1.4  | Flutter: retrieve FCM token and POST to `PATCH /v1/auth/fcm-token` after login   | ✅     |
| 1.5  | Flutter: handle foreground messages (`FirebaseMessaging.onMessage`)               | ✅     |
| 1.6  | Flutter: handle background messages (`onBackgroundMessage` top-level function)    | ✅     |
| 1.7  | Flutter: handle terminated-state tap (`getInitialMessage`)                        | ✅     |
| 1.8  | Flutter: add `flutter_local_notifications` for foreground display                | ✅     |
| 1.9  | Flutter: navigation routing on notification tap (deep link to correct screen)     | ✅     |
| 1.10 | Android: verify `google-services.json` present for both rider + driver flavors    | ✅     |
| 1.11 | Android: high-priority config for driver ride request notifications               | ✅     |
| 1.12 | Device test: rider receives push when app backgrounded/killed                     | ✅     |
| 1.13 | Device test: driver receives ride request push when app backgrounded/killed       | ✅     |

---

## 2. Admin Panel Phase 3

> Branch: `staging-sprint` (merged from `feat/admin-dashboard-phase3`)

### 2a. Enhanced DB Viewer

| #   | Task                                                                              | Status |
| --- | --------------------------------------------------------------------------------- | ------ |
| 2.1 | Filament Resource: `Location` — table with coordinates, beacon flag, usage count  | ✅     |
| 2.2 | Filament Resource: `RouteCache` — table with profile, distance, duration, age     | ✅     |
| 2.3 | Filament Resource: `RideRequest` — with relation to rider, driver, status timeline | ✅     |
| 2.4 | Filament Resource: `DriverProfile` — queue position, status, credit balance       | ✅     |
| 2.5 | Filament widgets: summary stats (active rides, online drivers, queue depth)        | ✅     |

### 2b. Live Map View

| #    | Task                                                                  | Status |
| ---- | --------------------------------------------------------------------- | ------ |
| 2.6  | Custom Filament page with embedded Mapbox GL JS                       | ✅     |
| 2.7  | Show all online drivers as markers (polling or WS)                    | ✅     |
| 2.8  | Show active rides with pickup/destination markers                     | ✅     |
| 2.9  | Draw route polylines for in-progress rides (from `route_geometry`)    | ✅     |
| 2.10 | Click a ride/driver to see detail panel                               | ✅     |

### 2c. Real-Time WebSocket Monitor

| #    | Task                                                                             | Status |
| ---- | -------------------------------------------------------------------------------- | ------ |
| 2.11 | LiveMonitoringPage: live event log (pending requests, active rides, online drivers) | ✅     |
| 2.12 | DriverQueuePage: queue depth, driver positions, wait times, cooldown status       | ✅     |
| 2.13 | SystemHealthPage: active Reverb channels + application metrics                    | ✅     |
| 2.14 | Dispatch timeline — which driver was offered which ride and when                  | ✅     |

---

## 3. UI/UX Polish

> Branch: `feat/ui-ux-polish` (merged to main)

| #   | Task                                                                        | Status |
| --- | --------------------------------------------------------------------------- | ------ |
| 3.1 | Audit and fix all known visual inconsistencies (fonts, colours, spacing)    | ✅     |
| 3.2 | Loading states — skeletons or shimmer instead of spinners where appropriate | ⏭️     |
| 3.3 | Empty states — meaningful illustrations/messages instead of blank screens   | ✅     |
| 3.4 | Error states — user-friendly messages instead of raw exception text         | ✅     |
| 3.5 | Ride history screen polish (rider + driver)                                 | ✅     |
| 3.6 | Onboarding flow review (KYC, first-launch experience)                      | ✅     |
| 3.7 | Pull-to-refresh: hide credit chip error when backend is down (known bug)   | ✅     |

---

## 4. Pre-Launch Bug Fixes

> Known bugs from `CONTINUE_HERE.md`

| #   | Bug                                                                            | Severity | Status |
| --- | ------------------------------------------------------------------------------ | -------- | ------ |
| 4.1 | Driver stuck on `ActiveRideScreen` after mid-ride app kill + session restore   | Medium   | ✅     |
| 4.2 | Admin tests A-17 / A-18 not yet verified                                       | Low      | ✅     |
| 4.3 | Audit 73 pre-existing test failures — identify real bugs vs env issues         | Medium   | ✅     |

---

## 5. Backend Hardening

| #    | Task                                                    | Notes                                                  | Status |
| ---- | ------------------------------------------------------- | ------------------------------------------------------ | ------ |
| 5.1  | API rate limiting on auth endpoints                     | `throttle:10,1` on `/auth` prefix                      | ✅     |
| 5.2  | API rate limiting on ride request endpoint              | `throttle:100,1` on `/requests`                        | ✅     |
| 5.3  | Verify all user inputs validated via Form Requests      | 6+ Form Request classes in use                         | ✅     |
| 5.4  | KYC document storage via Firebase Storage               | Use Firebase Storage instead of S3; set `FILESYSTEM_DISK` accordingly | 🔄     |
| 5.5  | Tighten CORS for production domain                      | Currently `*`; restrict to `api.anjem.me`              | 🔄     |
| 5.6  | Tighten Reverb `allowed_origins`                        | Currently `*` in `config/reverb.php`                   | ⬜     |
| 5.7  | Set `APP_DEBUG=false` and `APP_ENV=production` in prod  | Currently `true`/`local`                               | ⬜     |
| 5.8  | Set `CACHE_DRIVER=redis` in prod (currently `file`)     | File cache won't work multi-server                     | ⬜     |
| 5.9  | Secure Redis with password                              | `REDIS_PASSWORD=null` currently                        | ⬜     |
| 5.10 | Move `laravel/tinker` from `require` to `require-dev`   | Security risk in production                            | ⬜     |
| 5.11 | Enable session encryption                               | `SESSION_ENCRYPT=false` in `config/session.php`        | ⬜     |
| 5.12 | Replace Mailtrap with production mail (SES/Resend)      | Currently sandbox credentials                          | ⬜     |

---

## 6. Error Tracking (Sentry)

| #   | Task                                                                                                      | Status |
| --- | --------------------------------------------------------------------------------------------------------- | ------ |
| 6.1 | Create Sentry project (Laravel + Flutter)                                                                 | ✅     |
| 6.2 | Install `sentry/sentry-laravel` ^4.24 — DSN configured via `.env`                                        | ✅     |
| 6.3 | Install `sentry_flutter` ^9.16.0 — init in `main_rider.dart` + `main_driver.dart` with replay + screenshots | ✅     |
| 6.4 | Verify errors and crashes surface in Sentry dashboard                                                     | ✅     |
| 6.5 | Set up Sentry alerts for critical errors (unhandled exceptions, queue failures)                            | 🔄     |

---

## 7. Staging Deploy (Forge)

> Target: Laravel Forge + DigitalOcean/Hetzner droplet

| #    | Task                                                                         | Status |
| ---- | ---------------------------------------------------------------------------- | ------ |
| 7.1  | Create Forge account and connect cloud provider (DO or Hetzner)              | ⬜     |
| 7.2  | Provision server via Forge — Ubuntu 22.04, PHP 8.3, PostgreSQL, Redis, Nginx | ⬜     |
| 7.3  | Add PostGIS extension to PostgreSQL (`CREATE EXTENSION postgis;`)            | ⬜     |
| 7.4  | Connect GitHub repo to Forge site                                            | ⬜     |
| 7.5  | Configure `.env` in Forge (DB, Redis, Firebase, Mapbox, Sentry, Reverb)      | ⬜     |
| 7.6  | Configure Forge deploy script: `composer install --no-dev`, migrations, caches | ⬜     |
| 7.7  | Add Forge daemon for `php artisan reverb:start`                              | ⬜     |
| 7.8  | Add Forge queue worker for `php artisan queue:work`                          | ⬜     |
| 7.9  | Add Forge scheduler (cron) for `php artisan schedule:run`                    | ⬜     |
| 7.10 | Configure Nginx for Reverb WebSocket proxy (`/app` -> localhost:8080)        | ⬜     |
| 7.11 | SSL via Forge (Let's Encrypt) for API + WebSocket domain                     | ⬜     |
| 7.12 | Point staging domain (e.g., `api-staging.anjem.me`) at server                | ⬜     |
| 7.13 | Run seed: `php artisan db:seed` (admin user, campus beacons)                 | ⬜     |
| 7.14 | Smoke test API endpoints from Postman                                        | ⬜     |
| 7.15 | Smoke test mobile apps pointed at staging URL (HTTPS + WSS)                  | ⬜     |
| 7.16 | Set up Forge automatic DB backups                                            | ⬜     |

---

## 8. Android Release Build

| #   | Task                                                                  | Status |
| --- | --------------------------------------------------------------------- | ------ |
| 8.1 | Generate release keystore (`keytool -genkey ...`)                     | ✅     |
| 8.2 | Configure `android/key.properties` + `build.gradle.kts` signing config | ✅     |
| 8.3 | Store keystore securely (NOT in git — use password manager)           | 🔄     |
| 8.4 | Build rider release AAB                                               | ✅     |
| 8.5 | Build driver release AAB                                              | ✅     |
| 8.6 | Install and smoke test release builds on physical devices             | ✅     |
| 8.7 | Verify Mapbox token restrictions (bundle ID whitelist on Mapbox Dashboard) | ⬜     |

---

## 9. Play Store Submission

| #   | Task                                                                 | Status |
| --- | -------------------------------------------------------------------- | ------ |
| 9.1 | Create Google Play Console developer account (if not already)        | ⬜     |
| 9.2 | Create two app listings: Anjem Rider + Anjem Driver                  | ⬜     |
| 9.3 | Write store descriptions, upload screenshots, feature graphic        | ⬜     |
| 9.4 | Privacy policy URL (can host on GitHub Pages or simple static site)  | ⬜     |
| 9.5 | Complete Play Store content rating questionnaire                     | ⬜     |
| 9.6 | Upload AABs to internal test track first -> promote to open beta     | ⬜     |
| 9.7 | Enrol in Google Play App Signing (Google manages release key)        | ⬜     |

---

## 10. Secrets & Git Hygiene

> **CRITICAL** — the repo currently has secrets committed to git history.
> These must be cleaned up before any public/shared access.

### 10a. Remove tracked secrets from git

| #    | Task                                                                                    | Status |
| ---- | --------------------------------------------------------------------------------------- | ------ |
| 10.1 | `git rm --cached backend/.env backend/.env.testing` (stop tracking)                     | ⬜     |
| 10.2 | Remove `upload-keystore.jks` from git, add `*.jks` to `.gitignore`                      | ⬜     |
| 10.3 | Remove `mobile/android/key.properties` from git (already in `.gitignore` but was tracked) | ⬜     |
| 10.4 | Scrub secrets from git history with `git filter-repo` (or accept risk for private repo) | ⬜     |

### 10b. Rotate exposed credentials

| #    | Task                                                            | Status |
| ---- | --------------------------------------------------------------- | ------ |
| 10.5 | Rotate database password (`irisdotd` is exposed)                | ⬜     |
| 10.6 | Regenerate `APP_KEY`                                            | ⬜     |
| 10.7 | Regenerate Reverb app key + secret                              | ⬜     |
| 10.8 | Rotate Mapbox secret token (`sk.eyJ1...` in `gradle.properties`) | ⬜     |
| 10.9 | Rotate Mailtrap credentials (or replace with prod mail service) | ⬜     |

### 10c. Externalize remaining hardcoded values

| #     | Task                                                                               | Status |
| ----- | ---------------------------------------------------------------------------------- | ------ |
| 10.10 | Move Sentry DSN from hardcoded const to `--dart-define` in `main_rider/driver.dart` | ⬜     |
| 10.11 | Move Mapbox download token out of `gradle.properties` into CI secrets              | ⬜     |
| 10.12 | Ensure API/WS URL defaults are `https`/`wss` (currently `http`/`ws`)               | ⬜     |

---

## 11. Admin Access (Cloudflare Zero Trust)

> Protect the Filament admin panel behind Cloudflare Access so only authorized team members can reach it.

| #    | Task                                                                                       | Status |
| ---- | ------------------------------------------------------------------------------------------ | ------ |
| 11.1 | Add site to Cloudflare (DNS for `anjem.me` or staging domain)                              | ⬜     |
| 11.2 | Enable Cloudflare Zero Trust (free plan covers up to 50 users)                             | ⬜     |
| 11.3 | Create Access Application for admin path (`api-staging.anjem.me/admin/*`)                  | ⬜     |
| 11.4 | Configure Access Policy — allow by email (team members only)                               | ⬜     |
| 11.5 | Verify: unauthenticated requests to `/admin` get Cloudflare login wall                     | ⬜     |
| 11.6 | Optional: validate Cloudflare `Cf-Access-Jwt-Assertion` header in Laravel middleware       | ⬜     |

---

## 12. Production Readiness

> Catch-all for items that don't fit above but affect whether the app is launch-safe.

### 12a. Mobile hardening

| #    | Task                                                                                 | Status |
| ---- | ------------------------------------------------------------------------------------ | ------ |
| 12.1 | Replace all `print()` with `debugPrint()` (355+ occurrences leak logs in release)    | ⬜     |
| 12.2 | Add `network_security_config.xml` to disable cleartext traffic                       | ⬜     |
| 12.3 | Set `sendDefaultPii = false` in Sentry config (privacy/GDPR)                        | ⬜     |
| 12.4 | Lower Sentry replay `sessionSampleRate` for production (currently 10%)               | ⬜     |

### 12b. Backend ops

| #    | Task                                                                                 | Status |
| ---- | ------------------------------------------------------------------------------------ | ------ |
| 12.5 | Enhance health endpoint to check DB + Redis connectivity                             | ⬜     |
| 12.6 | Fix N+1 query in `AdminController` line ~480 (missing `with('driverProfile')`)       | ⬜     |
| 12.7 | Configure `LOG_CHANNEL=daily` for production (currently `single`)                    | ⬜     |
| 12.8 | Add `$tries` and `$timeout` to queue jobs (`ExpireRideRequest`, etc.)                | ⬜     |

### 12c. Governance

| #     | Task                                                                         | Status |
| ----- | ---------------------------------------------------------------------------- | ------ |
| 12.9  | Enable GitHub branch protection on `main` (require PR + status checks)       | ⬜     |
| 12.10 | Update `dependabot.yml` reviewer placeholder with real GitHub username        | ⬜     |
| 12.11 | Disable Dependabot until post-launch (or set to security-only)               | ⬜     |

---

## Known Deferred Items (Post-Beta)

| Item                              | Reason deferred                                          |
| --------------------------------- | -------------------------------------------------------- |
| iOS support                       | Android-only open beta                                   |
| MB-10 speed-based adaptive intervals | Emulator only — test with physical driving            |
| Rider suspend: full WS/location severance | Low priority for beta                            |
| Rating/review system              | Post-beta feature                                        |
| Surge pricing                     | Post-beta feature                                        |
| In-app driver/rider chat          | Post-beta feature                                        |
| CI/CD pipeline refinement         | `deploy-staging.yml` exists; refine post-beta            |
| Horizontal Reverb scaling         | Single server sufficient for campus scale                |
| Admin 2FA                         | Cloudflare Zero Trust covers access control for now      |
| Production deploy workflow        | Staging-first; production workflow after beta validation  |
