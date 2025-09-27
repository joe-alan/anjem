# Development Setup Guide

This guide will help new developers set up their local development environment for the Anjem ride-sharing platform.

## System Requirements

### General Requirements
- **Git**: Version 2.20+
- **Docker**: Version 20.10+ (for database and services)
- **Docker Compose**: Version 2.0+
- **Node.js**: Version 18+ (for tooling)

### Backend Requirements
- **PHP**: Version 8.2+ with extensions: `pdo_pgsql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `curl`
- **Composer**: Latest version
- **PostgreSQL**: 15+ with PostGIS extension
- **Redis**: 6.0+ (via Docker recommended)

### Mobile Requirements
- **Flutter SDK**: Version 3.24+
- **Dart SDK**: Version 3.0+
- **Android Studio**: Latest stable version
- **Xcode**: 14+ (macOS only, for iOS development)

### External Services
- **Firebase Project** with Authentication enabled
- **Google Cloud Console** project for OAuth

## Quick Start

1. **Clone the repository**
   ```bash
   git clone https://github.com/your-org/anjem.git
   cd anjem
   ```

2. **Run the setup script**
   ```bash
   make setup
   ```

3. **Start development servers**
   ```bash
   make dev
   ```

That's it! The setup script will handle most of the configuration automatically.

## Manual Setup

If the quick start doesn't work, follow these detailed instructions:

### 1. Backend Setup (Laravel)

```bash
cd backend

# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Start Docker services (PostgreSQL, Redis)
docker-compose up -d

# Run database migrations
php artisan migrate

# Seed database with test data
php artisan db:seed

# Start Laravel development server
php artisan serve
```

**Backend will be available at:** http://localhost:8000

### 2. Mobile Setup (Flutter)

```bash
cd mobile

# Get Flutter dependencies
flutter pub get

# Generate code (models, routes, etc.)
flutter packages pub run build_runner build

# Check Flutter doctor
flutter doctor

# Run on connected device/emulator
flutter run --flavor rider -t lib/main_rider.dart    # Rider app
flutter run --flavor driver -t lib/main_driver.dart  # Driver app
```

### 3. Environment Configuration

#### Backend (.env)
```env
APP_NAME=Anjem
APP_ENV=local
APP_KEY=base64:your-generated-key
APP_DEBUG=true
APP_URL=http://localhost:8000

# PostgreSQL Database with PostGIS
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=anjemme
DB_USERNAME=postgres
DB_PASSWORD=your_password

REDIS_HOST=localhost
REDIS_PASSWORD=null
REDIS_PORT=6379

# Firebase Configuration
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n"
FIREBASE_CLIENT_EMAIL=your-service-account@your-project.iam.gserviceaccount.com
FIREBASE_CLIENT_ID=your-client-id

# OAuth Configuration
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback

# Google Maps API
GOOGLE_MAPS_API_KEY=your-google-maps-key
```

#### Mobile (lib/core/config/environment.dart)
```dart
class Environment {
  static const String baseUrl = 'http://localhost:8000/api';
  static const String wsUrl = 'ws://localhost:8080';
  static const String googleMapsApiKey = 'your-google-maps-key';
  static const bool debugMode = true;
}
```

### 4. Database Setup

```bash
# Create database with PostGIS extension
createdb anjemme
psql anjemme -c "CREATE EXTENSION postgis;"

# Run migrations
php artisan migrate

# Optional: Seed test data
php artisan db:seed
```

### 5. Firebase Configuration

1. Download `google-services.json` from Firebase Console
2. Place in `mobile/android/app/src/rider/` and `mobile/android/app/src/driver/`
3. Download `GoogleService-Info.plist` from Firebase Console
4. Add to `mobile/ios/Runner/` via Xcode

### 6. Start Development Servers

```bash
# Start Laravel server
php artisan serve

# In another terminal, start WebSocket server
php artisan reverb:start

# Start queue worker (optional)
php artisan queue:work
```

## Development Workflow

### Daily Development
1. **Pull latest changes**
   ```bash
   git checkout main
   git pull origin main
   ```

2. **Start services**
   ```bash
   make dev  # Starts backend, database, and can run mobile
   ```

3. **Create feature branch**
   ```bash
   git checkout -b feat/your-feature-name
   ```

4. **Make changes and test**
   ```bash
   # Backend tests
   cd backend && php artisan test

   # Mobile tests
   cd mobile && flutter test
   ```

5. **Commit and push**
   ```bash
   git add .
   git commit -m "feat(scope): your description"
   git push origin feat/your-feature-name
   ```

### Available Make Commands

```bash
# Setup everything
make setup

# Start development environment
make dev

# Stop all services
make stop

# Clean and reset everything
make clean

# Run all tests
make test

# Run linters
make lint

# Build for production
make build
```

## Database Management

### Migrations
```bash
# Create new migration
php artisan make:migration create_rides_table

# Run migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback

# Reset and migrate
php artisan migrate:fresh --seed
```

### Seeders
```bash
# Create seeder
php artisan make:seeder UsersTableSeeder

# Run specific seeder
php artisan db:seed --class=UsersTableSeeder

# Run all seeders
php artisan db:seed
```

### PostGIS Operations
```bash
# Connect to database
psql anjemme

# Check PostGIS version
SELECT PostGIS_Version();

# View spatial tables
SELECT * FROM geometry_columns;
```

## Testing

### Backend Testing
```bash
cd backend

# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run with coverage
php artisan test --coverage

# Create new test
php artisan make:test UserCanCreateRideTest
```

### Mobile Testing
```bash
cd mobile

# Run all tests
flutter test

# Run tests with coverage
flutter test --coverage

# Run integration tests
flutter drive --target=test_driver/app.dart

# Create new test
flutter create --template=package test/widget_test.dart
```

## Build Configurations

### Mobile Builds

**Development Build:**
```bash
# Android - Rider flavor
flutter build apk --flavor rider --debug

# Android - Driver flavor
flutter build apk --flavor driver --debug

# iOS
flutter build ios --flavor rider --debug
```

**Production Build:**
```bash
flutter build apk --flavor rider --release --obfuscate --split-debug-info=build/debug-info
```

## IDE Setup

### VS Code (Recommended)

Install these extensions:
- **Flutter**: Dart and Flutter support
- **Laravel Extra Intellisense**: PHP/Laravel support
- **PHP Intelephense**: Advanced PHP language support
- **GitLens**: Enhanced Git integration
- **Docker**: Docker support

**Settings (.vscode/settings.json):**
```json
{
  "dart.flutterSdkPath": "/path/to/flutter",
  "php.validate.executablePath": "/path/to/php",
  "editor.formatOnSave": true,
  "dart.previewFlutterUiGuides": true,
  "php.suggest.basic": false
}
```

### Android Studio

1. Install Flutter and Dart plugins
2. Configure Flutter SDK path
3. Set up Android emulators
4. Configure code style (File > Settings > Editor > Code Style)

## Debugging

### Backend Debugging
- Use Laravel Telescope for request monitoring
- Check logs in `backend/storage/logs/laravel.log`
- Use `dd()` or `dump()` for quick debugging
- Configure Xdebug for step debugging

### Mobile Debugging
- Use Flutter Inspector in VS Code/Android Studio
- Add breakpoints and use debugger
- Check device logs: `flutter logs`
- Use `print()` statements for quick debugging

### API Testing
```bash
# Install HTTPie (optional)
brew install httpie

# Test endpoints
http POST localhost:8000/api/auth/login phone=+1234567890
http GET localhost:8000/api/rides Authorization:"Bearer your-token"
```

## Common Issues & Solutions

### Backend Issues

**Composer install fails:**
```bash
# Clear composer cache
composer clear-cache
composer install --no-cache
```

**Database connection error:**
```bash
# Check if PostgreSQL is running
docker-compose ps

# Restart database
docker-compose restart postgres
```

**Permission errors:**
```bash
# Fix storage permissions
chmod -R 775 storage bootstrap/cache
```

**PostGIS extension missing:**
```bash
# Install PostGIS extension
psql anjemme -c "CREATE EXTENSION IF NOT EXISTS postgis;"
```

### Mobile Issues

**Flutter doctor issues:**
```bash
# Accept Android licenses
flutter doctor --android-licenses

# Repair Flutter
flutter doctor -v
flutter clean && flutter pub get
```

**Build failures:**
```bash
# Clean build files
flutter clean
cd ios && rm -rf Pods/ Podfile.lock && cd ..
cd android && ./gradlew clean && cd ..
flutter pub get
```

**Flavor build issues:**
```bash
# Ensure correct build command
flutter run --flavor rider -t lib/main_rider.dart
flutter build apk --flavor rider -t lib/main_rider.dart
```

## Performance Monitoring

### Backend
- Use Laravel Telescope for debugging
- Monitor with Laravel Horizon for queues
- Set up error tracking (Sentry recommended)

### Mobile
- Use Flutter Performance tools
- Monitor with Firebase Performance
- Profile with Flutter Inspector

## Deployment

### Staging Environment
```bash
# Deploy to staging
make deploy-staging
```

### Production Environment
```bash
# Build for production
make build

# Deploy to production (requires permissions)
make deploy-production
```

## Getting Help

1. **Check existing documentation** in `/docs`
2. **Search closed issues** on GitHub
3. **Ask in team chat** (#anjem-dev Slack channel)
4. **Create GitHub issue** for bugs
5. **Schedule pair programming** session for complex issues

## Next Steps

After completing setup:

1. **Read the [Contributing Guide](CONTRIBUTING.md)** for development workflow
2. **Review the [API Documentation](API_DOCUMENTATION.md)** to understand endpoints
3. **Check the [Technical Specification](tech_spec.md)** for architecture overview
4. **Run the test suite** to ensure everything works
5. **Create a simple feature** to familiarize yourself with the codebase

Welcome to the Anjem development team! 🚗✨