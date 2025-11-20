import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/config/app_config.dart';
import '../../core/models/ride.dart';
import '../../core/models/lat_lng.dart';
import '../../core/providers/active_ride_provider.dart';
import '../../core/widgets/mapbox_map_widget.dart';
import '../../core/services/mapbox/mapbox_directions_service.dart';
import 'completed_screen.dart';

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

  @override
  void initState() {
    super.initState();

    print('🔄 [Rider] RiderActiveRideScreen initState for ride ${widget.initialRide.id}');

    // ✅ Clear any previous ride's polylines
    _polylines = {};

    // Build initial markers
    _buildMarkers(widget.initialRide, null);

    // Set initial ride in provider
    Future.microtask(() {
      print('📝 [Rider] Setting ride ${widget.initialRide.id} in provider');
      ref.read(activeRideProvider.notifier).setRide(widget.initialRide);
      _fetchAndDisplayRoute();
    });
  }

  @override
  void dispose() {
    // ✅ Clean up state when leaving screen
    _polylines = {};
    _markers = {};
    _mapController?.dispose();
    super.dispose();
  }

  /// Fetch and display route based on ride status
  Future<void> _fetchAndDisplayRoute() async {
    final rideState = ref.read(activeRideProvider);
    final ride = rideState.ride ?? widget.initialRide;  // ✅ Fallback to widget ride

    print('🗺️  [Rider] _fetchAndDisplayRoute called');
    print('    Provider ride: ${rideState.ride?.id}');
    print('    Widget ride: ${widget.initialRide.id}');
    print('    Using ride: ${ride.id}');

    try {
      print('🗺️  [Rider] Fetching route for ride ${ride.id}');

      final pickupLatLng = LatLng(
        ride.pickupLocation.coordinates.latitude,
        ride.pickupLocation.coordinates.longitude,
      );
      final destLatLng = LatLng(
        ride.destinationLocation.coordinates.latitude,
        ride.destinationLocation.coordinates.longitude,
      );

      // Always show route from pickup to destination
      final routePoints = await _directionsService.getRoute(
        origin: pickupLatLng,
        destination: destLatLng,
      );

      print('✅ [Rider] Route fetched: ${routePoints.length} points');

      // Create NEW set with the polyline (important for Flutter to detect changes)
      if (mounted) {
        setState(() {
          _polylines = {
            MapPolyline(
              id: 'ride_route_${ride.id}',  // ✅ Unique ID per ride
              points: routePoints,
              color: Colors.blue,
              width: 4.0,
            ),
          };
        });
      }
    } catch (e) {
      print('❌ [Rider] Failed to fetch route: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final rideState = ref.watch(activeRideProvider);

    // Listen for ride status changes
    ref.listen<ActiveRideState>(activeRideProvider, (previous, next) {
      if (next.ride?.status == RideStatus.completed) {
        // Navigate to completed screen
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (context) => CompletedScreen(ride: next.ride!),
          ),
        );
      }

      // Update route when status changes (but only if it's for the current ride)
      if (previous?.ride?.status != next.ride?.status &&
          next.ride?.id == widget.initialRide.id) {
        print('🔄 [Rider] Status changed to ${next.ride?.status}, refetching route');
        _fetchAndDisplayRoute();
      }

      // Update markers when ride data or driver location changes (only for current ride)
      if ((previous?.ride != next.ride || previous?.driverLocation != next.driverLocation) &&
          next.ride?.id == widget.initialRide.id) {
        print('🎯 [Rider] Updating markers for ride ${next.ride?.id}');
        setState(() {
          _buildMarkers(next.ride ?? widget.initialRide, next.driverLocation);
        });
      }
    });

    final ride = rideState.ride ?? widget.initialRide;
    final driverLocation = rideState.driverLocation;

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
            _buildDriverMatchedPopup(context, ride, config),

          // Top status card (always visible)
          _buildStatusCard(context, ride, rideState, config),

          // Bottom driver info card
          _buildDriverInfoCard(context, ride, config),
        ],
      ),
    );
  }

  /// Build driver matched popup (dismissible)
  Widget _buildDriverMatchedPopup(
      BuildContext context, Ride ride, AppConfig config) {
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

                    const Text(
                      'Driver Found!',
                      style: TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.bold,
                      ),
                    ),

                    const SizedBox(height: 8),

                    Text(
                      '${ride.driver?.name ?? "Your driver"} is on the way',
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
                      child: const Text('Got it!'),
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
      ActiveRideState rideState, AppConfig config) {
    return Positioned(
      top: 0,
      left: 16,
      right: 16,
      child: SafeArea(  // ✅ SafeArea should be INSIDE Positioned
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
                        _getStatusText(ride.status),
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ],
                ),
                if (rideState.estimatedArrivalMinutes != null) ...[
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Icon(Icons.access_time,
                          size: 16, color: config.primaryColor),
                      const SizedBox(width: 4),
                      Text(
                        'ETA: ${rideState.estimatedArrivalMinutes!.toStringAsFixed(0)} min',
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
      BuildContext context, Ride ride, AppConfig config) {
    return Positioned(
      bottom: 16,
      left: 16,
      right: 16,
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(16.0),
          child: Row(
            children: [
              CircleAvatar(
                radius: 24,
                backgroundImage: ride.driver?.avatarUrl != null
                    ? NetworkImage(ride.driver!.avatarUrl!)
                    : null,
                child: ride.driver?.avatarUrl == null
                    ? Text(
                        ride.driver?.name[0].toUpperCase() ?? 'D',
                        style: const TextStyle(fontSize: 18),
                      )
                    : null,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      ride.driver?.name ?? 'Driver',
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
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
                icon: Icon(Icons.phone, color: config.primaryColor),
                onPressed: () {
                  // TODO: Call driver
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Calling driver...'),
                    ),
                  );
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _buildMarkers(Ride ride, LatLng? driverLocation) {
    print('🎯 [Rider] Building markers - driver location: ${driverLocation != null}');

    // Create NEW set of markers (important for Flutter to detect changes)
    final newMarkers = <MapMarker>{};

    // Pickup marker (green)
    newMarkers.add(
      MapMarker(
        id: 'pickup',
        latitude: ride.pickupLocation.coordinates.latitude,
        longitude: ride.pickupLocation.coordinates.longitude,
        icon: 'circle',  // ✅ Guaranteed Mapbox icon
        size: 1.5,
      ),
    );

    // Destination marker (red)
    newMarkers.add(
      MapMarker(
        id: 'destination',
        latitude: ride.destinationLocation.coordinates.latitude,
        longitude: ride.destinationLocation.coordinates.longitude,
        icon: 'marker',  // ✅ Guaranteed Mapbox icon
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
          icon: 'car',  // ✅ Guaranteed Mapbox icon
          size: 1.8,
        ),
      );
    }

    _markers = newMarkers;
    print('✅ [Rider] Built ${_markers.length} markers');
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
        return 'Driver on the way';
      case RideStatus.driverArrived:
        return 'Driver has arrived';
      case RideStatus.inProgress:
        return 'Ride in progress';
      case RideStatus.completed:
        return 'Ride completed';
      case RideStatus.cancelled:
        return 'Ride cancelled';
    }
  }
}
