import 'package:flutter/material.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mapbox_maps_flutter/mapbox_maps_flutter.dart';
import 'package:sentry_flutter/sentry_flutter.dart';
import 'core/app.dart';
import 'core/config/app_config.dart';
import 'core/config/mapbox_config.dart';

@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  debugPrint('[FCM] Background message: ${message.messageId}');
}

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Initialize Firebase
  await Firebase.initializeApp();
  FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

  // Initialize Mapbox access token
  MapboxOptions.setAccessToken(MapboxConfig.accessToken);

  // Initialize app config for Driver
  const sentryDsn = 'https://fe97fd76734a76bf9909eb40263a084a@o4511166441455616.ingest.us.sentry.io/4511167242305536';

  AppConfig.initialize(
    flavor: AppFlavor.driver,
    appName: 'Anjem Driver',
    apiBaseUrl: const String.fromEnvironment('API_URL', defaultValue: 'http://10.0.2.2:8000/api/v1'),
    wsUrl: const String.fromEnvironment('WS_URL', defaultValue: 'ws://10.0.2.2:8000'),
    primaryColor: const Color(0xFF004743),
    // Laravel Reverb configuration (implements Pusher protocol)
    pusherKey: const String.fromEnvironment('PUSHER_KEY', defaultValue: 'rp4e38k1ovkaodrtfxqa'),
    pusherHost: const String.fromEnvironment('PUSHER_HOST', defaultValue: '10.0.2.2'),
    pusherPort: const int.fromEnvironment('PUSHER_PORT', defaultValue: 8080),
    pusherScheme: const String.fromEnvironment('PUSHER_SCHEME', defaultValue: 'http'),
    sentryDsn: sentryDsn,
  );

  await SentryFlutter.init(
    (options) {
      options.dsn = sentryDsn;
      options.environment = const String.fromEnvironment('SENTRY_ENV', defaultValue: 'development');
      options.sendDefaultPii = true;
      options.enableLogs = true;
      options.tracesSampleRate = 0.2;
      options.profilesSampleRate = 1.0;
      options.attachScreenshot = true;
      options.replay.sessionSampleRate = 0.1;
      options.replay.onErrorSampleRate = 1.0;
    },
    appRunner: () => runApp(
      const ProviderScope(child: AnjerApp()),
    ),
  );
}
