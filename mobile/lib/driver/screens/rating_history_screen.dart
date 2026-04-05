import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:mobile/l10n/app_localizations.dart';
import '../../core/config/app_config.dart';
import '../../core/models/ride.dart';
import '../../core/providers/driver_statistics_provider.dart';
import '../../core/providers/ride_history_provider.dart';

class RatingHistoryScreen extends ConsumerWidget {
  const RatingHistoryScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final config = AppConfig.instance;
    final l10n = AppLocalizations.of(context);
    final statsAsync = ref.watch(driverStatisticsProvider);
    final historyState = ref.watch(rideHistoryProvider);

    final ratedRides = historyState.rides
        .where((r) =>
            r.status == RideStatus.completed && r.riderRating != null)
        .toList();

    // Star distribution
    final starCounts = <int, int>{5: 0, 4: 0, 3: 0, 2: 0, 1: 0};
    for (final ride in ratedRides) {
      final s = ride.riderRating!;
      if (s >= 1 && s <= 5) starCounts[s] = starCounts[s]! + 1;
    }
    final maxCount =
        starCounts.values.fold(0, (a, b) => a > b ? a : b).clamp(1, 9999);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.ratingHistoryTitle),
        backgroundColor: config.primaryColor,
        foregroundColor: Colors.white,
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(driverStatisticsProvider);
          ref.read(rideHistoryProvider.notifier).refresh();
        },
        child: ListView(
          children: [
            // Hero rating card with star distribution
            statsAsync.when(
              data: (stats) => Card(
                margin: const EdgeInsets.all(16),
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Row(
                    children: [
                      // Left: big rating number
                      Column(
                        children: [
                          Text(
                            stats.rating.toStringAsFixed(1),
                            style: TextStyle(
                              fontSize: 48,
                              fontWeight: FontWeight.bold,
                              color: config.primaryColor,
                            ),
                          ),
                          Row(
                            children: List.generate(5, (i) => Icon(
                              i < stats.rating.round()
                                  ? Icons.star
                                  : Icons.star_border,
                              size: 16,
                              color: Colors.amber,
                            )),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            l10n.ratingsReceived(ratedRides.length),
                            style: TextStyle(
                                fontSize: 12, color: Colors.grey[600]),
                          ),
                        ],
                      ),
                      const SizedBox(width: 24),
                      // Right: star distribution bars
                      Expanded(
                        child: Column(
                          children: [5, 4, 3, 2, 1].map((star) {
                            final count = starCounts[star]!;
                            return Padding(
                              padding:
                                  const EdgeInsets.symmetric(vertical: 2),
                              child: Row(
                                children: [
                                  Text('$star',
                                      style: const TextStyle(fontSize: 12)),
                                  const SizedBox(width: 4),
                                  const Icon(Icons.star,
                                      size: 12, color: Colors.amber),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: ClipRRect(
                                      borderRadius:
                                          BorderRadius.circular(4),
                                      child: LinearProgressIndicator(
                                        value: count / maxCount,
                                        backgroundColor: Colors.grey[200],
                                        color: config.primaryColor,
                                        minHeight: 8,
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  SizedBox(
                                    width: 24,
                                    child: Text('$count',
                                        style: TextStyle(
                                            fontSize: 12,
                                            color: Colors.grey[600])),
                                  ),
                                ],
                              ),
                            );
                          }).toList(),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              loading: () => const Padding(
                padding: EdgeInsets.all(32),
                child: Center(child: CircularProgressIndicator()),
              ),
              error: (_, __) => const SizedBox.shrink(),
            ),

            // Rated rides list
            if (historyState.isLoading)
              const Padding(
                padding: EdgeInsets.all(32),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (ratedRides.isEmpty)
              Padding(
                padding: const EdgeInsets.all(48),
                child: Center(
                  child: Column(
                    children: [
                      Icon(Icons.star_border,
                          size: 64, color: Colors.grey[400]),
                      const SizedBox(height: 16),
                      Text(l10n.noRatingsYet,
                          style: TextStyle(color: Colors.grey[600])),
                    ],
                  ),
                ),
              )
            else
              ...ratedRides.map((ride) => _RatingItem(ride: ride)),
          ],
        ),
      ),
    );
  }
}

class _RatingItem extends StatelessWidget {
  final Ride ride;

  const _RatingItem({required this.ride});

  @override
  Widget build(BuildContext context) {
    final dateFormat = DateFormat('MMM dd, yyyy');
    final date = ride.completedAt ?? ride.createdAt;
    final score = ride.riderRating ?? 0;
    final feedback = ride.riderFeedback;
    final riderName = ride.rider?.name ?? '';
    final riderAvatar = ride.rider?.avatarUrl;

    return ListTile(
      leading: CircleAvatar(
        backgroundImage:
            riderAvatar != null ? NetworkImage(riderAvatar) : null,
        child: riderAvatar == null
            ? Text(riderName.isNotEmpty ? riderName[0].toUpperCase() : '?')
            : null,
      ),
      title: Row(
        children: [
          ...List.generate(5, (i) => Icon(
            i < score ? Icons.star : Icons.star_border,
            size: 16,
            color: Colors.amber,
          )),
          const SizedBox(width: 8),
          Text(dateFormat.format(date),
              style: TextStyle(fontSize: 12, color: Colors.grey[600])),
        ],
      ),
      subtitle: feedback != null && feedback.isNotEmpty
          ? Text(feedback, maxLines: 1, overflow: TextOverflow.ellipsis)
          : Text(riderName,
              style: TextStyle(fontSize: 13, color: Colors.grey[500])),
      onTap: feedback != null && feedback.isNotEmpty
          ? () => _showFeedback(context, riderName, feedback, score)
          : null,
    );
  }

  void _showFeedback(
      BuildContext context, String name, String feedback, int score) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Row(
          children: [
            ...List.generate(5, (i) => Icon(
              i < score ? Icons.star : Icons.star_border,
              size: 20,
              color: Colors.amber,
            )),
            if (name.isNotEmpty) ...[
              const SizedBox(width: 8),
              Flexible(
                child: Text(name,
                    style: const TextStyle(fontSize: 14),
                    overflow: TextOverflow.ellipsis),
              ),
            ],
          ],
        ),
        content: Text(feedback),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: Text(AppLocalizations.of(context).close),
          ),
        ],
      ),
    );
  }
}
