# GitHub Actions CI/CD Pipeline

This directory contains comprehensive GitHub Actions workflows for the Anjem ride-sharing platform project.

## Workflows Overview

### 1. Flutter CI (`flutter-ci.yml`)
**Triggers:** Push/PR to `mobile/` directory
**Features:**
- ✅ Code analysis with Flutter analyzer and Dart formatter
- ✅ Unit and widget tests with coverage reporting
- ✅ APK builds for both rider and driver flavors
- ✅ Integration tests with Android emulator
- ✅ Performance analysis (bundle size, large assets)
- ✅ Security scanning (audit, hardcoded secrets)
- ✅ Artifact uploads with 30-day retention

**Build Matrix:**
- Builds APKs for both `rider` and `driver` flavors
- Uses obfuscation and debug symbol splitting
- Caches Flutter dependencies for faster builds

### 2. Laravel CI (`laravel-ci.yml`)
**Triggers:** Push/PR to `backend/` directory
**Features:**
- ✅ Code quality with Laravel Pint and PHPStan
- ✅ Multi-PHP version testing (8.2, 8.3)
- ✅ PostgreSQL 15 and Redis 7 service containers
- ✅ Unit, feature, and integration tests
- ✅ API endpoint testing with WebSocket validation
- ✅ Load testing with k6
- ✅ Security scanning and vulnerability checks
- ✅ Coverage reporting with Codecov

**Test Services:**
- PostgreSQL 15 with health checks
- Redis 7 with health checks
- Laravel server and Reverb WebSocket server

### 3. Staging Deployment (`deploy-staging.yml`)
**Triggers:** Push to `main` branch, manual workflow dispatch
**Features:**
- ✅ Smart change detection (backend/mobile/infrastructure)
- ✅ DigitalOcean App Platform deployment
- ✅ Automatic database and Redis provisioning
- ✅ Mobile APK building and artifact storage
- ✅ Health checks and post-deployment validation
- ✅ Notification system for deployment status

**Deployment Strategy:**
- Backend: DigitalOcean App Platform with auto-scaling
- Mobile: APK builds uploaded to DigitalOcean Spaces
- Infrastructure: Automated database and Redis setup

## Required Secrets

### DigitalOcean Configuration
```bash
DIGITALOCEAN_ACCESS_TOKEN        # DigitalOcean API token
DO_SPACES_KEY                   # DigitalOcean Spaces access key
DO_SPACES_SECRET                # DigitalOcean Spaces secret key
DO_SPACES_BUCKET                # DigitalOcean Spaces bucket name
```

### Laravel Application
```bash
LARAVEL_APP_KEY                 # Laravel application key (base64 encoded)
REVERB_APP_KEY                  # Laravel Reverb WebSocket key
REVERB_APP_SECRET               # Laravel Reverb WebSocket secret
```

### External API Keys
```bash
FIREBASE_SERVER_KEY             # Firebase Cloud Messaging server key
FIREBASE_PROJECT_ID             # Firebase project ID
FIREBASE_CONFIG_STAGING         # Firebase config JSON (base64 encoded)
GOOGLE_MAPS_API_KEY            # Google Maps Distance Matrix API key
WHATSAPP_API_TOKEN             # WhatsApp Business API token
WHATSAPP_PHONE_NUMBER_ID       # WhatsApp phone number ID
```

### Mobile App Signing
```bash
ANDROID_KEYSTORE_BASE64        # Android keystore (base64 encoded)
ANDROID_KEY_ALIAS              # Android key alias
ANDROID_STORE_PASSWORD         # Android keystore password
ANDROID_KEY_PASSWORD           # Android key password
```

### Optional Services
```bash
CODECOV_TOKEN                  # Codecov token for coverage reports
WEBHOOK_URL                    # Notification webhook URL (Slack, Discord, etc.)
```

## Workflow Features

### Caching Strategy
- **Composer dependencies:** Cached by `composer.lock` hash
- **Flutter dependencies:** Cached by `pubspec.yaml` hash
- **Flutter SDK:** Cached by version
- **APT packages:** Cached for faster CI runs

### Artifact Management
- **APKs:** Retained for 30-90 days based on environment
- **Debug symbols:** Uploaded to DigitalOcean Spaces
- **Coverage reports:** Sent to Codecov
- **Test results:** Available in workflow summaries

### Performance Optimizations
- **Parallel jobs:** Tests run in parallel where possible
- **Smart caching:** Dependencies cached across workflow runs
- **Change detection:** Only runs relevant jobs based on file changes
- **Resource optimization:** Uses appropriate runner sizes

### Security Measures
- **Secret scanning:** Checks for hardcoded secrets
- **Vulnerability scanning:** Composer and Dart audit
- **Code analysis:** Static analysis with PHPStan and Flutter analyzer
- **Signed APKs:** Production builds are properly signed

## Setup Instructions

### 1. Configure Secrets
Add all required secrets to your GitHub repository:
`Settings > Secrets and variables > Actions > New repository secret`

### 2. DigitalOcean Setup
1. Create DigitalOcean account with student credits
2. Generate API token with full access
3. Create Spaces bucket for mobile app artifacts
4. Configure domain for API (api-staging.anjem.me)

### 3. Firebase Setup
1. Create Firebase project
2. Enable Cloud Messaging (Analytics optional; error tracking is Sentry, not Crashlytics)
3. Download `google-services.json` and encode as base64
4. Add Firebase server key for push notifications

### 4. Android Signing
1. Generate Android keystore: `keytool -genkey -v -keystore keystore.jks -alias anjem -keyalg RSA -keysize 2048 -validity 10000`
2. Encode keystore as base64: `base64 keystore.jks > keystore.txt`
3. Add keystore and passwords to GitHub secrets

### 5. Testing
- Push to feature branch to trigger CI
- Merge to `main` to trigger deployment
- Use manual workflow dispatch for selective deployments

## Monitoring and Debugging

### Workflow Status
- Check workflow status in GitHub Actions tab
- Review job logs for detailed error information
- Monitor deployment health checks

### Common Issues
1. **Cache misses:** Clear GitHub Actions cache if dependencies fail
2. **Build failures:** Check Flutter/PHP version compatibility
3. **Deployment timeout:** Increase health check timeouts
4. **Secret errors:** Verify all required secrets are configured

### Performance Monitoring
- APK size tracking in workflow logs
- Test coverage reports in Codecov
- Load test results in k6 output
- Deployment time tracking

## Customization

### Adding New Tests
1. Add test files to appropriate directories
2. Update workflow if new test commands are needed
3. Configure additional service containers if required

### Modifying Deployment
1. Update `.do/app-staging.yaml` for infrastructure changes
2. Modify deployment workflow for new environments
3. Add new secrets for additional services

### Notification Integration
1. Configure webhook URL in secrets
2. Customize notification format in workflow
3. Add service-specific notification logic

## Support

For issues with the CI/CD pipeline:
1. Check workflow logs for specific error messages
2. Verify all secrets are properly configured
3. Review DigitalOcean App Platform logs for deployment issues
4. Consult [DigitalOcean App Platform documentation](https://docs.digitalocean.com/products/app-platform/)

## Pipeline Status Badges

Add these badges to your main README:

```markdown
[![Flutter CI](https://github.com/your-username/anjem/actions/workflows/flutter-ci.yml/badge.svg)](https://github.com/your-username/anjem/actions/workflows/flutter-ci.yml)
[![Laravel CI](https://github.com/your-username/anjem/actions/workflows/laravel-ci.yml/badge.svg)](https://github.com/your-username/anjem/actions/workflows/laravel-ci.yml)
[![Deploy Staging](https://github.com/your-username/anjem/actions/workflows/deploy-staging.yml/badge.svg)](https://github.com/your-username/anjem/actions/workflows/deploy-staging.yml)
[![codecov](https://codecov.io/gh/your-username/anjem/branch/main/graph/badge.svg)](https://codecov.io/gh/your-username/anjem)
```