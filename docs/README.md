# Anjem documentation

Start with the [root README](../README.md) for a project overview, the stack, and how to run
things. This folder holds the longer-form material.

## Reference

| Document | What it covers |
| --- | --- |
| [TECHNICAL_SPEC.md](TECHNICAL_SPEC.md) | System design — data model, matching, real-time, geofence |
| [PRODUCT_AND_OPS.md](PRODUCT_AND_OPS.md) | Product rules, fare model, operational playbook |
| [api/API_DOCUMENTATION.md](api/API_DOCUMENTATION.md) | Full REST API reference (`/api/v1`) |
| [architecture/PROJECT_STRUCTURE.md](architecture/PROJECT_STRUCTURE.md) | Codebase layout (note: partly dated) |
| [architecture/ADMIN_PANEL.md](architecture/ADMIN_PANEL.md) | Filament admin panel design |
| [architecture/ARCHITECTURE_CHANGE_BEACON_TO_STANDARD.md](architecture/ARCHITECTURE_CHANGE_BEACON_TO_STANDARD.md) | Why fixed pickup points ("beacons") were dropped |
| [optimization/ROUTE_API_CACHING_PLAN.md](optimization/ROUTE_API_CACHING_PLAN.md) | Serving route geometry from the DB instead of re-calling Mapbox |
| [TODO.md](TODO.md) | Known follow-ups (from a 2026-03-05 audit) |

## Setup

- [setup/DEVELOPMENT.md](setup/DEVELOPMENT.md) — full local environment walkthrough
- [setup/BUILD_AND_RUN.md](setup/BUILD_AND_RUN.md) — building and running the two Flutter flavors
- [setup/FIREBASE_SETUP_GUIDE.md](setup/FIREBASE_SETUP_GUIDE.md) · [setup/FIREBASE_GRADLE_SETUP.md](setup/FIREBASE_GRADLE_SETUP.md) — Firebase Auth, FCM, Android config
- [setup/TEST_DATABASE_SETUP.md](setup/TEST_DATABASE_SETUP.md) — the isolated test database
- [setup/infrastructure.md](setup/infrastructure.md) — production infra (Laravel Forge)

## Guides

- [guides/CONTRIBUTING.md](guides/CONTRIBUTING.md) — workflow, branch naming, commit format, standards
- [guides/FLUTTER_IMPLEMENTATION_GUIDE.md](guides/FLUTTER_IMPLEMENTATION_GUIDE.md) — mobile architecture notes
- [guides/ADMIN_RIDE_OVERRIDE_GUIDE.md](guides/ADMIN_RIDE_OVERRIDE_GUIDE.md) — forcing status on stuck rides
- [guides/SESSION_RESUMPTION.md](guides/SESSION_RESUMPTION.md) — resuming an in-progress ride after app restart

## Testing

- [testing/TESTING_DOCUMENTATION.md](testing/TESTING_DOCUMENTATION.md) — strategy and coverage
- [testing/TESTING_SETUP_GUIDE.md](testing/TESTING_SETUP_GUIDE.md) — test environment
- [testing/MOBILE_TESTING_CHECKLIST.md](testing/MOBILE_TESTING_CHECKLIST.md) — manual mobile pass

## Design

- [design/UI_HANDOVER.md](design/UI_HANDOVER.md) — product context, full screen inventory, brand notes

## Archive

[archive/](archive/) holds point-in-time material kept for reference: phase completion reports,
dated status updates, device-test logs, superseded specs, and the old launch checklist.
It is not maintained.
