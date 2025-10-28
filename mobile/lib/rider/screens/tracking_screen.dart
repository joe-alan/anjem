import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/config/app_config.dart';
import '../../core/models/lat_lng.dart';
import '../../core/models/ride.dart';
import '../../core/providers/active_ride_provider.dart';
import '../../core/widgets/mapbox_map_widget.dart';
import 'completed_screen.dart';

class TrackingScreen extends ConsumerStatefulWidget {
  const TrackingScreen({super.key});

  @override
  ConsumerState<TrackingScreen> createState() => _TrackingScreenState();
}

class _TrackingScreenState extends ConsumerState<TrackingScreen> {
  MapboxMapController? _mapController;
  final Set<MapMarker> _markers = {};
  // Note: Polylines will be handled differently with Mapbox (using Directions API)

  @override
  void dispose() {
    _mapController?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final rideState = ref.watch(activeRideProvider);

    // Listen for ride completion
    ref.listen<ActiveRideState>(activeRideProvider, (previous, next) {
      if (next.ride?.status == RideStatus.completed) {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (context) => CompletedScreen(
              ride: next.ride!,
            ),
          ),
        );
      }
    });

    final ride = rideState.ride;
    final driverLocation = rideState.driverLocation;

    if (ride == null) {
      return const Scaffold(
        body: Center(
          child: Text('No active ride'),
        ),
      );
    }

    // Build markers
    _buildMarkers(ride, driverLocation);

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
            myLocationEnabled: true,
            myLocationButtonEnabled: false,
            zoomControlsEnabled: false,
            onMapCreated: (controller) {
              _mapController = controller;
              _fitBounds(ride, driverLocation);
            },
          ),

          // Top status card
          SafeArea(
            child: Positioned(
              top: 16,
              left: 16,
              right: 16,
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
                          Text(
                            _getStatusText(ride.status),
                            style: const TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.bold,
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

          // Driver info card (bottom)
          Positioned(
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
          ),
        ],
      ),
    );
  }

  void _buildMarkers(Ride ride, LatLng? driverLocation) {
    _markers.clear();

    // Pickup marker (green)
    _markers.add(
      MapMarker(
        id: 'pickup',
        latitude: ride.pickupLocation.coordinates.latitude,
        longitude: ride.pickupLocation.coordinates.longitude,
        icon: 'marker-15',
        size: 1.5,
      ),
    );

    // Destination marker (red)
    _markers.add(
      MapMarker(
        id: 'destination',
        latitude: ride.destinationLocation.coordinates.latitude,
        longitude: ride.destinationLocation.coordinates.longitude,
        icon: 'marker-15',
        size: 1.5,
      ),
    );

    // Driver marker (if available) (blue)
    if (driverLocation != null) {
      _markers.add(
        MapMarker(
          id: 'driver',
          latitude: driverLocation.latitude,
          longitude: driverLocation.longitude,
          icon: 'car-15', // Mapbox has a car icon
          size: 1.8,
        ),
      );

      // Note: Polylines are handled differently in Mapbox
      // Will be implemented using Mapbox Directions API in Phase 8
    }
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

    // Simplified: just center on the middle point
    // TODO: Calculate proper zoom level based on bounds
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
