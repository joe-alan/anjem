import 'package:equatable/equatable.dart';

/// A simple coordinate class that represents latitude and longitude
///
/// This is a platform-independent representation of coordinates that
/// doesn't depend on any specific map SDK (Google Maps, Mapbox, etc.)
class LatLng extends Equatable {
  final double latitude;
  final double longitude;

  const LatLng(this.latitude, this.longitude);

  /// Create from JSON
  factory LatLng.fromJson(Map<String, dynamic> json) {
    return LatLng(
      (json['latitude'] as num).toDouble(),
      (json['longitude'] as num).toDouble(),
    );
  }

  /// Convert to JSON
  Map<String, dynamic> toJson() {
    return {
      'latitude': latitude,
      'longitude': longitude,
    };
  }

  @override
  List<Object?> get props => [latitude, longitude];

  @override
  String toString() => 'LatLng($latitude, $longitude)';
}
