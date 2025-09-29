# Anjem Backend API

Laravel-based REST API server for the Anjem ride-sharing platform, handling authentication, ride management, and real-time updates.

## Overview

This Laravel application serves as the backend API for the Anjem ride-sharing platform. It provides endpoints for user authentication, ride requests, driver matching, and real-time communication between riders and drivers.

## Features

- **Firebase Authentication**: Email-based authentication with Firebase integration
- **Sanctum Authorization**: Token-based API authorization with role-based permissions
- **Ride Management**: Create, accept, track, and complete rides with spatial queries
- **Real-time Updates**: WebSocket support for live ride tracking
- **PostGIS Integration**: Advanced geolocation services with spatial indexing
- **Rate Limiting**: API rate limiting for security and performance
- **Background Jobs**: Queue processing for notifications and heavy tasks

## Requirements

- **PHP**: 8.2+
- **Composer**: Latest version
- **PostgreSQL**: 13+ with PostGIS extension
- **Redis**: 6.0+ (for caching and queues)
- **Node.js**: 18+ (for asset compilation)
- **Firebase**: Account for authentication services

## Quick Start

1. **Install dependencies**
   ```bash
   cd backend
   composer install
   npm install
   ```

2. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Database setup**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

4. **Start development server**
   ```bash
   php artisan serve
   ```

API will be available at: http://localhost:8000

## Project Structure

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/        # API controllers
│   │   ├── Middleware/         # Custom middleware
│   │   ├── Requests/           # Form request validators
│   │   └── Resources/          # API resources (transformers)
│   ├── Models/                 # Eloquent models
│   ├── Services/               # Business logic services
│   ├── Repositories/           # Data access layer
│   ├── Events/                 # Domain events
│   ├── Listeners/              # Event listeners
│   ├── Jobs/                   # Background jobs
│   └── Exceptions/             # Custom exceptions
├── config/                     # Configuration files
├── database/
│   ├── migrations/             # Database migrations
│   ├── seeders/               # Database seeders
│   └── factories/             # Model factories
├── routes/
│   ├── api.php                # API routes
│   ├── web.php                # Web routes
│   └── channels.php           # Broadcasting channels
├── storage/                    # File storage, logs, cache
└── tests/                     # PHPUnit tests
```

## Environment Configuration

### Database Configuration

```env
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=anjemme
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### Redis Configuration

```env
REDIS_HOST=localhost
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
```

### Third-party Services

```env
# Firebase Authentication
FIREBASE_PROJECT_ID=your_firebase_project_id
FIREBASE_PRIVATE_KEY_ID=your_private_key_id
FIREBASE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nyour_private_key\n-----END PRIVATE KEY-----\n"
FIREBASE_CLIENT_EMAIL=your_service_account_email
FIREBASE_CLIENT_ID=your_client_id
FIREBASE_AUTH_URI=https://accounts.google.com/o/oauth2/auth
FIREBASE_TOKEN_URI=https://oauth2.googleapis.com/token

# Google Services (for location estimation)
GOOGLE_MAPS_API_KEY=your_google_maps_api_key

# Broadcasting (using Laravel Reverb)
BROADCAST_DRIVER=reverb
REVERB_APP_ID=your_app_id
REVERB_APP_KEY=your_app_key
REVERB_APP_SECRET=your_app_secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

## Essential Artisan Commands

### Development Commands

```bash
# Generate application key
php artisan key:generate

# Start development server
php artisan serve

# Start queue worker
php artisan queue:work

# Clear various caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Restart queue workers
php artisan queue:restart
```

### Database Commands

```bash
# Run migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback

# Fresh migration with seeding
php artisan migrate:fresh --seed

# Create new migration
php artisan make:migration create_rides_table

# Create new seeder
php artisan make:seeder UsersTableSeeder

# Run specific seeder
php artisan db:seed --class=UsersTableSeeder
```

### Code Generation Commands

```bash
# Generate controller
php artisan make:controller RideController --api

# Generate model with migration
php artisan make:model Ride -m

# Generate service class
php artisan make:service RideMatchingService

# Generate form request
php artisan make:request StoreRideRequest

# Generate resource
php artisan make:resource RideResource

# Generate job
php artisan make:job SendRideNotification

# Generate event
php artisan make:event RideCompleted

# Generate listener
php artisan make:listener UpdateRideStatistics
```

### Testing Commands

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run tests with coverage
php artisan test --coverage

# Create new test
php artisan make:test RideControllerTest
```

## API Development

### Creating Controllers

```bash
# API controller with resource methods
php artisan make:controller Api/RideController --api --model=Ride
```

### Form Validation

```bash
# Create request class
php artisan make:request StoreRideRequest
```

Example request validation:
```php
public function rules(): array
{
    return [
        'pickup_location.latitude' => 'required|numeric|between:-90,90',
        'pickup_location.longitude' => 'required|numeric|between:-180,180',
        'pickup_location.address' => 'required|string|max:255',
        'destination_location.latitude' => 'required|numeric|between:-90,90',
        'destination_location.longitude' => 'required|numeric|between:-180,180',
        'destination_location.address' => 'required|string|max:255',
        'passenger_count' => 'required|integer|min:1|max:4',
    ];
}
```

### API Resources

```bash
# Create resource for JSON transformation
php artisan make:resource RideResource
```

Example resource:
```php
public function toArray($request): array
{
    return [
        'id' => $this->id,
        'status' => $this->status,
        'pickup_location' => $this->pickup_location,
        'destination_location' => $this->destination_location,
        'rider' => new UserResource($this->whenLoaded('rider')),
        'driver' => new UserResource($this->whenLoaded('driver')),
        'created_at' => $this->created_at,
        'updated_at' => $this->updated_at,
    ];
}
```

## Background Jobs

### Queue Configuration

```env
QUEUE_CONNECTION=redis
```

### Creating Jobs

```bash
# Create job class
php artisan make:job SendRideNotification
```

### Running Workers

```bash
# Start queue worker
php artisan queue:work

# Process specific queue
php artisan queue:work --queue=high,default

# Process jobs with timeout
php artisan queue:work --timeout=60
```

## Real-time Features

### Broadcasting Events

```bash
# Create event class
php artisan make:event RideStatusUpdated
```

### WebSocket Integration

Configure Laravel Echo for real-time updates:

```javascript
// Frontend WebSocket client
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY,
    cluster: process.env.MIX_PUSHER_APP_CLUSTER,
});

// Listen for ride updates
window.Echo.channel('ride.' + rideId)
    .listen('RideStatusUpdated', (e) => {
        console.log('Ride status updated:', e.ride);
    });
```

## Testing

### Test Structure

```bash
# Feature tests (HTTP endpoints)
php artisan make:test Api/RideControllerTest

# Unit tests (individual classes)
php artisan make:test Unit/RideServiceTest --unit
```

### Database Testing

Use factories and refresh database:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class RideControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_ride(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/api/rides', [
            'pickup_location' => [
                'latitude' => 40.7128,
                'longitude' => -74.0060,
                'address' => '123 Main St'
            ],
            'destination_location' => [
                'latitude' => 40.7589,
                'longitude' => -73.9851,
                'address' => '456 Park Ave'
            ]
        ]);

        $response->assertStatus(201);
    }
}
```

## Performance Optimization

### Caching

```bash
# Cache routes
php artisan route:cache

# Cache configuration
php artisan config:cache

# Cache views
php artisan view:cache
```

### Database Optimization

```php
// Use eager loading to prevent N+1 queries
$rides = Ride::with(['rider', 'driver'])->get();

// Use database indexes in migrations
Schema::table('rides', function (Blueprint $table) {
    $table->index(['status', 'created_at']);
    $table->spatialIndex(['pickup_location', 'destination_location']);
});
```

## Security

### API Rate Limiting

```php
// In RouteServiceProvider
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});
```

### Input Validation

Always validate input using Form Requests:

```bash
php artisan make:request StoreRideRequest
```

### CORS Configuration

Configure CORS in `config/cors.php` for mobile app access.

## Deployment

### Production Optimization

```bash
# Optimize for production
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set up supervisor for queue workers
sudo supervisorctl start laravel-worker:*
```

### Environment Variables

Set these in production `.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=your-production-key

# Database
DB_CONNECTION=pgsql
DB_HOST=your-db-host
DB_DATABASE=anjemme_production

# Queue
QUEUE_CONNECTION=redis

# Cache
CACHE_DRIVER=redis
SESSION_DRIVER=redis
```

## Monitoring and Logging

### Log Channels

Configure multiple log channels in `config/logging.php`:

```php
'channels' => [
    'single' => [
        'driver' => 'single',
        'path' => storage_path('logs/laravel.log'),
        'level' => 'debug',
    ],
    'rides' => [
        'driver' => 'single',
        'path' => storage_path('logs/rides.log'),
    ],
],
```

### Health Checks

```bash
# Check application status
php artisan about

# Check queue status
php artisan queue:monitor
```

## Troubleshooting

### Common Issues

**Permission errors:**
```bash
chmod -R 775 storage bootstrap/cache
```

**Database connection:**
```bash
php artisan migrate:status
php artisan db:show
```

**Cache issues:**
```bash
php artisan optimize:clear
```

**Queue not processing:**
```bash
php artisan queue:failed
php artisan queue:retry all
```

### Debug Mode

Enable debug mode for development:
```env
APP_DEBUG=true
```

View detailed error logs:
```bash
tail -f storage/logs/laravel.log
```

## API Documentation

- **Interactive Docs**: Available at `/api/documentation` (Swagger UI)
- **OpenAPI Spec**: See [API_DOCUMENTATION.md](../docs/API_DOCUMENTATION.md)
- **Postman Collection**: Available in `docs/postman/`

## Contributing

1. Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards
2. Write tests for new features
3. Run static analysis: `./vendor/bin/phpstan analyse`
4. Follow the [Contributing Guide](../docs/CONTRIBUTING.md)

## Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Laravel API Resources](https://laravel.com/docs/eloquent-resources)
- [Laravel Queues](https://laravel.com/docs/queues)
- [Laravel Broadcasting](https://laravel.com/docs/broadcasting)
- [PHPUnit Testing](https://phpunit.de/documentation.html)

## Support

- **Documentation**: Check `/docs` folder
- **Issues**: Create GitHub issues for bugs
- **Team Chat**: #anjem-backend Slack channel
- **API Questions**: Tag @backend-team in PRs
