# Infrastructure Setup Guide

## DigitalOcean Configuration

### Initial Setup (Using GitHub Student Pack)

```bash
# 1. Create DO account with student pack
# 2. Generate API token
export DO_TOKEN="your_token_here"

# 3. Create resources
doctl apps create --spec .do/app.yaml
doctl databases create anjem-db --engine mysql --version 8 --size db-s-1vcpu-1gb
doctl databases create anjem-redis --engine redis --version 7
```

### Environment Variables

```env
# Laravel Backend (.env)
APP_URL=https://api.anjem.me
DB_CONNECTION=mysql
DB_HOST=${db.HOSTNAME}
DB_PORT=${db.PORT}
DB_DATABASE=${db.DATABASE}
DB_USERNAME=${db.USERNAME}
DB_PASSWORD=${db.PASSWORD}

REDIS_HOST=${redis.HOSTNAME}
REDIS_PORT=${redis.PORT}
REDIS_PASSWORD=${redis.PASSWORD}

# Twilio for OTP
TWILIO_SID=your_twilio_sid
TWILIO_TOKEN=your_twilio_token
TWILIO_FROM=your_twilio_phone

# Flutter Apps (environment.dart)
class Environment {
  static const String apiBaseUrl = String.fromEnvironment('API_BASE_URL');
  static const String googleMapsApiKey = String.fromEnvironment('GOOGLE_MAPS_API_KEY');
}
```

## Firebase Setup (Free Tier)

### Services Required

- Authentication (backup for OTP)
- Cloud Messaging (push notifications)
- Analytics (GA4)
- Sentry (error tracking, both backend and mobile)

### Configuration Files

- Android: `google-services.json` in each flavor
- iOS: `GoogleService-Info.plist` (future)

## Third-party APIs

### Google Maps (Distance Matrix)

- API Key restricted to backend IPs only
- Quota: 40,000 elements/month free
- Cache responses for 24 hours

### Twilio API (Phone/SMS)

- Use Twilio for OTP delivery via SMS
- Free tier: $15 trial credit
- Rate limiting: 5 OTP requests per phone per 30 minutes
