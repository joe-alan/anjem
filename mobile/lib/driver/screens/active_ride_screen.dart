import 'dart:async';
import 'package:action_slider/action_slider.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:mobile/l10n/app_localizations.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import '../../core/config/app_config.dart';
import '../../core/models/ride.dart';
import '../../core/models/lat_lng.dart';
import '../../core/providers/active_ride_provider.dart';
import '../../core/providers/api_provider.dart';
import '../../core/services/api/api_exception.dart';
import '../../core/providers/driver_status_provider.dart';
import '../../core/providers/credits_provider.dart';
import '../../core/providers/session_provider.dart';
import '../../core/models/session_state.dart';
import 'driver_home_screen.dart';
import '../../core/widgets/mapbox_map_widget.dart';
import '../../core/widgets/whatsapp_launcher.dart';
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
          if (kDebugMode) print('⚠️ [Driver] Route fetch error handled: $e');
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

  Future<void> _startLocationUpdates() async {
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
        if (kDebugMode) print('⚠️ [Driver] Initial route fetch error handled: $e');
      });
    } catch (e) {
      if (kDebugMode) print('Failed to get initial location: $e');
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
              if (kDebugMode) print('⚠️ [Driver] Route update on location change error: $e');
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

        if (kDebugMode) print('Driver location updated (${interval.inSeconds}s interval, ${speedKmh.toStringAsFixed(1)} km/h)');

        // Schedule next update after the adaptive interval
        if (mounted) {
          _locationUpdateTimer = Timer(interval, _scheduleNextLocationUpdate);
        }
      } catch (e) {
        if (kDebugMode) print('Failed to update location: $e');
        // Retry after default interval on error
        if (mounted) {
          _locationUpdateTimer = Timer(const Duration(seconds: 10), _scheduleNextLocationUpdate);
        }
      }
    });
  }

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

  void _showLocationPermissionDialog() {
    final l10n = AppLocalizations.of(context);
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        title: Text(l10n.locationPermissionTitle),
        content: Text(l10n.locationPermissionMessage),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text(l10n.cancel),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
              Geolocator.openLocationSettings();
            },
            child: Text(l10n.openSettings),
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
      if (kDebugMode) print('⚠️  [Driver] Cannot fetch route - ride: ${ride != null}, location: ${_currentDriverLocation != null}');
      return;
    }

    try {
      if (kDebugMode) print('🗺️  [Driver] Fetching route for ride ${ride.id}, status: ${ride.status}');

      List<LatLng>? routePoints;
      MapPolyline? polyline;

      if (ride.status == RideStatus.accepted) {
        final pickupLatLng = LatLng(
          ride.pickupLocation.coordinates.latitude,
          ride.pickupLocation.coordinates.longitude,
        );

        routePoints = await _directionsService.getRoute(
          origin: _currentDriverLocation!,
          destination: pickupLatLng,
        );

        if (routePoints.isEmpty) {
          if (kDebugMode) print('⚠️ [Driver] Route to pickup is empty - continuing without route line');
          return;
        }

        if (kDebugMode) print('✅ [Driver] Route to pickup fetched: ${routePoints.length} points');

        polyline = MapPolyline(
          id: 'route_to_pickup_${ride.id}',
          points: routePoints,
          color: Colors.blue,
          width: 4.0,
        );
      } else if (ride.status == RideStatus.inProgress) {
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
          if (kDebugMode) print('🗺️  [Driver] No backend geometry, fetching from Mapbox directly');
          routePoints = await _directionsService.getRoute(
            origin: pickupLatLng,
            destination: destLatLng,
          );
        }

        if (routePoints.isEmpty) {
          if (kDebugMode) print('⚠️ [Driver] Route to destination is empty - continuing without route line');
          return;
        }

        if (kDebugMode) print('✅ [Driver] Route to destination fetched: ${routePoints.length} points');

        polyline = MapPolyline(
          id: 'route_to_destination_${ride.id}',
          points: routePoints,
          color: Colors.green,
          width: 4.0,
        );
      }

      if (mounted && polyline != null) {
        setState(() {
          _polylines = {polyline!};
        });
        if (kDebugMode) print('✅ [Driver] Polylines updated');
      }
    } catch (e) {
      if (kDebugMode) print('❌ [Driver] Failed to fetch route: $e');
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

      if (kDebugMode) print('Ride status updated to: $status');

      if (mounted) {
        final l10n = AppLocalizations.of(context);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(l10n.statusUpdatedTo(_formatStatus(status, l10n))),
            backgroundColor: Colors.green,
          ),
        );
      }

      if (status == 'completed') {
        _handleRideCompletion();
      } else if (status != 'cancelled') {
        _fetchAndDisplayRoute().catchError((e) {
          if (kDebugMode) print('⚠️ [Driver] Status change route fetch error handled: $e');
        });
      }
    } catch (e) {
      if (kDebugMode) print('Failed to update ride status: $e');

      if (mounted) {
        final l10n = AppLocalizations.of(context);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(l10n.failedToUpdateStatus(e.toString())),
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

    final l10n = AppLocalizations.of(context);
    final adminReason = ride?.adminReason;
    final isAdmin = ride?.adminOverride == true;

    await showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        title: Text(l10n.rideCancelledTitle),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.cancel_outlined, color: Colors.red, size: 48),
            const SizedBox(height: 16),
            if (isAdmin && adminReason != null)
              Text(
                l10n.adminOverrideBanner(adminReason),
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 14),
              )
            else
              Text(
                l10n.rideCancelledByRiderSnackbar,
                textAlign: TextAlign.center,
              ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: Text(l10n.ok),
          ),
        ],
      ),
    );

    if (!mounted) return;
    _handleRideCancelled();
  }

  void _handleRideCancelled() {
    if (!mounted) return;
    ref.read(driverStatusProvider.notifier).setActiveRide(null);
    ref.read(sessionStateProvider.notifier).updateSessionState(
      SessionState(
        state: SessionStateType.idle,
        driverContext: DriverContext(isDriver: true, isOnline: true),
      ),
    );

    if (mounted) {
      final l10n = AppLocalizations.of(context);
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const DriverHomeScreen()),
        (route) => false,
      );

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(_isCancelling
              ? l10n.rideCancelledSnackbar
              : l10n.rideCancelledByRiderSnackbar),
          backgroundColor: Colors.orange,
          duration: const Duration(seconds: 3),
        ),
      );
    }
  }

  bool _isHandlingCompletion = false;

  Future<void> _handleRideCompletion() async {
    if (_isHandlingCompletion || !mounted) return;
    _isHandlingCompletion = true;
    try {
      // Fetch fresh balance before clearing active ride state — the credit was
      // deducted at accept time so balance is already 0 in the DB.
      ref.invalidate(creditsProvider);
      final balance = await ref.read(creditsProvider.future).catchError((_) => -1);

      if (balance == 0) {
        ref.read(driverStatusProvider.notifier).setActiveRide(null);
        await ref.read(driverStatusProvider.notifier).goOffline();
      } else {
        ref.read(driverStatusProvider.notifier).setActiveRide(null);
      }

      final shouldStayOnline = balance > 0;

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
      final l10n = AppLocalizations.of(context);
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const DriverHomeScreen()),
        (route) => false,
      );

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(l10n.rideCompletedSnackbar),
          backgroundColor: Colors.green,
          duration: const Duration(seconds: 3),
        ),
      );
    }
  }

  String _formatStatus(String status, AppLocalizations l10n) {
    switch (status) {
      case 'driver_arrived':
        return l10n.driverStatusArrivedAtPickup;
      case 'in_progress':
        return l10n.driverStatusRideInProgress;
      case 'completed':
        return l10n.driverStatusRideCompleted;
      default:
        return status;
    }
  }

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final l10n = AppLocalizations.of(context);
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
          if (kDebugMode) print('⚠️ [Driver] Listen route fetch error handled: $e');
        });
      }
    });

    final ride = rideState.ride;

    if (kDebugMode) print(
        'ActiveRideScreen: build - ride=${ride?.id}, isLoading=${rideState.isLoading}, error=${rideState.error}');

    if (ride == null) {
      if (rideState.error != null) {
        return Scaffold(
          appBar: AppBar(
            title: Text(l10n.activeRideTitle),
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
                  l10n.failedToLoadRide,
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
                  child: Text(l10n.goBackButton),
                ),
              ],
            ),
          ),
        );
      }

      return Scaffold(
        appBar: AppBar(
          title: Text(l10n.activeRideTitle),
          backgroundColor: config.primaryColor,
          foregroundColor: Colors.white,
        ),
        body: const Center(
          child: CircularProgressIndicator(),
        ),
      );
    }

    if (kDebugMode) print('ActiveRideScreen: Rendering map for ride ${ride.id}');
    if (kDebugMode) print('  Pickup: ${ride.pickupLocation.name}, Coords: ${ride.pickupLocation.coordinates.latitude}, ${ride.pickupLocation.coordinates.longitude}');
    if (kDebugMode) print('  Dest: ${ride.destinationLocation.name}, Coords: ${ride.destinationLocation.coordinates.latitude}, ${ride.destinationLocation.coordinates.longitude}');

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

          // Top status card (matches rider layout: status dot + text only)
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            child: SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Card(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 16.0, vertical: 12.0),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
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
                                _getStatusText(ride.status, l10n),
                                style: const TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),
                            if (ride.status == RideStatus.accepted ||
                                ride.status == RideStatus.driverArrived)
                              IconButton(
                                icon: const Icon(Icons.close),
                                onPressed: () => _showCancelDialog(l10n),
                                tooltip: l10n.cancelRideTooltip,
                              ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        Center(
                          child: Text(
                            l10n.currencyFormat(ride.fare.toStringAsFixed(0)),
                            style: TextStyle(
                              fontSize: 28,
                              fontWeight: FontWeight.bold,
                              color: config.primaryColor,
                            ),
                          ),
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
              top: 110,
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
                        l10n.adminOverrideBanner(ride.adminReason!),
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

          // Recenter button
          Positioned(
            bottom: 250,
            right: 16,
            child: FloatingActionButton.small(
              heroTag: 'recenter_driver',
              backgroundColor: Colors.white,
              onPressed: () => _fitBounds(ride),
              tooltip: l10n.recenterMap,
              child: Icon(Icons.my_location, color: config.primaryColor),
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
                    // Rider info row
                    Row(
                      children: [
                        CircleAvatar(
                          radius: 22,
                          backgroundColor:
                              config.primaryColor.withValues(alpha: 0.12),
                          child: Icon(Icons.person,
                              color: config.primaryColor, size: 22),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                ride.rider?.name ?? l10n.riderFallback,
                                style: const TextStyle(
                                  fontSize: 15,
                                  fontWeight: FontWeight.bold,
                                ),
                                overflow: TextOverflow.ellipsis,
                              ),
                            ],
                          ),
                        ),
                        IconButton(
                          icon: FaIcon(
                            FontAwesomeIcons.whatsapp,
                            color: (ride.rider?.phone != null && ride.rider!.phone!.trim().isNotEmpty)
                                ? const Color(0xFF25D366)
                                : Colors.grey,
                          ),
                          padding: EdgeInsets.zero,
                          constraints: const BoxConstraints(),
                          tooltip: (ride.rider?.phone == null || ride.rider!.phone!.trim().isEmpty)
                              ? l10n.riderNoPhone
                              : null,
                          onPressed: (ride.rider?.phone != null && ride.rider!.phone!.trim().isNotEmpty)
                              ? () => openWhatsApp(context, ride.rider?.phone, ride.rider?.name ?? l10n.riderFallback)
                              : null,
                        ),
                      ],
                    ),

                    const Divider(height: 16),

                    // Route info
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Container(
                              width: 10,
                              height: 10,
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
                                  fontSize: 13,
                                  fontWeight: FontWeight.w500,
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 6),
                        Row(
                          children: [
                            Container(
                              width: 10,
                              height: 10,
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
                                  fontSize: 13,
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

                    const Divider(height: 16),

                    // Action slider based on current status
                    _buildActionButton(ride.status, l10n),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildActionButton(RideStatus status, AppLocalizations l10n) {
    switch (status) {
      case RideStatus.accepted:
        return ActionSlider.standard(
          key: const ValueKey('slider_accepted'),
          sliderBehavior: SliderBehavior.stretch,
          backgroundColor: Colors.orange.shade50,
          toggleColor: Colors.orange,
          icon: const Icon(Icons.arrow_forward_ios, color: Colors.white),
          loadingIcon: const SizedBox(
            width: 24,
            height: 24,
            child: CircularProgressIndicator(
              strokeWidth: 2.5,
              color: Colors.white,
            ),
          ),
          successIcon: const Icon(Icons.check_rounded, color: Colors.white),
          child: Text(
            l10n.markAsArrivedFab,
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.bold,
              color: Colors.orange,
            ),
          ),
          action: (controller) async {
            controller.loading();
            try {
              final apiService = ref.read(apiServiceProvider);
              await apiService.patch(
                '/rides/${widget.rideId}/status',
                data: {'status': 'driver_arrived'},
              );
              try { controller.success(); } catch (_) {}
              HapticFeedback.mediumImpact();
              if (mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(l10n.statusUpdatedTo(
                        _formatStatus('driver_arrived', l10n))),
                    backgroundColor: Colors.green,
                  ),
                );
                _fetchAndDisplayRoute().catchError((_) {});
              }
            } catch (e) {
              try { controller.failure(); } catch (_) {}
              await Future.delayed(const Duration(seconds: 2));
              if (mounted) {
                try { controller.reset(); } catch (_) {}
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(_parseStatusError(e, l10n)),
                    backgroundColor: Colors.red,
                  ),
                );
              }
            }
          },
        );

      case RideStatus.driverArrived:
        return ActionSlider.standard(
          key: const ValueKey('slider_arrived'),
          sliderBehavior: SliderBehavior.stretch,
          backgroundColor: Colors.blue.shade50,
          toggleColor: Colors.blue,
          icon: const Icon(Icons.arrow_forward_ios, color: Colors.white),
          loadingIcon: const SizedBox(
            width: 24,
            height: 24,
            child: CircularProgressIndicator(
              strokeWidth: 2.5,
              color: Colors.white,
            ),
          ),
          successIcon: const Icon(Icons.check_rounded, color: Colors.white),
          child: Text(
            l10n.startRideFab,
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.bold,
              color: Colors.blue,
            ),
          ),
          action: (controller) async {
            controller.loading();
            try {
              final apiService = ref.read(apiServiceProvider);
              await apiService.patch(
                '/rides/${widget.rideId}/status',
                data: {'status': 'in_progress'},
              );
              try { controller.success(); } catch (_) {}
              HapticFeedback.mediumImpact();
              if (mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(l10n.statusUpdatedTo(
                        _formatStatus('in_progress', l10n))),
                    backgroundColor: Colors.green,
                  ),
                );
                _fetchAndDisplayRoute().catchError((_) {});
              }
            } catch (e) {
              try { controller.failure(); } catch (_) {}
              await Future.delayed(const Duration(seconds: 2));
              if (mounted) {
                try { controller.reset(); } catch (_) {}
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(_parseStatusError(e, l10n)),
                    backgroundColor: Colors.red,
                  ),
                );
              }
            }
          },
        );

      case RideStatus.inProgress:
        return ActionSlider.standard(
          key: const ValueKey('slider_in_progress'),
          sliderBehavior: SliderBehavior.stretch,
          backgroundColor: Colors.green.shade50,
          toggleColor: Colors.green,
          icon: const Icon(Icons.arrow_forward_ios, color: Colors.white),
          loadingIcon: const SizedBox(
            width: 24,
            height: 24,
            child: CircularProgressIndicator(
              strokeWidth: 2.5,
              color: Colors.white,
            ),
          ),
          successIcon: const Icon(Icons.check_rounded, color: Colors.white),
          child: Text(
            l10n.completeRideFab,
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.bold,
              color: Colors.green,
            ),
          ),
          action: (controller) async {
            controller.loading();
            try {
              final apiService = ref.read(apiServiceProvider);
              await apiService.patch(
                '/rides/${widget.rideId}/status',
                data: {'status': 'completed'},
              );
              try { controller.success(); } catch (_) {}
              HapticFeedback.heavyImpact();
              if (mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(l10n.statusUpdatedTo(
                        _formatStatus('completed', l10n))),
                    backgroundColor: Colors.green,
                  ),
                );
                _handleRideCompletion();
              }
            } catch (e) {
              try { controller.failure(); } catch (_) {}
              await Future.delayed(const Duration(seconds: 2));
              if (mounted) {
                try { controller.reset(); } catch (_) {}
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(_parseStatusError(e, l10n)),
                    backgroundColor: Colors.red,
                  ),
                );
              }
            }
          },
        );

      default:
        return const SizedBox.shrink();
    }
  }

  String _parseStatusError(Object error, AppLocalizations l10n) {
    if (error is ApiException) {
      if (error.statusCode == 400) return l10n.errorUnexpectedRideState;
      if (error.statusCode == 404) return l10n.errorRideNotFound;
      if (error.statusCode == 403) return l10n.errorPermissionDenied;
      return error.message;
    }
    return l10n.failedToUpdateStatus(error.toString());
  }

  void _buildMarkers(Ride ride) {
    if (kDebugMode) print('🎯 [Driver] Building markers for ride ${ride.id}');

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

    if (kDebugMode) print('✅ [Driver] Built ${_markers.length} markers');
  }

  void _fitBounds(Ride ride) {
    if (_mapController == null) return;

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

  String _getStatusText(RideStatus status, AppLocalizations l10n) {
    switch (status) {
      case RideStatus.accepted:
        return l10n.driverStatusDrivingToPickup;
      case RideStatus.driverArrived:
        return l10n.driverStatusArrivedAtPickup;
      case RideStatus.inProgress:
        return l10n.driverStatusRideInProgress;
      case RideStatus.completed:
        return l10n.driverStatusRideCompleted;
      case RideStatus.cancelled:
        return l10n.driverStatusRideCancelled;
    }
  }

  void _showCancelDialog(AppLocalizations l10n) {
    showDialog(
      context: context,
      builder: (BuildContext context) {
        return AlertDialog(
          title: Text(l10n.cancelRideActiveTitle),
          content: Text(l10n.cancelRideActiveConfirmMessage),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.of(context).pop();
              },
              child: Text(l10n.noContinueButton),
            ),
            TextButton(
              onPressed: () async {
                Navigator.of(context).pop();
                _isCancelling = true;
                await _updateRideStatus('cancelled');
              },
              child: Text(
                l10n.yesCancelRide,
                style: const TextStyle(color: Colors.red),
              ),
            ),
          ],
        );
      },
    );
  }
}
