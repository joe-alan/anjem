# Anjem Documentation

**Comprehensive documentation for the Anjem ride-sharing platform**

Last Updated: December 1, 2025 | Version: 1.0.0 (MVP Phase 9)

---

## 📚 Quick Navigation

### **Getting Started**
- 🚀 [Quick Start Cheatsheet](QUICK_START_CHEATSHEET.md) - Get up and running in 5 minutes
- 📁 [Project Structure](architecture/PROJECT_STRUCTURE.md) - Navigate the codebase easily

### **Setup & Installation**
- [Development Setup](setup/DEVELOPMENT.md) - Complete dev environment setup
- [Build & Run Guide](setup/BUILD_AND_RUN.md) - Building and running the mobile apps
- [Firebase Setup](setup/FIREBASE_SETUP_GUIDE.md) - Authentication & push notifications
- [Firebase Gradle Configuration](setup/FIREBASE_GRADLE_SETUP.md) - Android build config
- [Infrastructure Setup](setup/infrastructure.md) - Production deployment guide

### **API Documentation**
- [API Reference](api/API_DOCUMENTATION.md) - Complete REST API documentation
  - Authentication endpoints
  - Ride request management
  - Driver operations
  - Admin endpoints
  - Real-time features

### **Implementation Guides**
- [Flutter Implementation Guide](guides/FLUTTER_IMPLEMENTATION_GUIDE.md) - Mobile app development roadmap
- [Admin Ride Override Guide](guides/ADMIN_RIDE_OVERRIDE_GUIDE.md) - Admin override functionality
- [Contributing Guidelines](guides/CONTRIBUTING.md) - How to contribute to the project

### **Testing**
- [Testing Documentation](testing/TESTING_DOCUMENTATION.md) - Test coverage and strategies
- [Testing Setup Guide](testing/TESTING_SETUP_GUIDE.md) - Setting up test environment
- [Mobile Testing Checklist](testing/MOBILE_TESTING_CHECKLIST.md) - Mobile app testing checklist
- [Edge Case Testing](testing/EDGE_CASE_TESTING_REPORT.md) - Edge case test results
- [Critical Fixes Required](testing/CRITICAL_FIXES_REQUIRED.md) - Known issues requiring fixes

### **Phase Completion Reports**
- [Phase 1: Core Setup & Auth](phases/PHASE_1_COMPLETION_SUMMARY.md) - Firebase + Sanctum integration
- [Phase 4: Controllers & API](phases/PHASE_4_COMPLETION_REPORT.md) - REST endpoints implementation
- [Phase 5: Real-time Features](phases/PHASE_5_COMPLETION_REPORT.md) - WebSocket broadcasting
- [Phase 5: Edge Case Testing](phases/PHASE_5_EDGE_CASE_TESTING_REPORT.md) - Security & validation tests
- [Phase 8: Mapbox Integration](phases/PHASE_8_COMPLETION_REPORT.md) - Route visualization & navigation
- [Phase 9: Implementation Plan](phases/PHASE_9_IMPLEMENTATION_PLAN.md) - Complete ride flow & driver app

### **Status Updates**
- [Phase 9B: Driver App Complete](status/2025-10-30_PHASE_9B_DRIVER_APP_COMPLETE.md) - Driver flow implementation
- [WebSocket Match Fix](status/2025-10-30_WEBSOCKET_MATCH_FIX.md) - Real-time matching fix
- [Admin Dashboard Complete](status/2025-11-21_PHASE_2_ADMIN_DASHBOARD_COMPLETE.md) - Admin panel
- [API Cost Optimization](status/2025-11-21_API_COST_OPTIMIZATION_COMPLETE.md) - Mapbox cost reduction
- [Backend Schema Fixes](status/2025-11-30_BACKEND_SCHEMA_FIXES_COMPLETE.md) - Database fixes
- [Comprehensive Review](status/2025-11-30_COMPREHENSIVE_PROJECT_REVIEW.md) - Full project review

### **Architecture**
- [Technical Specification](architecture/tech_spec.md) - System design and architecture
- [Architecture Change: Beacon to Standard](architecture/ARCHITECTURE_CHANGE_BEACON_TO_STANDARD.md) - Ride model changes

### **Planning & Optimization**
- [Driver Credit System Plan](phases/DRIVER_CREDIT_SYSTEM_IMPLEMENTATION_PLAN.md) - Future credit system
- [Route API Caching Plan](optimization/ROUTE_API_CACHING_PLAN.md) - Mapbox API optimization

### **Archive**
Older documentation and completed fixes are archived for reference:
- [Archive Folder](archive/) - Outdated specs, testing plans, and fix verification docs
  - Old API spec (pre-OpenAPI rewrite)
  - Legacy testing plans
  - Search fix verification (Nov 2025)
  - Applied fixes log (Nov 2025)

---

## 🏗️ Project Structure

```
anjem/
├── backend/              # Laravel API (PHP 8.2)
│   ├── app/             # Application logic
│   ├── database/        # Migrations, seeders, factories
│   ├── routes/          # API routes
│   └── tests/           # PHPUnit tests
│
├── mobile/              # Flutter apps (Dart 3.x)
│   ├── lib/
│   │   ├── core/       # Shared logic (models, providers, services)
│   │   ├── rider/      # Rider-specific screens
│   │   └── driver/     # Driver-specific screens
│   └── android/app/src/
│       ├── rider/      # Rider flavor config
│       └── driver/     # Driver flavor config
│
└── docs/               # Documentation (you are here!)
```

---

## 🎯 Current MVP Status

### **Completed Phases (85% Backend, 75% Mobile)**

#### Backend ✅
- **Phase 1**: Database models with PostGIS spatial support
- **Phase 2**: Firebase + Sanctum authentication
- **Phase 3**: Core services (Location, Ride, Queue, Notification)
- **Phase 4**: REST API endpoints with security validation
- **Phase 5**: WebSocket real-time features (Laravel Reverb)
- **Phase 0**: Mapbox integration & place search
- **Admin**: Dashboard with ride override support
- **Schema**: Backend schema fixes and optimizations

#### Mobile ✅
- **Phase 1**: Authentication (Firebase + Sanctum) ✅
- **Phase 1.5**: Driver KYC verification ✅
- **Phase 8**: Mapbox route visualization ✅
- **Phase 9A**: Rider flow (waiting, tracking, rating) ✅
- **Phase 9B**: Driver flow (accept, navigate, complete) ✅

#### In Progress 🚧
- **Phase 9 Polish**: Earnings history, settings, bug fixes
- **Testing**: Comprehensive edge case testing
- **Optimization**: Performance improvements

### **Recent Updates** 🎉
- ✅ Admin ride status override (backend + mobile)
- ✅ Audit logging for admin actions
- ✅ Real-time admin override notifications
- ✅ Comprehensive project documentation
- 🚧 Mobile app polish (earnings, settings, map zoom fix)

---

## 🔧 Tech Stack

### Backend
- **Framework**: Laravel 11.x
- **Database**: PostgreSQL 16 + PostGIS 3.6
- **Cache**: Redis 7
- **WebSocket**: Laravel Reverb
- **Authentication**: Firebase Auth + Laravel Sanctum
- **Maps**: Mapbox Directions API

### Mobile
- **Framework**: Flutter 3.24+
- **State Management**: Riverpod 2.x
- **Authentication**: Firebase Auth
- **API**: Dio (REST) + Laravel Echo (WebSocket)
- **Maps**: Mapbox Maps Flutter SDK

### Infrastructure
- **Deployment**: DigitalOcean (planned)
- **CI/CD**: GitHub Actions (planned)
- **Monitoring**: Firebase Crashlytics

---

## 📖 Documentation Conventions

### File Naming
- `UPPERCASE_WITH_UNDERSCORES.md` - Major documents
- `lowercase_with_underscores.md` - Legacy/archive documents

### Document Structure
All major documents follow this structure:
1. **Overview** - What this document covers
2. **Prerequisites** - What you need before starting
3. **Step-by-Step Instructions** - Detailed walkthrough
4. **Troubleshooting** - Common issues and solutions
5. **Next Steps** - What to do after completing this guide

---

## 🚀 Quick Start Guide

**For Developers New to This Project:**

1. **Read This First**: [Quick Start Cheatsheet](QUICK_START_CHEATSHEET.md)
2. **Setup Your Environment**: [Development Setup](setup/DEVELOPMENT.md)
3. **Understand the API**: [API Documentation](api/API_DOCUMENTATION.md)
4. **Start Contributing**: [Contributing Guidelines](guides/CONTRIBUTING.md)

**For QA/Testers:**
1. [Testing Setup Guide](testing/TESTING_SETUP_GUIDE.md)
2. [Testing Documentation](testing/TESTING_DOCUMENTATION.md)

**For DevOps:**
1. [Infrastructure Setup](setup/infrastructure.md)
2. [Technical Specification](architecture/tech_spec.md)

---

## 🐛 Known Issues & Limitations

### MVP Limitations
1. **Android Only** - iOS support deferred to post-MVP
2. **Campus Restricted** - Limited to UI Depok campus area
3. **No Payment Processing** - Cash/offline payments only
4. **Manual Driver Approval** - KYC verification requires admin approval

### Resolved Issues (Phase 8)
- ✅ Null ID type cast error in ride requests
- ✅ Route model binding with Laravel (snake_case convention)
- ✅ Map camera not fitting both points properly
- ✅ API response field mismatches (rider_id, estimated_fare_rp)

---

## 📞 Support & Contact

- **Issues**: GitHub Issues
- **Discussions**: GitHub Discussions
- **Email**: [Your contact email]

---

## 📝 License

[Your license information]

---

**Happy Coding!** 🚀
