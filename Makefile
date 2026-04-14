
# Anjem - Campus Ride-sharing Platform
# Development and deployment automation

.PHONY: help setup dev clean test build

# Default target
help:
	@echo "Anjem Development Commands"
	@echo "========================="
	@echo "setup           - Initial project setup"
	@echo "dev             - Start development servers"
	@echo "clean           - Clean build artifacts"
	@echo "test            - Run all tests"
	@echo "build           - Build all applications"
	@echo "lint            - Run code linting"
	@echo ""
	@echo "Backend Commands:"
	@echo "backend-setup   - Setup Laravel backend"
	@echo "backend-dev     - Start Laravel development server"
	@echo "backend-test    - Run PHPUnit tests"
	@echo "backend-migrate - Run database migrations"
	@echo ""
	@echo "Mobile Commands:"
	@echo "mobile-setup    - Setup Flutter project"
	@echo "mobile-dev      - Start Flutter development"
	@echo "mobile-test     - Run Flutter tests"
	@echo "build-rider     - Build rider app"
	@echo "build-driver    - Build driver app"
	@echo "build-all       - Build both mobile apps"
	@echo ""
	@echo "Staging Commands:"
	@echo "mobile-dev-staging-rider   - Run rider app against staging"
	@echo "mobile-dev-staging-driver  - Run driver app against staging"
	@echo "build-staging-rider        - Build staging rider APK"
	@echo "build-staging-driver       - Build staging driver APK"
	@echo "build-staging-all          - Build both staging APKs"
	@echo ""
	@echo "Deployment:"
	@echo "  Production deploys automatically via Forge on push to main"

# Initial project setup
setup: backend-setup mobile-setup
	@echo "✅ Project setup complete!"

# Start all development servers
dev:
	@echo "🚀 Starting development servers..."
	@make -j2 backend-dev mobile-dev

# Clean all build artifacts
clean:
	@echo "🧹 Cleaning build artifacts..."
	@cd backend && rm -rf vendor/ storage/logs/*.log bootstrap/cache/*.php
	@cd mobile && flutter clean
	@echo "✅ Cleanup complete!"

# Run all tests
test: backend-test mobile-test
	@echo "✅ All tests completed!"

# Build all applications
build: build-all
	@echo "✅ All builds completed!"

# Run code linting
lint:
	@echo "🔍 Running linters..."
	@cd backend && ./vendor/bin/pint --test
	@cd mobile && flutter analyze
	@echo "✅ Linting complete!"

# ===== BACKEND COMMANDS =====

# Setup Laravel backend
backend-setup:
	@echo "🔧 Setting up Laravel backend..."
	@cd backend && \
		composer install && \
		cp .env.example .env 2>/dev/null || echo "⚠️  Create .env file manually" && \
		php artisan key:generate 2>/dev/null || echo "⚠️  Run 'php artisan key:generate' after Laravel setup"
	@echo "✅ Backend setup complete!"

# Start Laravel development server
backend-dev:
	@echo "🚀 Starting Laravel development server..."
	@cd backend && php artisan serve --host=0.0.0.0 --port=8000

# Run backend tests
backend-test:
	@echo "🧪 Running PHPUnit tests..."
	@cd backend && ./vendor/bin/phpunit

# Run database migrations
backend-migrate:
	@echo "📊 Running database migrations..."
	@cd backend && php artisan migrate

# Start Laravel Reverb WebSocket server
backend-websocket:
	@echo "🔌 Starting WebSocket server..."
	@cd backend && php artisan reverb:start

# ===== MOBILE COMMANDS =====

# Setup Flutter project
mobile-setup:
	@echo "📱 Setting up Flutter project..."
	@cd mobile && \
		flutter pub get && \
		flutter precache
	@echo "✅ Mobile setup complete!"

# Start Flutter development
mobile-dev:
	@echo "📱 Starting Flutter development..."
	@echo "Choose your target:"
	@echo "1. Rider app: make mobile-dev-rider"
	@echo "2. Driver app: make mobile-dev-driver"

# Start Flutter development for rider app
mobile-dev-rider:
	@echo "📱 Starting Rider app development..."
	@cd mobile && flutter run --flavor rider -t lib/main_rider.dart

# Start Flutter development for driver app
mobile-dev-driver:
	@echo "🚗 Starting Driver app development..."
	@cd mobile && flutter run --flavor driver -t lib/main_driver.dart

# Run Flutter tests
mobile-test:
	@echo "🧪 Running Flutter tests..."
	@cd mobile && flutter test

# Build rider app (APK)
build-rider:
	@echo "📦 Building rider app..."
	@cd mobile && flutter build apk --flavor rider -t lib/main_rider.dart --release

# Build driver app (APK)
build-driver:
	@echo "📦 Building driver app..."
	@cd mobile && flutter build apk --flavor driver -t lib/main_driver.dart --release

# Build both mobile apps
build-all: build-rider build-driver
	@echo "📦 Both mobile apps built successfully!"

# ===== STAGING BUILDS =====
# Staging URLs — override MAPBOX_ACCESS_TOKEN via env var
STAGING_API_URL    = https://staging-api.anjem.me/api/v1
STAGING_WS_URL     = wss://staging-ws.anjem.me
STAGING_PUSHER_HOST = staging-ws.anjem.me
STAGING_PUSHER_PORT = 443
STAGING_PUSHER_SCHEME = https
STAGING_SENTRY_ENV = staging

STAGING_DART_DEFINES = \
	--dart-define=API_URL=$(STAGING_API_URL) \
	--dart-define=WS_URL=$(STAGING_WS_URL) \
	--dart-define=PUSHER_HOST=$(STAGING_PUSHER_HOST) \
	--dart-define=PUSHER_PORT=$(STAGING_PUSHER_PORT) \
	--dart-define=PUSHER_SCHEME=$(STAGING_PUSHER_SCHEME) \
	--dart-define=SENTRY_ENV=$(STAGING_SENTRY_ENV) \
	$(if $(MAPBOX_ACCESS_TOKEN),--dart-define=MAPBOX_ACCESS_TOKEN=$(MAPBOX_ACCESS_TOKEN)) \
	$(if $(PUSHER_KEY),--dart-define=PUSHER_KEY=$(PUSHER_KEY)) \
	$(if $(SENTRY_DSN),--dart-define=SENTRY_DSN=$(SENTRY_DSN))

# Run staging rider app (dev mode)
mobile-dev-staging-rider:
	@echo "📱 Starting Rider app (staging)..."
	@cd mobile && flutter run --flavor rider -t lib/main_rider.dart $(STAGING_DART_DEFINES)

# Run staging driver app (dev mode)
mobile-dev-staging-driver:
	@echo "🚗 Starting Driver app (staging)..."
	@cd mobile && flutter run --flavor driver -t lib/main_driver.dart $(STAGING_DART_DEFINES)

# Build staging rider APK
build-staging-rider:
	@echo "📦 Building rider app (staging)..."
	@cd mobile && flutter build apk --flavor rider -t lib/main_rider.dart --release $(STAGING_DART_DEFINES)

# Build staging driver APK
build-staging-driver:
	@echo "📦 Building driver app (staging)..."
	@cd mobile && flutter build apk --flavor driver -t lib/main_driver.dart --release $(STAGING_DART_DEFINES)

# Build both staging APKs
build-staging-all: build-staging-rider build-staging-driver
	@echo "📦 Both staging APKs built successfully!"

# ===== DEVELOPMENT TOOLS =====

# Generate Flutter code
mobile-generate:
	@echo "⚙️  Generating Flutter code..."
	@cd mobile && flutter packages pub run build_runner build --delete-conflicting-outputs

# Database operations
db-fresh:
	@echo "🗄️  Fresh database setup..."
	@cd backend && php artisan migrate:fresh --seed

# Clear caches
cache-clear:
	@echo "🧹 Clearing caches..."
	@cd backend && php artisan cache:clear && php artisan config:clear && php artisan route:clear

# ===== QUALITY ASSURANCE =====

# Code formatting
format:
	@echo "💄 Formatting code..."
	@cd backend && ./vendor/bin/pint
	@cd mobile && dart format .

# Security checks
security-check:
	@echo "🔒 Running security checks..."
	@cd backend && ./vendor/bin/phpstan analyse
	@echo "✅ Security checks complete!"

# ===== MONITORING AND LOGS =====

# View backend logs
logs:
	@echo "📋 Viewing backend logs..."
	@cd backend && tail -f storage/logs/laravel.log

# Check system status
status:
	@echo "📊 System Status Check"
	@echo "======================"
	@echo "Backend Status:"
	@curl -s http://localhost:8000/api/v1/health || echo "❌ Backend offline"
	@echo "\nDatabase Status:"
	@cd backend && php artisan migrate:status || echo "❌ Database connection failed"

# Performance test with k6
load-test:
	@echo "⚡ Running load tests..."
	@k6 run scripts/k6_load_test.js

# ===== MAINTENANCE =====

# Update dependencies
update:
	@echo "🔄 Updating dependencies..."
	@cd backend && composer update
	@cd mobile && flutter pub upgrade

# Backup database
backup:
	@echo "💾 Creating database backup..."
	@echo "⚠️  Backup script not yet implemented"

# Project statistics
stats:
	@echo "📈 Project Statistics"
	@echo "===================="
	@echo "Backend Files:"
	@find backend -name "*.php" | wc -l | xargs echo "  PHP files:"
	@echo "Mobile Files:"
	@find mobile/lib -name "*.dart" | wc -l | xargs echo "  Dart files:"
	@echo "Total Lines of Code:"
	@find backend mobile/lib -name "*.php" -o -name "*.dart" | xargs wc -l | tail -n 1