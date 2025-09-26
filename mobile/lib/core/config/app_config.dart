import 'package:flutter/material.dart';

enum AppFlavor { rider, driver }

class AppConfig {
  static late AppConfig _instance;

  final AppFlavor flavor;
  final String appName;
  final String apiBaseUrl;
  final String wsUrl;
  final Color primaryColor;

  AppConfig._({
    required this.flavor,
    required this.appName,
    required this.apiBaseUrl,
    required this.wsUrl,
    required this.primaryColor,
  });

  static void initialize({
    required AppFlavor flavor,
    required String appName,
    required String apiBaseUrl,
    required String wsUrl,
    required Color primaryColor,
  }) {
    _instance = AppConfig._(
      flavor: flavor,
      appName: appName,
      apiBaseUrl: apiBaseUrl,
      wsUrl: wsUrl,
      primaryColor: primaryColor,
    );
  }

  static AppConfig get instance => _instance;

  bool get isRiderApp => flavor == AppFlavor.rider;
  bool get isDriverApp => flavor == AppFlavor.driver;

  String get flavorName => flavor.name;
}