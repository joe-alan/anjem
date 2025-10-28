import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/lat_lng.dart';
import '../models/ride.dart';
import '../services/ride/ride_service.dart';
import '../services/websocket/websocket_service.dart';
import 'api_provider.dart';
import 'ride_request_provider.dart';

// Ride Service Provider
final rideServiceProvider = Provider<RideService>((ref) {
  final apiService = ref.watch(apiServiceProvider);
  return RideService(apiService: apiService);
});

// Active Ride State
class ActiveRideState {
  final Ride? ride;
  final LatLng? driverLocation;
  final double? estimatedArrivalMinutes;
  final bool isLoading;
  final String? error;

  const ActiveRideState({
    this.ride,
    this.driverLocation,
    this.estimatedArrivalMinutes,
    this.isLoading = false,
    this.error,
  });

  ActiveRideState copyWith({
    Ride? ride,
    LatLng? driverLocation,
    double? estimatedArrivalMinutes,
    bool? isLoading,
    String? error,
  }) {
    return ActiveRideState(
      ride: ride ?? this.ride,
      driverLocation: driverLocation ?? this.driverLocation,
      estimatedArrivalMinutes:
          estimatedArrivalMinutes ?? this.estimatedArrivalMinutes,
      isLoading: isLoading ?? this.isLoading,
      error: error,
    );
  }

  bool get isActive => ride != null && ride!.status != RideStatus.completed;
}

// Active Ride Notifier
class ActiveRideNotifier extends StateNotifier<ActiveRideState> {
  final RideService _service;
  final WebSocketService _wsService;

  ActiveRideNotifier(this._service, this._wsService)
      : super(const ActiveRideState());

  Future<void> loadRide(int rideId) async {
    state = state.copyWith(isLoading: true, error: null);

    try {
      final ride = await _service.getRide(rideId);
      state = state.copyWith(
        ride: ride,
        isLoading: false,
        error: null,
      );

      // Subscribe to real-time updates
      _subscribeToRideUpdates(rideId);
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.toString(),
      );
    }
  }

  void setRide(Ride ride) {
    state = state.copyWith(ride: ride, error: null);
    _subscribeToRideUpdates(ride.id);
  }

  void _subscribeToRideUpdates(int rideId) {
    _wsService.subscribeToRideChannel(
      rideId: rideId,
      onStatusUpdate: (data) {
        if (state.ride != null) {
          final statusString = data['status'] as String;
          final status = _parseRideStatus(statusString);
          if (status != null) {
            final updatedRide = state.ride!.copyWith(status: status);
            state = state.copyWith(ride: updatedRide);
          }
        }
      },
      onLocationUpdate: (latitude, longitude) {
        state = state.copyWith(
          driverLocation: LatLng(latitude, longitude),
        );
      },
    );
  }

  RideStatus? _parseRideStatus(String status) {
    switch (status.toLowerCase()) {
      case 'accepted':
        return RideStatus.accepted;
      case 'driver_arrived':
      case 'driverarrived':
        return RideStatus.driverArrived;
      case 'in_progress':
      case 'inprogress':
        return RideStatus.inProgress;
      case 'completed':
        return RideStatus.completed;
      case 'cancelled':
        return RideStatus.cancelled;
      default:
        return null;
    }
  }

  void updateDriverLocation(LatLng location) {
    state = state.copyWith(driverLocation: location);
  }

  void updateEstimatedArrival(double minutes) {
    state = state.copyWith(estimatedArrivalMinutes: minutes);
  }

  void clearError() {
    state = state.copyWith(error: null);
  }

  void reset() {
    state = const ActiveRideState();
  }
}

// Active Ride Provider
final activeRideProvider =
    StateNotifierProvider<ActiveRideNotifier, ActiveRideState>((ref) {
  final service = ref.watch(rideServiceProvider);
  final wsService = ref.watch(websocketServiceProvider);

  return ActiveRideNotifier(service, wsService);
});
