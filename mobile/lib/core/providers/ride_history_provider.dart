import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/ride.dart';
import '../services/ride/ride_service.dart';
import 'active_ride_provider.dart';

// Ride History State
class RideHistoryState {
  final List<Ride> rides;
  final int unratedCount;
  final bool isLoading;
  final String? error;
  final String selectedFilter; // 'all', 'completed', 'cancelled', 'unrated'

  const RideHistoryState({
    this.rides = const [],
    this.unratedCount = 0,
    this.isLoading = false,
    this.error,
    this.selectedFilter = 'all',
  });

  bool get hasUnrated => unratedCount > 0;

  RideHistoryState copyWith({
    List<Ride>? rides,
    int? unratedCount,
    bool? isLoading,
    String? error,
    String? selectedFilter,
  }) {
    return RideHistoryState(
      rides: rides ?? this.rides,
      unratedCount: unratedCount ?? this.unratedCount,
      isLoading: isLoading ?? this.isLoading,
      error: error,
      selectedFilter: selectedFilter ?? this.selectedFilter,
    );
  }
}

// Ride History Notifier
class RideHistoryNotifier extends StateNotifier<RideHistoryState> {
  final RideService _service;

  /// Cache of ALL rides (unfiltered) — used to derive unratedCount
  List<Ride> _allRides = [];

  RideHistoryNotifier(this._service) : super(const RideHistoryState()) {
    _loadAll();
  }

  /// Always fetches all rides, updates unratedCount, then applies the active filter.
  Future<void> _loadAll() async {
    state = state.copyWith(isLoading: true, error: null);

    try {
      _allRides = await _service.getUserRides();
      final unrated = _allRides.where((r) => r.canRate).length;

      state = state.copyWith(
        rides: _applyFilter(_allRides, state.selectedFilter),
        unratedCount: unrated,
        isLoading: false,
        error: null,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  List<Ride> _applyFilter(List<Ride> rides, String filter) {
    switch (filter) {
      case 'completed':
        return rides.where((r) => r.status == RideStatus.completed).toList();
      case 'cancelled':
        return rides.where((r) => r.status == RideStatus.cancelled).toList();
      case 'unrated':
        return rides.where((r) => r.canRate).toList();
      default:
        return rides;
    }
  }

  Future<void> rateRide({
    required int rideId,
    required int rating,
    List<String>? tags,
    String? feedback,
  }) async {
    try {
      await _service.rateRide(
        rideId: rideId,
        rating: rating,
        tags: tags,
        feedback: feedback,
      );

      await _loadAll();
    } catch (e) {
      state = state.copyWith(error: e.toString());
    }
  }

  void setFilter(String filter) {
    state = state.copyWith(
      selectedFilter: filter,
      rides: _applyFilter(_allRides, filter),
    );
  }

  void refresh() => _loadAll();

  void clearError() {
    state = state.copyWith(error: null);
  }
}

// Ride History Provider
final rideHistoryProvider =
    StateNotifierProvider<RideHistoryNotifier, RideHistoryState>((ref) {
  final service = ref.watch(rideServiceProvider);
  return RideHistoryNotifier(service);
});
