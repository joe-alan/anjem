import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../../config/app_config.dart';
import 'api_exception.dart';

class ApiService {
  late final Dio _dio;
  final FlutterSecureStorage _storage;

  /// Called when a 401 response cannot be recovered (token refresh failed).
  /// Set by authStateProvider after creation to avoid a circular import.
  /// Typically points to `authStateProvider.notifier.signOut()`.
  void Function()? onUnauthorized;

  /// Guard that prevents the 401 interceptor from recursively calling itself
  /// when the token-refresh request itself returns 401.
  bool _isRefreshing = false;

  static const String _tokenKey = 'sanctum_token';

  ApiService({FlutterSecureStorage? storage, this.onUnauthorized})
      : _storage = storage ?? const FlutterSecureStorage() {
    _dio = Dio(BaseOptions(
      baseUrl: AppConfig.instance.apiBaseUrl,
      connectTimeout: const Duration(seconds: 30),
      receiveTimeout: const Duration(seconds: 30),
      headers: {
        'Accept': 'application/json',
        // Don't set Content-Type here - let Dio automatically set it based on the request data
        // (application/json for JSON, multipart/form-data for FormData, etc.)
      },
    ));

    _dio.interceptors.add(_createInterceptor());
  }

  Interceptor _createInterceptor() {
    return InterceptorsWrapper(
      onRequest: (options, handler) async {
        // Add bearer token to all requests
        final token = await _storage.read(key: _tokenKey);
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        return handler.next(options);
      },
      onResponse: (response, handler) {
        return handler.next(response);
      },
      onError: (error, handler) async {
        final path = error.requestOptions.path;

        // Never attempt a refresh or trigger sign-out for auth endpoints.
        //
        // • POST /auth/logout  — a 401 here just means the token was already
        //   revoked server-side (e.g. session replaced).  It is expected and
        //   _authService.signOut() already swallows it.  Without this guard
        //   the interceptor calls onUnauthorized → signOut → POST /auth/logout
        //   → 401 → onUnauthorized → ... (infinite loop / splash flash).
        //
        // • POST /auth/refresh — a 401 here is handled by
        //   _attemptTokenRefresh()'s catch block; re-entering the interceptor
        //   would cause the same infinite recursion described above.
        final isAuthEndpoint =
            path.contains('/auth/logout') || path.contains('/auth/refresh');

        if (error.response?.statusCode == 401 &&
            !_isRefreshing &&
            !isAuthEndpoint) {
          _isRefreshing = true;
          try {
            final refreshed = await _attemptTokenRefresh();

            if (refreshed) {
              // Retry the original request with the new token.
              try {
                final retryResponse = await _dio.fetch(error.requestOptions);
                return handler.resolve(retryResponse);
              } catch (e) {
                return handler.next(error);
              }
            }
          } finally {
            _isRefreshing = false;
          }

          // Refresh failed — token is definitively invalid.
          // Notify the app so it can sign the user out.
          onUnauthorized?.call();
        }

        return handler.next(error);
      },
    );
  }

  Future<bool> _attemptTokenRefresh() async {
    try {
      final response = await _dio.post('/auth/refresh');
      if (response.statusCode == 200 && response.data['token'] != null) {
        await _storage.write(key: _tokenKey, value: response.data['token']);
        return true;
      }
    } catch (e) {
      // Refresh failed, user needs to re-authenticate
      await _storage.delete(key: _tokenKey);
    }
    return false;
  }

  // Generic HTTP methods
  Future<Response<T>> get<T>(
    String path, {
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    try {
      return await _dio.get<T>(
        path,
        queryParameters: queryParameters,
        options: options,
      );
    } on DioException catch (e) {
      throw ApiException.fromDioError(e);
    }
  }

  Future<Response<T>> post<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    try {
      return await _dio.post<T>(
        path,
        data: data,
        queryParameters: queryParameters,
        options: options,
      );
    } on DioException catch (e) {
      throw ApiException.fromDioError(e);
    }
  }

  Future<Response<T>> patch<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    try {
      return await _dio.patch<T>(
        path,
        data: data,
        queryParameters: queryParameters,
        options: options,
      );
    } on DioException catch (e) {
      throw ApiException.fromDioError(e);
    }
  }

  Future<Response<T>> put<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    try {
      return await _dio.put<T>(
        path,
        data: data,
        queryParameters: queryParameters,
        options: options,
      );
    } on DioException catch (e) {
      throw ApiException.fromDioError(e);
    }
  }

  Future<Response<T>> delete<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    try {
      return await _dio.delete<T>(
        path,
        data: data,
        queryParameters: queryParameters,
        options: options,
      );
    } on DioException catch (e) {
      throw ApiException.fromDioError(e);
    }
  }

  // Token management
  Future<void> setToken(String token) async {
    await _storage.write(key: _tokenKey, value: token);
  }

  Future<String?> getToken() async {
    return await _storage.read(key: _tokenKey);
  }

  Future<void> clearToken() async {
    await _storage.delete(key: _tokenKey);
  }

  Future<bool> hasToken() async {
    final token = await getToken();
    return token != null && token.isNotEmpty;
  }

  // Session management
  Future<Map<String, dynamic>> getSessionState() async {
    try {
      final response = await get<Map<String, dynamic>>('/session/resume');
      if (response.data != null && response.data!['success'] == true) {
        return response.data!['data'] as Map<String, dynamic>;
      }
      throw ApiException(
        message: 'Failed to get session state',
        statusCode: response.statusCode,
      );
    } on ApiException {
      rethrow;
    } catch (e) {
      throw ApiException(
        message: 'Failed to get session state: ${e.toString()}',
      );
    }
  }
}
