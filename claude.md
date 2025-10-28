# Anjem - Ride-sharing Platform Development Guide

## Project Context

Building a campus ride-sharing platform with Flutter mobile apps (rider/driver via product flavors) and Laravel backend. MVP target: 30 days.

## Active Development Phase

**Current Sprint**: Flutter Mobile Implementation (CRITICAL PRIORITY)
**Status**: Backend 83% complete (Phase 5 done) - Mobile Phase 1 complete + Driver KYC - 13 days to MVP
**Last Updated**: October 3, 2025

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
├── Realistic seeder with campus beacon locations ✅
└── Test accounts for development ✅

**Phase 3: Essential Services (3-4 hours) - COMPLETED ✅**
├── LocationService (PostGIS queries) ✅
├── QueueService (Redis caching) ✅
├── RideService (core business logic) ✅
└── NotificationService (FCM integration) ✅

**Phase 4: Controllers & API (2-3 hours) - COMPLETED ✅**
├── AuthController with Firebase integration ✅
├── RideController with security validation ✅
├── DriverController with queue management ✅
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
├── EssentialLocationsSeeder: 27 campus locations (12 beacons + 15 destinations) ✅
├── Mapbox configuration in services.php and .env ✅
└── API documentation updated with place search endpoint ✅

**Phase 6: Backend Testing & Polish (1-2 hours) - PENDING**
├── Feature tests for critical paths
├── Error handling improvements
└── Performance optimization

### Flutter Mobile Apps Implementation (60 hours / ~15 days) - IN PROGRESS 🚧

**See `docs/FLUTTER_IMPLEMENTATION_GUIDE.md` for detailed implementation plan:**

├── Phase 1: Core Setup & Authentication + Driver KYC (8 hours) - ✅ COMPLETED
├── Phase 2: Rider App Core Flow (10 hours) - NEXT
├── Phase 3: Driver App Core Flow (10 hours)
├── Phase 4: Maps & Navigation (8 hours)
├── Phase 5: Real-time WebSocket Integration (8 hours)
├── Phase 6: UI/UX Polish (8 hours)
└── Phase 7: Testing & Deployment (8 hours)

**Phase 1 Completion**: October 1, 2025 (See `docs/PHASE_1_COMPLETION_SUMMARY.md`)

## Key Constraints

- Single Flutter codebase, 2 product flavors (rider, driver)
- **Android-only for MVP** (iOS deferred to post-MVP)
- $0 infrastructure budget (use student credits)
- No payment processing in MVP
- Must handle 200 RPS at peak
- Crash-free rate ≥98.5%

## File References

- Technical specification: `docs/tech_spec.md`
- API contracts: `docs/api_spec.md` (see also `docs/API_DOCUMENTATION.md`)
- Infrastructure setup: `docs/infra_setup.md`
- Testing documentation: `docs/TESTING_DOCUMENTATION.md`
- Phase 4 completion report: `docs/PHASE_4_COMPLETION_REPORT.md`
- **Flutter implementation guide: `docs/FLUTTER_IMPLEMENTATION_GUIDE.md`** ⭐ NEW
- Contributing guidelines: `docs/CONTRIBUTING.md`
- Development setup: `docs/DEVELOPMENT.md`

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
- [x] Create Location model for beacons and P2P destinations
- [x] Create RideRequest, Ride, Rating models with relationships
- [x] Create DriverQueue and DriverSession models for analytics
- [x] Generate migrations with PostGIS spatial columns and indexes
- [x] Test and verify database connection with Laravel
- [x] Confirm PostGIS spatial functionality is working

### API Development (COMPLETED ✅)

- [x] Create model factories and seeders for campus beacon locations ✅
- [x] Create API resource classes for mobile app responses ✅
- [x] Implement MatchingService for driver-rider pairing algorithm ✅
- [x] Implement QueueService for beacon queue management with Redis ✅
- [x] Implement LocationService for PostGIS spatial queries ✅
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
- **Password**: ***REDACTED***
- **Extensions**: PostGIS v3.6 (spatial data), UUID (unique identifiers)
- **Status**: All tables created, Laravel connection verified

### Database Schema Overview

The PostgreSQL database is fully created with PostGIS spatial support and includes:

**Core Tables:**

- `users` - Unified user model (riders + drivers)
- `driver_profiles` - Driver-specific info with spatial location tracking + KYC fields
- `verification_codes` - Email verification codes for KYC (6-digit OTP, 10-min expiry)
- `locations` - Beacon pickup points + cached P2P destinations (PostGIS POINT geometry)
- `ride_requests` - Ride booking requests before matching
- `rides` - Actual completed trips with status tracking
- `ratings` - JSONB-powered rating system with predefined tags
- `driver_queue` - Queue management at beacon locations
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

**MatchingService** (`app/Services/MatchingService.php`)

- Driver-rider pairing algorithm based on proximity and queue position
- Priority scoring system (wait time, passenger count, fare amount)
- Redis caching for active ride requests

**QueueService** (`app/Services/QueueService.php`)

- Beacon queue management with real-time position tracking
- Redis-cached queue status for mobile app updates
  befor- Estimated wait time calculations

**LocationService** (`app/Services/LocationService.php`)

- PostGIS spatial queries for nearby beacon discovery
- P2P destination caching and deduplication
- Mapbox Directions API integration for distance/duration estimates (optional for MVP)

**PlaceSearchService** (`app/Services/PlaceSearchService.php`) - NEW ✅

- Full-text search on local `locations` table (PostgreSQL + Indonesian language)
- PostGIS proximity filtering with `ST_DWithin()`
- Mapbox Search API fallback when < 5 local results
- Auto-caching of API results back to database
- Usage count tracking for popularity ranking

### Redis Caching Strategy

- **Driver Status**: Online/offline status cached with TTL
- **Queue Positions**: Real-time queue positions at each beacon
- **Location Updates**: Buffer driver location updates before PostgreSQL writes
- **Active Requests**: Cache pending ride requests for fast matching

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

| Date    | Decision                           | Rationale                             |
| ------- | ---------------------------------- | ------------------------------------- |
| [25/09] | Flutter over PWA                   | iOS reliability, AdMob future         |
| [25/09] | Product flavors over separate apps | Shared codebase efficiency            |
| [26/09] | ~~MySQL~~ PostgreSQL as main DB    | Better scalability, JSON support      |
| [26/09] | Firebase Auth + OAuth + Sanctum    | Better security, social login support |
| [26/09] | Email-based authentication         | More reliable than SMS, better UX     |
| [29/09] | Comprehensive edge case testing    | Security-first approach, 48 tests     |
| [29/09] | Token-based authorization          | Sanctum abilities for role separation |
| [03/10] | Driver KYC with email verification | Auto-approve on email confirm, domain-restricted |
| [11/10] | **~~Google Maps~~ Mapbox Platform** | **$0 budget constraint, 10x cheaper, 100k free map loads/month** |
| [11/10] | **Local DB + API fallback search** | **80% searches hit DB (free), Mapbox fallback for uncommon queries** |
