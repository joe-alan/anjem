import 'dart:io';
import 'package:dio/dio.dart';
import 'package:path/path.dart' as path;
import '../api/api_service.dart';
import '../api/api_exception.dart';
import '../../models/kyc_submission.dart';

class KycService {
  final ApiService _apiService;

  KycService({required ApiService apiService}) : _apiService = apiService;

  /// Get current KYC status
  Future<KycSubmission> getKycStatus() async {
    try {
      print('KYC Service: Getting KYC status from /driver/kyc/status');
      final response = await _apiService.get('/driver/kyc/status');

      print('KYC Service: getKycStatus response - ${response.statusCode}');
      print('KYC Service: getKycStatus data - ${response.data}');

      if (response.data['success'] != true) {
        print('KYC Service: getKycStatus failed - ${response.data['message']}');
        throw ApiException(
          message: response.data['message'] ?? 'Failed to get KYC status',
          statusCode: response.statusCode,
        );
      }

      final kycData = response.data['data'];
      print('KYC Service: KYC status data - $kycData');
      print('KYC Service: is_verified = ${kycData['is_verified']}');
      print('KYC Service: email_verified = ${kycData['email_verified']}');
      print('KYC Service: kyc_submitted = ${kycData['kyc_submitted']}');

      final submission = KycSubmission.fromJson(kycData);
      print('KYC Service: KycSubmission created - isVerified=${submission.isVerified}');
      return submission;
    } on ApiException {
      rethrow;
    } catch (e, stackTrace) {
      print('KYC Service: getKycStatus exception - $e');
      print('KYC Service: Stack trace - $stackTrace');
      throw ApiException(
        message: 'Failed to get KYC status: ${e.toString()}',
        statusCode: null,
      );
    }
  }

  /// Check if student email is available for registration
  Future<bool> checkEmailAvailability(String studentEmail) async {
    try {
      print('KYC Service: Checking email availability for $studentEmail');
      final response = await _apiService.post(
        '/driver/kyc/check-email',
        data: {'student_email': studentEmail},
      );

      print('KYC Service: Email availability response - ${response.data}');

      if (response.data['success'] != true) {
        // If success is false, return the available status anyway
        // (handles cases like invalid domain)
        return response.data['available'] ?? false;
      }

      return response.data['available'] ?? false;
    } on ApiException catch (e) {
      print('KYC Service: ApiException checking email - ${e.message}');
      // On error, assume email is unavailable to be safe
      rethrow;
    } catch (e) {
      print('KYC Service: Error checking email availability - $e');
      throw ApiException(
        message: 'Failed to check email availability: ${e.toString()}',
        statusCode: null,
      );
    }
  }

  /// Submit KYC data with KTM photo
  Future<KycSubmission> submitKyc({
    required String studentEmail,
    required String studentId,
    required String studentName,
    required String phoneNumber,
    required String vehicleType,
    required String vehiclePlate,
    required String vehicleColor,
    required File ktmPhoto,
    required File profilePhoto,
  }) async {
    try {
      print('KYC Service: Preparing form data...');
      print('KYC Service: KTM photo path - ${ktmPhoto.path}');
      print('KYC Service: File exists - ${await ktmPhoto.exists()}');

      // Create MultipartFile for the KTM photo
      MultipartFile ktmPhotoFile;
      try {
        final ktmExt = path.extension(ktmPhoto.path);
        ktmPhotoFile = await MultipartFile.fromFile(
          ktmPhoto.path,
          filename: 'ktm_${DateTime.now().millisecondsSinceEpoch}$ktmExt',
        );
        print('KYC Service: MultipartFile created successfully - ${ktmPhotoFile.filename}');
      } catch (e) {
        print('KYC Service: Error creating MultipartFile - ${e.toString()}');
        throw ApiException(
          message: 'Failed to read KTM photo file: ${e.toString()}',
          statusCode: null,
        );
      }

      // Create MultipartFile for the profile photo
      MultipartFile profilePhotoFile;
      try {
        final profileExt = path.extension(profilePhoto.path);
        profilePhotoFile = await MultipartFile.fromFile(
          profilePhoto.path,
          filename: 'profile_${DateTime.now().millisecondsSinceEpoch}$profileExt',
        );
        print('KYC Service: Profile photo MultipartFile created - ${profilePhotoFile.filename}');
      } catch (e) {
        print('KYC Service: Error creating profile photo MultipartFile - ${e.toString()}');
        throw ApiException(
          message: 'Failed to read profile photo file: ${e.toString()}',
          statusCode: null,
        );
      }

      // Create multipart form data
      final formData = FormData.fromMap({
        'student_email': studentEmail,
        'student_id': studentId,
        'student_name': studentName,
        'phone_number': phoneNumber,
        'vehicle_type': vehicleType,
        'vehicle_plate': vehiclePlate,
        'vehicle_color': vehicleColor,
        'ktm_photo': ktmPhotoFile,
        'profile_photo': profilePhotoFile,
      });

      print('KYC Service: Sending request to /driver/kyc/submit');
      print('KYC Service: Request data - Fields: ${formData.fields.map((e) => e.key).join(", ")}, Files: ${formData.files.map((e) => e.key).join(", ")}');

      final response = await _apiService.post(
        '/driver/kyc/submit',
        data: formData,
      );

      print('KYC Service: Response received - ${response.statusCode}');
      print('KYC Service: Response data type - ${response.data.runtimeType}');
      print('KYC Service: Response data - ${response.data}');

      if (response.data == null) {
        throw ApiException(
          message: 'Server returned empty response',
          statusCode: response.statusCode,
        );
      }

      if (response.data is! Map) {
        print('KYC Service: Response is not a Map! It is: ${response.data.runtimeType}');
        throw ApiException(
          message: 'Invalid response format from server',
          statusCode: response.statusCode,
        );
      }

      final responseData = response.data as Map<String, dynamic>;
      print('KYC Service: Response success field - ${responseData['success']}');

      if (responseData['success'] != true) {
        final errorMsg = responseData['error'] ?? responseData['message'] ?? 'Failed to submit KYC';
        print('KYC Service: Server returned success=false with message: $errorMsg');
        throw ApiException(
          message: errorMsg,
          statusCode: response.statusCode,
        );
      }

      print('KYC Service: Parsing response data...');
      final kycData = responseData['data'];
      print('KYC Service: KYC data - $kycData');
      print('KYC Service: KYC data type - ${kycData.runtimeType}');

      try {
        final submission = KycSubmission.fromJson(kycData);
        print('KYC Service: KycSubmission parsed successfully!');
        return submission;
      } catch (e, stackTrace) {
        print('KYC Service: Error parsing KycSubmission - $e');
        print('KYC Service: Stack trace - $stackTrace');
        throw ApiException(
          message: 'Failed to parse KYC response: $e',
          statusCode: response.statusCode,
        );
      }
    } on ApiException {
      rethrow;
    } on DioException catch (e) {
      print('KYC Service: DioException caught!');
      print('KYC Service: Error type - ${e.type}');
      print('KYC Service: Error message - ${e.message}');
      print('KYC Service: Status code - ${e.response?.statusCode}');
      print('KYC Service: Response data - ${e.response?.data}');
      print('KYC Service: Response headers - ${e.response?.headers}');

      String errorMsg;
      if (e.response?.data != null && e.response!.data is Map) {
        errorMsg = e.response!.data['error'] ??
                   e.response!.data['message'] ??
                   'Failed to submit KYC';

        // Log validation errors if present
        if (e.response!.data['errors'] != null) {
          print('KYC Service: Validation errors - ${e.response!.data['errors']}');
        }
      } else {
        errorMsg = e.message ?? 'Failed to submit KYC';
      }

      throw ApiException(
        message: errorMsg,
        statusCode: e.response?.statusCode,
      );
    } catch (e, stackTrace) {
      print('KYC Service: Unexpected exception - ${e.toString()}');
      print('KYC Service: Stack trace - ${stackTrace.toString()}');
      throw ApiException(
        message: 'Failed to submit KYC: ${e.toString()}',
        statusCode: null,
      );
    }
  }

  /// Revoke KYC data
  Future<void> revokeKyc() async {
    try {
      final response = await _apiService.delete('/driver/kyc/revoke');

      if (response.data['success'] != true) {
        throw ApiException(
          message: response.data['message'] ?? 'Failed to revoke KYC',
          statusCode: response.statusCode,
        );
      }
    } on ApiException {
      rethrow;
    } catch (e) {
      throw ApiException(
        message: 'Failed to revoke KYC: ${e.toString()}',
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
