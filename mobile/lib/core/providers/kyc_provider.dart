import 'package:flutter/foundation.dart';
import 'dart:io';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../services/kyc/kyc_service.dart';
import '../services/api/api_exception.dart';
import '../models/kyc_submission.dart';
import 'api_provider.dart';
import 'auth_provider.dart';

// KYC Service Provider
final kycServiceProvider = Provider<KycService>((ref) {
  final apiService = ref.watch(apiServiceProvider);
  return KycService(apiService: apiService);
});

// KYC State Provider
final kycStateProvider = StateNotifierProvider<KycStateNotifier, KycState>(
  (ref) {
    final kycService = ref.watch(kycServiceProvider);
    return KycStateNotifier(kycService, ref);
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
  final Ref _ref;

  KycStateNotifier(this._kycService, this._ref) : super(const KycState()) {
    // Listen to auth state changes
    _ref.listen(authStateProvider, (previous, next) {
      debugPrint('KYC Provider: Auth state changed - wasAuth=${previous?.isAuthenticated}, isAuth=${next.isAuthenticated}');

      // If user just logged in (transitioned from not authenticated to authenticated)
      if (previous != null && !previous.isAuthenticated && next.isAuthenticated) {
        debugPrint('KYC Provider: User just logged in, loading KYC status');
        _loadKycStatus();
      }

      // If user logged out, clear KYC state
      if (previous != null && previous.isAuthenticated && !next.isAuthenticated) {
        debugPrint('KYC Provider: User logged out, clearing KYC state');
        clearKycState();
      }
    });

    // Check if user is already authenticated on initialization
    final isAuthenticated = _ref.read(authStateProvider).isAuthenticated;
    debugPrint('KYC Provider: Initializing - isAuthenticated=$isAuthenticated');

    if (isAuthenticated) {
      debugPrint('KYC Provider: User is authenticated, loading KYC status...');
      _loadKycStatus();
    } else {
      debugPrint('KYC Provider: User not authenticated, skipping KYC status load');
    }
  }

  Future<void> _loadKycStatus() async {
    debugPrint('KYC Provider: Loading KYC status...');
    state = state.copyWith(isLoading: true);

    try {
      final kycSubmission = await _kycService.getKycStatus();
      debugPrint('KYC Provider: KYC status loaded - isVerified=${kycSubmission.isVerified}');
      debugPrint('KYC Provider: Full submission - $kycSubmission');

      state = state.copyWith(
        isLoading: false,
        kycSubmission: kycSubmission,
        error: null,
      );

      debugPrint('KYC Provider: State updated - kycSubmission=${state.kycSubmission?.isVerified}');
    } on ApiException catch (e) {
      debugPrint('KYC Provider: API Exception - ${e.message}');
      state = state.copyWith(
        isLoading: false,
        error: e.userFriendlyMessage,
      );
    } catch (e, stackTrace) {
      debugPrint('KYC Provider: Exception - $e');
      debugPrint('KYC Provider: Stack trace - $stackTrace');
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
    required String phoneNumber,
    required String vehicleType,
    required String vehiclePlate,
    required String vehicleColor,
    required File ktmPhoto,
    File? profilePhoto,
  }) async {
    debugPrint('KYC Provider: submitKyc called with file path: ${ktmPhoto.path}');
    state = state.copyWith(isLoading: true, error: null, successMessage: null);

    try {
      final kycSubmission = await _kycService.submitKyc(
        studentEmail: studentEmail,
        studentId: studentId,
        studentName: studentName,
        phoneNumber: phoneNumber,
        vehicleType: vehicleType,
        vehiclePlate: vehiclePlate,
        vehicleColor: vehicleColor,
        ktmPhoto: ktmPhoto,
        profilePhoto: profilePhoto,
      );

      state = state.copyWith(
        isLoading: false,
        kycSubmission: kycSubmission,
        successMessage: 'KYC submitted successfully! Please verify your email.',
        error: null,
      );

      // Clear draft data after successful submission
      await clearDraft();

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

  Future<bool> revokeKyc() async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      await _kycService.revokeKyc();
      state = const KycState();
      return true;
    } on ApiException catch (e) {
      state = state.copyWith(isLoading: false, error: e.userFriendlyMessage);
      return false;
    } catch (e) {
      state = state.copyWith(isLoading: false, error: 'Failed to revoke KYC');
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

  void clearKycState() {
    debugPrint('KYC Provider: Clearing KYC state');
    state = const KycState();
  }

  // ========== Draft Management Methods ==========

  /// Save KYC form draft data to SharedPreferences
  Future<void> saveDraft({
    String? studentEmail,
    String? studentId,
    String? studentName,
    String? phoneNumber,
    String? vehicleType,
    String? vehiclePlate,
    String? vehicleColor,
    int? currentPage,
  }) async {
    try {
      final prefs = await SharedPreferences.getInstance();

      if (studentEmail != null) await prefs.setString('kyc_draft_email', studentEmail);
      if (studentId != null) await prefs.setString('kyc_draft_student_id', studentId);
      if (studentName != null) await prefs.setString('kyc_draft_name', studentName);
      if (phoneNumber != null) await prefs.setString('kyc_draft_phone', phoneNumber);
      if (vehicleType != null) await prefs.setString('kyc_draft_vehicle_type', vehicleType);
      if (vehiclePlate != null) await prefs.setString('kyc_draft_vehicle_plate', vehiclePlate);
      if (vehicleColor != null) await prefs.setString('kyc_draft_vehicle_color', vehicleColor);
      if (currentPage != null) await prefs.setInt('kyc_draft_current_page', currentPage);
      await prefs.setString('kyc_draft_saved_at', DateTime.now().toIso8601String());

      debugPrint('KYC Provider: Draft saved successfully');
    } catch (e) {
      debugPrint('KYC Provider: Error saving draft - $e');
    }
  }

  /// Load KYC form draft data from SharedPreferences
  Future<Map<String, dynamic>?> loadDraft() async {
    try {
      final prefs = await SharedPreferences.getInstance();

      if (!prefs.containsKey('kyc_draft_email')) {
        debugPrint('KYC Provider: No draft data found');
        return null;
      }

      final draft = {
        'student_email': prefs.getString('kyc_draft_email'),
        'student_id': prefs.getString('kyc_draft_student_id'),
        'student_name': prefs.getString('kyc_draft_name'),
        'phone_number': prefs.getString('kyc_draft_phone'),
        'vehicle_type': prefs.getString('kyc_draft_vehicle_type'),
        'vehicle_plate': prefs.getString('kyc_draft_vehicle_plate'),
        'vehicle_color': prefs.getString('kyc_draft_vehicle_color'),
        'current_page': prefs.getInt('kyc_draft_current_page') ?? 0,
        'saved_at': prefs.getString('kyc_draft_saved_at'),
      };

      debugPrint('KYC Provider: Draft loaded - saved at ${draft['saved_at']}');
      return draft;
    } catch (e) {
      debugPrint('KYC Provider: Error loading draft - $e');
      return null;
    }
  }

  /// Clear KYC draft data after successful submission
  Future<void> clearDraft() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('kyc_draft_email');
      await prefs.remove('kyc_draft_student_id');
      await prefs.remove('kyc_draft_name');
      await prefs.remove('kyc_draft_phone');
      await prefs.remove('kyc_draft_vehicle_type');
      await prefs.remove('kyc_draft_vehicle_plate');
      await prefs.remove('kyc_draft_vehicle_color');
      await prefs.remove('kyc_draft_current_page');
      await prefs.remove('kyc_draft_saved_at');

      debugPrint('KYC Provider: Draft cleared successfully');
    } catch (e) {
      debugPrint('KYC Provider: Error clearing draft - $e');
    }
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
