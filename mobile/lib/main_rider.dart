import 'package:flutter/material.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mapbox_maps_flutter/mapbox_maps_flutter.dart';
import 'core/app.dart';
import 'core/config/app_config.dart';
import 'core/config/mapbox_config.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Initialize Firebase
  await Firebase.initializeApp();

  // Initialize Mapbox access token
  MapboxOptions.setAccessToken(MapboxConfig.accessToken);

  // Initialize app config for Rider
  AppConfig.initialize(
    flavor: AppFlavor.rider,
    appName: 'Anjem Rider',
    apiBaseUrl: const String.fromEnvironment('API_URL', defaultValue: 'http://10.0.2.2:8000/api/v1'),
    wsUrl: const String.fromEnvironment('WS_URL', defaultValue: 'ws://10.0.2.2:8000'),
    primaryColor: const Color(0xFF2196F3), // Blue for rider
    // Laravel Reverb configuration (implements Pusher protocol)
    pusherKey: const String.fromEnvironment('PUSHER_KEY', defaultValue: 'rp4e38k1ovkaodrtfxqa'),
    pusherHost: const String.fromEnvironment('PUSHER_HOST', defaultValue: '10.0.2.2'),
    pusherPort: const int.fromEnvironment('PUSHER_PORT', defaultValue: 8080),
    pusherScheme: 'http',
  );

  runApp(
    const ProviderScope(
      child: AnjerApp(),
    ),
  );
}