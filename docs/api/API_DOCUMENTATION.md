# Anjem API Documentation

**Version**: 2.0.0
**Last Updated**: November 30, 2025
**Status**: ✅ **PRODUCTION-READY** - Complete and accurate

---

## OpenAPI Specification

```yaml
openapi: 3.0.3
info:
  title: Anjem Ride-sharing API
  description: |
    Complete API documentation for the Anjem campus ride-sharing platform.

    ## Authentication

    The API uses **Firebase Authentication + Laravel Sanctum tokens** for security:

    1. **Firebase Auth**: Mobile apps authenticate via Firebase SDK
    2. **Backend Verification**: Backend verifies Firebase tokens
    3. **Sanctum Tokens**: Backend issues Laravel Sanctum tokens with role-based abilities
    4. **Token Usage**: Include Sanctum token in Authorization header for all protected endpoints

    ```
    Authorization: Bearer <sanctum_token>
    ```

    ## Rate Limiting

    - **Authentication endpoints**: 5 requests/minute
    - **Location updates**: 200 requests/minute
    - **Place search**: 60 requests/minute
    - **General protected endpoints**: 100 requests/minute
    - **Admin endpoints**: 100 requests/minute

    ## Error Handling

    All errors follow this consistent format:
    ```json
    {
      "success": false,
      "message": "Human readable error message",
      "error": "Technical error details (optional)",
      "errors": {
        "field": ["Validation error messages"]
      }
    }
    ```

    ## Base URL

    All endpoints are prefixed with `/api/v1` for versioned routes.
    Admin endpoints use `/api/admin` prefix.

  version: 2.0.0
  contact:
    name: Anjem Development Team
  license:
    name: MIT

servers:
  - url: https://api.anjem.app/api
    description: Production server
  - url: https://staging-api.anjem.app/api
    description: Staging server
  - url: http://localhost:8000/api
    description: Local development server

security:
  - BearerAuth: []

tags:
  - name: Authentication
    description: Firebase authentication and session management
  - name: User Profile
    description: User profile and settings
  - name: Ride Requests
    description: Rider ride request operations
  - name: Rides
    description: Ride management and lifecycle
  - name: Driver Operations
    description: Driver-specific operations (online status, location, statistics)
  - name: Driver KYC
    description: Driver verification and KYC submission
  - name: Locations
    description: Location and place search services
  - name: Admin - Drivers
    description: Admin driver management endpoints
  - name: Admin - Riders
    description: Admin rider management endpoints
  - name: Admin - Analytics
    description: Admin analytics and statistics
  - name: Admin - Monitoring
    description: Admin real-time monitoring

paths:
  # ========================================
  # Authentication Endpoints
  # ========================================

  /v1/auth/firebase:
    post:
      tags:
        - Authentication
      summary: Authenticate with Firebase token
      description: |
        Verify Firebase JWT token and get Sanctum access token.
        Creates a new user if first-time login.
      security: []
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - firebase_token
                - device_type
              properties:
                firebase_token:
                  type: string
                  description: Firebase ID token from mobile SDK
                  example: "eyJhbGciOiJSUzI1NiIsImtpZCI6..."
                device_type:
                  type: string
                  enum: [rider, driver]
                  description: User role for this session
                  example: "rider"
                device_id:
                  type: string
                  description: Device identifier (optional)
                  example: "android-device-123"
                fcm_token:
                  type: string
                  minLength: 50
                  description: FCM token for push notifications
                  example: "dQw4w9WgXcQ:APA91bH..."
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
                  user:
                    type: object
                    properties:
                      id:
                        type: integer
                        example: 1
                      name:
                        type: string
                        example: "John Doe"
                      email:
                        type: string
                        example: "john@example.com"
                      user_type:
                        type: string
                        enum: [rider, driver, both, admin]
                        example: "rider"
                      firebase_uid:
                        type: string
                        example: "firebase-uid-123"
                      is_active:
                        type: boolean
                        example: true
                  token:
                    type: string
                    description: Laravel Sanctum access token
                    example: "1|AbCdEfGhIjKlMnOpQrStUvWxYz..."
                  token_type:
                    type: string
                    example: "Bearer"
                  abilities:
                    type: array
                    items:
                      type: string
                    example: ["rider:request-ride", "rider:cancel-ride", "rider:rate-driver"]
        '401':
          description: Authentication failed
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Error'
              example:
                error: "Authentication failed"
                message: "Invalid Firebase token"
        '422':
          description: Validation error
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ValidationError'

  /v1/auth/google:
    get:
      tags:
        - Authentication
      summary: Get Google OAuth redirect URL
      description: Returns the Google OAuth redirect URL for social login
      security: []
      responses:
        '200':
          description: Redirect URL retrieved
          content:
            application/json:
              schema:
                type: object
                properties:
                  redirect_url:
                    type: string
                    format: uri
                    example: "https://accounts.google.com/o/oauth2/auth?..."

  /v1/auth/google/callback:
    get:
      tags:
        - Authentication
      summary: Google OAuth callback
      description: Handle Google OAuth callback and authenticate user
      security: []
      parameters:
        - name: code
          in: query
          required: true
          schema:
            type: string
          description: OAuth authorization code from Google
        - name: device_type
          in: query
          schema:
            type: string
            enum: [rider, driver]
            default: rider
          description: User role for this session
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
                  user:
                    $ref: '#/components/schemas/UserBasic'
                  token:
                    type: string
                    example: "1|AbCdEfGhIjKlMnOpQrStUvWxYz..."
                  token_type:
                    type: string
                    example: "Bearer"
                  abilities:
                    type: array
                    items:
                      type: string
        '401':
          $ref: '#/components/responses/Unauthorized'

  /v1/auth/refresh:
    post:
      tags:
        - Authentication
      summary: Refresh access token
      description: |
        Refresh the current Sanctum access token.
        Revokes the old token and issues a new one.
      responses:
        '200':
          description: Token refreshed successfully
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                    example: true
                  token:
                    type: string
                    example: "2|XyZaBcDeFgHiJkLmNoPqRsTuVw..."
                  token_type:
                    type: string
                    example: "Bearer"
        '401':
          $ref: '#/components/responses/Unauthorized'

  /v1/auth/logout:
    post:
      tags:
        - Authentication
      summary: Logout user
      description: |
        Revoke current Sanctum token and Firebase refresh tokens.
        User must re-authenticate to continue.
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

  /v1/auth/fcm-token:
    post:
      tags:
        - Authentication
      summary: Update FCM push notification token
      description: Update user's Firebase Cloud Messaging token for push notifications
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - fcm_token
              properties:
                fcm_token:
                  type: string
                  minLength: 50
                  example: "dQw4w9WgXcQ:APA91bH..."
      responses:
        '200':
          description: FCM token updated
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
                    example: "FCM token updated successfully"

  # ========================================
  # Health Check
  # ========================================

  /v1/health:
    get:
      tags:
        - System
      summary: Health check endpoint
      description: Check API server health status
      security: []
      responses:
        '200':
          description: Server is healthy
          content:
            application/json:
              schema:
                type: object
                properties:
                  status:
                    type: string
                    example: "ok"
                  timestamp:
                    type: string
                    format: date-time
                    example: "2025-11-30T12:00:00Z"
                  version:
                    type: string
                    example: "1.0.0"

  # ========================================
  # User Profile
  # ========================================

  /v1/user:
    get:
      tags:
        - User Profile
      summary: Get current user profile
      description: Retrieve authenticated user's profile with driver profile if applicable
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

  # ========================================
  # Places / Locations
  # ========================================

  /v1/places/search:
    get:
      tags:
        - Locations
      summary: Search for places (locations)
      description: |
        Search for campus locations using local database with Mapbox fallback.

        **Search Strategy:**
        1. Searches local database (PostgreSQL full-text search)
        2. Filters by proximity if lat/lng provided (PostGIS spatial query)
        3. Falls back to Mapbox Search API if < 5 results
        4. Auto-caches Mapbox results for future searches

        **No authentication required** - public endpoint
      security: []
      parameters:
        - name: q
          in: query
          required: true
          schema:
            type: string
            minLength: 2
            maxLength: 100
          description: Search query
          example: "gate"
        - name: latitude
          in: query
          schema:
            type: number
            format: double
            minimum: -90
            maximum: 90
          description: User latitude for proximity sorting
          example: -6.3615
        - name: longitude
          in: query
          schema:
            type: number
            format: double
            minimum: -180
            maximum: 180
          description: User longitude for proximity sorting
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
          description: Maximum results
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
                        distance_km:
                          type: number
                          nullable: true
                          example: 0.5
                          description: Distance from user (if lat/lng provided)
                        usage_count:
                          type: integer
                          example: 150
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
        '422':
          description: Validation error
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ValidationError'

  /v1/locations:
    get:
      tags:
        - Locations
      summary: Get all active locations
      description: Retrieve all active campus locations
      responses:
        '200':
          description: Locations retrieved
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
                      $ref: '#/components/schemas/Location'

  # ========================================
  # Ride Requests (Rider)
  # ========================================

  /v1/requests:
    get:
      tags:
        - Ride Requests
      summary: Get user's ride requests
      description: Retrieve ride requests for authenticated rider
      parameters:
        - name: status
          in: query
          schema:
            type: string
            enum: [pending, matched, in_progress, completed, cancelled, expired]
          description: Filter by status
        - name: include_expired
          in: query
          schema:
            type: boolean
          description: Include expired requests
      responses:
        '200':
          description: Ride requests retrieved
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
                      $ref: '#/components/schemas/RideRequest'
                  meta:
                    $ref: '#/components/schemas/Pagination'
        '403':
          $ref: '#/components/responses/Forbidden'

    post:
      tags:
        - Ride Requests
      summary: Create ride request
      description: |
        Create a new ride request (rider only).

        **Business Rules:**
        - Rider cannot request rides while online as driver
        - Cannot have multiple active ride requests
        - Pickup and destination must be different locations
        - Passenger count: 1-4 passengers
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - pickup_location_id
                - destination_location_id
                - passenger_count
              properties:
                pickup_location_id:
                  type: integer
                  description: Pickup location ID
                  example: 1
                destination_location_id:
                  type: integer
                  description: Destination location ID
                  example: 5
                passenger_count:
                  type: integer
                  minimum: 1
                  maximum: 4
                  example: 1
                special_requests:
                  type: string
                  maxLength: 500
                  nullable: true
                  example: "Please pick me up at the main entrance"
      responses:
        '201':
          description: Ride request created
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
                    example: "Ride request created successfully"
                  data:
                    $ref: '#/components/schemas/RideRequest'
        '400':
          description: Business logic error
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Error'
              examples:
                already_online:
                  value:
                    success: false
                    message: "You cannot request a ride while you are online as a driver. Please go offline first."
                active_request:
                  value:
                    success: false
                    message: "You already have an active ride request"
                    data:
                      id: 123
                      status: "pending"
        '422':
          $ref: '#/components/responses/ValidationError'

  /v1/requests/estimates:
    get:
      tags:
        - Ride Requests
      summary: Get ride cost estimates
      description: Calculate estimated distance, duration, and fare for a ride
      parameters:
        - name: pickup_location_id
          in: query
          required: true
          schema:
            type: integer
          example: 1
        - name: destination_location_id
          in: query
          required: true
          schema:
            type: integer
          example: 5
      responses:
        '200':
          description: Estimates calculated
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
                      distance_km:
                        type: number
                        example: 5.2
                      duration_minutes:
                        type: integer
                        example: 15
                      estimated_fare_rp:
                        type: integer
                        example: 12500
                      route_geometry:
                        type: string
                        description: Mapbox route geometry (polyline)
                        example: "encoded_polyline_string"

  /v1/requests/{ride_request}:
    get:
      tags:
        - Ride Requests
      summary: Get ride request details
      description: Retrieve specific ride request by ID
      parameters:
        - name: ride_request
          in: path
          required: true
          schema:
            type: integer
          example: 123
      responses:
        '200':
          description: Ride request retrieved
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                    example: true
                  data:
                    $ref: '#/components/schemas/RideRequest'
        '404':
          $ref: '#/components/responses/NotFound'

  /v1/requests/{ride_request}/cancel:
    patch:
      tags:
        - Ride Requests
      summary: Cancel ride request
      description: Cancel a pending or matched ride request
      parameters:
        - name: ride_request
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: Ride request cancelled
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
                    example: "Ride request cancelled"
        '400':
          description: Cannot cancel (already in progress)
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Error'

  # ========================================
  # Rides
  # ========================================

  /v1/rides:
    get:
      tags:
        - Rides
      summary: Get user's rides
      description: |
        Retrieve rides for authenticated user.
        - Riders see their rides as rider
        - Drivers see their rides as driver
        - Users with 'both' role see all their rides
      parameters:
        - name: status
          in: query
          schema:
            type: string
            enum: [matched, accepted, driver_arrived, in_progress, completed, cancelled]
        - name: page
          in: query
          schema:
            type: integer
            minimum: 1
            default: 1
        - name: per_page
          in: query
          schema:
            type: integer
            minimum: 1
            maximum: 50
            default: 20
      responses:
        '200':
          description: Rides retrieved
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
                  meta:
                    $ref: '#/components/schemas/Pagination'

  /v1/rides/{ride}:
    get:
      tags:
        - Rides
      summary: Get ride details
      description: Retrieve specific ride by ID
      parameters:
        - name: ride
          in: path
          required: true
          schema:
            type: integer
          example: 456
      responses:
        '200':
          description: Ride retrieved
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
        '403':
          description: Unauthorized to view this ride
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Error'
        '404':
          $ref: '#/components/responses/NotFound'

  /v1/rides/{rideRequest}/accept:
    post:
      tags:
        - Rides
      summary: Accept ride request (driver only)
      description: |
        Driver accepts a pending ride request.

        **Business Rules:**
        - Driver must be verified (KYC completed)
        - Driver must be online
        - Driver cannot have another active ride
        - Ride request must be in 'pending' status
        - Race condition handled: first driver to accept wins
      parameters:
        - name: rideRequest
          in: path
          required: true
          schema:
            type: integer
          description: Ride request ID to accept
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
                  message:
                    type: string
                    example: "Ride accepted successfully"
                  data:
                    $ref: '#/components/schemas/Ride'
        '400':
          description: Driver has active ride
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Error'
              example:
                success: false
                message: "You already have an active ride"
        '403':
          $ref: '#/components/responses/Forbidden'
        '404':
          description: Ride request expired/cancelled
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Error'
              example:
                success: false
                message: "Ride request not found or expired"
        '409':
          description: Already accepted by another driver
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Error'
              example:
                success: false
                message: "Ride request has already been accepted by another driver"

  /v1/rides/{ride}/status:
    patch:
      tags:
        - Rides
      summary: Update ride status
      description: |
        Update ride status through its lifecycle.

        **Status Transitions:**
        - `driver_arrived`: Driver arrived at pickup (driver only)
        - `in_progress`: Ride started (driver only)
        - `completed`: Ride finished (driver only, requires completion data)
        - `cancelled`: Ride cancelled (both rider and driver)
      parameters:
        - name: ride
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
              required:
                - status
              properties:
                status:
                  type: string
                  enum: [accepted, driver_arrived, in_progress, completed, cancelled]
                  example: "driver_arrived"
                actual_distance_km:
                  type: number
                  nullable: true
                  minimum: 0
                  maximum: 1000
                  description: Required if status is 'completed'
                  example: 5.3
                actual_duration_minutes:
                  type: integer
                  nullable: true
                  minimum: 0
                  maximum: 1440
                  description: Required if status is 'completed'
                  example: 18
                actual_fare_rp:
                  type: integer
                  nullable: true
                  minimum: 0
                  maximum: 100000
                  description: Required if status is 'completed'
                  example: 13000
                driver_notes:
                  type: string
                  nullable: true
                  maxLength: 500
                  example: "Traffic was heavy"
                cancel_reason:
                  type: string
                  nullable: true
                  maxLength: 255
                  description: Required if status is 'cancelled'
                  example: "Rider not responding"
      responses:
        '200':
          description: Status updated successfully
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
                    example: "Ride status updated to driver_arrived"
                  data:
                    $ref: '#/components/schemas/Ride'
        '400':
          description: Invalid status transition
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Error'
        '403':
          $ref: '#/components/responses/Forbidden'
        '422':
          $ref: '#/components/responses/ValidationError'

  /v1/rides/{ride}/rate:
    post:
      tags:
        - Rides
      summary: Rate completed ride
      description: |
        Submit rating and feedback for completed ride.
        - Riders rate drivers
        - Drivers rate riders
        - Can only rate once per ride
      parameters:
        - name: ride
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
              required:
                - rating
              properties:
                rating:
                  type: integer
                  minimum: 1
                  maximum: 5
                  example: 5
                feedback:
                  type: string
                  nullable: true
                  maxLength: 500
                  example: "Great driver, very professional!"
                tags:
                  type: array
                  nullable: true
                  items:
                    type: string
                    enum:
                      - safe_driving
                      - friendly
                      - punctual
                      - clean_vehicle
                      - smooth_ride
                      - professional
                      - helpful
                      - good_communication
                      - on_time
                  example: ["safe_driving", "friendly", "punctual"]
      responses:
        '200':
          description: Rating submitted
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
                    example: "Rating submitted successfully"
                  data:
                    type: object
                    properties:
                      id:
                        type: integer
                        example: 789
                      rating:
                        type: integer
                        example: 5
                      feedback:
                        type: string
                        example: "Great driver!"
                      tags:
                        type: array
                        items:
                          type: string
                        example: ["safe_driving", "friendly"]
        '400':
          description: Already rated or ride not completed
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Error'
        '422':
          $ref: '#/components/responses/ValidationError'

  # ========================================
  # Driver Operations
  # ========================================

  /v1/driver/online:
    post:
      tags:
        - Driver Operations
      summary: Go online (driver)
      description: |
        Mark driver as online and available to accept rides.

        **Requirements:**
        - Driver must be verified (KYC completed)
        - Cannot have active rides as rider
        - Cannot have pending ride requests as rider
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                current_latitude:
                  type: number
                  format: double
                  example: -6.3615
                current_longitude:
                  type: number
                  format: double
                  example: 106.8242
      responses:
        '200':
          description: Successfully went online
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
                    example: "Successfully went online. You can now accept ride requests."
                  data:
                    type: object
                    properties:
                      status:
                        type: string
                        example: "online"
                      is_available:
                        type: boolean
                        example: true
                      driver_id:
                        type: integer
                        example: 1
        '400':
          description: Cannot go online (has active ride/request)
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Error'
        '403':
          description: Driver not verified
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Error'
              example:
                success: false
                message: "Please complete driver verification (KYC) before going online"

  /v1/driver/offline:
    post:
      tags:
        - Driver Operations
      summary: Go offline (driver)
      description: |
        Mark driver as offline and unavailable.
        Cannot go offline if driver has active ride.
      responses:
        '200':
          description: Successfully went offline
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
                    example: "Successfully went offline"
                  data:
                    type: object
                    properties:
                      status:
                        type: string
                        example: "offline"
                      is_available:
                        type: boolean
                        example: false
        '400':
          description: Cannot go offline (has active ride)
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Error'

  /v1/driver/location:
    post:
      tags:
        - Driver Operations
      summary: Update driver location
      description: |
        Update driver's current location.
        **High rate limit**: 200 requests/minute for real-time tracking.
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - latitude
                - longitude
              properties:
                latitude:
                  type: number
                  format: double
                  example: -6.3615
                longitude:
                  type: number
                  format: double
                  example: 106.8242
                heading:
                  type: number
                  nullable: true
                  description: Direction in degrees (0-360)
                  example: 180
                speed:
                  type: number
                  nullable: true
                  description: Speed in km/h
                  example: 45.5
      responses:
        '200':
          description: Location updated
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
                    example: "Location updated successfully"

  /v1/driver/queue:
    get:
      tags:
        - Driver Operations
      summary: Get driver queue status
      description: Get current queue position and available beacons (legacy)
      responses:
        '200':
          description: Queue status retrieved
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

  /v1/driver/beacons:
    get:
      tags:
        - Driver Operations
      summary: Get available beacons (legacy)
      description: List available beacon locations with capacity info
      responses:
        '200':
          description: Beacons retrieved
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                  data:
                    type: array
                    items:
                      $ref: '#/components/schemas/Location'

  /v1/driver/statistics:
    get:
      tags:
        - Driver Operations
      summary: Get driver statistics
      description: Retrieve driver's earnings, rides, and performance metrics
      responses:
        '200':
          description: Statistics retrieved
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
                      total_rides:
                        type: integer
                        example: 150
                      total_earnings_rp:
                        type: integer
                        example: 1500000
                      average_rating:
                        type: number
                        example: 4.8
                      rating_count:
                        type: integer
                        example: 142
                      acceptance_rate:
                        type: number
                        description: Percentage of accepted rides
                        example: 95.5
                      online_hours_today:
                        type: number
                        example: 6.5
                      rides_today:
                        type: integer
                        example: 12
                      earnings_today_rp:
                        type: integer
                        example: 120000

  # ========================================
  # Driver KYC
  # ========================================

  /v1/driver/kyc/check-email:
    post:
      tags:
        - Driver KYC
      summary: Check student email availability
      description: |
        Check if student email is available for registration.
        Validates email domain and uniqueness.
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - student_email
              properties:
                student_email:
                  type: string
                  format: email
                  example: "john.doe@ui.ac.id"
      responses:
        '200':
          description: Email checked
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                    example: true
                  available:
                    type: boolean
                    example: true
                  message:
                    type: string
                    example: "Email is available"
                  reason:
                    type: string
                    nullable: true
                    enum: [null, invalid_domain, already_registered]
                    example: null

  /v1/driver/kyc/submit:
    post:
      tags:
        - Driver KYC
      summary: Submit KYC data
      description: |
        Submit driver verification data including student info and vehicle details.
        Requires student email, ID, KTM photo, and vehicle information.
      requestBody:
        required: true
        content:
          multipart/form-data:
            schema:
              type: object
              required:
                - student_email
                - student_id
                - student_name
                - vehicle_type
                - vehicle_plate
                - vehicle_color
                - ktm_photo
              properties:
                student_email:
                  type: string
                  format: email
                  description: Must be from allowed domain (e.g., @ui.ac.id)
                  example: "john.doe@ui.ac.id"
                student_id:
                  type: string
                  maxLength: 50
                  example: "2106123456"
                student_name:
                  type: string
                  maxLength: 255
                  example: "John Doe"
                vehicle_type:
                  type: string
                  enum: [motorcycle, car]
                  example: "motorcycle"
                vehicle_plate:
                  type: string
                  maxLength: 20
                  example: "B 1234 XYZ"
                vehicle_color:
                  type: string
                  maxLength: 50
                  example: "Black"
                ktm_photo:
                  type: string
                  format: binary
                  description: Student ID card photo (JPEG/PNG, max 5MB)
      responses:
        '200':
          description: KYC submitted successfully
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
                    example: "KYC data submitted successfully. Please verify your student email."
                  data:
                    type: object
                    properties:
                      kyc_submitted:
                        type: boolean
                        example: true
                      email_verified:
                        type: boolean
                        example: false
                      is_verified:
                        type: boolean
                        example: false
                      student_email:
                        type: string
                        example: "john.doe@ui.ac.id"
        '422':
          $ref: '#/components/responses/ValidationError'
        '500':
          description: Server error during KYC submission
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Error'

  /v1/driver/kyc/send-code:
    post:
      tags:
        - Driver KYC
      summary: Send email verification code
      description: |
        Send 6-digit OTP to student email for verification.
        Code expires in 10 minutes.
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - student_email
              properties:
                student_email:
                  type: string
                  format: email
                  example: "john.doe@ui.ac.id"
      responses:
        '200':
          description: Verification code sent
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
                    example: "Verification code sent to john.doe@ui.ac.id"
                  expires_in_minutes:
                    type: integer
                    example: 10

  /v1/driver/kyc/verify-email:
    post:
      tags:
        - Driver KYC
      summary: Verify email with code
      description: |
        Verify student email using 6-digit OTP.
        Auto-approves driver after successful verification.
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - student_email
                - code
              properties:
                student_email:
                  type: string
                  format: email
                  example: "john.doe@ui.ac.id"
                code:
                  type: string
                  pattern: '^\d{6}$'
                  example: "123456"
      responses:
        '200':
          description: Email verified successfully
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
                    example: "Email verified successfully. You are now approved as a driver!"
                  data:
                    type: object
                    properties:
                      email_verified:
                        type: boolean
                        example: true
                      is_verified:
                        type: boolean
                        example: true
                      can_go_online:
                        type: boolean
                        example: true
        '400':
          description: Invalid or expired code
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Error'
              examples:
                invalid_code:
                  value:
                    success: false
                    message: "Invalid verification code"
                expired_code:
                  value:
                    success: false
                    message: "Verification code has expired"

  /v1/driver/kyc/status:
    get:
      tags:
        - Driver KYC
      summary: Get KYC verification status
      description: Retrieve current KYC status for authenticated driver
      responses:
        '200':
          description: KYC status retrieved
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
                      has_submitted_kyc:
                        type: boolean
                        example: true
                      email_verified:
                        type: boolean
                        example: true
                      is_verified:
                        type: boolean
                        example: true
                      can_go_online:
                        type: boolean
                        example: true
                      student_email:
                        type: string
                        nullable: true
                        example: "john.doe@ui.ac.id"
                      vehicle_type:
                        type: string
                        nullable: true
                        example: "motorcycle"
                      vehicle_plate:
                        type: string
                        nullable: true
                        example: "B 1234 XYZ"

  # ========================================
  # Admin - Driver Management
  # ========================================

  /admin/drivers:
    get:
      tags:
        - Admin - Drivers
      summary: List all drivers (admin)
      description: |
        Get paginated list of all drivers with filters.
        **Requires admin role**.
      parameters:
        - name: search
          in: query
          schema:
            type: string
          description: Search by name or email
        - name: kyc_status
          in: query
          schema:
            type: string
            enum: [pending, email_verified, approved]
          description: Filter by KYC status
        - name: is_online
          in: query
          schema:
            type: boolean
          description: Filter by online status
        - name: page
          in: query
          schema:
            type: integer
            default: 1
        - name: per_page
          in: query
          schema:
            type: integer
            default: 20
            maximum: 100
      responses:
        '200':
          description: Drivers list retrieved
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
                      $ref: '#/components/schemas/AdminDriverSummary'
                  meta:
                    $ref: '#/components/schemas/Pagination'
        '403':
          $ref: '#/components/responses/Forbidden'

  /admin/drivers/{id}:
    get:
      tags:
        - Admin - Drivers
      summary: Get driver details (admin)
      description: Get detailed driver information with statistics
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: Driver details retrieved
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                    example: true
                  data:
                    $ref: '#/components/schemas/AdminDriverDetail'

  /admin/drivers/{id}/suspend:
    post:
      tags:
        - Admin - Drivers
      summary: Suspend/unsuspend driver (admin)
      description: Toggle driver active status
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
                  example: "Violation of terms"
      responses:
        '200':
          description: Driver status updated
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
                    example: "Driver suspended successfully"

  # ========================================
  # Admin - Rider Management
  # ========================================

  /admin/riders:
    get:
      tags:
        - Admin - Riders
      summary: List all riders (admin)
      parameters:
        - name: search
          in: query
          schema:
            type: string
        - name: page
          in: query
          schema:
            type: integer
            default: 1
        - name: per_page
          in: query
          schema:
            type: integer
            default: 20
      responses:
        '200':
          description: Riders list retrieved
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
                  meta:
                    $ref: '#/components/schemas/Pagination'

  /admin/riders/{id}:
    get:
      tags:
        - Admin - Riders
      summary: Get rider details (admin)
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: Rider details retrieved

  /admin/riders/{id}/suspend:
    post:
      tags:
        - Admin - Riders
      summary: Suspend/unsuspend rider (admin)
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: Rider status updated

  # ========================================
  # Admin - Analytics
  # ========================================

  /admin/analytics/overview:
    get:
      tags:
        - Admin - Analytics
      summary: Get platform overview (admin)
      description: Get high-level platform statistics including route cache performance
      responses:
        '200':
          description: Overview retrieved
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
                      total_users:
                        type: integer
                        example: 5000
                      total_drivers:
                        type: integer
                        example: 800
                      total_riders:
                        type: integer
                        example: 4200
                      online_drivers:
                        type: integer
                        example: 45
                      active_rides:
                        type: integer
                        example: 12
                      total_rides:
                        type: integer
                        example: 25000
                      completed_rides:
                        type: integer
                        example: 23500
                      total_revenue_rp:
                        type: integer
                        example: 250000000
                      revenue_last_30_days_rp:
                        type: integer
                        example: 35000000
                      route_cache_stats:
                        type: object
                        properties:
                          total_cached_routes:
                            type: integer
                            example: 1250
                          cache_hit_rate:
                            type: number
                            example: 87.5
                          total_cache_hits:
                            type: integer
                            example: 21875
                          api_cost_savings_percentage:
                            type: number
                            example: 87.5

  /admin/analytics/rides:
    get:
      tags:
        - Admin - Analytics
      summary: Get ride analytics (admin)
      description: Get detailed ride statistics with date range filter
      parameters:
        - name: start_date
          in: query
          schema:
            type: string
            format: date
          example: "2025-11-01"
        - name: end_date
          in: query
          schema:
            type: string
            format: date
          example: "2025-11-30"
      responses:
        '200':
          description: Ride analytics retrieved

  /admin/analytics/popular-routes:
    get:
      tags:
        - Admin - Analytics
      summary: Get popular routes (admin)
      description: |
        Get most popular routes from two sources:
        - Cached routes (shows API optimization impact)
        - Actual rides (shows business insights)
      parameters:
        - name: limit
          in: query
          schema:
            type: integer
            default: 10
      responses:
        '200':
          description: Popular routes retrieved

  /admin/analytics/driver-performance:
    get:
      tags:
        - Admin - Analytics
      summary: Get driver performance leaderboard (admin)
      parameters:
        - name: limit
          in: query
          schema:
            type: integer
            default: 10
      responses:
        '200':
          description: Driver performance retrieved

  # ========================================
  # Admin - Monitoring
  # ========================================

  /admin/monitoring/active-rides:
    get:
      tags:
        - Admin - Monitoring
      summary: Get currently active rides (admin)
      description: Real-time view of ongoing rides
      responses:
        '200':
          description: Active rides retrieved

  /admin/monitoring/online-drivers:
    get:
      tags:
        - Admin - Monitoring
      summary: Get online drivers (admin)
      description: List of currently online drivers with locations
      responses:
        '200':
          description: Online drivers retrieved

  /admin/monitoring/pending-requests:
    get:
      tags:
        - Admin - Monitoring
      summary: Get pending ride requests (admin)
      description: Ride requests waiting for drivers
      responses:
        '200':
          description: Pending requests retrieved

  /admin/monitoring/requests/{id}:
    delete:
      tags:
        - Admin - Monitoring
      summary: Cancel ride request (admin)
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: Request cancelled

  /admin/monitoring/rides/{id}/cancel:
    post:
      tags:
        - Admin - Monitoring
      summary: Cancel ride (admin)
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: Ride cancelled

  /admin/monitoring/rides/{id}/complete:
    post:
      tags:
        - Admin - Monitoring
      summary: Force complete ride (admin)
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: Ride completed

# ========================================
# Components
# ========================================

components:
  securitySchemes:
    BearerAuth:
      type: http
      scheme: bearer
      bearerFormat: "Laravel Sanctum Token"
      description: |
        Use Laravel Sanctum token obtained from authentication endpoints.
        Format: `Authorization: Bearer <token>`

  schemas:
    # Basic User Schema
    UserBasic:
      type: object
      properties:
        id:
          type: integer
          example: 1
        name:
          type: string
          example: "John Doe"
        email:
          type: string
          format: email
          example: "john@example.com"
        user_type:
          type: string
          enum: [rider, driver, both, admin]
          description: User role (maps to 'role' in database)
          example: "rider"
        firebase_uid:
          type: string
          example: "firebase-uid-123"
        is_active:
          type: boolean
          example: true

    # Full User Schema (with driver profile)
    User:
      allOf:
        - $ref: '#/components/schemas/UserBasic'
        - type: object
          properties:
            email_verified_at:
              type: string
              format: date-time
              nullable: true
              example: "2025-11-01T10:00:00Z"
            last_active_at:
              type: string
              format: date-time
              nullable: true
              example: "2025-11-30T12:00:00Z"
            created_at:
              type: string
              format: date-time
              example: "2025-10-01T08:00:00Z"
            updated_at:
              type: string
              format: date-time
              example: "2025-11-30T12:00:00Z"
            driver_profile:
              nullable: true
              type: object
              description: Only present if user is driver/both/admin
              properties:
                id:
                  type: integer
                  example: 1
                user_id:
                  type: integer
                  example: 1
                license_number:
                  type: string
                  example: "DL123456"
                vehicle_info:
                  type: object
                  properties:
                    type:
                      type: string
                      enum: [motorcycle, car]
                      example: "motorcycle"
                    make:
                      type: string
                      nullable: true
                      example: "Honda"
                    model:
                      type: string
                      nullable: true
                      example: "Beat"
                    year:
                      type: integer
                      nullable: true
                      example: 2020
                    color:
                      type: string
                      example: "Black"
                    plate:
                      type: string
                      example: "B 1234 XYZ"
                vehicle_type:
                  type: string
                  enum: [motorcycle, car]
                  example: "motorcycle"
                vehicle_model:
                  type: string
                  nullable: true
                  example: "Beat"
                vehicle_year:
                  type: integer
                  nullable: true
                  example: 2020
                plate_number:
                  type: string
                  example: "B 1234 XYZ"
                is_verified:
                  type: boolean
                  example: true
                is_available:
                  type: boolean
                  example: true
                status:
                  type: string
                  enum: [online, offline]
                  example: "online"
                rating:
                  type: number
                  format: float
                  example: 4.8
                rating_count:
                  type: integer
                  example: 150
                total_rides:
                  type: integer
                  example: 200
                created_at:
                  type: string
                  format: date-time
                updated_at:
                  type: string
                  format: date-time

    # Location Schema
    Location:
      type: object
      properties:
        id:
          type: integer
          example: 1
        name:
          type: string
          example: "Gerbang Utama UI (Gate 1)"
        type:
          type: string
          enum: [beacon, p2p]
          example: "beacon"
        is_beacon:
          type: boolean
          example: true
        is_active:
          type: boolean
          example: true
        beacon_capacity:
          type: integer
          nullable: true
          example: 15
          description: Only for beacons
        queue_count:
          type: integer
          nullable: true
          example: 3
          description: Only for beacons
        current_queue_size:
          type: integer
          nullable: true
          example: 3
          description: Only for beacons (same as queue_count)
        has_capacity:
          type: boolean
          nullable: true
          example: true
          description: Only for beacons
        coordinates:
          type: object
          properties:
            latitude:
              type: number
              format: double
              example: -6.3615
            longitude:
              type: number
              format: double
              example: 106.8242
        address:
          type: string
          example: "Jl. Margonda Raya, Depok"
        description:
          type: string
          nullable: true
          example: "Main entrance gate"
        created_at:
          type: string
          format: date-time
        updated_at:
          type: string
          format: date-time

    # Ride Request Schema
    RideRequest:
      type: object
      properties:
        id:
          type: integer
          example: 123
        status:
          type: string
          enum: [pending, matched, in_progress, completed, cancelled, expired]
          example: "pending"
        passenger_count:
          type: integer
          minimum: 1
          maximum: 4
          example: 1
        estimated_distance_km:
          type: number
          nullable: true
          example: 5.2
        estimated_duration_minutes:
          type: integer
          nullable: true
          example: 15
        estimated_fare_rp:
          type: integer
          nullable: true
          example: 12500
        special_requests:
          type: string
          nullable: true
          maxLength: 500
          example: "Please pick me up at the main entrance"
        expires_at:
          type: string
          format: date-time
          nullable: true
          example: "2025-11-30T13:00:00Z"
        matched_at:
          type: string
          format: date-time
          nullable: true
          example: null
        created_at:
          type: string
          format: date-time
          example: "2025-11-30T12:45:00Z"
        updated_at:
          type: string
          format: date-time
          example: "2025-11-30T12:45:00Z"
        rider:
          $ref: '#/components/schemas/UserBasic'
        pickup_location:
          $ref: '#/components/schemas/Location'
        destination_location:
          $ref: '#/components/schemas/Location'
        is_expired:
          type: boolean
          example: false
        is_active:
          type: boolean
          example: true
        can_cancel:
          type: boolean
          example: true

    # Ride Schema
    Ride:
      type: object
      properties:
        id:
          type: integer
          example: 456
        ride_request_id:
          type: integer
          example: 123
        status:
          type: string
          enum: [matched, accepted, driver_arrived, in_progress, completed, cancelled]
          example: "in_progress"
        passenger_count:
          type: integer
          example: 1
        estimated_fare_rp:
          type: integer
          nullable: true
          example: 12500
        actual_fare_rp:
          type: integer
          nullable: true
          example: 13000
        final_fare_rp:
          type: integer
          description: Actual fare if set, otherwise estimated fare
          example: 13000
        actual_distance_km:
          type: number
          nullable: true
          example: 5.3
        actual_duration_minutes:
          type: integer
          nullable: true
          example: 18
        special_requests:
          type: string
          nullable: true
          example: "Please pick me up at the main entrance"
        driver_notes:
          type: string
          nullable: true
          example: "Traffic was heavy"
        driver_accepted_at:
          type: string
          format: date-time
          nullable: true
          example: "2025-11-30T12:46:00Z"
        pickup_time:
          type: string
          format: date-time
          nullable: true
          example: "2025-11-30T12:50:00Z"
        dropoff_time:
          type: string
          format: date-time
          nullable: true
          example: "2025-11-30T13:08:00Z"
        created_at:
          type: string
          format: date-time
          example: "2025-11-30T12:46:00Z"
        updated_at:
          type: string
          format: date-time
          example: "2025-11-30T13:08:00Z"
        rider:
          $ref: '#/components/schemas/UserBasic'
        driver:
          $ref: '#/components/schemas/UserBasic'
        pickup_location:
          $ref: '#/components/schemas/Location'
        destination_location:
          $ref: '#/components/schemas/Location'
        ride_request:
          $ref: '#/components/schemas/RideRequest'
        ratings:
          type: array
          items:
            type: object
            properties:
              id:
                type: integer
              rating:
                type: integer
                minimum: 1
                maximum: 5
              feedback:
                type: string
                nullable: true
              tags:
                type: array
                items:
                  type: string
        can_rate:
          type: boolean
          nullable: true
          description: Whether current user can rate this ride
          example: true

    # Admin Driver Summary
    AdminDriverSummary:
      type: object
      properties:
        id:
          type: integer
          example: 1
        name:
          type: string
          example: "John Driver"
        email:
          type: string
          example: "john.driver@ui.ac.id"
        phone:
          type: string
          nullable: true
          example: "+6281234567890"
        is_active:
          type: boolean
          example: true
        role:
          type: string
          enum: [driver, both, admin]
          example: "driver"
        kyc_status:
          type: string
          enum: [pending, email_verified, approved]
          example: "approved"
        vehicle_type:
          type: string
          nullable: true
          enum: [motorcycle, car]
          example: "motorcycle"
        vehicle_plate:
          type: string
          nullable: true
          example: "B 1234 XYZ"
        vehicle_color:
          type: string
          nullable: true
          example: "Black"
        rating:
          type: number
          example: 4.8
        rating_count:
          type: integer
          example: 150
        is_online:
          type: boolean
          example: true
        created_at:
          type: string
          format: date-time

    # Admin Driver Detail
    AdminDriverDetail:
      allOf:
        - $ref: '#/components/schemas/AdminDriverSummary'
        - type: object
          properties:
            student_email:
              type: string
              nullable: true
              example: "john.driver@ui.ac.id"
            student_id:
              type: string
              nullable: true
              example: "2106123456"
            ktm_url:
              type: string
              nullable: true
              format: uri
              example: "https://storage.example.com/ktm/123.jpg"
            email_verified_at:
              type: string
              format: date-time
              nullable: true
            statistics:
              type: object
              properties:
                total_rides:
                  type: integer
                  example: 200
                completed_rides:
                  type: integer
                  example: 195
                cancelled_rides:
                  type: integer
                  example: 5
                total_earnings_rp:
                  type: integer
                  example: 2000000
                acceptance_rate:
                  type: number
                  example: 95.5
            recent_rides:
              type: array
              items:
                type: object
                properties:
                  id:
                    type: integer
                  rider_name:
                    type: string
                  status:
                    type: string
                  fare_rp:
                    type: integer
                  created_at:
                    type: string
                    format: date-time

    # Pagination
    Pagination:
      type: object
      properties:
        current_page:
          type: integer
          example: 1
        last_page:
          type: integer
          example: 10
        per_page:
          type: integer
          example: 20
        total:
          type: integer
          example: 200

    # Error Schema
    Error:
      type: object
      properties:
        success:
          type: boolean
          example: false
        message:
          type: string
          example: "Error message"
        error:
          type: string
          nullable: true
          example: "Technical error details"

    # Validation Error Schema
    ValidationError:
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
          additionalProperties:
            type: array
            items:
              type: string
          example:
            email: ["The email field is required."]
            password: ["The password must be at least 8 characters."]

  responses:
    Unauthorized:
      description: Unauthorized - Invalid or missing token
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/Error'
          example:
            success: false
            message: "Unauthenticated."

    Forbidden:
      description: Forbidden - Insufficient permissions
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/Error'
          example:
            success: false
            message: "Unauthorized: Required permissions not found"

    NotFound:
      description: Resource not found
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/Error'
          example:
            success: false
            message: "Resource not found"

    ValidationError:
      description: Validation error
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/ValidationError'
```

---

## WebSocket Events (Laravel Reverb)

The API supports real-time features via Laravel Reverb WebSockets.

### Connection

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: process.env.REVERB_APP_KEY,
    wsHost: process.env.REVERB_HOST,
    wsPort: process.env.REVERB_PORT,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/api/broadcasting/auth',
    auth: {
        headers: {
            Authorization: `Bearer ${token}`
        }
    }
});
```

### Available Channels

#### 1. Driver Channels (Private)

**Channel**: `private-driver.{driver_id}`

**Events**:
- `NewRideRequest`: New ride request available
- `RideRequestCancelled`: Ride request was cancelled
- `RideStatusUpdated`: Ride status changed

#### 2. Rider Channels (Private)

**Channel**: `private-rider.{rider_id}`

**Events**:
- `RideRequestMatched`: Ride request accepted by driver
- `DriverLocationUpdated`: Driver location updated
- `RideStatusUpdated`: Ride status changed

#### 3. Ride Channels (Private)

**Channel**: `private-ride.{ride_id}`

**Events**:
- `RideStatusUpdated`: Ride status changed
- `DriverLocationUpdated`: Driver location updated (during active ride)

### Event Examples

**NewRideRequest Event**:
```json
{
  "ride_request": {
    "id": 123,
    "rider": {...},
    "pickup_location": {...},
    "destination_location": {...},
    "estimated_fare_rp": 12500
  }
}
```

**DriverLocationUpdated Event**:
```json
{
  "driver_id": 1,
  "location": {
    "latitude": -6.3615,
    "longitude": 106.8242,
    "heading": 180,
    "speed": 45.5
  },
  "timestamp": "2025-11-30T12:00:00Z"
}
```

---

## Rate Limiting Details

| Endpoint Category | Limit | Window |
|------------------|-------|--------|
| `/api/v1/auth/*` | 5 requests | 1 minute |
| `/api/v1/driver/location` | 200 requests | 1 minute |
| `/api/v1/places/search` | 60 requests | 1 minute |
| Protected endpoints | 100 requests | 1 minute |
| Admin endpoints | 100 requests | 1 minute |

**Rate Limit Headers**:
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1701345600
```

---

## Token Abilities (Sanctum)

Tokens are issued with role-based abilities:

### Rider Abilities
- `rider:request-ride`
- `rider:cancel-ride`
- `rider:rate-driver`

### Driver Abilities
- `driver:go-online`
- `driver:accept-ride`
- `driver:complete-ride`
- `driver:update-location`
- `driver:rate-rider`

### Both Role
Has all rider + driver abilities

### Admin Role
Has all abilities

---

## Testing & Development

### Local Development

```bash
# Start Laravel development server
php artisan serve

# API base URL
http://localhost:8000/api/v1
```

### Authentication for Testing

1. **Firebase Auth** (Production):
   - Use Firebase SDK in mobile app
   - Exchange Firebase token for Sanctum token

2. **Admin Token** (Development):
   ```bash
   php artisan tinker
   $admin = User::where('email', 'admin@anjem.app')->first();
   $token = $admin->createTokenWithAbilities(false, true);
   echo $token;
   ```

3. **Test with cURL**:
   ```bash
   curl -X GET "http://localhost:8000/api/v1/user" \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Accept: application/json"
   ```

---

## Migration from v1.0

### Breaking Changes

❌ **Removed** (v1.0 → v2.0):
- `POST /auth/send-otp` - Use Firebase authentication
- `POST /auth/verify-otp` - Use Firebase authentication
- All OTP-related endpoints

✅ **Added** (v2.0):
- Firebase authentication flow
- Google OAuth endpoints
- 5 Driver KYC endpoints
- 14 Admin endpoints
- WebSocket real-time events

⚠️ **Changed**:
- User responses now return `user_type` (backward compatible, maps to `role` internally)
- Ride request creation requires `pickup_location_id` / `destination_location_id` (supports legacy `pickup_beacon_id` / `destination_beacon_id`)

---

## Support & Resources

- **API Base URL**: `http://localhost:8000/api`
- **OpenAPI File**: Import this YAML spec into Postman/Insomnia
- **Source Code**: `/backend/routes/api.php`
- **Controllers**: `/backend/app/Http/Controllers/Api/`
- **Issue Tracker**: GitHub Issues

---

**Last Updated**: November 30, 2025
**Version**: 2.0.0
**Maintained By**: Anjem Development Team
