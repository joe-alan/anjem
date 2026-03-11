import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import '../../config/app_config.dart';
import '../../navigation/navigator_key.dart';
import 'local_notification_service.dart';
import '../auth/auth_service.dart';

class FcmService {
  final AuthService _authService;

  FcmService({required AuthService authService}) : _authService = authService;

  Future<void> initialize() async {
    await LocalNotificationService.instance.initialize();
    await _requestPermission();
    await _sendTokenToBackend();
    _setupTokenRefreshListener();
    _setupForegroundHandler();
    _setupBackgroundOpenedHandler();
  }

  Future<void> _requestPermission() async {
    final settings = await FirebaseMessaging.instance.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );
    debugPrint('[FCM] Permission: ${settings.authorizationStatus}');
  }

  Future<void> _sendTokenToBackend() async {
    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token != null) {
        await _authService.updateFcmToken(token);
        debugPrint('[FCM] Token sent to backend');
      }
    } catch (e) {
      debugPrint('[FCM] Failed to send token: $e');
    }
  }

  void _setupTokenRefreshListener() {
    FirebaseMessaging.instance.onTokenRefresh.listen((token) async {
      try {
        await _authService.updateFcmToken(token);
        debugPrint('[FCM] Refreshed token sent to backend');
      } catch (e) {
        debugPrint('[FCM] Failed to send refreshed token: $e');
      }
    });
  }

  void _setupForegroundHandler() {
    FirebaseMessaging.onMessage.listen((RemoteMessage message) {
      debugPrint('[FCM] Foreground message: ${message.data}');
      // Skip local notification for new_ride_request — WebSocket shows the full sheet
      final type = message.data['type'] as String?;
      if (type == 'new_ride_request') return;
      LocalNotificationService.instance.showNotification(message);
    });
  }

  void _setupBackgroundOpenedHandler() {
    FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
      debugPrint('[FCM] App opened from background notification: ${message.data}');
      handleNotificationNavigation(message.data);
    });
  }

  Future<void> checkInitialMessage() async {
    final message = await FirebaseMessaging.instance.getInitialMessage();
    if (message != null) {
      debugPrint('[FCM] App launched from terminated notification: ${message.data}');
      handleNotificationNavigation(message.data);
    }
  }

  Future<void> deleteToken() async {
    try {
      await FirebaseMessaging.instance.deleteToken();
      debugPrint('[FCM] Token deleted');
    } catch (e) {
      debugPrint('[FCM] Failed to delete token: $e');
    }
  }

  void handleNotificationNavigation(Map<String, dynamic> data) {
    final type = data['type'] as String?;
    if (type == null) return;

    debugPrint('[FCM] Navigating for type: $type');

    final nav = navigatorKey.currentState;
    if (nav == null) return;

    final isDriver = AppConfig.instance.isDriverApp;

    switch (type) {
      // Driver-only: KYC rejection — go to KYC form
      case 'kyc_rejected':
        if (isDriver) nav.pushNamedAndRemoveUntil('/kyc', (r) => false);

      // Rider-only: active ride events — pop to root, session wrapper shows active ride
      case 'ride_accepted':
      case 'driver_arrived':
      case 'ride_started':
        if (!isDriver) nav.popUntil((r) => r.isFirst);

      // Both: completion and cancellation — pop to root (home)
      case 'ride_completed':
      case 'ride_cancelled':
      case 'ride_request_timeout':
      case 'new_ride_request':
      case 'kyc_approved':
        nav.popUntil((r) => r.isFirst);

      default:
        break;
    }
  }
}
