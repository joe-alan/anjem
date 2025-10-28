import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/ride.dart';
import '../services/ride/ride_service.dart';
import 'active_ride_provider.dart';

// Ride History State
class RideHistoryState {
  final List<Ride> rides;
  final bool isLoading;
  final String? error;
  final String? selectedFilter; // 'all', 'completed', 'cancelled'

  const RideHistoryState({
    this.rides = const [],
    this.isLoading = false,
    this.error,
    this.selectedFilter = 'all',
  });

  RideHistoryState copyWith({
    List<Ride>? rides,
    bool? isLoading,
    String? error,
    String? selectedFilter,
  }) {
    return RideHistoryState(
      rides: rides ?? this.rides,
      isLoading: isLoading ?? this.isLoading,
      error: error,
      selectedFilter: selectedFilter ?? this.selectedFilter,
    );
  }
}

// Ride History Notifier
class RideHistoryNotifier extends StateNotifier<RideHistoryState> {
  final RideService _service;

  RideHistoryNotifier(this._service) : super(const RideHistoryState()) {
    loadRides();
  }

  Future<void> loadRides({String? status}) async {
    state = state.copyWith(isLoading: true, error: null);

    try {
      final rides = await _service.getUserRides(status: status);
      state = state.copyWith(
        rides: rides,
        isLoading: false,
        error: null,
      );
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.toString(),
      );
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

      // Reload rides to get updated data
      await loadRides();
    } catch (e) {
      state = state.copyWith(error: e.toString());
    }
  }

  void setFilter(String filter) {
    state = state.copyWith(selectedFilter: filter);

    // Reload with filter
    if (filter == 'all') {
      loadRides();
    } else {
      loadRides(status: filter);
    }
  }

  void refresh() {
    if (state.selectedFilter == 'all') {
      loadRides();
    } else {
      loadRides(status: state.selectedFilter);
    }
  }

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
