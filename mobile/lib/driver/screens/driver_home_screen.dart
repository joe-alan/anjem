import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:geolocator/geolocator.dart';
import 'package:mobile/l10n/app_localizations.dart';
import 'package:permission_handler/permission_handler.dart' as ph;
import '../../core/config/app_config.dart';
import '../../core/models/ride_request.dart';
import '../../core/providers/driver_incoming_request_provider.dart';
import '../../core/providers/credits_provider.dart';
import '../../core/providers/driver_status_provider.dart';
import '../../core/providers/driver_statistics_provider.dart';
import '../../core/providers/auth_provider.dart';
import '../../core/providers/kyc_provider.dart';
import 'ride_request_screen.dart';
import 'active_ride_screen.dart';
import 'driver_settings_screen.dart';
import 'earnings_history_screen.dart';
import 'driver_ride_history_screen.dart';
import 'rating_history_screen.dart';
import 'kyc_status_screen.dart';

class DriverHomeScreen extends ConsumerStatefulWidget {
  const DriverHomeScreen({super.key});

  @override
  ConsumerState<DriverHomeScreen> createState() => _DriverHomeScreenState();
}

class _DriverHomeScreenState extends ConsumerState<DriverHomeScreen>
    with WidgetsBindingObserver {
  ProviderSubscription<RideRequest?>? _incomingRequestSub;
  ProviderSubscription<DriverStatusState>? _driverStatusSub;
  bool _isPresentingRideRequest = false;

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      // Safety net: re-fetch user and KYC state in case a suspend/unsuspend
      // WebSocket event was missed while the app was backgrounded.
      ref.read(authStateProvider.notifier).refreshUser();
      ref.read(kycStateProvider.notifier).refreshKycStatus();
      // Restore incoming request if app was opened via FCM notification tap
      // and the WS event was missed while backgrounded.
      ref.read(driverStatusProvider.notifier).checkPendingDispatch();
    }
    // Best-effort offline call when the OS is about to destroy the app.
    if (state == AppLifecycleState.detached) {
      final driverStatus = ref.read(driverStatusProvider);
      if (driverStatus.isOnline && !driverStatus.hasActiveRide) {
        ref.read(driverStatusProvider.notifier).goOffline();
      }
    }
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    // Refresh statistics when screen loads
    Future.microtask(() {
      ref.refresh(driverStatisticsProvider);
    });
    // Request all permissions upfront on first screen mount
    WidgetsBinding.instance.addPostFrameCallback(
      (_) => _requestAllPermissions(),
    );

    _incomingRequestSub = ref.listenManual<RideRequest?>(
      driverIncomingRequestProvider,
      (previous, next) {
        if (next == null) {
          return;
        }

        if (previous?.id == next.id) {
          return;
        }

        _showRideRequestSheet(next);
      },
    );

    // Refresh stats when a ride completes (inActiveRide → online transition).
    // Also start/stop the idle location timer as online status changes.
    _driverStatusSub = ref.listenManual<DriverStatusState>(
      driverStatusProvider,
      (previous, next) {
        if (previous != null &&
            previous.hasActiveRide &&
            !next.hasActiveRide) {
          Future.microtask(() {
            if (mounted) ref.invalidate(driverStatisticsProvider);
          });
        }

        // Detect online → offline transition (system kick)
        if (previous != null &&
            previous.isOnline &&
            !next.isOnline) {
          final reason = ref.read(driverStatusProvider.notifier).consumeKickReason();
          if (reason != null && mounted) {
            final l10n = AppLocalizations.of(context);
            final message = switch (reason) {
              'location_stale' => l10n.kickedStaleGps,
              'zero_credits' => l10n.kickedZeroCredits,
              'admin_kick' => l10n.kickedAdminKick,
              _ => l10n.kickedGeneric,
            };
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(message),
                duration: const Duration(seconds: 6),
                behavior: SnackBarBehavior.floating,
              ),
            );
          }
        }

      },
    );
  }

  /// Request all permissions the driver app needs upfront.
  ///
  /// Ordered so that permissions which do NOT trigger Activity recreation come
  /// first.  Granting foreground location on some OEMs (Samsung, Xiaomi)
  /// destroys and recreates the Activity, killing the async chain.  By
  /// requesting notification first, we guarantee it completes.  If location
  /// grant recreates the Activity, initState re-fires, re-runs this method,
  /// and the already-granted steps are skipped via the isGranted checks.
  Future<void> _requestAllPermissions() async {
    // 1. Notification — safe, never triggers Activity recreation.
    if (!await ph.Permission.notification.isGranted) {
      await ph.Permission.notification.request();
    }
    if (!mounted) return;

    // 2. Foreground location — may trigger Activity recreation on some OEMs.
    //    If the Activity dies here, next mount resumes from step 3.
    if (!await ph.Permission.location.isGranted) {
      await ph.Permission.location.request();
    }
    if (!mounted) return;

    // 3. Background location (must be requested separately, AFTER foreground
    //    location is granted — Android policy). Skip entirely if foreground
    //    location was denied — no point asking for "all the time" without it.
    if (!await ph.Permission.location.isGranted) return;
    final bgStatus = await ph.Permission.locationAlways.status;
    if (!mounted) return;
    if (bgStatus.isDenied) {
      // Show rationale before the OS prompt — Android 11+ just opens
      // settings silently, which is confusing without context.
      final l10n = AppLocalizations.of(context);
      final shouldContinue = await showDialog<bool>(
        context: context,
        builder: (_) => AlertDialog(
          title: Text(l10n.backgroundLocationTitle),
          content: Text(l10n.backgroundLocationMessage),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: Text(l10n.backgroundLocationLater),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: Text(l10n.backgroundLocationContinue),
            ),
          ],
        ),
      );
      if (shouldContinue == true) {
        await ph.Permission.locationAlways.request();
      }
    }
    if (!mounted) return;

    // 4. Battery optimization exemption (one-shot, persisted via secure storage)
    await _promptBatteryOptimizationIfFirstTime();
  }

  /// Prompt the user to disable battery optimization for the app, the first
  /// time they go online. Without this, aggressive OEM power management
  /// (Xiaomi/Samsung/Oppo/Vivo) can kill the location foreground service even
  /// while the app is online, causing the backend to mark the driver stale and
  /// kick them from the queue.
  ///
  /// This is a one-shot prompt — we persist a flag so the user is not pestered
  /// every time they go online. They can still manually re-enable optimization
  /// from system settings if they choose.
  static const _batteryOptPromptedKey = 'battery_opt_prompted_v1';
  Future<void> _promptBatteryOptimizationIfFirstTime() async {
    const storage = FlutterSecureStorage();
    final alreadyPrompted = await storage.read(key: _batteryOptPromptedKey);
    if (alreadyPrompted == 'true') return;

    // If the user already granted the exemption (e.g. via OEM defaults), just
    // record the flag and skip the dialog.
    final current = await ph.Permission.ignoreBatteryOptimizations.status;
    if (current.isGranted) {
      await storage.write(key: _batteryOptPromptedKey, value: 'true');
      return;
    }

    if (!mounted) return;
    final l10n = AppLocalizations.of(context);
    final shouldOpen = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: Text(l10n.batteryOptimizationTitle),
        content: Text(l10n.batteryOptimizationMessage),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(l10n.batteryOptimizationLater),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(l10n.batteryOptimizationContinue),
          ),
        ],
      ),
    );

    if (shouldOpen == true) {
      await ph.Permission.ignoreBatteryOptimizations.request();
    }

    // Persist the flag regardless of the user's choice — we don't want to
    // re-prompt every time they go online.
    await storage.write(key: _batteryOptPromptedKey, value: 'true');
  }

  /// Check if location permission is granted (used before goOnline).
  Future<bool> _checkLocationPermission() async {
    final permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.whileInUse ||
        permission == LocationPermission.always) {
      return true;
    }
    // If denied at this point, direct to settings
    if (mounted) {
      final l10n = AppLocalizations.of(context);
      await showDialog<void>(
        context: context,
        builder: (_) => AlertDialog(
          title: Text(l10n.locationPermissionTitle),
          content: Text(l10n.locationPermissionMessage),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: Text(l10n.cancel),
            ),
            TextButton(
              onPressed: () {
                Navigator.pop(context);
                Geolocator.openAppSettings();
              },
              child: Text(l10n.openSettings),
            ),
          ],
        ),
      );
    }
    return false;
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _incomingRequestSub?.close();
    _driverStatusSub?.close();
    super.dispose();
  }

  Future<void> _showRideRequestSheet(RideRequest request) async {
    if (!mounted || _isPresentingRideRequest) {
      return;
    }

    _isPresentingRideRequest = true;

    await Navigator.of(context).push(
      MaterialPageRoute(
        fullscreenDialog: true,
        builder: (_) => RideRequestScreen(request: request),
      ),
    );

    if (mounted) {
      ref.read(driverIncomingRequestProvider.notifier).clear();
    }

    _isPresentingRideRequest = false;
  }

  void _showCreditsInfoSheet(BuildContext context, int balance) {
    final l10n = AppLocalizations.of(context);

    final Color accentColor = balance == 0
        ? Colors.red[700]!
        : balance < 5
            ? Colors.orange[700]!
            : Colors.green[700]!;

    final String statusLabel = balance == 0
        ? l10n.noCreditsBalance
        : balance < 5
            ? l10n.lowCreditsBalance
            : l10n.sufficientCreditsBalance;

    final String statusMessage = balance == 0
        ? l10n.noCreditsMessage
        : balance < 5
            ? l10n.lowCreditsMessage
            : l10n.sufficientCreditsMessage;

    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (_) => Padding(
        padding: const EdgeInsets.fromLTRB(24, 20, 24, 32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header
            Row(
              children: [
                Icon(Icons.toll_rounded, color: accentColor, size: 28),
                const SizedBox(width: 10),
                Text(
                  l10n.yourCreditsTitle,
                  style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                ),
              ],
            ),
            const SizedBox(height: 16),

            // Balance badge
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 16),
              decoration: BoxDecoration(
                color: accentColor,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Column(
                children: [
                  Text(
                    '$balance',
                    style: const TextStyle(
                      fontSize: 40,
                      fontWeight: FontWeight.bold,
                      color: Colors.white,
                    ),
                  ),
                  Text(
                    statusLabel,
                    style: const TextStyle(fontSize: 13, color: Colors.white),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Status message
            Text(statusMessage, style: const TextStyle(fontSize: 14)),
            const SizedBox(height: 16),

            // How credits work
            Text(
              l10n.howCreditsWork,
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            _CreditInfoRow(
              icon: Icons.check_circle_outline,
              text: l10n.creditInfo1,
            ),
            _CreditInfoRow(
              icon: Icons.block,
              text: l10n.creditInfo2,
            ),
            _CreditInfoRow(
              icon: Icons.admin_panel_settings_outlined,
              text: l10n.creditInfo3,
            ),
            const SizedBox(height: 24),

            // Top-up placeholder (future feature)
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: null, // TODO: implement top-up flow
                icon: const Icon(Icons.add_card_outlined),
                label: Text(l10n.topUpComingSoon),
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final l10n = AppLocalizations.of(context);
    final user = ref.watch(currentUserProvider);
    final driverStatus = ref.watch(driverStatusProvider);
    final statisticsAsync = ref.watch(driverStatisticsProvider);
    final isDriverVerified = ref.watch(isDriverVerifiedProvider);
    final creditsAsync = ref.watch(creditsProvider);
    final kycSubmission = ref.watch(kycStatusProvider);

    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            Text(config.appName),
            const SizedBox(width: 12),
            // Online/Offline indicator
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: driverStatus.isOnline ? Colors.green : Colors.grey,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Text(
                driverStatus.isOnline ? l10n.statusOnlineLabel : l10n.statusOfflineLabel,
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.bold,
                  color: Colors.white,
                ),
              ),
            ),
          ],
        ),
        backgroundColor: config.primaryColor,
        foregroundColor: Colors.white,
        actions: [
          // Credit balance chip
          creditsAsync.whenData(
            (balance) => Padding(
              padding: const EdgeInsets.only(right: 4),
              child: ActionChip(
                label: Text(
                  l10n.creditsChip(balance),
                  style: const TextStyle(
                    fontSize: 12,
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                backgroundColor: balance == 0
                    ? Colors.red[700]
                    : balance < 5
                        ? Colors.orange[700]
                        : Colors.green[700],
                padding: EdgeInsets.zero,
                visualDensity: VisualDensity.compact,
                onPressed: () => _showCreditsInfoSheet(context, balance),
              ),
            ),
          ).value ??
              const SizedBox.shrink(),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          await Future.wait([
            ref.refresh(driverStatisticsProvider.future).then((_) {}).onError((_, __) {}),
            ref.refresh(creditsProvider.future).then((_) {}).onError((_, __) {}),
          ]);
        },
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          child: Padding(
            padding: const EdgeInsets.all(16.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // Welcome Card
                Card(
                  elevation: 2,
                  child: Padding(
                    padding: const EdgeInsets.all(16.0),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          l10n.welcomeDriver(user?.name ?? l10n.driverFallback),
                          style: Theme.of(context).textTheme.headlineSmall,
                        ),
                        const SizedBox(height: 8),
                        Text(
                          driverStatus.isOnline
                              ? driverStatus.hasActiveRide
                                  ? l10n.statusOnlineWithRide
                                  : l10n.statusOnlineIdle
                              : l10n.statusOfflineMessage,
                          style:
                              Theme.of(context).textTheme.bodyMedium?.copyWith(
                                    color: Colors.grey[600],
                                  ),
                        ),
                      ],
                    ),
                  ),
                ),

                const SizedBox(height: 16),

                // Queue Position Card (only when online and not in active ride)
                if (driverStatus.isOnline && !driverStatus.hasActiveRide)
                  AnimatedSwitcher(
                    duration: const Duration(milliseconds: 300),
                    child: Card(
                      key: ValueKey(driverStatus.queuePosition),
                      elevation: 2,
                      color: driverStatus.queuePosition == 1
                          ? Colors.green[50]
                          : Colors.blue[50],
                      child: Padding(
                        padding: const EdgeInsets.all(16.0),
                        child: Row(
                          children: [
                            Icon(
                              Icons.queue,
                              size: 36,
                              color: driverStatus.queuePosition == 1
                                  ? Colors.green
                                  : config.primaryColor,
                            ),
                            const SizedBox(width: 16),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    driverStatus.queuePosition == 0
                                        ? l10n.joiningQueue
                                        : driverStatus.queuePosition == 1
                                            ? l10n.youreNextInQueue
                                            : l10n.queuePositionNumber(driverStatus.queuePosition),
                                    style: const TextStyle(
                                      fontSize: 18,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    driverStatus.queuePosition <= 1
                                        ? l10n.nextRideComing
                                        : l10n.driversAheadOfYou(driverStatus.queuePosition - 1),
                                    style: TextStyle(
                                      fontSize: 13,
                                      color: Colors.grey[700],
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),

                const SizedBox(height: 16),

                // Today's Earnings Card
                statisticsAsync.when(
                  data: (stats) => Card(
                    elevation: 2,
                    child: Padding(
                      padding: const EdgeInsets.all(16.0),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            l10n.todaysEarningsTitle,
                            style: Theme.of(context)
                                .textTheme
                                .titleMedium
                                ?.copyWith(
                                  color: Colors.grey[700],
                                ),
                          ),
                          const SizedBox(height: 12),
                          InkWell(
                            onTap: () => Navigator.push(context,
                              MaterialPageRoute(builder: (_) => const EarningsHistoryScreen())),
                            borderRadius: BorderRadius.circular(8),
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      l10n.currencyFormat(_formatCurrency(stats.todayEarnings)),
                                      style: Theme.of(context)
                                          .textTheme
                                          .headlineMedium
                                          ?.copyWith(
                                            color: config.primaryColor,
                                            fontWeight: FontWeight.bold,
                                          ),
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      l10n.ridesCompletedToday(stats.todayRides),
                                      style: TextStyle(
                                        fontSize: 14,
                                        color: Colors.grey[600],
                                      ),
                                    ),
                                  ],
                                ),
                                Icon(
                                  Icons.chevron_right,
                                  size: 28,
                                  color: config.primaryColor.withValues(alpha: 0.5),
                                ),
                              ],
                            ),
                          ),
                          const Divider(height: 24),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceAround,
                            children: [
                              _buildStatItem(
                                context,
                                Icons.star,
                                stats.rating.toStringAsFixed(1),
                                l10n.ratingLabel,
                                onTap: () => Navigator.push(context,
                                  MaterialPageRoute(builder: (_) => const RatingHistoryScreen())),
                              ),
                              _buildStatItem(
                                context,
                                Icons.two_wheeler,
                                stats.totalRides.toString(),
                                l10n.totalRidesLabel,
                                onTap: () => Navigator.push(context,
                                  MaterialPageRoute(builder: (_) => const DriverRideHistoryScreen())),
                              ),
                              _buildStatItem(
                                context,
                                !(user?.isActive ?? true)
                                    ? Icons.block
                                    : stats.isVerified
                                        ? Icons.verified
                                        : Icons.pending_outlined,
                                !(user?.isActive ?? true)
                                    ? l10n.statusSuspended
                                    : stats.isVerified
                                        ? l10n.statusVerified
                                        : l10n.statusUnverified,
                                l10n.statusLabel,
                                onTap: () => Navigator.push(context,
                                  MaterialPageRoute(builder: (_) => const KycStatusScreen())),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                  loading: () => const Card(
                    elevation: 2,
                    child: Padding(
                      padding: EdgeInsets.all(32.0),
                      child: Center(
                        child: CircularProgressIndicator(),
                      ),
                    ),
                  ),
                  error: (error, stack) => Card(
                    elevation: 2,
                    child: Padding(
                      padding: const EdgeInsets.all(16.0),
                      child: Column(
                        children: [
                          const Icon(Icons.error_outline,
                              size: 48, color: Colors.red),
                          const SizedBox(height: 8),
                          Text(
                            l10n.unableToLoadStats,
                            style: TextStyle(color: Colors.grey[600]),
                          ),
                          const SizedBox(height: 8),
                          ElevatedButton.icon(
                            onPressed: () {
                              ref.invalidate(driverStatisticsProvider);
                            },
                            icon: const Icon(Icons.refresh),
                            label: Text(l10n.retry),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),

                const SizedBox(height: 24),

                // Active Ride Card (if any)
                if (driverStatus.hasActiveRide)
                  Card(
                    elevation: 2,
                    color: Colors.blue[50],
                    child: Padding(
                      padding: const EdgeInsets.all(16.0),
                      child: Column(
                        children: [
                          const Icon(
                            Icons.two_wheeler,
                            size: 48,
                            color: Colors.blue,
                          ),
                          const SizedBox(height: 8),
                          Text(
                            l10n.youHaveActiveRide,
                            style: const TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          const SizedBox(height: 8),
                          ElevatedButton(
                            onPressed: () {
                              if (driverStatus.activeRideId != null) {
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (context) => ActiveRideScreen(
                                      rideId: driverStatus.activeRideId!,
                                    ),
                                  ),
                                );
                              }
                            },
                            style: ElevatedButton.styleFrom(
                              backgroundColor: Colors.blue,
                              foregroundColor: Colors.white,
                            ),
                            child: Text(l10n.viewActiveRide),
                          ),
                        ],
                      ),
                    ),
                  ),

                const SizedBox(height: 16),

                // Suspended warning
                if (!(user?.isActive ?? true))
                  Card(
                    color: Colors.red[50],
                    child: Padding(
                      padding: const EdgeInsets.all(12.0),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Icon(Icons.block, color: Colors.red, size: 28),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  l10n.accountSuspendedContactAdmin,
                                  style: const TextStyle(
                                    color: Colors.red,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                if (kycSubmission?.suspendReason != null) ...[
                                  const SizedBox(height: 4),
                                  Text(
                                    l10n.suspensionReason(kycSubmission!.suspendReason!),
                                    style: TextStyle(
                                      color: Colors.red[700],
                                      fontSize: 13,
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),

                // Unverified warning (email verified but admin not approved)
                if ((user?.isActive ?? true) && !isDriverVerified)
                  Card(
                    color: Colors.orange[50],
                    child: Padding(
                      padding: const EdgeInsets.all(12.0),
                      child: Row(
                        children: [
                          const Icon(Icons.hourglass_top_rounded,
                              color: Colors.orange, size: 28),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              l10n.pendingApprovalBanner,
                              style: const TextStyle(color: Colors.deepOrange),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),

                // Error Display
                if (driverStatus.error != null)
                  Card(
                    color: Colors.red[50],
                    child: Padding(
                      padding: const EdgeInsets.all(12.0),
                      child: Row(
                        children: [
                          const Icon(Icons.error, color: Colors.red),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              driverStatus.error!,
                              style: const TextStyle(color: Colors.red),
                            ),
                          ),
                          IconButton(
                            icon: const Icon(Icons.close, size: 20),
                            onPressed: () {
                              ref
                                  .read(driverStatusProvider.notifier)
                                  .clearError();
                            },
                          ),
                        ],
                      ),
                    ),
                  ),

                // Low-credit warning (only when offline)
                if (!driverStatus.isOnline)
                  creditsAsync.whenData((balance) {
                    if (balance == 0) {
                      return Card(
                        color: Colors.red[50],
                        child: Padding(
                          padding: const EdgeInsets.all(12.0),
                          child: Row(
                            children: [
                              const Icon(Icons.credit_card_off,
                                  color: Colors.red, size: 28),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Text(
                                  l10n.noCreditsWarning,
                                  style: const TextStyle(color: Colors.red),
                                ),
                              ),
                            ],
                          ),
                        ),
                      );
                    } else if (balance < 5) {
                      return Card(
                        color: Colors.orange[50],
                        child: Padding(
                          padding: const EdgeInsets.all(12.0),
                          child: Row(
                            children: [
                              const Icon(Icons.warning_amber,
                                  color: Colors.orange, size: 28),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Text(
                                  l10n.lowCreditsWarning(balance),
                                  style: const TextStyle(color: Colors.deepOrange),
                                ),
                              ),
                            ],
                          ),
                        ),
                      );
                    }
                    return const SizedBox.shrink();
                  }).value ??
                      const SizedBox.shrink(),

                const SizedBox(height: 24),

                // Quick Actions
                Row(
                  children: [
                    Expanded(
                      child: ElevatedButton.icon(
                        onPressed: () {
                          Navigator.push(context,
                            MaterialPageRoute(builder: (_) => const DriverRideHistoryScreen()));
                        },
                        icon: const Icon(Icons.history),
                        label: Text(l10n.rideHistoryButton),
                        style: ElevatedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 12),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: ElevatedButton.icon(
                        onPressed: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => const DriverSettingsScreen(),
                            ),
                          );
                        },
                        icon: const Icon(Icons.settings),
                        label: Text(l10n.settingsButton),
                        style: ElevatedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 12),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
      floatingActionButton: _buildFloatingActionButton(context),
      floatingActionButtonLocation: FloatingActionButtonLocation.centerFloat,
    );
  }

  Widget _buildStatItem(
    BuildContext context,
    IconData icon,
    String value,
    String label, {
    VoidCallback? onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        child: Column(
          children: [
            Icon(icon, size: 24, color: AppConfig.instance.primaryColor),
            const SizedBox(height: 4),
            Text(
              value,
              style: const TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
            Text(
              label,
              style: TextStyle(
                fontSize: 12,
                color: Colors.grey[600],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFloatingActionButton(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final driverStatus = ref.watch(driverStatusProvider);
    final user = ref.watch(currentUserProvider);
    final isDriverVerified = ref.watch(isDriverVerifiedProvider);

    // Show loading if user is not loaded yet
    if (user == null) {
      return FloatingActionButton.extended(
        onPressed: null,
        icon: const SizedBox(
          width: 20,
          height: 20,
          child: CircularProgressIndicator(
            strokeWidth: 2,
            valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
          ),
        ),
        label: Text(l10n.loadingProfile),
        backgroundColor: Colors.grey,
      );
    }

    if (driverStatus.isLoading) {
      return FloatingActionButton.extended(
        onPressed: null,
        icon: const SizedBox(
          width: 20,
          height: 20,
          child: CircularProgressIndicator(
            strokeWidth: 2,
            valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
          ),
        ),
        label: Text(l10n.loading),
        backgroundColor: Colors.grey,
      );
    }

    if (driverStatus.isOnline) {
      return FloatingActionButton.extended(
        // ✅ FIX: Disable button if driver has active ride
        onPressed: driverStatus.hasActiveRide
            ? null
            : () async {
                HapticFeedback.mediumImpact();
                await ref.read(driverStatusProvider.notifier).goOffline();
              },
        icon: const Icon(Icons.stop),
        label: Text(driverStatus.hasActiveRide ? l10n.activeRideButton : l10n.goOfflineButton),
        backgroundColor: driverStatus.hasActiveRide ? Colors.grey : Colors.red,
        foregroundColor: Colors.white,
      );
    }

    final isSuspended = !(user?.isActive ?? true);
    final canGoOnline = !isSuspended && isDriverVerified;

    return FloatingActionButton.extended(
      onPressed: canGoOnline
          ? () async {
              HapticFeedback.mediumImpact();
              final hasPermission = await _checkLocationPermission();
              if (!hasPermission || !mounted) return;
              await ref.read(driverStatusProvider.notifier).goOnline();
              final error = ref.read(driverStatusProvider).error;
              if (error != null && error.contains('credit') && mounted) {
                ref.read(driverStatusProvider.notifier).clearError();
                final balance = ref.read(creditsProvider).value ?? 0;
                _showCreditsInfoSheet(this.context, balance);
              }
            }
          : null,
      icon: const Icon(Icons.play_arrow),
      label: Text(l10n.goOnlineButton),
      backgroundColor: canGoOnline ? AppConfig.instance.primaryColor : Colors.grey,
      foregroundColor: Colors.white,
    );
  }

  String _formatCurrency(double amount) {
    // Format as Indonesian Rupiah
    if (amount >= 1000000) {
      return '${(amount / 1000000).toStringAsFixed(1)}M';
    } else if (amount >= 1000) {
      return '${(amount / 1000).toStringAsFixed(1)}K';
    }
    return amount.toStringAsFixed(0);
  }

}

class _CreditInfoRow extends StatelessWidget {
  final IconData icon;
  final String text;

  const _CreditInfoRow({required this.icon, required this.text});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 16, color: Colors.grey[600]),
          const SizedBox(width: 8),
          Expanded(
            child: Text(text, style: const TextStyle(fontSize: 13)),
          ),
        ],
      ),
    );
  }
}
