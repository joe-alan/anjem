# FCM Push Notification Implementation Plan

## Context

The Anjem ride-hailing app currently relies exclusively on WebSocket (Pusher/Laravel Reverb) for real-time ride updates. This means notifications only work while the app is in the foreground with an active WebSocket connection. When the app is backgrounded, killed, or loses connection, users miss critical ride events (driver arrival, ride cancellation, incoming ride requests for drivers).

The backend already has a complete `NotificationService.php` using Kreait Firebase SDK with methods for every ride event. The `users.fcm_token` column exists, the `POST /v1/auth/fcm-token` endpoint exists, and Flutter has `firebase_messaging` in its dependencies — but none of it is wired up on the mobile side. This plan fills that gap.

---

## 1. Codebase Audit Summary

### What already exists

| Component | File | Status |
|-----------|------|--------|
| FCM sending service | `backend/app/Services/NotificationService.php` | Complete — all ride event methods |
| Firebase SDK registration | `backend/app/Providers/AppServiceProvider.php` | Kreait `Messaging` singleton bound |
| FCM token column | `users.fcm_token` (string 500, nullable) | Exists |
| FCM token update endpoint | `POST /v1/auth/fcm-token` → `AuthController::updateFcmToken` | Exists |
| FCM token update at login | `AuthController::authenticateWithFirebase` | Accepts optional `fcm_token` param |
| Notification calls in controllers | `RideController`, `RequestController` | All trigger points call NotificationService |
| Notification call in jobs | `ExpireRideRequest` | Calls `sendRideRequestTimeoutToRider` |
| Flutter `firebase_messaging` dep | `mobile/pubspec.yaml` | Listed (`^15.1.3`) but never initialized |
| Flutter `updateFcmToken()` | `mobile/lib/core/services/auth/auth_service.dart:195` | Method exists, never called |
| `google-services.json` | `mobile/android/app/src/{rider,driver}/` | Both flavors configured |
| Firebase init | `main_rider.dart`, `main_driver.dart` | `Firebase.initializeApp()` called |

### What is missing

| Component | Details |
|-----------|---------|
| **Flutter FCM initialization** | `FirebaseMessaging.instance` never used |
| **Flutter FCM token retrieval & send** | Token never obtained or POSTed to backend |
| **Foreground message handler** | No `onMessage` listener |
| **Background message handler** | No `onBackgroundMessage` top-level function |
| **Terminated-state handler** | No `getInitialMessage` check |
| **`flutter_local_notifications`** | Not in pubspec — needed for foreground display |
| **Notification permission request** | Never called on either flavor |
| **Navigation on tap** | No routing logic for notification taps |
| **iOS APNs configuration** | No `GoogleService-Info.plist`, no entitlements |
| **New ride request push to driver** | `sendRideRequestToDriver(Ride)` takes wrong type; `dispatchToDriver()` never calls it |
| **Android high-priority config** | Driver ride request notifications not marked high-priority |

### Risks & conflicts

1. **`sendRideRequestToDriver` signature mismatch** — takes `Ride`, but at dispatch time only `RideRequest` exists (no Ride created until driver accepts). Must add a `RideRequest`-based overload.
2. **iOS requires APNs setup** — Firebase Console needs APNs auth key uploaded; Xcode needs push notification capability + background modes enabled. Without this, iOS push is silently broken.
3. **No conflict with real-time location** — location tracking uses Geolocator + WebSocket, NOT Firestore/RTDB. FCM is an independent channel.
4. **WebSocket remains primary** — FCM supplements WebSocket as a fallback for background/terminated states. Do NOT remove WebSocket handlers.

### Conventions to follow

- **Backend**: PSR-12, service class pattern, constructor DI, all data as string in FCM payloads
- **Flutter**: Riverpod providers, services in `lib/core/services/`, screens in flavor dirs, explicit types
- **Notifications**: All methods return bool, log success/failure, silently skip null tokens

---

## 2. Database Changes

**None required.** The `users.fcm_token` column (string 500, nullable) already exists on the `users` table.

---

## 3. Backend Implementation Plan

### 3.1 Add `sendRideRequestToDriver` overload for RideRequest

**File**: `backend/app/Services/NotificationService.php`
**Action**: Add new method alongside existing `sendRideRequestToDriver(Ride)`.

```
sendNewRideRequestToDriver(RideRequest $rideRequest, User $driver): bool
```

- **Inputs**: `RideRequest` (with pickupLocation, destinationLocation loaded), `User` (the driver)
- **Outputs**: bool (success/failure)
- **Data payload**:
  - `type`: `'new_ride_request'`
  - `ride_request_id`: string
  - `rider_name`: from `$rideRequest->rider->name`
  - `pickup_location`: from `$rideRequest->pickupLocation->name`
  - `destination_location`: from `$rideRequest->destinationLocation->name`
  - `estimated_fare_rp`: string
  - `passenger_count`: string
- **Android config**: High priority (`'priority' => 'high'`) so notification wakes the device
- **Why**: At dispatch time, no `Ride` model exists yet — only a `RideRequest`. The existing `sendRideRequestToDriver(Ride)` is for post-acceptance scenarios.

### 3.2 Add high-priority Android config for driver notifications

**File**: `backend/app/Services/NotificationService.php`
**Action**: Modify `sendNotification()` to accept an optional `$highPriority` parameter (default `false`). When true, attach:

```php
use Kreait\Firebase\Messaging\AndroidConfig;

$androidConfig = AndroidConfig::fromArray(['priority' => 'high']);
$message = $message->withAndroidConfig($androidConfig);
```

Apply high priority to:
- `sendNewRideRequestToDriver()` — always
- `sendRideRequestToDriver()` — always

### 3.3 Call FCM push in `dispatchToDriver()`

**File**: `backend/app/Services/MatchingQueueService.php`
**Action**: After the WebSocket broadcast on line 219, add FCM push call:

```php
// Push notification (supplements WebSocket for background/terminated app)
try {
    $rideRequest->loadMissing(['rider', 'pickupLocation', 'destinationLocation']);
    $driverUser = $driver->user;
    app(NotificationService::class)->sendNewRideRequestToDriver($rideRequest, $driverUser);
} catch (\Exception $e) {
    Log::warning('Failed to send FCM for new ride request', [
        'ride_request_id' => $rideRequest->id,
        'driver_id' => $driver->user_id,
        'error' => $e->getMessage(),
    ]);
}
```

- **Dependencies**: NotificationService resolved via `app()` (MatchingQueueService does not inject it currently)
- **Error handling**: Try/catch — FCM failure must not block dispatch

### 3.4 Add `SendFcmNotification` queued job (optional, recommended)

**File**: `backend/app/Jobs/SendFcmNotification.php` (new)
**Purpose**: Wrap FCM sends in a queued job to avoid blocking HTTP responses.
**Inputs**: `string $fcmToken`, `array $messageData`, `bool $highPriority`
**Queue**: `default` (same Redis queue)
**Retry**: 1 attempt, no retry on `MessagingException`

This is **optional for MVP** — the current synchronous approach works fine for low volume. Add when scaling becomes a concern.

### 3.5 Clear FCM token on logout

**File**: `backend/app/Http/Controllers/Api/AuthController.php`
**Action**: In `logout()` method (line 174), before deleting the token, clear `fcm_token`:

```php
$user->update(['fcm_token' => null]);
```

This prevents stale tokens from receiving notifications after logout.

### 3.6 No new `.env` variables needed

Firebase credentials are already configured via `FIREBASE_*` env vars. The Kreait SDK is already registered in `AppServiceProvider`.

---

## 4. Flutter Implementation Plan

### 4.1 Add `flutter_local_notifications` dependency

**File**: `mobile/pubspec.yaml`
**Action**: Add under dependencies:

```yaml
flutter_local_notifications: ^18.0.1
```

Both `firebase_messaging` (`^15.1.3`) and `firebase_core` (`^3.6.0`) already exist.

**Applies to**: Both rider and driver flavors.

### 4.2 Create `FcmService`

**File**: `mobile/lib/core/services/fcm/fcm_service.dart` (new)
**Applies to**: Both rider and driver flavors (shared core)

**Responsibilities**:
1. Request notification permission
2. Get FCM token and send to backend
3. Listen for token refresh and re-send
4. Handle foreground messages (display local notification)
5. Handle background messages (top-level function)
6. Handle notification taps (foreground, background, terminated)
7. Route to correct screen based on `event_type` and current flavor

**Constructor inputs**:
- `AuthService` (to call `updateFcmToken()`)
- `AppFlavor` (to determine navigation behavior)

**Key methods**:
- `Future<void> initialize()` — called once after Firebase.initializeApp()
- `Future<void> requestPermission()` — calls `FirebaseMessaging.instance.requestPermission()`
- `Future<String?> getToken()` — calls `FirebaseMessaging.instance.getToken()`
- `Future<void> sendTokenToBackend(String token)` — calls `AuthService.updateFcmToken(token)`
- `void _setupTokenRefreshListener()` — listens to `FirebaseMessaging.instance.onTokenRefresh`
- `void _setupForegroundHandler()` — listens to `FirebaseMessaging.onMessage`
- `void _handleNotificationTap(RemoteMessage message)` — navigation routing

**Background handler** (MUST be top-level, outside any class):
```dart
@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  // No-op for now — system tray handles display.
  // Add logging/analytics here if needed.
}
```

### 4.3 Create `LocalNotificationService`

**File**: `mobile/lib/core/services/fcm/local_notification_service.dart` (new)
**Applies to**: Both rider and driver flavors

**Purpose**: Display notifications when app is in foreground (FCM doesn't auto-display foreground notifications on Android).

**Setup**:
- Create Android notification channel: `anjem_rides` (high importance for ride events)
- Create a second channel: `anjem_general` (default importance for general notifications)
- Initialize `FlutterLocalNotificationsPlugin` with:
  - Android: app icon (`@mipmap/ic_launcher`)
  - iOS: request alert, badge, sound
- Handle `onDidReceiveNotificationResponse` for tap routing

**Key methods**:
- `Future<void> initialize()` — plugin setup + channel creation
- `Future<void> showNotification(RemoteMessage message)` — extract title/body/data, display
- Channel selection: `new_ride_request` type → `anjem_rides` (high importance); everything else → `anjem_general`

### 4.4 Create `fcmServiceProvider` Riverpod provider

**File**: `mobile/lib/core/providers/fcm_provider.dart` (new)

```dart
final fcmServiceProvider = Provider<FcmService>((ref) {
  final authService = ref.watch(authServiceProvider);
  return FcmService(authService: authService);
});
```

### 4.5 Initialize FCM in main entry points

**Files**: `mobile/lib/main_rider.dart`, `mobile/lib/main_driver.dart`
**Action**: After `Firebase.initializeApp()`, add:

```dart
// Register background message handler (must be top-level)
FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);
```

The `_firebaseMessagingBackgroundHandler` function is defined in `fcm_service.dart` and imported.

### 4.6 Wire FCM into auth flow

**File**: `mobile/lib/core/providers/auth_provider.dart`
**Action**: In `AuthStateNotifier`, after successful authentication (both `_checkAuthStatus` and `signInWithGoogle`), initialize FCM:

```dart
// After WebSocket init, before setting isAuthenticated:
final fcmService = ref.read(fcmServiceProvider); // Need ref access
await fcmService.initialize();
```

**Alternative approach** (simpler): Initialize FCM in a `ConsumerStatefulWidget` wrapper that watches `authStateProvider`. When `isAuthenticated` becomes true, call `fcmService.initialize()`. This avoids threading `ref` into the notifier.

**Recommended**: Create a `FcmInitializer` widget that wraps `AuthenticationWrapper` in `app.dart`:

```dart
class FcmInitializer extends ConsumerStatefulWidget { ... }
// In initState or build, when authState.isAuthenticated:
//   ref.read(fcmServiceProvider).initialize();
```

### 4.7 Clear FCM token on sign-out

**File**: `mobile/lib/core/providers/auth_provider.dart`
**Action**: In `signOut()`, before calling `_authService.signOut()`:

```dart
await FirebaseMessaging.instance.deleteToken();
```

### 4.8 Permission request flow

**When**: Called inside `FcmService.initialize()`
**Behavior**:
- On Android 13+ (API 33+), runtime permission is required (`POST_NOTIFICATIONS`)
- On Android 12 and below, permission is granted by default
- On iOS, `requestPermission()` triggers the system dialog
- If denied, FCM still works for data-only messages but notifications won't display

### 4.9 Foreground message handling

**How it works**:
1. `FirebaseMessaging.onMessage.listen((message) { ... })` receives messages while app is in foreground
2. Extract `title`, `body`, and `data` from `RemoteMessage`
3. Call `LocalNotificationService.showNotification(message)` to display
4. When user taps the local notification, `onDidReceiveNotificationResponse` fires
5. Route to appropriate screen based on `data['type']`

**Special case — driver ride request**: When `type == 'new_ride_request'` and flavor is driver:
- Do NOT show a local notification (the WebSocket handler already shows the full-screen `RideRequestScreen` sheet)
- FCM for this event is mainly a wake-up mechanism for background/terminated state

### 4.10 Background and terminated message handling

**Background** (app in background but not killed):
- System tray displays the notification automatically (Android/iOS)
- When user taps, `FirebaseMessaging.onMessageOpenedApp.listen((message) { ... })` fires
- Route to appropriate screen

**Terminated** (app was killed):
- On app launch, check `FirebaseMessaging.instance.getInitialMessage()`
- If non-null, the app was opened via a notification tap
- Route to appropriate screen after auth check completes

### 4.11 Navigation routing per event_type per flavor

| `data['type']` | Rider action | Driver action |
|-----------------|-------------|---------------|
| `ride_matched` | Navigate to `WaitingScreen` or `RiderActiveRideScreen` | N/A |
| `ride_accepted` | Navigate to `RiderActiveRideScreen` | N/A |
| `driver_arrived` | Navigate to `RiderActiveRideScreen` | N/A |
| `ride_started` | Navigate to `RiderActiveRideScreen` | N/A |
| `ride_completed` | Navigate to `CompletedScreen` with `ride_id` | Navigate to `DriverHomeScreen` |
| `ride_cancelled` | Navigate to `RiderHomeScreen` | Navigate to `DriverHomeScreen` |
| `new_ride_request` | N/A | Navigate to `DriverHomeScreen` (WebSocket handles request sheet) |
| `ride_request_timeout` | Navigate to `RiderHomeScreen` | N/A |
| `queue_position_update` | N/A | No navigation (info only) |
| `kyc_approved` | N/A | Navigate to `DriverHomeScreen` |
| `kyc_rejected` | N/A | Navigate to `KycFormScreen` |

**Implementation**: A `handleNotificationNavigation(Map<String, dynamic> data)` method in `FcmService` that uses `Navigator` or a global navigator key.

**Global navigator key**: Add `navigatorKey` to `AnjerApp`'s `MaterialApp` and expose it via a static/provider so `FcmService` can navigate without a `BuildContext`.

### 4.12 Android manifest changes

**File**: `mobile/android/app/src/main/AndroidManifest.xml`
**Action**: Add inside `<application>`:

```xml
<!-- FCM default notification channel -->
<meta-data
    android:name="com.google.firebase.messaging.default_notification_channel_id"
    android:value="anjem_rides" />

<!-- FCM default notification icon -->
<meta-data
    android:name="com.google.firebase.messaging.default_notification_icon"
    android:resource="@mipmap/ic_launcher" />
```

### 4.13 iOS configuration

**File**: `mobile/ios/Runner/Info.plist`
**Action**: Add `UIBackgroundModes`:

```xml
<key>UIBackgroundModes</key>
<array>
    <string>fetch</string>
    <string>remote-notification</string>
</array>
```

**File**: `mobile/ios/Runner/Runner.entitlements` (may need to create via Xcode)
**Action**: Add push notification entitlement:

```xml
<key>aps-environment</key>
<string>development</string>
```

**File**: `mobile/ios/Runner/GoogleService-Info.plist` (new — download from Firebase Console)
**Action**: Place the file downloaded from Firebase Console for the iOS app.

### 4.14 Admin flavor: explicit exclusion

No admin flavor exists in Flutter (`main_admin.dart` does not exist). The admin dashboard appears to be a web-based Filament panel (`filament/filament` in composer.json). No FCM work is needed for admin.

---

## 5. Notification Catalogue

| # | Event Name | Trigger Location | Recipient | Title | Body | Data `type` | Priority | Nav Destination |
|---|-----------|-----------------|-----------|-------|------|-------------|----------|----------------|
| 1 | New ride request dispatched | `MatchingQueueService::dispatchToDriver` | Driver | New Ride Request | Ride to {destination}. Fare: Rp {fare} | `new_ride_request` | **HIGH** | DriverHome (WS shows sheet) |
| 2 | Ride accepted | `RideController::accept` L125 | Rider | Ride Accepted | Driver {name} accepted, coming to pick you up | `ride_accepted` | Normal | RiderActiveRideScreen |
| 3 | Ride matched | `RideController::accept` (via sendRideAcceptedToRider) | Rider | Ride Accepted | (same as #2 — sendRideMatchedToRider not currently called from accept) | `ride_accepted` | Normal | RiderActiveRideScreen |
| 4 | Driver arrived | `RideController::updateStatus` L233 | Rider | Driver Arrived | Driver {name} arrived at pickup | `driver_arrived` | Normal | RiderActiveRideScreen |
| 5 | Ride started | `RideController::updateStatus` L257 | Rider | Ride Started | Ride to {destination} started | `ride_started` | Normal | RiderActiveRideScreen |
| 6 | Ride completed (rider) | `RideController::updateStatus` L288 | Rider | Ride Completed | Arrived at {destination}, rate your driver! | `ride_completed` | Normal | CompletedScreen |
| 7 | Ride completed (driver) | `RideController::updateStatus` L288 | Driver | Ride Completed | Earned Rp {fare}. Rate your rider! | `ride_completed` | Normal | DriverHomeScreen |
| 8 | Ride cancelled (to driver) | `RideController::updateStatus` L330 | Driver | Ride Cancelled | Rider cancelled the ride | `ride_cancelled` | Normal | DriverHomeScreen |
| 9 | Ride cancelled (to rider) | `RideController::updateStatus` L330 | Rider | Ride Cancelled | Driver cancelled the ride | `ride_cancelled` | Normal | RiderHomeScreen |
| 10 | Ride cancelled (matched, by rider) | `RequestController::cancel` L235 | Driver | Ride Cancelled | Rider cancelled the ride | `ride_cancelled` | Normal | DriverHomeScreen |
| 11 | No drivers available | `ExpireRideRequest::handle` L74 | Rider | No Drivers Available | Couldn't find a driver, try again shortly | `ride_request_timeout` | Normal | RiderHomeScreen |
| 12 | No drivers (cleanup) | `MatchingQueueService::notifyRiderNoDrivers` L458 | Rider | No Drivers Available | (same as #11) | `ride_request_timeout` | Normal | RiderHomeScreen |
| 13 | KYC approved | `AdminController` (KYC approval) | Driver | KYC Approved! | Verification approved, you can go online | `kyc_approved` | Normal | DriverHomeScreen |
| 14 | KYC rejected | `AdminController` (KYC rejection) | Driver | KYC Verification Rejected | Not approved, resubmit documents | `kyc_rejected` | Normal | KycFormScreen |
| 15 | Queue position update | `NotificationService::sendQueuePositionUpdate` | Driver | Queue Update | You're #{pos} in queue. Wait ~{min} min | `queue_position_update` | Normal | None (info only) |

---

## 6. Implementation Order

### Phase A: Backend fixes (1 session)

| # | Task | Depends on | Files |
|---|------|-----------|-------|
| A1 | Add `sendNewRideRequestToDriver(RideRequest, User)` method with high-priority Android config | — | `NotificationService.php` |
| A2 | Add high-priority support to `sendNotification()` private method | — | `NotificationService.php` |
| A3 | Call FCM push in `MatchingQueueService::dispatchToDriver()` | A1 | `MatchingQueueService.php` |
| A4 | Clear `fcm_token` on logout in `AuthController::logout()` | — | `AuthController.php` |

### Phase B: Flutter FCM service + local notifications (1 session)

| # | Task | Depends on | Files |
|---|------|-----------|-------|
| B1 | Add `flutter_local_notifications` to pubspec.yaml | — | `pubspec.yaml` |
| B2 | Create `LocalNotificationService` with channel setup | B1 | `core/services/fcm/local_notification_service.dart` (new) |
| B3 | Create `FcmService` with init, permission, token, handlers | B2 | `core/services/fcm/fcm_service.dart` (new) |
| B4 | Create `fcmServiceProvider` | B3 | `core/providers/fcm_provider.dart` (new) |
| B5 | Register background handler in both main files | B3 | `main_rider.dart`, `main_driver.dart` |

### Phase C: Wire FCM into auth + navigation (1 session)

| # | Task | Depends on | Files |
|---|------|-----------|-------|
| C1 | Add global navigator key to `AnjerApp` | — | `core/app.dart` |
| C2 | Create `FcmInitializer` wrapper widget | B4, C1 | `core/app.dart` or `core/widgets/fcm_initializer.dart` (new) |
| C3 | Implement notification tap navigation routing | C1, B3 | `core/services/fcm/fcm_service.dart` |
| C4 | Handle `getInitialMessage` for terminated-state launch | C3 | `core/services/fcm/fcm_service.dart` |
| C5 | Delete FCM token on sign-out | B3 | `core/providers/auth_provider.dart` |

### Phase D: Platform configuration (1 session)

| # | Task | Depends on | Files |
|---|------|-----------|-------|
| D1 | Add FCM meta-data to Android manifest | — | `android/app/src/main/AndroidManifest.xml` |
| D2 | Add `UIBackgroundModes` to iOS Info.plist | — | `ios/Runner/Info.plist` |
| D3 | Add push notification entitlement for iOS | — | Xcode project settings |
| D4 | Add `GoogleService-Info.plist` to iOS | — | `ios/Runner/GoogleService-Info.plist` |

### Phase E: Testing and validation (1 session)

| # | Task | Depends on | Files |
|---|------|-----------|-------|
| E1 | Manual testing per checklist (Section 7) | A-D | — |
| E2 | Fix any issues found | E1 | Various |

---

## 7. Testing Checklist

### For each notification event, verify:

| Event | Foreground | Background | Terminated | Null token | Token refresh |
|-------|-----------|-----------|-----------|-----------|--------------|
| New ride request (driver) | WebSocket sheet shows, no duplicate local notif | System tray shows, tap opens app to home | System tray shows, tap opens app, session resumes | No crash, WebSocket still works | Token re-sent to backend |
| Ride accepted (rider) | Local notification shows | System tray shows | Tap opens to active ride | Silently skipped | — |
| Driver arrived (rider) | Local notification shows | System tray shows | Tap opens to active ride | Silently skipped | — |
| Ride started (rider) | Local notification shows | System tray shows | Tap opens to active ride | Silently skipped | — |
| Ride completed (both) | Local notification shows | System tray shows | Tap opens to completed/home | Silently skipped | — |
| Ride cancelled (both) | Local notification shows | System tray shows | Tap opens to home | Silently skipped | — |
| No drivers available (rider) | Local notification shows | System tray shows | Tap opens to home | Silently skipped | — |
| KYC approved (driver) | Local notification shows | System tray shows | Tap opens to home | Silently skipped | — |
| KYC rejected (driver) | Local notification shows | System tray shows | Tap opens to KYC form | Silently skipped | — |

### Edge cases to verify:
- [ ] Permission denied: app still functions, just no notifications
- [ ] Multiple rapid notifications: no crashes, all display
- [ ] App foregrounded during background notification: no duplicate
- [ ] Sign out → sign in: new token registered, old cleared
- [ ] Two devices same account: last device gets notifications (last token wins)
- [ ] Network offline during token send: retry on next app launch

---

## 8. Post-Implementation Configuration

### Firebase Console
1. **Android**: No additional setup — `google-services.json` already exists for both flavors with FCM enabled
2. **iOS**: Upload APNs Authentication Key (`.p8` file) under Project Settings → Cloud Messaging → iOS app configuration. Obtain from Apple Developer → Certificates, Identifiers & Profiles → Keys → Create APNs key.

### Server (.env)
No new variables needed. Existing `FIREBASE_*` variables are sufficient for FCM via Kreait SDK.

### Laravel Forge / DigitalOcean
- Ensure `php artisan queue:work` is running (already required for existing jobs)
- No additional queue workers needed for FCM (uses same `default` queue)
- No new services or ports required

### APNs setup (iOS only, if iOS is in scope)
1. Create APNs Authentication Key in Apple Developer Console
2. Upload `.p8` file to Firebase Console → Project Settings → Cloud Messaging
3. Record Key ID and Team ID in Firebase Console
4. Add `GoogleService-Info.plist` to Xcode project (Runner target)
5. Enable Push Notifications capability in Xcode → Signing & Capabilities
6. Enable Background Modes → Remote notifications in Xcode

### Android notification icons (optional enhancement)
- Create a dedicated notification icon (monochrome, white-on-transparent) at `android/app/src/main/res/drawable/ic_notification.png`
- Reference in AndroidManifest `default_notification_icon` meta-data
- For MVP, `@mipmap/ic_launcher` is acceptable

---

## Files Summary

### Backend — modify:
- `backend/app/Services/NotificationService.php`
- `backend/app/Services/MatchingQueueService.php`
- `backend/app/Http/Controllers/Api/AuthController.php`

### Flutter — create:
- `mobile/lib/core/services/fcm/fcm_service.dart`
- `mobile/lib/core/services/fcm/local_notification_service.dart`
- `mobile/lib/core/providers/fcm_provider.dart`

### Flutter — modify:
- `mobile/pubspec.yaml`
- `mobile/lib/main_rider.dart`
- `mobile/lib/main_driver.dart`
- `mobile/lib/core/app.dart`
- `mobile/lib/core/providers/auth_provider.dart`

### Platform config — modify:
- `mobile/android/app/src/main/AndroidManifest.xml`
- `mobile/ios/Runner/Info.plist`

### Platform config — create (from Firebase Console):
- `mobile/ios/Runner/GoogleService-Info.plist`
