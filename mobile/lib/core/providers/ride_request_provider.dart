import 'dart:async';

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
  /// ISO8601 timestamp until which the rider cannot create a new request.
  final String? cooldownUntil;
  /// Set when server broadcasts no-drivers-available; rider sees countdown until this time.
  final DateTime? noDriversAvailableUntil;

  const RideRequestState({
    this.request,
    this.fareEstimate,
    this.matchedRide,
    this.isLoading = false,
    this.error,
    this.successMessage,
    this.cooldownUntil,
    this.noDriversAvailableUntil,
  });

  RideRequestState copyWith({
    RideRequest? request,
    FareEstimate? fareEstimate,
    Ride? matchedRide,
    bool? isLoading,
    String? error,
    String? successMessage,
    String? cooldownUntil,
    DateTime? noDriversAvailableUntil,
  }) {
    return RideRequestState(
      request: request ?? this.request,
      fareEstimate: fareEstimate ?? this.fareEstimate,
      matchedRide: matchedRide ?? this.matchedRide,
      isLoading: isLoading ?? this.isLoading,
      error: error,
      successMessage: successMessage,
      cooldownUntil: cooldownUntil ?? this.cooldownUntil,
      noDriversAvailableUntil: noDriversAvailableUntil ?? this.noDriversAvailableUntil,
    );
  }

  bool get isPending => request != null && matchedRide == null;
  bool get isMatched => matchedRide != null;

  /// Returns true if the rider is in a cooldown period and cannot request again.
  bool get isInCooldown {
    if (cooldownUntil == null) return false;
    try {
      return DateTime.parse(cooldownUntil!).isAfter(DateTime.now());
    } catch (_) {
      return false;
    }
  }

  /// Seconds remaining in cooldown, or 0 if not in cooldown.
  int get cooldownSecondsRemaining {
    if (!isInCooldown) return 0;
    try {
      final until = DateTime.parse(cooldownUntil!);
      return until.difference(DateTime.now()).inSeconds.clamp(0, 120);
    } catch (_) {
      return 0;
    }
  }
}

// Ride Request Notifier
class RideRequestNotifier extends StateNotifier<RideRequestState> {
  final RideRequestService _service;
  final WebSocketService _wsService;
  final dynamic _apiService;
  int? _activeUserId;
  Timer? _matchPollingTimer;
  int _pollAttempts = 0;
  static const _maxPollingAttempts = 10;
  static const _pollInterval = Duration(seconds: 3);

  RideRequestNotifier(
    this._service,
    this._wsService,
    this._apiService,
  ) : super(const RideRequestState());

  @override
  void dispose() {
    _stopMatchPolling();
    super.dispose();
  }

  Future<void> handleUserChanged({
    int? previousUserId,
    int? nextUserId,
  }) async {
    if (previousUserId != null && previousUserId != nextUserId) {
      await _wsService.unsubscribeFromChannel('user.$previousUserId');
    }

    if (nextUserId == null) {
      _activeUserId = null;
      _stopMatchPolling();
      state = const RideRequestState();
      return;
    }

    if (_activeUserId == nextUserId) {
      if (state.isPending) {
        await _subscribeToMatching();
      }
      return;
    }

    _activeUserId = nextUserId;
    await _checkPendingRequest();

    if (state.isPending) {
      await _subscribeToMatching();
      _startMatchPolling();
    }
  }

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

      // ✅ Reset state for new request (clear any previous matched ride)
      state = RideRequestState(
        request: request,
        fareEstimate: state.fareEstimate, // Keep fare estimate
        matchedRide: null, // Clear previous match
        isLoading: false,
        successMessage: 'Ride request created successfully',
        error: null,
      );

      // Subscribe to WebSocket for ride matching
      print('RideRequestProvider: Request created, userId=$_activeUserId');
      if (_activeUserId != null) {
        print('RideRequestProvider: Calling _subscribeToMatching()');
        await _subscribeToMatching();
        _startMatchPolling();
      } else {
        print(
            'RideRequestProvider: ⚠️ userId is NULL, cannot subscribe to WebSocket!');
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
      final apiService = _apiService;
      final requestId = state.request!.id;
      final response =
          await apiService.patch('/requests/$requestId/cancel');

      String? cooldownUntil;
      if (response.data['meta'] != null) {
        cooldownUntil =
            response.data['meta']['cooldown_until'] as String?;
      }

      state = RideRequestState(
        successMessage: 'Ride request cancelled',
        cooldownUntil: cooldownUntil,
      );
      _stopMatchPolling();
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.toString(),
      );
    }
  }

  Future<void> _subscribeToMatching() async {
    final userId = _activeUserId;

    print('RideRequestProvider._subscribeToMatching: START for userId=$userId');

    if (userId == null) {
      print(
          'RideRequestProvider._subscribeToMatching: userId is null, skipping subscription');
      return;
    }

    await _wsService.subscribeToUserChannel(
      userId: userId,
      onRideMatched: (eventData) {
        print('🎉 RIDE MATCHED CALLBACK TRIGGERED!');
        print('Event data received: $eventData');

        try {
          // Transform WebSocket event data to Ride model format
          // The event sends: ride_request_id, estimated_fare_rp, rider_name, driver_name
          // But Ride model expects: request_id, fare, rider/driver objects, created_at, updated_at

          Map<String, dynamic> normalizeLocation(Map<String, dynamic>? raw) {
            if (raw == null) {
              throw FormatException(
                  'Location data missing in ride match event');
            }

            final nowIso = DateTime.now().toIso8601String();

            return {
              'id': raw['id'],
              'name': raw['name'],
              'description': raw['description'],
              'coordinates': raw['coordinates'],
              'type': raw['type'] ?? 'beacon',
              'is_active': raw['is_active'] ?? true,
              'queue_count': raw['queue_count'],
              'created_at':
                  raw['created_at'] ?? eventData['matched_at'] ?? nowIso,
              'updated_at':
                  raw['updated_at'] ?? eventData['timestamp'] ?? nowIso,
            };
          }

          Map<String, dynamic> normalizeUser({
            required int id,
            required String name,
            required String role,
          }) {
            final nowIso = DateTime.now().toIso8601String();

            return {
              'id': id,
              'firebase_uid': 'ws_${role}_$id',
              'name': name,
              'email': '${role}_$id@placeholder.anjem',
              'phone': null,
              'profile_picture': null,
              'user_type': role,
              'created_at': eventData['matched_at'] ?? nowIso,
              'updated_at': eventData['timestamp'] ?? nowIso,
            };
          }

          final rideData = {
            'id': eventData['ride_id'],
            'ride_request_id':
                eventData['ride_request_id'], // Match backend field name
            'rider_id': eventData['rider_id'],
            'driver_id': eventData['driver_id'],
            'pickup_location': normalizeLocation(
              eventData['pickup_location'] as Map<String, dynamic>?,
            ),
            'destination_location': normalizeLocation(
              eventData['destination_location'] as Map<String, dynamic>?,
            ),
            'status': eventData['status'],
            'estimated_fare_rp': eventData['estimated_fare_rp'], // ✅ Keep original key name to match Ride.fromJson
            'passenger_count': eventData['passenger_count'],
            'special_requests': null,
            'rider': normalizeUser(
              id: eventData['rider_id'] as int,
              name: eventData['rider_name'] as String,
              role: 'rider',
            ),
            'driver': normalizeUser(
              id: eventData['driver_id'] as int,
              name: eventData['driver_name'] as String,
              role: 'driver',
            ),
            'driver_accepted_at': eventData['matched_at'], // ✅ Match Ride.fromJson expectation
            'arrived_at': null,
            'started_at': null,
            'completed_at': null,
            'cancelled_at': null,
            'cancellation_reason': null,
            'created_at':
                eventData['matched_at'] ?? DateTime.now().toIso8601String(),
            'updated_at':
                eventData['timestamp'] ?? DateTime.now().toIso8601String(),
          };

          final ride = Ride.fromJson(rideData);
          state = state.copyWith(
            matchedRide: ride,
            successMessage: 'Driver matched! Preparing your ride...',
          );
          _stopMatchPolling();

          print('✅ State updated with matched ride: ${ride.id}');
        } catch (e, stackTrace) {
          print('❌ ERROR parsing ride match event: $e');
          print('Stack trace: $stackTrace');
          print('Event data was: $eventData');

          // ✅ Don't update state on parse error - keeps user in waiting screen
          // Navigation only happens when matchedRide is not null
          state = state.copyWith(
            error: 'Failed to process ride match: $e',
          );
        }
      },
      onNoDriversAvailable: (eventData) {
        final countdown = (eventData['countdown_seconds'] as num?)?.toInt() ?? 60;
        state = state.copyWith(
          noDriversAvailableUntil: DateTime.now().add(Duration(seconds: countdown)),
        );
      },
      onRequestExpired: (eventData) {
        // Server expired the request after no-drivers countdown — clear it
        // and surface the cooldown so rider cannot immediately re-request.
        _stopMatchPolling();
        state = RideRequestState(
          cooldownUntil: eventData['rider_cooldown_until'] as String?,
        );
      },
    );

    print(
        'RideRequestProvider._subscribeToMatching: subscribeToUserChannel called');
  }

  void clearError() {
    state = state.copyWith(error: null);
  }

  void clearSuccessMessage() {
    state = state.copyWith(successMessage: null);
  }

  void reset() {
    _stopMatchPolling();
    state = const RideRequestState();
  }

  /// Set request from session resume (called by SessionCheckWrapper)
  /// This syncs the provider state with backend session and subscribes to WebSocket
  Future<void> setRequestFromSession(RideRequest request) async {
    print('RideRequestProvider: Setting request from session: ${request.id}');

    // Update state with the request
    state = RideRequestState(
      request: request,
      matchedRide: null,
      isLoading: false,
    );

    // Subscribe to WebSocket for ride matching
    if (_activeUserId != null) {
      print('RideRequestProvider: Subscribing to WebSocket for session-restored request');
      await _subscribeToMatching();
      _startMatchPolling();
    } else {
      print('RideRequestProvider: ⚠️ userId is NULL, cannot subscribe to WebSocket!');
    }
  }

  /// Check for existing pending requests and subscribe to them
  Future<void> _checkPendingRequest() async {
    if (_activeUserId == null) {
      print(
          'RideRequestProvider: User ID unavailable, deferring pending request check');
      return;
    }

    try {
      print(
          'RideRequestProvider: Checking for existing pending/matched requests...');

      // First check for pending requests
      final pendingRequests = await _service.getUserRequests(status: 'pending');

      if (pendingRequests.isNotEmpty) {
        // Load the first pending request
        final request = pendingRequests.first;
        print('RideRequestProvider: Found pending request #${request.id}');
        state = state.copyWith(request: request);

        // Subscribe to WebSocket for this request
        print(
            'RideRequestProvider: Subscribing to WebSocket for pending request');
        await _subscribeToMatching();
        _startMatchPolling();
        return;
      }

      // If no pending requests, check for matched requests
      final matchedRequests = await _service.getUserRequests(status: 'matched');

      if (matchedRequests.isNotEmpty) {
        final request = matchedRequests.first;
        print(
            'RideRequestProvider: Found matched request #${request.id}, fetching ride details...');

        // Fetch the full ride details via API
        try {
          final response = await _apiService.get('/rides?status=accepted');

          if (response.data['success'] == true) {
            final ridesData = response.data['data'] as List;
            if (ridesData.isNotEmpty) {
              final ride =
                  Ride.fromJson(ridesData.first as Map<String, dynamic>);
              state = state.copyWith(
                request: request,
                matchedRide: ride,
                successMessage: 'Driver matched! Resume your ride.',
              );
              print(
                  'RideRequestProvider: Loaded existing matched ride #${ride.id}');
              _stopMatchPolling();
            }
          }
        } catch (e) {
          print('RideRequestProvider: Error fetching ride details - $e');
        }
      } else {
        print('RideRequestProvider: No pending or matched requests found');
        _stopMatchPolling();
      }
    } catch (e) {
      print('RideRequestProvider: Error checking pending requests - $e');
      // Don't show error to user for background check
    }
  }

  void _startMatchPolling() {
    if (!state.isPending) {
      return;
    }

    _matchPollingTimer?.cancel();
    _pollAttempts = 0;
    _matchPollingTimer = Timer.periodic(_pollInterval, (_) async {
      _pollAttempts += 1;
      final foundMatch = await _fetchMatchViaApi();
      if (foundMatch ||
          !state.isPending ||
          _pollAttempts >= _maxPollingAttempts) {
        _stopMatchPolling();
      }
    });
  }

  void _stopMatchPolling() {
    _matchPollingTimer?.cancel();
    _matchPollingTimer = null;
    _pollAttempts = 0;
  }

  Future<bool> _fetchMatchViaApi() async {
    try {
      RideRequest? latestRequest;

      if (state.request != null) {
        latestRequest = await _service.getRequest(state.request!.id);
      } else {
        final matched = await _service.getUserRequests(status: 'matched');
        if (matched.isNotEmpty) {
          latestRequest = matched.first;
        }
      }

      if (latestRequest == null) {
        return false;
      }

      // Update the local request reference
      state = state.copyWith(request: latestRequest);

      if (latestRequest.isCancelled ||
          latestRequest.isExpired ||
          latestRequest.isCompleted) {
        // Request is terminal — clear state so the rider can request again
        state = RideRequestState(
          successMessage: latestRequest.isExpired
              ? 'No drivers available. Try again in a moment.'
              : 'Ride finished.',
        );
        _stopMatchPolling();
        return true;
      }

      final ride = await _loadRideForStatuses(latestRequest.id, const [
        'driver_arrived',
        'accepted',
        'in_progress',
      ]);

      if (ride != null) {
        state = state.copyWith(
          matchedRide: ride,
          successMessage: 'Driver matched! Resuming your ride...',
        );
        return true;
      }
    } catch (e) {
      print('RideRequestProvider: Match polling error - $e');
    }

    return false;
  }

  Future<Ride?> _loadRideForStatuses(
      int requestId, List<String> statuses) async {
    for (final status in statuses) {
      try {
        final response = await _apiService.get(
          '/rides',
          queryParameters: {
            'status': status,
            'per_page': 1,
          },
        );

        if (response.data['success'] == true) {
          final ridesData = _extractRideList(response.data['data']);

          if (ridesData.isEmpty) {
            continue;
          }

          final rideJson = ridesData.first as Map<String, dynamic>;
          if (rideJson['ride_request_id'] == requestId) {
            return Ride.fromJson(rideJson);
          }
        }
      } catch (e) {
        print(
            'RideRequestProvider: Error loading ride for status $status - $e');
      }
    }

    return null;
  }

  List<dynamic> _extractRideList(dynamic data) {
    if (data is List) {
      return data;
    }

    if (data is Map<String, dynamic>) {
      final innerData = data['data'];
      if (innerData is List) {
        return innerData;
      }
    }

    return [];
  }
}

// Ride Request Provider
final rideRequestProvider =
    StateNotifierProvider<RideRequestNotifier, RideRequestState>((ref) {
  final service = ref.watch(rideRequestServiceProvider);
  final wsService = ref.watch(websocketServiceProvider);
  final apiService = ref.watch(apiServiceProvider);
  final notifier = RideRequestNotifier(service, wsService, apiService);

  ref.listen(currentUserProvider, (previous, next) {
    notifier.handleUserChanged(
      previousUserId: previous?.id,
      nextUserId: next?.id,
    );
  });

  notifier.handleUserChanged(
    nextUserId: ref.read(currentUserProvider)?.id,
  );

  return notifier;
});

// Note: websocketServiceProvider is defined in auth_provider.dart
