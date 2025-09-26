# Development Setup Guide

## Prerequisites

### Backend Dependencies
- **PHP 8.2+** with extensions: `pdo_pgsql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `curl`
- **PostgreSQL 14+**
- **Composer 2.x**
- **Redis** (for caching and queues)

### Mobile Dependencies
- **Flutter SDK 3.9.2+**
- **Android SDK** (API 31+)
- **Xcode 15+** (for iOS development)

### External Services
- **Firebase Project** with Authentication enabled
- **Google Cloud Console** project for OAuth

## Backend Setup

### 1. Install Dependencies

```bash
cd backend
composer install
```

### 2. Environment Configuration

Copy and configure environment variables:

```bash
cp .env.example .env
```

Update the following variables in `.env`:

```env
# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=anjem_db
DB_USERNAME=postgres
DB_PASSWORD=your_password

# Firebase Configuration
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n"
FIREBASE_CLIENT_EMAIL=your-service-account@your-project.iam.gserviceaccount.com
FIREBASE_CLIENT_ID=your-client-id

# OAuth Configuration
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
```

### 3. Database Setup

```bash
# Create database
createdb anjem_db

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate

# Optional: Seed test data
php artisan db:seed
```

### 4. Start Development Server

```bash
# Start Laravel server
php artisan serve

# In another terminal, start WebSocket server
php artisan reverb:start
```

## Mobile Setup

### 1. Install Dependencies

```bash
cd mobile
flutter pub get
```

### 2. Firebase Configuration

1. Download `google-services.json` from Firebase Console
2. Place in `mobile/android/app/`
3. Download `GoogleService-Info.plist` from Firebase Console
4. Add to `mobile/ios/Runner/` via Xcode

### 3. Build Configurations

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

## Firebase Setup

### 1. Create Firebase Project

1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Create new project: `anjem-campus-rideshare`
3. Enable Authentication with Email/Password and Google providers
4. Generate service account key for backend

### 2. Authentication Configuration

**Enable Providers:**
- Email/Password
- Google OAuth

**Add Authorized Domains:**
- `localhost` (development)
- `your-app-domain.com` (production)

### 3. Service Account Setup

1. Go to Project Settings → Service Accounts
2. Generate new private key (JSON)
3. Extract required fields for `.env` configuration

## Google OAuth Setup

### 1. Google Cloud Console

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create/select project
3. Enable Google+ API
4. Create OAuth 2.0 Client IDs:
   - **Web Client**: For backend callback
   - **Android Client**: For mobile app
   - **iOS Client**: For mobile app

### 2. Configure Credentials

**Web Client (Backend):**
- Authorized redirect URIs: `http://localhost:8000/auth/google/callback`

**Mobile Clients:**
- Use package name: `com.anjem.rider` / `com.anjem.driver`
- Add SHA-1 fingerprints for release builds

## Development Workflow

### 1. Database Changes

```bash
# Create migration
php artisan make:migration create_table_name

# Run migrations
php artisan migrate

# Rollback (if needed)
php artisan migrate:rollback
```

### 2. Testing

```bash
# Backend tests
cd backend
php artisan test

# Mobile tests
cd mobile
flutter test

# Integration tests
flutter drive --target=test_driver/app.dart
```

### 3. Code Quality

```bash
# Backend
composer install --dev
./vendor/bin/phpstan analyse
./vendor/bin/php-cs-fixer fix

# Mobile
flutter analyze
flutter format lib/
```

## Environment-Specific Notes

### Local Development
- Use `localhost` URLs
- PostgreSQL on default port 5432
- Redis on default port 6379

### Production
- Use environment-specific Firebase project
- Configure proper CORS settings
- Enable SSL/TLS
- Use production database credentials

## Troubleshooting

### Common Issues

**Firebase Token Verification Fails:**
- Check service account credentials
- Verify Firebase project ID
- Ensure private key format is correct

**Database Connection Issues:**
- Verify PostgreSQL is running
- Check database credentials
- Ensure `pdo_pgsql` extension is installed

**Mobile Build Failures:**
- Clear Flutter cache: `flutter clean`
- Re-fetch dependencies: `flutter pub get`
- Check Android SDK/Xcode versions

### Logs

**Backend Logs:**
```bash
tail -f storage/logs/laravel.log
```

**Mobile Logs:**
```bash
flutter logs
```

## Performance Monitoring

### Backend
- Laravel Telescope (development)
- Application logs
- Database query logging

### Mobile
- Firebase Performance Monitoring
- Crashlytics for crash reporting
- Custom analytics events

---

For additional help, check the troubleshooting section in the main documentation or contact the development team.