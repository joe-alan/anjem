# Current Database Schema Documentation

**Last Updated**: November 21, 2025
**Database**: PostgreSQL 16 with PostGIS 3.6
**Status**: ✅ All migrations applied, schema verified

---

## Overview

This document reflects the **actual current state** of the database after all migrations have been applied. The migrations evolved through multiple "fix" migrations that corrected the initial schema to match business requirements.

---

## Schema Evolution Notes

The database schema went through several refinement phases:

1. **Initial Schema** (2025-09-28): Original design with beacon-based queue system
2. **Mobile Integration** (2025-09-28): Enhanced for Flutter app requirements
3. **Schema Fixes** (2025-09-28): Aligned with actual model usage patterns
4. **KYC Enhancement** (2025-10-03): Added driver verification fields
5. **Real-time Features** (2025-11-14 - 2025-11-18): Driver location tracking, ride status updates
6. **Admin Features** (2025-11-20 - 2025-11-21): Rating columns, role-based access, route caching

---

## Core Tables

### 1. users

**Purpose**: Unified user model for riders, drivers, and admins

```sql
Column             | Type                        | Nullable | Default
-------------------+-----------------------------+----------+------------------
id                 | bigint                      | NOT NULL | auto
name               | varchar(255)                | NOT NULL |
email              | varchar(255)                | NOT NULL | UNIQUE
password           | varchar(255)                | NULL     |
email_verified_at  | timestamp                   | NULL     |
firebase_uid       | varchar(255)                | NULL     | UNIQUE
phone_number       | varchar(255)                | NULL     |
phone_verified_at  | timestamp                   | NULL     |
fcm_token          | varchar(500)                | NULL     |
profile_picture    | varchar(500)                | NULL     |
emergency_contact  | varchar(255)                | NULL     |
preferred_payment  | varchar(100)                | NOT NULL | 'cash'
rider_rating_avg   | decimal(3,2)                | NOT NULL | 0.00
total_rides_taken  | integer                     | NOT NULL | 0
is_active          | boolean                     | NOT NULL | true
last_active_at     | timestamp                   | NULL     |
user_type          | varchar(255)                | NOT NULL | 'rider'
role               | varchar(255)                | NOT NULL | 'rider'
remember_token     | varchar(100)                | NULL     |
deleted_at         | timestamp (soft delete)     | NULL     |
created_at         | timestamp                   | NULL     |
updated_at         | timestamp                   | NULL     |
```

**Indexes**:
- PRIMARY KEY: `id`
- UNIQUE: `email`, `firebase_uid`
- INDEX: `(firebase_uid, is_active)`

**Key Fields**:
- `role`: 'admin' | 'rider' | 'driver' | 'both'
- `user_type`: Legacy field, replaced by `role`
- `is_active`: Controls account suspension (false = suspended)

---

### 2. driver_profiles

**Purpose**: Driver-specific information and KYC verification

```sql
Column                | Type                        | Nullable | Default
----------------------+-----------------------------+----------+------------------
id                    | bigint                      | NOT NULL | auto
user_id               | bigint                      | NOT NULL | FK users.id UNIQUE
student_email         | varchar(255)                | NULL     | UNIQUE
student_id            | varchar(50)                 | NULL     | UNIQUE
student_name          | varchar(255)                | NULL     |
ktm_url               | varchar(500)                | NULL     |
vehicle_type          | varchar(50)                 | NOT NULL | 'motorcycle'
vehicle_plate         | varchar(20)                 | NULL     | UNIQUE
vehicle_color         | varchar(50)                 | NULL     |
rating_average        | decimal(3,2)                | NOT NULL | 5.00
rating_count          | integer                     | NOT NULL | 0
driver_rating_avg     | decimal(3,2)                | NOT NULL | 0.00
reliability_score     | decimal(5,2)                | NOT NULL | 0.00
experience_points     | integer                     | NOT NULL | 0
on_time_rate          | decimal(3,2)                | NOT NULL | 0.00
total_earnings        | decimal(12,2)               | NOT NULL | 0.00
total_rides_given     | integer                     | NOT NULL | 0
current_location      | geography(Geometry,4326)    | NULL     |
last_location_update  | timestamp                   | NULL     |
is_verified           | boolean                     | NOT NULL | false
email_verified_at     | timestamp                   | NULL     |
went_online_at        | timestamp                   | NULL     |
created_at            | timestamp                   | NULL     |
updated_at            | timestamp                   | NULL     |
```

**Indexes**:
- PRIMARY KEY: `id`
- UNIQUE: `user_id`, `student_email`, `student_id`, `vehicle_plate`

**Key Fields**:
- `current_location`: PostGIS GEOGRAPHY type (better for distance calculations)
- `went_online_at`: NULL = offline, timestamp = online since
- `is_verified`: KYC approval status
- `email_verified_at`: Student email verification timestamp
- `rating_average` + `rating_count`: Real-time rating cache

**KYC Fields**:
- `student_email`: Must be from whitelisted domains (e.g., @umm.ac.id)
- `student_id`: Unique student ID number
- `ktm_url`: URL to uploaded student ID card image

---

### 3. locations

**Purpose**: Popular pickup/drop-off points (beacons) for autocomplete

```sql
Column             | Type                        | Nullable | Default
-------------------+-----------------------------+----------+------------------
id                 | bigint                      | NOT NULL | auto
name               | varchar(255)                | NOT NULL |
address            | text                        | NULL     |
coordinates        | geography(Point,4326)       | NOT NULL | PostGIS POINT
location_type      | varchar(50)                 | NOT NULL |
is_active          | boolean                     | NOT NULL | true
usage_count        | integer                     | NOT NULL | 0
created_at         | timestamp                   | NULL     |
updated_at         | timestamp                   | NULL     |
```

**Indexes**:
- PRIMARY KEY: `id`
- SPATIAL INDEX: `coordinates` (GiST)
- FULL-TEXT INDEX: `to_tsvector('indonesian', name || ' ' || COALESCE(address, ''))`

**Key Fields**:
- `coordinates`: PostGIS GEOGRAPHY for spatial queries
- `usage_count`: Tracks popularity, incremented on search/use
- Full-text search configured for Indonesian language

---

### 4. ride_requests

**Purpose**: Ride requests before driver matching

```sql
Column                      | Type                        | Nullable | Default
----------------------------+-----------------------------+----------+------------------
id                          | bigint                      | NOT NULL | auto
rider_id                    | bigint                      | NOT NULL | FK users.id
pickup_location_id          | bigint                      | NOT NULL | FK locations.id
destination_location_id     | bigint                      | NOT NULL | FK locations.id
status                      | varchar(255)                | NOT NULL | 'pending'
passenger_count             | integer                     | NOT NULL | 1
estimated_fare_rp           | decimal(10,2)               | NULL     |
estimated_distance_km       | decimal(8,2)                | NULL     |
estimated_duration_minutes  | integer                     | NULL     |
special_requests            | json                        | NULL     |
expires_at                  | timestamp                   | NULL     |
matched_at                  | timestamp                   | NULL     |
created_at                  | timestamp                   | NULL     |
updated_at                  | timestamp                   | NULL     |
```

**Indexes**:
- PRIMARY KEY: `id`
- INDEX: `(rider_id, status, created_at)`

**Status Values**: 'pending' | 'matched' | 'in_progress' | 'completed' | 'cancelled' | 'expired'

**Key Fields**:
- `special_requests`: JSON array, e.g., `["need_helmet", "have_luggage"]`
- `expires_at`: Auto-expire after 10 minutes if no match
- `matched_at`: Timestamp when driver accepted

**Removed Fields** (from original design):
- `matched_driver_id`: Moved to rides table
- `request_type`, `is_pooled`, `max_wait_minutes`: Beacon queue removed
- `pickup_address`, `dropoff_address`: Fetched from locations table

---

### 5. rides

**Purpose**: Actual matched rides in progress or completed

```sql
Column                      | Type                        | Nullable | Default
----------------------------+-----------------------------+----------+------------------
id                          | bigint                      | NOT NULL | auto
ride_request_id             | bigint                      | NOT NULL | FK ride_requests.id
driver_id                   | bigint                      | NOT NULL | FK users.id
rider_id                    | bigint                      | NOT NULL | FK users.id
pickup_location_id          | bigint                      | NULL     | FK locations.id
destination_location_id     | bigint                      | NULL     | FK locations.id
passenger_count             | integer                     | NOT NULL | 1
estimated_fare_rp           | integer                     | NULL     |
actual_fare_rp              | integer                     | NULL     |
actual_distance_km          | decimal(8,2)                | NULL     |
actual_duration_minutes     | integer                     | NULL     |
status                      | varchar(255)                | NOT NULL | 'matched'
driver_accepted_at          | timestamp                   | NULL     |
arrived_at                  | timestamp                   | NULL     |
pickup_time                 | timestamp                   | NULL     |
dropoff_time                | timestamp                   | NULL     |
special_requests            | json                        | NULL     |
driver_notes                | text                        | NULL     |
created_at                  | timestamp                   | NULL     |
updated_at                  | timestamp                   | NULL     |
```

**Indexes**:
- PRIMARY KEY: `id`

**Status Values**: 'matched' | 'accepted' | 'driver_arrived' | 'in_progress' | 'completed' | 'cancelled'

**Key Fields**:
- `status` lifecycle: matched → accepted → driver_arrived → in_progress → completed
- `driver_accepted_at`: When driver accepts request
- `arrived_at`: When driver arrives at pickup location
- `pickup_time`: When ride starts (rider gets in vehicle)
- `dropoff_time`: When ride completes (replaces `completed_at`)

**Removed Fields** (from original design):
- `assigned_at`, `started_at`, `completed_at`: Replaced with more granular timestamps
- `fare_rp`, `driver_earning_rp`: Replaced with `actual_fare_rp`
- `pickup_location`, `dropoff_location`: Geography columns replaced with location FK

---

### 6. ratings

**Purpose**: Ride ratings with flexible tagging system

```sql
Column       | Type                        | Nullable | Default
-------------+-----------------------------+----------+------------------
id           | bigint                      | NOT NULL | auto
ride_id      | bigint                      | NOT NULL | FK rides.id
rater_id     | bigint                      | NOT NULL | FK users.id
rated_id     | bigint                      | NOT NULL | FK users.id
rating_type  | varchar(255)                | NOT NULL |
score        | integer                     | NOT NULL | CHECK (1-5)
tags         | json                        | NULL     |
feedback     | text                        | NULL     |
created_at   | timestamp                   | NOT NULL | CURRENT_TIMESTAMP
```

**Indexes**:
- PRIMARY KEY: `id`

**Rating Types**: 'rider_to_driver' | 'driver_to_rider'

**Key Fields**:
- `score`: 1-5 star rating (validated by CHECK constraint)
- `tags`: JSON array, e.g., `["on-time", "friendly", "clean-bike"]`
- `rating_type`: Determines who is rating whom
- `rater_id`: User who gave the rating
- `rated_id`: User who received the rating (NOT `rated_user_id`)

---

### 7. driver_sessions

**Purpose**: Analytics tracking for driver active periods

```sql
Column        | Type                        | Nullable | Default
--------------+-----------------------------+----------+------------------
id            | bigint                      | NOT NULL | auto
driver_id     | bigint                      | NOT NULL | FK driver_profiles.id
went_online   | timestamp                   | NOT NULL |
went_offline  | timestamp                   | NULL     |
total_minutes | integer                     | NULL     |
created_at    | timestamp                   | NULL     |
updated_at    | timestamp                   | NULL     |
```

**Indexes**:
- PRIMARY KEY: `id`

**Key Fields**:
- `went_offline`: NULL = currently online
- `total_minutes`: Calculated on offline event

---

### 8. verification_codes

**Purpose**: Email OTP codes for driver KYC verification

```sql
Column     | Type                        | Nullable | Default
-----------+-----------------------------+----------+------------------
id         | bigint                      | NOT NULL | auto
email      | varchar(255)                | NOT NULL |
code       | varchar(6)                  | NOT NULL |
expires_at | timestamp                   | NOT NULL |
created_at | timestamp                   | NULL     |
updated_at | timestamp                   | NULL     |
```

**Indexes**:
- PRIMARY KEY: `id`
- INDEX: `(email, code, expires_at)`

**Key Fields**:
- `code`: 6-digit numeric OTP
- `expires_at`: 10-minute expiry from creation

---

### 9. route_cache

**Purpose**: Cache Mapbox Directions API responses (Phase 1 optimization)

```sql
Column            | Type                        | Nullable | Default
------------------+-----------------------------+----------+------------------
id                | bigint                      | NOT NULL | auto
pickup_lat        | decimal(10,8)               | NOT NULL |
pickup_lng        | decimal(11,8)               | NOT NULL |
destination_lat   | decimal(10,8)               | NOT NULL |
destination_lng   | decimal(11,8)               | NOT NULL |
distance_km       | decimal(8,2)                | NOT NULL |
duration_minutes  | integer                     | NOT NULL |
route_geometry    | jsonb                       | NOT NULL |
created_at        | timestamp                   | NULL     |
updated_at        | timestamp                   | NULL     |
cache_hits        | integer                     | NOT NULL | 0
last_used_at      | timestamp                   | NULL     |
```

**Indexes**:
- PRIMARY KEY: `id`
- UNIQUE: `(pickup_lat, pickup_lng, destination_lat, destination_lng)`
- INDEX: `(last_used_at, cache_hits)`

**Key Fields**:
- `route_geometry`: Full GeoJSON LineString from Mapbox (for route visualization)
- `cache_hits`: Tracks reuse count
- `last_used_at`: Updated on each cache hit
- Routes are NEVER auto-deleted (manual cleanup only)

**Performance Impact**:
- 80-90% API cost reduction
- 1446x faster response times
- 75% cache hit rate

---

## Authentication Tables

### 10. personal_access_tokens (Laravel Sanctum)

```sql
Column         | Type                        | Nullable | Default
---------------+-----------------------------+----------+------------------
id             | bigint                      | NOT NULL | auto
tokenable_type | varchar(255)                | NOT NULL |
tokenable_id   | bigint                      | NOT NULL |
name           | varchar(255)                | NOT NULL |
token          | varchar(64)                 | NOT NULL | UNIQUE
abilities      | text                        | NULL     |
last_used_at   | timestamp                   | NULL     |
expires_at     | timestamp                   | NULL     |
created_at     | timestamp                   | NULL     |
updated_at     | timestamp                   | NULL     |
```

**Indexes**:
- PRIMARY KEY: `id`
- UNIQUE: `token`
- INDEX: `(tokenable_type, tokenable_id)`

**Token Abilities**:
- Rider: `rider:request-ride`, `rider:cancel-ride`, `rider:rate-driver`
- Driver: `driver:go-online`, `driver:accept-ride`, `driver:complete-ride`
- Admin: All abilities including `admin:*`

---

### 11. password_reset_tokens

```sql
Column     | Type                        | Nullable | Default
-----------+-----------------------------+----------+------------------
email      | varchar(255)                | NOT NULL | PRIMARY KEY
token      | varchar(255)                | NOT NULL |
created_at | timestamp                   | NULL     |
```

---

## Deprecated/Removed Tables

### driver_queue

**Status**: Structure exists but NOT USED

Original beacon-based queue system was replaced with standard ride-hailing model (any driver can accept any nearby request). Table structure remains for potential future use.

---

## Important Schema Notes

### Column Name Corrections

These are the **CORRECT** column names in the actual database:

| Model Code May Use | Actual Database Column      |
|-------------------|-----------------------------|
| `rated_user_id`   | ✅ `rated_id`               |
| `rating`          | ✅ `score`                  |
| `type`            | ✅ `rating_type`            |
| `completed_at`    | ✅ `dropoff_time`           |
| `request_id`      | ✅ `ride_request_id`        |
| `dropoff_location_id` | ✅ `destination_location_id` |

### PostGIS Usage

**GEOGRAPHY vs GEOMETRY**:
- `driver_profiles.current_location`: GEOGRAPHY (used)
- `locations.coordinates`: GEOGRAPHY (used)
- Original migrations had GEOMETRY, corrected by PostgreSQL

**GEOGRAPHY** is preferred because:
- Returns distances in meters (not degrees)
- Handles earth curvature automatically
- Better for ride-sharing distance calculations

### Spatial Query Examples

```sql
-- Find drivers within 5km radius
SELECT * FROM driver_profiles
WHERE ST_DWithin(
  current_location,
  ST_MakePoint(106.8242, -6.3615)::geography,
  5000  -- meters
);

-- Calculate distance between two points
SELECT ST_Distance(
  ST_MakePoint(106.8242, -6.3615)::geography,
  ST_MakePoint(106.8350, -6.3700)::geography
) / 1000.0 AS distance_km;
```

---

## Migration History Summary

**Total Migrations**: 33 files

**Key Migration Phases**:

1. **Initial Schema** (2025-09-28):
   - Base tables: users, driver_profiles, locations, ride_requests, rides, ratings
   - Beacon-based queue system

2. **Mobile Integration** (2025-09-28):
   - Added `passenger_count`, `special_requests` to ride_requests
   - Added estimated duration/distance fields

3. **Schema Fixes** (2025-09-28):
   - Fixed foreign key references (driver_id → users, not driver_profiles)
   - Renamed `request_id` → `ride_request_id`
   - Renamed `dropoff_location_id` → `destination_location_id`
   - Replaced old timestamp fields with ride lifecycle timestamps
   - Updated status enums to match actual workflow

4. **KYC Enhancement** (2025-10-03):
   - Added `student_email`, `student_id`, `student_name` to driver_profiles
   - Added `email_verified_at` for email verification
   - Created `verification_codes` table

5. **Real-time Features** (2025-11-14 - 2025-11-18):
   - Added `last_location_update` to driver_profiles
   - Added `driver_arrived` status to rides
   - Added `arrived_at` timestamp to rides

6. **Admin Features** (2025-11-20 - 2025-11-21):
   - Added `rating_average`, `rating_count` to driver_profiles
   - Added unique constraints for KYC fields
   - Added `role` column to users (admin/rider/driver/both)
   - Created `route_cache` table for API optimization

---

## Testing & Verification

### Verify Schema Matches

```bash
# Check all table structures
PGPASSWORD=***REDACTED*** psql -h localhost -U jonathanalanohasiholan -d anjemme \
  -c "SELECT table_name FROM information_schema.tables WHERE table_schema='public' ORDER BY table_name;"

# Check specific column names
PGPASSWORD=***REDACTED*** psql -h localhost -U jonathanalanohasiholan -d anjemme \
  -c "\d ratings"
```

### Run Migrations from Scratch

⚠️ **WARNING**: Only use on development databases! This will DESTROY all data!

```bash
php artisan migrate:fresh --seed
```

---

## References

- **API Documentation**: `/docs/api/API_DOCUMENTATION.md`
- **Phase 1 Report**: `/docs/status/2025-11-21_API_COST_OPTIMIZATION_COMPLETE.md`
- **Phase 2 Report**: `/docs/status/2025-11-21_PHASE_2_ADMIN_DASHBOARD_COMPLETE.md`
- **Migrations Directory**: `/backend/database/migrations/`

---

**Status**: ✅ All migrations applied and verified
**Last Verified**: November 21, 2025
**Database**: `anjemme` (PostgreSQL 16 + PostGIS 3.6)
