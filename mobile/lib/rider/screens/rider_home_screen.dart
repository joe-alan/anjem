import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile/l10n/app_localizations.dart';
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
    final l10n = AppLocalizations.of(context);
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
          tooltip: l10n.logoutTooltip,
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
            Positioned(
              top: 16,
              left: 0,
              right: 0,
              child: Center(
                child: Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16.0),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                        const SizedBox(width: 12),
                        Text(l10n.loading),
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
                          l10n.accountSuspendedBanner,
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
                          l10n.activeRideRequestBanner,
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
                        child: Text(l10n.viewButton),
                      ),
                    ],
                  ),
                ),
              ),
            ),

          // Cancel warning banner
          if (rideRequestState.cancelCount >= 1)
            Positioned(
              bottom: 100,
              left: 16,
              right: 16,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                decoration: BoxDecoration(
                  color: Colors.orange.shade50,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.orange.shade300),
                ),
                child: Row(
                  children: [
                    Icon(Icons.warning_amber_rounded, color: Colors.orange.shade700, size: 20),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        rideRequestState.cancelCount >= 2
                            ? l10n.oneMoreCancellationWarning
                            : l10n.repeatedCancellationsWarning,
                        style: TextStyle(color: Colors.orange.shade800, fontSize: 13),
                      ),
                    ),
                  ],
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
                  isSuspended
                      ? l10n.accountSuspendedButton
                      : hasActiveRequest
                          ? l10n.requestInProgressButton
                          : l10n.requestRideButton,
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
    final l10n = AppLocalizations.of(context);
    showDialog(
      context: context,
      builder: (BuildContext context) {
        return AlertDialog(
          title: Text(l10n.logoutTitle),
          content: Text(l10n.logoutConfirmMessage),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: Text(l10n.cancel),
            ),
            TextButton(
              onPressed: () async {
                Navigator.of(context).pop();
                // Clear session state first to prevent stale data on re-login
                ref.read(sessionStateProvider.notifier).clearSession();
                await ref.read(authStateProvider.notifier).signOut();
              },
              child: Text(
                l10n.logoutTitle,
                style: const TextStyle(color: Colors.red),
              ),
            ),
          ],
        );
      },
    );
  }
}
