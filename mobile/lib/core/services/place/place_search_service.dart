import 'package:dio/dio.dart';
import '../../models/lat_lng.dart';
import '../../models/place_search_result.dart';
import '../api/api_service.dart';

/// Service for searching places using the backend Place Search API
///
/// This service integrates with the backend's local database + Mapbox fallback
/// place search system implemented in Phase 0.
class PlaceSearchService {
  final ApiService _apiService;

  PlaceSearchService({required ApiService apiService})
      : _apiService = apiService;

  /// Search for places near a location
  ///
  /// Parameters:
  /// - [query]: Search query string (e.g., "gate", "kantin fasilkom")
  /// - [userLocation]: Optional user location for proximity-based results
  /// - [radius]: Search radius in kilometers (default: 5km)
  /// - [limit]: Maximum number of results (default: 10)
  ///
  /// Returns a list of [PlaceSearchResult] from local database + Mapbox API
  Future<List<PlaceSearchResult>> search({
    required String query,
    LatLng? userLocation,
    double? radius,
    int? limit,
  }) async {
    try {
      // Build query parameters
      final params = <String, dynamic>{
        'q': query,
      };

      // Add optional location parameters
      if (userLocation != null) {
        params['latitude'] = userLocation.latitude;
        params['longitude'] = userLocation.longitude;
      }

      // Add optional radius and limit
      if (radius != null) {
        params['radius'] = radius;
      }
      if (limit != null) {
        params['limit'] = limit;
      }

      // Call backend API
      final response = await _apiService.get(
        '/places/search',
        queryParameters: params,
      );

      // Parse response
      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        final results = data['data'] as List<dynamic>;

        return results
            .map((json) => PlaceSearchResult.fromJson(json as Map<String, dynamic>))
            .toList();
      } else {
        throw PlaceSearchException(
          'Search failed with status ${response.statusCode}',
        );
      }
    } on DioException catch (e) {
      if (e.response?.statusCode == 422) {
        // Validation error
        final data = e.response?.data as Map<String, dynamic>?;
        final message = data?['message'] as String? ?? 'Invalid search parameters';
        throw PlaceSearchException(message);
      } else if (e.response?.statusCode == 500) {
        throw PlaceSearchException('Server error. Please try again later.');
      } else {
        throw PlaceSearchException(
          'Search failed: ${e.message ?? "Unknown error"}',
        );
      }
    } catch (e) {
      throw PlaceSearchException(
        'Failed to search places: ${e.toString()}',
      );
    }
  }

  /// Search for nearby beacons (pickup points)
  ///
  /// This is a convenience method that searches specifically for beacons
  /// near the user's location.
  Future<List<PlaceSearchResult>> searchNearbyBeacons({
    required LatLng userLocation,
    double radius = 5.0,
    int limit = 10,
  }) async {
    try {
      // Search with empty query to get all nearby beacons
      final results = await search(
        query: '', // Empty query returns all nearby locations
        userLocation: userLocation,
        radius: radius,
        limit: limit,
      );

      // Filter only beacons
      return results.where((result) => result.isBeacon).toList();
    } catch (e) {
      throw PlaceSearchException(
        'Failed to search nearby beacons: ${e.toString()}',
      );
    }
  }

  /// Get place details by ID
  ///
  /// Note: This assumes the place is already in the local database.
  /// For Mapbox results (id = null), they should be cached after first retrieval.
  Future<PlaceSearchResult?> getPlaceById(int id) async {
    try {
      // Search by exact ID (backend will need to support this endpoint)
      // For now, we'll just return null since this endpoint may not exist yet
      // TODO: Implement /places/{id} endpoint in backend
      return null;
    } catch (e) {
      throw PlaceSearchException(
        'Failed to get place details: ${e.toString()}',
      );
    }
  }
}

/// Exception thrown when place search fails
class PlaceSearchException implements Exception {
  final String message;

  PlaceSearchException(this.message);

  @override
  String toString() => message;
}
