import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/config/app_config.dart';
import '../../core/providers/ride_request_provider.dart';
import 'rider_active_ride_screen.dart';
import 'rider_home_screen.dart';

class WaitingScreen extends ConsumerStatefulWidget {
  const WaitingScreen({super.key});

  @override
  ConsumerState<WaitingScreen> createState() => _WaitingScreenState();
}

class _WaitingScreenState extends ConsumerState<WaitingScreen> {
  bool _isCancelling = false;
  Timer? _countdownTimer;
  int _countdownSeconds = 0;

  @override
  void dispose() {
    _countdownTimer?.cancel();
    super.dispose();
  }

  void _startCountdown(DateTime until) {
    _countdownTimer?.cancel();
    _countdownTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) return;
      final remaining = until.difference(DateTime.now()).inSeconds;
      if (remaining <= 0) {
        _countdownTimer?.cancel();
        // Server's ExpireRideRequest job should fire around now.
        // Navigate home as a safety-net in case the WS event is missed.
        if (mounted && !_isCancelling) {
          Navigator.of(context).pushAndRemoveUntil(
            MaterialPageRoute(builder: (context) => const RiderHomeScreen()),
            (route) => false,
          );
        }
      } else {
        setState(() => _countdownSeconds = remaining);
      }
    });
    setState(() {
      _countdownSeconds =
          until.difference(DateTime.now()).inSeconds.clamp(0, 120);
    });
  }

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final requestState = ref.watch(rideRequestProvider);

    ref.listen<RideRequestState>(rideRequestProvider, (previous, next) {
      if (_isCancelling) return;

      // Driver matched
      if (next.isMatched && next.matchedRide != null && mounted) {
        _countdownTimer?.cancel();
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (context) => RiderActiveRideScreen(
              initialRide: next.matchedRide!,
            ),
          ),
        );
        return;
      }

      // Start countdown when server says no drivers are available
      if (next.noDriversAvailableUntil != null &&
          next.noDriversAvailableUntil != previous?.noDriversAvailableUntil) {
        _startCountdown(next.noDriversAvailableUntil!);
        return;
      }

      // Request cleared externally (expiry WS event arrived before countdown ended)
      if (previous?.request != null && next.request == null && !next.isMatched && mounted) {
        _countdownTimer?.cancel();
        Navigator.of(context).pushAndRemoveUntil(
          MaterialPageRoute(builder: (context) => const RiderHomeScreen()),
          (route) => false,
        );
      }
    });

    final bool noDrivers = requestState.noDriversAvailableUntil != null;

    return Scaffold(
      appBar: AppBar(
        title: Text(noDrivers ? 'No Drivers Available' : 'Finding Driver'),
        backgroundColor: config.primaryColor,
        foregroundColor: Colors.white,
        automaticallyImplyLeading: false,
      ),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: noDrivers
              ? _buildNoDriversView(config)
              : _buildSearchingView(config, requestState),
        ),
      ),
    );
  }

  Widget _buildNoDriversView(AppConfig config) {
    return Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Icon(
          Icons.directions_car_outlined,
          size: 80,
          color: Colors.grey[400],
        ),
        const SizedBox(height: 32),
        const Text(
          'No drivers available right now',
          style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 12),
        const Text(
          'Your request will expire shortly.',
          style: TextStyle(fontSize: 14),
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 32),
        // Countdown circle
        Container(
          width: 100,
          height: 100,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(color: config.primaryColor, width: 3),
          ),
          child: Center(
            child: Text(
              '$_countdownSeconds',
              style: TextStyle(
                fontSize: 36,
                fontWeight: FontWeight.bold,
                color: config.primaryColor,
              ),
            ),
          ),
        ),
        const SizedBox(height: 16),
        Text(
          'Returning to home in $_countdownSeconds seconds…',
          style: TextStyle(fontSize: 13, color: Colors.grey[600]),
          textAlign: TextAlign.center,
        ),
      ],
    );
  }

  Widget _buildSearchingView(AppConfig config, RideRequestState requestState) {
    return Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        TweenAnimationBuilder(
          tween: Tween<double>(begin: 0, end: 1),
          duration: const Duration(seconds: 2),
          builder: (context, value, child) {
            return Transform.scale(
              scale: 0.8 + (value * 0.2),
              child: Container(
                width: 120,
                height: 120,
                decoration: BoxDecoration(
                  color: config.primaryColor.withOpacity(0.1),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  Icons.search,
                  size: 60,
                  color: config.primaryColor,
                ),
              ),
            );
          },
        ),
        const SizedBox(height: 32),
        const Text(
          'Finding a driver for you...',
          style: TextStyle(
            fontSize: 20,
            fontWeight: FontWeight.bold,
          ),
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 16),
        if (requestState.request != null) ...[
          Text(
            'Queue Position: ${requestState.request!.queuePosition ?? "-"}',
            style: TextStyle(
              fontSize: 16,
              color: Colors.grey[600],
            ),
          ),
          const SizedBox(height: 8),
          const CircularProgressIndicator(),
        ],
        const SizedBox(height: 32),
        const Text(
          'Please wait while we match you with a nearby driver',
          style: TextStyle(fontSize: 14),
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 48),
        OutlinedButton.icon(
          onPressed: requestState.isLoading
              ? null
              : () async {
                  final confirmed = await showDialog<bool>(
                    context: context,
                    builder: (context) => AlertDialog(
                      title: const Text('Cancel Request?'),
                      content: const Text(
                        'Are you sure you want to cancel this ride request?',
                      ),
                      actions: [
                        TextButton(
                          onPressed: () => Navigator.of(context).pop(false),
                          child: const Text('No'),
                        ),
                        TextButton(
                          onPressed: () => Navigator.of(context).pop(true),
                          child: const Text('Yes, Cancel'),
                        ),
                      ],
                    ),
                  );

                  if (confirmed == true && mounted) {
                    setState(() => _isCancelling = true);

                    await ref
                        .read(rideRequestProvider.notifier)
                        .cancelRequest();

                    if (mounted) {
                      Navigator.of(context).pushAndRemoveUntil(
                        MaterialPageRoute(
                          builder: (context) => const RiderHomeScreen(),
                        ),
                        (route) => false,
                      );
                    }
                  }
                },
          icon: const Icon(Icons.close),
          label: const Text('Cancel Request'),
          style: OutlinedButton.styleFrom(
            foregroundColor: Colors.red,
            side: const BorderSide(color: Colors.red),
            padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 12),
          ),
        ),
      ],
    );
  }
}
