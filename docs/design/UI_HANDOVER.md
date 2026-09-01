# Anjem — UI/UX Handover

Audience: UI/UX designer prototyping a consistent brand design for the Anjem rider and driver apps in Figma. Assumes no prior exposure to the codebase.

Source of truth for code references is the `mobile/` Flutter project on the `main` branch (as of April 2026).

---

## 1. Product context

Anjem is a **campus ride-sharing platform** for short motorbike hops, modeled on Grab/Uber. It matches **riders** (anyone with a valid email) with **drivers** (verified students) operating within a university geofence — the primary target is **UNDIP / Tembalang, Semarang**.

**Two user types, one codebase, two flavors:**
- **Rider app** (`me.anjem.rider`, labeled "Anjem.me") — anyone, no student check.
- **Driver app** (`me.anjem.driver`, labeled "Anjem Driver") — students only, KYC required.

**Monetization (MVP):** No platform fee on rides. Riders pay drivers directly in cash or via the driver's own QRIS/e-wallet. Drivers pay the platform through a **prepaid credit system** — 1 credit is deducted per ride accepted. Credits are admin-granted through the Filament panel; **there is no in-app top-up** yet (the button exists but is disabled).

**Fare:** Rp 5,000 base + Rp 3,000/km (Mapbox driving distance), Rp 5,000 minimum. No surge.

**Key differentiators vs. incumbents (Grab/Gojek):** short-hop campus focus, lower fare ceiling, student drivers ("someone from your faculty"), zero platform cut, no fee friction for first-time users.

---

## 2. Screen inventory

Navigation is **imperative `MaterialPageRoute` pushes** — no `go_router`, no named routes. Shared top-of-tree wrappers (splash, auth, version) sit above flavor-specific homes.

### 2.1 Shared (both flavors)

| File | Purpose | Primary actions | Key data |
|---|---|---|---|
| `core/widgets/splash_screen.dart` | Boot / auth-loading state | — | White SVG logo on teal (`#004743`), app name, spinner |
| `core/widgets/login_screen.dart` | Auth entry | Google sign-in; "Sign in with email" review bypass (email + code) | Gradient teal bg, logo, app name, tagline (flavor-specific from l10n), T&C + privacy links |
| `core/widgets/force_update_screen.dart` | Blocks outdated builds | "Update" button (opens Play Store) | Logo, message from backend `/app/config` |
| `core/widgets/version_check_wrapper.dart` | Checks min version on boot | — | Wraps entire app |
| `core/widgets/session_check_wrapper.dart` | Resumes into active ride on cold start | — | Routes to home or in-ride screen |
| `core/widgets/account_section.dart` | Reusable logout / delete-account card | Logout; delete account (type-to-confirm) | Used in both settings screens |
| `core/widgets/language_card.dart` | Reusable locale switcher card | Tap → language dialog | ID / EN |
| `core/widgets/mapbox_map_widget.dart` | Shared map renderer | — | Markers, polylines, camera; marker taps **not wired** (TODO at line 136) |
| `core/widgets/route_map_widget.dart` | Thin wrapper showing a prebuilt route | — | Used on ride-details preview |

### 2.2 Rider screens (`mobile/lib/rider/screens/`)

| File | Purpose | Primary actions | Key data |
|---|---|---|---|
| `rider_home_screen.dart` | Map hub + ride entry | Request Ride; history icon (badge = unrated count); settings icon; My Location FAB; "View" active request banner | Mapbox centered on GPS (fallback UNDIP `-7.0523, 110.4381`), beacon markers, banners for suspension / cooldown / missing phone, pulsating bubble for unrated rides |
| `location_search_screen.dart` | Pickup + dropoff picker | Type to search (400 ms debounce); tap result; swap button; clear | Two stacked fields (green/teal dots), Mapbox + DB results, recent destinations when empty |
| `location_selection_screen_legacy.dart` | **UNUSED** | — | Flagged legacy, zero imports — safe to delete |
| `map_confirm_screen.dart` | Fine-tune pickup pin, then dropoff pin | Drag map; "Pick this location?" (>50 m); "Confirm" | Full-screen map, fixed center pin (mode-colored), top bar card, bottom sheet with resolved name + address |
| `ride_details_screen.dart` | Final confirm before request | ActionSlider to submit; special-requests text (200 ch) | Map bg with route polyline, fare estimate card (primary-tinted), "Cash payment" notice, motorcycle-only note |
| `waiting_screen.dart` | Driver matching / dispatch | Cancel Request (confirm dialog) | Interactive map, sonar pulse around rider, driver pins (blue=notified, green=active, gray=unavailable), search radius ring, bottom sheet with animated status messages, retry progress arc, color legend |
| `rider_active_ride_screen.dart` | Live tracking while driver drives | Recenter FAB; close/cancel (only in `accepted`); WhatsApp driver; auto-dismiss "Driver Found" popup | Pickup (green) / dest (red) markers, driver car marker, blue polyline (pickup phase, trimmed locally), top status card with fare + ETA, bottom driver info card (avatar, name, plate, color) |
| `completed_screen.dart` | Post-ride rating | Tap 1–5 stars; toggle tag chips; feedback textarea; Submit; Skip | Success check, large fare, from/to summary, tag chips (clean vehicle, safe driving, friendly, on time, professional, smooth ride, helpful) |
| `ride_history_screen.dart` | Past rides list | Filter chips (All / Completed / Cancelled / Not Rated); tap row → details dialog; swipe to refresh; "Rate" from dialog | Rides list with status + unrated badges, fare, driver name |
| `rider_settings_screen.dart` | Profile + account | Edit name & phone; Save; language; logout; delete account | Profile header, personal info card, language card, account section, about/version |

### 2.3 Driver screens (`mobile/lib/driver/screens/`)

| File | Purpose | Primary actions | Key data |
|---|---|---|---|
| `driver_home_screen.dart` | Hub: status, queue, stats, credits | Go Online / Go Offline FAB (center-bottom, extended); tap credits chip → bottom sheet; tap stats → drill-down; active-ride shortcut | Greeting, online/offline badge, queue position when online+idle, today's earnings card, rides / rating / KYC stats grid, credits chip (red=0, orange=1–4, green=5+), suspension / pending-approval banners |
| `ride_request_screen.dart` | Incoming request modal (full-screen `MaterialPageRoute(fullscreenDialog: true)`) | ActionSlider to accept; Decline; implicit decline on back; auto-decline at 0 s | 20 s countdown + progress bar, pickup/dropoff rows, fare, passenger count, pickup distance & ETA (Mapbox), special requests, haptic at entry + 5 s warning |
| `active_ride_screen.dart` | Driver's live ride | Three sequential ActionSliders: "Mark as Arrived" → "Start Ride" → "Complete Ride"; close/cancel (accepted or arrived only); Recenter FAB; WhatsApp rider | Live map, pickup/dest markers, dynamic polyline (blue=to pickup, green=to dest), top status card with fare + close, bottom rider info card, admin-override banner if present |
| `driver_ride_history_screen.dart` | Past rides | Filter chips (All / Completed / Cancelled); tap row → dialog | Dates, routes, fares, rider name, rating stars in dialog |
| `earnings_history_screen.dart` | Earnings summary | Pull to refresh | 3-column card (today / this week / this month), list of completed rides with fares |
| `rating_history_screen.dart` | Ratings overview | Tap rated ride → feedback dialog | Hero card with big rating + star row + 5→1 distribution bars, per-ride list with rider avatar, stars, date, feedback snippet |
| `driver_settings_screen.dart` | Driver prefs | Toggle "Accept from anywhere"; adjust Max Pickup Radius slider (0.5–5 km, 9 steps); Save; language; account section | Ride settings card, read-only vehicle info card (type, color, plate), about |
| `email_verification_screen.dart` | Verify student `.ac.id` email | Enter 6-digit code (supports paste); Verify; edit email; resend (30 s cooldown, hourly cap) | Email icon, 6 code boxes, countdown, attempts-remaining warning, info box |
| `kyc_form_screen.dart` | 3-page onboarding wizard | Page 1: student email live-check, ID, name, WhatsApp. Page 2: vehicle type (car shows "not supported"), license plate (3 boxes), color dropdown. Page 3: KTM photo (camera/gallery), profile photo, T&C checkbox, Submit | Top progress bar (1/3 → 3/3), rejection banner if previously rejected, auto-saved drafts |
| `kyc_status_screen.dart` | Read-only KYC summary | "Edit KYC" (requires typing "WARJO" to confirm; disabled if online) | Status badge (verified / pending approval / email pending / not submitted / suspended), details rows, KTM photo, suspension reason |

### 2.4 Flow reach

- **Shared screens entered by both flavors:** SplashScreen, LoginScreen, ForceUpdateScreen.
- **Rider-only:** all `rider/screens/*`.
- **Driver-only:** all `driver/screens/*`.
- **Admin panel:** web-only (Filament at `api.anjem.me/admin`), **out of scope** for this handover.

---

## 3. User flows

All flows start after cold launch → `VersionCheckWrapper` → `FcmInitializer` → `AuthenticationWrapper` routes based on auth + (for driver) KYC state.

### 3.1 Shared: email OTP + Google auth

```
SplashScreen
  → LoginScreen
      ├─ "Sign in with Google" → Firebase Google OAuth → backend exchanges token for Sanctum
      └─ "Sign in with email" (dialog) → enter email + review code → backend verifies
  → AuthenticationWrapper re-evaluates:
      ├─ rider → RiderHomeScreen
      └─ driver → KYC-state routing (see 3.3)
```

Terms and Privacy links open `https://www.anjem.me/syarat-dan-ketentuan/` and `/kebijakan-privasi` in an external browser.

### 3.2 Rider flows

**Onboarding (rider):** No KYC. After first Google sign-in → land on `RiderHomeScreen`. Missing phone is surfaced as an in-home orange card → user taps "Go to Settings" → `RiderSettingsScreen` → save. Request Ride is disabled until a phone is saved.

**Booking (happy path):**
```
RiderHomeScreen
  → tap "Request Ride"
  → LocationSearchScreen (pickup auto-detected = closest beacon ≤2 km; dropoff manual)
  → MapConfirmScreen (mode: pickup) — drag / "Pick this location?" / Confirm
  → MapConfirmScreen (mode: dropoff) — same pattern
  → RideDetailsScreen — review route + fare; optional special requests; slide to confirm
  → WaitingScreen — dispatched; sonar pulse; driver pins; "Finding driver" / "Notifying" / "No drivers" + retry countdown (max 3)
  → RiderActiveRideScreen (pushReplacement) — driver matched; live tracking
  → CompletedScreen (pushReplacement) — rate (stars + tag chips + optional feedback) or skip
  → RiderHomeScreen (pushReplacement)
```

**In-ride:** `RiderActiveRideScreen` consumes WebSocket driver-location updates + ride-status transitions. A dismissible "Driver Found" card appears on first entry (5 s auto-dismiss). A cancel button is only available while status is `accepted`.

**Post-ride:** `CompletedScreen` can also be reached later from `RideHistoryScreen` (unrated filter → "Rate" in details dialog) with `fromHistory: true` — behavior differs (pop vs. pushReplacement home).

**Top-up:** Not implemented in the rider app (riders don't have credits).

### 3.3 Driver flows

**Onboarding / KYC** — `AuthenticationWrapper` routes by `kycSubmission.status`:
```
authenticated but no submission
  → KycFormScreen (3 pages)
  → pushReplacement → EmailVerificationScreen (autoSend=true)
  → (on verify) → pushAndRemoveUntil → DriverHomeScreen
submitted but email not verified
  → EmailVerificationScreen (autoSend=false, resume)
emailVerified (awaiting admin) | verified
  → DriverHomeScreen (home shows "pending approval" banner if not yet verified)
fetch failed
  → _KycLoadErrorScreen (retry button) — never falls through to KycForm (avoids re-submission for verified drivers)
```

KYC edits from `KycStatusScreen` require typing "WARJO" to confirm and are blocked while online. For a "both" role user, editing driver KYC warns that it also affects rider profile.

**Going online:**
```
DriverHomeScreen
  → tap "Go Online" FAB
  → permission sequence: notification → foreground location → background location → battery optimization (one-shot)
  → if credits < 1 → bottom sheet prompt (top-up disabled placeholder)
  → backend /driver/online → queue_joined_at set → FAB switches to "Go Offline" (red)
  → foreground service notification starts (currently hardcoded English)
```

**Incoming ride (full-screen):**
```
(online + idle)
  → FCM push + WebSocket dispatch
  → driverIncomingRequestProvider fires
  → DriverHomeScreen pushes RideRequestScreen via MaterialPageRoute(fullscreenDialog: true)
  → 20 s countdown + progress bar (turns red at ≤5 s)
      ├─ slide Accept → POST /rides/{id}/accept → pushReplacement ActiveRideScreen
      ├─ tap Decline or back → POST decline → pop with snackbar
      └─ timeout → auto-decline → pop with snackbar
```
This is **not an Android `FULL_SCREEN_INTENT`** — the app is woken by FCM and the route is pushed inside the Flutter navigator. On terminated state, `NotificationClickHandler` deep-links via `navigatorKey`.

**In-ride (driver):**
```
ActiveRideScreen (status = accepted)
  → slide "Mark as Arrived" → status = driver_arrived
  → slide "Start Ride" → status = in_progress
  → slide "Complete Ride" → status = completed
  → pop back to DriverHomeScreen
```
Route polyline is fetched once at accept and trimmed locally as driver moves; re-fetches if off-route >100 m. On complete, if balance hits 0, backend auto-kicks driver offline (snackbar reason surfaces on home).

**Going offline:** Tap FAB when idle. Disabled during active ride. App-detached lifecycle also makes a best-effort `/driver/offline` call on app close.

### 3.4 Shared: wallet / history

- **Rider wallet:** none. Payment is cash / driver's own QRIS outside the app.
- **Driver wallet:** credits chip in `DriverHomeScreen` AppBar → tap → `showModalBottomSheet` with balance, status label, "how credits work" rows, and a "Top-Up" button that **opens the marketing site in an external browser**. Top-up is handled off-app; the app only hands the driver off to a web page. (The button in code is currently disabled — design should turn it into an active primary CTA that launches an external URL.)
- **Ride history:** separate screens per flavor (`ride_history_screen.dart` vs. `driver_ride_history_screen.dart`), structurally similar, styled independently.

---

## 4. Existing design snapshot

**Honest framing:** there is one consistent brand color and a few theme-level button/app-bar defaults. Everything else — typography, spacing, radii, component patterns, input styling — is ad-hoc and per-screen. Treat this section as inventory, not as a design system.

### 4.1 Theme (`mobile/lib/core/app.dart`)

```dart
ThemeData(
  colorScheme: ColorScheme.fromSeed(seedColor: #004743, brightness: Brightness.light),
  useMaterial3: true,
  appBarTheme: AppBarTheme(elevation: 0, centerTitle: true),
  elevatedButtonTheme: ElevatedButtonThemeData(
    style: ElevatedButton.styleFrom(
      minimumSize: Size.fromHeight(50),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
    ),
  ),
)
```

No `TextTheme`, no `CardTheme`, no `InputDecorationTheme`, no `DialogTheme`, no `SnackBarTheme`, no extensions.

### 4.2 Colors

**Single brand constant:** `Color(0xFF004743)` (dark teal) — passed into `AppConfig.primaryColor` in both `main_rider.dart` and `main_driver.dart`. Used as AppBar seed, primary buttons, active states, route polylines.

**Semantic colors (hardcoded per-screen, no tokens):**

| Role | Values seen | Notes |
|---|---|---|
| Success / active / online | `Colors.green`, `#4CAF50`, `#25D366` (WhatsApp) | Three distinct greens in circulation |
| Warning / pending | `Colors.orange` + shades `.50/.200/.700/.900` | KYC banners, cancel warnings |
| Danger / cancel | `Colors.red` + `.50` for tints | Deletes, cancellations, errors |
| Info / notified driver | `Colors.blue`, `#2196F3` | Progress, driver pins (notified) |
| Neutral / unavailable | `Colors.grey` + shades, `#9E9E9E`, `#E0E0E0` | Disabled, tracks |
| Surface overlay | `Colors.black.withOpacity(0.1–0.5)` | Map gradients, sheet shadows |

Map pin semantics used in rider app: green = pickup / active driver, red = destination, blue = driver in-progress / notified, gray = unavailable.

### 4.3 Typography

System fonts only (Roboto / SF). No custom type face, no `TextTheme`, no scale. Replacing the system face with a different typeface is acceptable as long as it is **free to use** (licensing budget is zero).

Observed `fontSize` values in screens: `10, 11, 12, 13, 14, 15, 16, 18, 20, 24, 28, 32, 36, 40, 48`. `FontWeight.bold` and `FontWeight.w600` are used interchangeably for headings; `.w500` appears sporadically. Most body text leaves weight default.

**Representative heavy-weight uses:** splash/login app name 32–36; fare display (ride details, completed) 28–40; section headings 18–20 bold; card labels 12–14 gray.

### 4.4 Spacing

No scale. Actual values in `EdgeInsets` and `SizedBox`: `2, 4, 6, 8, 10, 11, 12, 14, 16, 20, 24, 28, 32, 36, 48`. Most common: `8, 12, 16, 24`. Values like `11, 14, 36` likely unintentional.

Common patterns: `EdgeInsets.all(16)` for cards, `EdgeInsets.symmetric(horizontal: 16)` for screen gutters, `EdgeInsets.fromLTRB(24, 20, 24, 32)` for driver home hero area.

### 4.5 Border radius

Observed: `2, 4, 8, 10, 12, 16, 20`. Theme default for ElevatedButton is `12`. Bottom sheets typically `16` or `20` (vertical top only). Filter chips 20 (pill). Some small overlays 8. No semantic mapping.

### 4.6 Elevation / shadow

- `elevation: 0` on AppBars (from theme).
- `elevation: 2` on most `Card` instances — this is the only common value.
- Custom `BoxShadow(color: black10%, blurRadius: 10, offset: Offset(0, -2))` on bottom sheets / floating cards in `map_confirm_screen.dart`, `waiting_screen.dart`, `location_search_screen.dart`.

### 4.7 Recurring component patterns (ad-hoc, not extracted)

- **Status chip** — colored pill for ride status; rebuilt inline in rider and driver history screens (different shades).
- **Filter chip** — `_FilterChip` class duplicated in history screens; not shared.
- **Location row** — green/red colored dot + name, used in ride request, active ride, map confirm, ride details, completed — built inline every time.
- **KYC warning box** — orange tinted card with border + icon, repeated in 3+ places with slight variation.
- **Info banner** — colored `Container` with icon + text + optional action button, rebuilt per screen.
- **Empty state** — `Center → Column → large gray icon + label`, similar but not identical across history screens.
- **ActionSlider** — `action_slider: ^0.7.0` package, used for ride confirm (rider) and accept/arrived/start/complete (driver). Primary reusable interaction.
- **AppBar** — mostly default; one or two screens override `automaticallyImplyLeading: false` (e.g. `completed_screen.dart`) or replace `leading` with a custom close button (e.g. `location_search_screen.dart`).

### 4.8 Icons & imagery

- **Material Icons** — dominant.
- **`font_awesome_flutter`** — in `pubspec.yaml`; used on the driver/rider active-ride WhatsApp button; otherwise scarce.
- **`material_design_icons_flutter`** — in `pubspec.yaml`; scattered usage.
- **SVG:** `mobile/assets/images/logo.svg` is the only image asset checked into the repo. Tinted white with `ColorFilter.mode` on splash and login.
- **Missing asset:** `login_screen.dart:164` references `assets/images/google_logo.png` but the file is **not on disk**. The `errorBuilder` silently falls back to `Icons.login`. Users currently see a generic door icon on the Google sign-in button.
- App launcher icons live under `mobile/android/app/src/main/res/mipmap-*` (default Flutter generated — not audited here).

### 4.9 Splash and login treatment

- Splash: solid `#004743` fill, logo tinted white, app name 32 bold white, small spinner.
- Login: top-to-bottom gradient `#004743 → #004743@70%`, SafeArea, white logo, app name 36 bold, flavor-specific tagline 18 white@90%, white ElevatedButton ("Continue with Google") with Google icon (currently fallback icon), small "Sign in with email" TextButton below, 12 px T&C footer.
- **Logo lockups:** default = **filled white logo on brand (`#004743`) background**. Inverted = **filled brand-color logo on white / transparent background** (used anywhere the surface under the logo is light).

---

## 5. Known pain points

### 5.1 Tracked TODOs / stubs

- **Top-up button should deep-link to the web.** Top-up lives off-app. The existing disabled `FilledButton.icon` at `driver_home_screen.dart:399` should become an active primary CTA that opens an external URL (exact URL TBD by product).
- **Map marker taps are a no-op by design.** `mapbox_map_widget.dart:136` has a `TODO: Setup marker tap listener`, but the product decision is that taps on markers (driver pins, pickup / destination, beacons) do nothing. The TODO can be closed — do not design marker popovers, tooltips, or detail sheets.
- **Legacy file** — `rider/screens/location_selection_screen_legacy.dart` is unreferenced. Kept around, safe to delete.

### 5.2 Checklist state (from `docs/archive/STAGING_LAUNCH_CHECKLIST.md`)

Completed in a previous pass (3.1, 3.3, 3.4, 3.5, 3.6, 3.7): visual consistency audit, empty states, error states, history polish, onboarding review, credit chip error handling.

**Deferred and still open:** **3.2 — replace spinners with skeletons / shimmer** where appropriate. Every loading state in the app today is a `CircularProgressIndicator`. Lists (history, earnings, ratings) would especially benefit from skeleton rows.

### 5.3 Visible inconsistencies a designer will notice

- **Greens multiply.** `Colors.green`, `#4CAF50`, and `#25D366` all appear. No rule; pick one per role.
- **Orange via ad-hoc shades.** Warning surfaces use `Colors.orange.shade50/200/700/900` inconsistently. Designer should define an actual warning palette.
- **Button radii drift.** Theme default is 12; screens reach for 8, 16, or 20 depending on context. No semantic hierarchy.
- **Bottom sheet vs. full-screen modal** isn't principled: credits info is a bottom sheet, incoming ride request is a full-screen dialog, location search is a full route, KYC dialogs are `AlertDialog`. The rationale is "ride request needs urgency and takes the whole screen," but nothing documents it.
- **Back button behavior varies.** Some screens use default AppBar back, `completed_screen.dart` sets `automaticallyImplyLeading: false`, `location_search_screen.dart` replaces leading with a custom close avatar, `ride_request_screen.dart` blocks back during processing via `PopScope`.
- **Loading UX is all spinners.** No skeletons, no shimmer. Feels slower than it is on list-heavy screens.
- **Empty states inconsistent.** Icon + text pattern is repeated but unstyled identically; some screens have friendlier copy, others are terse.
- **Foreground service notification needs fresh design.** `DriverLocationService.start()` ships default English copy and no branded treatment. This persistent system-shade notification should be redesigned (copy, small branded icon, localized ID/EN). Tracked as a deliverable in §8.
- **Missing Google logo asset** (see §4.8). Currently rendering a generic door icon on the main CTA in login.
- **Filter chips rebuilt per screen.** Rider and driver history filter chips look similar but diverge in padding, color tinting, and tap target size.
- **Pulsating "unrated rides" bubble** on `rider_home_screen.dart` is a one-off UI primitive with no analog elsewhere — consider standardizing attention affordances.

### 5.4 Non-UI caveats worth knowing

- Pre-existing race condition in `rider_active_ride_screen.dart` where concurrent WebSocket + polling cancel events can stack navigation pushes. Edge case; users may briefly see duplicated "cancelled" transitions. Flagged in PR #29 review, not yet fixed.
- Driver foreground location service uses a persistent system notification while online. This is intentional (Android OS requirement). Design should accept that an external OS notification is always co-present on the driver's shade.

---

## 6. Technical constraints

### 6.1 Platform

- **Android only for launch.** `mobile/ios/` exists but is untouched and not part of the pipeline. No iOS flavors, no Cupertino-specific UI. Design for Material 3 on Android, portrait primary.
- **Flavors:** `me.anjem.rider` (label "Anjem.me") and `me.anjem.driver` (label "Anjem Driver"). Same codebase, different app IDs and launcher labels. Users with the "both" role install **both flavors as separate apps** on the same device — this redesign is effectively for two distinct app products.
- **minSdk / targetSdk:** inherited from Flutter defaults (`flutter.minSdkVersion` / `flutter.targetSdkVersion`) — effectively `minSdk 21` (Android 5.0) and `targetSdk 35` (Android 15) given Flutter 3.24. Edge-to-edge behavior is required by Play Store at this target, though the app does not explicitly configure it yet (AppBars rely on default SafeArea).
- **Orientation:** not locked. In practice the app is always used in portrait; landscape is not designed for and will produce awkward layouts (map + bottom sheets collapse).

### 6.2 Design system baseline

- **Material 3** (`useMaterial3: true`). Do not mix Material 2 components.
- Single seed color, **light only. No dark-mode theme defined and no plans to ship one.**
- Button theme sets 50 px height + 12 px radius globally; override deliberately or stay consistent.

### 6.3 FCM and notifications

- FCM default channel is `anjem_rides` (set in `AndroidManifest.xml` metadata), `Importance.max` + `Priority.max` — produces heads-up notifications on Android 7.1+.
- Second channel `anjem_general` (`Importance.default`) for KYC / general updates.
- **Driver ride request is not a system `FULL_SCREEN_INTENT`.** FCM wakes the app; `RideRequestScreen` is pushed as an in-app `MaterialPageRoute(fullscreenDialog: true)`. A silent FCM fallback shows a minimal heads-up if the app can't handle it in-foreground. The notification is cancelled by ID (`9001`) once the in-app screen takes over.
- Terminated-state taps route via the global `navigatorKey` in `core/navigation/navigator_key.dart`.
- **Driver foreground service notification** (geolocator) persists while online. `MainActivity.kt` contains custom reflection-based cleanup of orphan notifications after OEM kills, and a `MethodChannel` (`me.anjem.driver/notifications`) for belt-and-suspenders cleanup from Dart on cold start.

### 6.4 Permissions (driver app)

Requested in sequence on `DriverHomeScreen` load:

1. `POST_NOTIFICATIONS`
2. `ACCESS_FINE_LOCATION` / `ACCESS_COARSE_LOCATION` (when-in-use)
3. `ACCESS_BACKGROUND_LOCATION` (requested **after** foreground grant)
4. `REQUEST_IGNORE_BATTERY_OPTIMIZATIONS` (one-shot, persisted flag)

Rider app requests location when-in-use only and `POST_NOTIFICATIONS`.

### 6.5 Maps

- Mapbox via `mapbox_maps_flutter: ^2.3.0`. Default Mapbox style; no custom layers. Mapbox token is injected via `--dart-define=MAPBOX_ACCESS_TOKEN=…`.
- Marker styling today uses the default Mapbox pin with a size multiplier (1.5×–1.8×) and color tinting. There is no custom marker asset pipeline. **Markers are non-interactive by design — taps are no-ops.**
- **Geofence:** **UNDIP Tembalang, Semarang** (centered near `-7.0523, 110.4381`). The Jakarta / Depok bbox that appears in `docs/PRODUCT_AND_OPS.md` is legacy and not in use — ignore it.
- Route geometry is computed backend-side at request creation and served as GeoJSON; the mobile client prefers that over a second Mapbox call.

### 6.6 Localization

- Flutter `gen-l10n` with `app_en.arb` and `app_id.arb`. **Default locale is Indonesian (`id`)**, set in `core/providers/locale_provider.dart`. No in-app language switcher UI yet beyond the settings language card.
- Never hardcode user-visible strings. One known violation: `DriverLocationService.start()` default parameters (English only).

### 6.7 State management

Riverpod. All screens are `ConsumerStatefulWidget` / `ConsumerWidget`. Real-time updates flow through providers backed by WebSocket (Laravel Reverb / Pusher protocol). Design should not assume optimistic updates — ride state transitions are server-authoritative.

### 6.8 System UI

- No `SystemChrome.setSystemUIOverlayStyle()` calls. Status bar theming is Material default.
- No edge-to-edge insets configuration. Expect default SafeArea padding on all screens.

### 6.9 Screen-size targets

Design against common Android portrait sizes. Representative:
- 360 × 780 dp (baseline compact phone — Samsung A-series).
- 390 × 844 dp (medium phone).
- 412 × 915 dp (Pixel 7 class).

xxhdpi / xxxhdpi densities most common. No tablet use case.

### 6.10 Branding constants

- Brand color: `#004743` (dark teal).
- App display names: "Anjem.me" (rider), "Anjem Driver" (driver). Use "Anjem" as the brand mark in Figma when both apps are referenced.
- Marketing URLs: `https://www.anjem.me/syarat-dan-ketentuan/` (T&C), `https://www.anjem.me/kebijakan-privasi` (Privacy). Both always open in the external browser — no in-app webview is planned for policy content.

---

## 7. Brand direction (to fill in together)

This section is a skeleton. The designer and I will complete it before Figma work starts — treat the bullets as prompts, not answers.

### 7.1 Audience

Primary: **UNDIP students in Tembalang, Semarang**, ages ~18–24.
Secondary: non-student riders within the same geofence (staff, visitors, nearby residents).
Context: short hops between class buildings, to kos/boarding houses, to bus stops and warungs, often during tight class-changeover windows.

To define together:
- Frequency profile (daily commuter vs. occasional rider).
- Price sensitivity (expected: high — competing with walking).
- Device mix (likely mid-range Android, mixed network quality).

### 7.2 Positioning

Working positioning: _"The short-hop campus ride — cheaper than Grab, run by someone from your faculty."_

To define together:
- **Vs. Grab / Gojek:** how much of our personality is "student-run, informal, neighborly" vs. "serious ride-share lite"?
- **Vs. ojek pangkalan** (informal motorbike stands): we're safer/known, app-based, transparent fare.
- **Tone:** Indonesian-first, casual but trustworthy. Avoid corporate blue-tech aesthetic. Avoid overly-campus-mascot cuteness (alienates non-student riders).

### 7.3 Mood anchors (agreed)

- **Illustration style:** **flat geometric**, broadly. Empty states specifically use **monoline linearts** (single-weight strokes, no fills).
- **Typography:** free-to-use only. System fonts are fine; a custom free face is welcome if it adds personality.
- **Logo usage:** filled white logo on brand (`#004743`) background by default; inverted (brand-color logo on white / transparent) when placed on light surfaces.
- **Color extension:** one or two accent colors that sit next to `#004743` and don't collide with the existing red / orange / green semantic uses.
- **Photography / texture** (if used): campus life, bikes and helmets, warm daylight.

### 7.4 Brand guardrails

- Keep student drivers visible in the product — avatars, first names, faculty, not faceless.
- Never imply Anjem insures or employs drivers (ToS stance: matching platform, not carrier).
- Don't lean on surge-pricing or premium-tier visuals — there's no surge and no tier.

---

## 8. Deliverable expectations

What I want back from the design phase, in rough priority:

1. **Design tokens** (Figma variables):
   - Color: primary ramp, semantic roles (success / warning / danger / info / neutral) with accessible shades, surface & overlay tokens.
   - Typography: 5–6 named styles (display, title, subtitle, body, label, caption) — define font family (system or custom) and map to Flutter `TextTheme`.
   - Spacing: 4-point scale (4 / 8 / 12 / 16 / 24 / 32 / 48).
   - Radius: at most 3 values (small / medium / pill).
   - Elevation: 2–3 steps with consistent shadow recipes.

2. **Component library** — covering at minimum:
   - Buttons (primary / secondary / tertiary / destructive, plus loading and disabled states).
   - Input fields (with label, helper, error, prefix/suffix).
   - Cards (elevated, outlined).
   - Status chips and filter chips (unified across flavors).
   - Location row (pickup / dropoff colored dot + name + subtitle).
   - Info / warning / error / success banners.
   - Empty states (with illustrations if we commit to an illustration style).
   - Loading states (skeleton rows for list screens, in addition to spinners).
   - Modal patterns: bottom sheet, full-screen dialog, AlertDialog.
   - Rating stars, tag chips, fare display.
   - Map overlays: pin styles (pickup, destination, driver idle / notified / active / unavailable), top status card, bottom driver/rider info card.

3. **Full-screen prototypes (Figma)** for every screen in §2.2 and §2.3, in both states where meaningful (loading, error, empty, populated). Include:
   - Splash & login (both flavors).
   - **New rider onboarding flow** (first-run value-prop / short tutorial before the first ride). This does not exist in code today and needs designing from scratch.
   - Complete rider booking flow (home → search → confirm → details → waiting → active → completed → history).
   - Complete driver KYC flow (3-page wizard + email verification + KYC status).
   - Complete driver going-online → incoming request → in-ride → completion cycle.
   - **Driver-to-rider rating UI** — post-completion flow where the driver rates the rider. Backend schema supports it; the mobile flow is not yet built but will ship eventually.
   - Settings screens (both flavors).
   - Force-update screen, suspension state, "no internet" KYC retry screen.
   - **Driver foreground-service notification** — copy, small branded icon, ID/EN localized variants.

4. **Flow diagrams** updated to match the prototypes — one per flow listed in §3.

5. **Style guide** document (can live in Figma) covering:
   - Color usage rules (what each role means, contrast ratios).
   - Typography hierarchy and when to use each style.
   - Spacing and layout grid guidance.
   - Do's and don'ts for icons (Material vs. FontAwesome decision).
   - Voice and tone, including Indonesian vs. English phrasing and when to mix.

6. **Asset exports** (PNG / SVG) for any new illustrations, custom markers, and the Google logo replacement that's currently missing on login.

7. **Implementation-facing notes** where a design choice has Flutter implications — e.g., "this radius should be a token named `radius.md`," "this status-card surface uses elevation level 2," "this chip needs a 40 px min tap target." I'll translate these into Dart theme updates.

Out of scope for the designer: admin panel (Filament), backend templates, marketing site.

---

## Open Questions

One question remains open after the product review. Needs an answer before or during the Figma phase.

1. **Attention affordances** — the pulsating "unrated rides" bubble on rider home is currently the only proactive nudge in the app. Do we want a broader surface for this (a notification / inbox centre, a banner pattern, a persistent badge elsewhere), or keep poking inline as it is today? This has implications for how we design future nudges (promos, driver messages, KYC reminders).
