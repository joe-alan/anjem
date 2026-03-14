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

  Future<void> _showCancelDialog() async {
    if (_isCancelling) return;
    final nav = Navigator.of(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Cancel Request?'),
        content: const Text('Are you sure you want to cancel this ride request?'),
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
      await ref.read(rideRequestProvider.notifier).cancelRequest();
      if (mounted) {
        nav.pushAndRemoveUntil(
          MaterialPageRoute(builder: (context) => const RiderHomeScreen()),
          (route) => false,
        );
      }
    }
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

      // Driver matched — navigate to active ride
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

      // No drivers available — start countdown
      if (next.noDriversAvailableUntil != null &&
          next.noDriversAvailableUntil != previous?.noDriversAvailableUntil) {
        _startCountdown(next.noDriversAvailableUntil!);
        return;
      }

      // Search resumed (new driver joined) — cancel countdown
      if (previous?.noDriversAvailableUntil != null &&
          next.noDriversAvailableUntil == null &&
          next.request != null &&
          mounted) {
        _countdownTimer?.cancel();
        setState(() => _countdownSeconds = 0);
        return;
      }

      // Request cleared externally (expiry WS event arrived before countdown ended)
      if (previous?.request != null &&
          next.request == null &&
          !next.isMatched &&
          mounted) {
        _countdownTimer?.cancel();
        Navigator.of(context).pushAndRemoveUntil(
          MaterialPageRoute(builder: (context) => const RiderHomeScreen()),
          (route) => false,
        );
      }
    });

    final bool noDrivers = requestState.noDriversAvailableUntil != null;

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (bool didPop, dynamic result) {
        if (!didPop) _showCancelDialog();
      },
      child: Scaffold(
      appBar: AppBar(
        title: const Text('Finding Driver'),
        backgroundColor: config.primaryColor,
        foregroundColor: Colors.white,
        automaticallyImplyLeading: false,
      ),
      body: Column(
        children: [
          if (requestState.cancelCount >= 1)
            Container(
              width: double.infinity,
              margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              decoration: BoxDecoration(
                color: Colors.orange.shade50,
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.orange.shade300),
              ),
              child: Row(
                children: [
                  Icon(Icons.warning_amber_rounded, color: Colors.orange.shade700, size: 20),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      requestState.cancelCount >= 2
                          ? 'Warning: One more cancellation will suspend your account.'
                          : 'Warning: Repeated cancellations may result in a temporary ban.',
                      style: TextStyle(color: Colors.orange.shade800, fontSize: 13),
                    ),
                  ),
                ],
              ),
            ),
          Expanded(
          child: Center(
          child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              // Searching animation
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
                        color: config.primaryColor.withValues(alpha: 0.1),
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        noDrivers ? Icons.directions_car_outlined : Icons.search,
                        size: 60,
                        color: noDrivers ? Colors.grey[400] : config.primaryColor,
                      ),
                    ),
                  );
                },
              ),
              const SizedBox(height: 32),
              Text(
                noDrivers
                    ? 'No drivers available right now'
                    : 'Finding a driver for you...',
                style: const TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.bold,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 12),
              if (noDrivers) ...[
                Text(
                  'Retrying in $_countdownSeconds seconds…',
                  style: TextStyle(fontSize: 14, color: Colors.grey[600]),
                  textAlign: TextAlign.center,
                ),
              ] else ...[
                if (requestState.request != null)
                  Text(
                    'Queue Position: ${requestState.request!.queuePosition ?? "-"}',
                    style: TextStyle(fontSize: 16, color: Colors.grey[600]),
                  ),
                const SizedBox(height: 8),
                const CircularProgressIndicator(),
                const SizedBox(height: 16),
                const Text(
                  'Please wait while we match you with a nearby driver',
                  style: TextStyle(fontSize: 14),
                  textAlign: TextAlign.center,
                ),
              ],
              const SizedBox(height: 48),
              OutlinedButton.icon(
                onPressed: requestState.isLoading ? null : _showCancelDialog,
                icon: const Icon(Icons.close),
                label: const Text('Cancel Request'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: Colors.red,
                  side: const BorderSide(color: Colors.red),
                  padding:
                      const EdgeInsets.symmetric(horizontal: 32, vertical: 12),
                ),
              ),
            ],
          ),
        ),
        ),
        ),
        ],
      ),
      ),
    );
  }
}
