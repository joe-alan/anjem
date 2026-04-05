# Repository Guidelines

## Project Overview

Anjem is a campus ride-sharing platform composed of a Laravel 11 backend (`backend/`) and a Flutter 3.24 rider/driver app (`mobile/`). Documentation, phase reports, and setup guides live in `docs/`, while `CONTINUE_HERE.md` tracks current priorities.

## Project Structure & Modules

- `backend/app/` (controllers, services, events, models) with API routes in `routes/api.php`, migrations/seeders in `database/`, and integration/unit tests under `tests/`.
- `mobile/lib/` with shared modules in `core/` (models, services, providers, widgets) and flavor-specific code in `rider/` and `driver/`.
- Supporting assets: `Makefile` and docs referenced above.

## First-Time Setup

1. **Backend**
   - `cd backend && cp .env.example .env`, fill DB/Firebase/Mapbox keys, then `composer install`, `php artisan key:generate`, `php artisan migrate --seed`, `php artisan storage:link`. Ensure Postgres and Redis are running as native services.
   - Start services: `php artisan serve`, `php artisan reverb:start`, `php artisan queue:work`, `php artisan schedule:work` (Redis-backed queues).
2. **Mobile**
   - `cd mobile && flutter pub get`.
   - Update environment constants (e.g., `lib/core/config/environment.dart`) with API base URL, WebSocket URL, and API keys.
   - Generate code when models/providers change: `flutter packages pub run build_runner build --delete-conflicting-outputs`.

## Daily Development Flow

- Backend: `php artisan serve` + `php artisan reverb:start` + `php artisan queue:work` + `php artisan schedule:work` (ensure Redis is running). All four processes must be running — `schedule:work` drives the stale-driver kick and request cleanup jobs.
- Mobile: `flutter run --flavor rider -t lib/main_rider.dart` or `flutter run --flavor driver -t lib/main_driver.dart`.
- Ensure Postgres and Redis are running as native services, and rerun `build_runner` whenever annotated files change.

## Destructive Commands (Use Carefully)

- `php artisan migrate:fresh --seed` wipes dev data—run only when you intend to reseed.
- `php artisan test` must target the isolated test DB (see next section) to avoid truncating dev data.
- Dropping databases affects both backend and Flutter clients; confirm backups or seed scripts first.

## Testing & Test Environment Setup

- Create `.env.testing` (copy from `.env`) and set `DB_DATABASE=anjemme_test`. Provision that DB (`CREATE DATABASE anjemme_test;` + `CREATE EXTENSION postgis;`) and run `php artisan migrate --env=testing`.
- Run suites: `cd backend && php artisan test` and `cd mobile && flutter test`.
- Test names should describe behavior (e.g., `RideControllerTest::test_driver_cannot_accept_ride_without_permissions`).

## Build, Lint, and QA Commands

- Backend: `./vendor/bin/phpstan analyse`, `php artisan test`, `php artisan queue:work`, `php artisan reverb:start`.
- Mobile: `dart analyze`, `dart format --set-exit-if-changed .`, `flutter test`, `flutter packages pub run build_runner build --delete-conflicting-outputs`.

## Coding Style & Naming

- PHP: PSR-12, type-hinted methods, PascalCase classes, camelCase methods/properties. Keep controllers thin—move logic into `app/Services`.
- Dart: Effective Dart; explicit types where clarity matters; widgets ≤20–25 lines; reusable components in `lib/core/widgets`; state handled via Riverpod providers.
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
- `MaterialApp` in `mobile/lib/core/app.dart` watches `localeProvider` — updating its state switches the whole app live:
  ```dart
  ref.read(localeProvider.notifier).state = const Locale('en');
  ```
- There is currently no in-app language switcher UI; adding one requires only a settings screen that writes to `localeProvider`.

## Branching & Git Flow

- `main`: production-ready; protected.
- `dev`: integration branch; feature branches merge here before promotion to `main`.
- Feature branches use `feat/…`, `fix/…`, `docs/…`, `refactor/…`, `test/…`, or `chore/…`.
- Rebase feature branches onto `dev`, resolve conflicts locally, and keep history clean.

## Commit & Pull Request Guidelines

- Commit format: `type(scope): short description` (e.g., `feat(auth): add OTP verification`). Include tests/docs when relevant.
- PR checklist:
  - Summary + “Changes Made” checklist.
  - Test evidence (`php artisan test`, `flutter test`, analyzer outputs).
  - Backend PRs must list new endpoints with sample request/response payloads, note migrations (with rollback instructions), and mention queue/WebSocket impacts.
  - Link issues (`fixes #123`), attach screenshots for UI changes, and confirm docs updated.
  - Ensure `.env.testing` is updated if new config keys are required.

## Environment & Operations Notes

- Redis backs queues and broadcasting; ensure `redis-server` is running before `queue:work`.
- WebSockets use Laravel Reverb (`php artisan reverb:start`); mobile clients rely on it for ride updates.
- Storage: run `php artisan storage:link` whenever cloning the backend repo.
- Keep Firebase, Mapbox, and Google OAuth credentials secure—never commit secrets; use `.env` or CI secrets.

These guidelines keep backend, mobile, and documentation efforts aligned while protecting shared environments.
