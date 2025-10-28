import 'package:equatable/equatable.dart';
import 'lat_lng.dart';

/// Place search result from backend API
class PlaceSearchResult extends Equatable {
  final int? id; // null if from Mapbox API
  final String name;
  final String? address;
  final String? description;
  final LatLng coordinates;
  final bool isBeacon;
  final int? beaconCapacity;
  final int? currentQueueSize;
  final double? distanceKm;
  final String? category;
  final String source; // 'database' or 'mapbox'

  const PlaceSearchResult({
    this.id,
    required this.name,
    this.address,
    this.description,
    required this.coordinates,
    this.isBeacon = false,
    this.beaconCapacity,
    this.currentQueueSize,
    this.distanceKm,
    this.category,
    this.source = 'database',
  });

  factory PlaceSearchResult.fromJson(Map<String, dynamic> json) {
    // Handle coordinates from API response
    LatLng coordinates;
    if (json['coordinates'] is Map<String, dynamic>) {
      final coords = json['coordinates'] as Map<String, dynamic>;
      coordinates = LatLng(
        (coords['latitude'] as num).toDouble(),
        (coords['longitude'] as num).toDouble(),
      );
    } else if (json['latitude'] != null && json['longitude'] != null) {
      coordinates = LatLng(
        (json['latitude'] as num).toDouble(),
        (json['longitude'] as num).toDouble(),
      );
    } else {
      throw FormatException('Invalid coordinate format in place search result');
    }

    return PlaceSearchResult(
      id: json['id'] as int?,
      name: json['name'] as String,
      address: json['address'] as String?,
      description: json['description'] as String?,
      coordinates: coordinates,
      isBeacon: json['is_beacon'] as bool? ?? false,
      beaconCapacity: json['beacon_capacity'] as int?,
      currentQueueSize: json['current_queue_size'] as int?,
      distanceKm: json['distance_km'] != null
          ? (json['distance_km'] as num).toDouble()
          : null,
      category: json['category'] as String?,
      source: json['source'] as String? ?? 'database',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'address': address,
      'description': description,
      'coordinates': {
        'latitude': coordinates.latitude,
        'longitude': coordinates.longitude,
      },
      'is_beacon': isBeacon,
      'beacon_capacity': beaconCapacity,
      'current_queue_size': currentQueueSize,
      'distance_km': distanceKm,
      'category': category,
      'source': source,
    };
  }

  /// Get display text for list items
  String get displayName => name;

  /// Get subtitle text with address and distance
  String? get subtitle {
    final parts = <String>[];
    if (address != null && address!.isNotEmpty) {
      parts.add(address!);
    }
    if (distanceKm != null) {
      parts.add('${distanceKm!.toStringAsFixed(1)} km');
    }
    return parts.isEmpty ? null : parts.join(' • ');
  }

  /// Get icon based on type
  String get iconName {
    if (isBeacon) return 'beacon';
    switch (category?.toLowerCase()) {
      case 'gate':
        return 'gate';
      case 'faculty':
        return 'school';
      case 'canteen':
        return 'restaurant';
      case 'library':
        return 'library';
      case 'sports':
        return 'sports';
      case 'dormitory':
        return 'home';
      case 'transportation':
        return 'train';
      case 'healthcare':
        return 'hospital';
      case 'shopping':
        return 'shopping';
      default:
        return 'place';
    }
  }

  @override
  List<Object?> get props => [
        id,
        name,
        address,
        coordinates,
        isBeacon,
        source,
      ];
}
