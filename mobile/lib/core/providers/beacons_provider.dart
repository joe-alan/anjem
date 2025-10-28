import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/location.dart';
import '../services/beacon/beacon_service.dart';
import 'api_provider.dart';

// Beacon Service Provider
final beaconServiceProvider = Provider<BeaconService>((ref) {
  final apiService = ref.watch(apiServiceProvider);
  return BeaconService(apiService: apiService);
});

// Beacons State
class BeaconsState {
  final List<Location> beacons;
  final bool isLoading;
  final String? error;

  const BeaconsState({
    this.beacons = const [],
    this.isLoading = false,
    this.error,
  });

  BeaconsState copyWith({
    List<Location>? beacons,
    bool? isLoading,
    String? error,
  }) {
    return BeaconsState(
      beacons: beacons ?? this.beacons,
      isLoading: isLoading ?? this.isLoading,
      error: error,
    );
  }
}

// Beacons Notifier
class BeaconsNotifier extends StateNotifier<BeaconsState> {
  final BeaconService _beaconService;

  BeaconsNotifier(this._beaconService) : super(const BeaconsState()) {
    loadBeacons();
  }

  Future<void> loadBeacons() async {
    if (state.isLoading) return; // Prevent duplicate loads

    state = state.copyWith(isLoading: true, error: null);

    try {
      final beacons = await _beaconService.getBeacons();
      state = state.copyWith(
        beacons: beacons,
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

  Future<void> getNearbyBeacons({
    required double latitude,
    required double longitude,
    double radiusKm = 5.0,
  }) async {
    state = state.copyWith(isLoading: true, error: null);

    try {
      final beacons = await _beaconService.getNearbyBeacons(
        latitude: latitude,
        longitude: longitude,
        radiusKm: radiusKm,
      );
      state = state.copyWith(
        beacons: beacons,
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

  void refresh() {
    loadBeacons();
  }
}

// Beacons Provider
final beaconsProvider =
    StateNotifierProvider<BeaconsNotifier, BeaconsState>((ref) {
  final beaconService = ref.watch(beaconServiceProvider);
  return BeaconsNotifier(beaconService);
});
