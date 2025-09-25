````markdown
# Testing Strategy

## Unit Tests (Target: 80% coverage)

### Backend (PHPUnit)

- Auth flow: OTP generation, verification, token refresh
- Matching algorithm with edge cases
- Fare calculation with distance steps
- State machine transitions

### Mobile (Flutter Test)

- BLoC/Riverpod state management
- Form validations
- Offline queue for requests
- Location permission flows

## Integration Tests

### Critical Paths

1. Rider: Request → Match → Complete → Rate
2. Driver: Online → Accept → Arrive → Complete
3. Cancellation flows with penalties

## Load Testing (k6)

### Scenario

```javascript
// k6_load_test.js
import http from "k6/http";
import { check } from "k6";

export let options = {
  stages: [
    { duration: "2m", target: 100 },
    { duration: "5m", target: 200 },
    { duration: "2m", target: 0 },
  ],
  thresholds: {
    http_req_duration: ["p(95)<300"],
    http_req_failed: ["rate<0.005"],
  },
};

export default function () {
  // Test request creation at peak
  let res = http.post(`${__ENV.API_URL}/v1/requests`, {
    mode: "beacon",
    beacon_in: "test-beacon-1",
    beacon_out: "test-beacon-2",
  });
  check(res, { "status is 201": (r) => r.status === 201 });
}
```

## E2E Testing

### Playwright Web Admin

- Admin login → View metrics → Export CSV
- Block user → Verify access denied

### Flutter Integration Test

- Complete ride flow with mock backend
- Background location tracking verification
- Push notification delivery
````
