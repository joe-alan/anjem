# Anjem Mobile - Quick Start Cheatsheet

## 🤖 Android-Only MVP

**Note**: iOS development is deferred to post-MVP. This guide covers Android testing only.

## 🚀 Get Testing in 10 Minutes

### Step 1: Firebase Setup (5 min - Android only)
```bash
# 1. Go to: https://console.firebase.google.com/
# 2. Create/select "Anjem" project
# 3. Add 2 Android apps with these package names:
#    - Android Rider: com.anjem.rider
#    - Android Driver: com.anjem.driver
# 4. Download config files:
#    - google-services.json → android/app/src/rider/
#    - google-services.json → android/app/src/driver/
# 5. Enable Google Sign-In: Authentication → Sign-in method → Google
```

### Step 2: Start Backend (2 min)
```bash
# Terminal 1 - API
cd backend && php artisan serve

# Terminal 2 - WebSocket
cd backend && php artisan reverb:start
```

### Step 3: Run Mobile App (3 min)
```bash
cd mobile

# For Android Emulator (recommended for first test)
flutter run --flavor rider

# For Physical Device
flutter run --flavor rider -d <device-id>
```

---

## 📱 Testing Scenarios

### Scenario 1: Android Emulator (Easiest)
```bash
# 1. Open Android Studio → Virtual Device Manager
# 2. Create/start Pixel 7 emulator
# 3. Update API URL in main files:
apiBaseUrl: 'http://10.0.2.2:8000/api/v1'  # 10.0.2.2 = localhost
wsUrl: 'ws://10.0.2.2:6001'

# 4. Run app
cd mobile
flutter run --flavor rider

# 5. Test sign-in flow
```

### Scenario 2: Physical Android Device (Same WiFi)
```bash
# 1. Find your computer's IP
ifconfig | grep "inet "  # Mac/Linux
# ipconfig  # Windows
# Example: 192.168.1.105

# 2. Update API URL in main_rider.dart:
apiBaseUrl: 'http://192.168.1.105:8000/api/v1'
wsUrl: 'ws://192.168.1.105:6001'

# 3. Enable USB debugging on phone
# Settings → About phone → Tap Build number 7 times
# Settings → Developer options → USB debugging ON

# 4. Connect phone via USB
flutter devices
flutter run --flavor rider

# 5. Test sign-in flow
```

---

## 🔍 File Locations Quick Reference

### Firebase Config Files (Android Only)
```
mobile/
└── android/app/src/
    ├── rider/google-services.json        ← Add this
    └── driver/google-services.json       ← Add this
```

### Main Entry Points
```
mobile/lib/
├── main_rider.dart      ← Update API URL here
├── main_driver.dart     ← Update API URL here
└── core/
    ├── app.dart
    ├── services/
    ├── models/
    ├── providers/
    └── widgets/
```

### Backend Files
```
backend/
├── .env                           ← Database config
├── app/Http/Controllers/Api/
│   └── AuthController.php         ← /auth/firebase endpoint
├── routes/api.php                 ← API routes
└── storage/logs/laravel.log       ← Check errors here
```

---

## 🐛 Quick Troubleshooting

### Problem: Build fails with "google-services.json not found"
```bash
# Solution: Add Firebase config files
ls -la mobile/android/app/src/rider/google-services.json
# If missing, download from Firebase Console
```

### Problem: "Failed to connect to backend"
```bash
# Check backend is running
curl http://127.0.0.1:8000/api/v1/health

# For emulator, use: http://10.0.2.2:8000/api/v1
# For physical device, use: http://YOUR_IP:8000/api/v1
```

### Problem: Google Sign-In fails
```bash
# 1. Check Google Sign-In is enabled in Firebase Console
# 2. Add SHA-1 certificate:
cd mobile/android
./gradlew signingReport
# Copy SHA-1 → Firebase Console → Add fingerprint
# Re-download google-services.json
flutter clean && flutter pub get && flutter run --flavor rider
```

### Problem: Emulator is too slow
```bash
# Use hardware acceleration:
# Android Studio → Virtual Device Manager → Edit device
# Graphics: Hardware - GLES 2.0
# RAM: 4096 MB
```

### Problem: App crashes on startup
```bash
# Check logs
flutter run --flavor rider -v

# Common causes:
# - Firebase config missing
# - Backend not running
# - Wrong API URL
```

---

## 💡 Development Tips

### Hot Reload Workflow
```bash
flutter run --flavor rider
# Make changes in code
# Press 'r' for hot reload (< 1s)
# Press 'R' for full restart
# Press 'q' to quit
```

### Test Both Flavors
```bash
# Rider app (blue theme)
flutter run --flavor rider

# Driver app (green theme)
flutter run --flavor driver
```

### Clean Build (if things break)
```bash
cd mobile
flutter clean
flutter pub get
flutter run --flavor rider
```

### View Logs
```bash
# Flutter logs: Terminal output
# Backend logs: backend/storage/logs/laravel.log
# Android logs: adb logcat
```

---

## 📋 Authentication Flow Checklist

### Expected Flow:
1. ✅ App launches → Splash screen (2-3 seconds)
2. ✅ Login screen appears
3. ✅ Tap "Sign in with Google"
4. ✅ Google account picker opens
5. ✅ Select account
6. ✅ Backend receives Firebase token
7. ✅ Backend returns Sanctum token
8. ✅ Token saved securely
9. ✅ Navigate to home screen
10. ✅ Restart app → still authenticated

### Test Sign Out:
1. ✅ Tap sign out button (if implemented)
2. ✅ Returns to login screen
3. ✅ Token cleared
4. ✅ Can sign in again

---

## 🔑 Important URLs

### For Android Emulator:
```dart
apiBaseUrl: 'http://10.0.2.2:8000/api/v1'
wsUrl: 'ws://10.0.2.2:6001'
```

### For Physical Android Device (Replace with your IP):
```dart
apiBaseUrl: 'http://192.168.1.XXX:8000/api/v1'
wsUrl: 'ws://192.168.1.XXX:6001'
```

### Find Your IP:
```bash
# Mac/Linux
ifconfig | grep "inet "
# Look for 192.168.1.xxx

# Windows
ipconfig
# Look for IPv4 Address
```

---

## 🎯 Commands Summary

```bash
# Backend
cd backend && php artisan serve                    # Start API
cd backend && php artisan reverb:start            # Start WebSocket

# Mobile
cd mobile && flutter pub get                       # Install dependencies
cd mobile && flutter devices                       # List devices
cd mobile && flutter run --flavor rider           # Run Rider app
cd mobile && flutter run --flavor driver          # Run Driver app
cd mobile && flutter clean                        # Clean build
cd mobile && flutter doctor                       # Check setup

# Git
git status                                         # Check changes
git add .                                         # Stage all
git commit -m "message"                           # Commit
git push                                          # Push to remote
```

---

## 📚 Documentation Reference

- **Phase 1 Summary**: `docs/PHASE_1_COMPLETION_SUMMARY.md`
- **Firebase Setup**: `docs/FIREBASE_SETUP_GUIDE.md`
- **Testing Guide**: `docs/TESTING_SETUP_GUIDE.md`
- **Flutter Guide**: `docs/FLUTTER_IMPLEMENTATION_GUIDE.md`
- **Backend API**: `docs/API_DOCUMENTATION.md`

---

## ✅ Pre-Test Checklist

Before running `flutter run`:
- [ ] Firebase config files added (4 files)
- [ ] Google Sign-In enabled in Firebase Console
- [ ] Backend running: `php artisan serve`
- [ ] WebSocket running: `php artisan reverb:start`
- [ ] API URL updated in main files
- [ ] Dependencies installed: `flutter pub get`
- [ ] Device/emulator ready

---

## 🚀 You're Ready!

```bash
# The magic command:
cd mobile && flutter run --flavor rider

# Then test sign-in flow! 🎉
```

**Next**: After successful test → Proceed to Phase 2 (Rider App Core Flow)
