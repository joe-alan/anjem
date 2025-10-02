import 'package:equatable/equatable.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';

enum LocationType { beacon, p2p }

class Location extends Equatable {
  final int id;
  final String name;
  final String? description;
  final LatLng coordinates;
  final LocationType type;
  final bool isActive;
  final int? queueCount;
  final DateTime createdAt;
  final DateTime updatedAt;

  const Location({
    required this.id,
    required this.name,
    this.description,
    required this.coordinates,
    required this.type,
    required this.isActive,
    this.queueCount,
    required this.createdAt,
    required this.updatedAt,
  });

  factory Location.fromJson(Map<String, dynamic> json) {
    LatLng coordinates;

    // Handle different coordinate formats from backend
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
      throw FormatException('Invalid coordinate format');
    }

    return Location(
      id: json['id'] as int,
      name: json['name'] as String,
      description: json['description'] as String?,
      coordinates: coordinates,
      type: _parseType(json['type'] as String),
      isActive: json['is_active'] as bool? ?? true,
      queueCount: json['queue_count'] as int?,
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'description': description,
      'coordinates': {
        'latitude': coordinates.latitude,
        'longitude': coordinates.longitude,
      },
      'type': _typeToString(type),
      'is_active': isActive,
      'queue_count': queueCount,
      'created_at': createdAt.toIso8601String(),
      'updated_at': updatedAt.toIso8601String(),
    };
  }

  static LocationType _parseType(String typeString) {
    switch (typeString.toLowerCase()) {
      case 'p2p':
        return LocationType.p2p;
      case 'beacon':
      default:
        return LocationType.beacon;
    }
  }

  static String _typeToString(LocationType type) {
    switch (type) {
      case LocationType.beacon:
        return 'beacon';
      case LocationType.p2p:
        return 'p2p';
    }
  }

  bool get isBeacon => type == LocationType.beacon;
  bool get isP2P => type == LocationType.p2p;

  Location copyWith({
    int? id,
    String? name,
    String? description,
    LatLng? coordinates,
    LocationType? type,
    bool? isActive,
    int? queueCount,
    DateTime? createdAt,
    DateTime? updatedAt,
  }) {
    return Location(
      id: id ?? this.id,
      name: name ?? this.name,
      description: description ?? this.description,
      coordinates: coordinates ?? this.coordinates,
      type: type ?? this.type,
      isActive: isActive ?? this.isActive,
      queueCount: queueCount ?? this.queueCount,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  @override
  List<Object?> get props => [
        id,
        name,
        description,
        coordinates,
        type,
        isActive,
        queueCount,
        createdAt,
        updatedAt,
      ];
}
