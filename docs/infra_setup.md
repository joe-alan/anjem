````markdown
# Infrastructure Setup Guide

## DigitalOcean Configuration

### Initial Setup (Using GitHub Student Pack)

```bash
# 1. Create DO account with student pack
# 2. Generate API token
export DO_TOKEN="your_token_here"

# 3. Create resources
doctl apps create --spec .do/app.yaml
doctl databases create anjem-db --engine pg --version 15 --size db-s-1vcpu-1gb
doctl databases create anjem-redis --engine redis --version 7
```
````

### Environment Variables

```env
# Laravel Backend (.env)
APP_URL=https://api.anjem.me
DB_CONNECTION=pgsql
DB_HOST=${db.HOSTNAME}
DB_PORT=${db.PORT}
DB_DATABASE=${db.DATABASE}
DB_USERNAME=${db.USERNAME}
DB_PASSWORD=${db.PASSWORD}

REDIS_HOST=${redis.HOSTNAME}
REDIS_PORT=${redis.PORT}
REDIS_PASSWORD=${redis.PASSWORD}

REVERB_APP_ID=anjem-mvp
REVERB_APP_KEY=${REVERB_KEY}
REVERB_APP_SECRET=${REVERB_SECRET}

# Flutter Apps (env.dart)
const API_BASE_URL = String.fromEnvironment('API_URL');
const WS_URL = String.fromEnvironment('WS_URL');
```

## Firebase Setup (Free Tier)

### Services Required

- Authentication (backup for OTP)
- Cloud Messaging (push notifications)
- Analytics (GA4)
- Crashlytics

### Configuration Files

- Android: `google-services.json` in each flavor
- iOS: `GoogleService-Info.plist` (future)

## Third-party APIs

### Google Maps (Distance Matrix)

- API Key restricted to backend IPs only
- Quota: 40,000 elements/month free
- Cache responses for 24 hours

### WhatsApp Business API

- Use Cloud API (free tier: 1,000 conversations/month)
- Webhook endpoint: `/webhooks/whatsapp`

```

```
