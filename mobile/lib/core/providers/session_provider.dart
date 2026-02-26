import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../services/session/session_service.dart';
import '../models/session_state.dart';
import 'api_provider.dart';

// Session Service Provider
final sessionServiceProvider = Provider<SessionService>((ref) {
  final apiService = ref.watch(apiServiceProvider);
  return SessionService(apiService: apiService);
});

// Session State Provider
final sessionStateProvider =
    StateNotifierProvider<SessionStateNotifier, SessionStateData>(
  (ref) {
    final sessionService = ref.watch(sessionServiceProvider);
    return SessionStateNotifier(sessionService);
  },
);

// Session State Data (wrapper for loading/error states)
class SessionStateData {
  final bool isLoading;
  final SessionState? sessionState;
  final String? error;
  final DateTime? lastChecked;

  const SessionStateData({
    this.isLoading = false,
    this.sessionState,
    this.error,
    this.lastChecked,
  });

  SessionStateData copyWith({
    bool? isLoading,
    SessionState? sessionState,
    String? error,
    DateTime? lastChecked,
  }) {
    return SessionStateData(
      isLoading: isLoading ?? this.isLoading,
      sessionState: sessionState ?? this.sessionState,
      error: error,
      lastChecked: lastChecked ?? this.lastChecked,
    );
  }

  bool get hasActiveSession =>
      sessionState != null && !sessionState!.isIdle;
  bool get needsToResumeRide =>
      sessionState != null && sessionState!.needsToResumeRide;
  bool get isWaitingForDriver =>
      sessionState != null && sessionState!.isWaitingForDriver;
}

// Session State Notifier
class SessionStateNotifier extends StateNotifier<SessionStateData> {
  final SessionService _sessionService;

  SessionStateNotifier(this._sessionService)
      : super(const SessionStateData());

  /// Check session state (typically called on app launch)
  ///
  /// Returns the session state, or null if there's an error
  Future<SessionState?> checkSession() async {
    state = state.copyWith(isLoading: true, error: null);

    try {
      final sessionState = await _sessionService.getSessionState();
      state = state.copyWith(
        isLoading: false,
        sessionState: sessionState,
        lastChecked: DateTime.now(),
      );
      return sessionState;
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.toString(),
        lastChecked: DateTime.now(),
      );
      return null;
    }
  }

  /// Refresh session state (can be called periodically or on app resume)
  ///
  /// Returns the session state, or null if there's an error
  Future<SessionState?> refreshSession() async {
    return await checkSession();
  }

  /// Clear session state (called on logout or when session becomes invalid)
  void clearSession() {
    state = const SessionStateData();
  }

  /// Update session state manually (useful when state changes through other means)
  void updateSessionState(SessionState sessionState) {
    state = state.copyWith(
      sessionState: sessionState,
      lastChecked: DateTime.now(),
    );
  }

  /// Check if we should refresh the session (e.g., if it's been > 5 minutes)
  bool shouldRefresh() {
    if (state.lastChecked == null) return true;
    final timeSinceLastCheck = DateTime.now().difference(state.lastChecked!);
    return timeSinceLastCheck.inMinutes > 5;
  }
}
