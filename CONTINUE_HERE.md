# Continue Here - Project Context Dump

**Last Updated**: December 5, 2025
**Current Branch**: `fix/test-database-isolation`
**Status**: Queue-Based Matching System Design Complete, Ready for Implementation

---

## Quick Context for AI Models

This is a **ride-hailing app** (like Grab/Gojek) for a university campus. Stack:
- **Backend**: Laravel 11 + PostgreSQL + Redis + Pusher (WebSocket)
- **Mobile**: Flutter (single codebase for rider/driver modes)
- **Maps**: Mapbox (directions, geocoding)

---

## 🎯 NEXT PRIORITY: Queue-Based Driver Matching System

### Design Overview

Replace the current "broadcast to all drivers" approach with a **FIFO queue system**:

```
Driver goes online → Joins queue (position based on join time)
                  → Longest waiting driver = highest priority
                  → Driver sees their queue number

Rider creates request → Server finds top eligible driver (within radius)
                     → Sends request to that driver only
                     → 30-second response window
```

### Queue Rules

| Event | What Happens |
|-------|--------------|
| **Driver goes online** | Joins queue at bottom, `queue_joined_at = now()` |
| **Driver goes offline** | Leaves queue, `queue_joined_at = NULL` |
| **Driver accepts ride** | Leaves queue, completes ride |
| **Ride completed** | Auto-rejoins queue at bottom (fresh timestamp) |
| **Driver declines** | Progressive penalty (details TBD), request passes to next driver |
| **Driver timeout (30s)** | Same as decline |
| **All drivers decline** | Request expires, rider notified |

### Request Flow

```
1. Rider creates request
   └── Server queries queue:
       SELECT * FROM driver_profiles
       WHERE queue_joined_at IS NOT NULL           -- in queue
         AND queue_cooldown_until < NOW()          -- not in penalty cooldown
         AND distance(location, pickup) <= max_pickup_radius_km
       ORDER BY queue_joined_at ASC                -- longest waiting first
       LIMIT 1

2. Send request to Driver #1 (top of filtered queue)
   └── Driver has 30 seconds to respond
       ├── ACCEPT → Create ride, driver leaves queue
       ├── DECLINE → Apply penalty, pass to Driver #2
       └── TIMEOUT → Same as decline

3. If all eligible drivers decline/timeout
   └── Request expires
   └── Rider notified "No drivers available"
   └── Rider can re-request after 1 minute cooldown
```

### Driver Settings

Drivers can configure:
- **Max pickup radius** (km) - Simple straight-line distance, no Mapbox API call
- View their **current queue position**

### Progressive Decline Penalties

Exact thresholds TBD, but structure:
```
Few declines in short time → No penalty
More declines → Warning shown
Even more → Move to bottom of queue
Excessive → Temporary suspension from queue (e.g., 15 min)
```

Track in database:
- `decline_count` - declines in current window
- `decline_window_start` - when current penalty window started
- `queue_cooldown_until` - if suspended, when they can rejoin

### Rider Re-request Rules

- Rider can request indefinitely
- If request cancelled (by rider or expired), **1 minute cooldown** before next request
- Cooldown prevents spam and gives drivers time to become available

### Database Changes Required

```sql
-- Add to driver_profiles table
ALTER TABLE driver_profiles ADD COLUMN queue_joined_at TIMESTAMP NULL;
ALTER TABLE driver_profiles ADD COLUMN max_pickup_radius_km DECIMAL(4,2) DEFAULT 5.0;
ALTER TABLE driver_profiles ADD COLUMN queue_position INT NULL;  -- calculated, for display
ALTER TABLE driver_profiles ADD COLUMN decline_count INT DEFAULT 0;
ALTER TABLE driver_profiles ADD COLUMN decline_window_start TIMESTAMP NULL;
ALTER TABLE driver_profiles ADD COLUMN queue_cooldown_until TIMESTAMP NULL;

-- Add to ride_requests table (for rider cooldown)
ALTER TABLE ride_requests ADD COLUMN rider_cooldown_until TIMESTAMP NULL;
```

### Implementation Estimate

| Task | Effort |
|------|--------|
| Database migrations | 30 min |
| Update `goOnline`/`goOffline` endpoints | 1 hour |
| Create `QueueService` with matching logic | 2-3 hours |
| Update `RequestController` to use queue | 1-2 hours |
| Add decline tracking + penalties | 1-2 hours |
| Add driver settings (max radius) | 1 hour |
| Update mobile: driver queue position display | 1-2 hours |
| Update mobile: driver settings UI | 1-2 hours |
| Update mobile: rider cooldown handling | 1 hour |
| **Total** | **~10-14 hours** |

### Files to Create/Modify

**New files:**
- `backend/app/Services/QueueService.php` - Queue management logic

**Modify:**
- `backend/app/Http/Controllers/Api/DriverController.php` - goOnline/goOffline queue management
- `backend/app/Http/Controllers/Api/RequestController.php` - Use queue instead of broadcast
- `backend/app/Models/DriverProfile.php` - Add queue fields
- `backend/database/migrations/` - New migration for queue fields
- `mobile/lib/driver/screens/driver_home_screen.dart` - Show queue position
- `mobile/lib/driver/screens/settings_screen.dart` - Max radius setting

---

## What Happened Today (December 5, 2025)

### Architecture Investigation Completed

User asked to investigate claims about the architecture. Here's what was found:

#### Claim 1: "Ride flow is heavily client-side"
**Verdict: MOSTLY FALSE**

The ride flow is actually **server-driven** for critical operations:
- State transitions: Server-controlled via API
- Fare calculation: 100% server-side
- Race conditions: Prevented via row-level DB locking
- Source of truth: PostgreSQL database

What IS client-driven (needs improvement):
- Driver matching: Manual accept (no auto-matching algorithm)
- Route fetching: Mobile calls Mapbox directly (should use backend cache)

#### Claim 2: "Mapbox API duplication - rider and driver call independently"
**Verdict: TRUE**

Current wasteful flow:
```
Rider app → Mapbox API (1-2 calls for polyline)
Driver app → Mapbox API (1-2 calls for polyline)
Backend → Mapbox API (fare calc, but 95% cached for beacons)
Total: 2-4 API calls per ride instead of 1
```

Solution exists: `docs/optimization/ROUTE_API_CACHING_PLAN.md`

#### Claim 3: "Queue timer is client-side, drivers get different timers"
**Verdict: PARTIALLY TRUE**

Two separate timers exist (confusing):
| Timer | Location | Duration |
|-------|----------|----------|
| Request expiration | Server | 30 MINUTES |
| Accept/decline UI | Client | 30 SECONDS |

**Critical issues found**:
1. `Kernel.php` scheduler is EMPTY - no cron to expire old requests
2. No re-broadcast to newly online drivers
3. Mobile doesn't capture `expires_at` field

---

## Current Architecture Summary

### What's Good (Server-Driven)

| Component | How It Works |
|-----------|--------------|
| State transitions | All go through server API with validation |
| Fare calculation | 100% server-side, client cannot manipulate |
| Race prevention | Row-level locking prevents double-acceptance |
| Source of truth | PostgreSQL database |
| Real-time sync | WebSocket broadcasts from server |
| Session recovery | `/session/resume` reconstructs full state |

### What Needs Work

| Issue | Severity | Current State |
|-------|----------|---------------|
| **Mapbox duplication** | Medium | Mobile + Backend both call API |
| **No request re-broadcast** | High | One-time broadcast, offline drivers miss it |
| **No expiration cleanup** | High | Scheduler empty, requests pile up in DB |
| **No auto-matching** | Medium | Drivers cherry-pick rides manually |
| **No idempotency keys** | Medium | Network retry could cause duplicates |
| **No ride timeouts** | Medium | Stuck rides need manual admin intervention |

---

## Test Suite Status

```
Tests:  89 failed, 140 passed (229 total)
Pass Rate: 61%
```

**Main failures**:
- 21 tests: LocationServiceTest (dependency injection broken after RouteCacheService added)
- 15 tests: MatchingServiceEdgeCasesTest
- 12 tests: RequestControllerTest
- 10 tests: RideServiceTest
- 31 tests: Various others

---

## Phases Completed (Implementation Done, Tests Missing)

### Phase 1: Route Caching ✅ Implemented, ❌ No Tests
- `RouteCacheService` caches Mapbox routes by location ID pairs
- 80-90% cost reduction for beacon-to-beacon routes
- Files: `backend/app/Services/RouteCacheService.php`, `backend/app/Models/RouteCache.php`

### Phase 2: Admin Dashboard ✅ Implemented, ❌ No Tests
- 14+ admin API endpoints
- Web dashboard at `backend/public/admin-dashboard.html`
- Force ride status change with audit logging
- Admin override broadcasts to mobile with reason

### Phase 3: Schema Fixes ✅ Implemented, ⚠️ Broke Some Tests
- `role` column replaces `user_type`
- `ratings` table uses `rated_id` and `rating_type`

---

## Priority Fixes Needed

### 🔴 Critical (Do First) - Queue System

1. **Implement Queue-Based Matching System** (~10-14 hours)
   - See detailed design in "NEXT PRIORITY" section above
   - Replaces current broadcast-to-all approach
   - Includes: migrations, QueueService, driver settings, mobile UI

2. **Add expiration cleanup cron** (~30 min)
   ```php
   // backend/app/Console/Kernel.php
   $schedule->call(fn() => app(RideService::class)->cleanupExpiredRequests())
       ->everyFiveMinutes();
   ```

### 🟠 High Priority

3. **Backend route API for mobile** (~2-3 hours)
   - Implement `GET /api/v1/rides/{id}/route`
   - Detailed plan: `docs/optimization/ROUTE_API_CACHING_PLAN.md`
   - Mobile fetches cached route instead of calling Mapbox directly

4. **Fix LocationServiceTest** (~2-3 hours)
   - Update test to inject `MapboxService` and `RouteCacheService` mocks
   - File: `backend/tests/Unit/Services/LocationServiceTest.php`

### 🟡 Medium Priority

5. **Write tests for Phase 1 & 2** (~15-20 hours)
6. **Add idempotency keys** for critical operations
7. **Add ride timeout enforcement** (auto-cancel stuck rides)

---

## Key Files Reference

### Backend - Core Services
```
backend/app/Services/
├── RideService.php          # Core ride logic, has cleanupExpiredRequests()
├── MatchingService.php      # Driver matching (manual accept)
├── LocationService.php      # Mapbox integration
├── RouteCacheService.php    # Route caching (Phase 1)
└── MapboxService.php        # Direct Mapbox API calls
```

### Backend - Controllers
```
backend/app/Http/Controllers/Api/
├── RideController.php       # Ride status updates
├── RequestController.php    # Ride request creation, broadcasts to drivers
├── DriverController.php     # Driver online/offline
├── AdminController.php      # Admin dashboard (Phase 2)
└── SessionController.php    # Session resume
```

### Backend - Scheduler (EMPTY - needs fix)
```
backend/app/Console/Kernel.php  # schedule() method is empty!
```

### Mobile - Key Files
```
mobile/lib/core/
├── models/ride_request.dart           # Missing expiresAt field
├── services/mapbox/mapbox_directions_service.dart  # Calls Mapbox directly
├── providers/active_ride_provider.dart
└── providers/ride_request_provider.dart
```

### Mobile - Screens That Call Mapbox Directly
```
mobile/lib/rider/screens/rider_active_ride_screen.dart  # Line 138-141
mobile/lib/driver/screens/active_ride_screen.dart       # Line 198-201
```

### Documentation
```
docs/optimization/ROUTE_API_CACHING_PLAN.md  # Detailed implementation plan
PHASE_COMPLETION_AUDIT.md                     # Phase 1-3 completion status
```

---

## WebSocket Channels

| Channel | Purpose |
|---------|---------|
| `private-user.{userId}` | Ride match notifications |
| `private-driver.{driverId}` | New ride request broadcasts (one-time, no re-broadcast) |
| `private-ride.{rideId}` | Ride status updates, driver location |

---

## Request Lifecycle (Current Implementation)

```
1. Rider creates request
   └── POST /requests
       └── Server sets expires_at = now + 30 minutes
       └── Broadcasts NewRideRequest to up to 50 online drivers

2. Driver receives request (if online)
   └── WebSocket event received
   └── 30-second UI timer starts (client-side)
   └── Driver accepts or declines

3. If no driver accepts
   └── Request stays "pending" in DB (NO cleanup job!)
   └── expires_at passes, but status doesn't change automatically
   └── Only enforced when someone tries to accept expired request

4. Problem: Driver goes offline and comes back
   └── Misses the broadcast
   └── Never sees the request
   └── No mechanism to poll for pending requests
```

---

## Commands Reference

```bash
# Run Backend
cd backend && php artisan serve

# Run Tests
cd backend && ./vendor/bin/phpunit

# Check pending requests in DB
php artisan tinker
>>> RideRequest::pending()->where('expires_at', '<', now())->count()  # Should be 0 but probably isn't

# Clear cache if rate limited
php artisan cache:clear

# Run Mobile
cd mobile && flutter run
```

---

## Next Steps (Suggested Order)

### Queue System Implementation
1. [ ] Create migration for queue fields on `driver_profiles`
2. [ ] Create migration for rider cooldown on `ride_requests`
3. [ ] Create `QueueService.php` with core queue logic
4. [ ] Update `DriverController` - goOnline/goOffline manage queue
5. [ ] Update `RequestController` - use queue instead of broadcast
6. [ ] Add decline tracking and penalty logic
7. [ ] Update mobile: show queue position on driver home
8. [ ] Update mobile: driver settings for max pickup radius
9. [ ] Update mobile: rider cooldown handling after cancel
10. [ ] Add expiration cleanup cron to `Kernel.php`

### After Queue System
11. [ ] Implement `GET /rides/{id}/route` for cached routes
12. [ ] Update mobile to use backend route API
13. [ ] Fix LocationServiceTest dependency injection
14. [ ] Write RouteCacheServiceTest
15. [ ] Write AdminControllerTest

---

## Questions Resolved

1. ~~**Auto-matching vs manual accept**~~ → **Queue-based with manual accept** (driver sees request, has 30s to respond)
2. **Payment integration**: TBD
3. **iOS build**: TBD
4. **Production deployment**: TBD

---

## Notes for AI Models

### Key Insights from Investigation
- The architecture is NOT "heavily client-side" - it's server-driven for critical paths
- The real issue is missing infrastructure (no cron, no queue system)
- Mobile calling Mapbox directly is wasteful but not architecturally wrong
- Admin override feature works and broadcasts to mobile with reason

### Current Priority: Queue-Based Matching
The top priority is implementing the queue-based driver matching system:
- Drivers join queue when going online (FIFO)
- Requests sent to top eligible driver only (not broadcast)
- 30-second response window
- Progressive penalties for excessive declines
- Drivers can set max pickup radius
- Riders have 1-minute cooldown after cancelled requests

### Don't Waste Time On
- Refactoring ride flow to be "more server-side" - it already is
- Adding admin override banner to rider side (low priority)
- Complex auto-matching algorithms - use simple FIFO queue instead

### Focus On
1. **Queue system implementation** (see detailed design at top of file)
2. Adding the missing cron job for request expiration
3. Implementing the route caching API (plan exists in docs/optimization/)

### Code Patterns to Follow
- Use `Future.microtask()` for provider updates in Flutter
- Use row-level locking for critical DB operations
- Broadcast events for real-time updates (but to specific driver, not all)
- Cache Mapbox responses by location ID pairs
- Use simple distance calculation for radius (no Mapbox API)

---

**Ready to implement queue system!**
