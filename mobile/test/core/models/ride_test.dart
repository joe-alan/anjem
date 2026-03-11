import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/models/lat_lng.dart';
import 'package:mobile/core/models/ride.dart';

// Minimal stubs for required nested objects
Map<String, dynamic> _locationJson(String name) => {
      'id': 1,
      'name': name,
      'address': 'UI Campus',
      'coordinates': {'latitude': -6.3605, 'longitude': 106.8271},
      'type': 'beacon',
      'is_beacon': true,
      'is_active': true,
      'created_at': '2026-03-09T10:00:00.000Z',
      'updated_at': '2026-03-09T10:00:00.000Z',
    };

Map<String, dynamic> _userJson(String name) => {
      'id': 1,
      'firebase_uid': 'uid_test_123',
      'name': name,
      'email': 'test@example.com',
      'role': 'driver',
      'is_active': true,
      'created_at': '2026-03-09T10:00:00.000Z',
      'updated_at': '2026-03-09T10:00:00.000Z',
    };

Map<String, dynamic> _baseRideJson({dynamic routeGeometry = 'ABSENT'}) {
  final json = <String, dynamic>{
    'id': 42,
    'ride_request_id': 7,
    'status': 'accepted',
    'passenger_count': 1,
    'estimated_fare_rp': 15000,
    'actual_fare_rp': null,
    'final_fare_rp': null,
    'actual_distance_km': null,
    'actual_duration_minutes': null,
    'special_requests': null,
    'driver_notes': null,
    'driver_accepted_at': null,
    'pickup_time': null,
    'dropoff_time': null,
    'created_at': '2026-03-09T10:00:00.000Z',
    'updated_at': '2026-03-09T10:00:00.000Z',
    'rider': _userJson('Budi'),
    'driver': _userJson('Siti'),
    'pickup_location': _locationJson('Library Gate'),
    'destination_location': _locationJson('Engineering Faculty'),
  };

  if (routeGeometry != 'ABSENT') {
    json['route_geometry'] = routeGeometry;
  }

  return json;
}

void main() {
  group('Ride.fromJson — route_geometry (Phase 1)', () {
    test('routeCoordinates is null when route_geometry key is absent', () {
      final ride = Ride.fromJson(_baseRideJson());

      expect(ride.routeCoordinates, isNull);
    });

    test('routeCoordinates is null when route_geometry is null', () {
      final ride = Ride.fromJson(_baseRideJson(routeGeometry: null));

      expect(ride.routeCoordinates, isNull);
    });

    test('routeCoordinates is null when route_geometry is not a Map', () {
      final ride = Ride.fromJson(_baseRideJson(routeGeometry: 'invalid_string'));

      expect(ride.routeCoordinates, isNull);
    });

    test('routeCoordinates is null when coordinates key is missing', () {
      final ride = Ride.fromJson(_baseRideJson(routeGeometry: {
        'type': 'LineString',
        // 'coordinates' key intentionally absent
      }));

      expect(ride.routeCoordinates, isNull);
    });

    test('routeCoordinates is null when coordinates value is not a List', () {
      final ride = Ride.fromJson(_baseRideJson(routeGeometry: {
        'type': 'LineString',
        'coordinates': 'not_a_list',
      }));

      expect(ride.routeCoordinates, isNull);
    });

    test('routeCoordinates parses valid GeoJSON LineString correctly', () {
      final ride = Ride.fromJson(_baseRideJson(routeGeometry: {
        'type': 'LineString',
        'coordinates': [
          [106.8271, -6.3605], // [lng, lat] — GeoJSON order
          [106.8283, -6.3600],
          [106.8295, -6.3595],
        ],
      }));

      expect(ride.routeCoordinates, isNotNull);
      expect(ride.routeCoordinates!.length, equals(3));

      // Verify [lng, lat] is correctly swapped to LatLng(lat, lng)
      expect(ride.routeCoordinates![0].latitude, closeTo(-6.3605, 0.00001));
      expect(ride.routeCoordinates![0].longitude, closeTo(106.8271, 0.00001));

      expect(ride.routeCoordinates![2].latitude, closeTo(-6.3595, 0.00001));
      expect(ride.routeCoordinates![2].longitude, closeTo(106.8295, 0.00001));
    });

    test('routeCoordinates handles single-point LineString', () {
      final ride = Ride.fromJson(_baseRideJson(routeGeometry: {
        'type': 'LineString',
        'coordinates': [
          [106.8271, -6.3605],
        ],
      }));

      expect(ride.routeCoordinates, isNotNull);
      expect(ride.routeCoordinates!.length, equals(1));
    });

    test('routeCoordinates is empty list when coordinates array is empty', () {
      final ride = Ride.fromJson(_baseRideJson(routeGeometry: {
        'type': 'LineString',
        'coordinates': <dynamic>[],
      }));

      expect(ride.routeCoordinates, isNotNull);
      expect(ride.routeCoordinates!.isEmpty, isTrue);
    });

    test('returns List<LatLng> type (not List<List<double>>)', () {
      final ride = Ride.fromJson(_baseRideJson(routeGeometry: {
        'type': 'LineString',
        'coordinates': [
          [106.8271, -6.3605],
        ],
      }));

      expect(ride.routeCoordinates, isA<List<LatLng>>());
    });
  });

  group('Ride.copyWith — routeCoordinates', () {
    test('copyWith preserves routeCoordinates when not overridden', () {
      final ride = Ride.fromJson(_baseRideJson(routeGeometry: {
        'type': 'LineString',
        'coordinates': [
          [106.8271, -6.3605],
        ],
      }));

      final copy = ride.copyWith(passengerCount: 2);

      expect(copy.routeCoordinates, isNotNull);
      expect(copy.routeCoordinates!.length, equals(1));
    });

    test('copyWith can override routeCoordinates', () {
      final ride = Ride.fromJson(_baseRideJson(routeGeometry: null));
      final newCoords = [
        const LatLng(-6.3605, 106.8271),
        const LatLng(-6.3595, 106.8295),
      ];

      final copy = ride.copyWith(routeCoordinates: newCoords);

      expect(copy.routeCoordinates, equals(newCoords));
    });
  });

  group('Ride equality — routeCoordinates in props', () {
    test('rides with same routeCoordinates are equal', () {
      final json = _baseRideJson(routeGeometry: {
        'type': 'LineString',
        'coordinates': [
          [106.8271, -6.3605],
        ],
      });

      final ride1 = Ride.fromJson(json);
      final ride2 = Ride.fromJson(json);

      expect(ride1, equals(ride2));
    });

    test('rides with different routeCoordinates are not equal', () {
      final ride1 = Ride.fromJson(_baseRideJson(routeGeometry: {
        'type': 'LineString',
        'coordinates': [
          [106.8271, -6.3605],
        ],
      }));

      final ride2 = Ride.fromJson(_baseRideJson(routeGeometry: {
        'type': 'LineString',
        'coordinates': [
          [106.8295, -6.3595],
        ],
      }));

      expect(ride1, isNot(equals(ride2)));
    });
  });
}
