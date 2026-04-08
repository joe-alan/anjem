import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/lat_lng.dart';
import '../models/ride.dart';
import '../services/ride/ride_service.dart';
import '../services/websocket/websocket_service.dart';
import 'api_provider.dart';
import 'auth_provider.dart'; // For websocketServiceProvider

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
  int? _currentRideId;  // ✅ Track current ride ID to unsubscribe from old channel

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
    // ✅ Reset ALL state fields for new ride (don't carry over old driver location, etc.)
    state = ActiveRideState(ride: ride);
    _subscribeToRideUpdates(ride.id);
  }

  void _subscribeToRideUpdates(int rideId) {
    // ✅ Unsubscribe from previous ride channel if it exists
    if (_currentRideId != null && _currentRideId != rideId) {
      debugPrint('Unsubscribing from previous ride channel: private-ride.$_currentRideId');
      _wsService.unsubscribeFromChannel('private-ride.$_currentRideId');
    }

    _currentRideId = rideId;
    debugPrint('Subscribing to new ride channel: private-ride.$rideId');

    _wsService.subscribeToRideChannel(
      rideId: rideId,
      onStatusUpdate: (data) {
        debugPrint('📡 [Provider] Received status update for ride $rideId: ${data['status']}');

        // Check for admin override
        final isAdminOverride = data['admin_override'] == true;
        final adminReason = data['admin_reason'] as String?;

        if (isAdminOverride && adminReason != null) {
          debugPrint('    🛡️ Admin override detected: $adminReason');
        }

        debugPrint('    Current state ride ID: ${state.ride?.id}');
        if (state.ride != null) {
          final statusString = data['status'] as String;
          final status = _parseRideStatus(statusString);
          if (status != null) {
            debugPrint('    Updating ride ${state.ride!.id} status to: $status');
            final updatedRide = state.ride!.copyWith(
              status: status,
              adminOverride: isAdminOverride,
              adminReason: adminReason,
            );
            state = state.copyWith(ride: updatedRide);
            debugPrint('    ✅ State updated, new status: ${state.ride?.status}');
          } else {
            debugPrint('    ⚠️ Failed to parse status: $statusString');
          }
        } else {
          debugPrint('    ⚠️ State.ride is null, cannot update status');
        }
      },
      onLocationUpdate: (latitude, longitude) {
        debugPrint('📍 [Provider] Received location update for ride $rideId: ($latitude, $longitude)');
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
    // ✅ Unsubscribe from current ride channel when resetting
    if (_currentRideId != null) {
      debugPrint('Unsubscribing from ride channel on reset: private-ride.$_currentRideId');
      _wsService.unsubscribeFromChannel('private-ride.$_currentRideId');
      _currentRideId = null;
    }
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
