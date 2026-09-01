# Test Database Setup Guide

This guide explains how to set up a separate test database for Laravel testing to ensure tests don't affect your development data.

## Why Separate Test Database?

Laravel tests using the `RefreshDatabase` trait will wipe and re-migrate the database between tests. Without a separate test database, this would destroy your development data.

## Quick Setup (New Environment)

If you're setting up the project on a new machine or environment:

### 1. Create `.env.testing`

```bash
cd backend
cp .env .env.testing
```

### 2. Configure Test Database Name

Edit `.env.testing` and change:

```env
DB_DATABASE=anjem
```

To:

```env
DB_DATABASE=anjem_test
```

**Important:** Only change the database name. Keep all other credentials the same (DB_USERNAME, DB_PASSWORD, etc.)

### 3. Create Test Database in PostgreSQL

```bash
psql -U postgres -d postgres -c "CREATE DATABASE anjem_test;"
```

### 4. Enable PostGIS Extension

Our app uses geography types which require PostGIS:

```bash
psql -U postgres -d anjem_test -c "CREATE EXTENSION IF NOT EXISTS postgis;"
```

### 5. Run Migrations on Test Database

```bash
cd backend
php artisan migrate --env=testing
```

### 6. Verify Setup

Check that tests don't affect main database:

```bash
# Check main DB user count
php artisan tinker --execute="echo App\Models\User::count();"

# Run a test
php artisan test --filter=ExampleTest

# Check main DB user count again (should be unchanged)
php artisan tinker --execute="echo App\Models\User::count();"
```

If the count is the same, you're all set! ✅

---

## Database Commands Reference

### Main Database (Default)

All commands use main database by default:

```bash
php artisan migrate              # Main DB
php artisan migrate:fresh --seed # Main DB
php artisan db:seed              # Main DB
php artisan tinker               # Main DB
```

### Test Database (Explicit)

Only use `--env=testing` when you want to manually interact with test DB:

```bash
php artisan migrate --env=testing       # Test DB
php artisan migrate:fresh --env=testing # Test DB
php artisan db:seed --env=testing       # Test DB
```

### Running Tests

Tests automatically use `.env.testing` (no flag needed):

```bash
php artisan test                    # Uses test DB automatically
php artisan test --filter=TestName  # Uses test DB automatically
```

---

## Troubleshooting

### Error: "type geography does not exist"

**Solution:** Enable PostGIS extension:

```bash
psql -U postgres -d anjem_test -c "CREATE EXTENSION IF NOT EXISTS postgis;"
```

### Tests still affecting main database

**Check:**

1. `.env.testing` exists in `backend/` directory
2. `.env.testing` has `DB_DATABASE=anjem_test`
3. Test database exists: `psql -U postgres -d postgres -c "\l" | grep anjem`

### Need to reset test database

```bash
php artisan migrate:fresh --env=testing
```

This won't affect your main database.

---

## Security Note

The `.env.testing` file is gitignored and should **never be committed** to version control as it contains sensitive credentials.

Each developer needs to create their own `.env.testing` file following this guide.

---

## See Also

- [CRITICAL_TEST_DATABASE_ISSUE.md](../../CRITICAL_TEST_DATABASE_ISSUE.md) - Details of the original issue and resolution
- [Laravel Testing Documentation](https://laravel.com/docs/testing)
