# Anjem - Campus Ride-sharing Platform
# Development and deployment automation

.PHONY: help setup dev clean test build deploy-staging deploy-production

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
	@echo "Deployment Commands:"
	@echo "deploy-staging  - Deploy to staging environment"
	@echo "deploy-production - Deploy to production environment"

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
	@cd mobile && flutter run --flavor rider_app

# Start Flutter development for driver app
mobile-dev-driver:
	@echo "🚗 Starting Driver app development..."
	@cd mobile && flutter run --flavor driver_app

# Run Flutter tests
mobile-test:
	@echo "🧪 Running Flutter tests..."
	@cd mobile && flutter test

# Build rider app (APK)
build-rider:
	@echo "📦 Building rider app..."
	@cd mobile && flutter build apk --flavor rider_app --release

# Build driver app (APK)
build-driver:
	@echo "📦 Building driver app..."
	@cd mobile && flutter build apk --flavor driver_app --release

# Build both mobile apps
build-all: build-rider build-driver
	@echo "📦 Both mobile apps built successfully!"

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

# ===== DEPLOYMENT COMMANDS =====

# Deploy to staging
deploy-staging:
	@echo "🚀 Deploying to staging..."
	@echo "⚠️  Staging deployment not yet configured"
	@echo "Run: doctl apps create --spec .do/staging.yaml"

# Deploy to production
deploy-production:
	@echo "🚀 Deploying to production..."
	@echo "⚠️  Production deployment requires manual confirmation"
	@echo "Run: doctl apps create --spec .do/production.yaml"

# Infrastructure setup
infra-setup:
	@echo "🌊 Setting up DigitalOcean infrastructure..."
	@echo "Ensure DO_TOKEN is set and run:"
	@echo "doctl databases create anjem-db --engine pg --version 15 --size db-s-1vcpu-1gb"
	@echo "doctl databases create anjem-redis --engine redis --version 7"

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