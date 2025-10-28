# Anjem - Campus Ride-sharing Platform

**A modern ride-sharing platform designed for campus environments**

Built with Flutter mobile apps and Laravel backend. Features dual app flavors for riders and drivers, real-time matching with WebSockets, and Mapbox route visualization.

[![Flutter](https://img.shields.io/badge/Flutter-3.24+-blue.svg)](https://flutter.dev/)
[![Laravel](https://img.shields.io/badge/Laravel-11.x-red.svg)](https://laravel.com/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

**Current Status**: MVP Phase 8 Complete (83% Backend, 40% Mobile) | Last Updated: October 28, 2025

---

## 🚀 Quick Start

```bash
# Clone the repository
git clone <repository-url>
cd anjem

# Backend setup
cd backend && composer install
php artisan migrate --seed
php artisan reverb:start

# Mobile setup (rider app)
cd mobile
flutter pub get
flutter run --flavor rider -t lib/main_rider.dart
```

📖 **Detailed Setup**: See [Development Setup Guide](docs/setup/DEVELOPMENT.md)

---

## 🎯 Key Features

### For Riders ✅
- 🗺️ **Visual Route Preview** - See your route before confirming (Phase 8)
- 📍 **Beacon & P2P Locations** - Fixed pickup points or anywhere on campus
- 💰 **Real-time Fare Estimates** - Based on actual distance via Mapbox
- 🔔 **Push Notifications** - Ride status updates via Firebase
- ⭐ **Driver Ratings** - Rate your driver after each ride

### For Drivers 🚧
- ✅ **Email-based KYC Verification** - Automated approval system (Phase 1.5)
- 🚦 Queue-based Ride Acceptance (Pending)
- 📊 Performance Scoring System (Pending)
- 💵 Earnings Dashboard (Pending)

### Platform Features
- 🔥 **Firebase Authentication** - Secure Google Sign-In
- 🔐 **Laravel Sanctum** - Token-based API authorization
- ⚡ **Real-time Updates** - Laravel Reverb WebSocket broadcasting
- 🗄️ **Spatial Queries** - PostgreSQL + PostGIS for location-based matching
- 🗺️ **Mapbox Integration** - Route visualization & directions API

---

## 🏗️ Technology Stack

### Mobile (Flutter 3.24+)
- **UI Framework**: Flutter with Material Design 3
- **State Management**: Riverpod 2.x
- **Authentication**: Firebase Auth + Google Sign-In
- **Networking**: Dio (REST) + Laravel Echo (WebSocket)
- **Maps**: Mapbox Maps Flutter SDK
- **Notifications**: Firebase Cloud Messaging

### Backend (Laravel 11.x)
- **Framework**: Laravel with PHP 8.2
- **Database**: PostgreSQL 16 + PostGIS 3.6 (spatial data)
- **Cache**: Redis 7 (queue management, sessions)
- **WebSocket**: Laravel Reverb (real-time updates)
- **Authentication**: Firebase Admin SDK + Laravel Sanctum
- **Maps**: Mapbox Directions API (100k free requests/month)

### Infrastructure
- **Development**: Local with Docker (optional)
- **Deployment**: DigitalOcean (planned)
- **CI/CD**: GitHub Actions (planned)
- **Monitoring**: Firebase Crashlytics

---

## 📱 Mobile Apps (Product Flavors)

Single Flutter codebase, two production apps:

### Rider App (`me.anjem.rider`)
- Request rides from beacons or anywhere
- View route before confirming
- Track driver in real-time (pending)
- Rate and review drivers

### Driver App (`me.anjem.driver`)
- Email-based KYC verification ✅
- Go online at specific beacons (pending)
- Accept rides from queue (pending)
- Navigate to pickup/dropoff (pending)

### Build Commands
```bash
# Rider app
flutter run --flavor rider -t lib/main_rider.dart
flutter build apk --flavor rider -t lib/main_rider.dart

# Driver app
flutter run --flavor driver -t lib/main_driver.dart
flutter build apk --flavor driver -t lib/main_driver.dart
```

---

## 📊 Current MVP Progress

### Backend (83% Complete)
- ✅ **Phase 1**: PostgreSQL + PostGIS database models
- ✅ **Phase 2**: Firebase + Sanctum authentication
- ✅ **Phase 3**: Core services (Location, Ride, Queue, Notification)
- ✅ **Phase 4**: REST API endpoints with security validation
- ✅ **Phase 5**: WebSocket real-time features (Laravel Reverb)
- ✅ **Phase 0**: Mapbox integration & place search
- 🔲 **Phase 6**: Testing & performance optimization

### Mobile (40% Complete)
- ✅ **Phase 1**: Authentication (Firebase + Sanctum)
- ✅ **Phase 1.5**: Driver KYC verification
- ✅ **Phase 8**: Mapbox route visualization ← **JUST COMPLETED**
- 🚧 **Phase 2**: Rider app core flow (In Progress)
- 🔲 **Phase 3**: Driver app core flow
- 🔲 **Phase 4**: Maps & navigation
- 🔲 **Phase 5**: Real-time WebSocket integration
- 🔲 **Phase 6**: UI/UX polish
- 🔲 **Phase 7**: Testing & deployment

---

## 📖 Documentation

**Start Here:**
- 📘 [Documentation Hub](docs/README.md) - Master index for all docs
- 🚀 [Quick Start Cheatsheet](docs/QUICK_START_CHEATSHEET.md) - Get running in 5 minutes

**Setup Guides:**
- [Development Setup](docs/setup/DEVELOPMENT.md) - Complete environment setup
- [Firebase Setup](docs/setup/FIREBASE_SETUP_GUIDE.md) - Authentication & FCM
- [Infrastructure Setup](docs/setup/infrastructure.md) - Production deployment

**API & Architecture:**
- [API Documentation](docs/api/API_DOCUMENTATION.md) - Complete REST API reference
- [Technical Specification](docs/architecture/tech_spec.md) - System design
- [Flutter Implementation Guide](docs/guides/FLUTTER_IMPLEMENTATION_GUIDE.md) - Mobile development roadmap

**Testing:**
- [Testing Documentation](docs/testing/TESTING_DOCUMENTATION.md) - Test strategies
- [Testing Setup Guide](docs/testing/TESTING_SETUP_GUIDE.md) - Test environment setup

**Phase Reports:**
- [Phase 1: Auth & Setup](docs/phases/PHASE_1_COMPLETION_SUMMARY.md)
- [Phase 4: API Implementation](docs/phases/PHASE_4_COMPLETION_REPORT.md)
- [Phase 5: Real-time Features](docs/phases/PHASE_5_COMPLETION_REPORT.md)
- [Phase 8: Mapbox Integration](docs/phases/PHASE_8_COMPLETION_REPORT.md) ← **NEW**

---

## 📋 Project Structure

```
anjem/
├── backend/              # Laravel API (PHP 8.2)
│   ├── app/
│   │   ├── Models/      # Eloquent models with PostGIS
│   │   ├── Services/    # Business logic (Ride, Location, Queue, Notification)
│   │   ├── Http/
│   │   │   ├── Controllers/  # API controllers
│   │   │   └── Resources/    # JSON response transformers
│   │   └── Events/      # WebSocket broadcast events
│   ├── database/        # Migrations & seeders
│   ├── routes/          # API routes
│   └── tests/           # PHPUnit tests (183/220 passing)
│
├── mobile/              # Flutter apps (Dart 3.x)
│   ├── lib/
│   │   ├── core/       # Shared code
│   │   │   ├── models/      # Data models (User, Ride, Location, etc.)
│   │   │   ├── providers/   # Riverpod state management
│   │   │   ├── services/    # API, Auth, Location, WebSocket
│   │   │   └── widgets/     # Reusable UI components
│   │   ├── rider/      # Rider-specific screens
│   │   └── driver/     # Driver-specific screens
│   └── android/app/src/
│       ├── rider/      # Rider flavor config & Firebase
│       └── driver/     # Driver flavor config & Firebase
│
└── docs/               # Documentation (reorganized!)
    ├── README.md       # Documentation hub
    ├── setup/          # Setup guides
    ├── api/            # API documentation
    ├── guides/         # Implementation guides
    ├── phases/         # Phase completion reports
    ├── testing/        # Testing docs
    ├── architecture/   # Technical specs
    └── archive/        # Old/deprecated docs
```

---

## 🔐 Security

- ✅ Firebase Authentication with Google Sign-In
- ✅ Laravel Sanctum token-based API authorization
- ✅ Token abilities for role-based permissions (rider:*, driver:*)
- ✅ SQL injection prevention via Eloquent ORM
- ✅ XSS protection via Laravel's automatic escaping
- ✅ Rate limiting on all API endpoints (100 req/min)
- ✅ Strict Firebase token validation
- 🔲 Dart code obfuscation (production builds)
- 🔲 SSL/TLS encryption (production deployment)

---

## 📊 Performance Metrics

### Current Benchmarks (Phase 8)
- **Route Fetch**: < 2 seconds (avg 800ms)
- **Map Render**: < 1 second
- **API Response**: p95 < 500ms
- **Ride Request Creation**: < 500ms

### Target Metrics (Production)
- **API Response**: p95 ≤ 300ms
- **Cold Start**: ≤ 2.5s (mid-range Android)
- **APK Size**: ≤ 30MB
- **Peak Load**: 200 RPS
- **Crash-free Rate**: ≥98.5%

---

## 🐛 Known Issues & Limitations

### MVP Constraints
- 🔲 **Android Only** - iOS support deferred to post-MVP
- 🔲 **Campus Area Only** - Optimized for UI Depok campus
- 🔲 **No Payment Processing** - Cash/offline payments only
- 🔲 **Manual Driver Approval** - Admin approval required post-KYC

### Resolved (Phase 8)
- ✅ Null ID type cast error in ride requests
- ✅ Route model binding with Laravel
- ✅ Map camera not fitting both points properly
- ✅ API response field mismatches

---

## 🤝 Contributing

1. **Read**: [Contributing Guidelines](docs/guides/CONTRIBUTING.md)
2. **Setup**: [Development Environment](docs/setup/DEVELOPMENT.md)
3. **Code**:
   - Branch naming: `feat/description` or `fix/description`
   - Commit format: `type(scope): message`
   - Run tests before committing
4. **Submit PR** with clear description

---

## 📝 License

This project is licensed under the MIT License. See [LICENSE](LICENSE) for details.

---

## 🆘 Support

- **Documentation**: [docs/](docs/README.md)
- **Issues**: GitHub Issues
- **Discussions**: GitHub Discussions

---

## 🎉 Recent Updates (Phase 8 - October 28, 2025)

### ✨ New Features
- 🗺️ **Mapbox Route Visualization** - See your route before confirming
- 📍 **Interactive Map Widget** - Full-screen map with route polyline
- 💰 **Real-time Fare Calculation** - Based on actual Mapbox distance
- 🎨 **Redesigned Ride Details** - Map background with bottom sheet overlay

### 🐛 Bug Fixes
- Fixed null ID type cast errors
- Fixed ride cancellation route binding
- Improved map camera fitting algorithm
- Resolved API response field mismatches

### 📚 Documentation
- Reorganized docs folder with logical structure
- Added Phase 8 completion report
- Created documentation hub (docs/README.md)
- Updated all setup guides

---

**Built with ❤️ for Campus Communities**
