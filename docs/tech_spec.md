````markdown
# Technical Specification

## Architecture Overview

- **Backend**: Laravel 10 API with Reverb WebSockets
- **Mobile**: Flutter with BLoC/Riverpod state management
- **Database**: PostgreSQL 15 with Redis cache
- **Real-time**: WebSockets (primary) + HTTP polling (fallback)

## Data Models

```sql
-- Core entities only, full schema in migrations
riders(id, email, name, fcm_token, created_at)
drivers(id, email, name, ktm_url, rating_avg, reliability_score)
requests(id, rider_id, beacon_in, beacon_out, status, matched_driver_id)
rides(id, request_id, driver_id, distance_m, fare_rp, started_at, completed_at)
```
````

## Matching Algorithm

```
score = reliability_score + on_time_rate + experience_points - 0.5*queue_age
// Note: Add minimum score threshold to prevent negative values
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

```

```
