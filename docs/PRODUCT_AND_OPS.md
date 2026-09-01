# Anjem — Product & Operations Document

_Last updated: 2026-03-14_

---

## 1. What Anjem Is

Anjem is a campus ride-sharing platform that connects riders (anyone) with student drivers on motorbikes. It operates within a defined campus geofence (currently scoped to a single faculty cluster) and is optimized for short-hop trips during class changeover peaks.

The platform consists of:

- **Rider app** — Flutter (Android). Anyone can ride. No student restriction.
- **Driver app** — Flutter (Android). Separate flavor of the same codebase. Drivers must be verified students.
- **Admin panel** — Web-based (Filament 3 on Laravel). Session-authenticated, accessible at `/admin`. Used by the ops team for live monitoring, KYC review, ride overrides, credit management, and audit logs.
- **Backend API** — Laravel 10, serving both mobile apps and the admin panel.

### What Anjem Is Not

- **Not a carrier.** Anjem is a matching platform. Drivers are independent and responsible for their own safety gear, vehicle legality, and insurance.
- **Not a payment processor.** The platform does not touch money in MVP. Drivers accept cash or their own e-wallet/QRIS directly from riders.
- **Not student-only for riders.** Anyone with a valid email can ride. Only drivers must be verified students.

---

## 2. Product Shape (Current State)

### Ride Modes

The original plan distinguished between "beacon-based" peak rides and "point-to-point" off-peak rides. **This was simplified.** As of October 2025, the system uses a **standard ride-sharing model** (similar to Grab/Uber):

- Rider picks any pickup and destination location (via Mapbox place search).
- Any online driver within range can receive the request.
- Matching is FIFO-based (first eligible driver in the queue gets the offer).
- No physical beacon queueing is required.

The `locations` table still exists and stores frequently-used campus points, but riders are not restricted to them.

### Ride Flow (Happy Path)

```
Rider                           Backend                          Driver
──────                          ───────                          ──────
Select pickup + destination
  → POST /requests
                                Creates RideRequest (pending)
                                Runs findTopDriver() from FIFO queue
                                Dispatches to single best driver
                                  → WebSocket: ride.request.new
                                  → FCM push (if WS unavailable)
                                                                 Sees incoming request
                                                                 30s countdown to accept/decline
                                                                   → POST /rides/{id}/accept
                                Creates Ride (accepted)
                                  → WebSocket: ride.request.matched
Sees "Driver matched!" screen
  with driver info, ETA
                                                                 Navigates to pickup
                                                                 Location updates every 5–30s
                                                                   → POST /driver/location
Sees live driver location
  on map
                                                                 Arrives at pickup
                                                                   → PATCH /rides/{id}/status (driver_arrived)
Sees "Driver has arrived"
                                                                 Picks up rider, starts ride
                                                                   → PATCH /rides/{id}/status (in_progress)
Sees ride in progress
  with live tracking
                                                                 Arrives at destination
                                                                   → PATCH /rides/{id}/status (completed)
Sees "Ride completed"
  → POST /rides/{id}/rate
```

### What Happens When No Driver Is Found

1. `findTopDriver()` runs and finds no eligible driver (pool empty, all declined, or all in cooldown).
2. The `RideRequest` stays in `pending` status.
3. A `HandleRequestTimeout` job is dispatched with a 35-second delay as a safety net.
4. If the rider cancels before a driver is found → request moves to `cancelled`.
5. If the request expires → request moves to `expired`.

### Driver Decline Behavior

- A driver can decline an incoming request (30-second window).
- On decline, the system offers to the next FIFO-eligible driver.
- After 3 declines within a rolling window, the driver enters a cooldown period (`queue_cooldown_until`).
- During cooldown, the driver remains "online" but is skipped by the matcher.

---

## 3. Value Propositions

### For Riders

- Ultra-cheap short hops on campus — flat base fare + simple step pricing.
- Fast pickup, especially during class changeover peaks.
- Anyone can ride — no student restriction, no platform fees (MVP).
- Real-time tracking of driver location and ETA.
- Route preview with fare estimate before confirming.

### For Drivers (Students)

- Easy earnings at times and places they're already riding.
- No tight SOP, no uniforms, fully flexible schedule.
- Go online when you want, go offline when you want.
- MVP perk: early cohort gets future monthly free credits when driver-credit economy launches.

---

## 4. User Types and Verification

### Riders

- **Sign up**: Google OAuth via Firebase Authentication.
- **Verification**: Email only. No student email required.
- **Permissions**: Request rides, cancel rides, rate drivers.

### Drivers

- **Sign up**: Same Google OAuth flow as riders.
- **KYC Process** (must complete before going online):
  1. Enter student email → receive verification code → verify.
  2. Upload KTM (Kartu Tanda Mahasiswa / student ID card) photo.
  3. Enter vehicle details (type, plate, color).
  4. Submit for admin review.
- **Admin KYC Review**: Admin sees the KTM photo and submitted details in the Filament panel. Two options:
  - **Approve** → driver can go online. KTM file is deleted from storage after approval.
  - **Reject** (with reason) → all KYC data is reset. Driver must re-submit from scratch. FCM notification sent.
- **We do NOT collect**: SIM (driver's license), STNK (vehicle registration). Driver self-attests road legality.

### Admin

- Role `admin` on the `users` table.
- Can access Filament panel at `/admin`.
- Can also hit REST admin endpoints with admin-scoped Sanctum tokens.
- Seeded accounts: `admin@anjem.app` / `admin123`, `test@anjem.app` / `test123`.

### "Both" Role

- A user with role `both` can act as rider AND driver.
- Token abilities are granted for both roles.

---

## 5. Geography and Operations

### Geofence

- **Day-1 scope**: Single faculty cluster (exact polygon TBD — will be defined in Figma map).
- Mapbox place search is bbox-scoped to the campus area: `106.80, -6.39, 106.86, -6.33`.
- The platform doesn't hard-enforce geofencing in code yet, but search results are limited to this area.

### Peak Windows

- **09:00–09:30** — Morning class start.
- **11:30–12:00** — Midday break.
- **14:30–15:00** — Afternoon class start.
- These windows are informational for ops planning. The system doesn't change behavior during peaks (no surge pricing, no special matching rules).

### Driver Availability

- Free-for-all for verified drivers. No shift booking, no roster.
- Drivers go online/offline at will.
- A driver must have ≥ 1 credit to go online (credit system enforced).

### Operations Cadence

- **Live monitoring**: Admin panel refreshes every 10–15 seconds showing pending requests, active rides, online drivers.
- **Stuck ride detection**: Any ride that hasn't progressed for 2+ hours is flagged as "stuck" and can be force-completed or force-cancelled by admin.
- **Stale driver cleanup**: Scheduled job kicks offline drivers who've been idle too long.
- **Request cleanup**: Expired requests are cleaned up by scheduled jobs.

---

## 6. Pricing and Fares

### Fare Structure (No Platform Margin)

The platform does not take a cut in MVP. All fare goes to the driver.

| Component | Formula |
|---|---|
| **Base fare** | Rp 5,000 |
| **Distance fare** | Rp 3,000 per km (actual Mapbox driving distance) |
| **Minimum fare** | Rp 5,000 |

**Note**: The original plan mentioned "Rp 6,000 for ≤ 2.0 km, then + Rp 2,000 per 0.5 km." The actual implementation uses a base + per-km model. The exact constants are in `RideService::calculateRideEstimates()`.

### Distance Calculation

- **Primary**: Mapbox Directions API (`driving-traffic` profile) — returns actual road distance and duration.
- **Cache**: Route results cached in `route_caches` table with 7-day TTL. 80–90% cache hit rate, reducing Mapbox API calls by 80–90%.
- **Fallback**: If Mapbox API fails, uses Haversine straight-line distance × 1.3 multiplier.

### Fare Estimate Flow

1. Rider selects pickup and destination.
2. Frontend calls `GET /requests/estimates` with location IDs.
3. Backend checks route cache → hits Mapbox on miss → calculates fare.
4. Returns: `base_fare`, `distance_fare`, `total_fare`, `estimated_distance` (km), `estimated_duration` (minutes), `route_geometry` (GeoJSON for map display).

### Pooled Rides

- **Deferred.** Not implemented. Original plan mentioned 0.8× multiplier with Rp 5,000 minimum. Will be revisited post-MVP.

### Payment

- **Cash** or driver's own e-wallet/QRIS. Platform doesn't intermediate.
- No QRIS merchant setup enforced for drivers in MVP.
- Optional "Buy me a coffee" donation — not implemented yet.

---

## 7. Driver Credit System

### Overview

Prepaid credit system for the open beta phase. Drivers consume 1 credit per ride accepted. Credits are managed by admin.

### Rules

| Rule | Value |
|---|---|
| Credit deduction | 1 credit per ride accepted |
| Minimum to go online | ≥ 1 credit |
| Minimum to accept ride | ≥ 1 credit |
| Top-up method | Admin grants via Filament panel |
| Deduction method | Automatic on ride accept; admin can also deduct manually |

### Credit Lifecycle

1. Admin grants credits to a driver via the Filament DriverResource (with reason, audit-logged).
2. Driver goes online (blocked if credits < 1).
3. Driver accepts a ride → 1 credit deducted atomically within the same DB transaction as ride creation.
4. If ride creation fails, transaction rolls back and credit is restored automatically.
5. Driver can view balance and transaction history via `GET /driver/credits/balance` and `GET /driver/credits/transactions`.

### What's NOT in Scope (Current Phase)

- Daily credit claim system.
- Driver credit request/purchase workflow.
- Payment/purchase flow for credits.
- Automated credit refunds (admin handles manually).

---

## 8. Matching System (FIFO Queue)

### How It Works

The matching system is a **FIFO (First In, First Out) queue** tracked via the `queue_joined_at` timestamp on `driver_profiles`.

When a rider creates a request:

1. `MatchingQueueService::findTopDriver()` runs.
2. It queries online drivers ordered by `queue_joined_at ASC` (longest-waiting driver first).
3. Filters:
   - Driver must be online (`went_online_at IS NOT NULL`).
   - Driver must not have an active ride.
   - Driver must not be in cooldown (`queue_cooldown_until IS NULL OR < now()`).
   - Driver must be within pickup radius (PostGIS `ST_Distance` on `current_location`).
   - Driver's `max_pickup_radius_km` setting is respected.
4. The top candidate gets dispatched the request via WebSocket + FCM.
5. Driver has 30 seconds to accept or decline.

### Decline Mechanics

- A driver can explicitly decline (`POST /rides/{id}/decline`).
- After a decline, the system immediately offers to the next eligible driver.
- Decline tracking: `decline_count` and `decline_window_start` on `driver_profiles`.
- After 3 declines in the rolling window → driver enters cooldown (`queue_cooldown_until` set).
- During cooldown, driver is skipped by the matcher but stays "online."

### Safety Net

- A `HandleRequestTimeout` job runs after 35 seconds.
- If the request is still pending and the dispatched driver hasn't responded, it re-runs matching.

### After Ride Completion

- `MatchingQueueService::rejoinAfterRide()` is called.
- Driver's `queue_joined_at` is reset to `now()` — they go to the back of the queue.
- This also runs after admin force-complete or force-cancel.

---

## 9. Cancellations

### Rider Cancellation

- Riders can cancel a pending request (`PATCH /requests/{id}/cancel`).
- Riders can also cancel after a driver is matched (ride in `accepted` or `driver_arrived` status).
- **Penalties**: Not enforced in current implementation. Original plan: 2 free/day or 8/week → auto temp ban. This is deferred.
- Rider cooldown: `rider_cooldown_until` field exists on `ride_requests` but enforcement is TBD.

### Driver Cancellation

- Drivers can decline an incoming request before accepting.
- After accepting, the driver cannot cancel through the normal flow — admin must force-cancel if needed.
- **Penalties**: Decline count tracked. 3 declines → cooldown. No credit penalties implemented yet.

---

## 10. Ratings

### Bidirectional Rating System

- After ride completion, the rider can rate the driver (1–5 stars).
- Predefined tags: clean-vehicle, safe-driving, friendly, punctual (stored as JSON array).
- Optional text feedback.
- Rider can skip rating.

### Rating Storage

- `ratings` table with `rater_id`, `rated_id`, `rating_type` (rider_to_driver / driver_to_rider).
- Driver rating average is maintained on `driver_profiles.rating_average` and `rating_count`.
- Rider rating average is on `users.rider_rating_avg`.

### Driver-to-Rider Rating

- Schema supports it (`driver_to_rider` type) but the UI flow is not implemented yet.

---

## 11. Trust, Safety, and Policy

### Terms of Service (MVP Stance)

- No formal waiver. Plain-language Terms published:
  - Anjem is a matching platform, not a carrier.
  - Drivers are independent and responsible for safety gear & legal eligibility.
- House rules (advisory): helmet, one passenger, pickup only in allowed zones, no in-building pickup.

### Privacy

- No biometrics or face ID.
- KTM photos are deleted after KYC review (both approve and reject).
- Minimal data retention for location events.
- Data deletion on request (GDPR-style, manual process).

### Security

- Firebase Authentication + Google OAuth for identity.
- Laravel Sanctum tokens with 24hr expiry and role-based abilities.
- All admin actions are audit-logged with IP, user-agent, before/after diffs.
- Rate limiting on all endpoints (auth: 10/min, location: 200/min, general: 100/min).
- No API keys in mobile app binary — uses `--dart-define` at build time.
- Dart obfuscation in release builds.

### Admin Audit Trail

Every admin action creates an immutable `AdminAuditLog` record:
- Who did it (admin ID).
- What they did (action type: 12 defined types).
- What was affected (target type + ID).
- What changed (JSON diff of before/after values).
- Why (reason text, required for destructive actions like KYC reject, force-cancel).
- IP address and user-agent.

---

## 12. Communications

### Rider ↔ Driver

- **WhatsApp deep-link**: Rider can tap to open WhatsApp chat with driver's phone number. No in-app chat.
- **Phone call**: Driver phone number shown on the matched screen.

### Platform ↔ Users

- **FCM push notifications**: Ride status changes, KYC decisions, new requests for drivers.
- **WhatsApp Communities**: For announcements, community building.
- **WhatsApp Hotline**: For support. Managed by ops partner.

### No In-App Chat (MVP)

In-app messaging is deferred to post-MVP.

---

## 13. Admin Panel Capabilities

The Filament 3 admin panel at `/admin` provides:

| Area | What You Can Do |
|---|---|
| **Dashboard** | KPI cards (total users, online drivers, active rides, 30-day revenue), daily rides chart, KYC pending count |
| **Drivers** | View profiles, credits, ratings, online status; grant/deduct credits; suspend/unsuspend |
| **KYC Review** | Review pending applications with KTM photo; approve or reject with reason; FCM notification sent to driver |
| **Riders** | View accounts; suspend/unsuspend |
| **Rides** | Browse all rides with full lifecycle; filter by status, date, stuck; force-complete or force-cancel |
| **Live Monitoring** | Real-time view (10–15s poll) of pending requests + active rides + online drivers, with per-row override actions |
| **Audit Log** | Searchable, immutable record of every admin action |

### Planned Phase 3 Additions

- Failed Jobs Resource (view + retry Laravel failed jobs).
- Driver Queue Visualizer (FIFO queue state with wait times).
- System Health Dashboard (Redis, Reverb, queue metrics).
- Real-time Live Monitoring upgrade (WebSocket push instead of polling).
- Match Attempt Diagnostics (live feed of findTopDriver() executions).
- Active WebSocket Subscriptions Viewer.

---

## 14. KPIs and Dashboards

### App KPIs

| Metric | Target |
|---|---|
| Crash-free sessions | ≥ 98.5% (Week 1–2), ≥ 99.2% (Week 3–4) |
| Cold start (mid-range Android) | ≤ 2.5 s |
| APK size | ≤ 30 MB |
| Battery — driver background tracking | ≤ 3%/hr |
| Battery — rider foreground | ≤ 1%/10 min |
| Push delivery p95 | ≤ 4 s |

### Ops KPIs

| Metric | Target |
|---|---|
| Ride fulfillment (request → completed) | ≤ 10 min |
| Pickup time median | TBD |
| Rides per peak window | Track and grow |
| Mapbox API cost | $0 (stay within free tier via caching) |
| Error budget | TBD |

### Dashboard (Current)

The Filament dashboard shows:
- Total users.
- Online drivers (live count).
- Active rides (live count).
- Revenue — last 30 days (sum of `actual_fare_rp`).
- Daily completed rides — 30-day line chart.
- KYC pending count (links to KYC review).

### Dashboard (Planned — Phase 3)

- Redis memory, clients, uptime.
- Queue depth (pending/failed jobs).
- Reverb WebSocket health.
- Route cache statistics.

---

## 15. Brand, Comms, and Channels

- **Name**: Anjem
- **Domain**: anjem.me
- **Tone**: Student-friendly but open to all riders. Not student-only branding.
- **Channels**:
  - WhatsApp Community + blasts.
  - Instagram, TikTok (managed by ops partner).
  - Faculty promotions and standees at high-traffic points.
- **Assets**: QR standees, simple microsite (privacy policy, status page), hotline number.

---

## 16. Legal and Campus Access (MVP Stance)

- **Entity**: Operating as founders (pre-company). No formal business entity yet.
- **Campus permissions**: No formal permit Day-1. Place standees outside restricted zones. Partner with faculty senate for legitimacy.
- **Terms/Privacy**: Plain-language pages shipped with microsite.
- **Data retention**: KTM images deleted after review. Minimize location data retention. Delete on request.

---

## 17. Roles and Responsibilities

### Alan (Tech)

- Build and maintain Flutter apps (rider + driver).
- Backend API, database, infrastructure (DigitalOcean).
- CI/CD, monitoring, SLOs.
- Security, data model, load tests, release management.
- Admin panel development and maintenance.
- Demo dataset and pilot scripts.

### Partner (Ops/PR/Design)

- Figma UI designs, brand and content.
- Faculty senate liaison, campus access coordination.
- WhatsApp Communities management, hotline scripts and macros.
- Beacon/standee placements, PR copy.
- Social channel ops (Instagram, TikTok), faculty blasts.
- Incident first response, feedback loops.

---

## 18. Launch Plan

### Pre-Launch Checklist

- [ ] All Phase 3 admin features implemented.
- [ ] FCM notifications working end-to-end.
- [ ] Credit system tested with real flows.
- [ ] KYC flow tested with real KTM uploads.
- [ ] Privacy policy and Terms published on anjem.me.
- [ ] Play Store listing (Internal → Closed testing track).
- [ ] Admin accounts provisioned.
- [ ] Initial driver cohort recruited and KYC'd.
- [ ] Initial credit grants to driver cohort.
- [ ] Beacon/standee placements decided.
- [ ] WhatsApp Community set up.
- [ ] Hotline number operational.

### Private Pilot (Target: 30–50 Real Rides)

1. Recruit 5–10 driver friends. Complete KYC, grant initial credits.
2. Recruit 10–20 rider friends.
3. Run 1-hour test session during a peak window.
4. Monitor via Live Monitoring page.
5. Collect feedback, fix critical bugs.
6. Verify crash-free rate ≥ 98.5%.

### Closed Beta

1. Expand driver cohort to 20–30 verified students.
2. Open rider app to faculty community via WhatsApp blast.
3. Monitor daily: rides per peak, fulfillment time, driver availability.
4. Iterate on pricing if needed.
5. Weekly driver credit grants based on activity.

### Scaling Triggers

| Trigger | Action |
|---|---|
| > 100 rides/day consistently | Consider formalizing entity, revenue model |
| > 300 rides/day | Evaluate infrastructure scaling (GCP migration) |
| Mapbox approaching 100k calls/month | Verify caching hit rate, consider paid tier |
| Multiple campus requests | Plan multi-geofence support |

---

## 19. Post-MVP Roadmap (Priority Order)

1. **Real-time admin monitoring** (WebSocket push — Phase 3, planned).
2. **Referral/promo engine** — growth driver.
3. **Driver credit economy** — self-service credit purchase, daily claims.
4. **Pooled rides** — 0.8× multiplier, minimum fare, shared-ride matching.
5. **iOS support** — TestFlight → App Store.
6. **In-app chat** — replace WhatsApp deep-links.
7. **Jastip vertical** — item delivery, reuses mapping and matching.
8. **Payment custody** — platform processes payments (requires entity + compliance).
9. **Multi-campus expansion** — multiple geofences, inter-campus routes.

---

## 20. Open Questions (TBD List)

- Exact geofence polygon (faculty map from partner).
- Pooled ride discount multiplier (start at 0.8× if undecided).
- Final rating tag list.
- Incident severity codes and escalation flow.
- Hotline phone number.
- Microsite copy.
- Terms and privacy policy wording (legal review).
- Rider cancellation penalty enforcement rules.
- Driver cancellation credit penalty amounts.
- Event targeting for launch demo.

---

## 21. Change Control Rule

If a change doesn't increase stability or improve the investor demo of reliability at peaks, it waits.

---

_End of Product & Operations Document._
