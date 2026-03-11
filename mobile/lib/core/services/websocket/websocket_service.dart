import 'dart:async';
import 'package:pusher_client_socket/pusher_client_socket.dart';
import '../../config/app_config.dart';
import '../api/api_service.dart';

class WebSocketService {
  final ApiService _apiService;
  PusherClient? _pusher;

  bool _isInitialized = false;
  bool _isConnected = false;

  final Map<String, dynamic> _channels = {};
  final StreamController<WsConnectionState> _connectionStateController =
      StreamController<WsConnectionState>.broadcast();

  Stream<WsConnectionState> get connectionStateStream =>
      _connectionStateController.stream;

  bool get isConnected => _isConnected;

  WebSocketService({required ApiService apiService}) : _apiService = apiService;

  Future<void> initialize() async {
    if (_isInitialized) return;

    try {
      final config = AppConfig.instance;

      print(
          'Initializing WebSocket with Reverb at ${config.pusherHost}:${config.pusherPort}');

      final options = PusherOptions(
        key: config.pusherKey,
        host: config.pusherHost,
        wsPort: config.pusherPort,
        encrypted: config.pusherScheme == 'https',
        authOptions: PusherAuthOptions(
          '${config.apiBaseUrl.replaceAll('/api/v1', '')}/broadcasting/auth',
          headers: () async {
            final token = await _apiService.getToken();
            return {
              'Accept': 'application/json',
              'Authorization': 'Bearer $token',
              'Content-Type': 'application/x-www-form-urlencoded',
            };
          },
        ),
        autoConnect: false,
      );

      _pusher = PusherClient(options: options);

      // Setup connection established listener
      _pusher!.onConnectionEstablished((data) {
        print('Connection established: $data');
        _isConnected = true;
        _connectionStateController.add(WsConnectionState.connected);
      });

      // Setup error listener
      _pusher!.onConnectionError((error) {
        print('WebSocket connection error: $error');
        _isConnected = false;
        _connectionStateController.add(WsConnectionState.disconnected);
      });

      // Setup disconnected listener
      _pusher!.onDisconnected((data) {
        print('WebSocket disconnected: $data');
        _isConnected = false;
        _connectionStateController.add(WsConnectionState.disconnected);
      });

      // Setup general error listener
      _pusher!.onError((error) {
        print('WebSocket error: $error');
      });

      _isInitialized = true;
      print('WebSocket initialization successful');
    } catch (e) {
      print('Failed to initialize Pusher: $e');
      rethrow;
    }
  }

  Future<void> connect() async {
    if (!_isInitialized) {
      await initialize();
    }

    try {
      _connectionStateController.add(WsConnectionState.connecting);
      _pusher?.connect();
      print('Connecting to WebSocket...');
    } catch (e) {
      print('Failed to connect to WebSocket: $e');
      rethrow;
    }
  }

  Future<void> disconnect() async {
    if (!_isInitialized || _pusher == null) return;

    try {
      // Unsubscribe from all channels
      for (final channelName in _channels.keys.toList()) {
        final channel = _channels[channelName];
        if (channel != null) {
          try {
            channel.unsubscribe();
          } catch (e) {
            print('Error unsubscribing from $channelName: $e');
          }
        }
      }

      _channels.clear();

      _pusher?.disconnect();
      _isConnected = false;
      print('WebSocket disconnected');
    } catch (e) {
      print('Failed to disconnect from WebSocket: $e');
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
      if (_channels.containsKey(channelName)) {
        print('Already subscribed to $channelName');
        return;
      }

      final channel = _pusher!.private(channelName);
      if (!_isConnected) {
        print(
            'WebSocket currently disconnected, will subscribe to $channelName once connection is ready');
      }
      channel.subscribe();

      // Listen for ride status updates
      channel.bind('ride.status.updated', (data) {
        print('Received ride status update: $data');
        if (data != null) {
          onStatusUpdate(data as Map<String, dynamic>);
        }
      });

      // Listen for driver location updates
      channel.bind('driver.location.updated', (data) {
        print('Received driver location update: $data');
        if (data != null) {
          final locationData = data as Map<String, dynamic>;
          final lat = (locationData['latitude'] as num).toDouble();
          final lng = (locationData['longitude'] as num).toDouble();
          onLocationUpdate(lat, lng);
        }
      });

      _channels[channelName] = channel;
      print('Subscribed to ride channel: $channelName');
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
    final channelName =
        'user.$userId'; // Don't add 'private-' prefix, .private() method does it automatically

    try {
      if (_channels.containsKey(channelName)) {
        print('Already subscribed to $channelName');
        return;
      }

      final channel = _pusher!.private(channelName);
      if (!_isConnected) {
        print(
            'WebSocket currently disconnected, will subscribe to $channelName once connection is ready');
      }
      channel.subscribe();

      // Listen for ride match events
      print('Setting up event binding for: ride.request.matched');
      channel.bind('ride.request.matched', (data) {
        print('═══════════════════════════════════════');
        print('🎊 WEBSOCKET EVENT RECEIVED! 🎊');
        print('Event: ride.request.matched');
        print('Data type: ${data.runtimeType}');
        print('Data: $data');
        print('═══════════════════════════════════════');

        if (data != null) {
          print('Calling onRideMatched callback...');
          onRideMatched(data as Map<String, dynamic>);
          print('onRideMatched callback completed');
        } else {
          print('⚠️ Data is NULL!');
        }
      });
      print('Event binding completed for ride.request.matched');

      _channels[channelName] = channel;
      print('Subscribed to user channel: $channelName');
    } catch (e) {
      print('Failed to subscribe to user channel: $e');
      rethrow;
    }
  }

  // Subscribe to private driver channel for new ride requests
  Future<void> subscribeToDriverChannel({
    required int driverId,
    required Function(Map<String, dynamic>) onNewRideRequest,
    Function(Map<String, dynamic>)? onQueuePositionChanged,
  }) async {
    final channelName = 'driver.$driverId';

    try {
      if (_channels.containsKey(channelName)) {
        print('Already subscribed to $channelName');
        return;
      }

      final channel = _pusher!.private(channelName);
      if (!_isConnected) {
        print(
            'WebSocket currently disconnected, will subscribe to $channelName once connection is ready');
      }
      channel.subscribe();

      // Listen for new ride request events
      channel.bind('ride.request.new', (data) {
        print('Received new ride request: $data');
        if (data != null) {
          onNewRideRequest(data as Map<String, dynamic>);
        }
      });

      // Listen for FIFO queue position updates
      if (onQueuePositionChanged != null) {
        channel.bind('queue.position.changed', (data) {
          print('Received queue position update: $data');
          if (data != null) {
            onQueuePositionChanged(data as Map<String, dynamic>);
          }
        });
      }

      _channels[channelName] = channel;
      print('Subscribed to driver channel: private-$channelName');
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
      if (_channels.containsKey(channelName)) {
        print('Already subscribed to $channelName');
        return;
      }

      final channel = _pusher!.presence(channelName);
      if (!_isConnected) {
        print(
            'WebSocket currently disconnected, will subscribe to $channelName once connection is ready');
      }
      channel.subscribe();

      // Listen for queue position updates
      channel.bind('queue.position.changed', (data) {
        print('Received queue position update: $data');
        if (data != null) {
          onQueuePositionChanged(data as Map<String, dynamic>);
        }
      });

      _channels[channelName] = channel;
      print('Subscribed to beacon channel: $channelName');
    } catch (e) {
      print('Failed to subscribe to beacon channel: $e');
      rethrow;
    }
  }

  // Unsubscribe from a channel
  Future<void> unsubscribeFromChannel(String channelName) async {
    try {
      if (_channels.containsKey(channelName)) {
        final channel = _channels[channelName];
        if (channel != null) {
          channel.unsubscribe();
          _channels.remove(channelName);
          print('Unsubscribed from channel: $channelName');
        }
      }
    } catch (e) {
      print('Failed to unsubscribe from $channelName: $e');
    }
  }

  void dispose() {
    disconnect();
    _connectionStateController.close();
  }
}

enum WsConnectionState {
  connecting,
  connected,
  disconnected,
}
