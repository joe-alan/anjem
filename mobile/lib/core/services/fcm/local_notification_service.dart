import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

class LocalNotificationService {
  LocalNotificationService._();
  static final LocalNotificationService instance = LocalNotificationService._();

  final FlutterLocalNotificationsPlugin _plugin = FlutterLocalNotificationsPlugin();

  static const _rideChannelId = 'anjem_rides';
  static const _generalChannelId = 'anjem_general';

  Future<void> initialize() async {
    const androidSettings = AndroidInitializationSettings('@mipmap/ic_launcher');
    const initSettings = InitializationSettings(android: androidSettings);
    await _plugin.initialize(initSettings);

    // Create Android notification channels
    const rideChannel = AndroidNotificationChannel(
      _rideChannelId,
      'Ride Notifications',
      description: 'Ride requests, status updates, and driver alerts',
      importance: Importance.max,
      playSound: true,
    );
    const generalChannel = AndroidNotificationChannel(
      _generalChannelId,
      'General Notifications',
      description: 'KYC updates and general alerts',
      importance: Importance.defaultImportance,
    );

    await _plugin
        .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(rideChannel);
    await _plugin
        .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(generalChannel);
  }

  Future<void> showNotification(RemoteMessage message) async {
    final notification = message.notification;
    if (notification == null) return;

    final type = message.data['type'] as String?;
    final isRideEvent = type != null && type != 'queue_position_update';
    final channelId = isRideEvent ? _rideChannelId : _generalChannelId;
    final channelName = isRideEvent ? 'Ride Notifications' : 'General Notifications';
    final importance = isRideEvent ? Importance.max : Importance.defaultImportance;
    final priority = isRideEvent ? Priority.max : Priority.defaultPriority;

    final androidDetails = AndroidNotificationDetails(
      channelId,
      channelName,
      importance: importance,
      priority: priority,
      playSound: true,
    );

    await _plugin.show(
      notification.hashCode,
      notification.title,
      notification.body,
      NotificationDetails(android: androidDetails),
      payload: message.data.toString(),
    );
  }
}
