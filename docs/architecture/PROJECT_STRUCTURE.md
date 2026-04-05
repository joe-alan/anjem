# Anjem Project Directory Structure

Quick reference guide for navigating the Anjem ride-sharing platform codebase.

## Root Level

```
anjem/
├── backend/              # Laravel API backend
├── mobile/               # Flutter mobile apps (rider + driver)
├── docs/                 # Documentation
├── CLAUDE.md            # AI assistant project instructions
├── CONTINUE_HERE.md     # Development progress tracker
└── PROJECT_STRUCTURE.md # This file
```

## Backend (`backend/`)

Laravel API with PostgreSQL + PostGIS for spatial data.

```
backend/
├── app/
│   ├── Events/                    # Broadcasting events
│   │   ├── RideStatusUpdated.php
│   │   ├── DriverLocationUpdated.php
│   │   └── RideRequestCreated.php
│   │
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── AuthController.php          # Firebase auth + Sanctum
│   │   │   ├── RideController.php          # Ride lifecycle
│   │   │   ├── RequestController.php       # Ride requests
│   │   │   ├── DriverController.php        # Driver operations
│   │   │   ├── PlaceController.php         # Location search
│   │   │   └── AdminController.php         # Admin endpoints
│   │   │
│   │   ├── Requests/                        # Form validation
│   │   └── Resources/                       # API response formatting
│   │
│   ├── Models/
│   │   ├── User.php                         # Unified rider/driver model
│   │   ├── DriverProfile.php               # Driver-specific data + KYC
│   │   ├── Location.php                    # Campus locations (PostGIS)
│   │   ├── RideRequest.php                 # Ride booking requests
│   │   ├── Ride.php                        # Active/completed rides
│   │   ├── Rating.php                      # Driver ratings
│   │   ├── VerificationCode.php            # Email OTP codes
│   │   └── AdminAuditLog.php               # Admin action tracking
│   │
│   └── Services/
│       ├── LocationService.php             # PostGIS spatial queries
│       ├── RideService.php                 # Core business logic
│       ├── PlaceSearchService.php          # DB + Mapbox search
│       ├── NotificationService.php         # FCM push notifications
│       └── MapboxService.php               # Mapbox API integration
│
├── database/
│   ├── migrations/                          # Database schema
│   ├── factories/                           # Test data factories
│   └── seeders/
│       ├── DatabaseSeeder.php              # Main seeder (locations + users)
│       └── new_locations.csv               # Campus locations data
│
├── routes/
│   ├── api.php                             # API routes
│   └── channels.php                        # WebSocket channels
│
├── config/
│   ├── services.php                        # External services (Mapbox, FCM)
│   └── reverb.php                          # WebSocket config
│
├── tests/
│   ├── Feature/                            # Integration tests
│   └── Unit/                               # Unit tests
│
└── public/
    └── admin-dashboard.html                # Admin control panel
```

## Mobile (`mobile/`)

Flutter app with product flavors (rider/driver).

```
mobile/
├── lib/
│   ├── main.dart                           # App entry point
│   │
│   ├── core/                               # Shared code
│   │   ├── models/
│   │   │   ├── user.dart
│   │   │   ├── ride.dart
│   │   │   ├── ride_request.dart
│   │   │   ├── location.dart
│   │   │   └── driver_profile.dart
│   │   │
│   │   ├── services/
│   │   │   ├── api/
│   │   │   │   └── api_service.dart        # HTTP client
│   │   │   ├── auth/
│   │   │   │   └── auth_service.dart       # Firebase auth
│   │   │   ├── ride/
│   │   │   │   ├── ride_service.dart
│   │   │   │   └── ride_request_service.dart
│   │   │   ├── location/
│   │   │   │   └── location_service.dart   # GPS tracking
│   │   │   ├── websocket/
│   │   │   │   └── websocket_service.dart  # Real-time events
│   │   │   ├── mapbox/
│   │   │   │   └── mapbox_directions_service.dart
│   │   │   └── place/
│   │   │       └── place_search_service.dart
│   │   │
│   │   ├── providers/
│   │   │   ├── auth_provider.dart
│   │   │   ├── active_ride_provider.dart
│   │   │   └── location_provider.dart
│   │   │
│   │   └── widgets/                        # Reusable components
│   │       ├── custom_button.dart
│   │       ├── location_input.dart
│   │       └── map_widget.dart
│   │
│   ├── rider/                              # Rider-specific
│   │   └── screens/
│   │       ├── home_screen.dart            # Ride booking
│   │       ├── tracking_screen.dart        # Active ride tracking
│   │       ├── waiting_screen.dart         # Waiting for driver
│   │       └── rating_screen.dart          # Rate driver
│   │
│   └── driver/                             # Driver-specific
│       └── screens/
│           ├── email_verification_screen.dart
│           ├── kyc_form_screen.dart        # KYC verification
│           ├── driver_home_screen.dart     # Online/offline + requests
│           ├── ride_request_screen.dart    # Accept/decline ride
│           └── active_ride_screen.dart     # Navigation + controls
│
├── android/
│   └── app/src/
│       ├── rider/                          # Rider flavor config
│       └── driver/                         # Driver flavor config
│
└── test/                                   # Widget/unit tests
```

## Documentation (`docs/`)

```
docs/
├── architecture/
│   └── tech_spec.md                        # Technical specification
│
├── api/
│   └── API_DOCUMENTATION.md                # OpenAPI spec
│
├── guides/
│   ├── FLUTTER_IMPLEMENTATION_GUIDE.md     # Mobile development plan
│   ├── CONTRIBUTING.md                     # Contribution guidelines
│   └── DEVELOPMENT.md                      # Setup instructions
│
├── phases/
│   ├── PHASE_1_COMPLETION_SUMMARY.md       # Mobile auth completion
│   ├── PHASE_4_COMPLETION_REPORT.md        # Backend API completion
│   ├── PHASE_5_COMPLETION_REPORT.md        # WebSocket completion
│   ├── PHASE_8_COMPLETION_REPORT.md        # Mapbox integration
│   └── PHASE_9_IMPLEMENTATION_PLAN.md      # Current phase plan
│
├── setup/
│   └── infrastructure.md                   # DigitalOcean setup
│
└── testing/
    └── TESTING_DOCUMENTATION.md            # Testing strategy
```

## Key Files

| File | Purpose |
|------|---------|
| `backend/routes/api.php` | All API endpoints definition |
| `backend/app/Services/RideService.php` | Core ride logic (matching, lifecycle) |
| `mobile/lib/main.dart` | App entry point with flavor routing |
| `mobile/lib/core/services/api/api_service.dart` | Backend API client |
| `mobile/lib/core/providers/active_ride_provider.dart` | Ride state management |
| `backend/database/seeders/DatabaseSeeder.php` | Seed test data |
| `backend/public/admin-dashboard.html` | Admin control panel |
| `CLAUDE.md` | AI development instructions |
| `CONTINUE_HERE.md` | Current progress tracker |

## Quick Navigation Tips

### Backend Development
```bash
cd backend
php artisan serve              # Start API server
php artisan reverb:start       # Start WebSocket server
php artisan test               # Run tests
php artisan migrate:fresh      # Reset database
php artisan db:seed            # Seed test data
```

### Mobile Development
```bash
cd mobile
flutter pub get                         # Install dependencies
flutter run --flavor rider              # Run rider app
flutter run --flavor driver             # Run driver app
flutter test                            # Run tests
flutter analyze                         # Check code quality
```

### Common Tasks

| Task | Location |
|------|----------|
| Add new API endpoint | `backend/app/Http/Controllers/Api/` + `backend/routes/api.php` |
| Add new model | `backend/app/Models/` + create migration |
| Add new screen (rider) | `mobile/lib/rider/screens/` |
| Add new screen (driver) | `mobile/lib/driver/screens/` |
| Add shared widget | `mobile/lib/core/widgets/` |
| Add new service | `mobile/lib/core/services/` or `backend/app/Services/` |
| Update API docs | `docs/api/API_DOCUMENTATION.md` |
| Track progress | `CONTINUE_HERE.md` |

## Project Phases

See `docs/guides/FLUTTER_IMPLEMENTATION_GUIDE.md` for detailed phase breakdown.

**Current Phase**: Phase 9 - Complete Ride Flow & Driver App
**Status**: Backend 83% complete, Mobile Phase 1 + 8 complete

---

Last Updated: December 1, 2025
