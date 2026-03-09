import 'package:equatable/equatable.dart';
import 'lat_lng.dart';
import 'location.dart';
import 'user.dart';

enum RideStatus {
  accepted,
  driverArrived,
  inProgress,
  completed,
  cancelled,
}

class Ride extends Equatable {
  final int id;
  final int requestId;
  final int riderId;
  final int driverId;
  final Location pickupLocation;
  final Location destinationLocation;
  final RideStatus status;
  final double fare;
  final int passengerCount;
  final String? specialRequests;
  final User? rider;
  final User? driver;
  final DateTime? acceptedAt;
  final DateTime? arrivedAt;
  final DateTime? startedAt;
  final DateTime? completedAt;
  final DateTime? cancelledAt;
  final String? cancellationReason;
  final bool? adminOverride;
  final String? adminReason;
  final List<LatLng>? routeCoordinates;
  final DateTime createdAt;
  final DateTime updatedAt;

  const Ride({
    required this.id,
    required this.requestId,
    required this.riderId,
    required this.driverId,
    required this.pickupLocation,
    required this.destinationLocation,
    required this.status,
    required this.fare,
    required this.passengerCount,
    this.specialRequests,
    this.rider,
    this.driver,
    this.acceptedAt,
    this.arrivedAt,
    this.startedAt,
    this.completedAt,
    this.cancelledAt,
    this.cancellationReason,
    this.adminOverride,
    this.adminReason,
    this.routeCoordinates,
    required this.createdAt,
    required this.updatedAt,
  });

  factory Ride.fromJson(Map<String, dynamic> json) {
    // Extract rider and driver objects first
    final riderObj = json['rider'] != null
        ? User.fromJson(json['rider'] as Map<String, dynamic>)
        : null;
    final driverObj = json['driver'] != null
        ? User.fromJson(json['driver'] as Map<String, dynamic>)
        : null;

    return Ride(
      id: json['id'] as int,
      requestId: json['ride_request_id'] as int,
      riderId: riderObj?.id ?? 0, // Fallback to 0 if not provided
      driverId: driverObj?.id ?? 0, // Fallback to 0 if not provided
      pickupLocation: Location.fromJson(
        json['pickup_location'] as Map<String, dynamic>,
      ),
      destinationLocation: Location.fromJson(
        json['destination_location'] as Map<String, dynamic>,
      ),
      status: _parseStatus(json['status'] as String),
      fare: (json['final_fare_rp'] as num?)?.toDouble() ??
            (json['estimated_fare_rp'] as num).toDouble(),
      passengerCount: json['passenger_count'] as int,
      specialRequests: json['special_requests'] as String?,
      rider: riderObj,
      driver: driverObj,
      acceptedAt: json['driver_accepted_at'] != null
          ? DateTime.parse(json['driver_accepted_at'] as String)
          : null,
      arrivedAt: null, // Not provided by backend yet
      startedAt: json['pickup_time'] != null
          ? DateTime.parse(json['pickup_time'] as String)
          : null,
      completedAt: json['dropoff_time'] != null
          ? DateTime.parse(json['dropoff_time'] as String)
          : null,
      cancelledAt: json['cancelled_at'] != null
          ? DateTime.parse(json['cancelled_at'] as String)
          : null,
      cancellationReason: json['cancellation_reason'] as String?,
      adminOverride: json['admin_override'] as bool?,
      adminReason: json['admin_reason'] as String?,
      routeCoordinates: _parseRouteGeometry(json['route_geometry']),
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
    );
  }

  static List<LatLng>? _parseRouteGeometry(dynamic geometry) {
    if (geometry == null) return null;
    final coords = geometry['coordinates'];
    if (coords == null) return null;
    return (coords as List)
        .map((c) => LatLng((c[1] as num).toDouble(), (c[0] as num).toDouble()))
        .toList();
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'request_id': requestId,
      'rider_id': riderId,
      'driver_id': driverId,
      'pickup_location': pickupLocation.toJson(),
      'destination_location': destinationLocation.toJson(),
      'status': _statusToString(status),
      'fare': fare,
      'passenger_count': passengerCount,
      'special_requests': specialRequests,
      'rider': rider?.toJson(),
      'driver': driver?.toJson(),
      'accepted_at': acceptedAt?.toIso8601String(),
      'arrived_at': arrivedAt?.toIso8601String(),
      'started_at': startedAt?.toIso8601String(),
      'completed_at': completedAt?.toIso8601String(),
      'cancelled_at': cancelledAt?.toIso8601String(),
      'cancellation_reason': cancellationReason,
      'admin_override': adminOverride,
      'admin_reason': adminReason,
      'created_at': createdAt.toIso8601String(),
      'updated_at': updatedAt.toIso8601String(),
    };
  }

  static RideStatus _parseStatus(String statusString) {
    switch (statusString.toLowerCase()) {
      case 'driver_arrived':
      case 'arrived':
        return RideStatus.driverArrived;
      case 'in_progress':
      case 'started':
        return RideStatus.inProgress;
      case 'completed':
        return RideStatus.completed;
      case 'cancelled':
        return RideStatus.cancelled;
      case 'accepted':
      default:
        return RideStatus.accepted;
    }
  }

  static String _statusToString(RideStatus status) {
    switch (status) {
      case RideStatus.accepted:
        return 'accepted';
      case RideStatus.driverArrived:
        return 'driver_arrived';
      case RideStatus.inProgress:
        return 'in_progress';
      case RideStatus.completed:
        return 'completed';
      case RideStatus.cancelled:
        return 'cancelled';
    }
  }

  bool get isAccepted => status == RideStatus.accepted;
  bool get isDriverArrived => status == RideStatus.driverArrived;
  bool get isInProgress => status == RideStatus.inProgress;
  bool get isCompleted => status == RideStatus.completed;
  bool get isCancelled => status == RideStatus.cancelled;
  bool get isActive => !isCompleted && !isCancelled;

  Ride copyWith({
    int? id,
    int? requestId,
    int? riderId,
    int? driverId,
    Location? pickupLocation,
    Location? destinationLocation,
    RideStatus? status,
    double? fare,
    int? passengerCount,
    String? specialRequests,
    User? rider,
    User? driver,
    DateTime? acceptedAt,
    DateTime? arrivedAt,
    DateTime? startedAt,
    DateTime? completedAt,
    DateTime? cancelledAt,
    String? cancellationReason,
    bool? adminOverride,
    String? adminReason,
    List<LatLng>? routeCoordinates,
    DateTime? createdAt,
    DateTime? updatedAt,
  }) {
    return Ride(
      id: id ?? this.id,
      requestId: requestId ?? this.requestId,
      riderId: riderId ?? this.riderId,
      driverId: driverId ?? this.driverId,
      pickupLocation: pickupLocation ?? this.pickupLocation,
      destinationLocation: destinationLocation ?? this.destinationLocation,
      status: status ?? this.status,
      fare: fare ?? this.fare,
      passengerCount: passengerCount ?? this.passengerCount,
      specialRequests: specialRequests ?? this.specialRequests,
      rider: rider ?? this.rider,
      driver: driver ?? this.driver,
      acceptedAt: acceptedAt ?? this.acceptedAt,
      arrivedAt: arrivedAt ?? this.arrivedAt,
      startedAt: startedAt ?? this.startedAt,
      completedAt: completedAt ?? this.completedAt,
      cancelledAt: cancelledAt ?? this.cancelledAt,
      cancellationReason: cancellationReason ?? this.cancellationReason,
      adminOverride: adminOverride ?? this.adminOverride,
      adminReason: adminReason ?? this.adminReason,
      routeCoordinates: routeCoordinates ?? this.routeCoordinates,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  @override
  List<Object?> get props => [
        id,
        requestId,
        riderId,
        driverId,
        pickupLocation,
        destinationLocation,
        status,
        fare,
        passengerCount,
        specialRequests,
        rider,
        driver,
        acceptedAt,
        arrivedAt,
        startedAt,
        completedAt,
        cancelledAt,
        cancellationReason,
        adminOverride,
        adminReason,
        routeCoordinates,
        createdAt,
        updatedAt,
      ];
}
