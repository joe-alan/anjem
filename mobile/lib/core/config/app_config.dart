import 'package:flutter/material.dart';

enum AppFlavor { rider, driver }

class AppConfig {
  static late AppConfig _instance;

  final AppFlavor flavor;
  final String appName;
  final String apiBaseUrl;
  final String wsUrl;
  final Color primaryColor;
  final String pusherKey;
  final String pusherHost;
  final int pusherPort;
  final String pusherScheme;
  final String? sentryDsn;

  AppConfig._({
    required this.flavor,
    required this.appName,
    required this.apiBaseUrl,
    required this.wsUrl,
    required this.primaryColor,
    required this.pusherKey,
    required this.pusherHost,
    required this.pusherPort,
    required this.pusherScheme,
    this.sentryDsn,
  });

  static void initialize({
    required AppFlavor flavor,
    required String appName,
    required String apiBaseUrl,
    required String wsUrl,
    required Color primaryColor,
    required String pusherKey,
    required String pusherHost,
    required int pusherPort,
    String pusherScheme = 'http',
    String? sentryDsn,
  }) {
    _instance = AppConfig._(
      flavor: flavor,
      appName: appName,
      apiBaseUrl: apiBaseUrl,
      wsUrl: wsUrl,
      primaryColor: primaryColor,
      pusherKey: pusherKey,
      pusherHost: pusherHost,
      pusherPort: pusherPort,
      pusherScheme: pusherScheme,
      sentryDsn: sentryDsn,
    );
  }

  static AppConfig get instance => _instance;

  bool get isRiderApp => flavor == AppFlavor.rider;
  bool get isDriverApp => flavor == AppFlavor.driver;

  String get flavorName => flavor.name;
}
