# LocationService Refactor & Test Fix Plan

## Context

`LocationService::getDrivingDetails()` currently returns hardcoded estimates (MVP placeholder). The goal is to replace it with real Mapbox Directions API calls, add Redis-based caching via a new `RouteCacheService`, and inject both as constructor dependencies into `LocationService`.

This change will break the existing `LocationServiceTest` and `RideServiceTest` (both do `new LocationService()` with no arguments). The fix is to update both test setups to inject Mockery mocks for the two new dependencies.

Branch: `fix/location-service-test`

---

## Files to Change

| Action | File |
|--------|------|
| CREATE | `backend/app/Services/MapboxService.php` |
| CREATE | `backend/app/Services/RouteCacheService.php` |
| MODIFY | `backend/app/Services/LocationService.php` |
| MODIFY | `backend/tests/Unit/Services/LocationServiceTest.php` |
| MODIFY | `backend/tests/Unit/Services/RideServiceTest.php` |

---

## Step 1: Create `MapboxService`

**File:** `backend/app/Services/MapboxService.php`

Wraps the Mapbox Directions API. Single public method:

```php
public function getRoute(float $originLat, float $originLng, float $destLat, float $destLng): ?array
// Returns ['distance_meters' => int, 'duration_seconds' => int] or null on failure
```

- Reads `MAPBOX_ACCESS_TOKEN` from `config('services.mapbox.access_token')`
- Calls `GET https://api.mapbox.com/directions/v5/mapbox/driving/{lng},{lat};{lng},{lat}?access_token=...`
- Uses `Http::get(...)`, extracts `routes[0].distance` and `routes[0].duration`
- Returns `null` (logs error) on HTTP failure or missing route
- No caching logic here — that belongs in RouteCacheService

---

## Step 2: Create `RouteCacheService`

**File:** `backend/app/Services/RouteCacheService.php`

Wraps MapboxService with Redis caching. Single public method:

```php
public function __construct(private MapboxService $mapboxService) {}

public function getRoute(float $originLat, float $originLng, float $destLat, float $destLng): ?array
```

- Cache key: `route:{originLat},{originLng}:{destLat},{destLng}` (rounded to 4 decimal places for better hit rate)
- TTL: 24 hours (`Cache::remember(..., now()->addHours(24), fn() => ...)`)
- Delegates to `$this->mapboxService->getRoute(...)` on miss
- Returns same `['distance_meters', 'duration_seconds']` shape or `null`

---

## Step 3: Update `LocationService`

**File:** `backend/app/Services/LocationService.php`

Add constructor injection:

```php
public function __construct(
    private RouteCacheService $routeCacheService
) {}
```

Update `getDrivingDetails()` to use the cache service:

```php
public function getDrivingDetails(float $originLat, float $originLng, float $destLat, float $destLng): array
{
    $route = $this->routeCacheService->getRoute($originLat, $originLng, $destLat, $destLng);

    if ($route) {
        return [
            'distance_meters' => $route['distance_meters'],
            'duration_minutes' => (int) ceil($route['duration_seconds'] / 60),
            'estimated' => false,
        ];
    }

    // Fallback to Haversine estimate if Mapbox unavailable
    $distance = $this->calculateDistance($originLat, $originLng, $destLat, $destLng);
    $estimatedDistance = $distance * 1.3;
    $estimatedDuration = ($estimatedDistance / 1000) / 20 * 60;

    return [
        'distance_meters' => (int) $estimatedDistance,
        'duration_minutes' => (int) $estimatedDuration,
        'estimated' => true,
    ];
}
```

No other methods change. Constructor is the only breaking addition.

---

## Step 4: Fix `LocationServiceTest`

**File:** `backend/tests/Unit/Services/LocationServiceTest.php`

Add Mockery imports and mock setup:

```php
use App\Services\RouteCacheService;
use Mockery;
use Mockery\MockInterface;
```

Update `setUp()`:
```php
private MockInterface $routeCacheService;

protected function setUp(): void
{
    parent::setUp();
    $this->routeCacheService = Mockery::mock(RouteCacheService::class);
    $this->locationService = new LocationService($this->routeCacheService);
}

protected function tearDown(): void
{
    Mockery::close();
    parent::tearDown();
}
```

Update `test_can_get_driving_details()` to set a mock expectation:
```php
$this->routeCacheService
    ->shouldReceive('getRoute')
    ->once()
    ->andReturn(['distance_meters' => 350, 'duration_seconds' => 63]);

$details = $this->locationService->getDrivingDetails(-6.3605, 106.8271, -6.3595, 106.8295);
$this->assertFalse($details['estimated']);
$this->assertEquals(350, $details['distance_meters']);
```

All other 20 tests need no changes (they don't call `getDrivingDetails`).

---

## Step 5: Fix `RideServiceTest`

**File:** `backend/tests/Unit/Services/RideServiceTest.php`

Same mock injection for the `LocationService` dependency:

```php
use App\Services\RouteCacheService;
use Mockery;
use Mockery\MockInterface;

private MockInterface $routeCacheService;

protected function setUp(): void
{
    parent::setUp();
    $this->routeCacheService = Mockery::mock(RouteCacheService::class);
    $this->locationService = new LocationService($this->routeCacheService);
    $this->queueService = new QueueService();
    $this->rideService = new RideService($this->locationService, $this->queueService);
}

protected function tearDown(): void
{
    Mockery::close();
    parent::tearDown();
}
```

If any RideService test calls a method that triggers `getDrivingDetails`, add `$this->routeCacheService->shouldReceive('getRoute')->andReturn(...)` in that specific test.

---

## Notes

- `MapboxService` is injected into `RouteCacheService`, not directly into `LocationService`. This keeps LocationService's constructor minimal (one dependency).
- Laravel's auto-resolution (service container) will automatically wire `MapboxService → RouteCacheService → LocationService` in production. No manual service provider binding needed.
- `RideService` already accepts `LocationService` via constructor, so no changes needed there.

---

## Verification

```bash
cd backend

# Run only the affected tests
php artisan test --filter=LocationServiceTest
php artisan test --filter=RideServiceTest

# Run full suite to catch regressions
php artisan test
```

Expected: all 21 `LocationServiceTest` methods pass, `RideServiceTest` passes. `test_can_get_driving_details` now asserts `estimated = false` when Mapbox mock returns data.
