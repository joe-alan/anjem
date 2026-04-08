import 'package:flutter/foundation.dart';
import '../api/api_service.dart';
import '../../config/app_config.dart';
import '../../models/ride.dart';

class RideService {
  final ApiService _apiService;

  RideService({required ApiService apiService}) : _apiService = apiService;

  /// Get ride details
  Future<Ride> getRide(int rideId) async {
    try {
      final response = await _apiService.get('/rides/$rideId');

      if (response.data['success'] != true) {
        throw Exception(
          response.data['message'] ?? 'Failed to get ride details',
        );
      }

      return Ride.fromJson(response.data['data'] as Map<String, dynamic>);
    } catch (e) {
      debugPrint('RideService: Error getting ride - $e');
      rethrow;
    }
  }

  /// Get list of user's rides
  Future<List<Ride>> getUserRides({String? status}) async {
    try {
      final params = <String, String>{
        'role': AppConfig.instance.flavorName, // 'rider' or 'driver'
      };
      if (status != null) params['status'] = status;

      final response = await _apiService.get(
        '/rides',
        queryParameters: params,
      );

      if (response.data['success'] != true) {
        throw Exception(
          response.data['message'] ?? 'Failed to get rides',
        );
      }

      final ridesData = response.data['data'] as List;
      return ridesData
          .map((json) => Ride.fromJson(json as Map<String, dynamic>))
          .toList();
    } catch (e) {
      debugPrint('RideService: Error getting rides - $e');
      rethrow;
    }
  }

  /// Rate a completed ride
  Future<void> rateRide({
    required int rideId,
    required int rating,
    List<String>? tags,
    String? feedback,
  }) async {
    try {
      final response = await _apiService.post('/rides/$rideId/rate', data: {
        'rating': rating,
        if (tags != null && tags.isNotEmpty) 'tags': tags,
        if (feedback != null && feedback.isNotEmpty) 'feedback': feedback,
      });

      if (response.data['success'] != true) {
        throw Exception(
          response.data['message'] ?? 'Failed to rate ride',
        );
      }
    } catch (e) {
      debugPrint('RideService: Error rating ride - $e');
      rethrow;
    }
  }

  /// Cancel an active ride (rider). Returns the ride and penalty meta.
  Future<({Ride ride, Map<String, dynamic> meta})> cancelRide(int rideId) async {
    try {
      final response = await _apiService.patch(
        '/rides/$rideId/status',
        data: {'status': 'cancelled'},
      );

      if (response.data['success'] != true) {
        throw Exception(
          response.data['message'] ?? 'Failed to cancel ride',
        );
      }

      return (
        ride: Ride.fromJson(response.data['data'] as Map<String, dynamic>),
        meta: (response.data['meta'] as Map<String, dynamic>?) ?? {},
      );
    } catch (e) {
      debugPrint('RideService: Error cancelling ride - $e');
      rethrow;
    }
  }

  /// Update ride status (used by driver)
  Future<Ride> updateRideStatus({
    required int rideId,
    required String status,
  }) async {
    try {
      final response = await _apiService.patch(
        '/rides/$rideId/status',
        data: {'status': status},
      );

      if (response.data['success'] != true) {
        throw Exception(
          response.data['message'] ?? 'Failed to update ride status',
        );
      }

      return Ride.fromJson(response.data['data'] as Map<String, dynamic>);
    } catch (e) {
      debugPrint('RideService: Error updating ride status - $e');
      rethrow;
    }
  }
}
