import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import '../models/ride_request.dart';
import '../services/api/api_service.dart';
import '../services/websocket/websocket_service.dart';
import 'api_provider.dart';
import 'auth_provider.dart'; // also used for authStateProvider (session.replaced)
import 'credits_provider.dart';
import 'driver_incoming_request_provider.dart';
import 'kyc_provider.dart';

// Driver Status State
enum DriverStatusEnum {
  offline,
  online,
  inActiveRide,
}

class DriverStatusState {
  final DriverStatusEnum status;
  final int? activeRideId;
  final bool isLoading;
  final String? error;
  final int queuePosition;
  final double maxPickupRadiusKm;

  const DriverStatusState({
    this.status = DriverStatusEnum.offline,
    this.activeRideId,
    this.isLoading = false,
    this.error,
    this.queuePosition = 0,
    this.maxPickupRadiusKm = 5.0,
  });

  DriverStatusState copyWith({
    DriverStatusEnum? status,
    int? activeRideId,
    bool? isLoading,
    String? error,
    int? queuePosition,
    double? maxPickupRadiusKm,
  }) {
    return DriverStatusState(
      status: status ?? this.status,
      activeRideId: activeRideId ?? this.activeRideId,
      isLoading: isLoading ?? this.isLoading,
      error: error,
      queuePosition: queuePosition ?? this.queuePosition,
      maxPickupRadiusKm: maxPickupRadiusKm ?? this.maxPickupRadiusKm,
    );
  }

  bool get isOnline =>
      status == DriverStatusEnum.online ||
      status == DriverStatusEnum.inActiveRide;
  bool get hasActiveRide => status == DriverStatusEnum.inActiveRide;
  bool get isInQueue => isOnline && queuePosition > 0;
}

// Driver Status Notifier
class DriverStatusNotifier extends StateNotifier<DriverStatusState> {
  final ApiService _apiService;
  final WebSocketService _wsService;
  final Ref _ref;
  int? _driverId;

  DriverStatusNotifier(
    this._apiService,
    this._wsService,
    this._ref,
  ) : super(const DriverStatusState());

  void setDriverId(int? driverId) {
    print('DriverStatusProvider: setDriverId called with: $driverId');
    _driverId = driverId;
    if (driverId == null) {
      print(
          'DriverStatusProvider: Driver ID is null - resetting to offline state');
      state = const DriverStatusState();
    } else {
      print('DriverStatusProvider: Driver ID set successfully: $_driverId');
    }
  }

  Future<void> goOnline() async {
    // Check if user is loaded
    final user = _ref.read(currentUserProvider);

    if (user == null) {
      print('DriverStatusProvider: Cannot go online - user not loaded yet');
      state = state.copyWith(
        error: 'Please wait for your profile to load, then try again',
      );
      return;
    }

    // Check if driver is verified (has completed KYC)
    final isVerified = _ref.read(isDriverVerifiedProvider);
    if (!isVerified) {
      print('DriverStatusProvider: Cannot go online - driver not verified');
      print(
          'DriverStatusProvider: User needs to complete KYC verification first');
      state = state.copyWith(
        error: 'Please complete driver verification (KYC) before going online',
      );
      return;
    }

    if (_driverId == null) {
      print('DriverStatusProvider: Cannot go online - driver ID is null');
      print(
          'DriverStatusProvider: This usually means the user is not loaded yet or not a driver');
      state = state.copyWith(
        error: 'Please wait for your profile to load, then try again',
      );
      return;
    }

    // Credit gate: driver must have at least 1 credit to go online
    try {
      final creditService = _ref.read(creditServiceProvider);
      final balance = await creditService.getBalance();
      if (balance < 1) {
        state = state.copyWith(
          error:
              'You need at least 1 credit to go online. Contact admin to top up.',
        );
        return;
      }
    } catch (_) {
      // If credit check fails, allow online — do not block on network errors
    }

    state = state.copyWith(isLoading: true, error: null);

    try {
      print(
          'DriverStatusProvider: Going online for driver (user ID) $_driverId');
      print('DriverStatusProvider: Is verified: $isVerified');

      // Include current GPS so backend sets current_location immediately —
      // without this, ST_Distance matching fails until the home screen timer fires.
      Map<String, dynamic> body = {};
      try {
        final permission = await Geolocator.checkPermission();
        if (permission != LocationPermission.denied &&
            permission != LocationPermission.deniedForever) {
          final pos = await Geolocator.getCurrentPosition(
            locationSettings: const LocationSettings(
              accuracy: LocationAccuracy.high,
              timeLimit: Duration(seconds: 5),
            ),
          );
          body = {'current_latitude': pos.latitude, 'current_longitude': pos.longitude};
        }
      } catch (_) {
        // Non-fatal — backend falls back to last known location
      }

      // Call backend endpoint to go online
      final response = await _apiService.post('/driver/online', data: body);

      if (response.data['success'] != true) {
        throw Exception(response.data['message'] ?? 'Failed to go online');
      }

      print('DriverStatusProvider: Backend confirmed driver is online');

      final queuePosition =
          (response.data['data']?['queue_position'] as int?) ?? 0;

      state = state.copyWith(
        status: DriverStatusEnum.online,
        isLoading: false,
        queuePosition: queuePosition,
      );

      // Refresh balance so the chip and request screen show the current value.
      _ref.invalidate(creditsProvider);

      // Subscribe to driver channel for incoming ride requests
      await _subscribeToRideRequests();

      print('DriverStatusProvider: Successfully went online');
    } catch (e) {
      print('DriverStatusProvider: Error going online - $e');
      state = state.copyWith(
        isLoading: false,
        error: e.toString(),
      );
    }
  }

  Future<void> goOffline() async {
    if (_driverId == null) {
      print('DriverStatusProvider: Cannot go offline - driver ID is null');
      return;
    }

    // ✅ FIX: Check for active ride first
    if (state.hasActiveRide) {
      print(
          'DriverStatusProvider: Cannot go offline - active ride in progress');
      state = state.copyWith(
        error: 'Please complete your current ride before going offline',
      );
      return;
    }

    state = state.copyWith(isLoading: true, error: null);

    try {
      print('DriverStatusProvider: Going offline for driver $_driverId');

      // Call backend endpoint
      print('DriverStatusProvider: Calling POST /driver/offline');
      final response = await _apiService.post('/driver/offline');
      print('DriverStatusProvider: goOffline API response: ${response.data}');

      // Keep the driver channel subscription alive so session.replaced
      // is received immediately if another device logs in while offline.
      // The channel is unsubscribed on sign-out via wsService.disconnect().

      state = state.copyWith(
        status: DriverStatusEnum.offline,
        activeRideId: null,
        isLoading: false,
        queuePosition: 0,
      );

      print(
          'DriverStatusProvider: Successfully went offline - state updated to ${state.status}');
    } catch (e) {
      print('DriverStatusProvider: ❌ EXCEPTION in goOffline: $e');
      state = state.copyWith(
        isLoading: false,
        error: e.toString(),
      );
    }
  }

  Future<void> _subscribeToRideRequests() async {
    if (_driverId == null) {
      print('DriverStatusProvider: Cannot subscribe - driver ID is null');
      return;
    }

    print(
        'DriverStatusProvider: Subscribing to driver channel for driver $_driverId');

    await _wsService.subscribeToDriverChannel(
      driverId: _driverId!,
      onNewRideRequest: (eventData) {
        print('🎉 NEW RIDE REQUEST RECEIVED!');
        print('Event data: $eventData');

        try {
          final rideRequest = _mapRideRequestEvent(eventData);
          _ref
              .read(driverIncomingRequestProvider.notifier)
              .setRequest(rideRequest);
        } catch (error, stackTrace) {
          print(
              'DriverStatusProvider: Failed to parse ride request event - $error');
          print(stackTrace);
        }
      },
      onQueuePositionChanged: (eventData) {
        print('DriverStatusProvider: Queue position changed - $eventData');
        final newPosition = (eventData['queue_position'] as int?) ?? 0;
        state = state.copyWith(queuePosition: newPosition);
      },
      onRequestCancelled: (eventData) {
        print('DriverStatusProvider: Ride request cancelled - $eventData');
        // Clear the incoming request so RideRequestScreen dismisses itself
        _ref.read(driverIncomingRequestProvider.notifier).clear();
      },
      onSessionReplaced: (eventData) {
        print('DriverStatusProvider: Session replaced — signing out this device');
        // Another device logged in with this driver account; sign out locally.
        _ref.read(authStateProvider.notifier).signOut();
      },
      onDriverStatusChanged: (eventData) {
        final isOnline = eventData['is_online'] as bool? ?? true;
        if (!isOnline) {
          print('DriverStatusProvider: Auto-kicked offline by backend (zero credits)');
          _ref.read(driverIncomingRequestProvider.notifier).clear();
          state = state.copyWith(
            status: DriverStatusEnum.offline,
            activeRideId: null,
            queuePosition: 0,
          );
          // Invalidate credits so the chip and warning card reflect balance = 0
          _ref.invalidate(creditsProvider);
        }
      },
      onKycStatusChanged: (eventData) {
        print('DriverStatusProvider: Admin changed KYC status — refreshing');
        _ref.read(kycStateProvider.notifier).refreshKycStatus();
      },
      onCreditsUpdated: (eventData) {
        print('DriverStatusProvider: Admin updated credits — refreshing balance');
        _ref.invalidate(creditsProvider);
      },
      onAccountStatusChanged: (eventData) {
        final isSuspended = eventData['is_suspended'] as bool? ?? false;
        if (isSuspended) {
          print('DriverStatusProvider: Account suspended — going offline');
          _ref.read(driverIncomingRequestProvider.notifier).clear();
          state = const DriverStatusState();
        } else {
          print('DriverStatusProvider: Account unsuspended');
        }
        // Refresh user (updates isActive) and KYC status (updates suspendReason).
        _ref.read(authStateProvider.notifier).refreshUser();
        _ref.read(kycStateProvider.notifier).refreshKycStatus();
      },
    );

    print('DriverStatusProvider: Subscribed to ride requests');
  }

  RideRequest _mapRideRequestEvent(Map<String, dynamic> eventData) {
    Map<String, dynamic> normalizeLocation(Map<String, dynamic>? raw) {
      if (raw == null) {
        throw FormatException('Location data missing in ride request event');
      }

      final createdAt = raw['created_at'] ??
          eventData['timestamp'] ??
          DateTime.now().toIso8601String();

      final coordinates = raw['coordinates'] as Map<String, dynamic>? ??
          {
            'latitude': raw['latitude'],
            'longitude': raw['longitude'],
          };

      return {
        'id': raw['id'],
        'name': raw['name'],
        'description': raw['description'],
        'coordinates': coordinates,
        'type': (raw['type'] ?? 'beacon').toString(),
        'is_active': raw['is_active'] ?? true,
        'queue_count': raw['queue_count'],
        'created_at': createdAt,
        'updated_at': raw['updated_at'] ?? createdAt,
      };
    }

    final fallbackTimestamp =
        (eventData['timestamp'] ?? DateTime.now().toIso8601String()).toString();

    final requestJson = {
      'id': eventData['ride_request_id'],
      'rider_id': eventData['rider_id'],
      'pickup_location': normalizeLocation(
        eventData['pickup_location'] as Map<String, dynamic>?,
      ),
      'destination_location': normalizeLocation(
        eventData['destination_location'] as Map<String, dynamic>?,
      ),
      'passenger_count': eventData['passenger_count'] ?? 1,
      'special_requests': eventData['special_requests'],
      'estimated_fare_rp':
          eventData['estimated_fare_rp'] ?? eventData['estimated_fare'],
      'status': (eventData['status'] ?? 'pending').toString(),
      'queue_position': eventData['queue_position'],
      'estimated_wait_time': eventData['estimated_pickup_minutes'],
      'created_at': eventData['created_at'] ?? fallbackTimestamp,
      'updated_at': fallbackTimestamp,
      'dispatched_at': eventData['dispatched_at'],
    };

    return RideRequest.fromJson(requestJson);
  }

  void setActiveRide(int? rideId) {
    if (rideId != null) {
      state = state.copyWith(
        status: DriverStatusEnum.inActiveRide,
        activeRideId: rideId,
      );
    } else {
      state = state.copyWith(
        status: DriverStatusEnum.online,
        activeRideId: null,
      );
    }
  }

  void clearError() {
    state = state.copyWith(error: null);
  }

  void updateMaxRadius(double km) {
    state = state.copyWith(maxPickupRadiusKm: km);
  }

  /// Called on cold app launch when the backend still shows the driver online
  /// but there is no active ride (force-quit / crash left went_online_at set).
  ///
  /// Sets local state to offline, subscribes the driver channel so that
  /// session.replaced is received immediately, and notifies the backend
  /// (fire-and-forget — the heartbeat job is the backstop on error).
  Future<void> kickOfflineOnLaunch() async {
    if (_driverId == null) return;

    print('DriverStatusProvider: kickOfflineOnLaunch — clearing stale online state');

    state = state.copyWith(
      status: DriverStatusEnum.offline,
      activeRideId: null,
      queuePosition: 0,
    );

    // Stay subscribed so session.replaced works while offline.
    await _subscribeToRideRequests();

    try {
      await _apiService.post('/driver/offline');
      print('DriverStatusProvider: kickOfflineOnLaunch — backend notified offline');
    } catch (e) {
      // Non-fatal: KickStaleDrivers heartbeat will clear it within ~90s.
      print('DriverStatusProvider: kickOfflineOnLaunch — backend notify failed (non-fatal): $e');
    }
  }

  /// Fetch the ride request currently dispatched to this driver (if any) and
  /// populate [driverIncomingRequestProvider] so the request sheet shows.
  /// Called when app resumes from a new_ride_request FCM notification tap.
  Future<void> checkPendingDispatch() async {
    if (!state.isOnline) return;
    try {
      final response = await _apiService.get('/driver/current-request');
      final data = response.data['data'];
      if (data == null) return;
      final rideRequest = _mapRideRequestEvent(data as Map<String, dynamic>);
      _ref.read(driverIncomingRequestProvider.notifier).setRequest(rideRequest);
    } catch (_) {
      // Non-fatal — WS will deliver if backend reconnects
    }
  }

  /// Sync driver status from backend session state
  /// Called during session resumption to match backend state
  Future<void> syncFromBackend({
    required bool isOnline,
    int? activeRideId,
  }) async {
    if (activeRideId != null) {
      print(
          'DriverStatusProvider: Syncing status - inActiveRide ($activeRideId)');
      state = state.copyWith(
        status: DriverStatusEnum.inActiveRide,
        activeRideId: activeRideId,
      );
      await _subscribeToRideRequests();
    } else if (isOnline) {
      print('DriverStatusProvider: Syncing status - online');
      state = state.copyWith(
        status: DriverStatusEnum.online,
        activeRideId: null,
      );
      await _subscribeToRideRequests();
    } else {
      print('DriverStatusProvider: Syncing status - offline');
      state = state.copyWith(
        status: DriverStatusEnum.offline,
        activeRideId: null,
      );
      // Subscribe even when offline so session.replaced is received
      // immediately if another device logs in while this one is idle.
      await _subscribeToRideRequests();
    }
  }
}

// Driver Status Provider
final driverStatusProvider =
    StateNotifierProvider<DriverStatusNotifier, DriverStatusState>((ref) {
  final apiService = ref.watch(apiServiceProvider);
  final wsService = ref.watch(websocketServiceProvider);
  final notifier = DriverStatusNotifier(apiService, wsService, ref);

  // Listen to auth changes
  ref.listen(currentUserProvider, (previous, next) {
    print('DriverStatusProvider: currentUserProvider changed');
    print('  - Previous user ID: ${previous?.id}');
    print('  - New user ID: ${next?.id}');
    print('  - User email: ${next?.email}');
    print('  - User role: ${next?.role}');
    print('  - Has driver profile: ${next?.driverProfile != null}');
    if (next?.driverProfile != null) {
      print('  - Driver profile ID: ${next?.driverProfile?.id}');
    }
    notifier.setDriverId(next?.id);
  });

  // Set initial driver ID
  final initialUser = ref.read(currentUserProvider);
  print('DriverStatusProvider: Initializing with user ID: ${initialUser?.id}');
  print(
      'DriverStatusProvider: Has driver profile: ${initialUser?.driverProfile != null}');
  notifier.setDriverId(initialUser?.id);

  return notifier;
});
