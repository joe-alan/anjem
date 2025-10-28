import 'dart:io';
import 'package:dio/dio.dart';
import '../api/api_service.dart';
import '../api/api_exception.dart';
import '../../models/kyc_submission.dart';

class KycService {
  final ApiService _apiService;

  KycService({required ApiService apiService}) : _apiService = apiService;

  /// Get current KYC status
  Future<KycSubmission> getKycStatus() async {
    try {
      final response = await _apiService.get('/driver/kyc/status');

      if (response.data['success'] != true) {
        throw ApiException(
          message: response.data['message'] ?? 'Failed to get KYC status',
          statusCode: response.statusCode,
        );
      }

      return KycSubmission.fromJson(response.data['data']);
    } on ApiException {
      rethrow;
    } catch (e) {
      throw ApiException(
        message: 'Failed to get KYC status: ${e.toString()}',
        statusCode: null,
      );
    }
  }

  /// Submit KYC data with KTM photo
  Future<KycSubmission> submitKyc({
    required String studentEmail,
    required String studentId,
    required String studentName,
    required String vehicleType,
    required String vehiclePlate,
    required String vehicleColor,
    required File ktmPhoto,
  }) async {
    try {
      print('KYC Service: Preparing form data...');

      // Create multipart form data
      final formData = FormData.fromMap({
        'student_email': studentEmail,
        'student_id': studentId,
        'student_name': studentName,
        'vehicle_type': vehicleType,
        'vehicle_plate': vehiclePlate,
        'vehicle_color': vehicleColor,
        'ktm_photo': await MultipartFile.fromFile(
          ktmPhoto.path,
          filename: 'ktm_${DateTime.now().millisecondsSinceEpoch}.jpg',
        ),
      });

      print('KYC Service: Sending request to /driver/kyc/submit');

      final response = await _apiService.post(
        '/driver/kyc/submit',
        data: formData,
      );

      print('KYC Service: Response received - ${response.statusCode}');
      print('KYC Service: Response data - ${response.data}');

      if (response.data['success'] != true) {
        final errorMsg = response.data['error'] ?? response.data['message'] ?? 'Failed to submit KYC';
        throw ApiException(
          message: errorMsg,
          statusCode: response.statusCode,
        );
      }

      return KycSubmission.fromJson(response.data['data']);
    } on ApiException {
      rethrow;
    } on DioException catch (e) {
      print('KYC Service: DioException - ${e.message}');
      print('KYC Service: Response data - ${e.response?.data}');

      final errorMsg = e.response?.data?['error'] ??
                       e.response?.data?['message'] ??
                       e.message ??
                       'Failed to submit KYC';

      throw ApiException(
        message: errorMsg,
        statusCode: e.response?.statusCode,
      );
    } catch (e) {
      print('KYC Service: Exception - ${e.toString()}');
      throw ApiException(
        message: 'Failed to submit KYC: ${e.toString()}',
        statusCode: null,
      );
    }
  }

  /// Send verification code to student email
  Future<void> sendVerificationCode(String studentEmail) async {
    try {
      final response = await _apiService.post(
        '/driver/kyc/send-code',
        data: {'student_email': studentEmail},
      );

      if (response.data['success'] != true) {
        throw ApiException(
          message: response.data['message'] ?? 'Failed to send verification code',
          statusCode: response.statusCode,
        );
      }
    } on ApiException {
      rethrow;
    } catch (e) {
      throw ApiException(
        message: 'Failed to send verification code: ${e.toString()}',
        statusCode: null,
      );
    }
  }

  /// Verify email with code
  Future<KycSubmission> verifyEmail({
    required String studentEmail,
    required String code,
  }) async {
    try {
      final response = await _apiService.post(
        '/driver/kyc/verify-email',
        data: {
          'student_email': studentEmail,
          'code': code,
        },
      );

      if (response.data['success'] != true) {
        throw ApiException(
          message: response.data['message'] ?? 'Failed to verify email',
          statusCode: response.statusCode,
        );
      }

      return KycSubmission.fromJson(response.data['data']);
    } on ApiException {
      rethrow;
    } catch (e) {
      throw ApiException(
        message: 'Failed to verify email: ${e.toString()}',
        statusCode: null,
      );
    }
  }
}
