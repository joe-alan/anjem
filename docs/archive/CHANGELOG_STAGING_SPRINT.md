# Staging Sprint — Unsaved Changes Summary

> Generated: 2026-04-08
> Branch: `staging-sprint`
> Base: commit `38a23d1` (chore(mobile): production hardening)

---

## Overview

This changeset prepares Anjem for production deployment on Laravel Forge. It covers infrastructure cleanup (removing unused DigitalOcean/staging artifacts), a new **force-update system** (backend + mobile), deployment documentation updates, and minor bug fixes.

---

## 1. Infrastructure Cleanup

### Removed files
| File | Reason |
|------|--------|
| `.do/.gitkeep` | DigitalOcean App Platform directory no longer needed — hosting moved to Forge |
| `.github/workflows/deploy-staging.yml` | 455-line staging deploy workflow removed — no separate staging server; Forge handles production deploy on push to `main` |
| `backend/database/seeders/EssentialLocationsSeeder.php` | Locations seeder removed (data already seeded in production) |
| `backend/database/seeders/new_locations.csv` | CSV data file for the removed seeder |

### Modified: `Makefile`
- Removed `deploy-staging`, `deploy-production`, and `infra-setup` targets (referenced DigitalOcean CLI)
- Updated help text to note that production deploys automatically via Forge

---

## 2. Force Update System (New Feature)

A complete admin-controlled force-update mechanism that blocks outdated mobile apps from being used.

### Backend

| File | Type | Description |
|------|------|-------------|
| `backend/database/migrations/2026_04_08_133622_create_app_settings_table.php` | New | Creates `app_settings` key-value table, seeds `min_version`, `update_url`, `force_update` defaults |
| `backend/app/Models/AppSetting.php` | New | Eloquent model with `getValue()` and `getAppConfig()` helpers |
| `backend/app/Http/Controllers/Api/AppConfigController.php` | New | `GET /api/v1/app/config` — public endpoint returning force-update config |
| `backend/app/Filament/Pages/AppSettingsPage.php` | New | Admin panel page under System > App Settings with min version, update URL, and toggle |
| `backend/resources/views/filament/pages/app-settings.blade.php` | New | Blade view for the settings form |
| `backend/routes/api.php` | Modified | Registered `app/config` route (public, throttled 60/min) |
| `backend/app/Providers/Filament/AdminPanelProvider.php` | Modified | Registered `AppSettingsPage` in admin panel |

### Mobile

| File | Type | Description |
|------|------|-------------|
| `mobile/lib/core/widgets/version_check_wrapper.dart` | New | Stateful widget that calls `/app/config` on startup, compares semver, blocks if outdated |
| `mobile/lib/core/widgets/force_update_screen.dart` | New | Full-screen blocking UI with "Update Now" button (opens update URL via url_launcher) |
| `mobile/lib/core/app.dart` | Modified | Wraps `FcmInitializer` with `VersionCheckWrapper` |
| `mobile/lib/l10n/app_en.arb` | Modified | Added `forceUpdateTitle`, `forceUpdateMessage`, `forceUpdateButton` strings |
| `mobile/lib/l10n/app_id.arb` | Modified | Added Indonesian translations for force update strings |
| `mobile/lib/l10n/app_localizations.dart` | Modified | Generated abstract methods |
| `mobile/lib/l10n/app_localizations_en.dart` | Modified | Generated English implementations |
| `mobile/lib/l10n/app_localizations_id.dart` | Modified | Generated Indonesian implementations |

### How it works
1. Admin sets `min_version`, `update_url`, and enables `force_update` toggle in Filament panel
2. On app launch, `VersionCheckWrapper` fetches `GET /api/v1/app/config`
3. If `force_update` is true and app version < `min_version`, a blocking `ForceUpdateScreen` is shown
4. User can only tap "Update Now" to open the store/download link — back button is disabled

---

## 3. Documentation & Config Updates

### Modified: `CLAUDE.md`
- Updated branching strategy: removed `dev` branch references, documented Forge auto-deploy on `main`
- Added Deployment section (Forge, CI workflows, push-to-deploy)

### Modified: `STAGING_LAUNCH_CHECKLIST.md`
- Renamed Section 7 from "Staging Deploy" to "Production Deploy (Forge)"
- Updated all 16 tasks with actual Forge setup details and completion status (7.1–7.13 marked complete)
- Added notes on DB name, Redis client, Mailtrap config, SSL, and Cloudflare status

### New: `FIREBASE_STORAGE_PLAN.md`
- Implementation plan for migrating file storage from local disk to Firebase Storage
- Covers 4 phases: infrastructure, upload/delete rewrite, URL compatibility, data migration command
- Lists 3 new files and 7 modified files with detailed specs

### New: `SMOKE_TEST_CHECKLIST.md`
- Production smoke test checklist covering: server health, auth, WebSocket, KYC flow, full ride E2E, edge cases
- Includes `flutter run` and `flutter build` commands for prod testing and release builds

---

## 4. Bug Fix

### Modified: `mobile/lib/core/providers/driver_status_provider.dart`
- Fixed `debugPrint(stackTrace)` → `debugPrint(stackTrace.toString())` — `debugPrint` expects a `String`, not a `StackTrace` object

---

## Migration Required

After merging, run on the production server:

```bash
php artisan migrate
```

This creates the `app_settings` table with default values. No data loss risk.
