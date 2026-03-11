import 'package:equatable/equatable.dart';
import 'location.dart';

enum RideRequestStatus { pending, matched, cancelled, expired, completed }

class RideRequest extends Equatable {
  final int id;
  final int? riderId; // Nullable - not always provided by backend
  final Location pickupLocation;
  final Location destinationLocation;
  final int passengerCount;
  final String? specialRequests;
  final double estimatedFare;
  final RideRequestStatus status;
  final int? queuePosition;
  final int? estimatedWaitTime; // in minutes
  final DateTime createdAt;
  final DateTime updatedAt;

  const RideRequest({
    required this.id,
    this.riderId, // Now optional
    required this.pickupLocation,
    required this.destinationLocation,
    required this.passengerCount,
    this.specialRequests,
    required this.estimatedFare,
    required this.status,
    this.queuePosition,
    this.estimatedWaitTime,
    required this.createdAt,
    required this.updatedAt,
  });

  factory RideRequest.fromJson(Map<String, dynamic> json) {
    return RideRequest(
      id: json['id'] as int,
      riderId: json['rider_id'] as int?, // Nullable - not always provided
      pickupLocation: Location.fromJson(
        json['pickup_location'] as Map<String, dynamic>,
      ),
      destinationLocation: Location.fromJson(
        json['destination_location'] as Map<String, dynamic>,
      ),
      passengerCount: json['passenger_count'] as int,
      specialRequests: json['special_requests'] as String?,
      // Backend returns 'estimated_fare_rp', fallback to 'estimated_fare'
      estimatedFare: (json['estimated_fare_rp'] ?? json['estimated_fare'] as num).toDouble(),
      status: _parseStatus(json['status'] as String),
      queuePosition: json['queue_position'] as int?,
      estimatedWaitTime: json['estimated_wait_time'] as int?,
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'rider_id': riderId,
      'pickup_location': pickupLocation.toJson(),
      'destination_location': destinationLocation.toJson(),
      'passenger_count': passengerCount,
      'special_requests': specialRequests,
      'estimated_fare': estimatedFare,
      'status': _statusToString(status),
      'queue_position': queuePosition,
      'estimated_wait_time': estimatedWaitTime,
      'created_at': createdAt.toIso8601String(),
      'updated_at': updatedAt.toIso8601String(),
    };
  }

  static RideRequestStatus _parseStatus(String statusString) {
    switch (statusString.toLowerCase()) {
      case 'matched':
        return RideRequestStatus.matched;
      case 'cancelled':
        return RideRequestStatus.cancelled;
      case 'expired':
        return RideRequestStatus.expired;
      case 'completed':
        return RideRequestStatus.completed;
      case 'pending':
      default:
        return RideRequestStatus.pending;
    }
  }

  static String _statusToString(RideRequestStatus status) {
    switch (status) {
      case RideRequestStatus.pending:
        return 'pending';
      case RideRequestStatus.matched:
        return 'matched';
      case RideRequestStatus.cancelled:
        return 'cancelled';
      case RideRequestStatus.expired:
        return 'expired';
      case RideRequestStatus.completed:
        return 'completed';
    }
  }

  bool get isPending => status == RideRequestStatus.pending;
  bool get isMatched => status == RideRequestStatus.matched;
  bool get isCancelled => status == RideRequestStatus.cancelled;
  bool get isExpired => status == RideRequestStatus.expired;
  bool get isCompleted => status == RideRequestStatus.completed;

  RideRequest copyWith({
    int? id,
    int? riderId,
    Location? pickupLocation,
    Location? destinationLocation,
    int? passengerCount,
    String? specialRequests,
    double? estimatedFare,
    RideRequestStatus? status,
    int? queuePosition,
    int? estimatedWaitTime,
    DateTime? createdAt,
    DateTime? updatedAt,
  }) {
    return RideRequest(
      id: id ?? this.id,
      riderId: riderId ?? this.riderId,
      pickupLocation: pickupLocation ?? this.pickupLocation,
      destinationLocation: destinationLocation ?? this.destinationLocation,
      passengerCount: passengerCount ?? this.passengerCount,
      specialRequests: specialRequests ?? this.specialRequests,
      estimatedFare: estimatedFare ?? this.estimatedFare,
      status: status ?? this.status,
      queuePosition: queuePosition ?? this.queuePosition,
      estimatedWaitTime: estimatedWaitTime ?? this.estimatedWaitTime,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  @override
  List<Object?> get props => [
        id,
        riderId,
        pickupLocation,
        destinationLocation,
        passengerCount,
        specialRequests,
        estimatedFare,
        status,
        queuePosition,
        estimatedWaitTime,
        createdAt,
        updatedAt,
      ];
}
