import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../services/auth/auth_service.dart';
import '../services/api/api_exception.dart';
import '../models/user.dart';
import '../services/websocket/websocket_service.dart';
import 'api_provider.dart';

// Auth Service Provider
final authServiceProvider = Provider<AuthService>((ref) {
  final apiService = ref.watch(apiServiceProvider);
  return AuthService(apiService: apiService);
});

// WebSocket Service Provider
final websocketServiceProvider = Provider<WebSocketService>((ref) {
  final apiService = ref.watch(apiServiceProvider);
  return WebSocketService(apiService: apiService);
});

// Auth State Provider
final authStateProvider = StateNotifierProvider<AuthStateNotifier, AuthState>(
  (ref) {
    final authService = ref.watch(authServiceProvider);
    final wsService = ref.watch(websocketServiceProvider);
    final notifier = AuthStateNotifier(authService, wsService);

    // Wire global 401 handler: when a token is invalidated mid-session the
    // Dio interceptor calls this, which logs the user out automatically.
    // Done here (not in api_provider) to avoid a circular import.
    final apiService = ref.read(apiServiceProvider);
    apiService.onUnauthorized = notifier.signOut;

    // Clear the callback on disposal so a rebuilt provider doesn't call
    // signOut on an already-disposed notifier.
    ref.onDispose(() {
      apiService.onUnauthorized = null;
    });

    return notifier;
  },
);

// Auth State
class AuthState {
  final bool isLoading;
  final bool isAuthenticated;
  final User? user;
  final String? error;

  const AuthState({
    this.isLoading = false,
    this.isAuthenticated = false,
    this.user,
    this.error,
  });

  AuthState copyWith({
    bool? isLoading,
    bool? isAuthenticated,
    User? user,
    String? error,
  }) {
    return AuthState(
      isLoading: isLoading ?? this.isLoading,
      isAuthenticated: isAuthenticated ?? this.isAuthenticated,
      user: user ?? this.user,
      error: error,
    );
  }
}

// Auth State Notifier
class AuthStateNotifier extends StateNotifier<AuthState> {
  final AuthService _authService;
  final WebSocketService _wsService;

  AuthStateNotifier(this._authService, this._wsService) : super(const AuthState()) {
    _checkAuthStatus();
  }

  Future<void> _checkAuthStatus() async {
    state = state.copyWith(isLoading: true);

    try {
      final isAuthenticated = await _authService.isAuthenticated();

      if (isAuthenticated) {
        // Token exists, try to get user data
        try {
          final user = await _authService.getCurrentUser();
          // Initialize WebSocket before setting isAuthenticated — providers that
          // watch auth state subscribe to WS channels on first build, so the
          // connection must be ready before the home screen is rendered.
          debugPrint('AuthProvider: Calling _initializeWebSocket()');
          await _initializeWebSocket();
          debugPrint('AuthProvider: _initializeWebSocket() completed');
          state = state.copyWith(
            isLoading: false,
            isAuthenticated: true,
            user: user,
            error: null,
          );
        } catch (e) {
          // If user fetch fails with 401, token is expired - log them out
          debugPrint('Failed to fetch user data: $e');

          // Check if it's an auth error (401)
          if (e is ApiException && e.statusCode == 401) {
            debugPrint('Token expired or invalid - clearing auth state');
            await _authService.signOut();
            state = state.copyWith(
              isLoading: false,
              isAuthenticated: false,
              user: null,
              error: null,
            );
          } else {
            // Other errors (network, etc) - keep them logged in
            state = state.copyWith(
              isLoading: false,
              isAuthenticated: true,
              user: null,
              error: null,
            );
          }
        }
      } else {
        state = state.copyWith(
          isLoading: false,
          isAuthenticated: false,
          user: null,
          error: null,
        );
      }
    } catch (e) {
      debugPrint('Auth check failed: $e');
      // Check if we have a token even if auth check failed
      final hasToken = await _authService.isAuthenticated();
      state = state.copyWith(
        isLoading: false,
        isAuthenticated: hasToken,
        user: null,
        error: null,
      );
    }
  }

  Future<void> signInWithGoogle() async {
    state = state.copyWith(isLoading: true, error: null);

    try {
      final user = await _authService.signInWithGoogle();

      // Initialize WebSocket before setting isAuthenticated for the same
      // reason as _checkAuthStatus — channels must exist before first build.
      await _initializeWebSocket();

      state = state.copyWith(
        isLoading: false,
        isAuthenticated: true,
        user: user,
        error: null,
      );
    } on ApiException catch (e) {
      state = state.copyWith(
        isLoading: false,
        isAuthenticated: false,
        user: null,
        error: e.userFriendlyMessage,
      );
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        isAuthenticated: false,
        user: null,
        error: 'An unexpected error occurred during sign in',
      );
    }
  }

  Future<void> signInWithReviewCode(String email, String code) async {
    state = state.copyWith(isLoading: true, error: null);

    try {
      final user = await _authService.signInWithReviewCode(email, code);
      await _initializeWebSocket();

      state = state.copyWith(
        isLoading: false,
        isAuthenticated: true,
        user: user,
        error: null,
      );
    } on ApiException catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.userFriendlyMessage,
      );
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: 'An unexpected error occurred during sign in',
      );
    }
  }

  Future<void> signOut() async {
    state = state.copyWith(isLoading: true);

    try {
      // Disconnect WebSocket
      await _wsService.disconnect();

      await _authService.signOut();

      state = const AuthState(
        isLoading: false,
        isAuthenticated: false,
        user: null,
        error: null,
      );
    } catch (e) {
      // Even if sign out fails, clear local state
      state = const AuthState(
        isLoading: false,
        isAuthenticated: false,
        user: null,
        error: null,
      );
    }
  }

  Future<void> refreshUser() async {
    if (!state.isAuthenticated) return;

    try {
      final user = await _authService.getCurrentUser();
      state = state.copyWith(user: user);
    } catch (e) {
      // Silently fail, user data will be stale
      debugPrint('Failed to refresh user: $e');
    }
  }

  void updateUserInState(User user) {
    state = state.copyWith(user: user);
  }

  Future<void> deleteAccount() async {
    await _wsService.disconnect();
    await _authService.signOut();
    state = const AuthState(
      isLoading: false,
      isAuthenticated: false,
      user: null,
      error: null,
    );
  }

  void clearError() {
    state = state.copyWith(error: null);
  }

  Future<void> _initializeWebSocket() async {
    debugPrint('======================================');
    debugPrint('WEBSOCKET INIT START - THIS MUST PRINT!!!');
    debugPrint('======================================');

    try {
      debugPrint('Step 1: About to call _wsService.initialize()');
      await _wsService.initialize();

      debugPrint('Step 2: About to call _wsService.connect()');
      await _wsService.connect();

      debugPrint('Step 3: WebSocket initialized and connected successfully!');
    } catch (e, stackTrace) {
      debugPrint('WEBSOCKET ERROR: $e');
      debugPrint('STACK TRACE: $stackTrace');
      // Don't throw - WebSocket is not critical for initial app function
    }

    debugPrint('======================================');
    debugPrint('WEBSOCKET INIT END');
    debugPrint('======================================');
  }
}

// Convenience provider for current user
final currentUserProvider = Provider<User?>((ref) {
  return ref.watch(authStateProvider).user;
});

// Convenience provider for auth status
final isAuthenticatedProvider = Provider<bool>((ref) {
  return ref.watch(authStateProvider).isAuthenticated;
});
