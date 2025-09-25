# Anjem - Campus Ride-sharing Platform

A modern ride-sharing platform designed for campus environments, built with Flutter mobile apps and Laravel backend. Features dual app flavors for riders and drivers, real-time matching, and efficient ride management.

## 🚀 Technology Stack

- **Mobile**: Flutter with product flavors (rider, driver)
- **Backend**: Laravel 10 with Sanctum authentication
- **Database**: MySQL 8.0+ with Redis caching
- **Infrastructure**: DigitalOcean (student credits)
- **Real-time**: WebSockets with HTTP polling fallback
- **State Management**: Provider/Riverpod (to be implemented)
- **Authentication**: Phone-based OTP with JWT tokens

## 📋 Project Structure

```
anjem/
├── backend/          # Laravel API server
├── mobile/           # Flutter apps (dual flavors)
├── docs/            # Technical documentation
├── scripts/         # Development and deployment scripts
├── .do/             # DigitalOcean configuration
└── Makefile         # Common development commands
```

## 🎯 Key Features

### For Riders
- Beacon-to-beacon and P2P ride requests
- Real-time driver tracking
- Ride pooling capabilities
- In-app notifications

### For Drivers
- Queue-based ride acceptance
- Performance scoring system
- Reliability tracking
- Earnings dashboard

## 🚀 Quick Start

### Prerequisites
- Flutter SDK (latest stable)
- PHP 8.2+ with Composer
- Node.js for asset compilation
- MySQL 8.0+ and Redis

### Development Setup

```bash
# Clone the repository
git clone <repository-url>
cd anjem

# Setup backend
make backend-setup

# Setup mobile apps
make mobile-setup

# Start development servers
make dev
```

## 📱 Mobile Apps

The project uses Flutter product flavors to generate two apps from a single codebase:

- **Rider App**: For passengers requesting rides
- **Driver App**: For drivers providing rides

### Building Apps

```bash
# Build rider app
make build-rider

# Build driver app
make build-driver

# Build both flavors
make build-all
```

## 🔧 Development

### Running Tests
```bash
# Run all tests
make test

# Backend tests only
make test-backend

# Mobile tests only
make test-mobile
```

### Code Style
- Follow existing code conventions in each module
- Run linters before committing
- Maintain test coverage ≥80%

## 🚀 Deployment

### Staging
```bash
make deploy-staging
```

### Production
```bash
make deploy-production
```

## 📊 Performance Targets

- **API Response**: p95 ≤ 300ms
- **Cold Start**: ≤ 2.5s (mid-range Android)
- **APK Size**: ≤ 30MB
- **Peak Load**: 200 RPS
- **Crash-free Rate**: ≥98.5%

## 🔐 Security

- OTP-based authentication with rate limiting
- JWT tokens with 24hr expiry
- Dart code obfuscation in release builds
- API keys restricted to backend only

## 📖 Documentation

- [Technical Specification](docs/tech_spec.md)
- [API Documentation](docs/API_DOCUMENTATION.md)
- [API Specification](docs/api_spec.md)
- [Infrastructure Setup](docs/infra_setup.md)
- [Testing Strategy](docs/testing_plan.md)
- [Contributing Guidelines](docs/CONTRIBUTING.md)
- [Development Setup](docs/DEVELOPMENT.md)

## 🤝 Contributing

1. Follow the git workflow defined in [CONTRIBUTING.md](docs/CONTRIBUTING.md)
2. Branch naming: `feat/description` or `fix/description`
3. Commit format: `type(scope): message`
4. Always run tests before committing
5. See [DEVELOPMENT.md](docs/DEVELOPMENT.md) for setup instructions

## 📝 License

This project is licensed under the MIT License.

## 🆘 Support

For development questions and support, please refer to the documentation in the `docs/` directory or contact the development team.