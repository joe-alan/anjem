import 'package:flutter/material.dart';
import 'core/app.dart';
import 'core/config/app_config.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Initialize app config for Rider
  AppConfig.initialize(
    flavor: AppFlavor.rider,
    appName: 'Anjem Rider',
    apiBaseUrl: const String.fromEnvironment('API_URL', defaultValue: 'http://localhost:8000/api/v1'),
    wsUrl: const String.fromEnvironment('WS_URL', defaultValue: 'ws://localhost:8000'),
    primaryColor: const Color(0xFF2196F3), // Blue for rider
  );

  runApp(const AnjerApp());
}