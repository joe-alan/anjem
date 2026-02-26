import 'dart:convert';
import 'package:http/http.dart' as http;
import '../../config/mapbox_config.dart';
import '../../models/lat_lng.dart';

/// Service for fetching directions and routes from Mapbox Directions API
class MapboxDirectionsService {
  /// Fetch route between two coordinates
  /// Returns a list of LatLng points representing the route geometry
  Future<List<LatLng>> getRoute({
    required LatLng origin,
    required LatLng destination,
    String profile = MapboxConfig.defaultDirectionsProfile,
    bool alternatives = false,
    bool steps = false,
    String geometries = 'geojson',
    String overview = 'full',
  }) async {
    try {
      // Build request URL
      final coordString = '${origin.longitude},${origin.latitude};${destination.longitude},${destination.latitude}';

      final uri = Uri.parse(
        '${MapboxConfig.directionsApiBaseUrl}/$profile/$coordString',
      ).replace(queryParameters: {
        'access_token': MapboxConfig.accessToken,
        'alternatives': alternatives.toString(),
        'steps': steps.toString(),
        'geometries': geometries,
        'overview': overview,
      });

      print('🗺️  Fetching route from Mapbox Directions API');
      print('   Origin: ${origin.latitude}, ${origin.longitude}');
      print('   Destination: ${destination.latitude}, ${destination.longitude}');

      // Make request with timeout - wrapped in inner try/catch for DNS errors
      http.Response response;
      try {
        response = await http.get(uri).timeout(
          const Duration(seconds: 10),
          onTimeout: () {
            throw MapboxDirectionsException(
              'Request timed out. Please check your internet connection.',
            );
          },
        );
      } catch (networkError) {
        print('🔴 Network error during HTTP request: $networkError');
        return []; // Return empty immediately on any network error
      }

      if (response.statusCode != 200) {
        print('❌ Mapbox Directions API error: ${response.statusCode}');
        print('   Response: ${response.body}');
        throw MapboxDirectionsException(
          'Failed to fetch directions: ${response.statusCode}',
        );
      }

      // Parse response
      final data = json.decode(response.body);

      if (data['routes'] == null || (data['routes'] as List).isEmpty) {
        print('❌ No routes found in response');
        throw MapboxDirectionsException('No routes found');
      }

      final route = data['routes'][0];
      final geometry = route['geometry'];

      if (geometry == null || geometry['coordinates'] == null) {
        print('❌ No geometry found in route');
        throw MapboxDirectionsException('No geometry found in route');
      }

      // Parse coordinates from GeoJSON format
      final routeCoordinates = (geometry['coordinates'] as List)
          .map((coord) => LatLng(
                coord[1] as double, // latitude
                coord[0] as double, // longitude
              ))
          .toList();

      print('✅ Route fetched successfully: ${routeCoordinates.length} points');

      return routeCoordinates;
    } catch (e) {
      print('❌ Error fetching directions: $e');

      // Check for DNS resolution errors - return empty list instead of crashing
      if (e.toString().contains('No address associated with hostname') ||
          e.toString().contains('Failed host lookup') ||
          e.toString().contains('SocketException') ||
          e.toString().contains('Connection refused') ||
          e.toString().contains('Network is unreachable')) {
        print('⚠️  Network error - returning empty route (app will continue without route line)');
        return []; // Return empty instead of throwing - prevents crash
      }

      // For timeout errors, also return empty
      if (e is MapboxDirectionsException && e.message.contains('timed out')) {
        print('⚠️  Request timed out - returning empty route');
        return [];
      }

      // For other MapboxDirectionsException, return empty to be safe
      if (e is MapboxDirectionsException) {
        print('⚠️  Mapbox error: ${e.message} - returning empty route');
        return [];
      }

      // For any other error, return empty to prevent crash
      print('⚠️  Unknown error - returning empty route to prevent crash');
      return [];
    }
  }

  /// Fetch route with additional information (distance, duration, etc.)
  Future<RouteInfo> getRouteInfo({
    required LatLng origin,
    required LatLng destination,
    String profile = MapboxConfig.defaultDirectionsProfile,
  }) async {
    try {
      // Build request URL
      final coordString = '${origin.longitude},${origin.latitude};${destination.longitude},${destination.latitude}';

      final uri = Uri.parse(
        '${MapboxConfig.directionsApiBaseUrl}/$profile/$coordString',
      ).replace(queryParameters: {
        'access_token': MapboxConfig.accessToken,
        'geometries': 'geojson',
        'overview': 'full',
      });

      // Make request
      final response = await http.get(uri);

      if (response.statusCode != 200) {
        throw MapboxDirectionsException(
          'Failed to fetch directions: ${response.statusCode}',
        );
      }

      // Parse response
      final data = json.decode(response.body);

      if (data['routes'] == null || (data['routes'] as List).isEmpty) {
        throw MapboxDirectionsException('No routes found');
      }

      final route = data['routes'][0];
      final geometry = route['geometry'];

      // Parse route geometry
      final routeCoordinates = (geometry['coordinates'] as List)
          .map((coord) => LatLng(
                coord[1] as double, // latitude
                coord[0] as double, // longitude
              ))
          .toList();

      // Extract route info
      return RouteInfo(
        coordinates: routeCoordinates,
        distanceMeters: (route['distance'] as num).toDouble(),
        durationSeconds: (route['duration'] as num).toDouble(),
      );
    } catch (e) {
      if (e is MapboxDirectionsException) {
        rethrow;
      }
      throw MapboxDirectionsException('Failed to fetch route info: $e');
    }
  }
}

/// Information about a route
class RouteInfo {
  final List<LatLng> coordinates;
  final double distanceMeters;
  final double durationSeconds;

  RouteInfo({
    required this.coordinates,
    required this.distanceMeters,
    required this.durationSeconds,
  });

  /// Distance in kilometers
  double get distanceKm => distanceMeters / 1000;

  /// Duration in minutes
  double get durationMinutes => durationSeconds / 60;
}

/// Exception thrown when Mapbox Directions API fails
class MapboxDirectionsException implements Exception {
  final String message;

  MapboxDirectionsException(this.message);

  @override
  String toString() => 'MapboxDirectionsException: $message';
}
