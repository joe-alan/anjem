import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile/l10n/app_localizations.dart';
import '../../core/config/app_config.dart';
import '../../core/models/lat_lng.dart';
import '../../core/models/place_search_result.dart';
import '../../core/providers/reverse_geocoding_provider.dart';
import '../../core/providers/ride_request_provider.dart';
import '../../core/widgets/mapbox_map_widget.dart';
import 'ride_details_screen.dart';

enum MapConfirmMode { pickup, dropoff }

class MapConfirmScreen extends ConsumerStatefulWidget {
  final MapConfirmMode mode;
  final LatLng initialCenter;
  final String initialName;

  /// Already-confirmed pickup (passed when mode == dropoff)
  final PlaceSearchResult? confirmedPickup;

  /// Already-confirmed dropoff (passed when mode == pickup and we already have dropoff from search)
  final PlaceSearchResult? confirmedDropoff;

  const MapConfirmScreen({
    super.key,
    required this.mode,
    required this.initialCenter,
    required this.initialName,
    this.confirmedPickup,
    this.confirmedDropoff,
  });

  @override
  ConsumerState<MapConfirmScreen> createState() => _MapConfirmScreenState();
}

class _MapConfirmScreenState extends ConsumerState<MapConfirmScreen> {
  String _resolvedName = '';
  String? _resolvedAddress;
  bool _isResolving = false;
  int _geocodeGeneration = 0;
  LatLng _currentCenter = const LatLng(0, 0);

  @override
  void initState() {
    super.initState();
    _resolvedName = widget.initialName;
    _currentCenter = widget.initialCenter;
  }

  void _onCameraIdle(CameraPosition position) {
    _currentCenter = LatLng(position.latitude, position.longitude);
    _reverseGeocode(position.latitude, position.longitude);
  }

  Future<void> _reverseGeocode(double lat, double lng) async {
    final generation = ++_geocodeGeneration;
    setState(() => _isResolving = true);

    try {
      final service = ref.read(reverseGeocodingServiceProvider);
      final result = await service.reverseGeocode(
        latitude: lat,
        longitude: lng,
      );

      if (generation != _geocodeGeneration || !mounted) return;

      setState(() {
        _resolvedName = result.name;
        _resolvedAddress = result.address;
        _isResolving = false;
      });
    } catch (e) {
      if (generation != _geocodeGeneration || !mounted) return;
      final l10n = AppLocalizations.of(context);
      setState(() {
        _resolvedName = l10n.droppedPin;
        _resolvedAddress = null;
        _isResolving = false;
      });
    }
  }

  void _onConfirm() {
    final confirmed = PlaceSearchResult(
      name: _resolvedName,
      address: _resolvedAddress,
      coordinates: _currentCenter,
      source: 'mapbox',
    );

    if (widget.mode == MapConfirmMode.pickup) {
      // Go to dropoff confirmation
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (context) => MapConfirmScreen(
            mode: MapConfirmMode.dropoff,
            initialCenter: widget.confirmedDropoff?.coordinates ?? _currentCenter,
            initialName: widget.confirmedDropoff?.name ?? '',
            confirmedPickup: confirmed,
          ),
        ),
      );
    } else {
      // Dropoff confirmed — get estimate and go to ride details
      _getEstimateAndNavigate(confirmed);
    }
  }

  Future<void> _getEstimateAndNavigate(PlaceSearchResult confirmedDropoff) async {
    final pickup = widget.confirmedPickup;
    if (pickup == null) {
      assert(false, 'confirmedPickup should not be null in dropoff mode');
      return;
    }
    final l10n = AppLocalizations.of(context);

    // If both have IDs, use beacon-based estimate; otherwise coordinates
    if (pickup.id != null && confirmedDropoff.id != null) {
      await ref.read(rideRequestProvider.notifier).getEstimate(
        pickupBeaconId: pickup.id!,
        destinationBeaconId: confirmedDropoff.id!,
      );
    } else {
      await ref.read(rideRequestProvider.notifier).getEstimateByCoordinates(
        pickupLat: pickup.coordinates.latitude,
        pickupLng: pickup.coordinates.longitude,
        destLat: confirmedDropoff.coordinates.latitude,
        destLng: confirmedDropoff.coordinates.longitude,
      );
    }

    if (!mounted) return;

    final state = ref.read(rideRequestProvider);
    if (state.error != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(l10n.estimateFailed(state.error!)),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (context) => RideDetailsScreen(
          pickupLocation: pickup,
          destinationLocation: confirmedDropoff,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final l10n = AppLocalizations.of(context);
    final isPickup = widget.mode == MapConfirmMode.pickup;
    final requestState = ref.watch(rideRequestProvider);

    return Scaffold(
      body: Stack(
        children: [
          // Full-screen map
          MapboxMapWidget(
            initialCameraPosition: CameraPosition(
              latitude: widget.initialCenter.latitude,
              longitude: widget.initialCenter.longitude,
              zoom: 17,
            ),
            myLocationEnabled: true,
            myLocationButtonEnabled: false,
            zoomControlsEnabled: false,
            onCameraIdle: _onCameraIdle,
          ),

          // Fixed center pin
          Center(
            child: Padding(
              padding: const EdgeInsets.only(bottom: 36),
              child: Icon(
                isPickup ? Icons.trip_origin : Icons.location_on,
                color: config.primaryColor,
                size: 40,
              ),
            ),
          ),

          // Hint text at top
          Positioned(
            top: MediaQuery.of(context).padding.top + 12,
            left: 16,
            right: 16,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.1),
                    blurRadius: 8,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Row(
                children: [
                  IconButton(
                    icon: const Icon(Icons.arrow_back),
                    onPressed: () => Navigator.of(context).pop(),
                    constraints: const BoxConstraints(minWidth: 48, minHeight: 48),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      isPickup ? l10n.adjustPinPickup : l10n.adjustPinDropoff,
                      style: const TextStyle(fontSize: 14),
                    ),
                  ),
                ],
              ),
            ),
          ),

          // Bottom sheet with resolved name + confirm button
          Positioned(
            bottom: 0,
            left: 0,
            right: 0,
            child: Container(
              decoration: const BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.only(
                  topLeft: Radius.circular(20),
                  topRight: Radius.circular(20),
                ),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black12,
                    blurRadius: 10,
                    offset: Offset(0, -2),
                  ),
                ],
              ),
              padding: EdgeInsets.fromLTRB(
                20, 16, 20, MediaQuery.of(context).padding.bottom + 16,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Drag handle
                  Center(
                    child: Container(
                      width: 40,
                      height: 4,
                      decoration: BoxDecoration(
                        color: Colors.grey[300],
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Resolved name
                  if (_isResolving)
                    Row(
                      children: [
                        SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: config.primaryColor,
                          ),
                        ),
                        const SizedBox(width: 12),
                        Text(
                          l10n.resolvingLocation,
                          style: TextStyle(color: Colors.grey[600]),
                        ),
                      ],
                    )
                  else ...[
                    Text(
                      _resolvedName,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    if (_resolvedAddress != null) ...[
                      const SizedBox(height: 4),
                      Text(
                        _resolvedAddress!,
                        style: TextStyle(
                          fontSize: 13,
                          color: Colors.grey[600],
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ],

                  const SizedBox(height: 16),

                  // Confirm button
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: (_isResolving || requestState.isLoading)
                          ? null
                          : _onConfirm,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: config.primaryColor,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: requestState.isLoading
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : Text(
                              isPickup ? l10n.confirmPickup : l10n.confirmDropoff,
                              style: const TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
