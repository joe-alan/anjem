import 'dart:async';
import 'dart:math';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile/l10n/app_localizations.dart';
import '../../core/config/app_config.dart';
import '../../core/models/lat_lng.dart';
import '../../core/models/place_search_result.dart';
import '../../core/providers/beacons_provider.dart';
import '../../core/providers/place_search_provider.dart';
import '../../core/providers/reverse_geocoding_provider.dart';
import '../../core/providers/ride_request_provider.dart';
import '../../core/widgets/mapbox_map_widget.dart';
import 'ride_details_screen.dart';

enum MapConfirmMode { pickup, dropoff }

const double _reResolveThresholdMeters = 50.0;

class MapConfirmScreen extends ConsumerStatefulWidget {
  final MapConfirmMode mode;
  final LatLng initialCenter;
  final String initialName;
  final int? initialLocationId;
  final PlaceSearchResult? confirmedPickup;
  final PlaceSearchResult? confirmedDropoff;

  const MapConfirmScreen({
    super.key,
    required this.mode,
    required this.initialCenter,
    required this.initialName,
    this.initialLocationId,
    this.confirmedPickup,
    this.confirmedDropoff,
  });

  @override
  ConsumerState<MapConfirmScreen> createState() => _MapConfirmScreenState();
}

class _MapConfirmScreenState extends ConsumerState<MapConfirmScreen> {
  LatLng _currentCenter = const LatLng(0, 0);
  bool _isResolving = false;
  MapboxMapController? _mapController;
  bool _isSnapping = false;

  /// true when pin is within 50m of original — direct confirm.
  bool _withinThreshold = true;

  /// After user taps "Pick this location?" and resolve completes,
  /// this holds the resolved result. Show name + final confirm button.
  PlaceSearchResult? _resolvedResult;

  /// Controls visibility of the "drag to adjust" hint.
  bool _showDragHint = true;

  @override
  void initState() {
    super.initState();
    _currentCenter = widget.initialCenter;
    Future.delayed(const Duration(seconds: 3), () {
      if (mounted) setState(() => _showDragHint = false);
    });
  }

  void _onCameraIdle(CameraPosition position) {
    if (_isSnapping) {
      _isSnapping = false;
      return;
    }

    final newCenter = LatLng(position.latitude, position.longitude);
    _currentCenter = newCenter;

    final distanceM = _haversineMeters(
      widget.initialCenter.latitude, widget.initialCenter.longitude,
      newCenter.latitude, newCenter.longitude,
    );

    final nowWithin = distanceM <= _reResolveThresholdMeters;
    if (nowWithin != _withinThreshold || _resolvedResult != null || _showDragHint) {
      setState(() {
        _withinThreshold = nowWithin;
        _resolvedResult = null;
        _showDragHint = false;
      });
    }
  }

  /// Called when user taps "Pick this location?" — resolves the pin.
  Future<void> _onPickLocation() async {
    setState(() => _isResolving = true);

    final resolved = await _resolvePin();

    if (!mounted) return;

    // Recenter pin to the resolved location's actual coordinates
    _snapMapTo(resolved.coordinates);
    _currentCenter = resolved.coordinates;

    setState(() {
      _resolvedResult = resolved;
      _isResolving = false;
    });
  }

  void _snapMapTo(LatLng target) {
    _isSnapping = true;
    _mapController?.animateCamera(
      CameraPosition(
        latitude: target.latitude,
        longitude: target.longitude,
        zoom: 17,
      ),
      duration: const Duration(milliseconds: 300),
    );
  }

  /// Called when user taps the final "Confirm Pickup/Dropoff" button.
  Future<void> _onConfirm() async {
    // Within threshold — resolve instantly (no API call, pure math)
    final confirmed = _withinThreshold
        ? PlaceSearchResult(
            id: widget.initialLocationId,
            name: widget.initialName,
            coordinates: _currentCenter,
            isBeacon: widget.initialLocationId != null,
            source: widget.initialLocationId != null ? 'database' : 'mapbox',
          )
        : _resolvedResult!;

    if (widget.mode == MapConfirmMode.pickup) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (context) => MapConfirmScreen(
            mode: MapConfirmMode.dropoff,
            initialCenter: widget.confirmedDropoff?.coordinates ?? confirmed.coordinates,
            initialName: widget.confirmedDropoff?.name ?? '',
            initialLocationId: widget.confirmedDropoff?.id,
            confirmedPickup: confirmed,
          ),
        ),
      );
    } else {
      await _getEstimateAndNavigate(confirmed);
    }
  }

  /// Resolve pin: DB beacon within 50m, then Mapbox Search Box POI.
  Future<PlaceSearchResult> _resolvePin() async {
    final beacons = ref.read(beaconsProvider).beacons;
    PlaceSearchResult? dbMatch;
    double closestDist = double.infinity;

    for (final beacon in beacons) {
      final dist = _haversineMeters(
        _currentCenter.latitude, _currentCenter.longitude,
        beacon.coordinates.latitude, beacon.coordinates.longitude,
      );
      if (dist < closestDist && dist <= _reResolveThresholdMeters) {
        closestDist = dist;
        dbMatch = PlaceSearchResult(
          id: beacon.id,
          name: beacon.name,
          address: beacon.description,
          coordinates: beacon.coordinates,
          isBeacon: true,
          source: 'database',
        );
      }
    }

    if (dbMatch != null) return dbMatch;

    // No DB match — call Mapbox Search Box reverse for POI name
    final geocoder = ref.read(reverseGeocodingServiceProvider);
    final result = await geocoder.reverseSearchPOI(
      latitude: _currentCenter.latitude,
      longitude: _currentCenter.longitude,
    );

    // Save to backend DB so future searches find it
    try {
      final placeService = ref.read(placeSearchServiceProvider);
      final saved = await placeService.resolveLocation(
        name: result.name,
        address: result.address,
        latitude: result.coordinates.latitude,
        longitude: result.coordinates.longitude,
      );
      return saved;
    } catch (_) {
      // DB save failed — still return the Mapbox result without an ID
      return PlaceSearchResult(
        name: result.name,
        address: result.address,
        coordinates: result.coordinates,
        source: 'mapbox',
      );
    }
  }

  Future<void> _getEstimateAndNavigate(PlaceSearchResult confirmedDropoff) async {
    final pickup = widget.confirmedPickup;
    if (pickup == null) {
      assert(false, 'confirmedPickup should not be null in dropoff mode');
      return;
    }
    final l10n = AppLocalizations.of(context);

    setState(() => _isResolving = true);

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
    setState(() => _isResolving = false);

    final state = ref.read(rideRequestProvider);
    if (state.error != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(l10n.estimateFailed(state.error!)),
          backgroundColor: Colors.red,
          action: SnackBarAction(
            label: l10n.retry,
            textColor: Colors.white,
            onPressed: () => _getEstimateAndNavigate(confirmedDropoff),
          ),
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

  static double _haversineMeters(double lat1, double lng1, double lat2, double lng2) {
    const r = 6371000.0;
    final dLat = (lat2 - lat1) * pi / 180;
    final dLng = (lng2 - lng1) * pi / 180;
    final a = sin(dLat / 2) * sin(dLat / 2) +
        cos(lat1 * pi / 180) * cos(lat2 * pi / 180) *
        sin(dLng / 2) * sin(dLng / 2);
    return r * 2 * atan2(sqrt(a), sqrt(1 - a));
  }

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final l10n = AppLocalizations.of(context);
    final isPickup = widget.mode == MapConfirmMode.pickup;
    final requestState = ref.watch(rideRequestProvider);
    final busy = _isResolving || requestState.isLoading;

    return Scaffold(
      body: Stack(
        children: [
          MapboxMapWidget(
            initialCameraPosition: CameraPosition(
              latitude: widget.initialCenter.latitude,
              longitude: widget.initialCenter.longitude,
              zoom: 17,
            ),
            myLocationEnabled: true,
            myLocationButtonEnabled: false,
            zoomControlsEnabled: false,
            onMapCreated: (controller) => _mapController = controller,
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

          // Drag hint (fades out after 3s or on first interaction)
          Center(
            child: Padding(
              padding: const EdgeInsets.only(top: 28),
              child: IgnorePointer(
                child: AnimatedOpacity(
                  opacity: _showDragHint ? 1.0 : 0.0,
                  duration: const Duration(milliseconds: 500),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    decoration: BoxDecoration(
                      color: Colors.black54,
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: Text(
                      l10n.dragToAdjustHint,
                      style: const TextStyle(color: Colors.white, fontSize: 12),
                    ),
                  ),
                ),
              ),
            ),
          ),

          // Top bar
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

          // Bottom sheet
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

                  // Location name
                  Text(
                    _resolvedResult?.name ?? widget.initialName,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  if (_resolvedResult?.address != null) ...[
                    const SizedBox(height: 4),
                    Text(
                      _resolvedResult!.address!,
                      style: TextStyle(fontSize: 13, color: Colors.grey[600]),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],

                  const SizedBox(height: 16),

                  // Button logic:
                  // 1. Within 50m → "Confirm Pickup/Dropoff" (direct)
                  // 2. Beyond 50m, not resolved yet → "Pick this location?"
                  // 3. Beyond 50m, resolved → "Confirm Pickup/Dropoff" (final)
                  SizedBox(
                    width: double.infinity,
                    child: _withinThreshold || _resolvedResult != null
                        ? ElevatedButton(
                            onPressed: busy ? null : _onConfirm,
                            style: ElevatedButton.styleFrom(
                              backgroundColor: config.primaryColor,
                              foregroundColor: Colors.white,
                              padding: const EdgeInsets.symmetric(vertical: 16),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                              ),
                            ),
                            child: busy
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
                          )
                        : OutlinedButton(
                            onPressed: busy ? null : _onPickLocation,
                            style: OutlinedButton.styleFrom(
                              foregroundColor: config.primaryColor,
                              side: BorderSide(color: config.primaryColor),
                              padding: const EdgeInsets.symmetric(vertical: 16),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                              ),
                            ),
                            child: busy
                                ? SizedBox(
                                    width: 20,
                                    height: 20,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      color: config.primaryColor,
                                    ),
                                  )
                                : Text(
                                    l10n.pickThisLocation,
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
