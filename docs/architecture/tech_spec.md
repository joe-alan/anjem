# Technical Specification

## Architecture Overview

**Last Updated**: November 30, 2025

- **Backend**: Laravel 11 API with Firebase Auth + OAuth + Sanctum
- **Mobile**: Flutter with Riverpod state management + Firebase Auth SDK
- **Database**: PostgreSQL 15 with PostGIS 3.6 + Redis 7 cache
- **Authentication**: Firebase Authentication + Google OAuth + Laravel Sanctum tokens
- **Real-time**: Laravel Reverb (WebSockets) + HTTP polling fallback
- **Maps & Routing**: Mapbox Platform (Directions API, Search API, Maps SDK)
- **Route Optimization**: Custom caching layer (80-90% API cost reduction)
- **Admin**: Role-based dashboard with 14 REST endpoints

## Data Models

**Last Updated**: November 30, 2025

```sql
-- Core entities (verified against PostgreSQL schema)

-- Users (unified model for riders, drivers, and admins)
users(
  id, firebase_uid, name, email, password,
  role ENUM('rider', 'driver', 'both', 'admin'), -- Consolidated from user_type
  phone_number, phone_verified_at, email_verified_at,
  fcm_token, profile_picture, emergency_contact, preferred_payment,
  rider_rating_avg, total_rides_taken, is_active, last_active_at,
  created_at, updated_at, deleted_at
)

-- Driver Profiles (driver-specific information)
driver_profiles(
  id, user_id,
  ktm_url, student_email, student_id, student_name, email_verified_at,
  vehicle_type, vehicle_plate, vehicle_color,
  current_location GEOGRAPHY(POINT, 4326), last_location_update,
  is_verified, went_online_at,
  rating_average, rating_count, driver_rating_avg,
  reliability_score, experience_points, on_time_rate,
  total_earnings, total_rides_given,
  created_at, updated_at
)

-- Locations (pickup/destination points with PostGIS)
locations(
  id, name, address, coordinates GEOMETRY(POINT, 4326),
  location_type ENUM('beacon', 'p2p'), campus_area,
  usage_count, is_active,
  created_at, updated_at
)

-- Ride Requests (before matching)
ride_requests(
  id, rider_id, pickup_location_id, destination_location_id,
  status ENUM('pending', 'matched', 'in_progress', 'completed', 'cancelled', 'expired'),
  passenger_count, special_requests JSON,
  estimated_fare_rp, estimated_distance_km, estimated_duration_minutes,
  expires_at, matched_at,
  created_at, updated_at
)

-- Rides (matched and completed)
rides(
  id, ride_request_id, rider_id, driver_id,
  pickup_location_id, destination_location_id,
  status ENUM('matched', 'accepted', 'driver_arrived', 'in_progress', 'completed', 'cancelled'),
  passenger_count, special_requests JSON, driver_notes,
  estimated_fare_rp, actual_fare_rp,
  actual_distance_km, actual_duration_minutes,
  driver_accepted_at, arrived_at, pickup_time, dropoff_time,
  created_at, updated_at
)

-- Ratings (bidirectional rider ↔ driver)
ratings(
  id, ride_id, rater_id, rated_id,
  rating_type ENUM('rider_to_driver', 'driver_to_rider'),
  score INT CHECK(score BETWEEN 1 AND 5),
  tags JSON, feedback TEXT,
  created_at
)

-- Route Cache (Mapbox API cost optimization - 80-90% savings)
route_caches(
  id, origin_lat, origin_lng, dest_lat, dest_lng,
  distance_meters, duration_seconds, route_geometry TEXT,
  hit_count, last_used_at,
  created_at, updated_at
)

-- Driver Sessions (analytics)
driver_sessions(
  id, driver_id, went_online_at, went_offline_at,
  total_duration_minutes, rides_completed, earnings_rp,
  created_at, updated_at
)

-- Note: OTP table removed - using Firebase Authentication
-- Note: Separate riders/drivers tables consolidated into users + driver_profiles
```

## Matching Algorithm

```
score = driver_rating + proximity_factor + availability_score - queue_wait_time
// Note: Will be implemented based on real usage patterns
```

## Performance Requirements

- API Response: p95 ≤ 300ms
- Cold Start: ≤ 2.5s (mid-range Android)
- APK Size: ≤ 30MB
- Battery: Driver ≤ 3%/hr, Rider ≤ 1%/10min

## Security Measures

- Firebase Authentication with email verification
- Google OAuth integration for social login
- Laravel Sanctum tokens with 24hr expiry
- Firebase JWT token verification on backend
- Dart obfuscation in release builds
- No API keys in mobile apps
- Input validation and sanitization
- Rate limiting on auth endpoints
