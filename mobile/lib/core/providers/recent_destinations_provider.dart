import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/place_search_result.dart';
import 'ride_history_provider.dart';

/// Derives unique recent destination locations from completed rides.
final recentDestinationsProvider = Provider<List<PlaceSearchResult>>((ref) {
  final historyState = ref.watch(rideHistoryProvider);
  final seen = <int>{};
  final results = <PlaceSearchResult>[];

  for (final ride in historyState.rides) {
    if (ride.isCompleted) {
      final dest = ride.destinationLocation;
      if (seen.contains(dest.id)) continue;
      seen.add(dest.id);
      results.add(PlaceSearchResult(
        id: dest.id,
        name: dest.name,
        address: dest.description,
        coordinates: dest.coordinates,
        isBeacon: dest.isBeacon,
        source: 'database',
      ));
      if (results.length >= 10) break;
    }
  }

  return results;
});
