import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../config/app_config.dart';
import '../models/session_state.dart';
import '../providers/session_provider.dart';
import '../providers/active_ride_provider.dart';
import '../providers/driver_status_provider.dart';
import '../providers/ride_request_provider.dart';
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
        // No active session, go to default home screen
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
      print('SessionCheckWrapper: Error checking session: $e');
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

    // Only refresh if enough time has passed (>5 minutes)
    if (sessionNotifier.shouldRefresh()) {
      final sessionState = await sessionNotifier.refreshSession();

      if (!mounted) return;

      if (sessionState != null && !sessionState.isIdle) {
        // Show dialog asking if user wants to resume
        _showResumeDialog(sessionState);
      }
    }
  }

  void _showResumeDialog(SessionState sessionState) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        title: const Text('Continue Previous Session?'),
        content: Text(
          sessionState.needsToResumeRide
              ? 'You have an active ride. Would you like to continue?'
              : 'You have a pending ride request. Would you like to continue?',
        ),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.pop(context);

              // Navigate to the session screen
              final targetScreen = _getScreenForSessionState(sessionState);
              Navigator.of(context).pushReplacement(
                MaterialPageRoute(builder: (context) => targetScreen),
              );

              // Update active ride provider after navigation starts
              if (sessionState.activeRide != null) {
                Future.microtask(() {
                  if (mounted) {
                    ref.read(activeRideProvider.notifier).setRide(sessionState.activeRide!);
                  }
                });
              }
            },
            child: const Text('Yes'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('No'),
          ),
        ],
      ),
    );
  }

  Widget _getScreenForSessionState(SessionState sessionState) {
    final config = AppConfig.instance;

    print('🔍 SessionCheckWrapper._getScreenForSessionState:');
    print('   State: ${sessionState.state}');
    print('   Ride Role: ${sessionState.rideRole}');
    print('   Active Ride: ${sessionState.activeRide?.id}');
    print('   Is Driver App: ${config.isDriverApp}');
    print('   Driver Context: online=${sessionState.driverContext.isOnline}, activeRideId=${sessionState.driverContext.activeRideId}');

    switch (sessionState.state) {
      case SessionStateType.rideActive:
        if (sessionState.activeRide == null) {
          print('   ⚠️  State is rideActive but activeRide is null!');
          return widget.defaultHomeScreen;
        }

        // Navigate based on role
        // Note: activeRideProvider is already updated in _checkSession()
        // Only navigate to ride screen if role matches current app
        if (config.isDriverApp &&
            sessionState.rideRole == RideRole.driver) {
          print('   ✅ Navigating to ActiveRideScreen (rideId: ${sessionState.activeRide!.id})');
          return ActiveRideScreen(rideId: sessionState.activeRide!.id);
        } else if (!config.isDriverApp &&
            sessionState.rideRole == RideRole.rider) {
          print('   ✅ Navigating to RiderActiveRideScreen (rideId: ${sessionState.activeRide!.id})');
          return RiderActiveRideScreen(initialRide: sessionState.activeRide!);
        }

        // Role mismatch (e.g., driver ride but on rider app)
        // Go to home screen - user needs to switch to correct app
        print('   ⚠️  Role mismatch - going to home screen');
        print('   rideRole: ${sessionState.rideRole}, isDriverApp: ${config.isDriverApp}');
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
          print('   ⚠️  State is requestPending but activeRequest is null!');
          return widget.defaultHomeScreen;
        }

        // For riders, show waiting screen for pending requests
        if (!config.isDriverApp) {
          print('   ✅ Navigating to WaitingScreen (pending request)');
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
