# Phase 1: Route Caching Manual Testing Guide

**Date**: December 2, 2025
**Feature**: API Cost Optimization with Route Caching
**Expected Result**: 80-90% reduction in Mapbox API calls, 1000x+ faster responses

---

## Prerequisites

1. **Backend server running**:
   ```bash
   cd backend
   php artisan serve
   ```

2. **Database seeded with locations**:
   ```bash
   php artisan db:seed --class=LocationSeeder
   ```

3. **Valid user token** (we'll create one in Step 1)

---

## Test 1: Verify Locations Exist

**Check if you have locations in database:**

```bash
php artisan tinker --execute="
echo 'Total locations: ' . App\Models\Location::count() . PHP_EOL;
echo 'Active beacons: ' . App\Models\Location::where('is_beacon', true)->where('is_active', true)->count() . PHP_EOL;
App\Models\Location::where('is_beacon', true)->where('is_active', true)->limit(5)->get(['id', 'name'])->each(function(\$loc) {
    echo 'ID: ' . \$loc->id . ' - ' . \$loc->name . PHP_EOL;
});
"
```

**Expected Output**:
```
Total locations: 15-30
Active beacons: 10-20
ID: 1 - Central Library
ID: 2 - Engineering Faculty
ID: 3 - Medical Faculty
...
```

**If no locations exist**, seed them:
```bash
php artisan db:seed --class=LocationSeeder
```

---

## Test 2: Check Route Cache Table

**Verify route_cache table exists and is empty (for fresh test):**

```bash
php artisan tinker --execute="
echo 'Route cache table exists: ' . (Schema::hasTable('route_cache') ? 'YES' : 'NO') . PHP_EOL;
echo 'Cached routes: ' . App\Models\RouteCache::count() . PHP_EOL;
"
```

**Expected Output**:
```
Route cache table exists: YES
Cached routes: 0
```

**Optional - Clear cache for fresh test**:
```bash
php artisan tinker --execute="
\$deleted = App\Models\RouteCache::count();
App\Models\RouteCache::truncate();
echo 'Cleared ' . \$deleted . ' cached routes' . PHP_EOL;
"
```

---

## Test 3: Create Test User and Token

**Create a test rider:**

```bash
php artisan tinker --execute="
\$user = App\Models\User::firstOrCreate(
    ['email' => 'test.rider@test.com'],
    [
        'name' => 'Test Rider',
        'phone' => '+6281234567890',
        'role' => 'rider',
        'is_active' => true,
        'password' => Hash::make('password123')
    ]
);
\$token = \$user->createTokenWithAbilities(true, false);
echo 'User ID: ' . \$user->id . PHP_EOL;
echo 'Token: ' . \$token . PHP_EOL;
"
```

**Save the token** - you'll need it for API calls.

---

## Test 4: Make First Request (CACHE MISS)

**This should call Mapbox API and cache the result.**

Pick two location IDs from Test 1 (e.g., ID 1 and ID 2) and make a request estimate:

```bash
# Replace YOUR_TOKEN and location IDs
curl -X POST "http://localhost:8000/api/v1/requests/estimate" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "pickup_location_id": 1,
    "destination_location_id": 2,
    "passenger_count": 1
  }' | jq .
```

**Expected Response**:
```json
{
  "success": true,
  "data": {
    "estimated_distance_km": 0.27,
    "estimated_duration_minutes": 2,
    "estimated_price": 5000,
    "cached": false,        // ← CACHE MISS!
    "cache_age_days": 0
  }
}
```

**Key Indicators**:
- ✅ `"cached": false` - First fetch, not from cache
- ✅ `"cache_age_days": 0` - Freshly cached
- ⏱️ Response time: **2-5 seconds** (Mapbox API call)

**Check logs** (you should see cache MISS):
```bash
tail -f storage/logs/laravel.log | grep "Route cache"
```

Look for:
```
[timestamp] local.INFO: Route cache MISS - fetching from Mapbox {"origin_id":1,"destination_id":2}
[timestamp] local.INFO: Route cached (new) {"origin_id":1,"destination_id":2}
```

---

## Test 5: Make Second Request (CACHE HIT)

**Same route - should return cached data, NO Mapbox API call.**

Run the **exact same curl command** from Test 4:

```bash
curl -X POST "http://localhost:8000/api/v1/requests/estimate" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "pickup_location_id": 1,
    "destination_location_id": 2,
    "passenger_count": 1
  }' | jq .
```

**Expected Response**:
```json
{
  "success": true,
  "data": {
    "estimated_distance_km": 0.27,
    "estimated_duration_minutes": 2,
    "estimated_price": 5000,
    "cached": true,         // ← CACHE HIT!
    "cache_age_days": 0
  }
}
```

**Key Indicators**:
- ✅ `"cached": true` - Data from cache, NO Mapbox API call
- ✅ Same distance/duration as before
- ⚡ Response time: **10-50ms** (1000x+ faster!)

**Check logs**:
```
[timestamp] local.INFO: Route cache HIT {"origin_id":1,"destination_id":2,"fetch_count":2}
```

---

## Test 6: Verify Cache in Database

**Check that route was cached:**

```bash
php artisan tinker --execute="
\$route = App\Models\RouteCache::first();
if (\$route) {
    echo 'Origin: ' . \$route->originLocation->name . PHP_EOL;
    echo 'Destination: ' . \$route->destinationLocation->name . PHP_EOL;
    echo 'Distance: ' . \$route->distance_meters . 'm' . PHP_EOL;
    echo 'Duration: ' . \$route->duration_minutes . ' min' . PHP_EOL;
    echo 'Fetch count: ' . \$route->fetch_count . ' (times reused)' . PHP_EOL;
    echo 'Last fetched: ' . \$route->last_fetched_at->diffForHumans() . PHP_EOL;
} else {
    echo 'No routes cached yet' . PHP_EOL;
}
"
```

**Expected Output**:
```
Origin: Central Library
Destination: Engineering Faculty
Distance: 270m
Duration: 2 min
Fetch count: 2 (times reused)
Last fetched: 1 second ago
```

**Key Indicators**:
- ✅ `fetch_count: 2` - Route was fetched once, then reused once (cache hit)
- ✅ Route geometry stored in database
- ✅ Last fetched timestamp is recent

---

## Test 7: Check Cache Statistics

**View overall cache performance:**

```bash
php artisan tinker --execute="
\$service = app(App\Services\RouteCacheService::class);
\$stats = \$service->getCacheStats();
print_r(\$stats);
"
```

**Expected Output**:
```php
Array
(
    [total_cached_routes] => 1
    [fresh_routes] => 1        // Routes < 7 days old
    [stale_routes] => 0        // Routes > 7 days old
    [total_cache_hits] => 2    // Total times cached routes were used
    [avg_reuse_per_route] => 2 // Average reuses per route
    [cache_hit_rate] => 50%    // (2-1)/2 = 50% (will increase with more requests)
)
```

**Key Indicators**:
- ✅ `total_cached_routes: 1` - One route cached
- ✅ `total_cache_hits: 2` - Route was used twice
- ✅ `cache_hit_rate: 50%` - Half of requests were cache hits (will improve)

---

## Test 8: Test Multiple Routes

**Cache several different routes to see cache growth:**

```bash
# Route 1 → 2
curl -X POST "http://localhost:8000/api/v1/requests/estimate" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"pickup_location_id": 1, "destination_location_id": 2, "passenger_count": 1}'

# Route 1 → 3
curl -X POST "http://localhost:8000/api/v1/requests/estimate" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"pickup_location_id": 1, "destination_location_id": 3, "passenger_count": 1}'

# Route 2 → 3
curl -X POST "http://localhost:8000/api/v1/requests/estimate" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"pickup_location_id": 2, "destination_location_id": 3, "passenger_count": 1}'
```

**Check total cached routes:**
```bash
php artisan tinker --execute="
echo 'Total cached routes: ' . App\Models\RouteCache::count() . PHP_EOL;
"
```

**Expected**: 3 cached routes

---

## Test 9: View Popular Routes

**See which routes are most frequently requested:**

```bash
php artisan tinker --execute="
\$service = app(App\Services\RouteCacheService::class);
\$popular = \$service->getPopularRoutes(5);
foreach (\$popular as \$route) {
    echo \$route['origin'] . ' → ' . \$route['destination'] . ' (' . \$route['fetch_count'] . ' requests)' . PHP_EOL;
}
"
```

**Expected Output**:
```
Central Library → Engineering Faculty (3 requests)
Central Library → Medical Faculty (2 requests)
Engineering Faculty → Medical Faculty (1 request)
```

---

## Test 10: Test Cache Expiry (Optional)

**Simulate stale cache by backdating a route:**

```bash
php artisan tinker --execute="
\$route = App\Models\RouteCache::first();
\$route->update(['last_fetched_at' => Carbon\Carbon::now()->subDays(8)]);
echo 'Route backdated to 8 days ago (stale)' . PHP_EOL;
"
```

**Now request that route again** - should refresh from Mapbox:

```bash
curl -X POST "http://localhost:8000/api/v1/requests/estimate" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"pickup_location_id": 1, "destination_location_id": 2, "passenger_count": 1}' | jq .
```

**Expected**:
- `"cached": false` - Cache was stale, re-fetched from Mapbox
- Logs show "Route cache refreshed (stale)"

---

## Success Criteria ✅

Phase 1 is working correctly if:

1. ✅ **First request** → `"cached": false`, slow (2-5 sec), logs show "MISS"
2. ✅ **Second request** → `"cached": true`, fast (<100ms), logs show "HIT"
3. ✅ **Database** → route_cache table has entries
4. ✅ **Fetch count** → Increments with each cache hit
5. ✅ **Cache stats** → Shows correct hit rate and total cache hits
6. ✅ **Stale routes** → Refresh automatically after 7 days

---

## Performance Benchmarks

| Metric | Target | How to Verify |
|--------|--------|---------------|
| **Cache MISS** | 2-5 seconds | First request to new route |
| **Cache HIT** | <100ms | Subsequent requests |
| **Speed improvement** | 1000x+ faster | Compare MISS vs HIT response times |
| **Cost reduction** | 80-90% | With 80% cache hit rate, API calls drop 80% |

---

## Troubleshooting

### Issue: Routes not being cached

**Check 1**: Verify RouteCacheService is injected properly
```bash
php artisan tinker --execute="
\$service = app(App\Services\RouteCacheService::class);
echo 'Service loaded: ' . get_class(\$service) . PHP_EOL;
"
```

**Check 2**: Verify LocationService is using cache
```bash
php artisan tinker --execute="
\$locService = app(App\Services\LocationService::class);
\$reflection = new ReflectionClass(\$locService);
\$constructor = \$reflection->getConstructor();
echo 'LocationService constructor params: ' . \$constructor->getNumberOfParameters() . PHP_EOL;
"
```
Should be **2** (MapboxService + RouteCacheService)

**Check 3**: Check if location IDs are being passed
- Route caching ONLY works when location IDs are provided
- P2P destinations (custom addresses) will NOT be cached

### Issue: All requests show "cached": false

**Possible causes**:
1. Locations don't exist in database
2. Location IDs are null/missing
3. Cache was cleared between requests
4. Using different location IDs each time

**Debug**:
```bash
php artisan tinker --execute="
App\Models\RouteCache::latest()->limit(5)->get(['origin_location_id', 'destination_location_id', 'fetch_count', 'last_fetched_at'])->each(function(\$r) {
    echo 'Route ' . \$r->origin_location_id . ' → ' . \$r->destination_location_id . ' (fetch_count: ' . \$r->fetch_count . ')' . PHP_EOL;
});
"
```

### Issue: 500 errors

**Check logs**:
```bash
tail -f storage/logs/laravel.log
```

Common issues:
- Mapbox token not configured
- Database connection issue
- Missing LocationService dependencies

---

## What You Should See

### Console Output (Successful Test)
```bash
# First request
Request 1 → 2: CACHE MISS (4.2 seconds) ❌ → ✅ Cached
Request 1 → 2: CACHE HIT  (0.05 seconds) ⚡
Request 1 → 3: CACHE MISS (3.8 seconds) ❌ → ✅ Cached
Request 1 → 3: CACHE HIT  (0.03 seconds) ⚡

Cache Hit Rate: 50% (2 hits / 4 total requests)
API Cost Saved: ~50% ($0.50 saved if 1000 requests)
```

### Database State
```sql
SELECT
    origin_location_id,
    destination_location_id,
    fetch_count,
    distance_meters,
    last_fetched_at
FROM route_cache;

-- Results:
-- origin | dest | fetches | distance | last_fetched
--    1   |  2   |    2    |   270m   | 2025-12-02 10:30:15
--    1   |  3   |    2    |   450m   | 2025-12-02 10:30:20
```

---

## Next Steps

Once Phase 1 is verified working:
1. ✅ Move to Phase 2 testing (Admin Dashboard)
2. ✅ Test admin analytics showing cache stats
3. ✅ Test popular routes endpoint

**Ready to test?** Copy the commands above and let me know what results you see!
