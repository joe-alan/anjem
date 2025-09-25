# Technical Specification

## Architecture Overview

- **Backend**: Laravel 10 API with Sanctum authentication
- **Mobile**: Flutter with Provider/Riverpod state management
- **Database**: MySQL 8.0+ with Redis cache
- **Real-time**: WebSockets (primary) + HTTP polling (fallback)

## Data Models

```sql
-- Core entities only, full schema in migrations
users(id, phone, name, email, profile_picture, role, created_at, updated_at)
rides(id, rider_id, driver_id, pickup_location, destination_location, status, created_at, updated_at)
otp_codes(id, phone, code, expires_at, verified_at, created_at)
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

- OTP rate limit: 5/30min/IP
- JWT tokens with 24hr expiry
- Dart obfuscation in release builds
- No API keys in mobile apps
- Laravel Sanctum for API authentication
- Input validation and sanitization
