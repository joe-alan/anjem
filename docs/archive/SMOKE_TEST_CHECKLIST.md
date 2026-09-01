# Smoke Test & Production Build Checklist

## How to Run

```bash
# Debug build against prod server (for smoke testing)
cd mobile
flutter run --flavor driver -t lib/main_driver.dart --dart-define-from-file=.env.prod
flutter run --flavor rider -t lib/main_rider.dart --dart-define-from-file=.env.prod
```

---

## 1. Server Health

- [x] `GET https://api.anjem.me/api/v1/health` returns 200
- [x] Admin panel loads at `https://api.anjem.me/admin`
- [x] Admin login works with seeded credentials

## 2. Authentication

- [x] Sign in with Google on rider app → token received, user created
- [x] Sign in with Google on driver app → token received, role upgraded to `both`
- [x] App shows correct user profile after login

## 3. WebSocket Connection

- [x] Driver app connects to `wss://ws.anjem.me` (check logs for "Connection established")
- [x] Rider app connects to WebSocket after login

## 4. Driver KYC Flow

- [x] Submit KYC (student email, student ID, KTM photo, profile photo)
- [x] KTM photo uploads to Firebase Storage (check Firebase Console)
- [x] Email verification code sent and received
- [x] Email verified → status shows "Pending Approval"
- [x] Admin approves in `/admin` panel → driver status changes to verified

## 5. Ride Flow (Full E2E)

- [x] Driver goes online → queue position shown
- [x] Rider creates ride request → driver receives WebSocket broadcast + FCM push
- [x] Driver accepts → ride created, rider notified
- [x] Driver updates status: accepted → driver_arrived → in_progress → completed
- [x] Credit deducted from driver on accept
- [x] Rider can rate after completion

## 6. Edge Cases

- [x] Rider cancels during search → penalty system works
- [x] Driver goes offline → rider sees "no drivers available" after timeout
- [x] App kill + reopen → `GET /session/resume` restores active ride state
- [x] Account deletion → PII wiped, re-signup works

---

## Production Build

```bash
cd mobile

# Build release AABs (for Play Store)
flutter build appbundle --flavor rider -t lib/main_rider.dart --dart-define-from-file=.env.prod
flutter build appbundle --flavor driver -t lib/main_driver.dart --dart-define-from-file=.env.prod

# Build release APKs (for device testing)
flutter build apk --flavor rider -t lib/main_rider.dart --dart-define-from-file=.env.prod
flutter build apk --flavor driver -t lib/main_driver.dart --dart-define-from-file=.env.prod
```

### Release Build Verification

- [x] Both apps launch without crash
- [x] Google sign-in works
- [x] No debug logs in `adb logcat` (all `print()` replaced with `debugPrint()`)
- [x] Maps render correctly
- [x] API calls go to `https://api.anjem.me` (not localhost)
