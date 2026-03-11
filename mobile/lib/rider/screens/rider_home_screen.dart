import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/config/app_config.dart';
import '../../core/models/lat_lng.dart';
import '../../core/providers/auth_provider.dart';
import '../../core/providers/beacons_provider.dart';
import '../../core/providers/ride_request_provider.dart';
import '../../core/providers/session_provider.dart';
import '../../core/providers/user_location_provider.dart';
import '../../core/widgets/mapbox_map_widget.dart';
import 'location_selection_screen.dart';
import 'waiting_screen.dart';

class RiderHomeScreen extends ConsumerStatefulWidget {
  const RiderHomeScreen({super.key});

  @override
  ConsumerState<RiderHomeScreen> createState() => _RiderHomeScreenState();
}

class _RiderHomeScreenState extends ConsumerState<RiderHomeScreen> {
  MapboxMapController? _mapController;
  final Set<MapMarker> _markers = {};

  @override
  void dispose() {
    _mapController?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final beaconsState = ref.watch(beaconsProvider);
    final locationState = ref.watch(userLocationProvider);
    final rideRequestState = ref.watch(rideRequestProvider);
    final authState = ref.watch(authStateProvider);

    final isSuspended = !(authState.user?.isActive ?? true);

    // Check if there's an active request that should block new requests
    final hasActiveRequest = rideRequestState.request != null &&
        rideRequestState.matchedRide == null;

    // Build markers from beacons
    _buildBeaconMarkers(beaconsState.beacons);

    // Default location — Undip Tembalang, Semarang (used only until GPS resolves)
    final initialPosition = locationState.location ??
        const LatLng(-7.0523, 110.4381);

    return Scaffold(
      appBar: AppBar(
        title: Text(config.appName),
        backgroundColor: config.primaryColor,
        foregroundColor: Colors.white,
        leading: IconButton(
          icon: const Icon(Icons.logout),
          onPressed: () => _showLogoutDialog(context),
          tooltip: 'Logout',
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () {
              ref.read(beaconsProvider.notifier).refresh();
              ref.read(userLocationProvider.notifier).getCurrentLocation();
              ref.read(authStateProvider.notifier).refreshUser();
            },
          ),
        ],
      ),
      body: Stack(
        children: [
          // Mapbox Map
          MapboxMapWidget(
            initialCameraPosition: CameraPosition(
              latitude: initialPosition.latitude,
              longitude: initialPosition.longitude,
              zoom: 15,
            ),
            markers: _markers,
            myLocationEnabled: true,
            myLocationButtonEnabled: false,
            zoomControlsEnabled: false,
            onMapCreated: (controller) {
              _mapController = controller;
              // Animate to user location if available
              if (locationState.location != null) {
                _animateToLocation(locationState.location!);
              }
            },
            onCameraMove: (position) {
              // Optional: load nearby beacons when camera moves
            },
          ),

          // Loading indicator
          if (beaconsState.isLoading || locationState.isLoading)
            const Positioned(
              top: 16,
              left: 0,
              right: 0,
              child: Center(
                child: Card(
                  child: Padding(
                    padding: EdgeInsets.all(16.0),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                        SizedBox(width: 12),
                        Text('Loading...'),
                      ],
                    ),
                  ),
                ),
              ),
            ),

          // Error messages
          if (beaconsState.error != null)
            Positioned(
              top: 16,
              left: 16,
              right: 16,
              child: Card(
                color: Colors.red[50],
                child: Padding(
                  padding: const EdgeInsets.all(12.0),
                  child: Row(
                    children: [
                      Icon(Icons.error_outline, color: Colors.red[700]),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          beaconsState.error!,
                          style: TextStyle(color: Colors.red[700]),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),

          // My Location button
          Positioned(
            bottom: 100,
            right: 16,
            child: FloatingActionButton(
              mini: true,
              backgroundColor: Colors.white,
              child: Icon(Icons.my_location, color: config.primaryColor),
              onPressed: () {
                if (locationState.location != null) {
                  _animateToLocation(locationState.location!);
                } else {
                  ref.read(userLocationProvider.notifier).getCurrentLocation();
                }
              },
            ),
          ),

          // Suspended banner
          if (isSuspended)
            Positioned(
              top: 16,
              left: 16,
              right: 16,
              child: Card(
                color: Colors.red[50],
                child: Padding(
                  padding: const EdgeInsets.all(12.0),
                  child: Row(
                    children: [
                      Icon(Icons.block, color: Colors.red[700]),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Your account has been suspended. You cannot request rides.',
                          style: TextStyle(color: Colors.red[700], fontWeight: FontWeight.w500),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),

          // Active request banner (if any)
          if (hasActiveRequest)
            Positioned(
              bottom: 90,
              left: 16,
              right: 16,
              child: Card(
                color: Colors.blue[50],
                child: Padding(
                  padding: const EdgeInsets.all(12.0),
                  child: Row(
                    children: [
                      Icon(Icons.info_outline, color: Colors.blue[700]),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          'You have an active ride request',
                          style: TextStyle(color: Colors.blue[700]),
                        ),
                      ),
                      TextButton(
                        onPressed: () {
                          Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (context) => const WaitingScreen(),
                            ),
                          );
                        },
                        child: const Text('View'),
                      ),
                    ],
                  ),
                ),
              ),
            ),

          // Request Ride button
          Positioned(
            bottom: 24,
            left: 16,
            right: 16,
            child: SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: (isSuspended || beaconsState.beacons.isEmpty || hasActiveRequest)
                    ? null
                    : () {
                        Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (context) =>
                                const LocationSelectionScreen(),
                          ),
                        );
                      },
                icon: const Icon(Icons.add_location),
                label: Text(
                  isSuspended ? 'Account Suspended' : hasActiveRequest ? 'Request in Progress' : 'Request Ride',
                  style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: (isSuspended || hasActiveRequest) ? Colors.grey : config.primaryColor,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  void _buildBeaconMarkers(List beacons) {
    _markers.clear();

    for (final beacon in beacons) {
      _markers.add(
        MapMarker(
          id: 'beacon_${beacon.id}',
          latitude: beacon.coordinates.latitude,
          longitude: beacon.coordinates.longitude,
          // title and snippet are not directly supported in simplified wrapper
          // Will need to handle marker info windows separately if needed
          icon: 'marker-15', // Mapbox default marker
          size: 1.5, // Larger size for beacons
        ),
      );
    }
  }

  void _animateToLocation(LatLng location) {
    _mapController?.animateCamera(
      CameraPosition(
        latitude: location.latitude,
        longitude: location.longitude,
        zoom: 16,
      ),
    );
  }

  void _showLogoutDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (BuildContext context) {
        return AlertDialog(
          title: const Text('Logout'),
          content: const Text('Are you sure you want to logout?'),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Cancel'),
            ),
            TextButton(
              onPressed: () async {
                Navigator.of(context).pop();
                // Clear session state first to prevent stale data on re-login
                ref.read(sessionStateProvider.notifier).clearSession();
                await ref.read(authStateProvider.notifier).signOut();
              },
              child: const Text(
                'Logout',
                style: TextStyle(color: Colors.red),
              ),
            ),
          ],
        );
      },
    );
  }
}
