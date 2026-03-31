# Branch Context: feat/ui-ux-polish

**Branch:** `feat/ui-ux-polish`
**Base:** `main`
**Session date:** 2026-03-31
**Status:** In progress — ready for testing, not yet merged

---

## What This Branch Does

Full UI/UX polish pass on the Anjem Flutter app. Covers five areas:

1. **Full i18n implementation** (gen-l10n, all screens)
2. **Brand colour** set to `#004743`
3. **Slide-to-confirm** buttons for high-stakes actions
4. **Language switcher** UI on both driver and rider settings screens
5. **Active ride screen** unification (driver ↔ rider layout consistency)

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

---

## Key Changes Per Area

### 1. i18n (gen-l10n)

**New files:**
- `mobile/l10n.yaml` — gen-l10n config (arb-dir, output class, nullable-getter: false)
- `mobile/lib/l10n/app_en.arb` — English source (~400 keys)
- `mobile/lib/l10n/app_id.arb` — Bahasa Indonesia (~300 translated keys)
- `mobile/lib/core/providers/locale_provider.dart` — `StateProvider<Locale>`, default `Locale('id')`
- `mobile/lib/l10n/app_localizations.dart` + `_en.dart` + `_id.dart` — generated, do not edit manually

**Modified files (hardcoded strings → l10n):**
- `login_screen.dart`, `session_check_wrapper.dart`
- All rider screens: `rider_home`, `location_selection`, `waiting`, `rider_active_ride`, `completed`, `ride_history`, `ride_details`
- All driver screens: `driver_home`, `driver_settings`, `kyc_form`, `email_verification`, `ride_request`, `active_ride`
- `core/app.dart` — added `localizationsDelegates`, `supportedLocales`, `locale: ref.watch(localeProvider)`

**ARB workflow:**
1. Add key to `app_en.arb` and `app_id.arb`
2. Run `cd mobile && flutter gen-l10n` to regenerate
3. Use `AppLocalizations.of(context).yourKey` in Dart

**Default locale:** `id` (Bahasa Indonesia). Switch via:
```dart
ref.read(localeProvider.notifier).state = const Locale('en');
```

**Known gap:** Locale preference is in-memory only — resets to `id` on cold restart. SharedPreferences persistence is not yet implemented.

---

### 2. Brand Colour `#004743`

Set in `AppConfig` for both rider and driver flavours. All primary colour references use `config.primaryColor`.

---

### 3. Slide-to-Confirm Buttons (`action_slider: ^0.7.0`)

**Package:** `action_slider: ^0.7.0` added to `pubspec.yaml`.
(^0.8.0 doesn't exist on pub.dev — use ^0.7.0.)

**Screens with sliders:**

| Screen | Button | Colour |
|--------|--------|--------|
| `ride_details_screen.dart` | Confirm Request | brand primary |
| `ride_request_screen.dart` | Accept Ride | green |
| `active_ride_screen.dart` | Mark as Arrived | orange |
| `active_ride_screen.dart` | Start Ride | blue |
| `active_ride_screen.dart` | Complete Ride | green |

**Pattern used:** `ActionSlider.standard(sliderBehavior: SliderBehavior.stretch)` with inline API calls (not delegated to `_updateRideStatus` to avoid state guard conflicts). `controller.loading()` → API → `controller.success()` or `controller.failure()` + 2s + `controller.reset()`.

---

### 4. Language Switcher

**Driver:** `_LanguageCard` widget added to `driver_settings_screen.dart` (below radius card). Uses `SegmentedButton<String>` with brand-coloured selected state.

**Rider:** New file `rider/screens/rider_settings_screen.dart` — `ConsumerWidget` with same `SegmentedButton` layout.
Settings accessed via `Icons.settings_outlined` `IconButton` in `rider_home_screen.dart` AppBar.

Both write to `localeProvider`.

---

### 5. Driver Settings: Max Pickup Radius

- **Max reduced from 20 km → 5 km** (slider: 0.5–5.0, 9 divisions)
- **"No max pickup radius" toggle** (`SwitchListTile`) sends `50.0` as sentinel to API
- On load: if API returns `>= 50.0` the toggle auto-enables
- When toggle is on: slider card is greyed out (`Opacity(0.38)`) and `onChanged: null`
- `_noMaxSentinel = 50.0` (not shown to user)

---

### 6. Active Ride Screen Unification

**Unified bottom card structure for both driver and rider:**

```
[Avatar] [Name + subtitle]  ...  [Fare badge]  [Phone icon button]
─────────────────────────────────────────────────────────────────
● Pickup location name
● Destination location name
─────────────────────────────────────────────────────────────────
(driver only) Action slider
```

**Top card (both):**
```
● Status text                              [X cancel — conditional]
  ETA (rider only, when available)
```

- Driver cancel X: visible during `accepted` and `driverArrived` only (hidden during `inProgress`)
- Rider cancel X: visible during `accepted` only (moved here from old bottom card)
- Phone button: both screens — shows snackbar (`callingDriver` / `callingRider`). Actual dialer not yet wired.

**Recenter FAB positions:**
- Driver: `bottom: 250, right: 16` (`heroTag: 'recenter_driver'`)
- Rider: `bottom: 200, right: 16` (`heroTag: 'recenter_rider'`)

---

## Pending / Pre-Launch TODOs

| # | Item | Where |
|---|------|--------|
| 1 | **Locale persistence** — save/restore chosen language via SharedPreferences | `locale_provider.dart` |
| 2 | **Phone dialer** — wire phone button to `url_launcher` `tel:` URI | both active ride screens |
| 3 | **Rider settings screen** has only the language switcher; may grow before launch | `rider_settings_screen.dart` |

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

## Files Added This Branch (mobile only)

```
mobile/l10n.yaml
mobile/lib/l10n/app_en.arb
mobile/lib/l10n/app_id.arb
mobile/lib/l10n/app_localizations.dart        ← generated
mobile/lib/l10n/app_localizations_en.dart     ← generated
mobile/lib/l10n/app_localizations_id.dart     ← generated
mobile/lib/core/providers/locale_provider.dart
mobile/lib/rider/screens/rider_settings_screen.dart
```
