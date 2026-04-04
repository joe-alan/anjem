import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:mobile/l10n/app_localizations.dart';
import '../../core/config/app_config.dart';
import '../../core/models/ride.dart';
import '../../core/providers/ride_history_provider.dart';

class EarningsHistoryScreen extends ConsumerStatefulWidget {
  const EarningsHistoryScreen({super.key});

  @override
  ConsumerState<EarningsHistoryScreen> createState() =>
      _EarningsHistoryScreenState();
}

class _EarningsHistoryScreenState
    extends ConsumerState<EarningsHistoryScreen> {
  @override
  void initState() {
    super.initState();
    // Ensure we show completed rides
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(rideHistoryProvider.notifier).setFilter('completed');
    });
  }

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final l10n = AppLocalizations.of(context);
    final historyState = ref.watch(rideHistoryProvider);

    final completedRides = historyState.rides
        .where((r) => r.status == RideStatus.completed)
        .toList();

    final now = DateTime.now();
    final todayStart = DateTime(now.year, now.month, now.day);
    final weekStart = todayStart.subtract(Duration(days: todayStart.weekday - 1));
    final monthStart = DateTime(now.year, now.month, 1);

    double todayEarnings = 0;
    double weekEarnings = 0;
    double monthEarnings = 0;

    for (final ride in completedRides) {
      final date = ride.completedAt ?? ride.createdAt;
      if (!date.isBefore(todayStart)) todayEarnings += ride.fare;
      if (!date.isBefore(weekStart)) weekEarnings += ride.fare;
      if (!date.isBefore(monthStart)) monthEarnings += ride.fare;
    }

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.earningsHistoryTitle),
        backgroundColor: config.primaryColor,
        foregroundColor: Colors.white,
      ),
      body: historyState.isLoading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: () async {
                ref.read(rideHistoryProvider.notifier).refresh();
              },
              child: ListView(
                children: [
                  // Summary card
                  Card(
                    margin: const EdgeInsets.all(16),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceAround,
                        children: [
                          _EarningsColumn(
                            label: l10n.earningsToday,
                            amount: todayEarnings,
                            color: config.primaryColor,
                          ),
                          _EarningsColumn(
                            label: l10n.earningsThisWeek,
                            amount: weekEarnings,
                            color: config.primaryColor,
                          ),
                          _EarningsColumn(
                            label: l10n.earningsThisMonth,
                            amount: monthEarnings,
                            color: config.primaryColor,
                          ),
                        ],
                      ),
                    ),
                  ),

                  if (completedRides.isEmpty)
                    Padding(
                      padding: const EdgeInsets.all(48),
                      child: Center(
                        child: Column(
                          children: [
                            Icon(Icons.account_balance_wallet,
                                size: 64, color: Colors.grey[400]),
                            const SizedBox(height: 16),
                            Text(l10n.noEarningsYet,
                                style: TextStyle(color: Colors.grey[600])),
                          ],
                        ),
                      ),
                    )
                  else
                    ...completedRides.map(
                        (ride) => _EarningsRideItem(ride: ride)),
                ],
              ),
            ),
    );
  }
}

class _EarningsColumn extends StatelessWidget {
  final String label;
  final double amount;
  final Color color;

  const _EarningsColumn({
    required this.label,
    required this.amount,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    return Column(
      children: [
        Text(label,
            style: TextStyle(fontSize: 12, color: Colors.grey[600])),
        const SizedBox(height: 4),
        Text(
          l10n.currencyFormat(amount.toStringAsFixed(0)),
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.bold,
            color: color,
          ),
        ),
      ],
    );
  }
}

class _EarningsRideItem extends StatelessWidget {
  final Ride ride;

  const _EarningsRideItem({required this.ride});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final dateFormat = DateFormat('MMM dd • hh:mm a');
    final date = ride.completedAt ?? ride.createdAt;

    return ListTile(
      leading: const Icon(Icons.two_wheeler),
      title: Text(
        '${ride.pickupLocation.name} → ${ride.destinationLocation.name}',
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
      ),
      subtitle: Text(dateFormat.format(date)),
      trailing: Text(
        l10n.currencyFormat(ride.fare.toStringAsFixed(0)),
        style: const TextStyle(
          fontWeight: FontWeight.bold,
          fontSize: 15,
        ),
      ),
    );
  }
}
