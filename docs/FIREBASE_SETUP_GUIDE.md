# Firebase Setup Guide for Anjem Mobile Apps

## 🤖 Android-Only Setup (MVP)

**Note**: iOS setup is deferred to post-MVP. This guide focuses exclusively on Android configuration to accelerate development.

## Quick Start (5 minutes)

### Prerequisites
- Firebase account (Google account)
- Access to [Firebase Console](https://console.firebase.google.com/)

---

## Step 1: Create/Access Firebase Project

1. Go to https://console.firebase.google.com/
2. Click "Add project" (or select existing "Anjem" project)
3. Project name: **Anjem**
4. Enable Google Analytics (optional)
5. Click "Create project"

---

## Step 2: Add Android Apps (Only 2 apps needed for MVP)

### Rider App (Android)
1. In Firebase console, click "+ Add app" → Android icon
2. **Android package name**: `me.anjem.rider` ⚠️ (Important: use `me.anjem` not `com.anjem`)
3. **App nickname**: Anjem Rider
4. **Debug signing certificate SHA-1**: (Optional for now)
   - Get from: `keytool -list -v -alias androiddebugkey -keystore ~/.android/debug.keystore`
   - Password: `android`
5. Click "Register app"
6. **Download `google-services.json`**
7. Place file at: `mobile/android/app/src/rider/google-services.json`

### Driver App (Android)
1. Click "+ Add app" → Android icon again
2. **Android package name**: `me.anjem.driver` ⚠️ (Important: use `me.anjem` not `com.anjem`)
3. **App nickname**: Anjem Driver
4. **Debug signing certificate SHA-1**: (Same as above)
5. Click "Register app"
6. **Download `google-services.json`**
7. Place file at: `mobile/android/app/src/driver/google-services.json`

### Configure Google Services Plugin

See `docs/FIREBASE_GRADLE_SETUP.md` for detailed Gradle configuration, but in summary:

1. Add to `android/settings.gradle.kts`:
   ```kotlin
   id("com.google.gms.google-services") version "4.4.2" apply false
   ```

2. Add to bottom of `android/app/build.gradle.kts`:
   ```kotlin
   apply(plugin = "com.google.gms.google-services")
   ```

---

## Step 3: Enable Authentication

1. In Firebase Console, go to **Authentication** → **Sign-in method**
2. Click **Google** provider
3. Enable toggle
4. **Support email**: (Your email)
5. Click **Save**

---

## Step 4: Verify File Structure

Your project should now have (Android only):

```
mobile/
└── android/
    └── app/
        └── src/
            ├── rider/
            │   └── google-services.json  ✅
            └── driver/
                └── google-services.json  ✅
```

**Note**: iOS files are not needed for MVP. They will be added post-MVP when iOS support is implemented.

---

## Step 5: Test Authentication

### Option 1: Android Emulator
```bash
cd mobile
flutter run --flavor rider
```

### Option 2: Physical Android Device
```bash
flutter run --flavor rider -d <device-id>
```

---

## Common Issues & Solutions

### Issue 1: "google-services.json not found"
**Solution**: Ensure file is in correct flavor directory:
- Rider: `android/app/src/rider/google-services.json`
- Driver: `android/app/src/driver/google-services.json`

### Issue 2: "Sign in failed - API key not valid"
**Solution**:
1. Check SHA-1 certificate is added in Firebase Console
2. Re-download `google-services.json`
3. Clean build: `flutter clean && flutter pub get`

### Issue 3: "Sign in cancelled"
**Solution**:
1. Ensure Google Sign-In is enabled in Firebase Console
2. Check support email is set
3. Try on physical device (emulator may have issues)

---

## Production Setup (Required before Play Store release)

### 1. Add Release SHA-1 Certificate (Android)
```bash
# Generate release keystore
keytool -genkey -v -keystore ~/release.keystore -alias release -keyalg RSA -keysize 2048 -validity 10000

# Get SHA-1
keytool -list -v -alias release -keystore ~/release.keystore
```

Add SHA-1 to Firebase Console → Project Settings → Your apps → Add fingerprint

### 2. Enable Firebase Cloud Messaging (Push Notifications)
1. Firebase Console → Project Settings → Cloud Messaging
2. Enable Cloud Messaging API
3. Android configuration is automatic (no additional setup needed)

---

## Backend Integration

### Update Laravel .env
After Firebase setup, update backend configuration:

```env
FIREBASE_PROJECT_ID=anjem-xxxxx
FIREBASE_CREDENTIALS=/path/to/service-account.json
```

### Generate Service Account Key
1. Firebase Console → Project Settings → Service Accounts
2. Click "Generate new private key"
3. Save as `firebase-service-account.json`
4. Place in `backend/storage/app/`
5. Update `.env` with path

---

## Verification Checklist (Android Only - MVP)

- [ ] Firebase project created
- [ ] Rider Android app registered (`com.anjem.rider`)
- [ ] Driver Android app registered (`com.anjem.driver`)
- [ ] `google-services.json` files downloaded and placed in:
  - [ ] `android/app/src/rider/google-services.json`
  - [ ] `android/app/src/driver/google-services.json`
- [ ] Google Sign-In enabled in Firebase Console
- [ ] Support email set
- [ ] Debug SHA-1 certificate added
- [ ] Backend has Firebase service account key
- [ ] Test sign-in works on Android device/emulator

**Note**: iOS setup (2 apps + plist files) deferred to post-MVP phase.

---

## Next Steps

After Firebase is configured:
1. Run the app: `flutter run --flavor rider`
2. Test Google Sign-In
3. Verify backend receives authentication request
4. Check Sanctum token is stored
5. Proceed to Phase 2: Rider App Core Flow

---

## Resources

- [Firebase Console](https://console.firebase.google.com/)
- [FlutterFire Documentation](https://firebase.flutter.dev/)
- [Google Sign-In Flutter Plugin](https://pub.dev/packages/google_sign_in)
- [Firebase Admin SDK (Laravel)](https://github.com/kreait/laravel-firebase)
