import 'package:equatable/equatable.dart';
import 'lat_lng.dart';

enum DriverStatus { offline, online, busy }

class DriverProfile extends Equatable {
  final int id;
  final int userId;
  final String licenseNumber;
  final Map<String, dynamic> vehicleInfo;
  final DriverStatus status;
  final LatLng? currentLocation;
  final double rating;
  final int totalRides;
  final DateTime createdAt;
  final DateTime updatedAt;

  const DriverProfile({
    required this.id,
    required this.userId,
    required this.licenseNumber,
    required this.vehicleInfo,
    required this.status,
    this.currentLocation,
    required this.rating,
    required this.totalRides,
    required this.createdAt,
    required this.updatedAt,
  });

  factory DriverProfile.fromJson(Map<String, dynamic> json) {
    LatLng? location;
    if (json['current_location'] != null) {
      final loc = json['current_location'];
      if (loc is Map<String, dynamic> &&
          loc['latitude'] != null &&
          loc['longitude'] != null) {
        location = LatLng(
          (loc['latitude'] as num).toDouble(),
          (loc['longitude'] as num).toDouble(),
        );
      }
    }

    return DriverProfile(
      id: json['id'] as int,
      userId: json['user_id'] as int,
      licenseNumber: json['license_number'] as String,
      vehicleInfo: json['vehicle_info'] as Map<String, dynamic>,
      status: _parseStatus(json['status'] as String),
      currentLocation: location,
      rating: (json['rating'] as num?)?.toDouble() ?? 0.0,
      totalRides: json['total_rides'] as int? ?? 0,
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'user_id': userId,
      'license_number': licenseNumber,
      'vehicle_info': vehicleInfo,
      'status': _statusToString(status),
      'current_location': currentLocation != null
          ? {
              'latitude': currentLocation!.latitude,
              'longitude': currentLocation!.longitude,
            }
          : null,
      'rating': rating,
      'total_rides': totalRides,
      'created_at': createdAt.toIso8601String(),
      'updated_at': updatedAt.toIso8601String(),
    };
  }

  static DriverStatus _parseStatus(String statusString) {
    switch (statusString.toLowerCase()) {
      case 'online':
        return DriverStatus.online;
      case 'busy':
        return DriverStatus.busy;
      case 'offline':
      default:
        return DriverStatus.offline;
    }
  }

  static String _statusToString(DriverStatus status) {
    switch (status) {
      case DriverStatus.offline:
        return 'offline';
      case DriverStatus.online:
        return 'online';
      case DriverStatus.busy:
        return 'busy';
    }
  }

  String get vehicleMake => vehicleInfo['make'] as String? ?? '';
  String get vehicleModel => vehicleInfo['model'] as String? ?? '';
  String get vehicleColor => vehicleInfo['color'] as String? ?? '';
  String get vehiclePlate => vehicleInfo['plate'] as String? ?? '';
  String get vehicleType => vehicleInfo['type'] as String? ?? '';

  DriverProfile copyWith({
    int? id,
    int? userId,
    String? licenseNumber,
    Map<String, dynamic>? vehicleInfo,
    DriverStatus? status,
    LatLng? currentLocation,
    double? rating,
    int? totalRides,
    DateTime? createdAt,
    DateTime? updatedAt,
  }) {
    return DriverProfile(
      id: id ?? this.id,
      userId: userId ?? this.userId,
      licenseNumber: licenseNumber ?? this.licenseNumber,
      vehicleInfo: vehicleInfo ?? this.vehicleInfo,
      status: status ?? this.status,
      currentLocation: currentLocation ?? this.currentLocation,
      rating: rating ?? this.rating,
      totalRides: totalRides ?? this.totalRides,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  @override
  List<Object?> get props => [
        id,
        userId,
        licenseNumber,
        vehicleInfo,
        status,
        currentLocation,
        rating,
        totalRides,
        createdAt,
        updatedAt,
      ];
}
