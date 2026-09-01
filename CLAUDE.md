# Repository Guidelines

## Project Overview

Anjem is a campus ride-sharing platform (Grab/Uber model) for the Semarang/Undip area. It connects riders with verified student motorbike drivers within a university geofence.

- **Backend:** Laravel 10 (`backend/`) — API, Filament 3 admin panel, Horizon queue manager, Reverb WebSocket server
- **Mobile:** Flutter 3.24 (`mobile/`) — two flavors: `rider` and `driver`
- **Docs:** `docs/` — product & technical specs, architecture notes, setup guides; point-in-time material under `docs/archive/`
- **Status:** feature-complete MVP, deployed then paused before Play Store launch. Being prepared for open source. `docs/archive/STAGING_LAUNCH_CHECKLIST.md` records the (paused) launch plan.

## Project Structure & Modules

- `backend/app/` — controllers, services, events, models; API routes in `routes/api.php`; migrations/seeders in `database/`; tests under `tests/`.
- `mobile/lib/` — shared modules in `core/` (models, services, providers, widgets, config) and flavor-specific code in `rider/` and `driver/`.
- `Makefile` — dev automation shortcuts (note: some targets have stale flavor names).

## First-Time Setup

### Backend

1. `cd backend && cp .env.example .env`
2. Fill required keys in `.env`:
   - **Database:** Postgres connection (`DB_*`)
   - **Firebase:** Service account credentials (`FIREBASE_*`) + `FIREBASE_STORAGE_BUCKET`
   - **Mapbox:** `MAPBOX_PUBLIC_TOKEN`, `MAPBOX_SECRET_TOKEN`
   - **Reverb:** `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`
   - **Mail:** Mailtrap transactional SMTP (`MAIL_HOST`, `MAIL_PORT=2525`, `MAIL_USERNAME`, `MAIL_PASSWORD`)
   - **Sentry:** `SENTRY_LARAVEL_DSN`, `SENTRY_TRACES_SAMPLE_RATE`, `SENTRY_ENVIRONMENT`
3. `composer install && php artisan key:generate && php artisan migrate --seed && php artisan storage:link`
4. Ensure Postgres and Redis are running as **native services** (not Docker).

### Mobile

1. `cd mobile && flutter pub get`
2. Environment is configured via `--dart-define` flags at build time (no `.env` file). All values feed into `AppConfig` singleton via `main_rider.dart` / `main_driver.dart`:
   - `API_URL` (default: `http://10.0.2.2:8000/api/v1`)
   - `WS_URL` (default: `ws://10.0.2.2:8000`)
   - `MAPBOX_ACCESS_TOKEN`
   - `PUSHER_KEY`, `PUSHER_HOST`, `PUSHER_PORT`, `PUSHER_SCHEME`
   - `SENTRY_DSN`, `SENTRY_ENV` (optional — Sentry disabled if DSN empty)
3. Generate code when annotated models/providers change: `dart run build_runner build --delete-conflicting-outputs`

## Daily Development Flow

- **Backend** — all four processes must be running:
  ```bash
  php artisan serve            # API on :8000
  php artisan reverb:start     # WebSocket on :8080
  php artisan queue:work        # Jobs (FCM sends, ride expiry, etc.)
  php artisan schedule:work     # Stale-driver kick + route cache cleanup
  ```
- **Mobile:**
  ```bash
  flutter run --flavor rider -t lib/main_rider.dart \
    --dart-define=MAPBOX_ACCESS_TOKEN=pk.eyJ1...

  flutter run --flavor driver -t lib/main_driver.dart \
    --dart-define=MAPBOX_ACCESS_TOKEN=pk.eyJ1...
  ```
- Ensure Postgres and Redis are running natively. Rerun `build_runner` whenever annotated files change.

## Destructive Commands (Use Carefully)

- `php artisan migrate:fresh --seed` wipes dev data — run only when you intend to reseed.
- `php artisan test` must target the isolated test DB (see next section) to avoid truncating dev data.
- Dropping databases affects both backend and Flutter clients; confirm backups or seed scripts first.

## Testing & Test Environment Setup

- Create `.env.testing` (copy from `.env`) and set `DB_DATABASE=anjemme_test`. Provision that DB (`CREATE DATABASE anjemme_test;` + `CREATE EXTENSION postgis;`) and run `php artisan migrate --env=testing`.
- Run suites: `cd backend && php artisan test` and `cd mobile && flutter test`.
- Test names should describe behavior (e.g., `RideControllerTest::test_driver_cannot_accept_ride_without_permissions`).

## Build, Lint, and QA Commands

- **Backend:** `./vendor/bin/phpstan analyse` (static analysis), `php artisan test`, `./vendor/bin/pint` (code formatting).
- **Mobile:** `dart analyze`, `dart format --set-exit-if-changed .`, `flutter test`, `dart run build_runner build --delete-conflicting-outputs`.

## Coding Style & Naming

- **PHP:** PSR-12, type-hinted methods, PascalCase classes, camelCase methods/properties. Keep controllers thin — move logic into `app/Services`.
- **Dart:** Effective Dart; explicit types where clarity matters; widgets ≤20–25 lines; reusable components in `lib/core/widgets`; state handled via Riverpod providers.
- Run formatters/analyzers before committing.

## Internationalisation (i18n)

The app uses Flutter's `gen-l10n` system. **Never hardcode user-visible strings** in Dart files.

### Adding a string

1. Add the key to `mobile/lib/l10n/app_en.arb` (English source of truth).
2. Add the matching translation to `mobile/lib/l10n/app_id.arb` (Bahasa Indonesia).
3. Use it in Dart: `AppLocalizations.of(context).yourKey`.
4. Parametrised strings use `{placeholder}` in ARB and become method calls in Dart: `l10n.etaMinutes(minutes)`.

### Async safety

Read `AppLocalizations.of(context)` **before** any `await`. After an async gap, re-read inside `if (mounted)`:

```dart
final l10n = AppLocalizations.of(context); // before await
await someAsyncCall();
if (mounted) {
  final l10n = AppLocalizations.of(context); // re-read post-await
  ScaffoldMessenger.of(context).showSnackBar(...);
}
```

Builder/helper methods that need l10n should receive it as a parameter (`AppLocalizations l10n`) rather than calling `of(context)` inside them.

### Default locale & switching

- Default locale is **Bahasa Indonesia** (`id`), set in `mobile/lib/core/providers/locale_provider.dart`.
- `MaterialApp` in `mobile/lib/core/app.dart` watches `localeProvider` — updating its state switches the whole app live.
- No in-app language switcher UI yet.

## Branching & Git Flow

- `main` is the default branch. There is no live deployment anymore (the hosted infra was torn down), so `main` is no longer auto-deployed.
- Feature branches use `feat/…`, `fix/…`, `docs/…`, `refactor/…`, `test/…`, or `chore/…`; open a PR into `main`.
- Local dev is the only environment — there is no staging or production server.

## Commit & Pull Request Guidelines

- Commit format: `type(scope): short description` (e.g., `feat(auth): add OTP verification`). Include tests/docs when relevant.
- PR checklist:
  - Summary + "Changes Made" checklist.
  - Test evidence (`php artisan test`, `flutter test`, analyzer outputs).
  - Backend PRs must list new endpoints with sample request/response payloads, note migrations (with rollback instructions), and mention queue/WebSocket impacts.
  - Link issues (`fixes #123`), attach screenshots for UI changes, and confirm docs updated.
  - Ensure `.env.testing` is updated if new config keys are required.

## Deployment

> The hosted deployment below is **no longer running** — it was torn down after development
> paused. Kept as a reference for how the project was operated.

- **Server:** Laravel Forge (Hobby plan) on DigitalOcean (4GB/2vCPU, Singapore). Ubuntu, PHP 8.2+, PostgreSQL 15+ with PostGIS, Redis, Nginx.
- **Domains:** `api.anjem.me` (API + admin panel), `ws.anjem.me` (WebSocket via Reverb).
- **Push-to-deploy:** Forge auto-deploys on push to `main`. Deploy script runs `composer install --no-dev`, `optimize`, and migrations.
- **Daemons managed by Forge:** Horizon (queue), Reverb (WebSocket), Scheduler.
- **Admin panel:** Filament at `api.anjem.me/admin`, protected by session auth + Nginx rate limiting (5r/s).
- **SSL:** Let's Encrypt — `api.anjem.me` via DNS-01, `ws.anjem.me` via HTTP-01.
- **CI:** GitHub Actions — `laravel-ci.yml` (backend tests/lint), `flutter-ci.yml` (mobile tests/lint), `pr-checks.yml` (conventional commit format, description length).

## Key Systems

### Force-Update System
- Backend: `GET /api/v1/app/config` returns `min_version`, `force_update`, `update_url` (managed via Filament `AppSettingsPage`).
- Mobile: `VersionCheckWrapper` wraps the app root; blocks navigation with `ForceUpdateScreen` when the installed version is below `min_version`.

### Account Deletion (GDPR/PDP)
- Soft-delete with PII anonymization. Foreign keys use `nullOnDelete`. Users can re-sign-up after deletion.

### KYC Flow
- Drivers submit student ID photo → Firebase Storage upload → email OTP verification (queued via Horizon) → admin reviews in Filament with approve/reject.
- OTP has backend rate limiting: 60s cooldown between resends, 5/hour cap.

### Notification Delivery
- Dual delivery: WebSocket (Reverb) for instant in-app updates + FCM push to wake background/terminated apps.
- `new_ride_request` foreground notification intentionally suppressed — WebSocket shows the full-screen request sheet instead.

### Credit System
- Prepaid: drivers consume 1 credit per ride accepted. Credits managed by admin. No payment processing — cash or driver's own QRIS.

## Environment & Operations Notes

- Postgres and Redis run as **native services** (not Docker). `docker-compose.yml` is legacy — do not use Docker commands for local dev.
- Two queue systems exist — only `MatchingQueueService.php` (FIFO via `driver_profiles.queue_joined_at`) is active. `QueueService.php` / `BeaconQueueService` is deprecated legacy.
- Firebase Auth handles Google OAuth identity; backend issues its own Sanctum tokens with role-based abilities for authorization. Filament admin uses separate session auth.
- Sentry is integrated in both backend (`sentry/sentry-laravel`) and mobile (`sentry_flutter`) for error tracking.
- Production email uses Mailtrap transactional SMTP (`live.smtp.mailtrap.io:2525`), domain `anjem.me` verified with SPF/DKIM/DMARC.
- Route geometry (GeoJSON) is computed once at ride request creation and served from DB — no duplicate Mapbox API calls.
- Keep Firebase, Mapbox, Google OAuth, Sentry, and Mailtrap credentials secure — never commit secrets; use `.env` or CI secrets.
