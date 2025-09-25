# Anjem Mobile Apps

Flutter-based mobile applications for the Anjem ride-sharing platform, supporting both rider and driver experiences through product flavors.

## Architecture Overview

This Flutter project uses **product flavors** to generate two separate apps from a single codebase:

- **Rider App**: For students requesting rides
- **Driver App**: For students providing rides

## Prerequisites

- **Flutter SDK**: 3.10+ ([Installation Guide](https://docs.flutter.dev/get-started/install))
- **Dart SDK**: 3.0+ (included with Flutter)
- **Android Studio**: Latest stable version
- **Xcode**: 14+ (macOS only, for iOS development)
- **VS Code**: (Optional, but recommended with Flutter extensions)

## Quick Start

1. **Install dependencies**
   ```bash
   cd mobile
   flutter pub get
   ```

2. **Generate code**
   ```bash
   flutter packages pub run build_runner build
   ```

3. **Run the apps**
   ```bash
   # Rider app
   flutter run --flavor rider -t lib/main_rider.dart

   # Driver app
   flutter run --flavor driver -t lib/main_driver.dart
   ```

## Project Structure

```
mobile/
├── lib/
│   ├── core/                   # Shared code between apps
│   │   ├── config/            # Configuration and constants
│   │   ├── models/            # Data models
│   │   ├── services/          # API services and business logic
│   │   ├── utils/             # Utility functions
│   │   └── widgets/           # Reusable UI components
│   ├── rider/                 # Rider-specific code
│   │   ├── screens/           # Rider screens
│   │   ├── widgets/           # Rider-specific widgets
│   │   └── services/          # Rider-specific services
│   ├── driver/                # Driver-specific code
│   │   ├── screens/           # Driver screens
│   │   ├── widgets/           # Driver-specific widgets
│   │   └── services/          # Driver-specific services
│   ├── main_rider.dart        # Rider app entry point
│   └── main_driver.dart       # Driver app entry point
├── android/
│   └── app/src/
│       ├── rider/             # Rider flavor configuration
│       ├── driver/            # Driver flavor configuration
│       └── main/              # Shared Android configuration
├── ios/
│   ├── Runner/                # Shared iOS configuration
│   ├── RiderApp/              # Rider iOS configuration
│   └── DriverApp/             # Driver iOS configuration
├── test/                      # Unit and widget tests
├── integration_test/          # Integration tests
└── assets/                    # Images, fonts, and other assets
```

## Flavor Configuration

### Android Flavors

Located in `android/app/build.gradle`:

```gradle
flavorDimensions "app"
productFlavors {
    rider {
        dimension "app"
        applicationId "com.anjem.rider"
        versionNameSuffix "-rider"
        resValue "string", "app_name", "Anjem Rider"
    }
    driver {
        dimension "app"
        applicationId "com.anjem.driver"
        versionNameSuffix "-driver"
        resValue "string", "app_name", "Anjem Driver"
    }
}
```

### iOS Flavors

iOS schemes are configured in Xcode with separate targets:
- **RiderApp**: Bundle ID `com.anjem.rider`
- **DriverApp**: Bundle ID `com.anjem.driver`

## Development Commands

### Running the Apps

```bash
# Development - Debug mode
flutter run --flavor rider -t lib/main_rider.dart
flutter run --flavor driver -t lib/main_driver.dart

# With hot reload (recommended for development)
flutter run --flavor rider -t lib/main_rider.dart --hot

# On specific device
flutter run --flavor rider -t lib/main_rider.dart -d "device-id"

# List available devices
flutter devices
```

### Building the Apps

```bash
# Debug builds
flutter build apk --flavor rider -t lib/main_rider.dart --debug
flutter build apk --flavor driver -t lib/main_driver.dart --debug

# Release builds
flutter build apk --flavor rider -t lib/main_rider.dart --release
flutter build apk --flavor driver -t lib/main_driver.dart --release

# iOS builds (macOS only)
flutter build ios --flavor rider -t lib/main_rider.dart --release
flutter build ios --flavor driver -t lib/main_driver.dart --release

# App bundles for Play Store
flutter build appbundle --flavor rider -t lib/main_rider.dart --release
flutter build appbundle --flavor driver -t lib/main_driver.dart --release
```

## Testing

### Unit Tests

```bash
# Run all unit tests
flutter test

# Run tests with coverage
flutter test --coverage
genhtml coverage/lcov.info -o coverage/html

# Run specific test file
flutter test test/services/api_service_test.dart

# Run tests matching pattern
flutter test --plain-name="AuthService"
```

### Widget Tests

```bash
# Run widget tests
flutter test test/widgets/

# Test specific widget
flutter test test/widgets/ride_card_test.dart
```

### Integration Tests

```bash
# Run integration tests on connected device
flutter test integration_test/

# Run specific integration test
flutter test integration_test/rider_flow_test.dart

# Run on specific device
flutter test integration_test/ -d "device-id"
```

## Code Generation

This project uses code generation for models, routes, and other boilerplate:

```bash
# Generate code once
flutter packages pub run build_runner build

# Watch for changes and regenerate
flutter packages pub run build_runner watch

# Clean and regenerate
flutter packages pub run build_runner build --delete-conflicting-outputs
```

## State Management

The project uses **Provider** pattern for state management:

```dart
// Example provider
class RideProvider with ChangeNotifier {
  Ride? _currentRide;

  Ride? get currentRide => _currentRide;

  void updateRide(Ride ride) {
    _currentRide = ride;
    notifyListeners();
  }
}

// Usage in widget
Consumer<RideProvider>(
  builder: (context, rideProvider, child) {
    return Text(rideProvider.currentRide?.status ?? 'No ride');
  },
)
```

## Configuration

### Environment Configuration

Create environment-specific configuration files:

```dart
// lib/core/config/environment.dart
class Environment {
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://localhost:8000/api',
  );

  static const String googleMapsApiKey = String.fromEnvironment(
    'GOOGLE_MAPS_API_KEY',
    defaultValue: 'your-api-key',
  );
}
```

Run with environment variables:
```bash
flutter run --flavor rider -t lib/main_rider.dart \
  --dart-define=API_BASE_URL=https://api.anjem.app \
  --dart-define=GOOGLE_MAPS_API_KEY=your-actual-key
```

### Assets Configuration

Add assets in `pubspec.yaml`:

```yaml
flutter:
  assets:
    - assets/images/
    - assets/icons/rider/
    - assets/icons/driver/

  fonts:
    - family: Inter
      fonts:
        - asset: assets/fonts/Inter-Regular.ttf
        - asset: assets/fonts/Inter-Bold.ttf
          weight: 700
```

## Key Dependencies

### Core Dependencies

```yaml
dependencies:
  flutter:
    sdk: flutter

  # State Management
  provider: ^6.0.5

  # HTTP Client
  dio: ^5.3.2
  pretty_dio_logger: ^1.3.1

  # Local Storage
  shared_preferences: ^2.2.0
  hive: ^2.2.3
  hive_flutter: ^1.1.0

  # Navigation
  go_router: ^10.1.2

  # UI Components
  flutter_svg: ^2.0.7
  cached_network_image: ^3.3.0

  # Maps
  google_maps_flutter: ^2.5.0
  geolocator: ^9.0.2

  # Utilities
  intl: ^0.18.1
  uuid: ^3.0.7
```

### Development Dependencies

```yaml
dev_dependencies:
  flutter_test:
    sdk: flutter

  # Code Generation
  build_runner: ^2.4.6
  json_annotation: ^4.8.1
  json_serializable: ^6.7.1

  # Linting
  flutter_lints: ^2.0.3

  # Testing
  mockito: ^5.4.2
  integration_test:
    sdk: flutter
```

## Debugging

### Flutter Inspector

Use Flutter Inspector in VS Code or Android Studio to:
- Inspect widget tree
- Debug layout issues
- Monitor widget rebuilds
- Analyze performance

### Logging

```dart
// Use structured logging
import 'dart:developer' as developer;

void logInfo(String message, {String? tag}) {
  developer.log(
    message,
    name: tag ?? 'Anjem',
    level: 800, // Info level
  );
}

void logError(String message, {Object? error, StackTrace? stackTrace}) {
  developer.log(
    message,
    name: 'Anjem',
    level: 1000, // Error level
    error: error,
    stackTrace: stackTrace,
  );
}
```

### Performance Monitoring

```bash
# Profile app performance
flutter run --flavor rider -t lib/main_rider.dart --profile

# Analyze widget rebuilds
flutter run --flavor rider -t lib/main_rider.dart --trace-skia

# Memory profiling
flutter run --flavor rider -t lib/main_rider.dart --profile --enable-software-rendering
```

## Deployment

### Android Deployment

1. **Configure signing** in `android/app/build.gradle`
2. **Build release APK/Bundle**
   ```bash
   flutter build appbundle --flavor rider -t lib/main_rider.dart --release
   flutter build appbundle --flavor driver -t lib/main_driver.dart --release
   ```
3. **Upload to Google Play Console**

### iOS Deployment

1. **Open in Xcode**
   ```bash
   open ios/Runner.xcworkspace
   ```
2. **Configure certificates and provisioning profiles**
3. **Build and archive**
4. **Upload to App Store Connect**

### CI/CD with GitHub Actions

```yaml
# .github/workflows/mobile.yml
name: Mobile CI/CD

on:
  push:
    branches: [main, develop]
    paths: ['mobile/**']

jobs:
  test:
    runs-on: ubuntu-latest
    defaults:
      run:
        working-directory: mobile

    steps:
      - uses: actions/checkout@v3
      - uses: subosito/flutter-action@v2
        with:
          flutter-version: '3.10.0'

      - run: flutter pub get
      - run: flutter analyze
      - run: flutter test --coverage
      - run: flutter build apk --flavor rider -t lib/main_rider.dart
```

## Troubleshooting

### Common Issues

**Build failures:**
```bash
# Clean build files
flutter clean
cd ios && rm -rf Pods/ Podfile.lock && cd ..
cd android && ./gradlew clean && cd ..
flutter pub get
```

**Flavor not found:**
```bash
# Ensure you're using the correct flavor and target
flutter run --flavor rider -t lib/main_rider.dart
```

**Code generation issues:**
```bash
# Force regenerate
flutter packages pub run build_runner build --delete-conflicting-outputs
```

**iOS build issues:**
```bash
# Update CocoaPods
cd ios && pod repo update && pod install && cd ..
```

### Performance Tips

1. **Use `const` constructors** where possible
2. **Implement `ListView.builder`** for large lists
3. **Cache network images** with `cached_network_image`
4. **Use `RepaintBoundary`** for complex widgets
5. **Profile regularly** with Flutter DevTools

## Contributing

1. Follow the [Contributing Guide](../docs/CONTRIBUTING.md)
2. Run tests before committing: `flutter test`
3. Use consistent code formatting: `dart format .`
4. Analyze code quality: `flutter analyze`

## Resources

- [Flutter Documentation](https://docs.flutter.dev/)
- [Dart Language Tour](https://dart.dev/guides/language/language-tour)
- [Flutter Cookbook](https://docs.flutter.dev/cookbook)
- [Material Design Guidelines](https://material.io/design)
- [Human Interface Guidelines](https://developer.apple.com/design/human-interface-guidelines/)

## Support

- **Documentation**: Check `/docs` folder
- **Issues**: Create GitHub issues for bugs
- **Team Chat**: #anjem-mobile Slack channel
- **Code Reviews**: Tag @mobile-team in PRs
