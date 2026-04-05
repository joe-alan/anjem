import 'package:equatable/equatable.dart';

enum KycStatus {
  notSubmitted,
  submitted,
  emailVerified,
  verified,
}

class KycSubmission extends Equatable {
  final bool kycSubmitted;
  final bool emailVerified;
  final bool isVerified;
  final String? studentEmail;
  final String? studentId;
  final String? studentName;
  final String? vehicleType;
  final String? vehiclePlate;
  final String? vehicleColor;
  final String? ktmUrl;
  final String? phoneNumber;
  final String? profilePhotoUrl;
  final String? rejectionReason;
  final String? suspendReason;

  const KycSubmission({
    required this.kycSubmitted,
    required this.emailVerified,
    required this.isVerified,
    this.studentEmail,
    this.studentId,
    this.studentName,
    this.vehicleType,
    this.vehiclePlate,
    this.vehicleColor,
    this.ktmUrl,
    this.phoneNumber,
    this.profilePhotoUrl,
    this.rejectionReason,
    this.suspendReason,
  });

  factory KycSubmission.fromJson(Map<String, dynamic> json) {
    return KycSubmission(
      kycSubmitted: json['kyc_submitted'] ?? false,
      emailVerified: json['email_verified'] ?? false,
      isVerified: json['is_verified'] ?? false,
      studentEmail: json['student_email'] as String?,
      studentId: json['student_id'] as String?,
      studentName: json['student_name'] as String?,
      vehicleType: json['vehicle_type'] as String?,
      vehiclePlate: json['vehicle_plate'] as String?,
      vehicleColor: json['vehicle_color'] as String?,
      ktmUrl: json['ktm_url'] as String?,
      phoneNumber: json['phone_number'] as String?,
      profilePhotoUrl: json['profile_photo_url'] as String?,
      rejectionReason: json['rejection_reason'] as String?,
      suspendReason: json['suspend_reason'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'kyc_submitted': kycSubmitted,
      'email_verified': emailVerified,
      'is_verified': isVerified,
      'student_email': studentEmail,
      'student_id': studentId,
      'student_name': studentName,
      'vehicle_type': vehicleType,
      'vehicle_plate': vehiclePlate,
      'vehicle_color': vehicleColor,
      'ktm_url': ktmUrl,
      'phone_number': phoneNumber,
      'profile_photo_url': profilePhotoUrl,
      'rejection_reason': rejectionReason,
      'suspend_reason': suspendReason,
    };
  }

  KycStatus get status {
    if (isVerified) return KycStatus.verified;
    if (emailVerified) return KycStatus.emailVerified;
    if (kycSubmitted) return KycStatus.submitted;
    return KycStatus.notSubmitted;
  }

  @override
  List<Object?> get props => [
        kycSubmitted,
        emailVerified,
        isVerified,
        studentEmail,
        studentId,
        studentName,
        vehicleType,
        vehiclePlate,
        vehicleColor,
        ktmUrl,
        phoneNumber,
        profilePhotoUrl,
        rejectionReason,
        suspendReason,
      ];
}
