# Anjem - Ride-sharing Platform Development Guide

## Project Context

Building a campus ride-sharing platform with Flutter mobile apps (rider/driver via product flavors) and Laravel backend. MVP target: 30 days.

## Active Development Phase

**Current Sprint**: Phase 4 - Controllers & API Implementation
**Status**: Phase 4 edge case testing completed ✅ - Ready for Phase 5
**Last Updated**: September 29, 2025

### Development Phases (Structured Approach)

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

**Phase 5: Real-time Features (2 hours)**
├── Reverb WebSocket setup
├── Broadcasting events
└── Driver location updates

**Phase 6: Testing & Polish (1-2 hours)**
├── Feature tests for critical paths
├── Error handling
└── Performance optimization

## Key Constraints

- Single Flutter codebase, 2 product flavors (rider, driver)
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
- [ ] Configure DigitalOcean infrastructure
- [ ] Setup CI/CD pipelines
- [ ] Add real-time WebSocket communication

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
- `driver_profiles` - Driver-specific info with spatial location tracking
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

**✅ Phase 1 & 2 Completed:**

- Database structure based on original `anjem_database_setup.sql` design ✅
- Firebase authentication integration with Laravel Sanctum ✅
- PostGIS Laravel package installed (`matanyadaev/laravel-eloquent-spatial`) ✅
- Core tables: users, driver_profiles, locations, ride_requests, rides, ratings, driver_queue, driver_sessions ✅
- User model with Firebase UID, user types (rider/driver/both), and soft deletes ✅
- Enhanced ride_requests table with mobile app features ✅
- Sanctum token abilities for role-based API access ✅
- Campus beacon locations seeded (5 locations at UI campus) ✅
- Test users created with different user types ✅
- Database connection tested and verified working ✅

**✅ Phase 3 Completed:**

- LocationService with PostGIS spatial queries ✅
- QueueService with Redis caching ✅
- RideService with core business logic ✅
- NotificationService with FCM integration ✅
- Comprehensive test coverage (96 tests, 220 assertions) ✅

**✅ Phase 4 Completed:**

- API controllers with proper authentication middleware ✅
- Form Request validation classes ✅
- API Resource classes for clean responses ✅
- Complete ride management endpoints ✅
- Comprehensive edge case testing (50+ security tests) ✅
- Token security validation (SQL injection, XSS protection) ✅
- Role-based authorization testing ✅

**🔄 Phase 5 Ready:**

- Reverb WebSocket setup for real-time communication
- Broadcasting events for driver location updates
- Real-time ride status notifications

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
- Google Maps integration for distance/duration estimates

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
