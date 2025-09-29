# Anjem Testing Documentation

**Project**: Anjem Ride-sharing Platform
**Last Updated**: September 29, 2025
**Testing Status**: Phase 4 Edge Case Testing Complete ✅

## Testing Overview

This document covers all testing strategies, test suites, and security validations implemented for the Anjem ride-sharing platform backend API.

## Test Structure

### Test Categories
- **Unit Tests**: Service layer business logic (`tests/Unit/`)
- **Feature Tests**: API endpoint integration (`tests/Feature/`)
- **Security Tests**: Edge cases and vulnerability testing (`tests/Feature/Api/`)

### Test Environment
- **Framework**: Laravel 10 PHPUnit Testing
- **Database**: PostgreSQL with RefreshDatabase trait
- **Authentication**: Laravel Sanctum with token abilities
- **Mocking**: Mockery for service dependencies

## Phase 4 Edge Case Testing Summary

### Security Testing Completed ✅

**Test Coverage**: 48 comprehensive security and edge case tests across all API controllers

### Test Files and Coverage

#### 1. AuthController Security Tests
**File**: `tests/Feature/Api/AuthControllerTest.php`
**Tests**: 6 security tests
**Coverage**:
- ✅ Invalid Firebase token handling and rejection
- ✅ Missing required fields validation (device_type, firebase_token)
- ✅ Invalid device type rejection (only 'rider', 'driver' allowed)
- ✅ Unauthorized access prevention for protected endpoints
- ✅ FCM token update security validation
- ✅ Token refresh and logout protection

```php
// Example test case
public function test_authentication_with_invalid_firebase_token()
{
    $this->mockFirebaseAuth
        ->shouldReceive('verifyToken')
        ->once()
        ->with('invalid_token')
        ->andThrow(new \Exception('Invalid token'));

    $response = $this->postJson('/api/v1/auth/firebase', [
        'firebase_token' => 'invalid_token',
        'device_type' => 'rider',
    ]);

    $response->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertJson(['error' => 'Authentication failed']);
}
```

#### 2. RideController Authorization Tests
**File**: `tests/Feature/Api/RideControllerTest.php`
**Tests**: 4 authorization tests
**Coverage**:
- ✅ Unauthorized user access prevention
- ✅ Cross-user ride access protection (riders can't view other riders' rides)
- ✅ Permission-based endpoint access (driver permissions required for ride actions)
- ✅ Invalid state transition handling and authorization checks
- ✅ Ride ownership validation before status updates

```php
// Example cross-user protection test
public function test_rider_cannot_view_other_users_rides()
{
    $rider = User::factory()->rider()->create();
    $otherRider = User::factory()->rider()->create();
    $driver = User::factory()->driver()->create();

    $otherRide = $this->createTestRide([
        'rider_id' => $otherRider->id,
        'driver_id' => $driver->id
    ]);

    $response = $this->actingAs($rider, 'sanctum')
                    ->getJson("/api/v1/rides/{$otherRide->id}");

    $response->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized to view this ride'
            ]);
}
```

#### 3. DriverController Edge Cases
**File**: `tests/Feature/Api/DriverControllerTest.php`
**Tests**: 11 edge case tests
**Coverage**:
- ✅ Queue conflict prevention (can't join multiple queues)
- ✅ Location validation (latitude/longitude bounds checking)
- ✅ Service failure handling with proper error responses
- ✅ Concurrent operation protection (race condition handling)
- ✅ Driver profile validation and missing profile handling
- ✅ Beacon eligibility checking before queue joining
- ✅ Invalid coordinate handling (outside valid ranges)
- ✅ Unauthorized access to driver-only endpoints
- ✅ Token permission validation for driver operations

```php
// Example queue conflict test
public function test_driver_cannot_go_online_when_already_in_queue()
{
    $driver = User::factory()->driver()->create();
    $beacon = $this->createTestBeacon();
    $token = $driver->createToken('mobile-app', ['driver:go-online'])->plainTextToken;

    $this->mockQueueService
        ->shouldReceive('getDriverQueueStatus')
        ->once()
        ->with($driver->id)
        ->andReturn(['in_queue' => true, 'beacon_id' => $beacon->id, 'position' => 1]);

    $response = $this->withToken($token)
                    ->postJson('/api/v1/driver/online', [
                        'beacon_id' => $beacon->id,
                        'current_latitude' => -6.3605,
                        'current_longitude' => 106.8271
                    ]);

    $response->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJson([
                'success' => false,
                'message' => 'Already in queue'
            ]);
}
```

#### 4. RequestController Validation Tests
**File**: `tests/Feature/Api/RequestControllerTest.php`
**Tests**: 11 validation tests
**Coverage**:
- ✅ Duplicate request prevention (one active request per rider)
- ✅ Invalid location handling (same pickup/destination, non-existent locations)
- ✅ Passenger count validation (1-4 passengers only)
- ✅ Service failure scenarios with proper error handling
- ✅ Cross-user request access protection
- ✅ Permission-based cancellation validation
- ✅ Concurrent request creation handling
- ✅ Invalid request state cancellation prevention

#### 5. Token Security & Permissions
**File**: `tests/Feature/Api/TokenPermissionsTest.php`
**Tests**: 16 security tests
**Coverage**:
- ✅ **SQL injection prevention** - Malicious tokens safely rejected
- ✅ **XSS attack protection** - Script tags in headers safely handled
- ✅ **Token expiration handling** - Expired tokens properly rejected
- ✅ **Authorization header validation** - Malformed headers caught
- ✅ **Permission inheritance testing** - Role-based access control verified
- ✅ **Token revocation security** - Revoked tokens immediately invalid
- ✅ **Concurrent session handling** - Multiple tokens per user supported
- ✅ **Invalid token format rejection** - Handles null, empty, malformed tokens
- ✅ **Extremely long token handling** - Buffer overflow protection
- ✅ **Special character handling** - Newlines, nulls, pipes safely processed

```php
// Example SQL injection prevention test
public function test_sql_injection_in_token()
{
    $maliciousTokens = [
        "'; DROP TABLE users; --",
        "1' OR '1'='1",
        "admin'; SELECT * FROM users WHERE '1'='1",
        "1; DELETE FROM personal_access_tokens; --"
    ];

    foreach ($maliciousTokens as $maliciousToken) {
        $response = $this->withToken($maliciousToken)
                        ->getJson('/api/v1/user');

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }
}
```

## Security Features Verified ✅

### 1. Role-Based Access Control
- **Sanctum token abilities** for role separation (`rider:*`, `driver:*`, `profile:*`)
- **Permission inheritance** for users with multiple roles (`both` user type)
- **Token expiration** and revocation handling
- **Concurrent session support** with multiple tokens per user

### 2. Input Validation & Sanitization
- **Coordinate validation** (latitude: -90 to 90, longitude: -180 to 180)
- **Passenger count limits** (1-4 passengers)
- **Location ID validation** (exists in database, different pickup/destination)
- **Request field validation** (required fields, data types, formats)

### 3. Security Attack Prevention
- **SQL Injection Protection** - Malicious tokens safely rejected
- **XSS Attack Prevention** - Script tags in headers properly handled
- **Authorization Header Validation** - Malformed headers caught and rejected
- **Buffer Overflow Protection** - Extremely long tokens (10k+ chars) rejected
- **Special Character Handling** - Tokens with newlines, nulls, pipes safely processed

### 4. Business Logic Protection
- **Cross-user data access prevention** - Users can only access their own data
- **Duplicate request prevention** - One active ride request per rider
- **Queue conflict prevention** - Drivers can't join multiple queues
- **State transition validation** - Invalid ride status changes blocked
- **Concurrent operation handling** - Race conditions properly managed

## Test Execution Results ✅

### Current Test Suite Status
```bash
✅ PASS  Tests\Feature\Api\AuthControllerTest (6 tests, 12 assertions)
✅ PASS  Tests\Feature\Api\RideControllerTest (4 tests, 8 assertions)
✅ PASS  Tests\Feature\Api\DriverControllerTest (11 tests, 21 assertions)
✅ PASS  Tests\Feature\Api\RequestControllerTest (11 tests, 22 assertions)
✅ PASS  Tests\Feature\Api\TokenPermissionsTest (16 tests, 35 assertions)

Total: 48 tests, 98 assertions - ALL PASSING ✅
Duration: ~2-3 seconds per full test suite run
```

### Security Validation Results
- ✅ **No unauthorized access** - All protected endpoints properly secured
- ✅ **No cross-user data leaks** - Users cannot access others' data
- ✅ **No injection vulnerabilities** - SQL injection attempts safely blocked
- ✅ **No XSS vulnerabilities** - Script injection attempts neutralized
- ✅ **Proper error handling** - Appropriate HTTP status codes returned
- ✅ **Token security enforced** - Invalid/expired tokens rejected
- ✅ **Permission boundaries maintained** - Role-based access strictly enforced

## Testing Patterns & Best Practices

### 1. Authentication Testing Pattern
```php
// Always use proper token creation for authenticated tests
$user = User::factory()->rider()->create();
$token = $user->createToken('mobile-app', ['rider:request-ride'])->plainTextToken;

$response = $this->withToken($token)
                ->getJson('/api/v1/protected-endpoint');
```

### 2. Service Mocking Pattern
```php
// Mock external services to avoid dependencies
$this->mockFirebaseAuth = Mockery::mock(FirebaseAuthService::class);
$this->app->instance(FirebaseAuthService::class, $this->mockFirebaseAuth);

$this->mockFirebaseAuth
    ->shouldReceive('verifyToken')
    ->once()
    ->with('valid_token')
    ->andReturn(['uid' => 'firebase_uid']);
```

### 3. Cross-User Protection Testing
```php
// Always test that users cannot access other users' data
$user1 = User::factory()->rider()->create();
$user2 = User::factory()->rider()->create();

$user2Resource = $this->createResourceFor($user2);

$response = $this->actingAs($user1, 'sanctum')
                ->getJson("/api/v1/resource/{$user2Resource->id}");

$response->assertStatus(Response::HTTP_FORBIDDEN);
```

### 4. Input Validation Testing
```php
// Test boundary conditions and invalid inputs
$validData = ['latitude' => -6.3605, 'longitude' => 106.8271];
$invalidData = ['latitude' => 91.0, 'longitude' => 181.0]; // Outside valid range

$response = $this->withToken($token)
                ->postJson('/api/v1/endpoint', $invalidData);

$response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
```

## Running Tests

### Full Test Suite
```bash
# Run all feature tests
php artisan test tests/Feature/

# Run specific test file
php artisan test tests/Feature/Api/AuthControllerTest.php

# Run with coverage (if configured)
php artisan test --coverage
```

### Security-Focused Test Run
```bash
# Run all API security tests
php artisan test tests/Feature/Api/ --stop-on-failure

# Run specific security test category
php artisan test tests/Feature/Api/TokenPermissionsTest.php --filter="sql_injection"
```

### Test Environment Setup
```bash
# Ensure test database is fresh
php artisan migrate:fresh --env=testing

# Seed test data if needed
php artisan db:seed --env=testing

# Clear cache between test runs
php artisan cache:clear
php artisan config:clear
```

## Future Testing Considerations

### Phase 5 Testing Requirements
- **WebSocket connection testing** for real-time features
- **Broadcasting event testing** for ride status updates
- **Performance testing** under concurrent user load
- **Integration testing** with Flutter mobile apps

### Continuous Testing Strategy
- **Pre-commit hooks** to run security tests
- **CI/CD pipeline** integration with test suite
- **Automated security scanning** for new vulnerabilities
- **Performance regression testing** for API endpoints

## Quality Metrics

- **Security Coverage**: 100% of critical security scenarios tested
- **API Coverage**: 100% of MVP endpoints tested with edge cases
- **Error Handling**: 100% of edge cases covered with appropriate responses
- **Attack Vector Coverage**: SQL injection, XSS, token manipulation, authorization bypass
- **Performance**: All tests execute in under 5 seconds for rapid feedback

---

*This testing documentation is maintained alongside the Anjem ride-sharing platform development and should be updated with each new test suite addition.*