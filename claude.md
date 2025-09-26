# Anjem - Ride-sharing Platform Development Guide

## Project Context

Building a campus ride-sharing platform with Flutter mobile apps (rider/driver via product flavors) and Laravel backend. MVP target: 30 days.

## Active Development Phase

**Current Sprint**: API Development & Service Layer Implementation
**Status**: Database integration completed ✅ - Ready for API development
**Last Updated**: September 26, 2024

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
- Testing requirements: `docs/testing_plan.md`
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

### API Development (NEXT PRIORITY)
- [ ] Create model factories and seeders for campus beacon locations
- [ ] Create API resource classes for mobile app responses
- [ ] Implement MatchingService for driver-rider pairing algorithm
- [ ] Implement QueueService for beacon queue management with Redis
- [ ] Implement LocationService for PostGIS spatial queries
- [ ] Create API controllers for authentication, rides, and driver operations

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

**✅ Completed:**
- PostGIS Laravel package installed (`matanyadaev/laravel-eloquent-spatial`)
- All Eloquent models created with proper relationships and spatial casting
- Sanctum authentication configured with rider/driver token abilities
- Migrations generated with PostGIS spatial columns and indexes
- Database connection tested and verified working
- PostGIS spatial functionality confirmed (v3.6)
- All database tables created and schema validated

**🔄 Next Steps:**
- Create realistic campus beacon location seeders
- Implement core service classes (MatchingService, QueueService, LocationService)
- Build API resources for mobile app responses
- Create API controllers with proper authentication middleware

### Key Business Logic Services

**MatchingService** (`app/Services/MatchingService.php`)
- Driver-rider pairing algorithm based on proximity and queue position
- Priority scoring system (wait time, passenger count, fare amount)
- Redis caching for active ride requests

**QueueService** (`app/Services/QueueService.php`)
- Beacon queue management with real-time position tracking
- Redis-cached queue status for mobile app updates
- Estimated wait time calculations

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

| Date    | Decision                           | Rationale                     |
| ------- | ---------------------------------- | ----------------------------- |
| [25/07] | Flutter over PWA                   | iOS reliability, AdMob future |
| [25/07] | Product flavors over separate apps | Shared codebase efficiency    |
| [26/09] | ~~MySQL~~ PostgreSQL as main DB   | Better scalability, JSON support |
| [26/09] | Firebase Auth + OAuth + Sanctum    | Better security, social login support |
| [26/09] | Email-based authentication         | More reliable than SMS, better UX |
