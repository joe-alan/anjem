import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

class LocalNotificationService {
  LocalNotificationService._();
  static final LocalNotificationService instance = LocalNotificationService._();

  final FlutterLocalNotificationsPlugin _plugin = FlutterLocalNotificationsPlugin();

  static const _rideChannelId = 'anjem_rides';
  static const _generalChannelId = 'anjem_general';

  /// Fixed ID for ride request notifications so they can be cancelled.
  static const rideRequestNotificationId = 9001;

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

    // Use a fixed ID for ride requests so we can cancel them later.
    final id = type == 'new_ride_request'
        ? rideRequestNotificationId
        : notification.hashCode;

    final androidDetails = AndroidNotificationDetails(
      channelId,
      channelName,
      importance: importance,
      priority: priority,
      playSound: true,
    );

    await _plugin.show(
      id,
      notification.title,
      notification.body,
      NotificationDetails(android: androidDetails),
      payload: message.data.toString(),
    );
  }

  /// Cancel the ride request notification (e.g., on accept, decline, expiry,
  /// or going offline).
  Future<void> cancelRideRequestNotification() async {
    await _plugin.cancel(rideRequestNotificationId);
  }

  /// Cancel all notifications from this app.
  Future<void> cancelAll() async {
    await _plugin.cancelAll();
  }
}
