# Anjem Backend

Laravel 10 API + Filament 3 admin panel for the Anjem campus ride-sharing platform. See the
[root README](../README.md) for the full project overview and architecture.

## What's here

- **REST API** under `/api/v1` — auth, ride requests, matching, driver KYC, credits, admin
  (`routes/api.php`)
- **Filament admin panel** at `/admin` — driver/rider management, live monitoring, analytics,
  ride overrides, audit log
- **Real-time** — Laravel Reverb broadcasts ride events; broadcast channels in `routes/channels.php`
- **Queued work** — Laravel Horizon runs FCM sends, ride-request expiry, KYC email OTP, and
  route-cache cleanup
- **Spatial** — PostgreSQL + PostGIS for the campus geofence and nearest-driver queries
  (`laravel-eloquent-spatial`)

## Requirements

- PHP 8.2+, Composer
- PostgreSQL 15+ with the PostGIS extension
- Redis (cache, queue, sessions, Reverb)
- Node.js (only for building Filament/Vite assets)
- A Firebase project (Auth + FCM + Storage) and Mapbox tokens

## Setup

```bash
cp .env.example .env          # fill in DB, Firebase, Mapbox, Reverb, mail
composer install
php artisan key:generate
php artisan migrate --seed    # admin user, campus locations, demo accounts
php artisan storage:link
```

Run the four processes it needs:

```bash
php artisan serve             # API        :8000
php artisan reverb:start      # WebSocket  :8080
php artisan queue:work        # or: php artisan horizon
php artisan schedule:work     # stale-driver kick, route-cache cleanup
```

## Layout

```
app/
├── Http/Controllers/Api/   thin REST controllers
├── Http/Resources/         JSON transformers
├── Services/               business logic (MatchingQueueService, RouteCacheService,
│                           CreditService, KycVerificationService, NotificationService, …)
├── Events/ Jobs/ Listeners/ WebSocket broadcasts + queued work
├── Filament/               admin resources, pages, widgets
└── Models/
database/migrations/    ·   routes/api.php   ·   tests/
```

## Testing

```bash
php artisan test                     # needs .env.testing -> an isolated Postgres DB
./vendor/bin/pint --test             # code style
./vendor/bin/phpstan analyse         # static analysis (Larastan)
composer audit
```

See [`docs/setup/TEST_DATABASE_SETUP.md`](../docs/setup/TEST_DATABASE_SETUP.md). Note: CI is
currently broken — see [`docs/TODO.md`](../docs/TODO.md) #16.

## More

- API reference: [`docs/api/API_DOCUMENTATION.md`](../docs/api/API_DOCUMENTATION.md)
- Database schema: [`docs/database/CURRENT_DATABASE_SCHEMA.md`](docs/database/CURRENT_DATABASE_SCHEMA.md)
- Contributing: [`docs/guides/CONTRIBUTING.md`](../docs/guides/CONTRIBUTING.md)
