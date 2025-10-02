import 'package:flutter/material.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'core/app.dart';
import 'core/config/app_config.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Initialize Firebase
  await Firebase.initializeApp();

  // Initialize app config for Driver
  AppConfig.initialize(
    flavor: AppFlavor.driver,
    appName: 'Anjem Driver',
    apiBaseUrl: const String.fromEnvironment('API_URL', defaultValue: 'http://10.0.2.2:8000/api/v1'),
    wsUrl: const String.fromEnvironment('WS_URL', defaultValue: 'ws://10.0.2.2:8000'),
    primaryColor: const Color(0xFF4CAF50), // Green for driver
  );

  runApp(
    const ProviderScope(
      child: AnjerApp(),
    ),
  );
}