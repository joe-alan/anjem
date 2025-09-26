# Technical Specification

## Architecture Overview

- **Backend**: Laravel 10 API with Firebase Auth + OAuth + Sanctum
- **Mobile**: Flutter with Provider/Riverpod state management + Firebase Auth SDK
- **Database**: PostgreSQL 14+ with Redis cache
- **Authentication**: Firebase Authentication + Google OAuth + Laravel Sanctum tokens
- **Real-time**: WebSockets (primary) + HTTP polling (fallback)

## Data Models

```sql
-- Core entities only, full schema in migrations
users(id, firebase_uid, name, email, phone, role, profile_picture, created_at, updated_at)
rides(id, rider_id, driver_id, pickup_location, destination_location, status, created_at, updated_at)
riders(id, user_id, preferences, emergency_contact, created_at, updated_at)
drivers(id, user_id, license_number, vehicle_info, status, location, created_at, updated_at)
-- OTP table removed in favor of Firebase Auth
-- Additional tables will be added as needed
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
