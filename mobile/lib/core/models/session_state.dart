import 'package:equatable/equatable.dart';
import 'ride.dart';
import 'ride_request.dart';

enum SessionStateType {
  idle,
  requestPending,
  requestMatched,
  requestInProgress,
  rideActive,
}

enum RideRole {
  rider,
  driver,
}

class DriverContext extends Equatable {
  final bool isDriver;
  final bool isOnline;
  final DateTime? wentOnlineAt;
  final int? activeRideId;

  const DriverContext({
    required this.isDriver,
    required this.isOnline,
    this.wentOnlineAt,
    this.activeRideId,
  });

  factory DriverContext.fromJson(Map<String, dynamic> json) {
    return DriverContext(
      isDriver: json['is_driver'] as bool,
      isOnline: json['is_online'] as bool,
      wentOnlineAt: json['went_online_at'] != null
          ? DateTime.parse(json['went_online_at'] as String)
          : null,
      activeRideId: json['active_ride_id'] as int?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'is_driver': isDriver,
      'is_online': isOnline,
      'went_online_at': wentOnlineAt?.toIso8601String(),
      'active_ride_id': activeRideId,
    };
  }

  @override
  List<Object?> get props => [isDriver, isOnline, wentOnlineAt, activeRideId];
}

class RiderCooldown {
  final String cooldownUntil;
  final int cancelCount;

  const RiderCooldown({required this.cooldownUntil, required this.cancelCount});

  factory RiderCooldown.fromJson(Map<String, dynamic> json) {
    return RiderCooldown(
      cooldownUntil: json['cooldown_until'] as String,
      cancelCount: (json['cancel_count'] as int?) ?? 0,
    );
  }
}

class SessionState extends Equatable {
  final SessionStateType state;
  final RideRole? rideRole;
  final Ride? activeRide;
  final RideRequest? activeRequest;
  final DriverContext driverContext;
  final RiderCooldown? riderCooldown;

  const SessionState({
    required this.state,
    this.rideRole,
    this.activeRide,
    this.activeRequest,
    required this.driverContext,
    this.riderCooldown,
  });

  factory SessionState.fromJson(Map<String, dynamic> json) {
    return SessionState(
      state: _parseState(json['state'] as String),
      rideRole: json['ride_role'] != null
          ? _parseRideRole(json['ride_role'] as String)
          : null,
      activeRide: json['active_ride'] != null
          ? Ride.fromJson(json['active_ride'] as Map<String, dynamic>)
          : null,
      activeRequest: json['active_request'] != null
          ? RideRequest.fromJson(json['active_request'] as Map<String, dynamic>)
          : null,
      driverContext: DriverContext.fromJson(
        json['driver_context'] as Map<String, dynamic>,
      ),
      riderCooldown: json['rider_cooldown'] != null
          ? RiderCooldown.fromJson(json['rider_cooldown'] as Map<String, dynamic>)
          : null,
    );
  }

  static SessionStateType _parseState(String state) {
    switch (state) {
      case 'idle':
        return SessionStateType.idle;
      case 'request_pending':
        return SessionStateType.requestPending;
      case 'request_matched':
        return SessionStateType.requestMatched;
      case 'request_in_progress':
        return SessionStateType.requestInProgress;
      case 'ride_active':
        return SessionStateType.rideActive;
      default:
        return SessionStateType.idle;
    }
  }

  static RideRole _parseRideRole(String role) {
    print('DEBUG: Parsing ride_role: "$role" (type: ${role.runtimeType})');
    switch (role.toLowerCase().trim()) {
      case 'rider':
        return RideRole.rider;
      case 'driver':
        return RideRole.driver;
      default:
        print('ERROR: Invalid ride role received: "$role"');
        throw ArgumentError('Invalid ride role: $role');
    }
  }

  Map<String, dynamic> toJson() {
    return {
      'state': _stateToString(state),
      'ride_role': rideRole != null ? _roleToString(rideRole!) : null,
      'active_ride': activeRide?.toJson(),
      'active_request': activeRequest?.toJson(),
      'driver_context': driverContext.toJson(),
    };
  }

  static String _stateToString(SessionStateType state) {
    switch (state) {
      case SessionStateType.idle:
        return 'idle';
      case SessionStateType.requestPending:
        return 'request_pending';
      case SessionStateType.requestMatched:
        return 'request_matched';
      case SessionStateType.requestInProgress:
        return 'request_in_progress';
      case SessionStateType.rideActive:
        return 'ride_active';
    }
  }

  static String _roleToString(RideRole role) {
    switch (role) {
      case RideRole.rider:
        return 'rider';
      case RideRole.driver:
        return 'driver';
    }
  }

  bool get hasActiveRide => activeRide != null;
  bool get hasActiveRequest => activeRequest != null;
  bool get isIdle => state == SessionStateType.idle;
  bool get needsToResumeRide => state == SessionStateType.rideActive;
  bool get isWaitingForDriver =>
      state == SessionStateType.requestMatched ||
      state == SessionStateType.requestInProgress;

  @override
  List<Object?> get props =>
      [state, rideRole, activeRide, activeRequest, driverContext, riderCooldown];
}
