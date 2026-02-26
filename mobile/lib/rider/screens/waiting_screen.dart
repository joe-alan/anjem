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

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final requestState = ref.watch(rideRequestProvider);

    // Listen for driver match - but NOT if we're cancelling
    ref.listen<RideRequestState>(rideRequestProvider, (previous, next) {
      if (_isCancelling) return; // Don't navigate if cancel is in progress

      if (next.isMatched && next.matchedRide != null && mounted) {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (context) => RiderActiveRideScreen(
              initialRide: next.matchedRide!,
            ),
          ),
        );
      }
    });

    return Scaffold(
      appBar: AppBar(
        title: const Text('Finding Driver'),
        backgroundColor: config.primaryColor,
        foregroundColor: Colors.white,
        automaticallyImplyLeading: false,
      ),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              // Animated waiting indicator
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

              // Cancel button
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
                          // Set flag to prevent race condition with driver match
                          setState(() {
                            _isCancelling = true;
                          });

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
                  padding:
                      const EdgeInsets.symmetric(horizontal: 32, vertical: 12),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
