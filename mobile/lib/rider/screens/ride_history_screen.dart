import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:mobile/l10n/app_localizations.dart';
import '../../core/config/app_config.dart';
import '../../core/models/ride.dart';
import '../../core/providers/ride_history_provider.dart';

class RideHistoryScreen extends ConsumerWidget {
  const RideHistoryScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final config = AppConfig.instance;
    final historyState = ref.watch(rideHistoryProvider);
    final l10n = AppLocalizations.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.rideHistoryTitle),
        backgroundColor: config.primaryColor,
        foregroundColor: Colors.white,
      ),
      body: Column(
        children: [
          // Filter chips
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.all(16.0),
            child: Row(
              children: [
                _FilterChip(
                  label: l10n.filterAll,
                  isSelected: historyState.selectedFilter == 'all',
                  onTap: () =>
                      ref.read(rideHistoryProvider.notifier).setFilter('all'),
                ),
                const SizedBox(width: 8),
                _FilterChip(
                  label: l10n.filterCompleted,
                  isSelected: historyState.selectedFilter == 'completed',
                  onTap: () => ref
                      .read(rideHistoryProvider.notifier)
                      .setFilter('completed'),
                ),
                const SizedBox(width: 8),
                _FilterChip(
                  label: l10n.filterCancelled,
                  isSelected: historyState.selectedFilter == 'cancelled',
                  onTap: () => ref
                      .read(rideHistoryProvider.notifier)
                      .setFilter('cancelled'),
                ),
              ],
            ),
          ),

          // Rides list
          Expanded(
            child: historyState.isLoading
                ? const Center(child: CircularProgressIndicator())
                : historyState.rides.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(
                              Icons.history,
                              size: 80,
                              color: Colors.grey[400],
                            ),
                            const SizedBox(height: 16),
                            Text(
                              l10n.noRidesYet,
                              style: TextStyle(
                                fontSize: 18,
                                color: Colors.grey[600],
                              ),
                            ),
                          ],
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: () async {
                          ref.read(rideHistoryProvider.notifier).refresh();
                        },
                        child: AnimatedSwitcher(
                          duration: const Duration(milliseconds: 300),
                          child: ListView.builder(
                            key: ValueKey(historyState.selectedFilter),
                            itemCount: historyState.rides.length,
                            itemBuilder: (context, index) {
                              final ride = historyState.rides[index];
                              return _RideHistoryItem(ride: ride);
                            },
                          ),
                        ),
                      ),
          ),
        ],
      ),
    );
  }
}

class _FilterChip extends StatelessWidget {
  final String label;
  final bool isSelected;
  final VoidCallback onTap;

  const _FilterChip({
    required this.label,
    required this.isSelected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;

    return InkWell(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        decoration: BoxDecoration(
          color: isSelected ? config.primaryColor : Colors.grey[200],
          borderRadius: BorderRadius.circular(20),
        ),
        child: Text(
          label,
          style: TextStyle(
            color: isSelected ? Colors.white : Colors.black87,
            fontWeight: isSelected ? FontWeight.w600 : FontWeight.normal,
          ),
        ),
      ),
    );
  }
}

class _RideHistoryItem extends StatelessWidget {
  final Ride ride;

  const _RideHistoryItem({required this.ride});

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final dateFormat = DateFormat('MMM dd, yyyy • hh:mm a');

    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: InkWell(
        onTap: () {
          // Show ride details dialog
          showDialog(
            context: context,
            builder: (context) => _RideDetailsDialog(ride: ride),
          );
        },
        child: Padding(
          padding: const EdgeInsets.all(16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Date and status
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    dateFormat.format(ride.createdAt),
                    style: TextStyle(
                      fontSize: 12,
                      color: Colors.grey[600],
                    ),
                  ),
                  _StatusChip(status: ride.status),
                ],
              ),

              const SizedBox(height: 12),

              // Route
              Row(
                children: [
                  Icon(Icons.trip_origin,
                      size: 16, color: config.primaryColor),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      ride.pickupLocation.name,
                      style: const TextStyle(fontWeight: FontWeight.w600),
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 8),

              Row(
                children: [
                  Icon(Icons.location_on, size: 16, color: config.primaryColor),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      ride.destinationLocation.name,
                      style: const TextStyle(fontWeight: FontWeight.w600),
                    ),
                  ),
                ],
              ),

              const Divider(height: 24),

              // Fare and driver
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Rp ${ride.fare.toStringAsFixed(0)}',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  if (ride.driver != null)
                    Row(
                      children: [
                        const Icon(Icons.person, size: 16),
                        const SizedBox(width: 4),
                        Text(
                          ride.driver!.name,
                          style: TextStyle(
                            fontSize: 12,
                            color: Colors.grey[600],
                          ),
                        ),
                      ],
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  final RideStatus status;

  const _StatusChip({required this.status});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    Color color;
    String text;

    switch (status) {
      case RideStatus.completed:
        color = Colors.green;
        text = l10n.filterCompleted;
        break;
      case RideStatus.cancelled:
        color = Colors.red;
        text = l10n.filterCancelled;
        break;
      default:
        color = Colors.grey;
        text = status.name;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color),
      ),
      child: Text(
        text,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w600,
          color: color,
        ),
      ),
    );
  }
}

class _RideDetailsDialog extends StatelessWidget {
  final Ride ride;

  const _RideDetailsDialog({required this.ride});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final dateFormat = DateFormat('MMM dd, yyyy • hh:mm a');

    return AlertDialog(
      title: Text(l10n.rideDetailsTitle),
      content: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            _DetailRow(
              label: l10n.dateLabel,
              value: dateFormat.format(ride.createdAt),
            ),
            _DetailRow(
              label: l10n.fromLabel,
              value: ride.pickupLocation.name,
            ),
            _DetailRow(
              label: l10n.toLabel,
              value: ride.destinationLocation.name,
            ),
            _DetailRow(
              label: l10n.fareLabel,
              value: 'Rp ${ride.fare.toStringAsFixed(0)}',
            ),
            if (ride.driver != null)
              _DetailRow(
                label: l10n.driverLabel,
                value: ride.driver!.name,
              ),
            _DetailRow(
              label: l10n.passengerCountTitle,
              value: '${ride.passengerCount}',
            ),
            if (ride.specialRequests != null)
              _DetailRow(
                label: l10n.specialRequestsTitle,
                value: ride.specialRequests!,
              ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: Text(l10n.close),
        ),
      ],
    );
  }
}

class _DetailRow extends StatelessWidget {
  final String label;
  final String value;

  const _DetailRow({
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8.0),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 120,
            child: Text(
              '$label:',
              style: TextStyle(
                color: Colors.grey[600],
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}
