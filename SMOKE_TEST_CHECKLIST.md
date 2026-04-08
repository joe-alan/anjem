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

- [ ] `GET https://api.anjem.me/api/v1/health` returns 200
- [ ] Admin panel loads at `https://api.anjem.me/admin`
- [ ] Admin login works with seeded credentials

## 2. Authentication

- [ ] Sign in with Google on rider app → token received, user created
- [ ] Sign in with Google on driver app → token received, role upgraded to `both`
- [ ] App shows correct user profile after login

## 3. WebSocket Connection

- [ ] Driver app connects to `wss://ws.anjem.me` (check logs for "Connection established")
- [ ] Rider app connects to WebSocket after login

## 4. Driver KYC Flow

- [ ] Submit KYC (student email, student ID, KTM photo, profile photo)
- [ ] KTM photo uploads to Firebase Storage (check Firebase Console)
- [ ] Email verification code sent and received
- [ ] Email verified → status shows "Pending Approval"
- [ ] Admin approves in `/admin` panel → driver status changes to verified

## 5. Ride Flow (Full E2E)

- [ ] Driver goes online → queue position shown
- [ ] Rider creates ride request → driver receives WebSocket broadcast + FCM push
- [ ] Driver accepts → ride created, rider notified
- [ ] Driver updates status: accepted → driver_arrived → in_progress → completed
- [ ] Credit deducted from driver on accept
- [ ] Rider can rate after completion

## 6. Edge Cases

- [ ] Rider cancels during search → penalty system works
- [ ] Driver goes offline → rider sees "no drivers available" after timeout
- [ ] App kill + reopen → `GET /session/resume` restores active ride state
- [ ] Account deletion → PII wiped, re-signup works

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

- [ ] Both apps launch without crash
- [ ] Google sign-in works
- [ ] No debug logs in `adb logcat` (all `print()` replaced with `debugPrint()`)
- [ ] Maps render correctly
- [ ] API calls go to `https://api.anjem.me` (not localhost)
