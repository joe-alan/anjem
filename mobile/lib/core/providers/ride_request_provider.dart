import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/ride_request.dart';
import '../models/ride.dart';
import '../models/fare_estimate.dart';
import '../services/ride/ride_request_service.dart';
import '../services/websocket/websocket_service.dart';
import 'api_provider.dart';
import 'auth_provider.dart';

// Ride Request Service Provider
final rideRequestServiceProvider = Provider<RideRequestService>((ref) {
  final apiService = ref.watch(apiServiceProvider);
  return RideRequestService(apiService: apiService);
});

// Ride Request State
class RideRequestState {
  final RideRequest? request;
  final FareEstimate? fareEstimate;
  final Ride? matchedRide;
  final bool isLoading;
  final String? error;
  final String? successMessage;

  const RideRequestState({
    this.request,
    this.fareEstimate,
    this.matchedRide,
    this.isLoading = false,
    this.error,
    this.successMessage,
  });

  RideRequestState copyWith({
    RideRequest? request,
    FareEstimate? fareEstimate,
    Ride? matchedRide,
    bool? isLoading,
    String? error,
    String? successMessage,
  }) {
    return RideRequestState(
      request: request ?? this.request,
      fareEstimate: fareEstimate ?? this.fareEstimate,
      matchedRide: matchedRide ?? this.matchedRide,
      isLoading: isLoading ?? this.isLoading,
      error: error,
      successMessage: successMessage,
    );
  }

  bool get isPending => request != null && matchedRide == null;
  bool get isMatched => matchedRide != null;
}

// Ride Request Notifier
class RideRequestNotifier extends StateNotifier<RideRequestState> {
  final RideRequestService _service;
  final WebSocketService _wsService;
  final int? _userId;

  RideRequestNotifier(
    this._service,
    this._wsService,
    this._userId,
  ) : super(const RideRequestState());

  Future<void> getEstimate({
    required int pickupBeaconId,
    required int destinationBeaconId,
    int passengerCount = 1,
  }) async {
    state = state.copyWith(isLoading: true, error: null);

    try {
      final estimate = await _service.getEstimate(
        pickupBeaconId: pickupBeaconId,
        destinationBeaconId: destinationBeaconId,
        passengerCount: passengerCount,
      );

      state = state.copyWith(
        fareEstimate: estimate,
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

  Future<void> createRequest({
    required int pickupBeaconId,
    required int destinationBeaconId,
    required int passengerCount,
    String? specialRequests,
  }) async {
    state = state.copyWith(isLoading: true, error: null);

    try {
      final request = await _service.createRequest(
        pickupBeaconId: pickupBeaconId,
        destinationBeaconId: destinationBeaconId,
        passengerCount: passengerCount,
        specialRequests: specialRequests,
      );

      state = state.copyWith(
        request: request,
        isLoading: false,
        successMessage: 'Ride request created successfully',
        error: null,
      );

      // Subscribe to WebSocket for ride matching
      if (_userId != null) {
        _subscribeToMatching();
      }
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.toString(),
      );
    }
  }

  Future<void> cancelRequest() async {
    if (state.request == null) return;

    state = state.copyWith(isLoading: true, error: null);

    try {
      await _service.cancelRequest(state.request!.id);

      state = const RideRequestState(
        successMessage: 'Ride request cancelled',
      );
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.toString(),
      );
    }
  }

  void _subscribeToMatching() {
    _wsService.subscribeToUserChannel(
      userId: _userId!,
      onRideMatched: (rideData) {
        final ride = Ride.fromJson(rideData);
        state = state.copyWith(
          matchedRide: ride,
          successMessage: 'Driver matched! Preparing your ride...',
        );
      },
    );
  }

  void clearError() {
    state = state.copyWith(error: null);
  }

  void clearSuccessMessage() {
    state = state.copyWith(successMessage: null);
  }

  void reset() {
    state = const RideRequestState();
  }
}

// Ride Request Provider
final rideRequestProvider =
    StateNotifierProvider<RideRequestNotifier, RideRequestState>((ref) {
  final service = ref.watch(rideRequestServiceProvider);
  final wsService = ref.watch(websocketServiceProvider);
  final user = ref.watch(currentUserProvider);

  return RideRequestNotifier(service, wsService, user?.id);
});

// WebSocket Service Provider
final websocketServiceProvider = Provider<WebSocketService>((ref) {
  final apiService = ref.watch(apiServiceProvider);
  return WebSocketService(apiService: apiService);
});
