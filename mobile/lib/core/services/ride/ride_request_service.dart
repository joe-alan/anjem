import 'package:flutter/foundation.dart';
import '../api/api_service.dart';
import '../../models/ride_request.dart';
import '../../models/fare_estimate.dart';

/// Thrown when the rider is in a cooldown period and cannot create requests.
class CooldownException implements Exception {
  final String message;
  final String? cooldownUntil;
  CooldownException(this.message, {this.cooldownUntil});
  @override
  String toString() => message;
}

class RideRequestService {
  final ApiService _apiService;

  RideRequestService({required ApiService apiService})
      : _apiService = apiService;

  /// Get fare estimate for a route (by location IDs)
  Future<FareEstimate> getEstimate({
    required int pickupBeaconId,
    required int destinationBeaconId,
    int passengerCount = 1,
  }) async {
    try {
      final response = await _apiService.get('/requests/estimates', queryParameters: {
        'pickup_beacon_id': pickupBeaconId,
        'destination_beacon_id': destinationBeaconId,
        'passenger_count': passengerCount,
      });

      if (response.data['success'] != true) {
        throw Exception(
          response.data['message'] ?? 'Failed to get fare estimate',
        );
      }

      return FareEstimate.fromJson(
        response.data['data'] as Map<String, dynamic>,
      );
    } catch (e) {
      debugPrint('RideRequestService: Error getting estimate - $e');
      rethrow;
    }
  }

  /// Get fare estimate by coordinates (P2P flow)
  Future<FareEstimate> getEstimateByCoordinates({
    required double pickupLat,
    required double pickupLng,
    required double destLat,
    required double destLng,
    int passengerCount = 1,
  }) async {
    try {
      final response = await _apiService.get('/requests/estimates', queryParameters: {
        'pickup_latitude': pickupLat,
        'pickup_longitude': pickupLng,
        'destination_latitude': destLat,
        'destination_longitude': destLng,
        'passenger_count': passengerCount,
      });

      if (response.data['success'] != true) {
        throw Exception(
          response.data['message'] ?? 'Failed to get fare estimate',
        );
      }

      return FareEstimate.fromJson(
        response.data['data'] as Map<String, dynamic>,
      );
    } catch (e) {
      debugPrint('RideRequestService: Error getting coordinate estimate - $e');
      rethrow;
    }
  }

  /// Create a new ride request (by location IDs)
  Future<RideRequest> createRequest({
    required int pickupBeaconId,
    required int destinationBeaconId,
    required int passengerCount,
    String? specialRequests,
  }) async {
    try {
      final response = await _apiService.post('/requests', data: {
        'pickup_beacon_id': pickupBeaconId,
        'destination_beacon_id': destinationBeaconId,
        'passenger_count': passengerCount,
        if (specialRequests != null && specialRequests.isNotEmpty)
          'special_requests': specialRequests,
      });

      if (response.data['success'] != true) {
        if (response.statusCode == 429 && response.data['data'] is Map) {
          throw CooldownException(
            response.data['message'] ?? 'Please wait before requesting',
            cooldownUntil: (response.data['data'] as Map)['cooldown_until'] as String?,
          );
        }
        throw Exception(
          response.data['message'] ?? 'Failed to create ride request',
        );
      }

      return RideRequest.fromJson(
        response.data['data'] as Map<String, dynamic>,
      );
    } catch (e) {
      debugPrint('RideRequestService: Error creating request - $e');
      rethrow;
    }
  }

  /// Create a new ride request by coordinates (P2P flow)
  Future<RideRequest> createRequestByCoordinates({
    required double pickupLat,
    required double pickupLng,
    required String pickupName,
    required double destLat,
    required double destLng,
    required String destName,
    required int passengerCount,
    String? specialRequests,
  }) async {
    try {
      final response = await _apiService.post('/requests', data: {
        'pickup_latitude': pickupLat,
        'pickup_longitude': pickupLng,
        'pickup_name': pickupName,
        'destination_latitude': destLat,
        'destination_longitude': destLng,
        'destination_name': destName,
        'passenger_count': passengerCount,
        if (specialRequests != null && specialRequests.isNotEmpty)
          'special_requests': specialRequests,
      });

      if (response.data['success'] != true) {
        if (response.statusCode == 429 && response.data['data'] is Map) {
          throw CooldownException(
            response.data['message'] ?? 'Please wait before requesting',
            cooldownUntil: (response.data['data'] as Map)['cooldown_until'] as String?,
          );
        }
        throw Exception(
          response.data['message'] ?? 'Failed to create ride request',
        );
      }

      return RideRequest.fromJson(
        response.data['data'] as Map<String, dynamic>,
      );
    } catch (e) {
      debugPrint('RideRequestService: Error creating coordinate request - $e');
      rethrow;
    }
  }

  /// Get ride request details
  Future<RideRequest> getRequest(int requestId) async {
    try {
      final response = await _apiService.get('/requests/$requestId');

      if (response.data['success'] != true) {
        throw Exception(
          response.data['message'] ?? 'Failed to get ride request',
        );
      }

      return RideRequest.fromJson(
        response.data['data'] as Map<String, dynamic>,
      );
    } catch (e) {
      debugPrint('RideRequestService: Error getting request - $e');
      rethrow;
    }
  }

  /// Cancel a pending ride request
  Future<void> cancelRequest(int requestId) async {
    try {
      final response =
          await _apiService.patch('/requests/$requestId/cancel');

      if (response.data['success'] != true) {
        throw Exception(
          response.data['message'] ?? 'Failed to cancel ride request',
        );
      }
    } catch (e) {
      debugPrint('RideRequestService: Error cancelling request - $e');
      rethrow;
    }
  }

  /// Decline a ride request (driver declines or timeout)
  Future<void> declineRequest(int requestId) async {
    try {
      final response = await _apiService.post('/rides/$requestId/decline');

      // 403 means we're no longer the current driver (request moved on), that's fine
      if (response.data['success'] != true &&
          response.statusCode != 403 &&
          response.statusCode != 400) {
        throw Exception(
          response.data['message'] ?? 'Failed to decline ride request',
        );
      }
    } catch (e) {
      debugPrint('RideRequestService: Error declining request - $e');
      // Don't rethrow — a failed decline shouldn't crash the driver app
    }
  }

  /// Get list of user's ride requests
  Future<List<RideRequest>> getUserRequests({String? status}) async {
    try {
      final response = await _apiService.get(
        '/requests',
        queryParameters: status != null ? {'status': status} : null,
      );

      if (response.data['success'] != true) {
        throw Exception(
          response.data['message'] ?? 'Failed to get ride requests',
        );
      }

      final requestsData = response.data['data'] as List;
      return requestsData
          .map(
            (json) => RideRequest.fromJson(json as Map<String, dynamic>),
          )
          .toList();
    } catch (e) {
      debugPrint('RideRequestService: Error getting requests - $e');
      rethrow;
    }
  }
}
