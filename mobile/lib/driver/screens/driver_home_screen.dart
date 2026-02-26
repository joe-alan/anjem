import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/config/app_config.dart';
import '../../core/models/ride_request.dart';
import '../../core/providers/driver_incoming_request_provider.dart';
import '../../core/providers/driver_status_provider.dart';
import '../../core/providers/driver_statistics_provider.dart';
import '../../core/providers/auth_provider.dart';
import '../../core/providers/kyc_provider.dart';
import '../../core/providers/session_provider.dart';
import 'ride_request_screen.dart';
import 'active_ride_screen.dart';
import 'driver_settings_screen.dart';

class DriverHomeScreen extends ConsumerStatefulWidget {
  const DriverHomeScreen({super.key});

  @override
  ConsumerState<DriverHomeScreen> createState() => _DriverHomeScreenState();
}

class _DriverHomeScreenState extends ConsumerState<DriverHomeScreen> {
  ProviderSubscription<RideRequest?>? _incomingRequestSub;
  bool _isPresentingRideRequest = false;

  @override
  void initState() {
    super.initState();
    // Refresh statistics when screen loads
    Future.microtask(() {
      ref.refresh(driverStatisticsProvider);
    });

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
  }

  @override
  void dispose() {
    _incomingRequestSub?.close();
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

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final user = ref.watch(currentUserProvider);
    final driverStatus = ref.watch(driverStatusProvider);
    final statisticsAsync = ref.watch(driverStatisticsProvider);
    final isDriverVerified = ref.watch(isDriverVerifiedProvider);

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
                driverStatus.isOnline ? 'ONLINE' : 'OFFLINE',
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
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () {
              _showLogoutDialog(context);
            },
            tooltip: 'Logout',
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          await ref.refresh(driverStatisticsProvider.future);
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
                          'Welcome, ${user?.name ?? 'Driver'}!',
                          style: Theme.of(context).textTheme.headlineSmall,
                        ),
                        const SizedBox(height: 8),
                        Text(
                          driverStatus.isOnline
                              ? driverStatus.hasActiveRide
                                  ? 'You have an active ride'
                                  : 'Waiting for ride requests...'
                              : 'Go online to start receiving ride requests',
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
                  Card(
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
                                      ? 'Joining queue...'
                                      : driverStatus.queuePosition == 1
                                          ? 'You\'re next in queue!'
                                          : 'Queue Position: #${driverStatus.queuePosition}',
                                  style: const TextStyle(
                                    fontSize: 18,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  driverStatus.queuePosition <= 1
                                      ? 'The next ride request will come to you'
                                      : '${driverStatus.queuePosition - 1} driver(s) ahead of you',
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
                            'Today\'s Earnings',
                            style: Theme.of(context)
                                .textTheme
                                .titleMedium
                                ?.copyWith(
                                  color: Colors.grey[700],
                                ),
                          ),
                          const SizedBox(height: 12),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    'Rp ${_formatCurrency(stats.todayEarnings)}',
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
                                    '${stats.todayRides} rides completed today',
                                    style: TextStyle(
                                      fontSize: 14,
                                      color: Colors.grey[600],
                                    ),
                                  ),
                                ],
                              ),
                              Icon(
                                Icons.account_balance_wallet,
                                size: 48,
                                color: config.primaryColor.withOpacity(0.3),
                              ),
                            ],
                          ),
                          const Divider(height: 24),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceAround,
                            children: [
                              _buildStatItem(
                                context,
                                Icons.star,
                                stats.rating.toStringAsFixed(1),
                                'Rating',
                              ),
                              _buildStatItem(
                                context,
                                Icons.directions_car,
                                stats.totalRides.toString(),
                                'Total Rides',
                              ),
                              _buildStatItem(
                                context,
                                stats.isVerified
                                    ? Icons.verified
                                    : Icons.pending,
                                stats.isVerified ? 'Verified' : 'Pending',
                                'KYC Status',
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
                            'Unable to load statistics',
                            style: TextStyle(color: Colors.grey[600]),
                          ),
                          const SizedBox(height: 8),
                          ElevatedButton.icon(
                            onPressed: () {
                              ref.invalidate(driverStatisticsProvider);
                            },
                            icon: const Icon(Icons.refresh),
                            label: const Text('Retry'),
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
                            Icons.local_taxi,
                            size: 48,
                            color: Colors.blue,
                          ),
                          const SizedBox(height: 8),
                          const Text(
                            'You have an active ride',
                            style: TextStyle(
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
                            child: const Text('View Active Ride'),
                          ),
                        ],
                      ),
                    ),
                  ),

                const SizedBox(height: 16),

                // KYC Status Warning (if not verified)
                if (!isDriverVerified && !driverStatus.isOnline)
                  Card(
                    color: Colors.orange[50],
                    child: Padding(
                      padding: const EdgeInsets.all(16.0),
                      child: Row(
                        children: [
                          const Icon(Icons.warning,
                              color: Colors.orange, size: 32),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text(
                                  'KYC Verification Required',
                                  style: TextStyle(
                                    fontWeight: FontWeight.bold,
                                    fontSize: 16,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  'Complete driver verification to start accepting rides',
                                  style: TextStyle(
                                    fontSize: 14,
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

                const SizedBox(height: 24),

                // Quick Actions
                Row(
                  children: [
                    Expanded(
                      child: ElevatedButton.icon(
                        onPressed: () {
                          // TODO: Navigate to earnings history
                        },
                        icon: const Icon(Icons.history),
                        label: const Text('Earnings History'),
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
                        label: const Text('Settings'),
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
    String label,
  ) {
    return Column(
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
    );
  }

  Widget _buildFloatingActionButton(BuildContext context) {
    final driverStatus = ref.watch(driverStatusProvider);
    final user = ref.watch(currentUserProvider);

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
        label: const Text('Loading Profile...'),
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
        label: const Text('Loading...'),
        backgroundColor: Colors.grey,
      );
    }

    if (driverStatus.isOnline) {
      return FloatingActionButton.extended(
        // ✅ FIX: Disable button if driver has active ride
        onPressed: driverStatus.hasActiveRide
            ? null
            : () async {
                await ref.read(driverStatusProvider.notifier).goOffline();
              },
        icon: const Icon(Icons.stop),
        label: Text(driverStatus.hasActiveRide ? 'Active Ride' : 'Go Offline'),
        backgroundColor: driverStatus.hasActiveRide ? Colors.grey : Colors.red,
        foregroundColor: Colors.white,
      );
    }

    return FloatingActionButton.extended(
      onPressed: () async {
        await ref.read(driverStatusProvider.notifier).goOnline();
      },
      icon: const Icon(Icons.play_arrow),
      label: const Text('Go Online'),
      backgroundColor: AppConfig.instance.primaryColor,
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

  void _showLogoutDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (BuildContext context) {
        return AlertDialog(
          title: const Text('Logout'),
          content: const Text('Are you sure you want to logout?'),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.of(context).pop();
              },
              child: const Text('Cancel'),
            ),
            TextButton(
              onPressed: () async {
                Navigator.of(context).pop();
                // Clear session state first to prevent stale data on re-login
                ref.read(sessionStateProvider.notifier).clearSession();
                await ref.read(authStateProvider.notifier).signOut();
                // No need to navigate - AuthenticationWrapper will automatically
                // show LoginScreen when isAuthenticated becomes false
              },
              child: const Text(
                'Logout',
                style: TextStyle(color: Colors.red),
              ),
            ),
          ],
        );
      },
    );
  }
}
