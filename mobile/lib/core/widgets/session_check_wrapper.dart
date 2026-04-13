import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../config/app_config.dart';
import '../models/session_state.dart';
import '../providers/session_provider.dart';
import '../providers/active_ride_provider.dart';
import '../providers/driver_status_provider.dart';
import '../providers/ride_request_provider.dart';
import '../providers/user_location_provider.dart';
import '../../rider/screens/rider_active_ride_screen.dart';
import '../../rider/screens/waiting_screen.dart';
import '../../driver/screens/active_ride_screen.dart';
import 'splash_screen.dart';

/// Widget that checks for active session and navigates appropriately
///
/// This widget:
/// - Checks for active rides/requests on app launch
/// - Navigates to the correct screen based on session state
/// - Handles both rider and driver apps
class SessionCheckWrapper extends ConsumerStatefulWidget {
  final Widget defaultHomeScreen;

  const SessionCheckWrapper({
    super.key,
    required this.defaultHomeScreen,
  });

  @override
  ConsumerState<SessionCheckWrapper> createState() =>
      _SessionCheckWrapperState();
}

class _SessionCheckWrapperState extends ConsumerState<SessionCheckWrapper>
    with WidgetsBindingObserver {
  bool _isChecking = true;
  Widget? _targetScreen;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);

    // Delay session check until after the first frame is built
    // This prevents "setState during build" errors
    WidgetsBinding.instance.addPostFrameCallback((_) {
      // Eagerly trigger location permission prompt right after sign-in.
      // The provider's constructor calls checkPermission(), so reading
      // it forces instantiation before the home screen mounts.
      ref.read(userLocationProvider);
      _checkSession();
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _handleAppResume();
    }
  }

  Future<void> _checkSession() async {
    setState(() {
      _isChecking = true;
    });

    try {
      final sessionState =
          await ref.read(sessionStateProvider.notifier).checkSession();

      if (!mounted) return;

      if (sessionState == null || sessionState.isIdle) {
        // Restore rider cooldown if active (survives app restart)
        if (sessionState?.riderCooldown != null) {
          final cd = sessionState!.riderCooldown!;
          ref.read(rideRequestProvider.notifier).applyCancelPenalty(
            cancelCount: cd.cancelCount,
            cooldownUntil: cd.cooldownUntil,
            isSuspended: false,
          );
        }

        // No active session, go to default home screen.
        // Sync driver status with backend. On a cold launch the driver may
        // have been left online by a force-quit / crash. Always go offline in
        // that case so they must tap "Go Online" again — this also clears the
        // stale went_online_at on the backend (the webhook may not have fired).
        // Background → foreground resumes are handled by _handleAppResume, not
        // here, so this logic is safe for the M-7 (background resume) flow.
        Future.microtask(() {
          if (mounted) {
            final appConfig = AppConfig.instance;
            if (appConfig.isDriverApp &&
                sessionState != null &&
                sessionState.driverContext.isDriver) {
              final driverCtx = sessionState.driverContext;
              if (driverCtx.isOnline && driverCtx.activeRideId == null) {
                // Cold launch: backend still shows online but no active ride.
                // Kick offline so the driver starts fresh each launch.
                ref.read(driverStatusProvider.notifier).kickOfflineOnLaunch();
              } else {
                ref.read(driverStatusProvider.notifier).syncFromBackend(
                  isOnline: driverCtx.isOnline,
                  activeRideId: driverCtx.activeRideId,
                );
              }
            }
          }
        });

        setState(() {
          _targetScreen = widget.defaultHomeScreen;
          _isChecking = false;
        });
        return;
      }

      // Has active session, navigate based on state
      final targetScreen = _getScreenForSessionState(sessionState);

      setState(() {
        _targetScreen = targetScreen;
        _isChecking = false;
      });

      // Update providers AFTER setState completes
      // This prevents any potential "setState during build" errors
      Future.microtask(() {
        if (mounted) {
          // Update active ride provider if there's an active ride
          if (sessionState.activeRide != null) {
            ref.read(activeRideProvider.notifier).setRide(sessionState.activeRide!);
          }

          // Sync ride request provider if there's an active request (for riders)
          final appConfig = AppConfig.instance;
          if (!appConfig.isDriverApp && sessionState.activeRequest != null) {
            ref.read(rideRequestProvider.notifier).setRequestFromSession(
              sessionState.activeRequest!,
            );
          }

          // Sync driver status with backend if this is driver app
          if (appConfig.isDriverApp && sessionState.driverContext.isDriver) {
            ref.read(driverStatusProvider.notifier).syncFromBackend(
              isOnline: sessionState.driverContext.isOnline,
              activeRideId: sessionState.driverContext.activeRideId,
            );
          }
        }
      });
    } catch (e) {
      if (kDebugMode) print('SessionCheckWrapper: Error checking session: $e');
      // On error, default to home screen
      if (!mounted) return;
      setState(() {
        _targetScreen = widget.defaultHomeScreen;
        _isChecking = false;
      });
    }
  }

  Future<void> _handleAppResume() async {
    final sessionNotifier = ref.read(sessionStateProvider.notifier);

    // Always refresh session on resume — even brief screen-off can break
    // WebSocket and leave stale state. The API call is lightweight.
    final sessionState = await sessionNotifier.refreshSession();

    if (!mounted) return;

    if (sessionState != null) {
      final appConfig = AppConfig.instance;

      // Sync driver status so online/offline reflects backend truth
      if (appConfig.isDriverApp && sessionState.driverContext.isDriver) {
        ref.read(driverStatusProvider.notifier).syncFromBackend(
          isOnline: sessionState.driverContext.isOnline,
          activeRideId: sessionState.driverContext.activeRideId,
        );
      }

      // Sync active ride provider with backend truth
      if (sessionState.activeRide != null) {
        ref.read(activeRideProvider.notifier).setRide(sessionState.activeRide!);
      } else {
        ref.read(activeRideProvider.notifier).reset();
      }
    }

    // No resume dialog — the provider sync above already updates ride state.
    // The user is already on the correct screen (active ride / waiting / home),
    // so prompting "Continue session?" is redundant and confusing.
  }

  Widget _getScreenForSessionState(SessionState sessionState) {
    final config = AppConfig.instance;

    if (kDebugMode) print('SessionCheckWrapper._getScreenForSessionState:');
    if (kDebugMode) print('   State: ${sessionState.state}');
    if (kDebugMode) print('   Ride Role: ${sessionState.rideRole}');
    if (kDebugMode) print('   Active Ride: ${sessionState.activeRide?.id}');
    if (kDebugMode) print('   Is Driver App: ${config.isDriverApp}');
    if (kDebugMode) print('   Driver Context: online=${sessionState.driverContext.isOnline}, activeRideId=${sessionState.driverContext.activeRideId}');

    switch (sessionState.state) {
      case SessionStateType.rideActive:
        if (sessionState.activeRide == null) {
          if (kDebugMode) print('   State is rideActive but activeRide is null!');
          return widget.defaultHomeScreen;
        }

        // Navigate based on role
        // Note: activeRideProvider is already updated in _checkSession()
        // Only navigate to ride screen if role matches current app
        if (config.isDriverApp &&
            sessionState.rideRole == RideRole.driver) {
          if (kDebugMode) print('   Navigating to ActiveRideScreen (rideId: ${sessionState.activeRide!.id})');
          return ActiveRideScreen(rideId: sessionState.activeRide!.id);
        } else if (!config.isDriverApp &&
            sessionState.rideRole == RideRole.rider) {
          if (kDebugMode) print('   Navigating to RiderActiveRideScreen (rideId: ${sessionState.activeRide!.id})');
          return RiderActiveRideScreen(initialRide: sessionState.activeRide!);
        }

        // Role mismatch (e.g., driver ride but on rider app)
        // Go to home screen - user needs to switch to correct app
        if (kDebugMode) print('   Role mismatch - going to home screen');
        if (kDebugMode) print('   rideRole: ${sessionState.rideRole}, isDriverApp: ${config.isDriverApp}');
        return widget.defaultHomeScreen;

      case SessionStateType.requestMatched:
      case SessionStateType.requestInProgress:
        if (sessionState.activeRequest == null) {
          return widget.defaultHomeScreen;
        }

        // For riders, show waiting screen
        if (!config.isDriverApp) {
          return const WaitingScreen();
        }

        // For drivers, this means they accepted but haven't started yet
        // Just go to driver home which will show the matched request
        return widget.defaultHomeScreen;

      case SessionStateType.requestPending:
        if (sessionState.activeRequest == null) {
          if (kDebugMode) print('   State is requestPending but activeRequest is null!');
          return widget.defaultHomeScreen;
        }

        // For riders, show waiting screen for pending requests
        if (!config.isDriverApp) {
          if (kDebugMode) print('   Navigating to WaitingScreen (pending request)');
          return const WaitingScreen();
        }

        // For drivers, go to home (shouldn't happen normally)
        return widget.defaultHomeScreen;

      case SessionStateType.idle:
        return widget.defaultHomeScreen;
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isChecking) {
      return const SplashScreen();
    }

    return _targetScreen ?? widget.defaultHomeScreen;
  }
}
