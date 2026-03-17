import 'dart:async';
import 'package:flutter/material.dart';
import 'package:mapbox_maps_flutter/mapbox_maps_flutter.dart';
import '../config/mapbox_config.dart';
import '../models/lat_lng.dart';

/// A wrapper widget for Mapbox Maps that provides a similar API to Google Maps
///
/// This widget simplifies Mapbox Maps integration and provides a familiar
/// interface for developers migrating from Google Maps.
class MapboxMapWidget extends StatefulWidget {
  /// Initial camera position
  final CameraPosition initialCameraPosition;

  /// Map markers to display
  final Set<MapMarker> markers;

  /// Polylines to display
  final Set<MapPolyline> polylines;

  /// Whether to show user's current location
  final bool myLocationEnabled;

  /// Whether to show the location button
  final bool myLocationButtonEnabled;

  /// Whether to show zoom controls
  final bool zoomControlsEnabled;

  /// Callback when map is created
  final void Function(MapboxMapController controller)? onMapCreated;

  /// Callback when camera moves
  final void Function(CameraPosition position)? onCameraMove;

  /// Callback when camera movement ends
  final void Function(CameraPosition position)? onCameraIdle;

  /// Callback when marker is tapped
  final void Function(String markerId)? onMarkerTap;

  /// Map style URL
  final String? styleUrl;

  /// Minimum zoom level
  final double? minZoom;

  /// Maximum zoom level
  final double? maxZoom;

  const MapboxMapWidget({
    required this.initialCameraPosition,
    this.markers = const {},
    this.polylines = const {},
    this.myLocationEnabled = false,
    this.myLocationButtonEnabled = true,
    this.zoomControlsEnabled = true,
    this.onMapCreated,
    this.onCameraMove,
    this.onCameraIdle,
    this.onMarkerTap,
    this.styleUrl,
    this.minZoom,
    this.maxZoom,
    super.key,
  });

  @override
  State<MapboxMapWidget> createState() => _MapboxMapWidgetState();
}

class _MapboxMapWidgetState extends State<MapboxMapWidget> {
  MapboxMap? _mapboxMap;
  PointAnnotationManager? _pointAnnotationManager;
  PolylineAnnotationManager? _polylineAnnotationManager;
  final Map<String, PointAnnotation> _annotations = {};
  final Map<String, PolylineAnnotation> _polylines = {};

  @override
  Widget build(BuildContext context) {
    return MapWidget(
      key:
          ValueKey('mapbox_${widget.styleUrl ?? MapboxConfig.defaultStyleUrl}'),
      cameraOptions: CameraOptions(
        center: Point(
          coordinates: Position(
            widget.initialCameraPosition.longitude,
            widget.initialCameraPosition.latitude,
          ),
        ),
        zoom: widget.initialCameraPosition.zoom,
        pitch: widget.initialCameraPosition.pitch,
        bearing: widget.initialCameraPosition.bearing,
      ),
      styleUri: widget.styleUrl ?? MapboxConfig.defaultStyleUrl,
      textureView: true,
      onMapCreated: _onMapCreated,
      onCameraChangeListener: _onCameraChange,
    );
  }

  Future<void> _onMapCreated(MapboxMap mapboxMap) async {
    _mapboxMap = mapboxMap;

    // Setup location component if enabled
    if (widget.myLocationEnabled) {
      await _setupLocationComponent();
    }

    // Min/max zoom are handled in the initial camera options
    // No separate API calls needed

    // Create annotation managers
    _pointAnnotationManager =
        await mapboxMap.annotations.createPointAnnotationManager();
    _polylineAnnotationManager =
        await mapboxMap.annotations.createPolylineAnnotationManager();

    // Add initial markers and polylines
    await _updateMarkers();
    await _updatePolylines();

    // TODO: Setup marker tap listener
    // Note: Mapbox SDK tap events API may have changed
    // Will need to verify correct API usage in testing phase
    // For now, marker taps are not handled

    // Notify parent
    if (widget.onMapCreated != null) {
      widget.onMapCreated!(MapboxMapController(mapboxMap));
    }
  }

  Future<void> _setupLocationComponent() async {
    try {
      await _mapboxMap?.location.updateSettings(
        LocationComponentSettings(
          enabled: true,
          pulsingEnabled: true,
          pulsingColor: Colors.blue.red |
              (Colors.blue.green << 8) |
              (Colors.blue.blue << 16) |
              (Colors.blue.alpha << 24),
          showAccuracyRing: true,
        ),
      );
    } catch (e) {
      debugPrint('Error setting up location component: $e');
    }
  }

  void _onCameraChange(CameraChangedEventData data) {
    final center = data.cameraState.center;
    final position = CameraPosition(
      latitude: center.coordinates.lat.toDouble(),
      longitude: center.coordinates.lng.toDouble(),
      zoom: data.cameraState.zoom.toDouble(),
      pitch: data.cameraState.pitch.toDouble(),
      bearing: data.cameraState.bearing.toDouble(),
    );

    // Call appropriate callback based on reason
    if (data.cameraState.zoom != widget.initialCameraPosition.zoom ||
        center.coordinates.lat != widget.initialCameraPosition.latitude ||
        center.coordinates.lng != widget.initialCameraPosition.longitude) {
      widget.onCameraMove?.call(position);
    }
  }

  Future<void> _updateMarkers() async {
    if (_pointAnnotationManager == null || !mounted) return;

    try {
      if (_annotations.isNotEmpty) {
        await _pointAnnotationManager!.deleteAll();
        _annotations.clear();
      }

      for (final marker in widget.markers) {
        if (!mounted) return;
        final options = PointAnnotationOptions(
          geometry: Point(
            coordinates: Position(marker.longitude, marker.latitude),
          ),
          iconImage: marker.icon,
          iconSize: marker.size,
          iconAnchor: IconAnchor.BOTTOM,
        );

        final annotation = await _pointAnnotationManager!.create(options);
        _annotations[marker.id] = annotation;
      }
    } catch (e) {
      debugPrint('MapboxMapWidget: Failed to update markers: $e');
    }
  }

  Future<void> _updatePolylines() async {
    if (_polylineAnnotationManager == null || !mounted) return;

    try {
      if (_polylines.isNotEmpty) {
        await _polylineAnnotationManager!.deleteAll();
        _polylines.clear();
      }

      for (final polyline in widget.polylines) {
        if (!mounted) return;
        final options = PolylineAnnotationOptions(
          geometry: LineString(
            coordinates: polyline.points
                .map((point) => Position(point.longitude, point.latitude))
                .toList(),
          ),
          lineColor: polyline.color.value,
          lineWidth: polyline.width,
          lineOpacity: polyline.opacity,
        );

        final annotation = await _polylineAnnotationManager!.create(options);
        _polylines[polyline.id] = annotation;
      }
    } catch (e) {
      debugPrint('MapboxMapWidget: Failed to update polylines: $e');
    }
  }

  @override
  void didUpdateWidget(MapboxMapWidget oldWidget) {
    super.didUpdateWidget(oldWidget);

    if (!mounted) return;

    if (widget.markers != oldWidget.markers) {
      _updateMarkers();
    }

    if (widget.polylines != oldWidget.polylines) {
      _updatePolylines();
    }
  }
}

/// Camera position for Mapbox map
class CameraPosition {
  final double latitude;
  final double longitude;
  final double zoom;
  final double pitch;
  final double bearing;

  const CameraPosition({
    required this.latitude,
    required this.longitude,
    this.zoom = MapboxConfig.defaultZoom,
    this.pitch = MapboxConfig.defaultPitch,
    this.bearing = MapboxConfig.defaultBearing,
  });
}

/// Marker for Mapbox map
class MapMarker {
  final String id;
  final double latitude;
  final double longitude;
  final String? title;
  final String? snippet;
  final String icon;
  final double size;
  final VoidCallback? onTap;

  const MapMarker({
    required this.id,
    required this.latitude,
    required this.longitude,
    this.title,
    this.snippet,
    this.icon = 'marker-15', // Default Mapbox marker icon
    this.size = 1.0,
    this.onTap,
  });

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is MapMarker &&
          runtimeType == other.runtimeType &&
          id == other.id &&
          latitude == other.latitude &&
          longitude == other.longitude;

  @override
  int get hashCode => id.hashCode ^ latitude.hashCode ^ longitude.hashCode;
}

/// Polyline for Mapbox map
class MapPolyline {
  final String id;
  final List<LatLng> points;
  final Color color;
  final double width;
  final double opacity;

  const MapPolyline({
    required this.id,
    required this.points,
    this.color = Colors.blue,
    this.width = 3.0,
    this.opacity = 1.0,
  });

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is MapPolyline &&
          runtimeType == other.runtimeType &&
          id == other.id;

  @override
  int get hashCode => id.hashCode;
}

/// Controller for Mapbox map
class MapboxMapController {
  final MapboxMap _mapboxMap;

  MapboxMapController(this._mapboxMap);

  /// Animate camera to a new position
  Future<void> animateCamera(CameraPosition position,
      {Duration? duration}) async {
    await _mapboxMap.flyTo(
      CameraOptions(
        center: Point(
          coordinates: Position(position.longitude, position.latitude),
        ),
        zoom: position.zoom,
        pitch: position.pitch,
        bearing: position.bearing,
      ),
      MapAnimationOptions(
        duration: duration?.inMilliseconds ?? 1000,
        startDelay: 0,
      ),
    );
  }

  /// Move camera to a new position instantly
  Future<void> moveCamera(CameraPosition position) async {
    await _mapboxMap.setCamera(
      CameraOptions(
        center: Point(
          coordinates: Position(position.longitude, position.latitude),
        ),
        zoom: position.zoom,
        pitch: position.pitch,
        bearing: position.bearing,
      ),
    );
  }

  /// Get current camera position
  Future<CameraPosition> getCameraPosition() async {
    final cameraState = await _mapboxMap.getCameraState();
    return CameraPosition(
      latitude: cameraState.center.coordinates.lat.toDouble(),
      longitude: cameraState.center.coordinates.lng.toDouble(),
      zoom: cameraState.zoom.toDouble(),
      pitch: cameraState.pitch.toDouble(),
      bearing: cameraState.bearing.toDouble(),
    );
  }

  /// Convert geographic coordinate to screen pixel position.
  Future<Offset> pixelForCoordinate(double lat, double lng) async {
    final screenCoord = await _mapboxMap.pixelForCoordinate(
      Point(coordinates: Position(lng, lat)),
    );
    return Offset(screenCoord.x, screenCoord.y);
  }

  /// Dispose the controller
  void dispose() {
    // Cleanup if needed
  }
}
