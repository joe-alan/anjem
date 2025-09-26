#!/bin/bash

# Anjem Development Setup Script
echo "🚀 Setting up Anjem development environment..."

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker and try again."
    exit 1
fi

# Start infrastructure services
echo "📦 Starting database and cache services..."
docker-compose up -d postgres redis mailpit

# Wait for PostgreSQL to be ready
echo "⏳ Waiting for PostgreSQL to be ready..."
until docker-compose exec postgres pg_isready -U anjem_user -d anjem; do
    sleep 2
done

echo "✅ PostgreSQL is ready!"

# Setup Laravel backend
echo "🔧 Setting up Laravel backend..."
cd backend

# Copy environment file if it doesn't exist
if [ ! -f .env ]; then
    cp .env.example .env
    echo "📝 Created .env file from .env.example"
    echo "⚠️  Please update .env with your API keys and configuration"
fi

# Generate application key
php artisan key:generate

# Generate Reverb keys
echo "🔑 Generating Reverb WebSocket keys..."
REVERB_KEY=$(openssl rand -base64 32)
REVERB_SECRET=$(openssl rand -base64 32)

# Update .env with Reverb keys
if [[ "$OSTYPE" == "darwin"* ]]; then
    # macOS
    sed -i '' "s/REVERB_APP_KEY=.*/REVERB_APP_KEY=$REVERB_KEY/" .env
    sed -i '' "s/REVERB_APP_SECRET=.*/REVERB_APP_SECRET=$REVERB_SECRET/" .env
else
    # Linux
    sed -i "s/REVERB_APP_KEY=.*/REVERB_APP_KEY=$REVERB_KEY/" .env
    sed -i "s/REVERB_APP_SECRET=.*/REVERB_APP_SECRET=$REVERB_SECRET/" .env
fi

# Install dependencies
composer install

# Run database migrations
echo "🗄️ Running database migrations..."
php artisan migrate

# Seed sample data
echo "🌱 Seeding sample data..."
php artisan db:seed --class=BeaconSeeder 2>/dev/null || echo "ℹ️  BeaconSeeder not found, skipping..."

# Clear and cache config
php artisan config:cache
php artisan route:cache

echo "✅ Laravel backend setup complete!"

cd ..

# Setup Flutter mobile apps
echo "📱 Setting up Flutter mobile apps..."
cd mobile

# Get Flutter dependencies
flutter pub get

# Generate code (if build_runner is configured)
flutter packages pub run build_runner build --delete-conflicting-outputs 2>/dev/null || echo "ℹ️  No code generation needed"

echo "✅ Flutter mobile apps setup complete!"

cd ..

echo ""
echo "🎉 Development environment setup complete!"
echo ""
echo "📋 Next steps:"
echo "   1. Update backend/.env with your API keys"
echo "   2. Start Laravel: make backend-dev"
echo "   3. Start WebSocket: php artisan reverb:start"
echo "   4. Start Flutter: make mobile-dev-rider or make mobile-dev-driver"
echo ""
echo "🔗 Useful URLs:"
echo "   - API: http://localhost:8000/api/v1/health"
echo "   - WebSocket: ws://localhost:8080"
echo "   - Mailpit: http://localhost:8025"
echo "   - Redis: localhost:6379"
echo "   - PostgreSQL: localhost:5432"
echo ""
echo "🛠️  Management Tools (optional):"
echo "   - pgAdmin: docker-compose --profile tools up -d pgadmin"
echo "   - Redis Commander: docker-compose --profile tools up -d redis-commander"
echo ""

# Make the script executable
chmod +x "$0"