import 'dart:async';
import 'dart:convert';
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';
import '../../config/app_config.dart';
import '../api/api_service.dart';

class WebSocketService {
  final ApiService _apiService;
  late PusherChannelsFlutter _pusher;

  bool _isInitialized = false;
  bool _isConnected = false;

  final Map<String, dynamic> _activeChannels = {};
  final StreamController<WsConnectionState> _connectionStateController =
      StreamController<WsConnectionState>.broadcast();

  Stream<WsConnectionState> get connectionStateStream =>
      _connectionStateController.stream;

  bool get isConnected => _isConnected;

  WebSocketService({required ApiService apiService}) : _apiService = apiService;

  Future<void> initialize() async {
    if (_isInitialized) return;

    _pusher = PusherChannelsFlutter.getInstance();

    try {
      await _pusher.init(
        apiKey: _getPusherKey(),
        cluster: 'mt1', // Default cluster, adjust if needed
        onConnectionStateChange: _onConnectionStateChange,
        onError: _onError,
        onEvent: _onEvent,
        onSubscriptionSucceeded: _onSubscriptionSucceeded,
        onSubscriptionError: _onSubscriptionError,
        onDecryptionFailure: _onDecryptionFailure,
        onMemberAdded: _onMemberAdded,
        onMemberRemoved: _onMemberRemoved,
        onAuthorizer: _onAuthorizer,
      );

      _isInitialized = true;
    } catch (e) {
      print('Failed to initialize Pusher: $e');
      rethrow;
    }
  }

  String _getPusherKey() {
    return AppConfig.pusherKey ?? 'YOUR_PUSHER_KEY_HERE';
  }

  Future<void> connect() async {
    if (!_isInitialized) {
      await initialize();
    }

    try {
      await _pusher.connect();
    } catch (e) {
      print('Failed to connect to Pusher: $e');
      rethrow;
    }
  }

  Future<void> disconnect() async {
    if (!_isInitialized) return;

    try {
      // Unsubscribe from all channels
      for (final channelName in _activeChannels.keys.toList()) {
        await _unsubscribe(channelName);
      }

      await _pusher.disconnect();
      _isConnected = false;
    } catch (e) {
      print('Failed to disconnect from Pusher: $e');
    }
  }

  // Subscribe to private ride channel for ride updates
  Future<void> subscribeToRideChannel({
    required int rideId,
    required Function(Map<String, dynamic>) onStatusUpdate,
    required Function(double latitude, double longitude) onLocationUpdate,
  }) async {
    final channelName = 'private-ride.$rideId';

    try {
      final channel = await _pusher.subscribe(
        channelName: channelName,
        onEvent: (event) {
          final data = jsonDecode(event.data);

          if (event.eventName == 'RideStatusUpdated') {
            onStatusUpdate(data);
          } else if (event.eventName == 'DriverLocationUpdated') {
            final lat = (data['latitude'] as num).toDouble();
            final lng = (data['longitude'] as num).toDouble();
            onLocationUpdate(lat, lng);
          }
        },
      );

      _activeChannels[channelName] = channel;
    } catch (e) {
      print('Failed to subscribe to ride channel: $e');
      rethrow;
    }
  }

  // Subscribe to private user channel for ride matching
  Future<void> subscribeToUserChannel({
    required int userId,
    required Function(Map<String, dynamic>) onRideMatched,
  }) async {
    final channelName = 'private-user.$userId';

    try {
      final channel = await _pusher.subscribe(
        channelName: channelName,
        onEvent: (event) {
          if (event.eventName == 'RideRequestMatched') {
            final data = jsonDecode(event.data);
            onRideMatched(data);
          }
        },
      );

      _activeChannels[channelName] = channel;
    } catch (e) {
      print('Failed to subscribe to user channel: $e');
      rethrow;
    }
  }

  // Subscribe to private driver channel for new ride requests
  Future<void> subscribeToDriverChannel({
    required int driverId,
    required Function(Map<String, dynamic>) onNewRideRequest,
  }) async {
    final channelName = 'private-driver.$driverId';

    try {
      final channel = await _pusher.subscribe(
        channelName: channelName,
        onEvent: (event) {
          if (event.eventName == 'NewRideRequest') {
            final data = jsonDecode(event.data);
            onNewRideRequest(data);
          }
        },
      );

      _activeChannels[channelName] = channel;
    } catch (e) {
      print('Failed to subscribe to driver channel: $e');
      rethrow;
    }
  }

  // Subscribe to presence beacon channel for queue updates
  Future<void> subscribeToBeaconChannel({
    required int beaconId,
    required Function(Map<String, dynamic>) onQueuePositionChanged,
  }) async {
    final channelName = 'presence-beacon.$beaconId';

    try {
      final channel = await _pusher.subscribe(
        channelName: channelName,
        onEvent: (event) {
          if (event.eventName == 'QueuePositionChanged') {
            final data = jsonDecode(event.data);
            onQueuePositionChanged(data);
          }
        },
      );

      _activeChannels[channelName] = channel;
    } catch (e) {
      print('Failed to subscribe to beacon channel: $e');
      rethrow;
    }
  }

  // Unsubscribe from a channel
  Future<void> _unsubscribe(String channelName) async {
    try {
      await _pusher.unsubscribe(channelName: channelName);
      _activeChannels.remove(channelName);
    } catch (e) {
      print('Failed to unsubscribe from $channelName: $e');
    }
  }

  WsConnectionState _mapState(String? s) {
    switch (s) {
      case 'CONNECTED':
        return WsConnectionState.connected;
      case 'CONNECTING':
        return WsConnectionState.connecting;
      case 'DISCONNECTED':
      default:
        return WsConnectionState.disconnected;
    }
  }

  void _onConnectionStateChange(String? currentState, String? previousState) {
    print('Connection state changed: $previousState -> $currentState');
    final mapped = _mapState(currentState);
    _isConnected = mapped == WsConnectionState.connected;
    _connectionStateController.add(mapped);
  }

  void _onError(String message, int? code, dynamic error) {
    print('WebSocket error: $message (code: $code), error: $error');
  }

  void _onEvent(PusherEvent event) {
    // Global event handler (optional)
    print('Event received: ${event.eventName} on ${event.channelName}');
  }

  void _onSubscriptionSucceeded(String channelName, dynamic data) {
    print('Subscription succeeded: $channelName');
  }

  void _onSubscriptionError(String message, dynamic error) {
    print('Subscription error: $message, $error');
  }

  void _onDecryptionFailure(String event, String reason) {
    print('Decryption failure: $event, $reason');
  }

  void _onMemberAdded(String channelName, PusherMember member) {
    print('Member added to $channelName: ${member.userId}');
  }

  void _onMemberRemoved(String channelName, PusherMember member) {
    print('Member removed from $channelName: ${member.userId}');
  }

  // Custom authorizer for private/presence channels
  Future<dynamic> _onAuthorizer(
    String channelName,
    String socketId,
    dynamic options,
  ) async {
    try {
      final response = await _apiService.post(
        '/broadcasting/auth',
        data: {
          'socket_id': socketId,
          'channel_name': channelName,
        },
      );

      return response.data;
    } catch (e) {
      print('Authorization failed: $e');
      rethrow;
    }
  }

  void dispose() {
    _connectionStateController.close();
  }
}

enum WsConnectionState {
  connecting,
  connected,
  disconnected,
}
