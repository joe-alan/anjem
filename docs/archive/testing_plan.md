# Testing Strategy

## Unit Tests (Target: 80% coverage)

### Backend (PHPUnit)

- Auth flow: OTP generation, verification, token refresh
- Ride management: creation, matching, status updates
- API endpoints and validation rules
- Service layer business logic

### Mobile (Flutter Test)

- Provider/Riverpod state management
- Form validations
- API service layer
- Location permission flows

## Integration Tests

### Critical Paths

1. Rider: OTP Login → Create Ride → Track Progress → Complete
2. Driver: OTP Login → Go Online → Accept Ride → Complete
3. Cancellation flows and error handling

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
  // Test ride creation at peak
  let res = http.post(`${__ENV.API_URL}/rides`, {
    pickup_location: {
      latitude: 40.7128,
      longitude: -74.0060,
      address: "123 Test St"
    },
    destination_location: {
      latitude: 40.7589,
      longitude: -73.9851,
      address: "456 Test Ave"
    }
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
