import 'package:json_annotation/json_annotation.dart';

part 'fare_estimate.g.dart';

@JsonSerializable()
class FareEstimate {
  final double baseFare;
  final double distanceFare;
  final double totalFare;
  final double estimatedDistance; // in kilometers
  final int estimatedDuration; // in minutes
  final String currency;
  final Map<String, dynamic>? routeGeometry; // GeoJSON LineString from Mapbox

  const FareEstimate({
    required this.baseFare,
    required this.distanceFare,
    required this.totalFare,
    required this.estimatedDistance,
    required this.estimatedDuration,
    this.currency = 'IDR',
    this.routeGeometry,
  });

  factory FareEstimate.fromJson(Map<String, dynamic> json) =>
      _$FareEstimateFromJson(json);

  Map<String, dynamic> toJson() => _$FareEstimateToJson(this);

  String get formattedFare {
    return 'Rp ${totalFare.toStringAsFixed(0)}';
  }

  String get formattedDistance {
    return '${estimatedDistance.toStringAsFixed(1)} km';
  }

  String get formattedDuration {
    final hours = estimatedDuration ~/ 60;
    final minutes = estimatedDuration % 60;

    if (hours > 0) {
      return '$hours h $minutes min';
    }
    return '$minutes min';
  }

  @override
  String toString() {
    return 'FareEstimate(total: $formattedFare, distance: $formattedDistance, duration: $formattedDuration)';
  }
}
