import 'dart:async';
import 'dart:math';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile/l10n/app_localizations.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import '../../core/config/app_config.dart';
import '../../core/widgets/whatsapp_launcher.dart';
import '../../core/models/ride.dart';
import '../../core/models/lat_lng.dart';
import '../../core/providers/active_ride_provider.dart';
import '../../core/providers/ride_request_provider.dart';
import '../../core/widgets/mapbox_map_widget.dart';
import '../../core/services/mapbox/mapbox_directions_service.dart';
import 'completed_screen.dart';
import 'rider_home_screen.dart';

/// Unified rider screen that shows the map with driver tracking
/// and displays driver info/status as overlays
class RiderActiveRideScreen extends ConsumerStatefulWidget {
  final Ride initialRide;

  const RiderActiveRideScreen({
    super.key,
    required this.initialRide,
  });

  @override
  ConsumerState<RiderActiveRideScreen> createState() =>
      _RiderActiveRideScreenState();
}

class _RiderActiveRideScreenState extends ConsumerState<RiderActiveRideScreen> {
  MapboxMapController? _mapController;
  Set<MapMarker> _markers = {};
  Set<MapPolyline> _polylines = {};
  final MapboxDirectionsService _directionsService = MapboxDirectionsService();
  bool _showDriverMatchedPopup = true;
  bool _isCancelling = false;
  Timer? _statusPollingTimer;
  Timer? _autoDismissTimer;
  static const _pollInterval = Duration(seconds: 5);

  @override
  void initState() {
    super.initState();

    debugPrint('🔄 [Rider] RiderActiveRideScreen initState for ride ${widget.initialRide.id}');

    // ✅ Clear any previous ride's polylines
    _polylines = {};

    // Build initial markers
    _buildMarkers(widget.initialRide, null);

    // Set initial ride in provider, then immediately fetch full data from API
    // (the initial ride from WebSocket may lack driver phone/photo)
    Future.microtask(() {
      debugPrint('📝 [Rider] Setting ride ${widget.initialRide.id} in provider');
      ref.read(activeRideProvider.notifier).setRide(widget.initialRide);
      _refreshFullRide();
      _fetchAndDisplayRoute().catchError((e) {
        // Silently handle - error already logged inside _fetchAndDisplayRoute
        debugPrint('⚠️ [Rider] Route fetch error handled: $e');
      });
    });

    // Auto-dismiss driver matched popup after 5 seconds
    _autoDismissTimer = Timer(const Duration(seconds: 5), () {
      if (mounted && _showDriverMatchedPopup) {
        setState(() => _showDriverMatchedPopup = false);
      }
    });

    // Start polling as fallback for WebSocket (in case WS is disconnected)
    _startStatusPolling();
  }

  /// Fetch full ride data from API to fill in fields missing from WebSocket
  Future<void> _refreshFullRide() async {
    try {
      final rideService = ref.read(rideServiceProvider);
      final fullRide = await rideService.getRide(widget.initialRide.id);
      if (mounted) {
        ref.read(activeRideProvider.notifier).setRide(fullRide);
      }
    } catch (e) {
      debugPrint('⚠️ [Rider] Full ride refresh failed: $e');
    }
  }

  void _startStatusPolling() {
    _statusPollingTimer?.cancel();
    _statusPollingTimer = Timer.periodic(_pollInterval, (_) => _pollRideStatus());
  }

  Future<void> _pollRideStatus() async {
    if (!mounted) return;
    try {
      final rideService = ref.read(rideServiceProvider);
      final updatedRide = await rideService.getRide(widget.initialRide.id);

      if (!mounted) return;

      // Check if status changed
      final currentRide = ref.read(activeRideProvider).ride;
      if (updatedRide.status != currentRide?.status) {
        debugPrint('🔄 [Rider] Poll detected status change: ${currentRide?.status} → ${updatedRide.status}');

        // Update provider with new ride data
        ref.read(activeRideProvider.notifier).setRide(updatedRide);

        // Handle terminal states
        if (updatedRide.status == RideStatus.completed) {
          _statusPollingTimer?.cancel();
          Navigator.of(context).pushReplacement(
            MaterialPageRoute(
              builder: (context) => CompletedScreen(ride: updatedRide),
            ),
          );
        } else if (updatedRide.status == RideStatus.cancelled) {
          _statusPollingTimer?.cancel();
          if (_isCancelling) {
            Navigator.of(context).pushAndRemoveUntil(
              MaterialPageRoute(builder: (context) => const RiderHomeScreen()),
              (route) => false,
            );
          } else {
            _showCancellationInfo(updatedRide);
          }
        }
      }
    } catch (e) {
      debugPrint('⚠️ [Rider] Poll error (will retry): $e');
      // Don't stop polling on error - will retry next interval
    }
  }

  @override
  void dispose() {
    _autoDismissTimer?.cancel();
    _statusPollingTimer?.cancel();
    _polylines = {};
    _markers = {};
    _mapController?.dispose();
    super.dispose();
  }

  /// Fetch and display route based on ride status
  Future<void> _fetchAndDisplayRoute() async {
    final rideState = ref.read(activeRideProvider);
    final ride = rideState.ride ?? widget.initialRide;

    debugPrint('🗺️  [Rider] _fetchAndDisplayRoute — status: ${ride.status}');

    try {
      final pickupLatLng = LatLng(
        ride.pickupLocation.coordinates.latitude,
        ride.pickupLocation.coordinates.longitude,
      );
      final destLatLng = LatLng(
        ride.destinationLocation.coordinates.latitude,
        ride.destinationLocation.coordinates.longitude,
      );

      List<LatLng> routePoints = [];

      if (ride.status == RideStatus.accepted) {
        // Driver heading to pickup — show driver → pickup so rider sees driver approaching
        final driverLoc = rideState.driverLocation;
        if (driverLoc == null) {
          debugPrint('⚠️ [Rider] No driver location yet, clearing polyline');
          if (mounted) setState(() { _polylines = {}; });
          return;
        }
        routePoints = await _directionsService.getRoute(
          origin: driverLoc,
          destination: pickupLatLng,
        );
      } else if (ride.status == RideStatus.inProgress) {
        // Ride started — show pickup → destination (backend geometry preferred)
        routePoints = ride.routeCoordinates ?? [];
        if (routePoints.isEmpty) {
          debugPrint('🗺️  [Rider] No backend geometry, fetching from Mapbox directly');
          routePoints = await _directionsService.getRoute(
            origin: pickupLatLng,
            destination: destLatLng,
          );
        }
      } else {
        // driverArrived or other — no polyline needed
        if (mounted) setState(() { _polylines = {}; });
        return;
      }

      if (routePoints.isEmpty) {
        debugPrint('⚠️ [Rider] Route is empty - continuing without route line');
        return;
      }

      debugPrint('✅ [Rider] Route fetched: ${routePoints.length} points');

      if (mounted) {
        setState(() {
          _polylines = {
            MapPolyline(
              id: 'ride_route_${ride.id}',
              points: routePoints,
              color: Colors.blue,
              width: 4.0,
            ),
          };
        });
      }
    } catch (e) {
      debugPrint('❌ [Rider] Unexpected error fetching route: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final l10n = AppLocalizations.of(context);
    final rideState = ref.watch(activeRideProvider);

    // Listen for ride status changes (WebSocket updates)
    ref.listen<ActiveRideState>(activeRideProvider, (previous, next) {
      if (next.ride?.status == RideStatus.completed) {
        HapticFeedback.mediumImpact();
        _statusPollingTimer?.cancel();
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (context) => CompletedScreen(ride: next.ride!),
          ),
        );
      } else if (next.ride?.status == RideStatus.cancelled) {
        HapticFeedback.heavyImpact();
        _statusPollingTimer?.cancel();
        if (_isCancelling) {
          // Rider initiated cancel — skip popup and go home
          Navigator.of(context).pushAndRemoveUntil(
            MaterialPageRoute(builder: (context) => const RiderHomeScreen()),
            (route) => false,
          );
        } else {
          // External cancel (driver or admin) — show info first
          _showCancellationInfo(next.ride);
        }
      }

      // Haptic on driver arrived
      if (previous?.ride?.status != next.ride?.status &&
          next.ride?.status == RideStatus.driverArrived) {
        HapticFeedback.mediumImpact();
      }

      // Update route when status changes (but only if it's for the current ride)
      if (previous?.ride?.status != next.ride?.status &&
          next.ride?.id == widget.initialRide.id) {
        debugPrint('🔄 [Rider] Status changed to ${next.ride?.status}, refetching route');
        _fetchAndDisplayRoute().catchError((e) {
          debugPrint('⚠️ [Rider] Route fetch error handled: $e');
        });
      }

      // Update markers when ride data or driver location changes (only for current ride)
      if ((previous?.ride != next.ride || previous?.driverLocation != next.driverLocation) &&
          next.ride?.id == widget.initialRide.id) {
        debugPrint('🎯 [Rider] Updating markers for ride ${next.ride?.id}');
        setState(() {
          _buildMarkers(next.ride ?? widget.initialRide, next.driverLocation);
        });
        // Re-fetch route when driver location changes so polyline tracks driver movement
        if (previous?.driverLocation != next.driverLocation) {
          _fetchAndDisplayRoute().catchError((e) {
            debugPrint('⚠️ [Rider] Route fetch on driver location update error: $e');
          });
        }
      }
    });

    final ride = rideState.ride ?? widget.initialRide;
    final driverLocation = rideState.driverLocation;

    // Compute driver ETA (only when driver is heading to pickup)
    int? etaMinutes;
    if (driverLocation != null && ride.status == RideStatus.accepted) {
      final distKm = _haversineKm(
        driverLocation.latitude, driverLocation.longitude,
        ride.pickupLocation.coordinates.latitude,
        ride.pickupLocation.coordinates.longitude,
      );
      etaMinutes = (distKm / 25 * 60).ceil().clamp(1, 60);
    }

    // Initial camera position (pickup location)
    final pickupCoords = ride.pickupLocation.coordinates;

    return Scaffold(
      body: Stack(
        children: [
          // Full-screen map
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
              _fitBounds(ride, driverLocation);
            },
          ),

          // Driver matched popup (shows initially, can be dismissed)
          if (_showDriverMatchedPopup && ride.status == RideStatus.accepted)
            _buildDriverMatchedPopup(context, ride, config, l10n),

          // Top status card (always visible)
          _buildStatusCard(context, ride, rideState, config, l10n, etaMinutes: etaMinutes),

          // Recenter button
          Positioned(
            bottom: 200,
            right: 16,
            child: FloatingActionButton.small(
              heroTag: 'recenter_rider',
              backgroundColor: Colors.white,
              onPressed: () => _fitBounds(ride, driverLocation),
              tooltip: l10n.recenterMap,
              child: Icon(Icons.my_location, color: config.primaryColor),
            ),
          ),

          // Bottom driver info card
          _buildDriverInfoCard(context, ride, config, l10n),
        ],
      ),
    );
  }

  /// Build driver matched popup (dismissible)
  Widget _buildDriverMatchedPopup(
      BuildContext context, Ride ride, AppConfig config, AppLocalizations l10n) {
    return Positioned.fill(
      child: GestureDetector(
        onTap: () {
          setState(() {
            _showDriverMatchedPopup = false;
          });
        },
        child: Container(
          color: Colors.black54,
          child: Center(
            child: Card(
              margin: const EdgeInsets.all(32),
              child: Padding(
                padding: const EdgeInsets.all(24.0),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    // Success icon
                    Container(
                      width: 80,
                      height: 80,
                      decoration: BoxDecoration(
                        color: Colors.green.withOpacity(0.1),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.check_circle,
                        size: 50,
                        color: Colors.green,
                      ),
                    ),

                    const SizedBox(height: 16),

                    Text(
                      l10n.driverFoundTitle,
                      style: const TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.bold,
                      ),
                    ),

                    const SizedBox(height: 8),

                    Text(
                      l10n.driverOnTheWayMessage(
                          ride.driver?.name ?? l10n.yourDriver),
                      style: const TextStyle(fontSize: 14, color: Colors.grey),
                      textAlign: TextAlign.center,
                    ),

                    const SizedBox(height: 24),

                    ElevatedButton(
                      onPressed: () {
                        setState(() {
                          _showDriverMatchedPopup = false;
                        });
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: config.primaryColor,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(
                          horizontal: 32,
                          vertical: 12,
                        ),
                      ),
                      child: Text(l10n.gotIt),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  /// Build top status card
  Widget _buildStatusCard(BuildContext context, Ride ride,
      ActiveRideState rideState, AppConfig config, AppLocalizations l10n, {int? etaMinutes}) {
    return Positioned(
      top: 0,
      left: 16,
      right: 16,
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.only(top: 16),
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
                        _getStatusText(ride.status, l10n),
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                    if (ride.status == RideStatus.accepted)
                      IconButton(
                        icon: const Icon(Icons.close),
                        onPressed: () => _cancelRide(ride),
                        tooltip: l10n.cancelRideTooltip,
                        padding: EdgeInsets.zero,
                        constraints: const BoxConstraints(),
                        iconSize: 20,
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
                if (etaMinutes != null || rideState.estimatedArrivalMinutes != null) ...[
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Icon(Icons.access_time,
                          size: 16, color: config.primaryColor),
                      const SizedBox(width: 4),
                      Text(
                        l10n.etaMinutes((etaMinutes ?? rideState.estimatedArrivalMinutes!.toInt()).toString()),
                        style: TextStyle(color: Colors.grey[600]),
                      ),
                    ],
                  ),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  /// Build bottom driver info card
  Widget _buildDriverInfoCard(
      BuildContext context, Ride ride, AppConfig config, AppLocalizations l10n) {
    return Positioned(
      bottom: 16,
      left: 16,
      right: 16,
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(16.0),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Driver info row
              Row(
                children: [
                  CircleAvatar(
                    radius: 22,
                    backgroundImage: ride.driver?.avatarUrl != null
                        ? NetworkImage(ride.driver!.avatarUrl!)
                        : null,
                    child: ride.driver?.avatarUrl == null
                        ? Text(
                            (ride.driver?.name.isNotEmpty == true)
                                ? ride.driver!.name[0].toUpperCase()
                                : 'D',
                            style: const TextStyle(fontSize: 16),
                          )
                        : null,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          ride.driver?.name ?? l10n.driverFallback,
                          style: const TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.bold,
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                        if (ride.driver?.driverProfile != null)
                          Text(
                            '${ride.driver!.driverProfile!.vehiclePlate} • ${ride.driver!.driverProfile!.vehicleColor}',
                            style: TextStyle(
                              fontSize: 12,
                              color: Colors.grey[600],
                            ),
                          ),
                      ],
                    ),
                  ),
                  IconButton(
                    icon: const FaIcon(FontAwesomeIcons.whatsapp, color: Color(0xFF25D366)),
                    padding: EdgeInsets.zero,
                    constraints: const BoxConstraints(),
                    onPressed: () => openWhatsApp(context, ride.driver?.phone, ride.driver?.name ?? l10n.driverFallback),
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
            ],
          ),
        ),
      ),
    );
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
                l10n.adminCancelReason(adminReason),
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 14),
              )
            else
              Text(
                l10n.driverCancelledMessage,
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
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (context) => const RiderHomeScreen()),
      (route) => false,
    );
  }

  Future<void> _cancelRide(Ride ride) async {
    final l10n = AppLocalizations.of(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(l10n.cancelRideTitle),
        content: Text(l10n.cancelRideConfirmMessage),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: Text(l10n.no),
          ),
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            child: Text(l10n.yesCancelButton, style: const TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    setState(() => _isCancelling = true);

    try {
      final rideService = ref.read(rideServiceProvider);
      final result = await rideService.cancelRide(ride.id);
      final meta = result.meta;
      ref.read(rideRequestProvider.notifier).applyCancelPenalty(
        cancelCount: (meta['cancel_count'] as int?) ?? 0,
        cooldownUntil: meta['cooldown_until'] as String?,
        isSuspended: (meta['is_suspended'] as bool?) ?? false,
      );
      // ref.listen above will handle navigation once the status update arrives.
      // Fallback: if no navigation within 10 seconds, go home anyway.
      Future.delayed(const Duration(seconds: 10), () {
        if (mounted && _isCancelling) {
          Navigator.of(context).pushAndRemoveUntil(
            MaterialPageRoute(builder: (_) => const RiderHomeScreen()),
            (route) => false,
          );
        }
      });
    } catch (e) {
      if (!mounted) return;
      final l10nErr = AppLocalizations.of(context);
      setState(() => _isCancelling = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(l10nErr.cancelFailed(e.toString())),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  void _buildMarkers(Ride ride, LatLng? driverLocation) {
    debugPrint('🎯 [Rider] Building markers - driver location: ${driverLocation != null}');

    // Create NEW set of markers (important for Flutter to detect changes)
    final newMarkers = <MapMarker>{};

    // Pickup marker (green)
    newMarkers.add(
      MapMarker(
        id: 'pickup',
        latitude: ride.pickupLocation.coordinates.latitude,
        longitude: ride.pickupLocation.coordinates.longitude,
        icon: 'circle',
        size: 1.5,
      ),
    );

    // Destination marker (red)
    newMarkers.add(
      MapMarker(
        id: 'destination',
        latitude: ride.destinationLocation.coordinates.latitude,
        longitude: ride.destinationLocation.coordinates.longitude,
        icon: 'marker',
        size: 1.5,
      ),
    );

    // Driver marker (if available) (blue)
    if (driverLocation != null) {
      newMarkers.add(
        MapMarker(
          id: 'driver',
          latitude: driverLocation.latitude,
          longitude: driverLocation.longitude,
          icon: 'car',
          size: 1.8,
        ),
      );
    }

    _markers = newMarkers;
    debugPrint('✅ [Rider] Built ${_markers.length} markers');
  }

  void _fitBounds(Ride ride, LatLng? driverLocation) {
    if (_mapController == null) return;

    // Calculate center point and appropriate zoom level
    final lats = [
      ride.pickupLocation.coordinates.latitude,
      ride.destinationLocation.coordinates.latitude,
      if (driverLocation != null) driverLocation.latitude,
    ];
    final lngs = [
      ride.pickupLocation.coordinates.longitude,
      ride.destinationLocation.coordinates.longitude,
      if (driverLocation != null) driverLocation.longitude,
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

  static double _haversineKm(double lat1, double lng1, double lat2, double lng2) {
    const r = 6371.0;
    final dLat = (lat2 - lat1) * pi / 180;
    final dLng = (lng2 - lng1) * pi / 180;
    final a = sin(dLat / 2) * sin(dLat / 2) +
        cos(lat1 * pi / 180) * cos(lat2 * pi / 180) *
        sin(dLng / 2) * sin(dLng / 2);
    return r * 2 * atan2(sqrt(a), sqrt(1 - a));
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
        return l10n.statusDriverOnTheWay;
      case RideStatus.driverArrived:
        return l10n.statusDriverArrived;
      case RideStatus.inProgress:
        return l10n.statusRideInProgress;
      case RideStatus.completed:
        return l10n.statusRideCompleted;
      case RideStatus.cancelled:
        return l10n.statusRideCancelled;
    }
  }
}
