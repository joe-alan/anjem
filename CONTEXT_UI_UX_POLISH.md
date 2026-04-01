# Branch Context: feat/ui-ux-polish

**Branch:** `feat/ui-ux-polish`
**Base:** `main`
**Session dates:** 2026-03-31 → 2026-04-01
**Status:** In progress — ready for testing, not yet merged

---

## What This Branch Does

Full UI/UX polish pass on the Anjem Flutter app. Covers:

1. **Full i18n implementation** (gen-l10n, all screens)
2. **Brand colour** set to `#004743`
3. **Slide-to-confirm** buttons for high-stakes actions
4. **Language switcher** with SharedPreferences persistence
5. **Active ride screen** unification (driver ↔ rider layout consistency)
6. **Full settings screens** (rider: profile edit + account; driver: ride settings + vehicle info + account)
7. **Distance-based fare pricing** with 20 km cap
8. **Place search** limited to Semarang (15 km radius + Mapbox bbox)
9. **Bug fixes** (action slider stale state, setState-after-dispose, driver matching validation)

---

## Commits (oldest → newest)

| SHA | Scope | Summary |
|-----|-------|---------|
| `9787662` | feat(i18n) | Add full Bahasa Indonesia localisation and link legal pages |
| `5ae05ea` | feat(ui) | Set brand color to #004743 for both rider and driver flavours |
| `db28242` | feat(ux) | Add slide-to-confirm for high-stakes driver/rider actions |
| `2a6bf06` | docs(guidelines) | Add i18n rules and locale-switching notes to CLAUDE.md |
| `ac38921` | feat(settings) | Add language switcher to driver settings and new rider settings screen |
| `0d9b8bf` | chore | Remove Docker files — dev runs natively |
| `45fc352` | chore | Commit untracked docs, admin phase 3 files, and claude.md formatting |
| `394fc23` | feat(ux) | Slide buttons for all driver actions, radius cap + no-max toggle, recenter FAB |
| `66f5b2d` | chore(l10n) | Regenerate app_localizations with new ARB keys |
| `5257a45` | fix(ux) | Unify active ride screen layout for driver and rider |
| `cc2e775` | docs | Add context dump for feat/ui-ux-polish branch |
| `dbc7a22` | feat(ux) | Settings screens, search radius fix, ride UI improvements |
| `f24401e` | feat(pricing) | Distance-based fare tiers, 20km cap with haversine pre-check |

---

## Key Changes Per Area

### 1. i18n (gen-l10n)

**New files:**
- `mobile/l10n.yaml` — gen-l10n config (arb-dir, output class, nullable-getter: false)
- `mobile/lib/l10n/app_en.arb` — English source (~430 keys)
- `mobile/lib/l10n/app_id.arb` — Bahasa Indonesia (~430 translated keys)
- `mobile/lib/core/providers/locale_provider.dart` — `StateNotifierProvider<LocaleNotifier, Locale>`, persists to SharedPreferences
- `mobile/lib/l10n/app_localizations.dart` + `_en.dart` + `_id.dart` — generated, do not edit manually

**ARB workflow:**
1. Add key to `app_en.arb` and `app_id.arb`
2. Run `cd mobile && flutter gen-l10n` to regenerate
3. Use `AppLocalizations.of(context).yourKey` in Dart

**Default locale:** `id` (Bahasa Indonesia). Switch via:
```dart
ref.read(localeProvider.notifier).setLocale(const Locale('en'));
```

**Locale persistence:** ✅ Implemented — saves to SharedPreferences, survives cold restart.

---

### 2. Brand Colour `#004743`

Set in `AppConfig` for both rider and driver flavours. All primary colour references use `config.primaryColor`.

---

### 3. Slide-to-Confirm Buttons (`action_slider: ^0.7.0`)

**Package:** `action_slider: ^0.7.0` added to `pubspec.yaml`.

**Screens with sliders:**

| Screen | Button | Colour |
|--------|--------|--------|
| `ride_details_screen.dart` | Confirm Request | brand primary |
| `ride_request_screen.dart` | Accept Ride (fixed bottom) | green |
| `active_ride_screen.dart` | Mark as Arrived | orange |
| `active_ride_screen.dart` | Start Ride | blue |
| `active_ride_screen.dart` | Complete Ride | green |

Each `ActionSlider` has a unique `ValueKey` per status to prevent stale widget state on status transitions.

**Ride request screen layout:** Decline button on top, slide-to-accept at bottom — both fixed outside the scrollable area.

---

### 4. Settings Screens

#### Rider Settings (`rider_settings_screen.dart`)
- Profile header (Google avatar, name, email — read-only display)
- Personal info (name + phone TextFields → `PATCH /user`)
- Language card (shared widget, persistent)
- Account section (logout + delete account with double confirmation)
- About (app version via `package_info_plus`)

#### Driver Settings (`driver_settings_screen.dart`)
- Ride settings (no-max toggle + radius slider 0.5–5 km → `PATCH /driver/settings`)
- Vehicle info (read-only from KYC: type, color, plate)
- Language card (shared widget)
- Account section (shared widget)
- About (app version)

Profile edit (avatar upload, name/phone) deferred to its own separate screen for driver.

#### Shared Widgets
- `core/widgets/language_card.dart` — `SegmentedButton<String>` with brand-coloured selected state
- `core/widgets/account_section.dart` — Logout + Delete Account with type-to-confirm dialog (`DELETE`/`HAPUS`)

#### Home Screen Cleanup
- Logout button removed from both rider and driver home AppBars (now in settings)
- Rider: settings icon moved to `leading` position (left side)

---

### 5. Backend: User Management Endpoints

**New controller:** `backend/app/Http/Controllers/Api/UserController.php`

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `PATCH` | `/v1/user` | Update name, phone_number |
| `POST` | `/v1/user/avatar` | Upload profile picture (stored in `public/avatars`) |
| `DELETE` | `/v1/user` | Soft-delete account (rejects if active ride, revokes tokens) |

`UserResource` now returns `phone` and `profile_picture` fields.

---

### 6. Distance-Based Fare Pricing

Replaced old base+per-km+time formula with tiered distance pricing:

| Distance | Fare |
|----------|------|
| < 1 km | Rp 5,000 |
| 1–4.9 km | Rp 5,000 + Rp 1,000 × floor(km) |
| 5–6.9 km | Rp 10,000 + Rp 1,000 × floor((km-5)/0.5) |
| 7.0 km | Rp 15,000 |
| 7.1–20 km | Tiered formula rising from Rp 17,000 |
| > 20 km | **Rejected** — ride not allowed |

**20 km cap enforcement:**
1. Haversine straight-line pre-check — if > 20 km, rejects immediately without calling Mapbox (saves API cost)
2. Driving distance check — if Mapbox route > 20 km, fare returns null → API returns 422

---

### 7. Place Search Scoping (Semarang)

- Re-enabled `ST_DWithin` proximity filter in `PlaceSearchService` — local DB results limited to search radius
- Search radius increased from 5 km → 15 km (covers Semarang, excludes Demak)
- Fixed Mapbox bbox default from Jakarta coordinates to Semarang: `110.30,-7.15,110.55,-6.90`

---

### 8. Driver Matching Fix

- `DriverController::updateSettings` validation changed from `max:20` → `max:50` to accept "no max radius" sentinel value (50.0)
- Default pickup radius changed from 5 km → 1 km across mobile app

---

### 9. Active Ride Screen

**Unified bottom card (both driver and rider):**
```
[Avatar] [Name + subtitle]  ...  [Fare]  [WhatsApp icon]
──────────────────────────────────────────────────────────
● Pickup location name
● Destination location name
──────────────────────────────────────────────────────────
(driver only) Action slider
```

- WhatsApp-style chat icon (`Icons.chat`, `#25D366`) on both screens — functionality unchanged (snackbar placeholder)
- Action sliders keyed by status to prevent blank/stale state after transitions

---

### 10. Bug Fixes

| Bug | Fix |
|-----|-----|
| Action slider goes blank after status transition | Added unique `ValueKey` per slider status |
| `setState()` called after dispose on location selection | Added `mounted` checks around debounced search callbacks |
| Driver settings rejects "no max radius" (50.0) | Validation `max:20` → `max:50` |
| Place search returns locations outside Semarang | Re-enabled proximity filter + correct bbox |

---

## Pending / Pre-Launch TODOs

| # | Item | Where |
|---|------|--------|
| 1 | **WhatsApp dialer** — wire chat button to WhatsApp deep link | both active ride screens |
| 2 | **Driver profile edit** — own screen with avatar upload, name/phone edit | new screen (deferred) |

---

## How to Test

```bash
# Generate l10n (required after any ARB edit)
cd mobile && flutter gen-l10n

# Rider flavour
flutter run --flavor rider -t lib/main_rider.dart

# Driver flavour
flutter run --flavor driver -t lib/main_driver.dart
```

**Hot restart** picks up Dart code changes but NOT ARB changes. ARB changes require a full `flutter run`.

---

## Files Added This Branch

```
# Mobile
mobile/l10n.yaml
mobile/lib/l10n/app_en.arb
mobile/lib/l10n/app_id.arb
mobile/lib/l10n/app_localizations.dart        ← generated
mobile/lib/l10n/app_localizations_en.dart     ← generated
mobile/lib/l10n/app_localizations_id.dart     ← generated
mobile/lib/core/providers/locale_provider.dart
mobile/lib/core/widgets/language_card.dart
mobile/lib/core/widgets/account_section.dart
mobile/lib/rider/screens/rider_settings_screen.dart

# Backend
backend/app/Http/Controllers/Api/UserController.php
```
