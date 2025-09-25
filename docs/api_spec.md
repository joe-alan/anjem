````markdown
# API Specification v1

## Base URL

- Development: `http://localhost:8000/api/v1`
- Staging: `https://api-staging.anjem.me/v1`
- Production: `https://api.anjem.me/v1`

## Authentication

All endpoints except `/auth/*` require Bearer token.

### POST /auth/otp

Request OTP for email authentication

```json
{
  "email": "user@example.com",
  "device_id": "uuid",
  "device_type": "rider|driver"
}
```
````

### POST /auth/otp/verify

Verify OTP and receive JWT token

```json
{
  "request_id": "uuid",
  "code": "123456"
}
```

## Rider Endpoints

### POST /requests

Create ride request

```json
{
  "mode": "beacon|p2p",
  "beacon_in": "beacon_id",
  "beacon_out": "beacon_id",
  "pooled": true
}
```

### GET /requests/{id}

Get request status with WebSocket fallback

## Driver Endpoints

### POST /driver/online

Go online with location

```json
{
  "lat": -7.7956,
  "lng": 110.3695
}
```

### GET /driver/queue

Get available requests in vicinity

## WebSocket Events

- Channel: `ride:{ride_id}`
- Events: `driver.assigned`, `driver.arrived`, `ride.started`, `ride.completed`

```

```
