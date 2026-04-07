import 'package:flutter/foundation.dart';
import '../api/api_service.dart';
import '../../models/location.dart';

class BeaconService {
  final ApiService _apiService;

  BeaconService({required ApiService apiService}) : _apiService = apiService;

  /// Get all active beacon locations
  Future<List<Location>> getBeacons() async {
    try {
      final response = await _apiService.get('/locations');

      if (response.data['success'] != true) {
        throw Exception(
          response.data['message'] ?? 'Failed to fetch beacons',
        );
      }

      final beaconsData = response.data['data'] as List;
      return beaconsData
          .map((json) => Location.fromJson(json as Map<String, dynamic>))
          .toList();
    } catch (e) {
      debugPrint('BeaconService: Error fetching beacons - $e');
      rethrow;
    }
  }

  /// Get nearby beacons within a radius
  Future<List<Location>> getNearbyBeacons({
    required double latitude,
    required double longitude,
    double radiusKm = 5.0,
  }) async {
    try {
      final response = await _apiService.get('/locations', queryParameters: {
        'latitude': latitude,
        'longitude': longitude,
        'radius': radiusKm,
      });

      if (response.data['success'] != true) {
        throw Exception(
          response.data['message'] ?? 'Failed to fetch nearby beacons',
        );
      }

      final beaconsData = response.data['data'] as List;
      return beaconsData
          .map((json) => Location.fromJson(json as Map<String, dynamic>))
          .toList();
    } catch (e) {
      debugPrint('BeaconService: Error fetching nearby beacons - $e');
      rethrow;
    }
  }
}
