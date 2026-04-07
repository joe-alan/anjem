import 'package:flutter/foundation.dart';
import '../api/api_service.dart';
import '../api/api_exception.dart';
import '../../models/session_state.dart';

class SessionService {
  final ApiService _apiService;

  SessionService({required ApiService apiService}) : _apiService = apiService;

  /// Get the current session state for the authenticated user
  ///
  /// Returns the session state which includes:
  /// - Current state (idle, request_pending, request_matched, ride_active)
  /// - Active ride (if any)
  /// - Active ride request (if any)
  /// - Driver context
  ///
  /// Throws [ApiException] if the request fails or user is not authenticated
  Future<SessionState> getSessionState() async {
    try {
      final data = await _apiService.getSessionState();
      debugPrint('DEBUG SessionService: Received data: $data');
      return SessionState.fromJson(data);
    } on ApiException catch (e) {
      debugPrint('ERROR SessionService: ApiException - ${e.message}, code: ${e.statusCode}');

      // If 401, user needs to re-authenticate
      if (e.statusCode == 401) {
        throw ApiException(
          message: 'Authentication required',
          statusCode: 401,
        );
      }

      // If rate limited, return idle state instead of crashing
      // This prevents the app from crashing when backend is rate-limited
      if (e.isRateLimitError) {
        debugPrint('WARN SessionService: Rate limit hit, returning idle state');
        return SessionState(
          state: SessionStateType.idle,
          driverContext: const DriverContext(
            isDriver: false,
            isOnline: false,
          ),
        );
      }

      rethrow;
    } catch (e, stackTrace) {
      debugPrint('ERROR SessionService: Exception - $e');
      debugPrint('Stack trace: $stackTrace');
      throw ApiException(
        message: 'Failed to get session state: ${e.toString()}',
      );
    }
  }

  /// Check if user has an active session (ride or request)
  Future<bool> hasActiveSession() async {
    try {
      final sessionState = await getSessionState();
      return !sessionState.isIdle;
    } catch (e) {
      // If we can't get session state, assume no active session
      return false;
    }
  }
}
