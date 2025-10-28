import 'dart:io';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../services/kyc/kyc_service.dart';
import '../services/api/api_exception.dart';
import '../models/kyc_submission.dart';
import 'api_provider.dart';

// KYC Service Provider
final kycServiceProvider = Provider<KycService>((ref) {
  final apiService = ref.watch(apiServiceProvider);
  return KycService(apiService: apiService);
});

// KYC State Provider
final kycStateProvider = StateNotifierProvider<KycStateNotifier, KycState>(
  (ref) {
    final kycService = ref.watch(kycServiceProvider);
    return KycStateNotifier(kycService);
  },
);

// KYC State
class KycState {
  final bool isLoading;
  final KycSubmission? kycSubmission;
  final String? error;
  final String? successMessage;

  const KycState({
    this.isLoading = false,
    this.kycSubmission,
    this.error,
    this.successMessage,
  });

  KycState copyWith({
    bool? isLoading,
    KycSubmission? kycSubmission,
    String? error,
    String? successMessage,
  }) {
    return KycState(
      isLoading: isLoading ?? this.isLoading,
      kycSubmission: kycSubmission ?? this.kycSubmission,
      error: error,
      successMessage: successMessage,
    );
  }
}

// KYC State Notifier
class KycStateNotifier extends StateNotifier<KycState> {
  final KycService _kycService;

  KycStateNotifier(this._kycService) : super(const KycState()) {
    _loadKycStatus();
  }

  Future<void> _loadKycStatus() async {
    state = state.copyWith(isLoading: true);

    try {
      final kycSubmission = await _kycService.getKycStatus();
      state = state.copyWith(
        isLoading: false,
        kycSubmission: kycSubmission,
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
        error: 'Failed to load KYC status',
      );
    }
  }

  Future<bool> submitKyc({
    required String studentEmail,
    required String studentId,
    required String studentName,
    required String vehicleType,
    required String vehiclePlate,
    required String vehicleColor,
    required File ktmPhoto,
  }) async {
    state = state.copyWith(isLoading: true, error: null, successMessage: null);

    try {
      final kycSubmission = await _kycService.submitKyc(
        studentEmail: studentEmail,
        studentId: studentId,
        studentName: studentName,
        vehicleType: vehicleType,
        vehiclePlate: vehiclePlate,
        vehicleColor: vehicleColor,
        ktmPhoto: ktmPhoto,
      );

      state = state.copyWith(
        isLoading: false,
        kycSubmission: kycSubmission,
        successMessage: 'KYC submitted successfully! Please verify your email.',
        error: null,
      );

      return true;
    } on ApiException catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.userFriendlyMessage,
      );
      return false;
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: 'Failed to submit KYC: ${e.toString()}',
      );
      return false;
    }
  }

  Future<bool> sendVerificationCode(String studentEmail) async {
    state = state.copyWith(isLoading: true, error: null, successMessage: null);

    try {
      await _kycService.sendVerificationCode(studentEmail);

      state = state.copyWith(
        isLoading: false,
        successMessage: 'Verification code sent to $studentEmail',
        error: null,
      );

      return true;
    } on ApiException catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.userFriendlyMessage,
      );
      return false;
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: 'Failed to send verification code',
      );
      return false;
    }
  }

  Future<bool> verifyEmail({
    required String studentEmail,
    required String code,
  }) async {
    state = state.copyWith(isLoading: true, error: null, successMessage: null);

    try {
      final kycSubmission = await _kycService.verifyEmail(
        studentEmail: studentEmail,
        code: code,
      );

      state = state.copyWith(
        isLoading: false,
        kycSubmission: kycSubmission,
        successMessage: 'Email verified successfully! You can now go online.',
        error: null,
      );

      return true;
    } on ApiException catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.userFriendlyMessage,
      );
      return false;
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: 'Failed to verify email',
      );
      return false;
    }
  }

  Future<void> refreshKycStatus() async {
    await _loadKycStatus();
  }

  void clearError() {
    state = state.copyWith(error: null);
  }

  void clearSuccessMessage() {
    state = state.copyWith(successMessage: null);
  }
}

// Convenience provider for KYC status
final kycStatusProvider = Provider<KycSubmission?>((ref) {
  return ref.watch(kycStateProvider).kycSubmission;
});

// Convenience provider to check if driver is verified
final isDriverVerifiedProvider = Provider<bool>((ref) {
  final kycSubmission = ref.watch(kycStatusProvider);
  return kycSubmission?.isVerified ?? false;
});
