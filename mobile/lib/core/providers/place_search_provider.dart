import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/lat_lng.dart';
import '../models/place_search_result.dart';
import '../services/place/place_search_service.dart';
import 'api_provider.dart';

/// Provider for PlaceSearchService
final placeSearchServiceProvider = Provider<PlaceSearchService>((ref) {
  final apiService = ref.watch(apiServiceProvider);
  return PlaceSearchService(apiService: apiService);
});

/// State for place search
class PlaceSearchState {
  final List<PlaceSearchResult> results;
  final bool isLoading;
  final String? error;
  final String? lastQuery;

  const PlaceSearchState({
    this.results = const [],
    this.isLoading = false,
    this.error,
    this.lastQuery,
  });

  PlaceSearchState copyWith({
    List<PlaceSearchResult>? results,
    bool? isLoading,
    String? error,
    String? lastQuery,
  }) {
    return PlaceSearchState(
      results: results ?? this.results,
      isLoading: isLoading ?? this.isLoading,
      error: error,
      lastQuery: lastQuery ?? this.lastQuery,
    );
  }

  bool get hasResults => results.isNotEmpty;
  bool get hasError => error != null;
}

/// Notifier for place search state
class PlaceSearchNotifier extends StateNotifier<PlaceSearchState> {
  final PlaceSearchService _service;

  PlaceSearchNotifier(this._service) : super(const PlaceSearchState());

  /// Search for places with query
  Future<void> search({
    required String query,
    LatLng? userLocation,
    double? radius,
    int? limit,
  }) async {
    // Don't search if query is too short
    if (query.trim().length < 2) {
      state = const PlaceSearchState(results: []);
      return;
    }

    state = state.copyWith(
      isLoading: true,
      error: null,
      lastQuery: query,
    );

    try {
      final results = await _service.search(
        query: query.trim(),
        userLocation: userLocation,
        radius: radius ?? 5.0,
        limit: limit ?? 10,
      );

      state = state.copyWith(
        results: results,
        isLoading: false,
        error: null,
      );
    } catch (e) {
      state = state.copyWith(
        results: [],
        isLoading: false,
        error: e.toString(),
      );
    }
  }

  /// Search for nearby beacons
  Future<void> searchNearbyBeacons({
    required LatLng userLocation,
    double radius = 5.0,
    int limit = 10,
  }) async {
    state = state.copyWith(
      isLoading: true,
      error: null,
      lastQuery: 'Nearby beacons',
    );

    try {
      final results = await _service.searchNearbyBeacons(
        userLocation: userLocation,
        radius: radius,
        limit: limit,
      );

      state = state.copyWith(
        results: results,
        isLoading: false,
        error: null,
      );
    } catch (e) {
      state = state.copyWith(
        results: [],
        isLoading: false,
        error: e.toString(),
      );
    }
  }

  /// Clear search results
  void clear() {
    state = const PlaceSearchState();
  }

  /// Clear error
  void clearError() {
    state = state.copyWith(error: null);
  }
}

/// Provider for place search state
final placeSearchProvider =
    StateNotifierProvider<PlaceSearchNotifier, PlaceSearchState>((ref) {
  final service = ref.watch(placeSearchServiceProvider);
  return PlaceSearchNotifier(service);
});
