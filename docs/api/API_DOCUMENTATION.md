# Anjem API Documentation

## OpenAPI Specification

```yaml
openapi: 3.0.3
info:
  title: Anjem Ride-sharing API
  description: |
    API for the Anjem campus ride-sharing platform.

    ## Authentication
    Most endpoints require JWT authentication. Include the token in the Authorization header:
    `Authorization: Bearer <your_jwt_token>`

    ## Rate Limiting
    - 60 requests per minute for authenticated users
    - 10 requests per minute for unauthenticated users

    ## Error Handling
    All errors follow this format:
    ```json
    {
      "error": {
        "code": "ERROR_CODE",
        "message": "Human readable error message",
        "details": {}
      }
    }
    ```
  version: 1.0.0
  contact:
    name: Anjem Development Team
    url: https://github.com/your-org/anjem
  license:
    name: MIT
    url: https://opensource.org/licenses/MIT

servers:
  - url: https://api.anjem.app/v1
    description: Production server
  - url: https://staging-api.anjem.app/v1
    description: Staging server
  - url: http://localhost:8000/api
    description: Local development server

security:
  - BearerAuth: []

paths:
  # Authentication Endpoints
  /auth/send-otp:
    post:
      tags:
        - Authentication
      summary: Send OTP to phone number
      description: Sends a one-time password to the provided phone number for authentication
      security: []
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - phone
              properties:
                phone:
                  type: string
                  pattern: '^\\+[1-9]\\d{1,14}$'
                  example: "+1234567890"
                  description: Phone number in E.164 format
      responses:
        '200':
          description: OTP sent successfully
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                    example: true
                  message:
                    type: string
                    example: "OTP sent to +1234567890"
        '400':
          $ref: '#/components/responses/BadRequest'
        '429':
          $ref: '#/components/responses/TooManyRequests'

  /auth/verify-otp:
    post:
      tags:
        - Authentication
      summary: Verify OTP and authenticate user
      description: Verifies the OTP and returns a JWT token for authentication
      security: []
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - phone
                - otp
              properties:
                phone:
                  type: string
                  pattern: '^\\+[1-9]\\d{1,14}$'
                  example: "+1234567890"
                otp:
                  type: string
                  pattern: '^\\d{6}$'
                  example: "123456"
      responses:
        '200':
          description: Authentication successful
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                    example: true
                  data:
                    type: object
                    properties:
                      user:
                        $ref: '#/components/schemas/User'
                      token:
                        type: string
                        example: "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
                      expires_at:
                        type: string
                        format: date-time
                        example: "2024-01-30T10:00:00Z"
        '400':
          $ref: '#/components/responses/BadRequest'
        '401':
          $ref: '#/components/responses/Unauthorized'

  /auth/refresh:
    post:
      tags:
        - Authentication
      summary: Refresh JWT token
      description: Refreshes the current JWT token
      responses:
        '200':
          description: Token refreshed successfully
          content:
            application/json:
              schema:
                type: object
                properties:
                  token:
                    type: string
                  expires_at:
                    type: string
                    format: date-time
        '401':
          $ref: '#/components/responses/Unauthorized'

  /auth/logout:
    post:
      tags:
        - Authentication
      summary: Logout user
      description: Invalidates the current JWT token
      responses:
        '200':
          description: Logout successful
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                    example: true
                  message:
                    type: string
                    example: "Successfully logged out"

  # User Profile Endpoints
  /user/profile:
    get:
      tags:
        - User Profile
      summary: Get user profile
      description: Retrieves the authenticated user's profile information
      responses:
        '200':
          description: Profile retrieved successfully
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                    example: true
                  data:
                    $ref: '#/components/schemas/User'
        '401':
          $ref: '#/components/responses/Unauthorized'

    put:
      tags:
        - User Profile
      summary: Update user profile
      description: Updates the authenticated user's profile information
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                name:
                  type: string
                  example: "John Doe"
                email:
                  type: string
                  format: email
                  example: "john@university.edu"
                profile_picture:
                  type: string
                  format: uri
                  example: "https://example.com/avatar.jpg"
      responses:
        '200':
          description: Profile updated successfully
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                    example: true
                  data:
                    $ref: '#/components/schemas/User'
        '400':
          $ref: '#/components/responses/BadRequest'
        '401':
          $ref: '#/components/responses/Unauthorized'

  # Ride Endpoints
  /rides:
    get:
      tags:
        - Rides
      summary: Get rides
      description: Retrieves rides based on user role and filters
      parameters:
        - name: status
          in: query
          schema:
            type: string
            enum: [pending, accepted, in_progress, completed, cancelled]
          description: Filter by ride status
        - name: limit
          in: query
          schema:
            type: integer
            minimum: 1
            maximum: 50
            default: 10
        - name: offset
          in: query
          schema:
            type: integer
            minimum: 0
            default: 0
      responses:
        '200':
          description: Rides retrieved successfully
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                    example: true
                  data:
                    type: array
                    items:
                      $ref: '#/components/schemas/Ride'
                  pagination:
                    $ref: '#/components/schemas/Pagination'

    post:
      tags:
        - Rides
      summary: Create a ride request (Rider only)
      description: Creates a new ride request
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - pickup_location
                - destination_location
              properties:
                pickup_location:
                  $ref: '#/components/schemas/Location'
                destination_location:
                  $ref: '#/components/schemas/Location'
                scheduled_time:
                  type: string
                  format: date-time
                  example: "2024-01-30T10:00:00Z"
                  description: "Optional: for scheduled rides"
                passenger_count:
                  type: integer
                  minimum: 1
                  maximum: 4
                  default: 1
                notes:
                  type: string
                  maxLength: 500
                  example: "Please pick me up at the main entrance"
      responses:
        '201':
          description: Ride created successfully
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                    example: true
                  data:
                    $ref: '#/components/schemas/Ride'
        '400':
          $ref: '#/components/responses/BadRequest'
        '401':
          $ref: '#/components/responses/Unauthorized'

  /rides/{id}:
    get:
      tags:
        - Rides
      summary: Get ride details
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
          description: Ride ID
      responses:
        '200':
          description: Ride details retrieved successfully
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                    example: true
                  data:
                    $ref: '#/components/schemas/Ride'
        '404':
          $ref: '#/components/responses/NotFound'

    put:
      tags:
        - Rides
      summary: Update ride
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                status:
                  type: string
                  enum: [accepted, in_progress, completed, cancelled]
                driver_location:
                  $ref: '#/components/schemas/Location'
      responses:
        '200':
          description: Ride updated successfully
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                    example: true
                  data:
                    $ref: '#/components/schemas/Ride'

  /rides/{id}/accept:
    post:
      tags:
        - Rides
      summary: Accept a ride (Driver only)
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: Ride accepted successfully
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                    example: true
                  data:
                    $ref: '#/components/schemas/Ride'

  /rides/{id}/cancel:
    post:
      tags:
        - Rides
      summary: Cancel a ride
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                reason:
                  type: string
                  example: "Emergency came up"
      responses:
        '200':
          description: Ride cancelled successfully

  # Driver-specific Endpoints
  /driver/available-rides:
    get:
      tags:
        - Driver
      summary: Get available rides for driver
      description: Returns rides that are available for the driver to accept
      parameters:
        - name: radius
          in: query
          schema:
            type: number
            minimum: 1
            maximum: 50
            default: 10
          description: Search radius in kilometers
        - name: max_distance
          in: query
          schema:
            type: number
            minimum: 1
            maximum: 100
            default: 25
          description: Maximum ride distance in kilometers
      responses:
        '200':
          description: Available rides retrieved successfully
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                    example: true
                  data:
                    type: array
                    items:
                      $ref: '#/components/schemas/Ride'

  /driver/status:
    put:
      tags:
        - Driver
      summary: Update driver status
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - status
              properties:
                status:
                  type: string
                  enum: [online, offline, busy]
                location:
                  $ref: '#/components/schemas/Location'
      responses:
        '200':
          description: Driver status updated successfully

  # Location Endpoints
  /places/search:
    get:
      tags:
        - Location
      summary: Search for places (beacons + destinations)
      description: |
        Search for places using local database with Mapbox API fallback.
        Searches campus beacon locations and popular destinations first,
        then falls back to Mapbox Search API if fewer than 5 results.
      parameters:
        - name: q
          in: query
          required: true
          schema:
            type: string
            minLength: 2
            maxLength: 100
          description: Search query string
          example: "gate"
        - name: latitude
          in: query
          required: false
          schema:
            type: number
            format: double
            minimum: -90
            maximum: 90
          description: User's latitude for proximity sorting (must provide both lat/lng)
          example: -6.3615
        - name: longitude
          in: query
          required: false
          schema:
            type: number
            format: double
            minimum: -180
            maximum: 180
          description: User's longitude for proximity sorting (must provide both lat/lng)
          example: 106.8242
        - name: radius
          in: query
          schema:
            type: number
            minimum: 0.1
            maximum: 50
            default: 5.0
          description: Search radius in kilometers
          example: 5.0
        - name: limit
          in: query
          schema:
            type: integer
            minimum: 1
            maximum: 50
            default: 10
          description: Maximum number of results
          example: 10
      responses:
        '200':
          description: Places retrieved successfully
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                    example: true
                  data:
                    type: array
                    items:
                      type: object
                      properties:
                        id:
                          type: integer
                          example: 1
                        name:
                          type: string
                          example: "Gerbang Utama UI (Gate 1)"
                        address:
                          type: string
                          example: "Jl. Margonda Raya, Depok"
                        coordinates:
                          type: object
                          properties:
                            latitude:
                              type: number
                              example: -6.3615
                            longitude:
                              type: number
                              example: 106.8242
                        is_beacon:
                          type: boolean
                          example: true
                          description: True for official pickup points, false for destinations
                        category:
                          type: string
                          example: "gate"
                          description: Place category (gate, faculty, canteen, etc.)
                        distance_km:
                          type: number
                          example: 0.5
                          description: Distance from user location (only if lat/lng provided)
                        usage_count:
                          type: integer
                          example: 150
                          description: Popularity metric
                        beacon_capacity:
                          type: integer
                          nullable: true
                          example: 15
                          description: Max drivers at beacon (beacons only)
                        current_queue_size:
                          type: integer
                          nullable: true
                          example: 3
                          description: Current drivers waiting (beacons only)
                        has_capacity:
                          type: boolean
                          nullable: true
                          example: true
                          description: Whether beacon has space (beacons only)
                  meta:
                    type: object
                    properties:
                      query:
                        type: string
                        example: "gate"
                      count:
                        type: integer
                        example: 4
                      has_proximity:
                        type: boolean
                        example: true
                        description: Whether results are sorted by distance
        '422':
          description: Validation error
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                    example: false
                  message:
                    type: string
                    example: "Validation failed"
                  errors:
                    type: object
                    example:
                      q: ["The q field is required."]
                      latitude: ["Both latitude and longitude must be provided together"]
        '500':
          description: Server error
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                    example: false
                  message:
                    type: string
                    example: "Failed to search places"
                  error:
                    type: string
                    example: "Internal server error"

  /location/autocomplete:
    get:
      tags:
        - Location
      summary: Autocomplete location search (DEPRECATED)
      description: |
        **DEPRECATED**: Use `/places/search` instead.
        This endpoint is kept for backward compatibility.
      parameters:
        - name: query
          in: query
          required: true
          schema:
            type: string
            minLength: 2
          description: Search query for location
        - name: limit
          in: query
          schema:
            type: integer
            minimum: 1
            maximum: 10
            default: 5
      responses:
        '200':
          description: Location suggestions retrieved successfully
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                    example: true
                  data:
                    type: array
                    items:
                      type: object
                      properties:
                        place_id:
                          type: string
                        name:
                          type: string
                        address:
                          type: string
                        location:
                          $ref: '#/components/schemas/Location'

components:
  securitySchemes:
    BearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT

  schemas:
    User:
      type: object
      properties:
        id:
          type: integer
          example: 1
        phone:
          type: string
          example: "+1234567890"
        name:
          type: string
          example: "John Doe"
        email:
          type: string
          format: email
          example: "john@university.edu"
        profile_picture:
          type: string
          format: uri
          example: "https://example.com/avatar.jpg"
        role:
          type: string
          enum: [rider, driver, both]
          example: "rider"
        is_verified:
          type: boolean
          example: true
        created_at:
          type: string
          format: date-time
          example: "2024-01-01T00:00:00Z"
        updated_at:
          type: string
          format: date-time
          example: "2024-01-01T00:00:00Z"

    Ride:
      type: object
      properties:
        id:
          type: integer
          example: 1
        rider_id:
          type: integer
          example: 1
        driver_id:
          type: integer
          nullable: true
          example: 2
        pickup_location:
          $ref: '#/components/schemas/Location'
        destination_location:
          $ref: '#/components/schemas/Location'
        status:
          type: string
          enum: [pending, accepted, in_progress, completed, cancelled]
          example: "pending"
        scheduled_time:
          type: string
          format: date-time
          nullable: true
          example: "2024-01-30T10:00:00Z"
        passenger_count:
          type: integer
          example: 1
        estimated_duration:
          type: integer
          description: Estimated duration in minutes
          example: 15
        estimated_distance:
          type: number
          description: Estimated distance in kilometers
          example: 5.2
        fare_estimate:
          type: number
          description: Estimated fare (for future implementation)
          example: 12.50
        notes:
          type: string
          nullable: true
          example: "Please pick me up at the main entrance"
        rider:
          $ref: '#/components/schemas/User'
        driver:
          allOf:
            - $ref: '#/components/schemas/User'
            - nullable: true
        created_at:
          type: string
          format: date-time
          example: "2024-01-30T09:00:00Z"
        updated_at:
          type: string
          format: date-time
          example: "2024-01-30T09:00:00Z"

    Location:
      type: object
      required:
        - latitude
        - longitude
        - address
      properties:
        latitude:
          type: number
          format: double
          minimum: -90
          maximum: 90
          example: 40.7128
        longitude:
          type: number
          format: double
          minimum: -180
          maximum: 180
          example: -74.0060
        address:
          type: string
          example: "123 University Ave, Campus City, ST 12345"
        name:
          type: string
          example: "Main Library"
          description: "Optional location name/landmark"

    Pagination:
      type: object
      properties:
        current_page:
          type: integer
          example: 1
        per_page:
          type: integer
          example: 10
        total:
          type: integer
          example: 150
        total_pages:
          type: integer
          example: 15
        has_more:
          type: boolean
          example: true

    Error:
      type: object
      properties:
        error:
          type: object
          properties:
            code:
              type: string
              example: "VALIDATION_ERROR"
            message:
              type: string
              example: "The given data was invalid."
            details:
              type: object

  responses:
    BadRequest:
      description: Bad request
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/Error'
          example:
            error:
              code: "VALIDATION_ERROR"
              message: "The given data was invalid."
              details:
                phone: ["The phone field is required."]

    Unauthorized:
      description: Unauthorized
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/Error'
          example:
            error:
              code: "UNAUTHORIZED"
              message: "Unauthenticated."
              details: {}

    NotFound:
      description: Resource not found
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/Error'
          example:
            error:
              code: "NOT_FOUND"
              message: "The requested resource was not found."
              details: {}

    TooManyRequests:
      description: Too many requests
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/Error'
          example:
            error:
              code: "RATE_LIMIT_EXCEEDED"
              message: "Too many requests. Please try again later."
              details:
                retry_after: 60

tags:
  - name: Authentication
    description: User authentication and session management
  - name: User Profile
    description: User profile management
  - name: Rides
    description: Ride request and management
  - name: Driver
    description: Driver-specific operations
  - name: Location
    description: Location services and geocoding
```

## API Usage Examples

### Authentication Flow

```javascript
// 1. Send OTP
const response = await fetch('/api/auth/send-otp', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    phone: '+1234567890'
  })
});

// 2. Verify OTP
const authResponse = await fetch('/api/auth/verify-otp', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    phone: '+1234567890',
    otp: '123456'
  })
});

const { data } = await authResponse.json();
const token = data.token;

// 3. Use token for authenticated requests
const ridesResponse = await fetch('/api/rides', {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
```

### Creating a Ride Request

```javascript
const rideData = {
  pickup_location: {
    latitude: 40.7128,
    longitude: -74.0060,
    address: "123 University Ave, Campus City, ST 12345",
    name: "Main Library"
  },
  destination_location: {
    latitude: 40.7589,
    longitude: -73.9851,
    address: "456 Campus Blvd, Campus City, ST 12345",
    name: "Student Center"
  },
  passenger_count: 1,
  notes: "I'll be waiting at the main entrance"
};

const response = await fetch('/api/rides', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify(rideData)
});
```

## Error Codes

| Code | Description | Common Causes |
|------|-------------|---------------|
| `VALIDATION_ERROR` | Request validation failed | Invalid input data, missing required fields |
| `UNAUTHORIZED` | Authentication required | Missing or invalid JWT token |
| `FORBIDDEN` | Access denied | Insufficient permissions |
| `NOT_FOUND` | Resource not found | Invalid ID, deleted resource |
| `CONFLICT` | Resource conflict | Duplicate ride request, driver already busy |
| `RATE_LIMIT_EXCEEDED` | Too many requests | Exceeding API rate limits |
| `OTP_INVALID` | Invalid OTP | Wrong OTP code, expired OTP |
| `OTP_EXPIRED` | OTP expired | OTP older than 5 minutes |
| `RIDE_NOT_AVAILABLE` | Ride not available | Ride already accepted, cancelled |
| `LOCATION_INVALID` | Invalid location | Coordinates out of service area |
| `DRIVER_OFFLINE` | Driver offline | Driver status is offline |

## Rate Limiting

- **Authenticated users**: 60 requests per minute
- **Unauthenticated users**: 10 requests per minute
- **OTP requests**: 3 requests per phone number per hour
- **Location autocomplete**: 30 requests per minute

## Webhooks (Future Implementation)

The API will support webhooks for real-time updates:

- `ride.created` - New ride request created
- `ride.accepted` - Ride accepted by driver
- `ride.started` - Ride in progress
- `ride.completed` - Ride completed
- `ride.cancelled` - Ride cancelled

## SDKs and Libraries

### Flutter SDK
```dart
// Add to pubspec.yaml
dependencies:
  anjem_api: ^1.0.0

// Usage
import 'package:anjem_api/anjem_api.dart';

final client = AnjemApiClient('https://api.anjem.app/v1');
await client.auth.sendOtp('+1234567890');
```

### Testing

Use the included Postman collection or OpenAPI specification with tools like:
- [Insomnia](https://insomnia.rest/)
- [Postman](https://www.postman.com/)
- [Swagger UI](https://swagger.io/tools/swagger-ui/)

Generate API client code using [OpenAPI Generator](https://openapi-generator.tech/) for various languages.