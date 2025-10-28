import 'package:flutter/material.dart';
import 'package:mapbox_maps_flutter/mapbox_maps_flutter.dart';
import '../config/mapbox_config.dart';
import '../models/place_search_result.dart';

/// A Mapbox map widget that displays a route between two points
///
/// Displays pickup and dropoff markers with a route line connecting them.
/// Automatically fits the camera to show the entire route.
class RouteMapWidget extends StatefulWidget {
  final PlaceSearchResult pickupLocation;
  final PlaceSearchResult dropoffLocation;
  final Map<String, dynamic>? routeGeometry; // GeoJSON LineString from backend
  final double height;

  const RouteMapWidget({
    required this.pickupLocation,
    required this.dropoffLocation,
    this.routeGeometry,
    this.height = 250,
    super.key,
  });

  @override
  State<RouteMapWidget> createState() => _RouteMapWidgetState();
}

class _RouteMapWidgetState extends State<RouteMapWidget> {
  MapboxMap? _mapboxMap;
  PointAnnotationManager? _pointAnnotationManager;
  PolylineAnnotationManager? _polylineAnnotationManager;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: widget.height,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(12),
        child: MapWidget(
          cameraOptions: _getInitialCamera(),
          styleUri: MapboxConfig.defaultStyleUrl,
          textureView: true,
          onMapCreated: _onMapCreated,
        ),
      ),
    );
  }

  CameraOptions _getInitialCamera() {
    // Center camera between pickup and dropoff
    final centerLat = (widget.pickupLocation.coordinates.latitude +
            widget.dropoffLocation.coordinates.latitude) /
        2;
    final centerLng = (widget.pickupLocation.coordinates.longitude +
            widget.dropoffLocation.coordinates.longitude) /
        2;

    return CameraOptions(
      center: Point(
        coordinates: Position(centerLng, centerLat),
      ),
      zoom: 13.0, // Will be adjusted to fit route
    );
  }

  Future<void> _onMapCreated(MapboxMap mapboxMap) async {
    _mapboxMap = mapboxMap;

    debugPrint('RouteMapWidget: Map created');
    debugPrint('RouteMapWidget: Route geometry available: ${widget.routeGeometry != null}');
    if (widget.routeGeometry != null) {
      debugPrint('RouteMapWidget: Route geometry type: ${widget.routeGeometry!['type']}');
      debugPrint('RouteMapWidget: Route coordinates count: ${(widget.routeGeometry!['coordinates'] as List?)?.length}');
    }

    // Create annotation managers
    _pointAnnotationManager =
        await mapboxMap.annotations.createPointAnnotationManager();
    _polylineAnnotationManager =
        await mapboxMap.annotations.createPolylineAnnotationManager();

    debugPrint('RouteMapWidget: Annotation managers created');

    // Add markers
    await _addMarkers();
    debugPrint('RouteMapWidget: Markers added');

    // Draw route if geometry available
    if (widget.routeGeometry != null) {
      await _drawRoute();
      debugPrint('RouteMapWidget: Route drawn');
    }

    // Fit camera to show entire route
    await _fitCameraToRoute();
    debugPrint('RouteMapWidget: Camera fitted to route');
  }

  Future<void> _addMarkers() async {
    if (_pointAnnotationManager == null) {
      debugPrint('RouteMapWidget: Point annotation manager is null');
      return;
    }

    try {
      debugPrint('RouteMapWidget: Creating pickup marker at ${widget.pickupLocation.coordinates.latitude}, ${widget.pickupLocation.coordinates.longitude}');

      // Pickup marker (green)
      final pickupAnnotation = await _pointAnnotationManager!.create(
        PointAnnotationOptions(
          geometry: Point(
            coordinates: Position(
              widget.pickupLocation.coordinates.longitude,
              widget.pickupLocation.coordinates.latitude,
            ),
          ),
          iconImage: 'marker', // Use built-in Mapbox marker
          iconSize: 1.5,
          iconAnchor: IconAnchor.BOTTOM,
        ),
      );
      debugPrint('RouteMapWidget: Pickup marker created: ${pickupAnnotation.id}');

      debugPrint('RouteMapWidget: Creating dropoff marker at ${widget.dropoffLocation.coordinates.latitude}, ${widget.dropoffLocation.coordinates.longitude}');

      // Dropoff marker (red)
      final dropoffAnnotation = await _pointAnnotationManager!.create(
        PointAnnotationOptions(
          geometry: Point(
            coordinates: Position(
              widget.dropoffLocation.coordinates.longitude,
              widget.dropoffLocation.coordinates.latitude,
            ),
          ),
          iconImage: 'marker',
          iconSize: 1.5,
          iconAnchor: IconAnchor.BOTTOM,
        ),
      );
      debugPrint('RouteMapWidget: Dropoff marker created: ${dropoffAnnotation.id}');
    } catch (e) {
      debugPrint('RouteMapWidget: Error creating markers: $e');
    }
  }

  Future<void> _drawRoute() async {
    if (_polylineAnnotationManager == null) {
      debugPrint('RouteMapWidget: Polyline annotation manager is null');
      return;
    }

    if (widget.routeGeometry == null) {
      debugPrint('RouteMapWidget: Route geometry is null');
      return;
    }

    try {
      // Extract coordinates from GeoJSON geometry
      final coordinates = widget.routeGeometry!['coordinates'] as List;
      debugPrint('RouteMapWidget: Converting ${coordinates.length} route points to polyline');

      // Convert to Mapbox Position list
      final points = coordinates.map((coord) {
        return Position(
          (coord[0] as num).toDouble(), // longitude
          (coord[1] as num).toDouble(), // latitude
        );
      }).toList();

      debugPrint('RouteMapWidget: Creating polyline with ${points.length} points');

      // Create polyline
      final polyline = await _polylineAnnotationManager!.create(
        PolylineAnnotationOptions(
          geometry: LineString(coordinates: points),
          lineColor: Colors.blue.toARGB32(),
          lineWidth: 5.0,
        ),
      );

      debugPrint('RouteMapWidget: Polyline created: ${polyline.id}');
    } catch (e, stackTrace) {
      debugPrint('RouteMapWidget: Error drawing route: $e');
      debugPrint('RouteMapWidget: Stack trace: $stackTrace');
    }
  }

  Future<void> _fitCameraToRoute() async {
    if (_mapboxMap == null) return;

    try {
      // Calculate bounds to include both markers
      final pickupLat = widget.pickupLocation.coordinates.latitude;
      final pickupLng = widget.pickupLocation.coordinates.longitude;
      final dropoffLat = widget.dropoffLocation.coordinates.latitude;
      final dropoffLng = widget.dropoffLocation.coordinates.longitude;

      debugPrint('RouteMapWidget: Fitting camera to bounds');
      debugPrint('RouteMapWidget: Pickup: $pickupLat, $pickupLng');
      debugPrint('RouteMapWidget: Dropoff: $dropoffLat, $dropoffLng');

      final minLat = pickupLat < dropoffLat ? pickupLat : dropoffLat;
      final maxLat = pickupLat > dropoffLat ? pickupLat : dropoffLat;
      final minLng = pickupLng < dropoffLng ? pickupLng : dropoffLng;
      final maxLng = pickupLng > dropoffLng ? pickupLng : dropoffLng;

      // Add 60% padding on each side for wider view
      final latPadding = (maxLat - minLat) * 0.6;
      final lngPadding = (maxLng - minLng) * 0.6;

      final swLat = minLat - latPadding;
      final swLng = minLng - lngPadding;
      final neLat = maxLat + latPadding;
      final neLng = maxLng + lngPadding;

      debugPrint('RouteMapWidget: SW corner: $swLat, $swLng');
      debugPrint('RouteMapWidget: NE corner: $neLat, $neLng');

      // Use Mapbox's camera for bounds instead of manual zoom calculation
      final bounds = CoordinateBounds(
        southwest: Point(coordinates: Position(swLng, swLat)),
        northeast: Point(coordinates: Position(neLng, neLat)),
        infiniteBounds: false,
      );

      // Set camera bounds with padding for the UI overlay
      await _mapboxMap!.setBounds(
        CameraBoundsOptions(
          bounds: bounds,
          maxZoom: 15.0,
          minZoom: 11.0,
        ),
      );

      // Wait a bit for bounds to be set
      await Future.delayed(const Duration(milliseconds: 200));

      // Now animate to fit the bounds with UI padding
      await _mapboxMap!.flyTo(
        CameraOptions(
          center: Point(
            coordinates: Position(
              (minLng + maxLng) / 2,
              (minLat + maxLat) / 2,
            ),
          ),
          zoom: 12.5, // Lower zoom for wider view
          padding: MbxEdgeInsets(
            top: 120,    // More padding for transparent AppBar
            left: 60,
            bottom: 350, // More padding for bottom card
            right: 60,
          ),
        ),
        MapAnimationOptions(duration: 1000, startDelay: 0),
      );

      debugPrint('RouteMapWidget: Camera animation completed');
    } catch (e, stackTrace) {
      debugPrint('RouteMapWidget: Error fitting camera to route: $e');
      debugPrint('RouteMapWidget: Stack trace: $stackTrace');
    }
  }
}
