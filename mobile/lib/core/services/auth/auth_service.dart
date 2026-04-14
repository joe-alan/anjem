import 'package:flutter/foundation.dart';
import 'package:firebase_auth/firebase_auth.dart' as firebase;
import 'package:google_sign_in/google_sign_in.dart';
import '../api/api_service.dart';
import '../api/api_exception.dart';
import '../../models/user.dart';
import '../../config/app_config.dart';

class AuthService {
  final ApiService _apiService;
  final firebase.FirebaseAuth _firebaseAuth;
  final GoogleSignIn _googleSignIn;

  AuthService({
    required ApiService apiService,
    firebase.FirebaseAuth? firebaseAuth,
    GoogleSignIn? googleSignIn,
  })  : _apiService = apiService,
        _firebaseAuth = firebaseAuth ?? firebase.FirebaseAuth.instance,
        _googleSignIn = googleSignIn ?? GoogleSignIn(scopes: ['email']);

  /// Sign in with Google and exchange Firebase token for Sanctum token
  Future<User> signInWithGoogle() async {
    try {
      // 1. Sign in with Google
      final GoogleSignInAccount? googleUser = await _googleSignIn.signIn();

      if (googleUser == null) {
        throw ApiException(
          message: 'Google sign-in cancelled',
          statusCode: null,
        );
      }

      // 2. Get Google authentication credentials
      final GoogleSignInAuthentication googleAuth =
          await googleUser.authentication;

      // 3. Create Firebase credential
      final credential = firebase.GoogleAuthProvider.credential(
        accessToken: googleAuth.accessToken,
        idToken: googleAuth.idToken,
      );

      // 4. Sign in to Firebase
      final firebase.UserCredential userCredential =
          await _firebaseAuth.signInWithCredential(credential);

      if (userCredential.user == null) {
        throw ApiException(
          message: 'Firebase authentication failed',
          statusCode: null,
        );
      }

      // 5. Get Firebase ID token
      final String? idToken = await userCredential.user!.getIdToken();

      if (idToken == null) {
        throw ApiException(
          message: 'Failed to get Firebase token',
          statusCode: null,
        );
      }

      // 6. Exchange Firebase token for Sanctum token with backend
      final response = await _apiService.post(
        '/auth/firebase',
        data: {
          'firebase_token': idToken,
          'device_type': AppConfig.instance.flavorName, // 'rider' or 'driver'
        },
      );

      if (response.data['success'] != true) {
        throw ApiException(
          message: response.data['message'] ?? 'Authentication failed',
          statusCode: response.statusCode,
        );
      }

      // 7. Store Sanctum token
      final String sanctumToken = response.data['token'];
      await _apiService.setToken(sanctumToken);

      // 8. Fetch full user profile (login response is partial — missing phone, etc.)
      return await getCurrentUser();
    } on ApiException {
      rethrow;
    } catch (e) {
      throw ApiException(
        message: 'Failed to sign in: ${e.toString()}',
        statusCode: null,
      );
    }
  }

  /// Sign in with email + review code (Play Store reviewer bypass)
  Future<User> signInWithReviewCode(String email, String code) async {
    try {
      final response = await _apiService.post(
        '/auth/review-login',
        data: {
          'email': email,
          'code': code,
          'device_type': AppConfig.instance.flavorName,
        },
      );

      if (response.data['success'] != true) {
        throw ApiException(
          message: response.data['message'] ?? 'Authentication failed',
          statusCode: response.statusCode,
        );
      }

      final String sanctumToken = response.data['token'];
      await _apiService.setToken(sanctumToken);

      return await getCurrentUser();
    } on ApiException {
      rethrow;
    } catch (e) {
      throw ApiException(
        message: 'Failed to sign in: ${e.toString()}',
        statusCode: null,
      );
    }
  }

  /// Check if user is authenticated
  Future<bool> isAuthenticated() async {
    // Only check Sanctum token - Firebase is just for initial login
    final hasToken = await _apiService.hasToken();
    debugPrint('AuthService: isAuthenticated check - hasToken: $hasToken');
    if (hasToken) {
      final token = await _apiService.getToken();
      debugPrint('AuthService: Token exists (length: ${token?.length ?? 0})');
    }
    return hasToken;
  }

  /// Get current Firebase user
  firebase.User? get currentFirebaseUser => _firebaseAuth.currentUser;

  /// Get current user from backend
  Future<User> getCurrentUser() async {
    try {
      debugPrint('AuthService: Fetching current user from /user endpoint');
      final response = await _apiService.get('/user');

      debugPrint('AuthService: User fetch response - status: ${response.statusCode}');

      if (response.data['success'] != true) {
        throw ApiException(
          message: response.data['message'] ?? 'Failed to get user',
          statusCode: response.statusCode,
        );
      }

      final user = User.fromJson(response.data['data']);
      debugPrint('AuthService: User fetched successfully - id: ${user.id}, email: ${user.email}');
      return user;
    } on ApiException catch (e) {
      debugPrint('AuthService: ApiException fetching user - ${e.message}');
      rethrow;
    } catch (e) {
      debugPrint('AuthService: Exception fetching user - ${e.toString()}');
      throw ApiException(
        message: 'Failed to get current user: ${e.toString()}',
        statusCode: null,
      );
    }
  }

  /// Refresh Sanctum token
  Future<void> refreshToken() async {
    try {
      final firebase.User? firebaseUser = _firebaseAuth.currentUser;

      if (firebaseUser == null) {
        throw ApiException(
          message: 'No Firebase user found',
          statusCode: 401,
        );
      }

      // Get fresh Firebase ID token
      final String? idToken = await firebaseUser.getIdToken(true);

      if (idToken == null) {
        throw ApiException(
          message: 'Failed to refresh Firebase token',
          statusCode: null,
        );
      }

      // Exchange for new Sanctum token
      final response = await _apiService.post(
        '/auth/firebase',
        data: {
          'firebase_token': idToken,
          'device_type': AppConfig.instance.flavorName, // 'rider' or 'driver'
        },
      );

      if (response.data['success'] != true) {
        throw ApiException(
          message: response.data['message'] ?? 'Token refresh failed',
          statusCode: response.statusCode,
        );
      }

      // Store new Sanctum token
      final String sanctumToken = response.data['token'];
      await _apiService.setToken(sanctumToken);
    } on ApiException {
      rethrow;
    } catch (e) {
      throw ApiException(
        message: 'Failed to refresh token: ${e.toString()}',
        statusCode: null,
      );
    }
  }

  /// Update FCM token for push notifications
  Future<void> updateFcmToken(String fcmToken) async {
    try {
      await _apiService.post(
        '/auth/fcm-token',
        data: {'fcm_token': fcmToken},
      );
    } catch (e) {
      // Non-critical, log but don't throw
      debugPrint('Failed to update FCM token: $e');
    }
  }

  /// Sign out
  Future<void> signOut() async {
    try {
      // Call backend logout endpoint
      await _apiService.post('/auth/logout');
    } catch (e) {
      // Continue with logout even if backend call fails
      debugPrint('Backend logout failed: $e');
    } finally {
      // Clear Sanctum token
      await _apiService.clearToken();

      // Sign out from Firebase
      await _firebaseAuth.signOut();

      // Sign out from Google
      await _googleSignIn.signOut();
    }
  }
}
