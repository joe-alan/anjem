# API Specification v1

## Base URL

- Development: `http://localhost:8000/api`
- Staging: `https://api-staging.anjem.me/api`
- Production: `https://api.anjem.me/api`

## Authentication

All endpoints except `/auth/*` require Bearer token.

### POST /auth/firebase

Authenticate with Firebase ID token and receive Sanctum token

```json
{
  "firebase_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
  "device_type": "rider|driver",
  "device_id": "optional-uuid"
}
```

Response:
```json
{
  "success": true,
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "rider",
    "firebase_uid": "firebase-user-id"
  },
  "token": "sanctum-token",
  "token_type": "Bearer"
}
```

### GET /auth/google

Get Google OAuth redirect URL

Response:
```json
{
  "redirect_url": "https://accounts.google.com/oauth/authorize?..."
}
```

### GET /auth/google/callback

Handle Google OAuth callback (automatically processes the code)

Query params: `code`, `device_type` (optional, defaults to "rider")

### POST /auth/refresh

Refresh Sanctum token (requires authentication)

### POST /auth/logout

Logout and revoke tokens (requires authentication)

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
