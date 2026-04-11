import 'dart:async';
import 'dart:io' show Platform;
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import '../api/api_service.dart';
import '../../providers/api_provider.dart';

enum DriverLocationMode { idle, activeRide }

class DriverLocationService {
  final ApiService _apiService;

  StreamSubscription<Position>? _positionSubscription;
  final _positionController = StreamController<Position>.broadcast();

  DriverLocationMode _mode = DriverLocationMode.idle;
  DateTime _lastSendTime = DateTime(2000);
  bool _isTracking = false;

  DriverLocationService(this._apiService);

  bool get isTracking => _isTracking;
  Stream<Position> get positionStream => _positionController.stream;

  Future<void> start({
    DriverLocationMode mode = DriverLocationMode.idle,
    String notificationTitle = 'Anjem Driver',
    String notificationText = 'Your location is being shared while online',
  }) async {
    if (_isTracking) {
      if (kDebugMode) print('DriverLocationService: already tracking, switching mode');
      _mode = mode;
      return;
    }

    _mode = mode;
    _lastSendTime = DateTime(2000); // Force immediate first send
    _isTracking = true;

    final locationSettings = _buildLocationSettings(
      notificationTitle: notificationTitle,
      notificationText: notificationText,
    );

    _positionSubscription = Geolocator.getPositionStream(
      locationSettings: locationSettings,
    ).listen(
      _onPositionUpdate,
      onError: (error) {
        if (kDebugMode) print('DriverLocationService: stream error: $error');
      },
    );

    if (kDebugMode) print('DriverLocationService: started (mode=$_mode)');
  }

  void switchMode(DriverLocationMode mode) {
    _mode = mode;
    if (kDebugMode) print('DriverLocationService: switched to mode=$_mode');
  }

  Future<void> stop() async {
    if (!_isTracking) return;

    await _positionSubscription?.cancel();
    _positionSubscription = null;
    _isTracking = false;

    if (kDebugMode) print('DriverLocationService: stopped');
  }

  void dispose() {
    stop();
    _positionController.close();
  }

  void _onPositionUpdate(Position position) {
    // Always broadcast to UI consumers
    _positionController.add(position);

    // Throttle HTTP sends based on mode
    final now = DateTime.now();
    final elapsed = now.difference(_lastSendTime);
    final threshold = _getSendThreshold(position);

    if (elapsed >= threshold) {
      _sendToBackend(position);
      _lastSendTime = now;
    }
  }

  Duration _getSendThreshold(Position position) {
    if (_mode == DriverLocationMode.idle) {
      return const Duration(seconds: 25);
    }
    // Active ride: adaptive based on speed (m/s → km/h)
    final speedKmh = (position.speed < 0 ? 0 : position.speed) * 3.6;
    if (speedKmh > 15) return const Duration(seconds: 5);
    if (speedKmh >= 2) return const Duration(seconds: 10);
    return const Duration(seconds: 25);
  }

  Future<void> _sendToBackend(Position position) async {
    try {
      await _apiService.post('/driver/location', data: {
        'latitude': position.latitude,
        'longitude': position.longitude,
        'heading': position.heading,
        'speed': position.speed,
      });
      if (kDebugMode) {
        final speedKmh = (position.speed < 0 ? 0 : position.speed) * 3.6;
        print('DriverLocationService: sent (${speedKmh.toStringAsFixed(1)} km/h, mode=$_mode)');
      }
    } catch (e) {
      if (kDebugMode) print('DriverLocationService: send failed: $e');
    }
  }

  LocationSettings _buildLocationSettings({
    required String notificationTitle,
    required String notificationText,
  }) {
    if (Platform.isAndroid) {
      return AndroidSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 10,
        intervalDuration: const Duration(seconds: 5),
        foregroundNotificationConfig: ForegroundNotificationConfig(
          notificationTitle: notificationTitle,
          notificationText: notificationText,
          notificationChannelName: 'Driver Location',
          notificationIcon: const AndroidResource(
            name: 'ic_launcher',
            defType: 'mipmap',
          ),
          setOngoing: true,
          enableWakeLock: true,
          enableWifiLock: true,
          color: const Color(0xFF004743),
        ),
      );
    }
    // iOS / fallback
    return const LocationSettings(
      accuracy: LocationAccuracy.high,
      distanceFilter: 10,
    );
  }
}

final driverLocationServiceProvider = Provider<DriverLocationService>((ref) {
  final apiService = ref.watch(apiServiceProvider);
  final service = DriverLocationService(apiService);
  ref.onDispose(() => service.dispose());
  return service;
});
