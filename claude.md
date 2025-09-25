# Anjem - Ride-sharing Platform Development Guide

## Project Context

Building a campus ride-sharing platform with Flutter mobile apps (rider/driver via product flavors) and Laravel backend. MVP target: 30 days.

## Active Development Phase

**Current Sprint**: Foundation Setup
**Status**: Project initialization
**Last Updated**: [Auto-update this]

## Key Constraints

- Single Flutter codebase, 2 product flavors (rider_app, driver_app)
- $0 infrastructure budget (use student credits)
- No payment processing in MVP
- Must handle 200 RPS at peak
- Crash-free rate ≥98.5%

## File References

- Technical specification: `docs/tech_spec.md`
- API contracts: `docs/api_spec.md`
- Infrastructure setup: `docs/infra_setup.md`
- Testing requirements: `docs/testing_plan.md`

## Development Standards

### Code Organization

```
anjem/
├── backend/          # Laravel API
├── mobile/           # Flutter apps
│   ├── lib/
│   │   ├── core/    # Shared logic
│   │   ├── rider/   # Rider-specific
│   │   └── driver/  # Driver-specific
│   └── android/app/src/
│       ├── rider/   # Flavor config
│       └── driver/  # Flavor config
└── docs/            # Documentation
```

### Git Workflow

- Branch naming: `feat/description`, `fix/description`
- Commit format: `type(scope): message`
- Always run tests before commit

## Current TODOs (expand as we go)

- [ ] Initialize Flutter project with flavors
- [ ] Setup Laravel API structure
- [ ] Configure DigitalOcean infrastructure
- [ ] Implement OTP authentication

## Decision Log (update as we go)

| Date    | Decision                           | Rationale                     |
| ------- | ---------------------------------- | ----------------------------- |
| [25/07] | Flutter over PWA                   | iOS reliability, AdMob future |
| [25/07] | Product flavors over separate apps | Shared codebase efficiency    |
