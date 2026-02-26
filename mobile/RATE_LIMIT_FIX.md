# Rate Limit Error Fix

**Date**: December 3, 2025
**Issue**: Rider app crashes with "too many attempts" error when driver app is rate-limited

---

## The Problem

### What Happened:
1. User 1 logged into rider app and created a ride request
2. Multiple login attempts on driver app (logout/login cycles) triggered Laravel's rate limiter
3. Rider app (still on waiting screen) made background API calls
4. These calls hit the same rate-limited backend
5. **Rider app received 429 error and crashed**

### Root Cause:
- **Laravel rate limiting is per IP address**, not per app or user
- Both rider and driver apps share:
  - Same backend API
  - Same device IP address
  - Same rate limit bucket
- Background API calls (session checks, WebSocket auth, etc.) from rider app got caught in the rate limit
- **The 429 error wasn't handled gracefully** → caused crash

---

## The Fix

### Changes Made:

#### 1. Added Rate Limit Helper (api_exception.dart)
```dart
bool get isRateLimitError {
  return statusCode == 429;
}
```

#### 2. Graceful Rate Limit Handling (session_service.dart)
```dart
// If rate limited, return idle state instead of crashing
if (e.isRateLimitError) {
  print('WARN SessionService: Rate limit hit, returning idle state');
  return SessionState(
    state: SessionStateType.idle,
    driverContext: const DriverContext(
      isDriver: false,
      isOnline: false,
    ),
  );
}
```

**Result**: Instead of crashing, the app now:
- Detects 429 errors
- Returns an idle session state
- Logs a warning
- **Continues running normally**

---

## Why This Happens

### Laravel Rate Limiting:
Laravel's default rate limiter (in `RouteServiceProvider`) typically limits:
- **Login attempts**: 5 per minute per IP
- **API calls**: 60 per minute per IP (default)

### Shared IP = Shared Limit:
When running both apps on the same device:
```
Device IP: 10.0.2.2 (Android emulator)
    │
    ├─ Rider App API calls ──┐
    │                        ├──> Backend Rate Limiter (60/min)
    └─ Driver App API calls ─┘
```

Excessive requests from **either app** affect **both apps**.

---

## How to Prevent This

### Short Term (Development):
1. **Increase rate limits** in backend for development:
   ```php
   // backend/app/Providers/RouteServiceProvider.php
   RateLimiter::for('api', function (Request $request) {
       return Limit::perMinute(120)->by($request->ip()); // Increased from 60
   });
   ```

2. **Use different IP addresses** for rider and driver apps:
   - Run one app on physical device (different IP)
   - Run other on emulator

3. **Clear rate limit manually** when testing:
   ```bash
   php artisan cache:clear
   ```

### Long Term (Production):
1. **Rate limit by user ID** instead of IP:
   ```php
   return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
   ```

2. **Implement exponential backoff** in mobile apps:
   - Retry failed requests with increasing delays
   - Detect 429 and wait before retrying

3. **Different rate limits for different endpoints**:
   - Login: 5/min
   - Session checks: 10/min
   - Regular API: 60/min

---

## Testing the Fix

### Test Scenario:
1. Login to rider app as User 1
2. Create a ride request (go to waiting screen)
3. On driver app, rapidly login/logout multiple times
4. Trigger rate limit (you'll see "Too many attempts" on driver app)
5. **Rider app should NOT crash** - it should:
   - Continue showing waiting screen
   - Log warning: `WARN SessionService: Rate limit hit, returning idle state`
   - Gracefully handle the rate limit

### Expected Behavior:
✅ Rider app stays open (no crash)
✅ Warning logged in console
✅ App continues functioning normally
✅ Once rate limit clears, normal operation resumes

---

## Files Modified

1. **mobile/lib/core/services/api/api_exception.dart**
   - Added `isRateLimitError` getter

2. **mobile/lib/core/services/session/session_service.dart**
   - Added rate limit error handling
   - Returns idle state instead of crashing

---

## Future Improvements

### Backend (P2):
- [ ] Implement per-user rate limiting
- [ ] Add rate limit headers (X-RateLimit-Remaining, etc.)
- [ ] Different limits for different endpoint groups

### Mobile (P3):
- [ ] Show user-friendly "Rate limited, please wait" message
- [ ] Implement exponential backoff for retries
- [ ] Add rate limit info to error UI

---

## Related Issues

This fix also prevents crashes from:
- WebSocket connection spam
- Rapid session refresh attempts
- Background polling during rate limits
- Any other 429 errors from the backend

---

**Status**: ✅ **Fixed** - Rate limit errors now handled gracefully

---

**Last Updated**: December 3, 2025
