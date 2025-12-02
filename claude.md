# Anjem - Ride-sharing Platform Development Guide

## Project Context

Building a campus ride-sharing platform with Flutter mobile apps (rider/driver via product flavors) and Laravel backend. MVP target: 30 days.

## Active Development Phase

**Current Sprint**: Flutter Mobile Implementation (CRITICAL PRIORITY)
**Status**: Backend 83% complete - Mobile Phase 1 + Phase 8 complete - Now starting Phase 9
**Last Updated**: October 29, 2025
**Days to MVP**: ~10 days

### Backend Development Phases (Structured Approach)

**Phase 1: Core Models (2-3 hours) - COMPLETED ✅**
├── Database structure aligned with original design ✅
├── Firebase authentication integration ✅
├── Laravel Sanctum token abilities ✅
└── User model with soft deletes and role management ✅

**Phase 2: Database Integration (1 hour) - COMPLETED ✅**
├── Firebase authentication fields added ✅
├── Mobile app enhancements (passenger_count, special_requests) ✅
├── Enhanced UserFactory with user types ✅
├── Realistic seeder with campus locations ✅
└── Test accounts for development ✅

**Phase 3: Essential Services (3-4 hours) - COMPLETED ✅**
├── LocationService (PostGIS queries) ✅
├── RideService (core business logic) ✅
├── NotificationService (FCM integration) ✅
└── PlaceSearchService (Mapbox + DB search) ✅

**Phase 4: Controllers & API (2-3 hours) - COMPLETED ✅**
├── AuthController with Firebase integration ✅
├── RideController with security validation ✅
├── DriverController with location tracking ✅
├── RequestController with business logic ✅
├── API Resources for clean responses ✅
├── Form Request validation classes ✅
└── Comprehensive edge case testing (50+ security tests) ✅

**Phase 5: Real-time Features (2 hours) - COMPLETED ✅**
├── Reverb WebSocket setup ✅
├── Broadcasting events ✅
├── Driver location updates ✅
├── Event classes for all real-time features ✅
└── Comprehensive real-time testing ✅

**Phase 0: Mapbox Integration & Place Search (1.5 hours) - COMPLETED ✅**
├── Full-text search index for locations table with Indonesian language support ✅
├── PlaceSearchService: Local DB + Mapbox Search API fallback ✅
├── PlaceController with /api/v1/places/search endpoint ✅
├── EssentialLocationsSeeder: 27 campus locations (popular pickup/drop-off points) ✅
├── Mapbox configuration in services.php and .env ✅
└── API documentation updated with place search endpoint ✅

**Phase 6: Backend Testing & Polish (1-2 hours) - PENDING**
├── Feature tests for critical paths
├── Error handling improvements
└── Performance optimization

### Flutter Mobile Apps Implementation - IN PROGRESS 🚧

**See `docs/guides/FLUTTER_IMPLEMENTATION_GUIDE.md` for detailed implementation plan:**

├── Phase 1: Core Setup & Authentication + Driver KYC (8 hours) - ✅ COMPLETED (Oct 1)
├── Phase 8: Mapbox Integration & Route Visualization (2 days) - ✅ COMPLETED (Oct 28)
├── **Phase 9: Complete Ride Flow & Driver App (4-5 days) - 🚧 IN PROGRESS** ⭐
│ ├── Phase A: Complete Rider UI (1.5-2 days)
│ │ ├── WaitingScreen with WebSocket
│ │ ├── DriverMatchedScreen
│ │ ├── ActiveRideTrackingScreen
│ │ └── RatingScreen
│ ├── Phase B: Minimal Driver Flow (1.5-2 days)
│ │ ├── DriverHomeScreen (online/offline, incoming requests)
│ │ ├── RideRequestScreen (accept/decline)
│ │ └── DriverNavigationScreen (active ride tracking)
│ └── Phase C: Integration & Polish (1 day)
├── Phase 10: UI/UX Polish & Animations (2 days) - PENDING
├── Phase 11: Push Notifications (1 day) - PENDING
└── Phase 12: Testing & Deployment (2-3 days) - PENDING

**Completion Reports:**

- Phase 1: `docs/phases/PHASE_1_COMPLETION_SUMMARY.md` (Oct 1, 2025)
- Phase 8: `docs/phases/PHASE_8_COMPLETION_REPORT.md` (Oct 28, 2025)
- **Phase 9 Plan**: `docs/phases/PHASE_9_IMPLEMENTATION_PLAN.md` ⭐ NEW

## Key Constraints

- Single Flutter codebase, 2 product flavors (rider, driver)
- **Android-only for MVP** (iOS deferred to post-MVP)
- $0 infrastructure budget (use student credits)
- No payment processing in MVP
- Must handle 200 RPS at peak
- Crash-free rate ≥98.5%

## File References

### Core Documentation

- Technical specification: `docs/architecture/tech_spec.md`
- API documentation: `docs/api/API_DOCUMENTATION.md`
- Infrastructure setup: `docs/setup/infrastructure.md`
- Testing documentation: `docs/testing/TESTING_DOCUMENTATION.md`
- Flutter implementation guide: `docs/guides/FLUTTER_IMPLEMENTATION_GUIDE.md`
- Contributing guidelines: `docs/guides/CONTRIBUTING.md`
- Development setup: `docs/setup/DEVELOPMENT.md`

### Phase Completion Reports

- Phase 1 (Mobile Auth): `docs/phases/PHASE_1_COMPLETION_SUMMARY.md`
- Phase 4 (Backend API): `docs/phases/PHASE_4_COMPLETION_REPORT.md`
- Phase 5 (WebSocket): `docs/phases/PHASE_5_COMPLETION_REPORT.md`
- Phase 8 (Mapbox): `docs/phases/PHASE_8_COMPLETION_REPORT.md`
- **Phase 9 Plan (Current)**: `docs/phases/PHASE_9_IMPLEMENTATION_PLAN.md` ⭐ NEW

## Development Standards

### Code Organization

```
anjem/
├── backend/          # Laravel API
├── mobile/           # Flutter apps
│   ├── lib/
│   │   ├── core/    # Shared logic
│   │   ├── rider/   # Rider-specific
│   │   └── driver/  # Driver-specific
│   └── android/app/src/
│       ├── rider/   # Flavor config
│       └── driver/  # Flavor config
└── docs/            # Documentation
```

### Git Workflow

- Branch naming: `feat/description`, `fix/description`
- Commit format: `type(scope): message`
- Always run tests before commit

## Current TODOs (expand as we go)

### Database Integration (COMPLETED ✅)

- [x] Create PostgreSQL database with PostGIS extension
- [x] Install Laravel PostGIS spatial package (`matanyadaev/laravel-eloquent-spatial`)
- [x] Create unified User model with Sanctum token abilities
- [x] Create DriverProfile model with spatial location tracking
- [x] Create Location model for pickup/destination points
- [x] Create RideRequest, Ride, Rating models with relationships
- [x] Create DriverSession models for analytics
- [x] Generate migrations with PostGIS spatial columns and indexes
- [x] Test and verify database connection with Laravel
- [x] Confirm PostGIS spatial functionality is working

### API Development (COMPLETED ✅)

- [x] Create model factories and seeders for campus locations ✅
- [x] Create API resource classes for mobile app responses ✅
- [x] Implement RideService for driver-rider matching based on proximity ✅
- [x] Implement LocationService for PostGIS spatial queries ✅
- [x] Implement PlaceSearchService for location search ✅
- [x] Create API controllers for authentication, rides, and driver operations ✅
- [x] Comprehensive edge case testing with 50+ security tests ✅
- [x] Token security validation (SQL injection, XSS, malformed headers) ✅
- [x] Role-based authorization and permission testing ✅

### Infrastructure & DevOps

- [x] Initialize Flutter project with flavors
- [x] Setup Laravel API structure
- [x] Create comprehensive developer documentation
- [x] Implement Firebase authentication system
- [x] Migrate from MySQL to PostgreSQL
- [x] Add OAuth + Sanctum integration
- [x] Add driver KYC verification with email validation
- [ ] Configure DigitalOcean infrastructure
- [ ] Setup CI/CD pipelines
- [x] Add real-time WebSocket communication ✅

## Database Integration Guide

### Database Configuration

**PostgreSQL Database**: `anjemme` ✅ CONNECTED

- **Host**: localhost
- **Port**: 5432
- **Username**: jonathanalanohasiholan
- **Password**: irisdotd
- **Extensions**: PostGIS v3.6 (spatial data), UUID (unique identifiers)
- **Status**: All tables created, Laravel connection verified

### Database Schema Overview

The PostgreSQL database is fully created with PostGIS spatial support and includes:

**Core Tables:**

- `users` - Unified user model (riders + drivers)
- `driver_profiles` - Driver-specific info with spatial location tracking + KYC fields
- `verification_codes` - Email verification codes for KYC (6-digit OTP, 10-min expiry)
- `locations` - Popular pickup/drop-off points (PostGIS POINT geometry)
- `ride_requests` - Ride booking requests before matching
- `rides` - Actual completed trips with status tracking
- `ratings` - JSONB-powered rating system with predefined tags
- `driver_sessions` - Analytics tracking for driver performance

**Spatial Features:**

- PostGIS POINT geometry for coordinates with SRID 4326 (WGS84)
- GiST spatial indexes for efficient proximity queries using `ST_DWithin()`
- Distance calculations with `ST_Distance()` for matching algorithms

### Laravel Integration Status

**✅ Phases 1-5 Complete (Backend 83%):**

- PostgreSQL with PostGIS spatial support ✅
- Firebase + Sanctum authentication ✅
- Core models: Users, DriverProfile, Location, RideRequest, Ride, Rating ✅
- Business logic services: LocationService, QueueService, RideService, NotificationService ✅
- API endpoints: Auth, Requests, Rides, Driver operations ✅
- Laravel Reverb WebSocket real-time features ✅
- 183/220 tests passing (50+ security tests) ✅

**🔄 Phase 6 Pending (Backend Polish):**

- Feature tests for critical paths, error handling improvements, performance optimization

### Key Business Logic Services

**RideService** (`app/Services/RideService.php`)

- Core ride lifecycle management (create, accept, start, complete, cancel)
- Driver-rider matching based on proximity (any driver can accept)
- Fare calculation with campus-specific pricing
- Real-time status updates and WebSocket broadcasting

**LocationService** (`app/Services/LocationService.php`)

- PostGIS spatial queries for nearby drivers and locations
- Distance and duration calculations using Mapbox Directions API
- Route geometry generation for map visualization
- Popular location caching

**PlaceSearchService** (`app/Services/PlaceSearchService.php`) - NEW ✅

- Full-text search on local `locations` table (PostgreSQL + Indonesian language)
- PostGIS proximity filtering with `ST_DWithin()`
- Mapbox Search API fallback when < 5 local results
- Auto-caching of API results back to database
- Usage count tracking for popularity ranking

### Redis Caching Strategy

- **Driver Status**: Online/offline status cached with TTL
- **Driver Locations**: Real-time driver locations buffered before PostgreSQL writes
- **Active Requests**: Cache pending ride requests for fast matching
- **Popular Locations**: Frequently searched locations for quick autocomplete

### Authentication Flow

**Email OTP + Sanctum Tokens:**

1. User requests OTP via email (no phone numbers)
2. OTP verification creates Sanctum token with role-based abilities
3. Token abilities differ for rider vs driver actions
4. Firebase integration for push notifications

**Token Abilities:**

- **Rider**: `rider:request-ride`, `rider:cancel-ride`, `rider:rate-driver`
- **Driver**: `driver:go-online`, `driver:accept-ride`, `driver:complete-ride`
- **Shared**: `profile:read`, `profile:update`, `locations:read`

## Decision Log (update as we go)

| Date    | Decision                                 | Rationale                                                               |
| ------- | ---------------------------------------- | ----------------------------------------------------------------------- |
| [25/09] | Flutter over PWA                         | iOS reliability, AdMob future                                           |
| [25/09] | Product flavors over separate apps       | Shared codebase efficiency                                              |
| [26/09] | ~~MySQL~~ PostgreSQL as main DB          | Better scalability, JSON support                                        |
| [26/09] | Firebase Auth + OAuth + Sanctum          | Better security, social login support                                   |
| [26/09] | Email-based authentication               | More reliable than SMS, better UX                                       |
| [29/09] | Comprehensive edge case testing          | Security-first approach, 48 tests                                       |
| [29/09] | Token-based authorization                | Sanctum abilities for role separation                                   |
| [03/10] | Driver KYC with email verification       | Auto-approve on email confirm, domain-restricted                        |
| [11/10] | **~~Google Maps~~ Mapbox Platform**      | **$0 budget constraint, 10x cheaper, 100k free map loads/month**        |
| [11/10] | **Local DB + API fallback search**       | **80% searches hit DB (free), Mapbox fallback for uncommon queries**    |
| [30/10] | **~~Beacon-based~~ Standard Ride Model** | **Simpler UX like Uber/Grab, any driver can accept any nearby request** |
