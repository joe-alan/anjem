# Anjem

**A campus ride-sharing platform for the Universitas Diponegoro (Undip) area in Semarang, Indonesia.**

Anjem matches riders with verified student motorbike drivers inside a university geofence — a
Grab/Gojek-style model scoped to short campus hops. It is a full product: a Laravel API with a
Filament admin panel, a real-time matching engine, and a dual-flavor Flutter app for riders and
drivers.

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

> **Status:** Feature-complete MVP. Deployed to production infrastructure (Laravel Forge) and
> packaged as a signed Android release, but development paused before Play Store submission — it
> is not running publicly today. Designed and built solo. This repository is being prepared for
> open source as a portfolio project; see [Project status](#project-status).

---

## Overview

| | |
| --- | --- |
| **Model** | Standard ride-hailing (request → match → ride → rate). No fixed pickup points. |
| **Riders** | Anyone with a valid email. No student requirement. |
| **Drivers** | Verified students only — KYC with a student ID card and campus-email verification. |
| **Geofence** | Rides must start and end within the Undip / Tembalang campus area. |
| **Payments** | None in-app. Riders pay drivers directly (cash or the driver's own QRIS). |
| **Platform revenue** | Prepaid **credits** — a driver spends 1 credit per accepted ride. Credits are granted by an admin; there is no in-app top-up yet. |
| **Fare** | Rp 5,000 base + Rp 3,000/km (Mapbox driving distance), Rp 5,000 minimum. No surge. |

---

## Architecture

```
┌─────────────────┐        ┌─────────────────┐
│  Rider app      │        │  Driver app     │      Flutter, one codebase,
│  (me.anjem.rider)│       │ (me.anjem.driver)│     two build flavors
└────────┬────────┘        └────────┬────────┘
         │  REST (Dio)               │
         │  WebSocket (Reverb/Pusher protocol)
         ▼                           ▼
┌──────────────────────────────────────────────┐
│  Laravel 10 API  ·  /api/v1                   │
│  ┌────────────┐ ┌────────────┐ ┌───────────┐  │
│  │ Matching   │ │ Route      │ │ KYC / FCM │  │
│  │ queue(FIFO)│ │ cache      │ │ (Horizon) │  │
│  └────────────┘ └────────────┘ └───────────┘  │
│  Filament admin panel  ·  /admin              │
└───┬───────────────┬───────────────┬──────────┘
    │               │               │
    ▼               ▼               ▼
 PostgreSQL      Redis           External
 + PostGIS    (queue, cache,     Firebase Auth · FCM · Storage
 (spatial)    Reverb, sessions)  Mapbox Directions & Search
```

Key mechanisms:

- **Auth** — Firebase handles Google sign-in identity; the backend verifies the Firebase token
  and issues its own Laravel Sanctum token with role-scoped abilities (`rider:*`, `driver:*`,
  `admin:*`). Filament admin uses separate session auth.
- **Matching** — a single FIFO queue keyed on `driver_profiles.queue_joined_at`. When a request
  comes in, `MatchingQueueService` dispatches to the head-of-queue driver in range, with
  timeout-and-advance handling via queued jobs.
- **Notification delivery is dual** — every ride event is pushed over a Reverb WebSocket for
  instant in-app updates *and* sent as an FCM push to wake backgrounded/terminated apps.
- **Route geometry is computed once** at request creation and stored (`route_caches`), so the
  map polyline and distance are served from the DB instead of re-hitting Mapbox on every poll.
- **Stale-driver handling** — a scheduled command kicks drivers whose heartbeat has lapsed and
  cleans up the notifications they would otherwise leave orphaned on the device.

---

## Tech stack

### Backend (`backend/`)

| Area | Choice |
| --- | --- |
| Framework | Laravel 10 (PHP 8.2+) |
| Admin | Filament 3 |
| Database | PostgreSQL + PostGIS (spatial queries), via `laravel-eloquent-spatial` |
| Cache / queue / sessions | Redis (`predis`) |
| Queue worker | Laravel Horizon |
| WebSocket | Laravel Reverb (Pusher protocol) |
| Auth | Firebase Admin SDK (`kreait/firebase-php`) + Laravel Sanctum |
| Maps | Mapbox Directions & Search Box APIs |
| Error tracking | Sentry (`sentry-laravel`) |

### Mobile (`mobile/`)

| Area | Choice |
| --- | --- |
| Framework | Flutter (CI pins 3.24.x; Dart SDK `^3.5.4`) |
| Flavors | `rider` and `driver` — separate entrypoints, app IDs, and Firebase configs |
| State | Riverpod + `flutter_bloc` |
| Networking | Dio (REST) + `pusher_client_socket` (WebSocket) |
| Maps | `mapbox_maps_flutter` |
| Firebase | `firebase_auth`, `firebase_messaging`, `firebase_core` |
| i18n | `gen-l10n` — English + Bahasa Indonesia (default `id`) |
| Error tracking | `sentry_flutter` |

### Infrastructure

Laravel Forge on a DigitalOcean droplet (Singapore). Nginx, PHP-FPM, PostgreSQL 17 + PostGIS,
Redis. `api.anjem.me` serves the API and the Filament admin panel; `ws.anjem.me` serves Reverb.
Forge daemons run Horizon, Reverb, and the scheduler. Push-to-`main` auto-deploys.

---

## Features

**Rider**
- Search a destination (Mapbox), preview the route and fare, confirm a request
- Live waiting → matched → en route → completed flow over WebSocket
- Ride history and post-ride driver rating
- Account deletion with PII anonymization (GDPR / Indonesian PDP)

**Driver**
- KYC: submit student ID photo → campus-email OTP (queued via Horizon) → admin approval
- Go online / offline, see queue position, receive full-screen ride requests
- Accept / decline, navigate to pickup and drop-off, update ride status
- Credit balance and transaction history; earnings, ride, and rating history screens

**Admin (Filament)**
- Driver & rider management, KYC approve/reject, credit grant/deduct with audit logging
- Real-time monitoring — active rides, online drivers, pending requests
- Analytics — ride volume, popular routes, driver performance
- Admin ride override (force status on stuck rides), audit log viewer
- App settings page driving the client force-update system

**Platform**
- Versioned REST API under `/api/v1` (70+ endpoints; see [`docs/api/API_DOCUMENTATION.md`](docs/api/API_DOCUMENTATION.md))
- Per-route rate limiting (auth 10/min, general 100/min, location updates 200/min)
- Force-update gate — `GET /api/v1/app/config` returns `min_version` / `update_url`

---

## Project structure

```
anjem/
├── backend/                 Laravel API + Filament admin
│   ├── app/
│   │   ├── Http/Controllers/Api/   REST controllers (thin)
│   │   ├── Services/               business logic (Matching, Route cache, Credit, KYC, …)
│   │   ├── Events/ Jobs/ Listeners/  WebSocket broadcasts + queued work
│   │   ├── Filament/               admin resources, pages, widgets
│   │   └── Models/
│   ├── database/migrations/  ·  routes/api.php  ·  tests/
├── mobile/                   Flutter app
│   └── lib/
│       ├── core/             shared: models, providers, services, widgets, config
│       ├── rider/            rider screens + blocs
│       ├── driver/           driver screens + blocs
│       └── l10n/             ARB translation files
├── docs/                     product & technical specs, architecture notes, guides
└── Makefile                  dev shortcuts (make setup / dev / test / build)
```

---

## Running locally

### Prerequisites

- PHP 8.2+, Composer
- PostgreSQL with the PostGIS extension, Redis — **run as native services, not Docker**
  (`docker-compose.yml` is legacy and unused)
- Flutter 3.24.x, an Android emulator or device
- Accounts/keys: Firebase project (Auth + FCM + Storage), Mapbox tokens, an SMTP provider for
  mail, optionally a Sentry DSN

### Backend

```bash
cd backend
cp .env.example .env          # then fill in DB, Firebase, Mapbox, mail, Reverb keys
composer install
php artisan key:generate
php artisan migrate --seed    # seeds admin user, campus locations, demo accounts
php artisan storage:link
```

Run the four processes it needs:

```bash
php artisan serve             # API        :8000
php artisan reverb:start      # WebSocket  :8080
php artisan queue:work        # FCM sends, ride expiry, KYC OTP
php artisan schedule:work     # stale-driver kick, route-cache cleanup
```

### Mobile

Configuration is injected at build time with `--dart-define` (there is no `.env` file). Each
flavor needs its own `android/app/src/<flavor>/google-services.json` — copy the checked-in
`.example` and point it at your Firebase project.

```bash
cd mobile
flutter pub get

flutter run --flavor rider  -t lib/main_rider.dart  --dart-define=MAPBOX_ACCESS_TOKEN=pk....
flutter run --flavor driver -t lib/main_driver.dart --dart-define=MAPBOX_ACCESS_TOKEN=pk....
```

Regenerate code when annotated models/providers change:
`dart run build_runner build --delete-conflicting-outputs`.

See [`docs/setup/DEVELOPMENT.md`](docs/setup/DEVELOPMENT.md) for the full walkthrough.

---

## Testing

```bash
cd backend && php artisan test          # uses .env.testing → an isolated Postgres DB
cd mobile  && flutter test
```

Backend has a substantial suite — 44 feature/unit test files covering auth, the matching queue,
ride flow, credits, real-time events, rate limiting, and Filament resources. **Mobile test
coverage is minimal and is a known gap.**

Quality gates (also enforced in CI):

```bash
# backend
./vendor/bin/pint --test          # code style
./vendor/bin/phpstan analyse      # static analysis (Larastan)
composer audit
# mobile
dart analyze
dart format --set-exit-if-changed .
```

---

## Engineering notes

Longer-form write-ups of the decisions that shaped the codebase:

- [Beacon → standard ride-hailing pivot](docs/architecture/ARCHITECTURE_CHANGE_BEACON_TO_STANDARD.md)
  — why fixed pickup points were dropped
- [Route API cost optimisation](docs/optimization/ROUTE_API_CACHING_PLAN.md) — computing geometry
  once and serving it from the DB
- [Technical specification](docs/TECHNICAL_SPEC.md) · [Product & operations](docs/PRODUCT_AND_OPS.md)
- [Admin panel design](docs/architecture/ADMIN_PANEL.md)
- [Contributing guide](docs/guides/CONTRIBUTING.md)

---

## Project status

The product was built through a full Android release candidate and deployed to live
infrastructure, then paused before public launch.

| Area | State |
| --- | --- |
| Core ride flow (request, match, ride, rate) | ✅ Complete |
| Driver KYC, credit system, FIFO matching queue | ✅ Complete |
| Filament admin panel (management, monitoring, analytics, overrides) | ✅ Complete |
| Dual FCM + WebSocket notification delivery | ✅ Complete |
| Mapbox route caching, force-update system, account deletion | ✅ Complete |
| Backend test suite | ✅ Broad coverage |
| Production deploy (Forge) + signed Android release build | ✅ Done |
| Play Store submission | ⬜ Not started — development paused here |
| Public launch / real rides | ⬜ Never ran |
| Mobile test coverage | ⚠️ Minimal |
| iOS | ⬜ Out of scope — Android only |

Known follow-ups are tracked in [`docs/TODO.md`](docs/TODO.md).

---

## License

[MIT](LICENSE) © Jonathan Alano Hasiholan
