# Force Update System — Context Doc

> Status: **Work in progress**
> Last updated: 2026-04-08

---

## What it does

Allows an admin to block outdated mobile app versions from being used. When enabled, users running an app version below the configured minimum see a full-screen "Update Required" message and cannot proceed.

---

## Current state

The basic wiring is in place end-to-end but **has not been tested against a live server yet**.

### What exists

| Layer | File | Status |
|-------|------|--------|
| DB | `backend/database/migrations/2026_04_08_133622_create_app_settings_table.php` | Done — key-value table with `min_version`, `update_url`, `force_update` seeded |
| Model | `backend/app/Models/AppSetting.php` | Done — `getValue()`, `getAppConfig()` |
| API | `backend/app/Http/Controllers/Api/AppConfigController.php` | Done — `GET /api/v1/app/config` (public, 60 req/min) |
| Admin UI | `backend/app/Filament/Pages/AppSettingsPage.php` | Done — form with min version, update URL, toggle |
| Admin view | `backend/resources/views/filament/pages/app-settings.blade.php` | Done |
| Admin registration | `backend/app/Providers/Filament/AdminPanelProvider.php` | Done — page registered |
| Mobile check | `mobile/lib/core/widgets/version_check_wrapper.dart` | Done — wraps app root, calls API on init |
| Mobile block screen | `mobile/lib/core/widgets/force_update_screen.dart` | Done — branded blocking UI |
| Mobile integration | `mobile/lib/core/app.dart` | Done — `VersionCheckWrapper` wraps `FcmInitializer` |
| i18n | `mobile/lib/l10n/app_en.arb`, `app_id.arb` + generated files | Done — 3 strings (title, message, button) |

### What's NOT done / needs work

- [ ] **Migration not yet run on prod** — `php artisan migrate` needed on Forge
- [ ] **No smoke test** — the full flow (admin sets version → app blocked) hasn't been tested
- [ ] **Version source is `pubspec.yaml`** — currently `1.0.0+1`, will need bumping strategy
- [ ] **Update URL is empty** — no Play Store listing yet, needs a real URL or Firebase App Distribution link
- [ ] **No per-platform versioning** — same `min_version` for both rider and driver apps
- [ ] **No soft update** — it's force-or-nothing; no "update available, skip for now" option
- [ ] **No caching on mobile** — every app launch hits the API; could cache with a TTL
- [ ] **Fail-open on error** — if the API call fails (timeout, server down), the app proceeds normally (intentional, but worth noting)
- [ ] **No separate Android/iOS URLs** — single `update_url` field; may need platform detection later
- [ ] **Admin page has no input validation** — accepts any string for `min_version`, not validated as semver

---

## Architecture

```
┌─────────────┐     GET /api/v1/app/config     ┌─────────────────┐
│  Mobile App  │ ────────────────────────────── │  Laravel API     │
│              │                                │                  │
│  version_    │  ◄── { min_version, update_url,│  AppConfigCtrl   │
│  check_      │       force_update }           │       ↓          │
│  wrapper     │                                │  AppSetting      │
└──────┬───────┘                                │  (DB table)      │
       │                                        └────────┬─────────┘
       ▼                                                 │
 version < min?                              ┌───────────┴──────────┐
   yes → ForceUpdateScreen                   │  Filament Admin Page │
   no  → normal app                          │  /admin/app-settings │
                                             └──────────────────────┘
```

### API response shape

```json
GET /api/v1/app/config

{
  "success": true,
  "data": {
    "min_version": "1.0.0",
    "update_url": "",
    "force_update": false
  }
}
```

### Database: `app_settings` table

| Column | Type | Notes |
|--------|------|-------|
| `key` | `string` (PK) | One of: `min_version`, `update_url`, `force_update` |
| `value` | `text` (nullable) | Stored as string, booleans as `"true"`/`"false"` |
| `created_at` / `updated_at` | timestamps | |

Generic key-value design — can hold future settings without new migrations.

### Version comparison logic (`_isVersionBelow`)

Located in `version_check_wrapper.dart:66-87`. Splits on `.`, pads to 3 segments, compares major → minor → patch numerically. Returns `false` on parse errors (fail-open).

```
"1.0.0" vs "1.0.1" → true  (needs update)
"1.1.0" vs "1.0.1" → false (current is newer)
"1.0.0" vs "1.0.0" → false (equal, no update)
```

### App startup flow

```
main_rider.dart / main_driver.dart
  → AppConfig.initialize(...)
  → AnjerApp (MaterialApp)
    → VersionCheckWrapper          ← NEW
      → shows SplashScreen while checking
      → if blocked: ForceUpdateScreen (no escape)
      → if ok: FcmInitializer
        → SessionCheckWrapper
          → normal app
```

---

## Key files (quick reference)

**Backend:**
- `backend/app/Models/AppSetting.php` — model + query helpers
- `backend/app/Http/Controllers/Api/AppConfigController.php` — API endpoint
- `backend/app/Filament/Pages/AppSettingsPage.php` — admin form
- `backend/resources/views/filament/pages/app-settings.blade.php` — form view
- `backend/database/migrations/2026_04_08_133622_create_app_settings_table.php` — schema + seed data

**Mobile:**
- `mobile/lib/core/widgets/version_check_wrapper.dart` — startup check + semver comparison
- `mobile/lib/core/widgets/force_update_screen.dart` — blocking UI
- `mobile/lib/core/app.dart:54` — where wrapper is inserted

**Config:**
- `mobile/pubspec.yaml:19` — `version: 1.0.0+1` (source of truth for app version)
- `mobile/lib/core/config/app_config.dart` — `apiBaseUrl` used by version check

**i18n keys:** `forceUpdateTitle`, `forceUpdateMessage`, `forceUpdateButton`

---

## Dependencies

| Package | Where | Why |
|---------|-------|-----|
| `package_info_plus: ^8.0.2` | mobile | Reads app version at runtime from platform |
| `url_launcher: ^6.3.1` | mobile | Opens update URL in external browser/store |
| `dio` | mobile | HTTP call to `/app/config` (already a project dependency) |

---

## Ideas / future iterations

1. **Soft update mode** — show a dismissible banner instead of a blocking screen when `force_update` is false but a newer version exists
2. **Per-flavor versioning** — separate `min_version_rider` / `min_version_driver` if apps diverge
3. **Platform-aware URLs** — detect Android vs iOS and open the right store link
4. **Cache config locally** — store last-fetched config in SharedPreferences with a TTL (e.g., 1 hour) to reduce API calls
5. **Changelog in update screen** — show what's new to motivate the update
6. **Semver validation in admin** — regex or mask on the `min_version` input field
7. **In-app update (Android)** — use `in_app_update` package for seamless Play Store updates
8. **Versioned API** — the health endpoint already returns `version: '1.0.0'`, could unify
