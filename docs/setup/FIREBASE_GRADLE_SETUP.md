# Firebase Gradle Setup for Product Flavors

## Quick Answer

**Yes, you only need to add the Google Services plugin in ONE place** (the app-level `build.gradle.kts`), but you need **TWO separate `google-services.json` files** (one per flavor).

---

## Complete Setup Guide

### Step 1: Add Firebase Dependencies

#### 1.1 Update `android/settings.gradle.kts`

Add the Google Services classpath to the plugins section:

```kotlin
plugins {
    id("dev.flutter.flutter-plugin-loader") version "1.0.0"
    id("com.android.application") version "8.9.1" apply false
    id("org.jetbrains.kotlin.android") version "2.1.0" apply false
    id("com.google.gms.google-services") version "4.4.2" apply false  // ← ADD THIS
}
```

#### 1.2 Update `android/app/build.gradle.kts`

Add the Google Services plugin at the **bottom** of the file:

```kotlin
plugins {
    id("com.android.application")
    id("kotlin-android")
    id("dev.flutter.flutter-gradle-plugin")
}

android {
    namespace = "me.anjem.mobile"
    compileSdk = flutter.compileSdkVersion
    // ... rest of your config ...

    flavorDimensions += "app"

    productFlavors {
        create("rider") {
            dimension = "app"
            applicationId = "me.anjem.rider"  // This is your actual package name
            versionName = "1.0.0-rider"
            resValue("string", "app_name", "Anjem Rider")
            manifestPlaceholders["appLabel"] = "Anjem Rider"
        }

        create("driver") {
            dimension = "app"
            applicationId = "me.anjem.driver"  // This is your actual package name
            versionName = "1.0.0-driver"
            resValue("string", "app_name", "Anjem Driver")
            manifestPlaceholders["appLabel"] = "Anjem Driver"
        }
    }

    buildTypes {
        release {
            signingConfig = signingConfigs.getByName("debug")
        }
    }
}

flutter {
    source = "../.."
}

// ← ADD THIS AT THE BOTTOM (IMPORTANT: Must be last line)
apply(plugin = "com.google.gms.google-services")
```

**Important**: The `apply(plugin = "com.google.gms.google-services")` line MUST be at the very bottom, after the `flutter {}` block.

---

### Step 2: Firebase Console Setup

#### Create Firebase Apps with CORRECT Package Names

⚠️ **IMPORTANT**: Use the actual package names from your `build.gradle.kts`:

1. Go to https://console.firebase.google.com/
2. Select/create "Anjem" project
3. Add first Android app:
   - Package name: **`me.anjem.rider`** (NOT `com.anjem.rider`)
   - App nickname: `Anjem Rider`
   - Click "Register app"
   - Download `google-services.json`
4. Add second Android app:
   - Package name: **`me.anjem.driver`** (NOT `com.anjem.driver`)
   - App nickname: `Anjem Driver`
   - Click "Register app"
   - Download `google-services.json`

---

### Step 3: Place google-services.json Files

The Google Services plugin automatically picks the correct file based on flavor:

```
mobile/android/app/src/
├── rider/
│   └── google-services.json     ← Rider config (me.anjem.rider)
├── driver/
│   └── google-services.json     ← Driver config (me.anjem.driver)
└── main/
    └── AndroidManifest.xml
```

**How it works:**
- When you run `flutter run --flavor rider`, Gradle uses `src/rider/google-services.json`
- When you run `flutter run --flavor driver`, Gradle uses `src/driver/google-services.json`
- You don't need to do anything special - it's automatic!

---

### Step 4: Verify Configuration

#### 4.1 Check google-services.json Content

Open each `google-services.json` and verify the package name matches:

**Rider file** (`android/app/src/rider/google-services.json`):
```json
{
  "project_info": { ... },
  "client": [
    {
      "client_info": {
        "android_client_info": {
          "package_name": "me.anjem.rider"  // ← Should match
        }
      }
    }
  ]
}
```

**Driver file** (`android/app/src/driver/google-services.json`):
```json
{
  "project_info": { ... },
  "client": [
    {
      "client_info": {
        "android_client_info": {
          "package_name": "me.anjem.driver"  // ← Should match
        }
      }
    }
  ]
}
```

#### 4.2 Test the Build

```bash
cd mobile

# Clean build
flutter clean

# Build Rider flavor
flutter build apk --flavor rider --debug

# Build Driver flavor
flutter build apk --flavor driver --debug
```

If successful, you'll see:
```
✓ Built build/app/outputs/flutter-apk/app-rider-debug.apk
✓ Built build/app/outputs/flutter-apk/app-driver-debug.apk
```

---

## Common Issues & Solutions

### Issue 1: "google-services.json not found"

**Error:**
```
Execution failed for task ':app:processRiderDebugGoogleServices'.
> File google-services.json is missing.
```

**Solution:**
```bash
# Check files exist in correct locations
ls -la android/app/src/rider/google-services.json
ls -la android/app/src/driver/google-services.json

# If missing, download from Firebase Console
```

### Issue 2: "Package name doesn't match"

**Error:**
```
No matching client found for package name 'me.anjem.rider'
```

**Solution:**
1. Open Firebase Console
2. Check the package names in your Firebase apps
3. They must be **`me.anjem.rider`** and **`me.anjem.driver`**
4. If wrong, delete the Firebase apps and recreate with correct names
5. Re-download `google-services.json` files

### Issue 3: "Plugin not applied"

**Error:**
```
Could not find method google-services() for arguments...
```

**Solution:**
1. Make sure `settings.gradle.kts` has the Google Services plugin
2. Make sure `app/build.gradle.kts` applies the plugin at the BOTTOM
3. Run `flutter clean && flutter pub get`

### Issue 4: "SHA-1 certificate not configured"

**Warning (non-critical for dev):**
```
SHA-1 certificate fingerprints are not configured
```

**Solution (for development):**
```bash
# Get debug SHA-1
cd android
./gradlew signingReport

# Look for "SHA1:" under "Variant: riderDebug"
# Copy SHA-1 hash
# Add to Firebase Console → Project Settings → Your apps → Add fingerprint
```

---

## How the Google Services Plugin Works with Flavors

### Automatic Flavor Detection

The Google Services Gradle plugin is smart enough to:

1. **Detect the current flavor** being built (`rider` or `driver`)
2. **Look for the config file** in the flavor-specific directory:
   - For `rider`: `src/rider/google-services.json`
   - For `driver`: `src/driver/google-services.json`
3. **Parse the file** and generate Android resources
4. **Inject Firebase config** into your app at compile time

### What You DON'T Need to Do

❌ Create multiple `build.gradle` files per flavor
❌ Conditionally apply the plugin based on flavor
❌ Manually specify which JSON file to use
❌ Add flavor-specific plugin configurations

### What You DO Need to Do

✅ Add plugin to `settings.gradle.kts` once
✅ Apply plugin at bottom of `app/build.gradle.kts` once
✅ Place correct `google-services.json` in each flavor directory
✅ Ensure package names match between Firebase and Gradle

---

## Complete File Structure

```
mobile/
└── android/
    ├── settings.gradle.kts                    ← Add plugin here
    ├── build.gradle.kts                       ← No changes needed
    └── app/
        ├── build.gradle.kts                   ← Apply plugin here (bottom)
        └── src/
            ├── rider/
            │   ├── google-services.json       ← Rider Firebase config
            │   └── res/                       ← Rider resources (optional)
            ├── driver/
            │   ├── google-services.json       ← Driver Firebase config
            │   └── res/                       ← Driver resources (optional)
            └── main/
                ├── AndroidManifest.xml        ← Shared manifest
                └── kotlin/                    ← Shared code
```

---

## Final Checklist

Before running the app:

- [ ] `settings.gradle.kts` has Google Services plugin (version 4.4.2)
- [ ] `app/build.gradle.kts` applies plugin at the bottom
- [ ] Firebase Console has 2 apps:
  - [ ] `me.anjem.rider`
  - [ ] `me.anjem.driver`
- [ ] Both `google-services.json` files downloaded
- [ ] Files placed in correct directories:
  - [ ] `android/app/src/rider/google-services.json`
  - [ ] `android/app/src/driver/google-services.json`
- [ ] Package names in JSON match Gradle config
- [ ] Google Sign-In enabled in Firebase Console
- [ ] Clean build successful: `flutter clean && flutter pub get`

---

## Testing

```bash
# Test Rider flavor
flutter run --flavor rider
# Should build successfully and connect to Firebase

# Test Driver flavor
flutter run --flavor driver
# Should build successfully and connect to Firebase
```

**Expected output:**
```
✓ Built build/app/outputs/flutter-apk/app-rider-debug.apk
Launching lib/main_rider.dart on sdk gphone64 arm64 in debug mode...
Running Gradle task 'assembleRiderDebug'...
✓ Built successfully!
```

---

## Summary

**Short Answer**: Yes, add the Google Services plugin in just **ONE** place (the app-level `build.gradle.kts`). The plugin will automatically detect flavors and use the correct `google-services.json` file from each flavor directory.

**Key Points**:
1. Plugin goes in `settings.gradle.kts` (classpath)
2. Plugin applied in `app/build.gradle.kts` (at bottom)
3. Two separate `google-services.json` files (one per flavor)
4. Package names MUST be `me.anjem.rider` and `me.anjem.driver`
5. No flavor-specific plugin configuration needed

**It just works!** 🎉
