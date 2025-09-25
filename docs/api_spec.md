# API Specification v1

## Base URL

- Development: `http://localhost:8000/api`
- Staging: `https://api-staging.anjem.me/api`
- Production: `https://api.anjem.me/api`

## Authentication

All endpoints except `/auth/*` require Bearer token.

### POST /auth/send-otp

Request OTP for phone authentication

```json
{
  "phone": "+1234567890",
  "device_id": "uuid",
  "device_type": "rider|driver"
}
```

### POST /auth/verify-otp

Verify OTP and receive JWT token

```json
{
  "phone": "+1234567890",
  "otp": "123456"
}
```

## Rider Endpoints

### POST /rides

Create ride request

```json
{
  "pickup_location": {
    "latitude": 40.7128,
    "longitude": -74.0060,
    "address": "123 University Ave"
  },
  "destination_location": {
    "latitude": 40.7589,
    "longitude": -73.9851,
    "address": "456 Campus Blvd"
  },
  "passenger_count": 1
}
```

### GET /rides/{id}

Get ride status with real-time updates

## Driver Endpoints

### PUT /driver/status

Update driver status and location

```json
{
  "status": "online|offline|busy",
  "location": {
    "latitude": -7.7956,
    "longitude": 110.3695
  }
}
```

### GET /driver/available-rides

Get available rides for driver

## WebSocket Events

- Channel: `ride.{ride_id}`
- Events: `RideStatusUpdated`, `DriverLocationUpdated`, `RideCompleted`

## Note

For comprehensive API documentation with full schemas and examples, see [API_DOCUMENTATION.md](API_DOCUMENTATION.md)
