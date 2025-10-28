import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/config/app_config.dart';
import '../../core/models/ride.dart';
import '../../core/providers/active_ride_provider.dart';
import 'tracking_screen.dart';

class DriverMatchedScreen extends ConsumerWidget {
  final Ride ride;

  const DriverMatchedScreen({
    super.key,
    required this.ride,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final config = AppConfig.instance;
    final driver = ride.driver;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Driver Matched!'),
        backgroundColor: config.primaryColor,
        foregroundColor: Colors.white,
        automaticallyImplyLeading: false,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            // Success icon
            Container(
              width: 100,
              height: 100,
              decoration: BoxDecoration(
                color: Colors.green.withOpacity(0.1),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.check_circle,
                size: 60,
                color: Colors.green,
              ),
            ),

            const SizedBox(height: 24),

            const Text(
              'Driver Found!',
              style: TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.bold,
              ),
            ),

            const SizedBox(height: 8),

            const Text(
              'Your driver is on the way to pick you up',
              style: TextStyle(fontSize: 14, color: Colors.grey),
              textAlign: TextAlign.center,
            ),

            const SizedBox(height: 32),

            // Driver info card
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Column(
                  children: [
                    // Driver photo and name
                    Row(
                      children: [
                        CircleAvatar(
                          radius: 40,
                          backgroundImage: driver?.avatarUrl != null
                              ? NetworkImage(driver!.avatarUrl!)
                              : null,
                          child: driver?.avatarUrl == null
                              ? Text(
                                  driver?.name[0].toUpperCase() ?? 'D',
                                  style: const TextStyle(fontSize: 32),
                                )
                              : null,
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                driver?.name ?? 'Driver',
                                style: const TextStyle(
                                  fontSize: 20,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Row(
                                children: [
                                  const Icon(Icons.star,
                                      size: 16, color: Colors.amber),
                                  const SizedBox(width: 4),
                                  Text(
                                    '${driver?.rating?.toStringAsFixed(1) ?? "N/A"} (${driver?.totalRides ?? 0} rides)',
                                    style: TextStyle(color: Colors.grey[600]),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),

                    const Divider(height: 32),

                    // Vehicle info
                    if (driver?.driverProfile != null) ...[
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text(
                            'Vehicle:',
                            style: TextStyle(color: Colors.grey),
                          ),
                          Text(
                            '${driver!.driverProfile!.vehicleType}',
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text(
                            'Plate Number:',
                            style: TextStyle(color: Colors.grey),
                          ),
                          Text(
                            driver.driverProfile!.vehiclePlate ?? 'N/A',
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text(
                            'Color:',
                            style: TextStyle(color: Colors.grey),
                          ),
                          Text(
                            driver.driverProfile!.vehicleColor ?? 'N/A',
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
            ),

            const SizedBox(height: 24),

            // ETA info
            Card(
              color: config.primaryColor.withOpacity(0.1),
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.access_time, color: config.primaryColor),
                    const SizedBox(width: 8),
                    Text(
                      'Estimated arrival: 5-10 minutes',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                        color: config.primaryColor,
                      ),
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 24),

            // Action buttons
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () {
                      // TODO: Implement call driver
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                          content: Text('Calling driver...'),
                        ),
                      );
                    },
                    icon: const Icon(Icons.phone),
                    label: const Text('Call Driver'),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: ElevatedButton.icon(
                    onPressed: () {
                      // Set active ride in provider
                      ref.read(activeRideProvider.notifier).setRide(ride);

                      Navigator.of(context).pushReplacement(
                        MaterialPageRoute(
                          builder: (context) => const TrackingScreen(),
                        ),
                      );
                    },
                    icon: const Icon(Icons.navigation),
                    label: const Text('Track Driver'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: config.primaryColor,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
