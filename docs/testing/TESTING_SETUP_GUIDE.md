# Testing Setup Guide - Running Anjem Mobile Apps

## 🤖 Android-Only Testing (MVP)

**Note**: This guide focuses exclusively on Android testing. iOS support is deferred to post-MVP to accelerate development.

## Overview
This guide covers everything you need to test the mobile apps on Android Studio Emulator and physical Android devices after Firebase setup.

---

## Prerequisites Checklist

### ✅ Already Completed
- [x] Flutter SDK installed
- [x] Dependencies installed (`flutter pub get`)
- [x] Phase 1 code implementation
- [x] Firebase project created (or ready to create)

### 📋 Still Needed
- [ ] Firebase configuration files added (Android only)
- [ ] Backend server running
- [ ] Android Studio/Emulator setup

---

## Part 1: Firebase Setup (REQUIRED - 10 minutes)

### Step 1: Add Firebase Config Files

Follow `docs/FIREBASE_SETUP_GUIDE.md` to get these 2 files (Android only):

**Android:**
```
mobile/android/app/src/rider/google-services.json
mobile/android/app/src/driver/google-services.json
```

**Note**: iOS files are not needed for MVP.

### Step 2: Enable Google Sign-In
1. Firebase Console → Authentication → Sign-in method
2. Enable **Google** provider
3. Set support email
4. Save

### Step 3: Update Android Build Files (Already done, but verify)

Check `mobile/android/app/build.gradle` has product flavors:
```gradle
flavorDimensions "app"
productFlavors {
    rider {
        dimension "app"
        applicationId "com.anjem.rider"
        resValue "string", "app_name", "Anjem Rider"
    }
    driver {
        dimension "app"
        applicationId "com.anjem.driver"
        resValue "string", "app_name", "Anjem Driver"
    }
}
```

---

## Part 2: Backend Setup (REQUIRED - 5 minutes)

### Start Backend Server

Terminal 1 - Laravel API:
```bash
cd backend
php artisan serve
# Should run on http://127.0.0.1:8000
```

Terminal 2 - Laravel Reverb (WebSocket):
```bash
cd backend
php artisan reverb:start
# Should run on ws://127.0.0.1:6001
```

### Verify Backend is Running

```bash
# Test health endpoint
curl http://127.0.0.1:8000/api/v1/health

# Expected response:
# {"status": "ok"}
```

### Update API URLs for Local Testing

**For Android Emulator**, update `mobile/lib/main_rider.dart` and `main_driver.dart`:

```dart
apiBaseUrl: const String.fromEnvironment(
  'API_URL',
  defaultValue: 'http://10.0.2.2:8000/api/v1'  // 10.0.2.2 = localhost for Android Emulator
),
wsUrl: const String.fromEnvironment(
  'WS_URL',
  defaultValue: 'ws://10.0.2.2:6001'
),
```

**For Physical Android Device on same WiFi:**
```dart
// Find your computer's local IP:
// Mac/Linux: ifconfig | grep "inet "
// Windows: ipconfig

defaultValue: 'http://192.168.1.XXX:8000/api/v1'  // Replace XXX with your IP
```

---

## Part 3: Testing on Android Studio Emulator (Recommended for Development)

### Setup Android Emulator (One-time)

#### 3.1 Install Android Studio
1. Download from https://developer.android.com/studio
2. Install and open Android Studio
3. Go through setup wizard

#### 3.2 Create Virtual Device
1. Open Android Studio
2. Click **More Actions** → **Virtual Device Manager**
3. Click **Create device**
4. Select **Phone** → **Pixel 7** (or any recent device)
5. Click **Next**
6. Download a system image:
   - **Recommended**: API 34 (Android 14) - UpsideDownCake
   - Or: API 33 (Android 13) - Tiramisu
7. Click **Next** → **Finish**

#### 3.3 Start Emulator
1. Virtual Device Manager → Click ▶️ (Play) button
2. Wait for emulator to fully boot (~30 seconds)

### Run Flutter App on Emulator

#### Option 1: Using VS Code (Easiest)
1. Start emulator
2. In VS Code, press `Cmd+Shift+P` (Mac) or `Ctrl+Shift+P` (Windows)
3. Type "Flutter: Select Device"
4. Choose the emulator (e.g., "sdk gphone64 arm64")
5. Press `F5` or Run → Start Debugging
6. Select flavor: **rider** or **driver**

#### Option 2: Using Terminal
```bash
# Navigate to mobile directory
cd <repo-root>/mobile

# Check devices
flutter devices
# Should show: sdk gphone64 arm64 (mobile) • emulator-5554 • android

# Run Rider app
flutter run --flavor rider

# Or Driver app
flutter run --flavor driver
```

### First Launch Steps
1. App launches → Splash screen
2. Login screen appears
3. Tap **"Sign in with Google"**
4. Google account picker appears
5. Select account
6. Backend authenticates → redirects to home screen

### Expected Logs (Success)
```
✓ Built build/app/outputs/flutter-apk/app-rider-debug.apk
Installing build/app/outputs/flutter-apk/app.apk...
Syncing files to device sdk gphone64 arm64...
Flutter run key commands.
r Hot reload.
R Hot restart.
h List all available interactive commands.
d Detach (terminate "flutter run" but leave application running).
c Clear the screen
q Quit (terminate the application on the device).
```

---

## Part 4: Testing on Physical Android Device

### Setup Physical Device (One-time)

#### 4.1 Enable Developer Options
1. Open **Settings** on phone
2. Go to **About phone**
3. Tap **Build number** 7 times
4. Go back → **System** → **Developer options**

#### 4.2 Enable USB Debugging
1. In Developer options
2. Enable **USB debugging**
3. Enable **Install via USB** (if available)

#### 4.3 Connect Device
1. Connect phone to computer via USB
2. On phone: Tap **Allow USB debugging** popup
3. Check "Always allow from this computer"
4. Tap **OK**

### Run on Physical Device

```bash
cd mobile

# Check device is connected
flutter devices
# Should show: SM G960F (mobile) • R58M12345678 • android

# Run app
flutter run --flavor rider -d R58M12345678

# Or let Flutter auto-select
flutter run --flavor rider
```

### Testing on Same WiFi Network

If USB isn't available or you prefer wireless:

#### 4.4 Wireless Debugging (Android 11+)
1. Connect phone and computer to same WiFi
2. Phone: Settings → Developer options → Wireless debugging
3. Enable **Wireless debugging**
4. Tap **Pair device with pairing code**
5. Note the IP and port
6. On computer:
```bash
adb pair 192.168.1.XXX:XXXXX
# Enter pairing code from phone

adb connect 192.168.1.XXX:XXXXX
flutter devices
flutter run --flavor rider
```

**Important**: Update API URL to your computer's local IP:
```dart
defaultValue: 'http://192.168.1.YOUR_IP:8000/api/v1'
```

---

## Part 5: Common Issues & Solutions

### Issue 1: "google-services.json not found"
**Symptom**: Build fails with missing google-services.json
**Solution**:
```bash
# Verify files exist
ls -la mobile/android/app/src/rider/google-services.json
ls -la mobile/android/app/src/driver/google-services.json

# If missing, download from Firebase Console
```

### Issue 2: "Failed to connect to backend"
**Symptom**: Authentication fails, network error
**Solution**:
```bash
# Check backend is running
curl http://127.0.0.1:8000/api/v1/user

# For emulator, use 10.0.2.2
# For physical device, use 192.168.1.XXX (your computer's IP)

# Update main_rider.dart and main_driver.dart with correct IP
```

### Issue 3: "Google Sign-In failed"
**Symptom**: Sign-in popup closes immediately
**Solution**:
1. Check Firebase Console → Authentication → Google provider is enabled
2. Add SHA-1 certificate:
```bash
cd mobile/android
./gradlew signingReport

# Copy SHA-1 from output
# Add to Firebase Console → Project Settings → Your apps → Add fingerprint
```

3. Re-download google-services.json
4. Clean and rebuild:
```bash
flutter clean
flutter pub get
flutter run --flavor rider
```

### Issue 4: "Cleartext communication not permitted"
**Symptom**: Network requests fail on Android 9+
**Solution**: Already configured in `android/app/src/main/AndroidManifest.xml`:
```xml
android:usesCleartextTraffic="true"
```

### Issue 5: Emulator is slow
**Solution**:
1. Android Studio → Virtual Device Manager
2. Click ⚙️ (Edit) on your device
3. Show Advanced Settings
4. Graphics: **Hardware - GLES 2.0**
5. RAM: **4096 MB** (if you have 16GB+ RAM)
6. VM heap: **512 MB**

### Issue 6: Hot reload not working
**Solution**:
```bash
# In terminal where flutter run is active:
# Press 'r' for hot reload
# Press 'R' for hot restart
# Press 'q' to quit
```

### Issue 7: "Execution failed for task ':app:processRiderDebugGoogleServices'"
**Symptom**: Build fails with Google Services error
**Solution**:
```bash
# Ensure package name in google-services.json matches build.gradle
# Rider: com.anjem.rider
# Driver: com.anjem.driver

# Clean build
cd mobile
flutter clean
flutter pub get
cd android
./gradlew clean
cd ..
flutter run --flavor rider
```

---

## Part 7: Testing Checklist

### Pre-Testing Setup ✅
- [ ] Firebase config files added (4 files)
- [ ] Google Sign-In enabled in Firebase Console
- [ ] Backend server running (http://127.0.0.1:8000)
- [ ] Reverb WebSocket running (ws://127.0.0.1:6001)
- [ ] API URL updated in main files (10.0.2.2 for emulator)
- [ ] Flutter dependencies installed (`flutter pub get`)

### Basic Authentication Flow ✅
- [ ] App launches without crashes
- [ ] Splash screen appears briefly
- [ ] Login screen shows with Google Sign-In button
- [ ] Tapping button opens Google account picker
- [ ] Selecting account completes sign-in
- [ ] User is redirected to home screen
- [ ] App stays authenticated on restart
- [ ] Sign out works correctly

### Both Flavors ✅
- [ ] Rider app works (blue theme)
- [ ] Driver app works (green theme)
- [ ] Each has correct app name on device

### Device Types ✅
- [ ] Android Emulator works
- [ ] Physical Android device works
- [ ] iOS Simulator works (Mac only)
- [ ] Physical iPhone works (Mac only)

---

## Part 8: Development Workflow

### Recommended Setup for Daily Development

**Terminal Setup (4 terminals):**

```bash
# Terminal 1: Backend
cd backend && php artisan serve

# Terminal 2: WebSocket
cd backend && php artisan reverb:start

# Terminal 3: Flutter Hot Reload
cd mobile && flutter run --flavor rider

# Terminal 4: Git, testing, etc.
cd /path/to/project
```

### Hot Reload Workflow
1. Start app with `flutter run --flavor rider`
2. Make code changes
3. Press **`r`** in terminal for hot reload (< 1 second)
4. Press **`R`** for full restart (if hot reload doesn't work)
5. Press **`q`** to quit

### Debugging Tips
```bash
# Run with verbose logging
flutter run --flavor rider -v

# Run in profile mode (better performance testing)
flutter run --flavor rider --profile

# Run in release mode (final performance test)
flutter run --flavor rider --release
```

---

## Part 9: Quick Start Commands

### First Time Setup
```bash
# 1. Add Firebase files (manual step)

# 2. Start backend
cd backend
php artisan serve &
php artisan reverb:start &

# 3. Install dependencies
cd ../mobile
flutter pub get

# 4. Run on emulator
flutter run --flavor rider
```

### Daily Development
```bash
# Start backend (if not running)
cd backend && php artisan serve &
cd backend && php artisan reverb:start &

# Run app with hot reload
cd mobile
flutter run --flavor rider

# Make changes, press 'r' to reload
```

### Testing Both Flavors
```bash
# Test Rider
flutter run --flavor rider

# Stop (Ctrl+C), then test Driver
flutter run --flavor driver
```

---

## Part 10: Next Steps After Successful Test

Once authentication works:
1. ✅ **Celebrate!** 🎉 Core infrastructure is working
2. ✅ Test sign-out flow
3. ✅ Test app restart (token persistence)
4. ✅ Test on both emulator and physical device
5. ✅ Test both Rider and Driver apps
6. ⏭️ Proceed to **Phase 2: Rider App Core Flow**
   - Google Maps integration
   - Beacon locations
   - Ride request flow

---

## Quick Reference

### Flutter Commands
```bash
flutter devices           # List connected devices
flutter clean            # Clean build cache
flutter pub get          # Install dependencies
flutter doctor           # Check Flutter installation
flutter run --flavor rider    # Run rider app
flutter run --flavor driver   # Run driver app
flutter build apk --flavor rider  # Build APK
```

### Useful Shortcuts (when app is running)
- **`r`** - Hot reload (fast)
- **`R`** - Hot restart (slower, full restart)
- **`p`** - Toggle performance overlay
- **`o`** - Toggle platform (Android ↔ iOS)
- **`c`** - Clear console
- **`q`** - Quit

### IP Addresses for Local Development
- **Android Emulator**: `10.0.2.2` (maps to host localhost)
- **iOS Simulator**: `127.0.0.1` (localhost)
- **Physical Device (WiFi)**: `192.168.1.XXX` (your computer's local IP)

---

## Support

If you encounter issues:
1. Check logs in terminal
2. Search error message in Flutter docs
3. Clean and rebuild: `flutter clean && flutter pub get`
4. Restart emulator/device
5. Check backend logs: `backend/storage/logs/laravel.log`

**Common Log Locations:**
- Flutter logs: Terminal output
- Backend logs: `backend/storage/logs/laravel.log`
- Android logs: `adb logcat`
- iOS logs: Xcode → Window → Devices and Simulators → View Device Logs

---

**You're ready to test!** 🚀

Follow the steps in order, and you should have a working authentication flow within 30 minutes of Firebase setup.
