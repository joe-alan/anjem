# Staging & Android Open Beta Launch Checklist

**Target:** Android open beta via Google Play Console
**Backend:** Laravel Forge managed server (Nginx + PHP-FPM + PostgreSQL + Redis)
**Status:** In progress

---

## Progress Overview

| Sprint                                                                             | Area     | Status          |
| ---------------------------------------------------------------------------------- | -------- | --------------- |
| [1. FCM Push Notifications](#1-fcm-push-notifications)                             | Feature  | ✅ Done         |
| [2. Admin Panel Phase 3](#2-admin-panel-phase-3)                                   | Feature  | ✅ Done         |
| [3. UI/UX Polish](#3-uiux-polish)                                                  | Polish   | ✅ Done         |
| [4. Pre-Launch Bug Fixes](#4-pre-launch-bug-fixes)                                 | Bugs     | ✅ Done         |
| [5. Backend Hardening](#5-backend-hardening)                                       | Security | ✅ Done         |
| [6. Error Tracking (Sentry)](#6-error-tracking-sentry)                             | Ops      | ✅ Done         |
| [7. Production Deploy (Forge)](#7-production-deploy-forge)                         | Infra    | ✅ Done         |
| [8. Android Release Build](#8-android-release-build)                               | Mobile   | 🔄 Rebuild AABs |
| [9. Play Store Submission](#9-play-store-submission)                               | Launch   | ⬜ Not started  |
| [10. Secrets & Git Hygiene](#10-secrets--git-hygiene)                              | Security | ⏭️ Deferred     |
| [11. Admin Access (Cloudflare Zero Trust)](#11-admin-access-cloudflare-zero-trust) | Security | ⏭️ Deferred     |
| [12. Production Readiness](#12-production-readiness)                               | Ops      | ⏭️ Deferred     |

> **Status key:** ⬜ Not started · 🔄 In progress · ✅ Done · ⏭️ Deferred

---

## 1. FCM Push Notifications

> Plan: `archive/FCM_IMPLEMENTATION_PLAN.md`
> Branch: `feat/fcm-wiring` (merged to main)

| #    | Task                                                                           | Status |
| ---- | ------------------------------------------------------------------------------ | ------ |
| 1.1  | `sendNewRideRequestToDriver(RideRequest, User)` in `NotificationService.php`   | ✅     |
| 1.2  | Call FCM notification from `MatchingQueueService::dispatchToDriver()`          | ✅     |
| 1.3  | Flutter: request notification permission on app launch (rider + driver)        | ✅     |
| 1.4  | Flutter: retrieve FCM token and POST to `PATCH /v1/auth/fcm-token` after login | ✅     |
| 1.5  | Flutter: handle foreground messages (`FirebaseMessaging.onMessage`)            | ✅     |
| 1.6  | Flutter: handle background messages (`onBackgroundMessage` top-level function) | ✅     |
| 1.7  | Flutter: handle terminated-state tap (`getInitialMessage`)                     | ✅     |
| 1.8  | Flutter: add `flutter_local_notifications` for foreground display              | ✅     |
| 1.9  | Flutter: navigation routing on notification tap (deep link to correct screen)  | ✅     |
| 1.10 | Android: verify `google-services.json` present for both rider + driver flavors | ✅     |
| 1.11 | Android: high-priority config for driver ride request notifications            | ✅     |
| 1.12 | Device test: rider receives push when app backgrounded/killed                  | ✅     |
| 1.13 | Device test: driver receives ride request push when app backgrounded/killed    | ✅     |

---

## 2. Admin Panel Phase 3

> Branch: `staging-sprint` (merged from `feat/admin-dashboard-phase3`)

### 2a. Enhanced DB Viewer

| #   | Task                                                                               | Status |
| --- | ---------------------------------------------------------------------------------- | ------ |
| 2.1 | Filament Resource: `Location` — table with coordinates, beacon flag, usage count   | ✅     |
| 2.2 | Filament Resource: `RouteCache` — table with profile, distance, duration, age      | ✅     |
| 2.3 | Filament Resource: `RideRequest` — with relation to rider, driver, status timeline | ✅     |
| 2.4 | Filament Resource: `DriverProfile` — queue position, status, credit balance        | ✅     |
| 2.5 | Filament widgets: summary stats (active rides, online drivers, queue depth)        | ✅     |

### 2b. Live Map View

| #    | Task                                                               | Status |
| ---- | ------------------------------------------------------------------ | ------ |
| 2.6  | Custom Filament page with embedded Mapbox GL JS                    | ✅     |
| 2.7  | Show all online drivers as markers (polling or WS)                 | ✅     |
| 2.8  | Show active rides with pickup/destination markers                  | ✅     |
| 2.9  | Draw route polylines for in-progress rides (from `route_geometry`) | ✅     |
| 2.10 | Click a ride/driver to see detail panel                            | ✅     |

### 2c. Real-Time WebSocket Monitor

| #    | Task                                                                                | Status |
| ---- | ----------------------------------------------------------------------------------- | ------ |
| 2.11 | LiveMonitoringPage: live event log (pending requests, active rides, online drivers) | ✅     |
| 2.12 | DriverQueuePage: queue depth, driver positions, wait times, cooldown status         | ✅     |
| 2.13 | SystemHealthPage: active Reverb channels + application metrics                      | ✅     |
| 2.14 | Dispatch timeline — which driver was offered which ride and when                    | ✅     |

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
| 3.6 | Onboarding flow review (KYC, first-launch experience)                       | ✅     |
| 3.7 | Pull-to-refresh: hide credit chip error when backend is down (known bug)    | ✅     |

---

## 4. Pre-Launch Bug Fixes

> Known bugs from `CONTINUE_HERE.md`

| #   | Bug                                                                          | Severity | Status |
| --- | ---------------------------------------------------------------------------- | -------- | ------ |
| 4.1 | Driver stuck on `ActiveRideScreen` after mid-ride app kill + session restore | Medium   | ✅     |
| 4.2 | Admin tests A-17 / A-18 not yet verified                                     | Low      | ✅     |
| 4.3 | Audit 73 pre-existing test failures — identify real bugs vs env issues       | Medium   | ✅     |

---

## 5. Backend Hardening

| #    | Task                                                                            | Notes                                                                  | Status |
| ---- | ------------------------------------------------------------------------------- | ---------------------------------------------------------------------- | ------ |
| 5.1  | API rate limiting on auth endpoints                                             | `throttle:10,1` on `/auth` prefix                                      | ✅     |
| 5.2  | API rate limiting on ride request endpoint                                      | `throttle:100,1` on `/requests`                                        | ✅     |
| 5.3  | Verify all user inputs validated via Form Requests                              | 6+ Form Request classes in use                                         | ✅     |
| 5.4  | KYC document storage via Firebase Storage                                       | Uploads compressed to Firebase Storage, deletion on KYC reject/approve | ✅     |
| 5.5  | Tighten CORS for production domain                                              | Deferred — native apps don't use CORS, admin is same-origin            | ⏭️     |
| 5.13 | Account deletion (GDPR/PDP compliance)                                          | Soft-delete + PII anonymization, FK cascades set to nullOnDelete       | ✅     |
| 5.14 | Fix `rides.driver_id` FK (was referencing `driver_profiles` instead of `users`) | Latent schema bug, now corrected                                       | ✅     |
| 5.6  | Tighten Reverb `allowed_origins`                                                | Deferred — native apps don't send Origin headers                       | ⏭️     |
| 5.7  | Set `APP_DEBUG=false` and `APP_ENV=production` in prod                          | Confirmed set on Forge                                                 | ✅     |
| 5.8  | Set `CACHE_DRIVER=redis` in prod (currently `file`)                             | Confirmed set on Forge                                                 | ✅     |
| 5.9  | Secure Redis with password                                                      | Deferred — single-server, localhost-only                               | ⏭️     |
| 5.10 | Move `laravel/tinker` from `require` to `require-dev`                           | Already in `require-dev`, not installed on prod                        | ✅     |
| 5.11 | Enable session encryption                                                       | Config defaults to `true`, no override needed                          | ✅     |
| 5.12 | Production mail (Mailtrap transactional)                                        | `live.smtp.mailtrap.io:2525`, domain verified, `no-reply@anjem.me`     | ✅     |
| 5.15 | Switch OTP email to `Mail::queue()` via Horizon                                 | Was `Mail::send()` (synchronous), now queued for faster API response   | ✅     |
| 5.16 | OTP resend with backend rate limiting (60s cooldown, 5/hr cap)                  | Backend enforces, Flutter shows countdown timer                        | ✅     |
| 5.17 | Nginx rate limiting on `/admin` path                                            | `limit_req zone=admin` at 5r/s with burst=5                            | ✅     |

---

## 6. Error Tracking (Sentry)

| #   | Task                                                                                                        | Status |
| --- | ----------------------------------------------------------------------------------------------------------- | ------ |
| 6.1 | Create Sentry project (Laravel + Flutter)                                                                   | ✅     |
| 6.2 | Install `sentry/sentry-laravel` ^4.24 — DSN configured via `.env`                                           | ✅     |
| 6.3 | Install `sentry_flutter` ^9.16.0 — init in `main_rider.dart` + `main_driver.dart` with replay + screenshots | ✅     |
| 6.4 | Verify errors and crashes surface in Sentry dashboard                                                       | ✅     |
| 6.5 | Set up Sentry alerts for critical errors (unhandled exceptions, queue failures)                             | 🔄     |

---

## 7. Production Deploy (Forge)

> Target: Laravel Forge (Hobby, $12/mo) + DigitalOcean droplet (4GB/2vCPU, $24/mo, Singapore)
> Single prod server — no staging. Local dev → deploy to prod.
> Domain: `api.anjem.me` (API), `ws.anjem.me` (WebSocket)

| #    | Task                                                                                       | Status |
| ---- | ------------------------------------------------------------------------------------------ | ------ |
| 7.1  | Create Forge account, connect DO via API token                                             | ✅     |
| 7.2  | Provision server — Ubuntu, PHP 8.4, PostgreSQL 17, Redis, Nginx                            | ✅     |
| 7.3  | Install PostGIS (`postgresql-17-postgis-3`, extension enabled on `forge` database)         | ✅     |
| 7.4  | Connect GitHub repo, deploy key, root directory set to `/backend`                          | ✅     |
| 7.5  | Configure `.env` (DB, Redis, Firebase, Mapbox, Sentry, Reverb, Mailtrap transactional)     | ✅     |
| 7.6  | Deploy script: zero-downtime releases, `composer install --no-dev`, `optimize`, migrations | ✅     |
| 7.7  | Reverb daemon via Forge Laravel toggle (port 8080)                                         | ✅     |
| 7.8  | Horizon daemon via Forge Laravel toggle (replaces raw `queue:work`)                        | ✅     |
| 7.9  | Scheduler via Forge Laravel toggle                                                         | ✅     |
| 7.10 | Reverb Nginx proxy handled by Forge toggle (auto-configured for `ws.anjem.me`)             | ✅     |
| 7.11 | SSL: `ws.anjem.me` via Let's Encrypt HTTP-01, `api.anjem.me` via Let's Encrypt DNS-01      | ✅     |
| 7.12 | DNS: `api.anjem.me` + `ws.anjem.me` → `165.232.172.42` (Cloudflare grey cloud)             | ✅     |
| 7.13 | Run `php8.4 artisan db:seed --force` (admin user, campus beacons)                          | ✅     |
| 7.14 | Smoke test API from Postman — health, public, auth-protected endpoints confirmed           | ✅     |
| 7.15 | Smoke test Flutter app against prod (full E2E: auth, KYC, ride flow, WebSocket, FCM)       | ✅     |
| 7.16 | Daily DB backups via cron (`pg_dump` at 3 AM WIB, 7-day retention on server)               | ✅     |

**Notes:**

- DB name: `forge` (Forge default, PostGIS enabled here)
- DB user: created via Forge Database tab (initial `jonathanalanohasiholan` user failed auth)
- Redis client: `phpredis` (not predis — Forge installs the extension by default)
- Mailtrap: transactional SMTP (`live.smtp.mailtrap.io:2525`), `anjem.me` domain verified with SPF/DKIM/DMARC. Port 587 blocked by DigitalOcean (default outbound SMTP restriction on new droplets), port 2525 works as alternative.
- Filament admin: stays at `api.anjem.me/admin`, protected by Filament auth + Nginx rate limiting (5r/s)
- Cloudflare Zero Trust deferred — payment method (international debit card) declined during free plan signup. Revisit when card situation resolves.
- Let's Encrypt auto-renewal for `api.anjem.me` may need Nginx fix — HTTP-01 challenge returned 404 due to `/backend` subdirectory + zero-downtime release path mismatch. Used DNS-01 as one-time workaround. Cert expires in ~90 days; fix before then or renew manually via DNS-01 again.
- `composer.lock` was missing from git — caused Forge to not detect Laravel packages (no Application toggles). Committed to fix.
- Prod database accessible from local via Postico 2 (SSH tunnel as `forge` user with ed25519 key)
- Backups stored on-server at `/home/forge/backups/` — periodically `scp` to local machine for off-server safety

**Smoke test results (7.14–7.15):**

| Endpoint / Flow                   | Result                                                 |
| --------------------------------- | ------------------------------------------------------ |
| `GET /api/v1/health`              | 200 — `{"status":"ok","version":"1.0.0"}`              |
| `GET /api/v1/places/search?q=USU` | 200 — 7 results, fuzzy matching working                |
| `GET /api/v1/locations`           | 401 — correctly requires auth                          |
| Google sign-in (rider)            | ✅ Token received, user created                        |
| Google sign-in (driver)           | ✅ Token received, role upgraded to `both`             |
| WebSocket connection              | ✅ Both apps connect to `wss://ws.anjem.me`            |
| KYC submission + Firebase upload  | ✅ Photos stored, email OTP sent and verified          |
| Full ride flow (E2E)              | ✅ Request → accept → arrive → in_progress → completed |
| FCM push notifications            | ✅ Driver receives ride request push                   |
| Credit deduction on accept        | ✅ Working                                             |
| Post-ride rating                  | ✅ Working                                             |
| Rider cancellation + penalty      | ✅ Working                                             |
| App kill + session resume         | ✅ `GET /session/resume` restores active ride          |
| Account deletion + re-signup      | ✅ PII wiped, re-signup works                          |

---

## 8. Android Release Build

| #   | Task                                                                       | Status |
| --- | -------------------------------------------------------------------------- | ------ |
| 8.1 | Generate release keystore (`keytool -genkey ...`)                          | ✅     |
| 8.2 | Configure `android/key.properties` + `build.gradle.kts` signing config     | ✅     |
| 8.3 | Store keystore securely (NOT in git — use password manager)                | ⏭️     |
| 8.4 | Build rider release AAB                                                    | 🔄     |
| 8.5 | Build driver release AAB                                                   | 🔄     |
| 8.6 | Install and smoke test release builds on physical devices                  | 🔄     |
| 8.7 | Verify Mapbox token restrictions (bundle ID whitelist on Mapbox Dashboard) | ⏭️     |

---

## 9. Play Store Submission

| #   | Task                                                                | Status |
| --- | ------------------------------------------------------------------- | ------ |
| 9.1 | Create Google Play Console developer account (if not already)       | ⬜     |
| 9.2 | Create two app listings: Anjem Rider + Anjem Driver                 | ⬜     |
| 9.3 | Write store descriptions, upload screenshots, feature graphic       | ⬜     |
| 9.4 | Privacy policy URL (can host on GitHub Pages or simple static site) | ⬜     |
| 9.5 | Complete Play Store content rating questionnaire                    | ⬜     |
| 9.6 | Upload AABs to internal test track first -> promote to open beta    | ⬜     |
| 9.7 | Enrol in Google Play App Signing (Google manages release key)       | ⬜     |

---

## 10. Secrets & Git Hygiene

> **⏭️ DEFERRED** — repo is private, no shared access. Acceptable risk for beta.
> Revisit before making repo public or adding external contributors.

### 10a. Remove tracked secrets from git

| #    | Task                                                                                      | Status |
| ---- | ----------------------------------------------------------------------------------------- | ------ |
| 10.1 | `git rm --cached backend/.env backend/.env.testing` (stop tracking)                       | ⏭️     |
| 10.2 | Remove `upload-keystore.jks` from git, add `*.jks` to `.gitignore`                        | ⏭️     |
| 10.3 | Remove `mobile/android/key.properties` from git (already in `.gitignore` but was tracked) | ⏭️     |
| 10.4 | Scrub secrets from git history with `git filter-repo` (or accept risk for private repo)   | ⏭️     |

### 10b. Rotate exposed credentials

| #    | Task                                                             | Status |
| ---- | ---------------------------------------------------------------- | ------ |
| 10.5 | Rotate database password (`irisdotd` is exposed)                 | ⏭️     |
| 10.6 | Regenerate `APP_KEY`                                             | ⏭️     |
| 10.7 | Regenerate Reverb app key + secret                               | ⏭️     |
| 10.8 | Rotate Mapbox secret token (`sk.eyJ1...` in `gradle.properties`) | ⏭️     |
| 10.9 | Rotate Mailtrap credentials (or replace with prod mail service)  | ⏭️     |

### 10c. Externalize remaining hardcoded values

| #     | Task                                                                                | Status |
| ----- | ----------------------------------------------------------------------------------- | ------ |
| 10.10 | Move Sentry DSN from hardcoded const to `--dart-define` in `main_rider/driver.dart` | ⏭️     |
| 10.11 | Move Mapbox download token out of `gradle.properties` into CI secrets               | ⏭️     |
| 10.12 | Ensure API/WS URL defaults are `https`/`wss` (currently `http`/`ws`)                | ⏭️     |

---

## 11. Admin Access (Cloudflare Zero Trust)

> **⏭️ DEFERRED** — Cloudflare Zero Trust free plan requires a payment method on file.
> International debit card was declined during signup. Revisit when card situation resolves.
>
> **Current mitigation:** Filament admin at `api.anjem.me/admin` protected by:
>
> 1. Filament's built-in email/password auth
> 2. Nginx rate limiting on `/admin` path (5r/s, burst=5) to prevent brute force
>
> **Original plan was to isolate admin to `admin.anjem.me` behind Cloudflare Access:**
>
> - Created `admin.anjem.me` DNS record (orange cloud), Cloudflare Origin Certificate, Nginx server block
> - Added `->domain('admin.anjem.me')` to Filament `AdminPanelProvider`
> - All reverted after payment method failed — DNS record deleted, Nginx block removed, code change reverted
>
> **Future hardening path (post-beta):** Cloudflare Access → Filament 2FA → VPN-only access

| #    | Task                                                                                 | Status |
| ---- | ------------------------------------------------------------------------------------ | ------ |
| 11.1 | Add site to Cloudflare (DNS for `anjem.me` or staging domain)                        | ✅     |
| 11.2 | Enable Cloudflare Zero Trust (free plan covers up to 50 users)                       | ⏭️     |
| 11.3 | Create Access Application for admin path                                             | ⏭️     |
| 11.4 | Configure Access Policy — allow by email (team members only)                         | ⏭️     |
| 11.5 | Verify: unauthenticated requests to `/admin` get Cloudflare login wall               | ⏭️     |
| 11.6 | Optional: validate Cloudflare `Cf-Access-Jwt-Assertion` header in Laravel middleware | ⏭️     |

---

## 12. Production Readiness

> **⏭️ DEFERRED** — none of these block launch. Revisit post-beta.

### 12a. Mobile hardening

| #    | Task                                                                              | Status |
| ---- | --------------------------------------------------------------------------------- | ------ |
| 12.1 | Replace all `print()` with `debugPrint()` (355+ occurrences leak logs in release) | ✅     |
| 12.2 | Add `network_security_config.xml` to disable cleartext traffic                    | ✅     |
| 12.3 | Set `sendDefaultPii = false` in Sentry config (privacy/GDPR)                      | ✅     |
| 12.4 | Lower Sentry replay `sessionSampleRate` for production (currently 10%)            | ⏭️     |

### 12b. Backend ops

| #    | Task                                                                           | Status |
| ---- | ------------------------------------------------------------------------------ | ------ |
| 12.5 | Enhance health endpoint to check DB + Redis connectivity                       | ⏭️     |
| 12.6 | Fix N+1 query in `AdminController` line ~480 (missing `with('driverProfile')`) | ⏭️     |
| 12.7 | Configure `LOG_CHANNEL=daily` for production (currently `single`)              | ⏭️     |
| 12.8 | Add `$tries` and `$timeout` to queue jobs (`ExpireRideRequest`, etc.)          | ⏭️     |

### 12c. Governance

| #     | Task                                                                   | Status |
| ----- | ---------------------------------------------------------------------- | ------ |
| 12.9  | Enable GitHub branch protection on `main` (require PR + status checks) | ⏭️     |
| 12.10 | Update `dependabot.yml` reviewer placeholder with real GitHub username | ⏭️     |
| 12.11 | Disable Dependabot until post-launch (or set to security-only)         | ⏭️     |

---

## Known Deferred Items (Post-Beta)

| Item                                       | Reason deferred                                                                                                         |
| ------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------- |
| iOS support                                | Android-only open beta                                                                                                  |
| MB-10 speed-based adaptive intervals       | Emulator only — test with physical driving                                                                              |
| Rider suspend: full WS/location severance  | Low priority for beta                                                                                                   |
| Surge pricing                              | Post-beta feature                                                                                                       |
| In-app driver/rider chat                   | Post-beta feature                                                                                                       |
| CI/CD pipeline refinement                  | `deploy-staging.yml` exists; refine post-beta                                                                           |
| Horizontal Reverb scaling                  | Single server sufficient for campus scale                                                                               |
| Admin 2FA                                  | Cloudflare Zero Trust was planned but deferred; Filament auth sufficient                                                |
| Cloudflare Zero Trust                      | Payment method issue — revisit when international card works                                                            |
| DigitalOcean port 587 unblock              | Using port 2525 as workaround; submit DO support ticket when convenient                                                 |
| Let's Encrypt HTTP-01 fix for api.anjem.me | DNS-01 used as workaround; fix Nginx path or renew manually before ~90 days                                             |
| Off-server DB backups                      | Currently on-server cron; add DO Spaces or Firebase Storage upload later                                                |
| Cloudflare SSL Configuration Rule          | Created for `admin.anjem.me` Full Strict — deleted when admin subdomain reverted. Re-add if Zero Trust is set up later. |
