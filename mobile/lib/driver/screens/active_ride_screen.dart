import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import '../../core/config/app_config.dart';
import '../../core/models/ride.dart';
import '../../core/models/lat_lng.dart';
import '../../core/providers/active_ride_provider.dart';
import '../../core/providers/api_provider.dart';
import '../../core/providers/driver_status_provider.dart';
import '../../core/providers/credits_provider.dart';
import '../../core/providers/session_provider.dart';
import '../../core/models/session_state.dart';
import '../../core/widgets/mapbox_map_widget.dart';
import '../../core/services/mapbox/mapbox_directions_service.dart';

class ActiveRideScreen extends ConsumerStatefulWidget {
  final int rideId;

  const ActiveRideScreen({
    super.key,
    required this.rideId,
  });

  @override
  ConsumerState<ActiveRideScreen> createState() => _ActiveRideScreenState();
}

class _ActiveRideScreenState extends ConsumerState<ActiveRideScreen> {
  MapboxMapController? _mapController;
  Set<MapMarker> _markers = {};
  Set<MapPolyline> _polylines = {};
  Timer? _locationUpdateTimer;
  bool _isUpdatingStatus = false;
  bool _isCancelling = false;
  final MapboxDirectionsService _directionsService = MapboxDirectionsService();
  LatLng? _currentDriverLocation;

  @override
  void initState() {
    super.initState();
    _startLocationUpdates();

    // Load ride data from API
    Future.microtask(() async {
      await ref.read(activeRideProvider.notifier).loadRide(widget.rideId);
      // Build markers and fetch route once ride data is loaded
      final ride = ref.read(activeRideProvider).ride;
      if (ride != null) {
        setState(() {
          _buildMarkers(ride);
        });
        _fetchAndDisplayRoute().catchError((e) {
          print('⚠️ [Driver] Route fetch error handled: $e');
        });
      }
    });
  }

  @override
  void dispose() {
    _locationUpdateTimer?.cancel();
    _mapController?.dispose();
    super.dispose();
  }

  // ✅ FIX: Add permission check before starting location updates
  Future<void> _startLocationUpdates() async {
    // Check permissions first
    final hasPermission = await _checkLocationPermission();
    if (!hasPermission) {
      _showLocationPermissionDialog();
      return;
    }

    // Get initial location
    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          distanceFilter: 10,
        ),
      );
      _currentDriverLocation = LatLng(position.latitude, position.longitude);
      // Fetch route for initial position
      _fetchAndDisplayRoute().catchError((e) {
        print('⚠️ [Driver] Initial route fetch error handled: $e');
      });
    } catch (e) {
      print('Failed to get initial location: $e');
    }

    // Start adaptive location update loop
    _scheduleNextLocationUpdate();
  }

  // Adaptive location update interval based on speed:
  //   > 15 km/h  →  5 s  (fast-moving, high precision)
  //   2–15 km/h  → 10 s  (normal driving)
  //   < 2 km/h   → 30 s  (stationary/slow, save battery)
  void _scheduleNextLocationUpdate() {
    if (!mounted) return;
    Future.delayed(const Duration(seconds: 5), () async {
      if (!mounted) return;
      try {
        final position = await Geolocator.getCurrentPosition(
          locationSettings: const LocationSettings(
            accuracy: LocationAccuracy.high,
            distanceFilter: 10,
          ),
        );

        // Update current driver location
        if (mounted) {
          setState(() {
            _currentDriverLocation = LatLng(position.latitude, position.longitude);
          });
          // Redraw route from new driver position (only for accepted — dynamic route)
          final currentStatus = ref.read(activeRideProvider).ride?.status;
          if (currentStatus == RideStatus.accepted) {
            _fetchAndDisplayRoute().catchError((e) {
              print('⚠️ [Driver] Route update on location change error: $e');
            });
          }
        }

        // Determine adaptive interval from speed (m/s → km/h)
        final speedKmh = (position.speed < 0 ? 0 : position.speed) * 3.6;
        final interval = speedKmh > 15
            ? const Duration(seconds: 5)
            : speedKmh >= 2
                ? const Duration(seconds: 10)
                : const Duration(seconds: 30);

        final apiService = ref.read(apiServiceProvider);
        await apiService.post('/driver/location', data: {
          'latitude': position.latitude,
          'longitude': position.longitude,
          'heading': position.heading,
          'speed': position.speed,
        });

        print('Driver location updated (${interval.inSeconds}s interval, ${speedKmh.toStringAsFixed(1)} km/h)');

        // Schedule next update after the adaptive interval
        if (mounted) {
          _locationUpdateTimer = Timer(interval, _scheduleNextLocationUpdate);
        }
      } catch (e) {
        print('Failed to update location: $e');
        // Retry after default interval on error
        if (mounted) {
          _locationUpdateTimer = Timer(const Duration(seconds: 10), _scheduleNextLocationUpdate);
        }
      }
    });
  }

  // ✅ FIX: Check and request location permission
  Future<bool> _checkLocationPermission() async {
    LocationPermission permission = await Geolocator.checkPermission();

    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }

    if (permission == LocationPermission.deniedForever) {
      return false;
    }

    return permission == LocationPermission.whileInUse ||
        permission == LocationPermission.always;
  }

  // ✅ FIX: Show dialog to open settings if permission denied
  void _showLocationPermissionDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        title: const Text('Location Permission Required'),
        content: const Text(
          'This app needs location permission to track your ride and update your position. '
          'Please enable location access in Settings.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
              Geolocator.openLocationSettings();
            },
            child: const Text('Open Settings'),
          ),
        ],
      ),
    );
  }

  /// Fetch and display route based on current ride status
  Future<void> _fetchAndDisplayRoute() async {
    final rideState = ref.read(activeRideProvider);
    final ride = rideState.ride;

    if (ride == null || _currentDriverLocation == null) {
      print('⚠️  [Driver] Cannot fetch route - ride: ${ride != null}, location: ${_currentDriverLocation != null}');
      return;
    }

    try {
      print('🗺️  [Driver] Fetching route for ride ${ride.id}, status: ${ride.status}');

      // Determine what route to fetch based on ride status
      List<LatLng>? routePoints;
      MapPolyline? polyline;

      if (ride.status == RideStatus.accepted) {
        // Driver is heading to pickup - show route from driver to pickup
        final pickupLatLng = LatLng(
          ride.pickupLocation.coordinates.latitude,
          ride.pickupLocation.coordinates.longitude,
        );

        routePoints = await _directionsService.getRoute(
          origin: _currentDriverLocation!,
          destination: pickupLatLng,
        );

        // Handle empty route (network error, DNS failure, etc.)
        if (routePoints.isEmpty) {
          print('⚠️ [Driver] Route to pickup is empty - continuing without route line');
          return;
        }

        print('✅ [Driver] Route to pickup fetched: ${routePoints.length} points');

        // Create route polyline (blue for driving to pickup)
        polyline = MapPolyline(
          id: 'route_to_pickup_${ride.id}',  // ✅ Unique ID per ride
          points: routePoints,
          color: Colors.blue,
          width: 4.0,
        );
      } else if (ride.status == RideStatus.inProgress) {
        // Ride is in progress - show route from pickup to destination
        final pickupLatLng = LatLng(
          ride.pickupLocation.coordinates.latitude,
          ride.pickupLocation.coordinates.longitude,
        );
        final destLatLng = LatLng(
          ride.destinationLocation.coordinates.latitude,
          ride.destinationLocation.coordinates.longitude,
        );

        // Use backend geometry first (pickup→destination is fixed, already cached)
        routePoints = ride.routeCoordinates ?? [];

        if (routePoints.isEmpty) {
          print('🗺️  [Driver] No backend geometry, fetching from Mapbox directly');
          routePoints = await _directionsService.getRoute(
            origin: pickupLatLng,
            destination: destLatLng,
          );
        }

        if (routePoints.isEmpty) {
          print('⚠️ [Driver] Route to destination is empty - continuing without route line');
          return;
        }

        print('✅ [Driver] Route to destination fetched: ${routePoints.length} points');

        // Create route polyline (green for in progress)
        polyline = MapPolyline(
          id: 'route_to_destination_${ride.id}',  // ✅ Unique ID per ride
          points: routePoints,
          color: Colors.green,
          width: 4.0,
        );
      }

      // Create NEW set with polyline (important for Flutter to detect changes)
      if (mounted && polyline != null) {
        setState(() {
          _polylines = {polyline!};
        });
        print('✅ [Driver] Polylines updated');
      }
    } catch (e) {
      print('❌ [Driver] Failed to fetch route: $e');
      // Don't show error to user, just log it
    }
  }

  Future<void> _updateRideStatus(String status) async {
    if (_isUpdatingStatus) return;

    setState(() {
      _isUpdatingStatus = true;
    });

    try {
      final apiService = ref.read(apiServiceProvider);

      await apiService.patch(
        '/rides/${widget.rideId}/status',
        data: {'status': status},
      );

      print('Ride status updated to: $status');

      // WebSocket will automatically update the ride state via broadcast events

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Status updated to ${_formatStatus(status)}'),
            backgroundColor: Colors.green,
          ),
        );
      }

      // If completed, navigate back to home
      if (status == 'completed') {
        _handleRideCompletion();
      } else if (status != 'cancelled') {
        // Refresh route for new status (skip on cancel — WS listener handles navigation)
        _fetchAndDisplayRoute().catchError((e) {
          print('⚠️ [Driver] Status change route fetch error handled: $e');
        });
      }
    } catch (e) {
      print('Failed to update ride status: $e');

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Failed to update status: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isUpdatingStatus = false;
        });
      }
    }
  }

  Future<void> _showCancellationInfo(Ride? ride) async {
    if (!mounted) return;

    final adminReason = ride?.adminReason;
    final isAdmin = ride?.adminOverride == true;

    await showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        title: const Text('Ride Cancelled'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.cancel_outlined, color: Colors.red, size: 48),
            const SizedBox(height: 16),
            if (isAdmin && adminReason != null)
              Text(
                'Admin reason: $adminReason',
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 14),
              )
            else
              const Text(
                'This ride has been cancelled.',
                textAlign: TextAlign.center,
              ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('OK'),
          ),
        ],
      ),
    );

    if (!mounted) return;
    _handleRideCancelled();
  }

  void _handleRideCancelled() {
    ref.read(driverStatusProvider.notifier).setActiveRide(null);
    ref.read(sessionStateProvider.notifier).updateSessionState(
      SessionState(
        state: SessionStateType.idle,
        driverContext: DriverContext(isDriver: true, isOnline: true),
      ),
    );

    if (mounted) {
      Navigator.of(context).popUntil((route) => route.isFirst);

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(_isCancelling ? 'Ride cancelled' : 'Ride was cancelled by the rider'),
          backgroundColor: Colors.orange,
          duration: const Duration(seconds: 3),
        ),
      );
    }
  }

  bool _isHandlingCompletion = false;

  Future<void> _handleRideCompletion() async {
    if (_isHandlingCompletion) return;
    _isHandlingCompletion = true;
    try {
      // Fetch fresh balance before clearing active ride state — the credit was
      // deducted at accept time so balance is already 0 in the DB.
      ref.invalidate(creditsProvider);
      final balance = await ref.read(creditsProvider.future).catchError((_) => -1);

      if (balance == 0) {
        // Zero credits: go offline immediately without waiting for the WS event.
        // Clear hasActiveRide first so goOffline() doesn't block on it.
        ref.read(driverStatusProvider.notifier).setActiveRide(null);
        await ref.read(driverStatusProvider.notifier).goOffline();
      } else {
        // Credits remain: return to online/queue state.
        ref.read(driverStatusProvider.notifier).setActiveRide(null);
      }

      final shouldStayOnline = balance > 0;

      // Clear session rideActive state so session_check_wrapper reactively
      // navigates to home in both the normal push-flow and session-restore flow.
      ref.read(sessionStateProvider.notifier).updateSessionState(
        SessionState(
          state: SessionStateType.idle,
          driverContext: DriverContext(isDriver: true, isOnline: shouldStayOnline),
        ),
      );
    } finally {
      _isHandlingCompletion = false;
    }

    if (mounted) {
      Navigator.of(context).popUntil((route) => route.isFirst);

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Ride completed! 🎉'),
          backgroundColor: Colors.green,
          duration: Duration(seconds: 3),
        ),
      );
    }
  }

  String _formatStatus(String status) {
    switch (status) {
      case 'driver_arrived':
        return 'Arrived at pickup';
      case 'in_progress':
        return 'In progress';
      case 'completed':
        return 'Completed';
      default:
        return status;
    }
  }

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final rideState = ref.watch(activeRideProvider);

    // Listen for ride status changes to update markers/routes, or dismiss on terminal states
    ref.listen<ActiveRideState>(activeRideProvider, (previous, next) {
      if (previous?.ride?.status == next.ride?.status) return;

      final newStatus = next.ride?.status;

      if (newStatus == RideStatus.cancelled) {
        if (_isCancelling) {
          _handleRideCancelled();
        } else {
          _showCancellationInfo(next.ride);
        }
        return;
      }

      if (newStatus == RideStatus.completed) {
        _handleRideCompletion();
        return;
      }

      if (next.ride != null) {
        setState(() {
          _buildMarkers(next.ride!);
        });
        _fetchAndDisplayRoute().catchError((e) {
          print('⚠️ [Driver] Listen route fetch error handled: $e');
        });
      }
    });

    final ride = rideState.ride;

    print(
        'ActiveRideScreen: build - ride=${ride?.id}, isLoading=${rideState.isLoading}, error=${rideState.error}');

    if (ride == null) {
      if (rideState.error != null) {
        return Scaffold(
          appBar: AppBar(
            title: const Text('Active Ride'),
            backgroundColor: config.primaryColor,
            foregroundColor: Colors.white,
          ),
          body: Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.error_outline, size: 64, color: Colors.red),
                const SizedBox(height: 16),
                Text(
                  'Failed to load ride',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 8),
                Padding(
                  padding: const EdgeInsets.all(16.0),
                  child: Text(
                    rideState.error!,
                    textAlign: TextAlign.center,
                  ),
                ),
                const SizedBox(height: 16),
                ElevatedButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text('Go Back'),
                ),
              ],
            ),
          ),
        );
      }

      return Scaffold(
        appBar: AppBar(
          title: const Text('Active Ride'),
          backgroundColor: config.primaryColor,
          foregroundColor: Colors.white,
        ),
        body: const Center(
          child: CircularProgressIndicator(),
        ),
      );
    }

    print(
        'ActiveRideScreen: Rendering map for ride ${ride.id}');
    print(
        '  Pickup: ${ride.pickupLocation.name}, Coords: ${ride.pickupLocation.coordinates.latitude}, ${ride.pickupLocation.coordinates.longitude}');
    print(
        '  Dest: ${ride.destinationLocation.name}, Coords: ${ride.destinationLocation.coordinates.latitude}, ${ride.destinationLocation.coordinates.longitude}');

    // Initial camera position (pickup location)
    final pickupCoords = ride.pickupLocation.coordinates;

    return Scaffold(
      body: Stack(
        children: [
          // Map
          MapboxMapWidget(
            initialCameraPosition: CameraPosition(
              latitude: pickupCoords.latitude,
              longitude: pickupCoords.longitude,
              zoom: 15,
            ),
            markers: _markers,
            polylines: _polylines,
            myLocationEnabled: true,
            myLocationButtonEnabled: false,
            zoomControlsEnabled: false,
            onMapCreated: (controller) {
              _mapController = controller;
              _fitBounds(ride);
            },
          ),

          // Top status card
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            child: SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16.0),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Container(
                              width: 8,
                              height: 8,
                              decoration: BoxDecoration(
                                color: _getStatusColor(ride.status),
                                shape: BoxShape.circle,
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                _getStatusText(ride.status),
                                style: const TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),
                            IconButton(
                              icon: const Icon(Icons.close),
                              onPressed: () {
                                _showCancelDialog();
                              },
                              tooltip: 'Cancel ride',
                            ),
                          ],
                        ),
                        const Divider(height: 16),
                        Row(
                          children: [
                            Icon(Icons.person,
                                size: 16, color: Colors.grey[600]),
                            const SizedBox(width: 4),
                            Text(
                              ride.rider?.name ?? 'Rider',
                              style:
                                  const TextStyle(fontWeight: FontWeight.w500),
                            ),
                            const Spacer(),
                            Icon(Icons.attach_money,
                                size: 16, color: config.primaryColor),
                            Text(
                              'Rp ${_formatCurrency(ride.fare)}',
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                color: config.primaryColor,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),

          // Admin override banner
          if (ride.adminOverride == true && ride.adminReason != null)
            Positioned(
              top: 130,
              left: 16,
              right: 16,
              child: Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.orange.shade100,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.orange.shade900, width: 1),
                ),
                child: Row(
                  children: [
                    Icon(Icons.admin_panel_settings,
                        color: Colors.orange.shade900, size: 20),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        'Admin Override: ${ride.adminReason}',
                        style: TextStyle(
                          fontSize: 13,
                          color: Colors.orange.shade900,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),

          // Bottom action card
          Positioned(
            bottom: 16,
            left: 16,
            right: 16,
            child: Card(
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Route info
                    Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Container(
                                    width: 12,
                                    height: 12,
                                    decoration: const BoxDecoration(
                                      color: Colors.green,
                                      shape: BoxShape.circle,
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      ride.pickupLocation.name,
                                      style: const TextStyle(
                                        fontSize: 14,
                                        fontWeight: FontWeight.w500,
                                      ),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 8),
                              Row(
                                children: [
                                  Container(
                                    width: 12,
                                    height: 12,
                                    decoration: const BoxDecoration(
                                      color: Colors.red,
                                      shape: BoxShape.circle,
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      ride.destinationLocation.name,
                                      style: const TextStyle(
                                        fontSize: 14,
                                        fontWeight: FontWeight.w500,
                                      ),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),

                    const Divider(height: 24),

                    // Action button based on current status
                    _buildActionButton(ride.status),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildActionButton(RideStatus status) {
    if (_isUpdatingStatus) {
      return const Center(
        child: Padding(
          padding: EdgeInsets.all(12.0),
          child: CircularProgressIndicator(),
        ),
      );
    }

    switch (status) {
      case RideStatus.accepted:
        return ElevatedButton.icon(
          onPressed: () => _updateRideStatus('driver_arrived'),
          icon: const Icon(Icons.pin_drop),
          label: const Text('Mark as Arrived'),
          style: ElevatedButton.styleFrom(
            backgroundColor: Colors.orange,
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(vertical: 16),
            textStyle: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.bold,
            ),
          ),
        );

      case RideStatus.driverArrived:
        return ElevatedButton.icon(
          onPressed: () => _updateRideStatus('in_progress'),
          icon: const Icon(Icons.play_arrow),
          label: const Text('Start Ride'),
          style: ElevatedButton.styleFrom(
            backgroundColor: Colors.blue,
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(vertical: 16),
            textStyle: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.bold,
            ),
          ),
        );

      case RideStatus.inProgress:
        return ElevatedButton.icon(
          onPressed: () => _updateRideStatus('completed'),
          icon: const Icon(Icons.check_circle),
          label: const Text('Complete Ride'),
          style: ElevatedButton.styleFrom(
            backgroundColor: Colors.green,
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(vertical: 16),
            textStyle: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.bold,
            ),
          ),
        );

      default:
        return const SizedBox.shrink();
    }
  }

  void _buildMarkers(Ride ride) {
    print('🎯 [Driver] Building markers for ride ${ride.id}');

    _markers = {
      // Rider waiting at pickup
      MapMarker(
        id: 'rider',
        latitude: ride.pickupLocation.coordinates.latitude,
        longitude: ride.pickupLocation.coordinates.longitude,
        icon: 'marker',
        size: 1.5,
      ),
      // Destination
      MapMarker(
        id: 'destination',
        latitude: ride.destinationLocation.coordinates.latitude,
        longitude: ride.destinationLocation.coordinates.longitude,
        icon: 'marker',
        size: 1.5,
      ),
    };

    print('✅ [Driver] Built ${_markers.length} markers');
  }

  void _fitBounds(Ride ride) {
    if (_mapController == null) return;

    // Calculate center point
    final lats = [
      ride.pickupLocation.coordinates.latitude,
      ride.destinationLocation.coordinates.latitude,
    ];
    final lngs = [
      ride.pickupLocation.coordinates.longitude,
      ride.destinationLocation.coordinates.longitude,
    ];

    final centerLat = lats.reduce((a, b) => a + b) / lats.length;
    final centerLng = lngs.reduce((a, b) => a + b) / lngs.length;

    _mapController!.animateCamera(
      CameraPosition(
        latitude: centerLat,
        longitude: centerLng,
        zoom: 13,
      ),
    );
  }

  Color _getStatusColor(RideStatus status) {
    switch (status) {
      case RideStatus.accepted:
        return Colors.blue;
      case RideStatus.driverArrived:
        return Colors.orange;
      case RideStatus.inProgress:
        return Colors.green;
      case RideStatus.completed:
        return Colors.grey;
      case RideStatus.cancelled:
        return Colors.red;
    }
  }

  String _getStatusText(RideStatus status) {
    switch (status) {
      case RideStatus.accepted:
        return 'Driving to pickup';
      case RideStatus.driverArrived:
        return 'Arrived at pickup';
      case RideStatus.inProgress:
        return 'Ride in progress';
      case RideStatus.completed:
        return 'Ride completed';
      case RideStatus.cancelled:
        return 'Ride cancelled';
    }
  }

  String _formatCurrency(double amount) {
    if (amount >= 1000000) {
      return '${(amount / 1000000).toStringAsFixed(1)}M';
    } else if (amount >= 1000) {
      return '${(amount / 1000).toStringAsFixed(1)}K';
    }
    return amount.toStringAsFixed(0);
  }

  void _showCancelDialog() {
    showDialog(
      context: context,
      builder: (BuildContext context) {
        return AlertDialog(
          title: const Text('Cancel Ride'),
          content: const Text(
            'Are you sure you want to cancel this ride? This may affect your driver rating.',
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.of(context).pop();
              },
              child: const Text('No, Continue'),
            ),
            TextButton(
              onPressed: () async {
                Navigator.of(context).pop();
                _isCancelling = true;
                await _updateRideStatus('cancelled');
              },
              child: const Text(
                'Yes, Cancel',
                style: TextStyle(color: Colors.red),
              ),
            ),
          ],
        );
      },
    );
  }
}
