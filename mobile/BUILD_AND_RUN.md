# 🚀 Anjem Mobile - Build & Run Guide

## Quick Start

### Option 1: Run on Emulator/Device (Recommended)
```bash
# Make sure device/emulator is connected
flutter devices

# Run rider app
flutter run --flavor rider -t lib/main_rider.dart

# Run driver app
flutter run --flavor driver -t lib/main_driver.dart
```

### Option 2: Build APK
```bash
# Build debug APK for rider
flutter build apk --debug --flavor rider -t lib/main_rider.dart

# Build debug APK for driver
flutter build apk --debug --flavor driver -t lib/main_driver.dart

# APK location:
# build/app/outputs/flutter-apk/app-rider-debug.apk
# build/app/outputs/flutter-apk/app-driver-debug.apk
```

### Option 3: Build Release APK (Not recommended for testing)
```bash
# Requires signing configuration
flutter build apk --release --flavor rider -t lib/main_rider.dart
```

---

## Build Commands Reference

### Clean Build (if issues occur)
```bash
flutter clean
flutter pub get
flutter build apk --debug --flavor rider -t lib/main_rider.dart
```

### Check for Issues
```bash
# Static analysis
flutter analyze

# Check dependencies
flutter doctor

# List connected devices
flutter devices
```

### Hot Reload During Development
```bash
# Run and keep terminal open
flutter run --flavor rider -t lib/main_rider.dart

# Then in the app:
# - Press 'r' for hot reload
# - Press 'R' for hot restart
# - Press 'q' to quit
```

---

## Backend Setup (Required)

**Before testing, ensure backend is running:**

```bash
cd ../backend

# Start Laravel API
php artisan serve
# Runs on http://localhost:8000

# Start Laravel Reverb WebSocket (separate terminal)
php artisan reverb:start
# Runs on http://localhost:8080

# Verify database is seeded
php artisan db:seed --class=EssentialLocationsSeeder
```

---

## Testing Flow

1. **Start Backend** (both Laravel + Reverb)
2. **Build/Run App** (flutter run or build apk)
3. **Follow Testing Checklist** (see TESTING_CHECKLIST.md)

---

## Troubleshooting

### Build Fails
```bash
# Clean and retry
flutter clean
rm -rf build/
flutter pub get
flutter build apk --debug --flavor rider -t lib/main_rider.dart
```

### Gradle Issues
```bash
cd android
./gradlew clean
cd ..
flutter build apk --debug --flavor rider -t lib/main_rider.dart
```

### Can't Find Device
```bash
# List devices
flutter devices

# If emulator not running, start it
# Android Studio > AVD Manager > Run emulator

# Or from command line
emulator -avd <avd_name>
```

### Map Doesn't Load
- Check `android/gradle.properties` has `MAPBOX_DOWNLOADS_TOKEN`
- Verify internet connection
- Check AndroidManifest.xml permissions

---

## Development Tips

### Fast Iteration
```bash
# Use hot reload (fastest)
flutter run --flavor rider -t lib/main_rider.dart
# Then press 'r' to hot reload changes
```

### Debug Logs
```bash
# In separate terminal while app is running
flutter logs

# Or use adb logcat
adb logcat | grep Flutter
```

### Inspect Network Calls
```bash
# Use Flutter DevTools
flutter pub global activate devtools
flutter pub global run devtools
```

---

## What's Different from Standard Flutter?

1. **No `lib/main.dart`** - Uses `main_rider.dart` and `main_driver.dart`
2. **Flavors Required** - Must specify `--flavor rider` or `--flavor driver`
3. **Entry Point** - Must specify `-t lib/main_rider.dart`

---

Good luck with your build! 🎉
