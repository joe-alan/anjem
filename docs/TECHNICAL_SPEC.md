# Anjem — Technical Specification

_Last updated: 2026-03-14_

---

## 1. Stack Overview

| Layer | Technology | Version | Notes |
|---|---|---|---|
| **Backend** | Laravel | 11 | PHP 8.2 |
| **Mobile** | Flutter | 3.24 | Two flavors: rider, driver |
| **Database** | PostgreSQL | 15 | With PostGIS 3.6 for spatial queries |
| **Cache / Queue** | Redis | 7 | Queue driver, broadcasting, cache |
| **Real-time** | Laravel Reverb | 1.6+ | WebSocket server (Pusher protocol) |
| **Push Notifications** | Firebase Cloud Messaging | — | FCM via `kreait/firebase-php` |
| **Auth (Identity)** | Firebase Authentication | — | Google OAuth provider |
| **Auth (API)** | Laravel Sanctum | — | Token-based, 24hr expiry |
| **Maps & Routing** | Mapbox | — | Directions API, Search Box API, Maps SDK |
| **Admin Panel** | Filament | 3.2.123 | Server-rendered Blade/Livewire |
| **State Management** | Riverpod | — | Flutter side |
| **File Storage** | Local (public disk) | — | KTM uploads. DO Spaces planned for production. |

### Infrastructure (MVP)

| Component | Provider | Notes |
|---|---|---|
| Cloud | DigitalOcean | GitHub Student Pack credits |
| Compute | App Platform or Droplet + Docker | |
| Database | Managed Postgres (planned) | Currently running natively on dev machine |
| Cache | Managed Redis (planned) | Currently running natively on dev machine |
| Object Storage | DO Spaces + CDN (planned) | Currently local public disk |
| CI/CD | GitHub Actions | Laravel test, Flutter analyze + test |
| Android Distribution | Play Console | Internal → Closed testing |
| Domain | anjem.me | Microsite + privacy policy |

**Note**: The `docker-compose.yml` in the repo root is legacy. PostgreSQL and Redis run natively in development, not via Docker containers.

**Future migration**: GCP (Cloud Run + Cloud SQL + Memorystore) when Google for Startups credits are approved.

---

## 2. Repository Structure

```
anjem/
├── backend/                          # Laravel 11 application
│   ├── app/
│   │   ├── Console/
│   │   │   ├── Commands/             # Artisan commands (TestRouteCaching, etc.)
│   │   │   └── Kernel.php            # Scheduled tasks
│   │   ├── Events/                   # 14 broadcast events
│   │   ├── Exceptions/               # InsufficientCreditsException
│   │   ├── Filament/                 # Admin panel (Resources, Pages, Widgets)
│   │   ├── Http/
│   │   │   ├── Controllers/Api/      # 9 API controllers
│   │   │   ├── Middleware/           # AdminOnly, standard Laravel middleware
│   │   │   ├── Requests/            # Form request validation classes
│   │   │   └── Resources/           # API response transformers
│   │   ├── Jobs/                    # ExpireRideRequest, HandleRequestTimeout
│   │   ├── Models/                  # 12 Eloquent models
│   │   ├── Providers/               # Service providers, Filament panel
│   │   └── Services/               # 12 service classes (business logic)
│   ├── config/                      # Laravel config files
│   ├── database/
│   │   ├── migrations/              # 39 migration files
│   │   └── seeders/                 # AdminUserSeeder, EssentialLocationsSeeder
│   ├── routes/
│   │   ├── api.php                  # All API routes
│   │   └── channels.php            # WebSocket channel auth
│   ├── resources/views/filament/    # Blade templates for admin pages
│   └── tests/                       # PHPUnit test suite
│
├── mobile/                           # Flutter application
│   ├── lib/
│   │   ├── core/                    # Shared code (both flavors)
│   │   │   ├── config/             # AppConfig, MapboxConfig, environment
│   │   │   ├── models/             # Data models (User, Ride, RideRequest, etc.)
│   │   │   ├── providers/          # Riverpod providers (auth, driver status, etc.)
│   │   │   ├── services/           # API, Auth, WebSocket, Location, Credit, FCM
│   │   │   └── widgets/            # Shared widgets (RouteMapWidget, etc.)
│   │   ├── rider/                   # Rider-specific screens
│   │   │   └── screens/            # Home, LocationSelection, RideDetails, ActiveRide, Rating
│   │   ├── driver/                  # Driver-specific screens
│   │   │   └── screens/            # Home, KYC, RideRequest, ActiveRide, Credits
│   │   ├── main_rider.dart         # Rider flavor entry point
│   │   └── main_driver.dart        # Driver flavor entry point
│   ├── android/
│   │   └── app/src/
│   │       ├── rider/              # Rider flavor (google-services.json)
│   │       └── driver/             # Driver flavor (google-services.json)
│   └── pubspec.yaml                # Dependencies
│
├── docs/                            # Documentation
├── CLAUDE.md                        # Development guidelines
├── CONTINUE_HERE.md                 # Current task context
└── admin_phase3_plan.md             # Admin panel Phase 3 plan
```

---

## 3. Data Model

### 3.1 Core Tables

#### `users`

The unified user model for riders, drivers, and admins.

```sql
users (
    id                  BIGSERIAL PRIMARY KEY,
    firebase_uid        VARCHAR UNIQUE,
    name                VARCHAR NOT NULL,
    email               VARCHAR NOT NULL UNIQUE,
    password            VARCHAR,                    -- nullable (OAuth users)
    role                ENUM('rider', 'driver', 'both', 'admin') DEFAULT 'rider',
    phone_number        VARCHAR,
    phone_verified_at   TIMESTAMP,
    email_verified_at   TIMESTAMP,
    fcm_token           TEXT,                       -- Firebase Cloud Messaging token
    profile_picture     VARCHAR,
    emergency_contact   VARCHAR,
    preferred_payment   VARCHAR,
    rider_rating_avg    DECIMAL(3,2),
    total_rides_taken   INTEGER DEFAULT 0,
    is_active           BOOLEAN DEFAULT TRUE,
    last_active_at      TIMESTAMP,
    remember_token      VARCHAR(100),
    created_at          TIMESTAMP,
    updated_at          TIMESTAMP,
    deleted_at          TIMESTAMP                   -- soft deletes
);

-- Indexes
INDEX idx_users_role ON users(role);
INDEX idx_users_firebase_uid ON users(firebase_uid);
```

#### `driver_profiles`

Driver-specific information, linked 1:1 to `users`.

```sql
driver_profiles (
    id                      BIGSERIAL PRIMARY KEY,
    user_id                 BIGINT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,

    -- KYC fields
    ktm_url                 VARCHAR,                -- path to KTM photo (deleted after review)
    student_email           VARCHAR UNIQUE,
    student_id              VARCHAR UNIQUE,
    student_name            VARCHAR,
    email_verified_at       TIMESTAMP,              -- KYC email verification timestamp

    -- Vehicle
    vehicle_type            VARCHAR,
    vehicle_plate           VARCHAR,
    vehicle_color           VARCHAR,

    -- Location (PostGIS)
    current_location        GEOGRAPHY(POINT, 4326), -- real-time GPS
    last_location_update    TIMESTAMP,

    -- Status
    is_verified             BOOLEAN DEFAULT FALSE,  -- KYC approved
    went_online_at          TIMESTAMP,              -- NULL = offline

    -- Ratings
    rating_average          DECIMAL(3,2),
    rating_count            INTEGER DEFAULT 0,
    driver_rating_avg       DECIMAL(3,2),

    -- Performance
    reliability_score       DECIMAL(5,2) DEFAULT 100.0,
    experience_points       INTEGER DEFAULT 0,
    on_time_rate            DECIMAL(5,2) DEFAULT 100.0,

    -- Earnings
    total_earnings          DECIMAL(12,2) DEFAULT 0,
    total_rides_given       INTEGER DEFAULT 0,

    -- FIFO Queue fields
    queue_joined_at         TIMESTAMP,              -- when driver entered FIFO queue
    max_pickup_radius_km    DECIMAL(4,2) DEFAULT 5.0,
    decline_count           INTEGER DEFAULT 0,
    decline_window_start    TIMESTAMP,
    queue_cooldown_until    TIMESTAMP,

    -- Credit System
    credits_balance         INTEGER NOT NULL DEFAULT 0,
    credits_total_earned    INTEGER NOT NULL DEFAULT 0,
    credits_total_spent     INTEGER NOT NULL DEFAULT 0,

    created_at              TIMESTAMP,
    updated_at              TIMESTAMP
);

-- Indexes
INDEX idx_driver_profiles_user_id ON driver_profiles(user_id);
INDEX idx_driver_profiles_student_email ON driver_profiles(student_email);
INDEX idx_driver_profiles_student_id ON driver_profiles(student_id);
-- Spatial index on current_location (GIST)
```

#### `locations`

Pickup/destination points. Originally "beacons," now general locations.

```sql
locations (
    id              BIGSERIAL PRIMARY KEY,
    name            VARCHAR NOT NULL,
    address         VARCHAR,
    coordinates     GEOMETRY(POINT, 4326) NOT NULL, -- PostGIS
    location_type   VARCHAR DEFAULT 'p2p',          -- 'beacon' or 'p2p'
    campus_area     VARCHAR,
    usage_count     INTEGER DEFAULT 0,
    is_active       BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP
);

-- Indexes
INDEX idx_locations_active ON locations(is_active);
-- Spatial index on coordinates (GIST)
-- Text search index on name
```

#### `ride_requests`

Created when a rider requests a ride, before matching.

```sql
ride_requests (
    id                          BIGSERIAL PRIMARY KEY,
    rider_id                    BIGINT NOT NULL REFERENCES users(id),
    pickup_location_id          BIGINT NOT NULL REFERENCES locations(id),
    destination_location_id     BIGINT NOT NULL REFERENCES locations(id),
    status                      VARCHAR NOT NULL DEFAULT 'pending',
                                -- pending, matched, in_progress, completed, cancelled, expired
    passenger_count             INTEGER DEFAULT 1,
    special_requests            JSON,
    estimated_fare_rp           DECIMAL(10,2),
    estimated_distance_km       DECIMAL(8,3),
    estimated_duration_minutes  INTEGER,
    route_geometry              JSON,               -- GeoJSON LineString (stored at creation)
    expires_at                  TIMESTAMP,
    matched_at                  TIMESTAMP,
    current_driver_id           BIGINT REFERENCES users(id),  -- currently dispatched driver
    rider_cooldown_until        TIMESTAMP,
    created_at                  TIMESTAMP,
    updated_at                  TIMESTAMP
);

-- Indexes
INDEX idx_ride_requests_rider_id ON ride_requests(rider_id);
INDEX idx_ride_requests_status ON ride_requests(status);
```

#### `rides`

Created when a driver accepts a request. Tracks the full ride lifecycle.

```sql
rides (
    id                          BIGSERIAL PRIMARY KEY,
    ride_request_id             BIGINT NOT NULL REFERENCES ride_requests(id),
    rider_id                    BIGINT NOT NULL REFERENCES users(id),
    driver_id                   BIGINT NOT NULL REFERENCES users(id),
    pickup_location_id          BIGINT NOT NULL REFERENCES locations(id),
    destination_location_id     BIGINT NOT NULL REFERENCES locations(id),
    status                      VARCHAR NOT NULL DEFAULT 'matched',
                                -- matched, accepted, driver_arrived, in_progress, completed, cancelled
    passenger_count             INTEGER DEFAULT 1,
    special_requests            JSON,
    driver_notes                TEXT,
    estimated_fare_rp           DECIMAL(10,2),
    actual_fare_rp              DECIMAL(10,2),
    actual_distance_km          DECIMAL(8,3),
    actual_duration_minutes     INTEGER,
    driver_accepted_at          TIMESTAMP,
    arrived_at                  TIMESTAMP,
    pickup_time                 TIMESTAMP,
    dropoff_time                TIMESTAMP,
    created_at                  TIMESTAMP,
    updated_at                  TIMESTAMP
);

-- Indexes
INDEX idx_rides_rider_id ON rides(rider_id);
INDEX idx_rides_driver_id ON rides(driver_id);
INDEX idx_rides_status ON rides(status);
INDEX idx_rides_ride_request_id ON rides(ride_request_id);
```

#### `ratings`

Bidirectional ratings (rider↔driver).

```sql
ratings (
    id              BIGSERIAL PRIMARY KEY,
    ride_id         BIGINT NOT NULL REFERENCES rides(id) ON DELETE CASCADE,
    rater_id        BIGINT NOT NULL REFERENCES users(id),
    rated_id        BIGINT NOT NULL REFERENCES users(id),
    rating_type     VARCHAR NOT NULL,       -- 'rider_to_driver' or 'driver_to_rider'
    score           INTEGER NOT NULL CHECK (score BETWEEN 1 AND 5),
    tags            JSON,                   -- e.g. ["clean-vehicle", "friendly"]
    feedback        TEXT,
    created_at      TIMESTAMP
);
```

### 3.2 Supporting Tables

#### `credit_transactions`

Audit log for all credit movements.

```sql
credit_transactions (
    id              BIGSERIAL PRIMARY KEY,
    driver_id       BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type            VARCHAR(50) NOT NULL,   -- 'admin_grant', 'deduction', 'refund', 'admin_deduct'
    amount          INTEGER NOT NULL,       -- negative for deductions
    balance_before  INTEGER NOT NULL,
    balance_after   INTEGER NOT NULL,
    ride_id         BIGINT REFERENCES rides(id) ON DELETE SET NULL,
    description     TEXT,
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP
);

INDEX idx_driver_transactions ON credit_transactions(driver_id, created_at DESC);
INDEX idx_ride_deductions ON credit_transactions(ride_id);
```

#### `route_caches`

Cached Mapbox Directions API responses for cost reduction.

```sql
route_caches (
    id                          BIGSERIAL PRIMARY KEY,
    origin_location_id          BIGINT NOT NULL REFERENCES locations(id) ON DELETE CASCADE,
    destination_location_id     BIGINT NOT NULL REFERENCES locations(id) ON DELETE CASCADE,
    route_geometry              JSON NOT NULL,          -- GeoJSON LineString
    distance_meters             INTEGER NOT NULL,
    duration_minutes            INTEGER NOT NULL,
    profile                     VARCHAR(20) DEFAULT 'driving-traffic',
    last_fetched_at             TIMESTAMP NOT NULL,     -- TTL tracking
    fetch_count                 INTEGER DEFAULT 1,      -- popularity metric
    created_at                  TIMESTAMP,
    updated_at                  TIMESTAMP,

    UNIQUE (origin_location_id, destination_location_id, profile)
);

INDEX idx_route_cache_last_fetched ON route_caches(last_fetched_at);
```

#### `driver_sessions`

Analytics: tracks when drivers go online/offline.

```sql
driver_sessions (
    id                      BIGSERIAL PRIMARY KEY,
    driver_id               BIGINT NOT NULL REFERENCES users(id),
    went_online_at          TIMESTAMP NOT NULL,
    went_offline_at         TIMESTAMP,
    total_duration_minutes  INTEGER,
    rides_completed         INTEGER DEFAULT 0,
    earnings_rp             DECIMAL(10,2) DEFAULT 0,
    created_at              TIMESTAMP,
    updated_at              TIMESTAMP
);
```

#### `verification_codes`

For driver KYC email verification.

```sql
verification_codes (
    id          BIGSERIAL PRIMARY KEY,
    email       VARCHAR NOT NULL,
    code        VARCHAR NOT NULL,
    expires_at  TIMESTAMP NOT NULL,
    used_at     TIMESTAMP,
    created_at  TIMESTAMP,
    updated_at  TIMESTAMP
);
```

#### `admin_audit_logs`

Immutable audit trail for all admin actions.

```sql
admin_audit_logs (
    id              BIGSERIAL PRIMARY KEY,
    admin_id        BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    action_type     VARCHAR NOT NULL,       -- 12 defined types (see §11)
    target_type     VARCHAR NOT NULL,       -- PHP class name (e.g. 'App\Models\User')
    target_id       BIGINT NOT NULL,
    changes         JSON,                   -- before/after diff
    reason          TEXT,                   -- nullable
    metadata        JSON,
    ip_address      VARCHAR(45),            -- IPv6 support
    user_agent      TEXT,
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP
);

INDEX idx_audit_admin_created ON admin_audit_logs(admin_id, created_at);
INDEX idx_audit_action_created ON admin_audit_logs(action_type, created_at);
INDEX idx_audit_target ON admin_audit_logs(target_type, target_id);
```

#### `driver_queue` (DEPRECATED)

Legacy beacon-based queue table. No longer used after architecture change to standard ride-sharing model (Oct 2025). Can be dropped in a future migration.

#### `personal_access_tokens`

Standard Laravel Sanctum table for API tokens.

#### `failed_jobs`

Standard Laravel failed jobs table for queue debugging.

---

## 4. Authentication System

### Flow

```
Mobile App                    Firebase Auth                Backend
──────────                    ─────────────                ───────
Google Sign-In button
  → Google OAuth SDK
                              Authenticates user
                              Returns Firebase ID token
                                                           POST /api/v1/auth/firebase
                                                             body: { firebase_token }
                                                           Verifies token via Firebase Admin SDK
                                                           Finds or creates User
                                                           Issues Sanctum token with abilities
                                                             → { token, user }
Store token in
FlutterSecureStorage
Use token in all subsequent
  API calls via Authorization header
```

### Sanctum Token Abilities (Role-Based)

| Role | Token Abilities |
|---|---|
| `rider` | `rider:request-ride`, `rider:cancel-ride`, `rider:rate-ride`, `rider:view-rides`, `profile:view`, `profile:update` |
| `driver` | `driver:go-online`, `driver:go-offline`, `driver:accept-ride`, `driver:update-location`, `driver:view-queue`, `driver:kyc`, `profile:view`, `profile:update` |
| `both` | All rider + driver abilities |
| `admin` | All rider + driver + `admin:view-users`, `admin:manage-users`, `admin:view-rides`, `admin:manage-rides`, `admin:view-analytics`, `admin:view-monitoring`, `admin:manage-drivers`, `admin:manage-riders`, `admin:suspend-users` |

### Admin Panel Auth (Separate)

The Filament admin panel uses **session-based** authentication (Laravel's standard `Auth::attempt()`), not Sanctum tokens. After login, `User::canAccessPanel()` checks `role === 'admin' && is_active === true`.

---

## 5. API Routes

### Authentication (`/api/v1/auth/`) — Rate limit: 10/min

| Method | URI | Controller | Notes |
|---|---|---|---|
| POST | `/auth/firebase` | `AuthController@authenticateWithFirebase` | Exchange Firebase token for Sanctum token |
| GET | `/auth/google` | `AuthController@googleRedirect` | Server-side Google OAuth redirect |
| GET | `/auth/google/callback` | `AuthController@googleCallback` | OAuth callback |
| POST | `/auth/refresh` | `AuthController@refreshToken` | Refresh Sanctum token (auth required) |
| POST | `/auth/logout` | `AuthController@logout` | Revoke token (auth required) |
| POST | `/auth/fcm-token` | `AuthController@updateFcmToken` | Update FCM device token (auth required) |

### Public Routes

| Method | URI | Controller | Notes |
|---|---|---|---|
| GET | `/api/v1/health` | Closure | Health check |
| GET | `/api/v1/places/search` | `PlaceController@search` | Mapbox place search (rate limit: 60/min) |

### Protected Routes (`/api/v1/`) — Rate limit: 100/min

#### User

| Method | URI | Controller | Notes |
|---|---|---|---|
| GET | `/user` | Closure | Current user profile with driver_profile |
| GET | `/session/resume` | `SessionController@resume` | Resume session (returns active ride/request state) |

#### Ride Requests

| Method | URI | Controller | Notes |
|---|---|---|---|
| GET | `/requests/estimates` | `RequestController@getEstimates` | Fare estimate with route geometry |
| POST | `/requests` | `RequestController@store` | Create ride request |
| GET | `/requests/{ride_request}` | `RequestController@show` | View request details |
| PATCH | `/requests/{ride_request}/cancel` | `RequestController@cancel` | Cancel pending request |
| GET | `/requests` | `RequestController@index` | List rider's requests |

#### Driver

| Method | URI | Controller | Notes |
|---|---|---|---|
| POST | `/driver/online` | `DriverController@goOnline` | Go online (requires ≥1 credit) |
| POST | `/driver/offline` | `DriverController@goOffline` | Go offline |
| GET | `/driver/queue-position` | `DriverController@getQueuePosition` | FIFO queue position |
| GET | `/driver/current-request` | `DriverController@getCurrentRequest` | Currently dispatched request |
| PATCH | `/driver/settings` | `DriverController@updateSettings` | Update max pickup radius |
| POST | `/driver/location` | `DriverController@updateLocation` | GPS update (rate limit: 200/min) |
| GET | `/driver/statistics` | `DriverController@getStatistics` | Driver stats |

#### Driver KYC

| Method | URI | Controller | Notes |
|---|---|---|---|
| POST | `/driver/kyc/check-email` | `DriverKycController@checkEmailAvailability` | Check if student email is available |
| POST | `/driver/kyc/submit` | `DriverKycController@submitKyc` | Submit KYC application |
| POST | `/driver/kyc/send-code` | `DriverKycController@sendVerificationCode` | Send email verification code |
| POST | `/driver/kyc/verify-email` | `DriverKycController@verifyEmail` | Verify email with code |
| GET | `/driver/kyc/status` | `DriverKycController@getKycStatus` | Check KYC status |

#### Driver Credits

| Method | URI | Controller | Notes |
|---|---|---|---|
| GET | `/driver/credits/balance` | `CreditController@getBalance` | Current credit balance |
| GET | `/driver/credits/transactions` | `CreditController@getTransactions` | Credit transaction history |

#### Rides

| Method | URI | Controller | Notes |
|---|---|---|---|
| POST | `/rides/{rideRequest}/accept` | `RideController@accept` | Accept ride request |
| POST | `/rides/{rideRequest}/decline` | `RideController@decline` | Decline ride request |
| PATCH | `/rides/{ride}/status` | `RideController@updateStatus` | Update ride status |
| POST | `/rides/{ride}/rate` | `RideController@rate` | Rate completed ride |
| GET | `/rides/{ride}` | `RideController@show` | View ride details |
| GET | `/rides` | `RideController@index` | List user's rides |

#### Locations

| Method | URI | Controller | Notes |
|---|---|---|---|
| GET | `/locations` | Closure | List active locations |

### Admin Routes (`/api/admin/`) — Rate limit: 100/min

Protected by `auth:sanctum` + `admin` middleware.

#### Driver Management

| Method | URI | Controller |
|---|---|---|
| GET | `/drivers` | `AdminController@listDrivers` |
| GET | `/drivers/{id}` | `AdminController@getDriver` |
| POST | `/drivers/{id}/suspend` | `AdminController@suspendDriver` |
| POST | `/drivers/{id}/kyc/approve` | `AdminController@approveKyc` |
| POST | `/drivers/{id}/kyc/reject` | `AdminController@rejectKyc` |
| POST | `/drivers/{id}/credits/grant` | `AdminController@grantCredits` |
| POST | `/drivers/{id}/credits/deduct` | `AdminController@deductCredits` |
| GET | `/drivers/{id}/document` | `AdminController@getDriverDocument` |

#### Rider Management

| Method | URI | Controller |
|---|---|---|
| GET | `/riders` | `AdminController@listRiders` |
| GET | `/riders/{id}` | `AdminController@getRider` |
| POST | `/riders/{id}/suspend` | `AdminController@suspendRider` |

#### Analytics

| Method | URI | Controller |
|---|---|---|
| GET | `/analytics/overview` | `AdminController@getOverview` |
| GET | `/analytics/rides` | `AdminController@getRideAnalytics` |
| GET | `/analytics/popular-routes` | `AdminController@getPopularRoutes` |
| GET | `/analytics/driver-performance` | `AdminController@getDriverPerformance` |

#### Monitoring

| Method | URI | Controller |
|---|---|---|
| GET | `/monitoring/active-rides` | `AdminController@getActiveRides` |
| GET | `/monitoring/online-drivers` | `AdminController@getOnlineDrivers` |
| GET | `/monitoring/pending-requests` | `AdminController@getPendingRequests` |

#### Ride Management (Admin Override)

| Method | URI | Controller |
|---|---|---|
| GET | `/rides/stuck` | `AdminController@listStuckRides` |
| GET | `/rides/{ride}` | `AdminController@getRide` |
| POST | `/rides/{ride}/force-status` | `AdminController@forceUpdateStatus` |
| DELETE | `/monitoring/requests/{id}` | `AdminController@cancelRequest` |
| POST | `/monitoring/rides/{id}/cancel` | `AdminController@cancelRide` |
| POST | `/monitoring/rides/{id}/complete` | `AdminController@completeRide` |

#### Audit

| Method | URI | Controller |
|---|---|---|
| GET | `/audit-logs` | `AdminController@getAuditLogs` |

### API Response Format

All API responses follow:

```json
{
    "success": true,
    "data": { ... },
    "message": "Optional message"
}
```

Error responses:

```json
{
    "success": false,
    "message": "Human readable error",
    "error": "Technical details (optional)",
    "errors": { "field": ["Validation errors"] }
}
```

---

## 6. Backend Services

### `RideService`

Core ride lifecycle management. All critical operations use `lockForUpdate()` for row-level locking.

- `createRideRequest()` — creates request, calculates fare estimate, caches route geometry, dispatches matching.
- `acceptRideRequest()` — creates Ride, deducts credit (atomic), broadcasts match event.
- `declineRideRequest()` — records decline, dispatches to next driver.
- `startRide()` / `completeRide()` — status transitions with broadcasting.
- `cancelRide()` / `cancelRequest()` — cancellation logic.
- `calculateRideEstimates()` — fare calculation using cached routes.
- `findBestDriver()` — PostGIS proximity query (pre-FIFO legacy method).

### `MatchingQueueService`

FIFO matching queue logic.

- `findTopDriver()` — finds the longest-waiting eligible driver within radius.
- `dispatchToDriver()` — sends request to specific driver via WebSocket + FCM.
- `rejoinAfterRide()` — re-inserts driver into queue after ride completion.
- `handleDecline()` — records decline, checks cooldown threshold, dispatches to next.
- `clearCooldowns()` — scheduled cleanup of expired cooldowns.

### `CreditService`

Driver credit operations (all use `lockForUpdate()` for atomicity).

- `getBalance()` / `canAcceptRide()` / `canGoOnline()` — balance checks.
- `deductCredit()` — deducts 1 credit within existing DB transaction.
- `addCredits()` — admin grant (wraps in own transaction).
- `adminDeductCredits()` — admin deduction (throws on insufficient balance).
- `getTransactions()` — transaction history.

### `NotificationService`

FCM push notifications via `kreait/firebase-php`.

- `sendToDriver()` — sends notification to driver's FCM token.
- `sendRideRequestNotification()` — new ride request alert.
- `sendKycApprovedToDriver()` / `sendKycRejectedToDriver()` — KYC decision notifications.
- Gracefully handles null/invalid FCM tokens.

### `MapboxService`

Mapbox API integration.

- `getDirections()` — Directions API (`driving-traffic` profile), returns distance, duration, GeoJSON geometry.
- Haversine fallback on API failure.
- 10-second timeout.

### `RouteCacheService`

Route caching layer (cache-aside pattern).

- `getOrFetchRoute()` — checks DB cache first (7-day TTL), fetches from Mapbox on miss.
- `cacheRoute()` / `refreshRoute()` — store/update cached routes.
- `cleanupStaleRoutes()` — delete entries older than 30 days (scheduled daily at 03:00).
- `warmCache()` — preload popular routes.
- `getCacheStats()` / `getPopularRoutes()` — analytics.

### `PlaceSearchService`

Mapbox Search Box API integration.

- Searches local `locations` table first.
- Falls back to Mapbox Search Box API if < 3 local results.
- Uses session tokens for billing efficiency.
- Bbox-scoped to campus area.
- Results from Mapbox are cached back into `locations` table.

### `FirebaseAuthService`

Firebase token verification.

- `verifyIdToken()` — verifies Firebase JWT using `kreait/firebase-php`.
- `findOrCreateUser()` — creates user record from Firebase profile.

### `KycVerificationService`

Driver KYC flow.

- `checkEmailAvailability()` — verifies student email isn't already used.
- `sendVerificationCode()` — sends 6-digit code to student email.
- `verifyEmail()` — validates code against `verification_codes` table.
- `submitKyc()` — processes KTM upload and vehicle details.

### `LocationService`

Distance and location calculations.

- `getDrivingDetails()` — get distance/duration (with route caching support).
- `calculateHaversine()` — straight-line distance fallback.

### `QueueService` (Legacy)

Original beacon-based queue management. Deprecated but still in codebase. Do NOT confuse with `MatchingQueueService`.

### `MatchingService` (Legacy)

Original matching algorithm. Superseded by `MatchingQueueService`.

---

## 7. Real-Time System (WebSocket + FCM)

### WebSocket Server

Laravel Reverb runs on port 8080, speaking the Pusher protocol. Mobile apps connect via `pusher_channels_flutter`.

### Channel Authorization (`routes/channels.php`)

| Channel | Auth Rule |
|---|---|
| `private-ride.{rideId}` | User must be rider or driver on that ride |
| `private-user.{userId}` | User must own that user ID |
| `private-driver.{driverId}` | User must own that driver ID |
| `beacon.{beaconId}` | Public (aggregate stats only) |

### Broadcast Events

| Event Class | Channel(s) | Trigger |
|---|---|---|
| `RideStatusUpdated` | `ride.{id}`, `user.{rider_id}`, `user.{driver_id}` | Ride status change |
| `DriverLocationUpdated` | `driver.{id}`, `ride.{id}` (if active) | Driver GPS update |
| `QueuePositionChanged` | `user.{driver_id}`, `driver.{driver_id}`, `beacon.{id}` | Legacy queue position change |
| `MatchingQueuePositionChanged` | `driver.{driver_id}` | FIFO queue position update |
| `RideRequestMatched` | `user.{rider_id}`, `user.{driver_id}`, `ride.{id}` | Ride matched |
| `DriverOnlineStatusChanged` | `user.{driver_id}`, `driver.{driver_id}` | Online/offline toggle |
| `NewRideRequest` | `driver.{driver_id}` | New request dispatched to driver |
| `RideRequestCancelled` | `user.{rider_id}` | Request cancelled |
| `RideNoDriversAvailable` | `user.{rider_id}` | No drivers found |
| `RideSearchResumed` | `user.{rider_id}` | Search re-attempted |
| `DriverCreditsUpdated` | `driver.{driver_id}` | Credit balance changed |
| `DriverKycStatusChanged` | `driver.{driver_id}` | KYC approved/rejected |
| `UserAccountStatusChanged` | `user.{user_id}` | Account suspended/unsuspended |
| `SessionReplaced` | `user.{user_id}` | Login from another device |

### FCM Push Notifications

FCM is used as a **fallback** when the driver doesn't have an active WebSocket connection (app in background or closed). The `NotificationService` sends FCM pushes for:

- New ride request dispatched to driver.
- KYC approval/rejection.
- Account status changes.

FCM tokens are stored on `users.fcm_token` and updated via `POST /auth/fcm-token`.

### Dual Delivery Strategy

For ride requests, both WebSocket AND FCM are sent:
- WebSocket provides instant in-app updates.
- FCM wakes the app if it's in the background.

---

## 8. Matching Algorithm (FIFO)

### `MatchingQueueService::findTopDriver()`

```
Input: RideRequest with pickup location

1. Query driver_profiles WHERE:
   - went_online_at IS NOT NULL (online)
   - queue_joined_at IS NOT NULL (in queue)
   - current ride IS NULL (not busy)
   - queue_cooldown_until IS NULL OR < NOW() (not in cooldown)

2. Filter by proximity:
   - ST_Distance(current_location::geography, pickup_point::geography) <= max_pickup_radius_km * 1000

3. Order by queue_joined_at ASC (FIFO — longest waiting first)

4. Take first result

5. If found:
   - Set ride_request.current_driver_id = driver.id
   - Dispatch WebSocket event (NewRideRequest) to driver.{id}
   - Send FCM push to driver
   - Dispatch HandleRequestTimeout job (35s delay)

6. If not found:
   - Broadcast RideNoDriversAvailable to rider
   - Request stays pending for retry
```

### Decline Flow

```
Driver declines or 35s timeout expires:

1. Increment driver.decline_count
2. If decline_count >= 3 within decline_window:
   - Set queue_cooldown_until = NOW() + cooldown_duration
3. Clear ride_request.current_driver_id
4. Re-run findTopDriver() (skip previously declined drivers)
5. If no more eligible drivers → broadcast RideNoDriversAvailable
```

---

## 9. Route Caching Architecture

```
POST /api/v1/requests (create ride request)
  └─ RideService::createRideRequest()
       └─ calculateRideEstimates(pickupId, destId)
            └─ RouteCacheService::getOrFetchRoute()
                 ├─ CACHE HIT (< 7 days old)  →  return from DB (2ms)
                 └─ CACHE MISS  →  MapboxService::getDirections() (driving-traffic)
                                    →  store in route_caches table
                                    →  return (4000ms)
       └─ RideRequest::create(route_geometry: GeoJSON)   ← stored once

GET /api/v1/rides/{id}  or  GET /api/v1/session/resume
  └─ RideResource (eager-loads rideRequest)
       └─ route_geometry → mobile parses as List<LatLng>  ← no Mapbox call

Mobile rider/driver screen:
  └─ ride.routeCoordinates != null  →  draw polyline directly
  └─ ride.routeCoordinates == null  →  fallback to MapboxDirectionsService
```

### Key Metrics

| Metric | Value |
|---|---|
| Cache hit rate | 75–90%+ |
| Cache HIT response time | ~2ms |
| Cache MISS response time | ~4000ms |
| TTL | 7 days |
| Cleanup | 30-day-old entries deleted daily at 03:00 |
| API cost reduction | 80–90% |
| Directions profile | `driving-traffic` (live traffic) |

### Adaptive Driver Location Updates

Driver GPS update interval adapts to speed:

| Speed | Interval |
|---|---|
| > 15 km/h | 5 seconds |
| 2–15 km/h | 10 seconds |
| < 2 km/h | 30 seconds |

---

## 10. Mobile Architecture

### Flutter Flavors

| Flavor | Package Name | Entry Point | Primary Color |
|---|---|---|---|
| Rider | `com.anjem.rider` | `lib/main_rider.dart` | Blue |
| Driver | `com.anjem.driver` | `lib/main_driver.dart` | Green |

Both flavors share the `core/` module and have flavor-specific `rider/` and `driver/` directories.

### Key Providers (Riverpod)

| Provider | Type | Purpose |
|---|---|---|
| `authProvider` | StateNotifier | Auth state (user, isAuthenticated, isLoading) |
| `driverStatusProvider` | StateNotifier | Online/offline, queue position, pickup radius |
| `rideRequestProvider` | StateNotifier | Current ride request state, cooldown |
| `driverIncomingRequestProvider` | — | Currently dispatched incoming ride request |
| `creditsProvider` | StateNotifier | Credit balance and transaction history |
| `apiServiceProvider` | Provider | Singleton API service (Dio-based) |
| `webSocketServiceProvider` | Provider | WebSocket connection manager |

### Key Services (Mobile)

| Service | File | Purpose |
|---|---|---|
| `ApiService` | `core/services/api/api_service.dart` | Dio HTTP client, token management, interceptors |
| `AuthService` | `core/services/auth/auth_service.dart` | Google Sign-In → Firebase → Sanctum flow |
| `WebSocketService` | `core/services/websocket/websocket_service.dart` | Pusher client for Laravel Reverb |
| `LocationService` | `core/services/location/location_service.dart` | GPS permissions, tracking, distance calc |
| `CreditService` | `core/services/credit_service.dart` | Credit balance and transaction API |
| `NotificationService` | `core/services/notification_service.dart` | FCM setup and handling |

### Key Screens

**Rider:**
- `LocationSelectionScreen` — Mapbox place search, pickup/destination selection
- `RideDetailsScreen` — Route preview, fare estimate, confirm request
- `WaitingScreen` — Waiting for driver match
- `RiderActiveRideScreen` — Live tracking during ride
- `RatingScreen` — Post-ride rating

**Driver:**
- `DriverHomeScreen` — Online/offline toggle, credit balance, earnings
- `KycScreen` — KYC submission flow
- `RideRequestScreen` — Incoming request with countdown timer
- `ActiveRideScreen` — Navigation, status updates, complete ride
- `CreditsScreen` — Credit balance and transaction history

### Dependencies (Key Packages)

```yaml
# Core
flutter_riverpod          # State management
dio                       # HTTP client
flutter_secure_storage    # Secure token storage
go_router                 # Navigation/routing

# Firebase
firebase_core
firebase_auth
firebase_messaging
google_sign_in

# Maps
mapbox_maps_flutter       # Mapbox SDK
geolocator                # GPS

# WebSocket
pusher_channels_flutter   # Pusher protocol (Reverb compatible)

# UI
flutter_local_notifications
```

---

## 11. Admin Panel (Filament 3)

### URL & Auth

- **URL**: `/admin` (login page: `/admin/login`)
- **Auth**: Session-based Laravel auth (not Sanctum)
- **Access gate**: `User::canAccessPanel()` → `role === 'admin' && is_active`

### Resources

| Resource | Model | Nav Group | Key Actions |
|---|---|---|---|
| `DriverResource` | User (with driverProfile) | Users | Grant/deduct credits, suspend/unsuspend |
| `KycResource` | User (with driverProfile) | Users | Approve/reject KYC with KTM photo review |
| `RiderResource` | User | Users | Suspend/unsuspend |
| `RideResource` | Ride | Rides | Force-complete, force-cancel |
| `AuditLogResource` | AdminAuditLog | System | Read-only, searchable |

### Pages

| Page | Nav Group | Purpose |
|---|---|---|
| `Dashboard` | — | KPI stats, daily rides chart, KYC pending count |
| `LiveMonitoringPage` | Rides | Real-time pending requests, active rides, online drivers (10–15s poll) |

### Widgets

| Widget | Type | Data |
|---|---|---|
| `StatsOverviewWidget` | Stats cards | Total users, online drivers, active rides, 30-day revenue |
| `DailyRidesChartWidget` | Line chart | Completed rides per day (30 days) |
| `KycPendingWidget` | Stat card | Pending KYC count (links to KYC resource) |

### Audit Action Types

| Action Type | Triggered By |
|---|---|
| `credit_grant` | Grant credits to driver |
| `credit_deduct` | Deduct credits from driver |
| `driver_suspend` | Suspend driver account |
| `driver_unsuspend` | Unsuspend driver account |
| `rider_suspend` | Suspend rider account |
| `rider_unsuspend` | Unsuspend rider account |
| `kyc_approve` | Approve driver KYC |
| `kyc_reject` | Reject driver KYC |
| `ride_force_complete` | Force-complete a ride |
| `ride_cancel` | Force-cancel a ride |
| `ride_request_cancel` | Cancel a pending request |
| `force_ride_status` | Generic status override (legacy) |

---

## 12. Scheduled Tasks

Defined in `app/Console/Kernel.php`:

| Schedule | Task | Notes |
|---|---|---|
| Daily 03:00 | `RouteCacheService::cleanupStaleRoutes(30)` | Delete route cache entries > 30 days old |
| Every minute | Stale driver cleanup | Kick offline drivers who've been idle |
| Every minute | Expired request cleanup | Mark expired pending requests |

---

## 13. Queued Jobs

| Job | Queue | Behavior |
|---|---|---|
| `HandleRequestTimeout` | default | Dispatched with 35s delay. Re-runs matching if request still pending and driver hasn't responded. |
| `ExpireRideRequest` | default | Dispatched with delay matching `expires_at`. Marks request as expired if still pending. |

Queue is Redis-backed (`QUEUE_CONNECTION=redis`). Run with `php artisan queue:work`.

---

## 14. Non-Functional Requirements

### Performance

| Metric | Target |
|---|---|
| API response p95 | ≤ 300ms |
| Cold start (mid-range Android) | ≤ 2.5s |
| APK size | ≤ 30MB |
| Battery — driver background | ≤ 3%/hr |
| Battery — rider foreground | ≤ 1%/10 min |
| Push delivery p95 | ≤ 4s |
| WebSocket event delivery | < 50ms (local) |
| Route cache HIT response | ~2ms |

### Rate Limits

| Endpoint Group | Limit |
|---|---|
| Authentication | 10 requests/minute |
| Location updates | 200 requests/minute |
| Place search | 60 requests/minute |
| General protected | 100 requests/minute |
| Admin | 100 requests/minute |

### Security

- Firebase JWT verification on every auth request.
- Sanctum token 24hr expiry with role-based abilities.
- `lockForUpdate()` row-level locking on all critical ride operations.
- SQL injection prevention (Eloquent parameterized queries).
- XSS protection (Laravel's built-in escaping).
- CSRF protection on admin panel (session-based).
- Rate limiting on all endpoint groups.
- No API keys in mobile app binary (`--dart-define` at build time).
- Dart obfuscation in release builds.
- Input validation via Form Request classes.
- Coordinate validation (lat: -90 to 90, lng: -180 to 180).

---

## 15. Testing

### Backend Test Suite

```bash
cd backend && php artisan test
```

Test database: `anjemme_test` (PostgreSQL with PostGIS). Configured via `.env.testing`.

| Test Class | Count | Coverage |
|---|---|---|
| AuthControllerTest | 6 | Firebase auth, unauthorized access |
| RideControllerTest | 4 | Cross-user protection, permissions |
| DriverControllerTest | 11 | Queue conflicts, location validation |
| RequestControllerTest | 11 | Duplicate prevention, validation |
| TokenPermissionsTest | 16 | SQL injection, XSS, token security |
| RealTimeEventsTest | 5 | All broadcast event types |
| **Total** | **48+** | Core flows + security |

### Mobile

```bash
cd mobile && flutter test
cd mobile && dart analyze
cd mobile && dart format --set-exit-if-changed .
```

### Static Analysis

```bash
cd backend && ./vendor/bin/phpstan analyse
```

### Test Database Setup

```sql
CREATE DATABASE anjemme_test;
\c anjemme_test
CREATE EXTENSION postgis;
```

```bash
php artisan migrate --env=testing
```

---

## 16. Development Environment

### Required Processes (All Must Run)

```bash
# Terminal 1: Laravel API server
php artisan serve

# Terminal 2: WebSocket server
php artisan reverb:start

# Terminal 3: Queue worker
php artisan queue:work

# Terminal 4: Scheduled task runner
php artisan schedule:work
```

Redis must be running. PostgreSQL must be running with PostGIS extension.

### Mobile

```bash
# Rider app
flutter run --flavor rider -t lib/main_rider.dart \
  --dart-define=MAPBOX_ACCESS_TOKEN=pk.eyJ1...

# Driver app
flutter run --flavor driver -t lib/main_driver.dart \
  --dart-define=MAPBOX_ACCESS_TOKEN=pk.eyJ1...
```

### Environment Variables (Backend `.env`)

Key variables:

```env
APP_URL=http://localhost:8000
DB_CONNECTION=pgsql
DB_DATABASE=anjemme
QUEUE_CONNECTION=redis
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=localhost
REVERB_PORT=8080
MAPBOX_ACCESS_TOKEN=pk.eyJ1...
MAPBOX_SEARCH_BBOX=106.80,-6.39,106.86,-6.33
FIREBASE_CREDENTIALS=...   # path to Firebase service account JSON
```

---

## 17. CI/CD

### GitHub Actions

- **Laravel CI**: Runs on push/PR. Sets up PHP, Postgres, Redis. Runs `php artisan test` and `phpstan analyse`.
- **Flutter CI**: Runs on push/PR. Runs `dart analyze`, `dart format --set-exit-if-changed .`, `flutter test`.

### Android Release

- Signed with Android keystore.
- Published to Play Console (Internal → Closed testing track).
- Build: `flutter build apk --flavor rider --release --dart-define=...`

---

## 18. Third-Party API Usage & Costs

| Service | Free Tier | Expected Usage (MVP) | Status |
|---|---|---|---|
| **Mapbox Directions** | 100k requests/month | ~6k–12k (with caching) | Well within free tier |
| **Mapbox Search Box** | Free with session tokens | ~500/day | Free |
| **Mapbox Maps SDK** | Free | Unlimited map loads | Free |
| **Firebase Auth** | Free (Spark) | Unlimited | Free |
| **Firebase Cloud Messaging** | Free | Unlimited | Free |
| **Firebase Analytics** | Free (GA4 App stream) | Unlimited | Free |
| **DigitalOcean** | $200 GitHub Student Pack | Compute + DB + Redis | Free for ~12 months |

**Budget target**: $0/month infrastructure using student credits and free tiers.

---

## 19. Key Architectural Decisions

### 1. Standard Ride-Sharing Over Beacon Queues (Oct 2025)

**Decision**: Migrated from beacon-based physical queuing to standard ride-sharing model.
**Rationale**: Beacon system added complexity, limited flexibility, poor UX compared to industry standard.
**Impact**: `driver_queue` table deprecated. Drivers no longer need to physically go to beacon locations.

### 2. FIFO Matching Queue (Feb 2026)

**Decision**: Implemented FIFO queue via `queue_joined_at` on `driver_profiles` instead of score-based matching.
**Rationale**: Simpler, fairer for small driver pool. Score-based matching deferred until sufficient data.
**Impact**: New fields on `driver_profiles` (queue_joined_at, decline_count, cooldown_until).

### 3. Filament 3 Over Custom Admin Panel (Dec 2025)

**Decision**: Replaced custom admin HTML dashboard with Filament 3.
**Rationale**: Full CRUD, built-in auth, Livewire reactivity, audit logging — all out of the box.
**Impact**: Session-based admin auth is separate from Sanctum API auth.

### 4. Route Geometry on ride_requests (Mar 2026)

**Decision**: Store route GeoJSON in `ride_requests.route_geometry` at creation time.
**Rationale**: Eliminates duplicate Mapbox calls — route is computed once and served to both rider and driver from the database.

### 5. Mapbox Over Google Maps (Oct 2025)

**Decision**: Use Mapbox for all mapping needs.
**Rationale**: More generous free tier, better Flutter SDK, consistent provider for search + directions + rendering.

### 6. Firebase Auth + Sanctum (Not Firebase-Only)

**Decision**: Firebase handles identity (Google OAuth), but backend issues its own Sanctum tokens with role-based abilities.
**Rationale**: Server-side ability checks, token revocation, role management — all handled by Laravel, not Firebase.

---

_End of Technical Specification._
