import 'package:equatable/equatable.dart';
import 'driver_profile.dart';

enum UserRole { rider, driver, both }

class User extends Equatable {
  final int id;
  final String firebaseUid;
  final String name;
  final String email;
  final String? phone;
  final String? profilePicture;
  final UserRole role;
  final DriverProfile? driverProfile;
  final DateTime createdAt;
  final DateTime updatedAt;

  const User({
    required this.id,
    required this.firebaseUid,
    required this.name,
    required this.email,
    this.phone,
    this.profilePicture,
    required this.role,
    this.driverProfile,
    required this.createdAt,
    required this.updatedAt,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'] as int,
      firebaseUid: json['firebase_uid'] as String,
      name: json['name'] as String,
      email: json['email'] as String,
      phone: json['phone'] as String?,
      profilePicture: json['profile_picture'] as String?,
      role: _parseRole((json['user_type'] ?? json['role']) as String),
      driverProfile: json['driver_profile'] != null
          ? DriverProfile.fromJson(json['driver_profile'] as Map<String, dynamic>)
          : null,
      createdAt: json['created_at'] != null
          ? DateTime.parse(json['created_at'] as String)
          : DateTime.now(),
      updatedAt: json['updated_at'] != null
          ? DateTime.parse(json['updated_at'] as String)
          : DateTime.now(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'firebase_uid': firebaseUid,
      'name': name,
      'email': email,
      'phone': phone,
      'profile_picture': profilePicture,
      'role': _roleToString(role),
      'driver_profile': driverProfile?.toJson(),
      'created_at': createdAt.toIso8601String(),
      'updated_at': updatedAt.toIso8601String(),
    };
  }

  static UserRole _parseRole(String roleString) {
    switch (roleString.toLowerCase()) {
      case 'driver':
        return UserRole.driver;
      case 'both':
        return UserRole.both;
      case 'rider':
      default:
        return UserRole.rider;
    }
  }

  static String _roleToString(UserRole role) {
    switch (role) {
      case UserRole.rider:
        return 'rider';
      case UserRole.driver:
        return 'driver';
      case UserRole.both:
        return 'both';
    }
  }

  bool get isDriver => role == UserRole.driver || role == UserRole.both;
  bool get isRider => role == UserRole.rider || role == UserRole.both;

  // Convenience getters for UI
  String? get avatarUrl => profilePicture;
  double? get rating => driverProfile?.rating;
  int? get totalRides => driverProfile?.totalRides;

  User copyWith({
    int? id,
    String? firebaseUid,
    String? name,
    String? email,
    String? phone,
    String? profilePicture,
    UserRole? role,
    DriverProfile? driverProfile,
    DateTime? createdAt,
    DateTime? updatedAt,
  }) {
    return User(
      id: id ?? this.id,
      firebaseUid: firebaseUid ?? this.firebaseUid,
      name: name ?? this.name,
      email: email ?? this.email,
      phone: phone ?? this.phone,
      profilePicture: profilePicture ?? this.profilePicture,
      role: role ?? this.role,
      driverProfile: driverProfile ?? this.driverProfile,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  @override
  List<Object?> get props => [
        id,
        firebaseUid,
        name,
        email,
        phone,
        profilePicture,
        role,
        driverProfile,
        createdAt,
        updatedAt,
      ];
}
