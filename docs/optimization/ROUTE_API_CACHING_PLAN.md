# Route API Caching Optimization Plan

**Status**: 📋 Planned (Not Yet Implemented)
**Priority**: Medium (Performance Optimization)
**Estimated Effort**: 50 minutes
**Created**: November 20, 2025

---

## Problem Statement

Currently, both rider and driver apps make **separate Mapbox Directions API calls** to fetch the same route data:

- **Rider app**: Calls Mapbox for pickup → destination route
- **Driver app**: Calls Mapbox for driver → pickup, then pickup → destination route
- **Result**: 2-3 Mapbox API calls per ride (expensive, slow, redundant)

### Cost Impact
- ~2000-3000 Mapbox API calls per day for 1000 rides
- Each call costs money and adds latency (~500ms)
- Rider and driver might see slightly different routes (Mapbox recalculates)

---

## Proposed Solution

**Backend fetches route ONCE, caches it, both apps use the cached data**

### Architecture Change

**Before (Current)**:
```
Rider App → Mapbox Directions API (pickup → destination)
Driver App → Mapbox Directions API (driver → pickup)
Driver App → Mapbox Directions API (pickup → destination)
```

**After (Optimized)**:
```
Backend → Mapbox Directions API (ONE call)
    ↓
Redis Cache (TTL: 2 hours)
    ↓
Rider App ← GET /api/v1/rides/{id}/route
Driver App ← GET /api/v1/rides/{id}/route
```

---

## Benefits

### 1. Cost Savings
- **Reduction**: 50-66% fewer Mapbox API calls
- **Calculation**: 1000 rides × 2.5 avg calls = 2500 calls/day → 1000 calls/day
- **Annual Savings**: Significant if using paid Mapbox tier

### 2. Performance
- **Before**: Mobile waits ~500ms for Mapbox response
- **After**: Redis cache returns in ~10ms
- **Result**: 50x faster route loading

### 3. Consistency
- **Before**: Rider and driver might see different routes
- **After**: Both see identical route (single source of truth)

### 4. Offline Capability
- Routes cached on backend persist even if Mapbox temporarily unavailable
- Mobile apps can retry without hitting Mapbox rate limits

---

## Implementation Plan

### Step 1: Backend API Endpoint

**Create**: `GET /api/v1/rides/{id}/route`

**File**: `backend/app/Http/Controllers/Api/RideController.php`

```php
/**
 * Get cached route for a ride
 *
 * Fetches the pickup → destination route from cache or Mapbox
 * Caches result in Redis for 2 hours
 */
public function getRoute(Ride $ride): JsonResponse
{
    // Authorization check
    $user = request()->user();
    if ($ride->rider_id !== $user->id && $ride->driver_id !== $user->id) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 403);
    }

    // Check Redis cache first
    $cacheKey = "ride:route:{$ride->id}";
    $cached = Cache::get($cacheKey);

    if ($cached) {
        return response()->json([
            'success' => true,
            'data' => $cached,
            'cached' => true,
        ]);
    }

    // Cache miss - fetch from Mapbox
    try {
        $route = $this->locationService->getRouteGeometry(
            $ride->pickupLocation,
            $ride->destinationLocation
        );

        $data = [
            'ride_id' => $ride->id,
            'points' => $route['geometry'], // Array of [lng, lat]
            'distance_meters' => $route['distance'],
            'duration_seconds' => $route['duration'],
        ];

        // Cache for 2 hours
        Cache::put($cacheKey, $data, now()->addHours(2));

        return response()->json([
            'success' => true,
            'data' => $data,
            'cached' => false,
        ]);

    } catch (\Exception $e) {
        Log::error('Failed to fetch route', [
            'ride_id' => $ride->id,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch route',
        ], 500);
    }
}
```

**Add Route** (`backend/routes/api.php`):
```php
// Inside the authenticated routes group
Route::get('/rides/{ride}/route', [RideController::class, 'getRoute'])
    ->middleware(['auth:sanctum']);
```

---

### Step 2: Update LocationService (if needed)

**File**: `backend/app/Services/LocationService.php`

Verify `getRouteGeometry()` method exists and returns:
```php
[
    'geometry' => [[lng, lat], [lng, lat], ...],
    'distance' => 5420,  // meters
    'duration' => 780,   // seconds
]
```

If method doesn't exist, add it using existing Mapbox integration.

---

### Step 3: Update Mobile Apps

**Create Helper Method** (`mobile/lib/core/services/ride/ride_service.dart`):

```dart
/// Fetch route from backend (cached)
Future<List<LatLng>> getRideRoute(int rideId) async {
  try {
    final response = await _apiService.get('/rides/$rideId/route');

    if (response.data['success'] != true) {
      throw Exception(response.data['message'] ?? 'Failed to fetch route');
    }

    final points = response.data['data']['points'] as List;
    return points.map((p) => LatLng(p[1], p[0])).toList();  // [lng, lat] → LatLng(lat, lng)

  } catch (e) {
    print('RideService: Error fetching route - $e');
    rethrow;
  }
}
```

**Update Rider Screen** (`mobile/lib/rider/screens/rider_active_ride_screen.dart`):

Replace direct Mapbox call:
```dart
// ❌ BEFORE: Direct Mapbox call
final routePoints = await _directionsService.getRoute(
  origin: pickupLatLng,
  destination: destLatLng,
);

// ✅ AFTER: Backend API call
final routePoints = await ref.read(rideServiceProvider).getRideRoute(ride.id);
```

**Update Driver Screen** (`mobile/lib/driver/screens/active_ride_screen.dart`):

**For pickup → destination route only**:
```dart
// When ride status is in_progress
if (ride.status == RideStatus.inProgress) {
  // ✅ Use backend cached route
  routePoints = await ref.read(rideServiceProvider).getRideRoute(ride.id);

  polyline = MapPolyline(
    id: 'route_to_destination_${ride.id}',
    points: routePoints,
    color: Colors.green,
    width: 4.0,
  );
}
```

**Keep driver → pickup route as direct Mapbox call** (driver's location is dynamic):
```dart
// When ride status is accepted
if (ride.status == RideStatus.accepted) {
  // ✅ Keep direct Mapbox call (driver location changes)
  routePoints = await _directionsService.getRoute(
    origin: _currentDriverLocation!,
    destination: pickupLatLng,
  );

  polyline = MapPolyline(
    id: 'route_to_pickup_${ride.id}',
    points: routePoints,
    color: Colors.blue,
    width: 4.0,
  );
}
```

---

### Step 4: Delete Unused Files

After confirming backend API works:

**Delete**: `mobile/lib/core/services/mapbox/mapbox_directions_service.dart`

Only if driver's dynamic routing is also moved to backend (not recommended).

---

## Edge Cases & Error Handling

### 1. Redis Cache Miss
- **Scenario**: Cache expired or Redis restarted
- **Handling**: Backend fetches from Mapbox, caches result
- **Impact**: Slight delay on first request only

### 2. Mapbox API Failure
- **Scenario**: Mapbox API down or rate limited
- **Handling**: Return cached route if available, else return error
- **Fallback**: Mobile app could fall back to direct Mapbox call (if keeping MapboxDirectionsService)

### 3. Route Becomes Outdated
- **Scenario**: Road closure, traffic changes after 2 hours
- **Handling**: Cache expires after 2 hours, new route fetched
- **Consideration**: For active rides, this is acceptable

### 4. Concurrent Requests
- **Scenario**: Rider and driver request route simultaneously
- **Handling**: First request fetches from Mapbox and caches, second gets cached result
- **Redis**: Atomic operations prevent race conditions

---

## Testing Plan

### Unit Tests
- Test `RideController::getRoute()` with mocked LocationService
- Test cache hit and cache miss scenarios
- Test authorization (rider/driver can access their ride's route)

### Integration Tests
1. **First Request**: Verify Mapbox is called and result cached
2. **Second Request**: Verify cache is used (no Mapbox call)
3. **Cache Expiry**: Wait 2 hours, verify new Mapbox call
4. **Concurrent Requests**: Verify only 1 Mapbox call

### Performance Tests
- Measure response time: cache hit (~10ms) vs cache miss (~500ms)
- Monitor Mapbox API usage before and after implementation

### End-to-End Test
1. Create ride
2. Rider opens active ride screen → Backend fetches and caches route
3. Driver opens active ride screen → Gets cached route (fast!)
4. Complete ride
5. Check Redis: `redis-cli GET "ride:route:13"`

---

## Monitoring

### Metrics to Track
1. **Cache Hit Rate**: Target >80% (most requests hit cache)
2. **Mapbox API Calls**: Should drop by 50-66%
3. **Response Time**: Cache hits should be <50ms
4. **Cache Size**: Monitor Redis memory usage

### Logs to Add
```php
Log::info('Route cache hit', ['ride_id' => $ride->id]);
Log::info('Route cache miss - fetching from Mapbox', ['ride_id' => $ride->id]);
```

---

## Rollback Plan

If issues arise:

1. **Immediate**: Remove route from `api.php`, mobile apps fall back to direct Mapbox calls
2. **Keep**: MapboxDirectionsService in mobile app as fallback
3. **Clear**: Redis cache: `redis-cli FLUSHDB`

---

## Future Enhancements

### 1. Proactive Route Caching
When ride is **accepted**, backend immediately fetches and caches route:
```php
// In RideService::acceptRide()
dispatch(function() use ($ride) {
    app(LocationService::class)->getRouteGeometry(
        $ride->pickupLocation,
        $ride->destinationLocation
    );
})->afterResponse();
```

### 2. Store Route in Database
For historical analysis, store route geometry in `rides` table:
```php
$table->json('route_geometry')->nullable();
$table->integer('route_distance_meters')->nullable();
$table->integer('route_duration_seconds')->nullable();
```

### 3. Multiple Route Options
Fetch alternative routes and let user choose:
```php
GET /api/v1/rides/{id}/routes?alternatives=true
```

### 4. Real-Time Traffic Updates
Refresh cache every 30 minutes during active ride to account for traffic changes.

---

## Cost-Benefit Analysis

### Costs
- **Development**: 50 minutes implementation + testing
- **Redis Memory**: ~5KB per route × 100 active rides = 500KB (negligible)
- **Maintenance**: Minimal (standard Redis caching)

### Benefits
- **Mapbox API Savings**: 50-66% reduction = $X/month (depends on volume)
- **Performance**: 50x faster route loading (500ms → 10ms)
- **User Experience**: Instant route display for cached rides
- **Consistency**: Identical routes for rider and driver

**ROI**: High - pays for itself in first week of production usage

---

## Dependencies

### Backend
- Laravel Cache (Redis driver configured)
- Existing LocationService with Mapbox integration
- Redis running and accessible

### Mobile
- Existing RideService and ApiService
- Error handling for network failures

---

## Implementation Checklist

- [ ] Add `getRoute()` method to RideController
- [ ] Add route endpoint to api.php
- [ ] Verify LocationService has getRouteGeometry() method
- [ ] Test backend endpoint with Postman/Insomnia
- [ ] Add getRideRoute() method to mobile RideService
- [ ] Update rider screen to use backend API
- [ ] Update driver screen to use backend API (for static route)
- [ ] Test cache hit/miss scenarios
- [ ] Monitor Mapbox API usage reduction
- [ ] Update API documentation
- [ ] (Optional) Delete MapboxDirectionsService if no longer needed

---

## Notes

- **Don't delete MapboxDirectionsService yet**: Keep as fallback until backend API is proven stable
- **Driver dynamic routing**: Keep direct Mapbox call for driver → pickup (moving origin)
- **Cache invalidation**: 2-hour TTL is reasonable; rides rarely take that long
- **Authorization**: Ensure only rider and driver of a ride can access its route

---

## Related Documentation

- API Documentation: `docs/api/API_DOCUMENTATION.md`
- LocationService: `backend/app/Services/LocationService.php`
- Redis Setup: `docs/setup/infrastructure.md`
- Mapbox Integration: `docs/architecture/tech_spec.md`

---

**Ready to implement when needed!** This optimization will significantly reduce API costs and improve performance.
