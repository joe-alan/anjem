import 'package:flutter/foundation.dart';
import 'dart:async';
import 'package:pusher_client_socket/pusher_client_socket.dart';
import '../../config/app_config.dart';
import '../api/api_service.dart';

class WebSocketService {
  final ApiService _apiService;
  PusherClient? _pusher;

  bool _isInitialized = false;
  bool _isConnected = false;
  bool _intentionalDisconnect = false;

  // Reconnect state
  Timer? _reconnectTimer;
  int _reconnectAttempts = 0;
  static const int _maxReconnectDelaySecs = 30;

  final Map<String, dynamic> _channels = {};
  // Store event bindings per channel so we can re-apply after reconnect
  final Map<String, List<_EventBinding>> _channelBindings = {};
  final StreamController<WsConnectionState> _connectionStateController =
      StreamController<WsConnectionState>.broadcast();

  Stream<WsConnectionState> get connectionStateStream =>
      _connectionStateController.stream;

  bool get isConnected => _isConnected;

  WebSocketService({required ApiService apiService}) : _apiService = apiService;

  void _scheduleReconnect() {
    _reconnectTimer?.cancel();
    // Exponential backoff: 2s, 4s, 8s, 16s, 30s (capped)
    final delaySecs = (_reconnectAttempts < 4
            ? (2 << _reconnectAttempts) // 2, 4, 8, 16
            : _maxReconnectDelaySecs)
        .clamp(2, _maxReconnectDelaySecs);
    _reconnectAttempts++;
    _reconnectTimer = Timer(Duration(seconds: delaySecs), () {
      if (_intentionalDisconnect) return;
      _pusher?.connect();
    });
  }

  void _resubscribeChannels() {
    if (_channels.isEmpty) return;
    for (final entry in _channels.entries) {
      try {
        entry.value.subscribe();
        // Re-apply stored event bindings after reconnect
        final bindings = _channelBindings[entry.key];
        if (bindings != null) {
          for (final binding in bindings) {
            entry.value.bind(binding.event, binding.callback);
          }
          debugPrint('Re-bound ${bindings.length} events on ${entry.key}');
        }
      } catch (e, st) {
        debugPrint('Failed to resubscribe to ${entry.key}: $e\n$st');
      }
    }
  }

  Future<void> initialize() async {
    if (_isInitialized) return;

    try {
      final config = AppConfig.instance;

      debugPrint(
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
        debugPrint('Connection established: $data');
        _isConnected = true;
        _reconnectAttempts = 0;
        _reconnectTimer?.cancel();
        _connectionStateController.add(WsConnectionState.connected);
        _resubscribeChannels();
      });

      // Setup error listener
      _pusher!.onConnectionError((error) {
        debugPrint('WebSocket connection error: $error');
        _isConnected = false;
        _connectionStateController.add(WsConnectionState.disconnected);
        if (!_intentionalDisconnect) _scheduleReconnect();
      });

      // Setup disconnected listener
      _pusher!.onDisconnected((data) {
        debugPrint('WebSocket disconnected: $data');
        _isConnected = false;
        _connectionStateController.add(WsConnectionState.disconnected);
        if (!_intentionalDisconnect) _scheduleReconnect();
      });

      // Setup general error listener
      _pusher!.onError((error) {
        debugPrint('WebSocket error: $error');
      });

      _isInitialized = true;
      debugPrint('WebSocket initialization successful');
    } catch (e) {
      debugPrint('Failed to initialize Pusher: $e');
      rethrow;
    }
  }

  Future<void> connect() async {
    if (!_isInitialized) {
      await initialize();
    }

    try {
      _intentionalDisconnect = false;
      _connectionStateController.add(WsConnectionState.connecting);
      _pusher?.connect();
      debugPrint('Connecting to WebSocket...');
    } catch (e) {
      debugPrint('Failed to connect to WebSocket: $e');
      rethrow;
    }
  }

  Future<void> disconnect() async {
    if (!_isInitialized || _pusher == null) return;

    _intentionalDisconnect = true;
    _reconnectTimer?.cancel();

    try {
      // Unsubscribe from all channels
      for (final channelName in _channels.keys.toList()) {
        final channel = _channels[channelName];
        if (channel != null) {
          try {
            channel.unsubscribe();
          } catch (e) {
            debugPrint('Error unsubscribing from $channelName: $e');
          }
        }
      }

      _channels.clear();
      _channelBindings.clear();

      _pusher?.disconnect();
      _isConnected = false;
      debugPrint('WebSocket disconnected');
    } catch (e) {
      debugPrint('Failed to disconnect from WebSocket: $e');
    }
  }

  // Subscribe to private ride channel for ride updates.
  // If already subscribed, re-binds callbacks (needed after session resume).
  Future<void> subscribeToRideChannel({
    required int rideId,
    required Function(Map<String, dynamic>) onStatusUpdate,
    required Function(double latitude, double longitude) onLocationUpdate,
  }) async {
    final channelName = 'private-ride.$rideId';

    try {
      dynamic channel;
      if (_channels.containsKey(channelName)) {
        // Channel exists — reuse it but refresh callbacks
        channel = _channels[channelName];
        debugPrint('Re-binding callbacks on existing $channelName');
      } else {
        channel = _pusher!.private(channelName);
        if (!_isConnected) {
          debugPrint(
              'WebSocket currently disconnected, will subscribe to $channelName once connection is ready');
        }
        channel.subscribe();
        _channels[channelName] = channel;
      }

      // Build fresh bindings
      final bindings = <_EventBinding>[
        _EventBinding('ride.status.updated', (data) {
          debugPrint('Received ride status update: $data');
          if (data != null) {
            onStatusUpdate(data as Map<String, dynamic>);
          }
        }),
        _EventBinding('driver.location.updated', (data) {
          debugPrint('Received driver location update: $data');
          if (data != null) {
            final locationData = data as Map<String, dynamic>;
            final lat = (locationData['latitude'] as num).toDouble();
            final lng = (locationData['longitude'] as num).toDouble();
            onLocationUpdate(lat, lng);
          }
        }),
      ];

      // Apply bindings to channel
      for (final binding in bindings) {
        channel.bind(binding.event, binding.callback);
      }
      _channelBindings[channelName] = bindings;

      debugPrint('Subscribed to ride channel: $channelName');
    } catch (e) {
      debugPrint('Failed to subscribe to ride channel: $e');
      rethrow;
    }
  }

  // Subscribe to private user channel for ride matching
  Future<void> subscribeToUserChannel({
    required int userId,
    required Function(Map<String, dynamic>) onRideMatched,
    Function(Map<String, dynamic>)? onNoDriversAvailable,
    Function(Map<String, dynamic>)? onRequestExpired,
    Function(Map<String, dynamic>)? onSearchResumed,
    Function(Map<String, dynamic>)? onAccountStatusChanged,
    Function(Map<String, dynamic>)? onRideStatusUpdated,
  }) async {
    final channelName =
        'user.$userId'; // Don't add 'private-' prefix, .private() method does it automatically

    try {
      if (_channels.containsKey(channelName)) {
        debugPrint('Already subscribed to $channelName');
        return;
      }

      if (_pusher == null) {
        await initialize();
        await connect();
      }

      final channel = _pusher!.private(channelName);
      if (!_isConnected) {
        debugPrint(
            'WebSocket currently disconnected, will subscribe to $channelName once connection is ready');
      }
      channel.subscribe();

      // Listen for ride match events
      debugPrint('Setting up event binding for: ride.request.matched');
      channel.bind('ride.request.matched', (data) {
        debugPrint('═══════════════════════════════════════');
        debugPrint('🎊 WEBSOCKET EVENT RECEIVED! 🎊');
        debugPrint('Event: ride.request.matched');
        debugPrint('Data type: ${data.runtimeType}');
        debugPrint('Data: $data');
        debugPrint('═══════════════════════════════════════');

        if (data != null) {
          debugPrint('Calling onRideMatched callback...');
          onRideMatched(data as Map<String, dynamic>);
          debugPrint('onRideMatched callback completed');
        } else {
          debugPrint('⚠️ Data is NULL!');
        }
      });
      debugPrint('Event binding completed for ride.request.matched');

      // Listen for no-drivers-available notification (shows countdown on rider)
      if (onNoDriversAvailable != null) {
        channel.bind('ride.no_drivers_available', (data) {
          debugPrint('Received ride.no_drivers_available: $data');
          if (data != null) {
            onNoDriversAvailable(data as Map<String, dynamic>);
          }
        });
      }

      // Listen for expiry broadcast from ExpireRideRequest job
      if (onRequestExpired != null) {
        channel.bind('ride.request.cancelled', (data) {
          debugPrint('Received ride.request.cancelled on user channel: $data');
          if (data != null) {
            onRequestExpired(data as Map<String, dynamic>);
          }
        });
      }

      // Listen for search-resumed signal when a new driver joins during countdown
      if (onSearchResumed != null) {
        channel.bind('ride.search.resumed', (data) {
          debugPrint('Received ride.search.resumed: $data');
          if (data != null) {
            onSearchResumed(data as Map<String, dynamic>);
          }
        });
      }

      // Listen for admin account suspension / unsuspension
      if (onAccountStatusChanged != null) {
        channel.bind('account.status.changed', (data) {
          debugPrint('Received account.status.changed (rider): $data');
          if (data != null) {
            onAccountStatusChanged(data as Map<String, dynamic>);
          }
        });
      }

      // Listen for admin force-complete / force-cancel while rider is on
      // the "Finding Driver" screen (not yet subscribed to the ride channel).
      if (onRideStatusUpdated != null) {
        channel.bind('ride.status.updated', (data) {
          debugPrint('Received ride.status.updated on user channel: $data');
          if (data != null) {
            onRideStatusUpdated(data as Map<String, dynamic>);
          }
        });
      }

      _channels[channelName] = channel;
      debugPrint('Subscribed to user channel: $channelName');
    } catch (e) {
      debugPrint('Failed to subscribe to user channel: $e');
      rethrow;
    }
  }

  // Subscribe to private driver channel for new ride requests
  Future<void> subscribeToDriverChannel({
    required int driverId,
    required Function(Map<String, dynamic>) onNewRideRequest,
    Function(Map<String, dynamic>)? onQueuePositionChanged,
    Function(Map<String, dynamic>)? onRequestCancelled,
    Function(Map<String, dynamic>)? onSessionReplaced,
    Function(Map<String, dynamic>)? onDriverStatusChanged,
    Function(Map<String, dynamic>)? onKycStatusChanged,
    Function(Map<String, dynamic>)? onCreditsUpdated,
    Function(Map<String, dynamic>)? onAccountStatusChanged,
  }) async {
    final channelName = 'driver.$driverId';

    try {
      if (_channels.containsKey(channelName)) {
        debugPrint('Already subscribed to $channelName');
        return;
      }

      final channel = _pusher!.private(channelName);
      if (!_isConnected) {
        debugPrint(
            'WebSocket currently disconnected, will subscribe to $channelName once connection is ready');
      }
      channel.subscribe();

      // Listen for new ride request events
      channel.bind('ride.request.new', (data) {
        debugPrint('Received new ride request: $data');
        if (data != null) {
          onNewRideRequest(data as Map<String, dynamic>);
        }
      });

      // Listen for FIFO queue position updates
      if (onQueuePositionChanged != null) {
        channel.bind('queue.position.changed', (data) {
          debugPrint('Received queue position update: $data');
          if (data != null) {
            onQueuePositionChanged(data as Map<String, dynamic>);
          }
        });
      }

      // Listen for ride request cancellations (rider cancelled or admin cancelled while dispatched)
      if (onRequestCancelled != null) {
        channel.bind('ride.request.cancelled', (data) {
          debugPrint('Received ride request cancellation: $data');
          if (data != null) {
            onRequestCancelled(data as Map<String, dynamic>);
          }
        });
      }

      // Listen for session displacement (another device logged in as this driver)
      if (onSessionReplaced != null) {
        channel.bind('session.replaced', (data) {
          debugPrint('Received session.replaced — signing out displaced device');
          onSessionReplaced(data as Map<String, dynamic>? ?? {});
        });
      }

      // Listen for driver online status changes (e.g. auto-kick on zero credits)
      if (onDriverStatusChanged != null) {
        channel.bind('driver.status.changed', (data) {
          debugPrint('Received driver.status.changed: $data');
          if (data != null) {
            onDriverStatusChanged(data as Map<String, dynamic>);
          }
        });
      }

      // Listen for admin KYC approval / rejection
      if (onKycStatusChanged != null) {
        channel.bind('driver.kyc.updated', (data) {
          debugPrint('Received driver.kyc.updated: $data');
          if (data != null) {
            onKycStatusChanged(data as Map<String, dynamic>);
          }
        });
      }

      // Listen for admin credit grant / deduct
      if (onCreditsUpdated != null) {
        channel.bind('driver.credits.updated', (data) {
          debugPrint('Received driver.credits.updated: $data');
          if (data != null) {
            onCreditsUpdated(data as Map<String, dynamic>);
          }
        });
      }

      // Listen for admin account suspension / unsuspension
      if (onAccountStatusChanged != null) {
        channel.bind('account.status.changed', (data) {
          debugPrint('Received account.status.changed (driver): $data');
          if (data != null) {
            onAccountStatusChanged(data as Map<String, dynamic>);
          }
        });
      }

      _channels[channelName] = channel;
      debugPrint('Subscribed to driver channel: private-$channelName');
    } catch (e) {
      debugPrint('Failed to subscribe to driver channel: $e');
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
        debugPrint('Already subscribed to $channelName');
        return;
      }

      final channel = _pusher!.presence(channelName);
      if (!_isConnected) {
        debugPrint(
            'WebSocket currently disconnected, will subscribe to $channelName once connection is ready');
      }
      channel.subscribe();

      // Listen for queue position updates
      channel.bind('queue.position.changed', (data) {
        debugPrint('Received queue position update: $data');
        if (data != null) {
          onQueuePositionChanged(data as Map<String, dynamic>);
        }
      });

      _channels[channelName] = channel;
      debugPrint('Subscribed to beacon channel: $channelName');
    } catch (e) {
      debugPrint('Failed to subscribe to beacon channel: $e');
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
          _channelBindings.remove(channelName);
          debugPrint('Unsubscribed from channel: $channelName');
        }
      }
    } catch (e) {
      debugPrint('Failed to unsubscribe from $channelName: $e');
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

class _EventBinding {
  final String event;
  final Function(dynamic) callback;
  const _EventBinding(this.event, this.callback);
}
