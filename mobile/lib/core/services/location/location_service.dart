import 'dart:async';
import 'package:geolocator/geolocator.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:permission_handler/permission_handler.dart';

class LocationService {
  StreamSubscription<Position>? _positionStreamSubscription;

  /// Check if location services are enabled
  Future<bool> isLocationServiceEnabled() async {
    return await Geolocator.isLocationServiceEnabled();
  }

  /// Check location permission status
  Future<LocationPermission> checkPermission() async {
    return await Geolocator.checkPermission();
  }

  /// Request location permission
  Future<LocationPermission> requestPermission() async {
    return await Geolocator.requestPermission();
  }

  /// Ensure location permission is granted
  Future<bool> ensurePermission() async {
    // Check if location service is enabled
    bool serviceEnabled = await isLocationServiceEnabled();
    if (!serviceEnabled) {
      throw LocationServiceException(
        'Location services are disabled. Please enable them in settings.',
      );
    }

    // Check permission
    LocationPermission permission = await checkPermission();

    if (permission == LocationPermission.denied) {
      permission = await requestPermission();
      if (permission == LocationPermission.denied) {
        throw LocationServiceException(
          'Location permission denied. Please grant permission in settings.',
        );
      }
    }

    if (permission == LocationPermission.deniedForever) {
      throw LocationServiceException(
        'Location permission permanently denied. Please enable it in app settings.',
      );
    }

    return true;
  }

  /// Get current location
  Future<LatLng> getCurrentLocation() async {
    await ensurePermission();

    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          distanceFilter: 0,
        ),
      );

      return LatLng(position.latitude, position.longitude);
    } catch (e) {
      throw LocationServiceException(
        'Failed to get current location: ${e.toString()}',
      );
    }
  }

  /// Start tracking location with periodic updates
  Stream<LatLng> trackLocation({
    int intervalSeconds = 10,
    int distanceFilter = 10, // meters
  }) {
    final controller = StreamController<LatLng>();

    ensurePermission().then((_) {
      _positionStreamSubscription = Geolocator.getPositionStream(
        locationSettings: LocationSettings(
          accuracy: LocationAccuracy.high,
          distanceFilter: distanceFilter,
          timeLimit: Duration(seconds: intervalSeconds),
        ),
      ).listen(
        (position) {
          controller.add(LatLng(position.latitude, position.longitude));
        },
        onError: (error) {
          controller.addError(
            LocationServiceException(
              'Location tracking error: ${error.toString()}',
            ),
          );
        },
      );
    }).catchError((error) {
      controller.addError(error);
    });

    return controller.stream;
  }

  /// Stop location tracking
  void stopTracking() {
    _positionStreamSubscription?.cancel();
    _positionStreamSubscription = null;
  }

  /// Calculate distance between two coordinates (in meters)
  double calculateDistance(LatLng from, LatLng to) {
    return Geolocator.distanceBetween(
      from.latitude,
      from.longitude,
      to.latitude,
      to.longitude,
    );
  }

  /// Calculate bearing between two coordinates (in degrees)
  double calculateBearing(LatLng from, LatLng to) {
    return Geolocator.bearingBetween(
      from.latitude,
      from.longitude,
      to.latitude,
      to.longitude,
    );
  }

  /// Check if user is within radius of a location
  bool isWithinRadius(LatLng userLocation, LatLng targetLocation,
      double radiusMeters) {
    final distance = calculateDistance(userLocation, targetLocation);
    return distance <= radiusMeters;
  }

  /// Open app settings for permission management
  Future<void> openAppSettings() async {
    await Geolocator.openAppSettings();
  }

  /// Open location settings
  Future<void> openLocationSettings() async {
    await Geolocator.openLocationSettings();
  }

  void dispose() {
    stopTracking();
  }
}

class LocationServiceException implements Exception {
  final String message;

  LocationServiceException(this.message);

  @override
  String toString() => message;
}
