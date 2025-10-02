import 'package:dio/dio.dart';

class ApiException implements Exception {
  final String message;
  final int? statusCode;
  final Map<String, dynamic>? errors;
  final DioExceptionType? type;

  ApiException({
    required this.message,
    this.statusCode,
    this.errors,
    this.type,
  });

  factory ApiException.fromDioError(DioException error) {
    String message;
    int? statusCode;
    Map<String, dynamic>? errors;

    switch (error.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        message = 'Connection timeout. Please check your internet connection.';
        break;

      case DioExceptionType.badResponse:
        statusCode = error.response?.statusCode;
        final data = error.response?.data;

        if (data is Map<String, dynamic>) {
          message = data['message'] ?? _getDefaultMessage(statusCode);
          errors = data['errors'];
        } else {
          message = _getDefaultMessage(statusCode);
        }
        break;

      case DioExceptionType.cancel:
        message = 'Request cancelled';
        break;

      case DioExceptionType.connectionError:
        message = 'No internet connection';
        break;

      case DioExceptionType.badCertificate:
        message = 'Security certificate error';
        break;

      case DioExceptionType.unknown:
      default:
        message = 'An unexpected error occurred';
    }

    return ApiException(
      message: message,
      statusCode: statusCode,
      errors: errors,
      type: error.type,
    );
  }

  static String _getDefaultMessage(int? statusCode) {
    switch (statusCode) {
      case 400:
        return 'Bad request';
      case 401:
        return 'Unauthorized. Please sign in again.';
      case 403:
        return 'You don\'t have permission to perform this action';
      case 404:
        return 'Resource not found';
      case 422:
        return 'Invalid data provided';
      case 429:
        return 'Too many requests. Please try again later.';
      case 500:
        return 'Server error. Please try again later.';
      case 503:
        return 'Service temporarily unavailable';
      default:
        return 'An error occurred';
    }
  }

  String get userFriendlyMessage {
    return message;
  }

  bool get isNetworkError {
    return type == DioExceptionType.connectionError ||
        type == DioExceptionType.connectionTimeout;
  }

  bool get isAuthError {
    return statusCode == 401 || statusCode == 403;
  }

  bool get isValidationError {
    return statusCode == 422 && errors != null;
  }

  @override
  String toString() {
    return 'ApiException(message: $message, statusCode: $statusCode, errors: $errors)';
  }
}
