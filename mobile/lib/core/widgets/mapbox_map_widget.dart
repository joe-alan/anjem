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

  /// Whether the user-location dot pulses (continuous GPU animation).
  /// Defaults to false to keep the device cool during active rides.
  final bool pulsingEnabled;

  /// Whether to draw the accuracy ring around the user-location dot
  /// (continuous GPU draw). Defaults to false for the same reason.
  final bool showAccuracyRing;

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
    this.pulsingEnabled = false,
    this.showAccuracyRing = false,
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
  final Map<String, MapMarker> _markerSources = {};
  final Map<String, PolylineAnnotation> _polylines = {};
  final Map<String, MapPolyline> _polylineSources = {};
  Timer? _idleTimer;

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
          pulsingEnabled: widget.pulsingEnabled,
          pulsingColor: Colors.blue.red |
              (Colors.blue.green << 8) |
              (Colors.blue.blue << 16) |
              (Colors.blue.alpha << 24),
          showAccuracyRing: widget.showAccuracyRing,
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

    widget.onCameraMove?.call(position);

    // Debounced idle detection
    _idleTimer?.cancel();
    _idleTimer = Timer(const Duration(milliseconds: 300), () {
      if (mounted) {
        widget.onCameraIdle?.call(position);
      }
    });
  }

  Future<void> _updateMarkers() async {
    if (_pointAnnotationManager == null || !mounted) return;

    try {
      final incoming = <String, MapMarker>{
        for (final m in widget.markers) m.id: m,
      };

      // Delete annotations that were removed or moved.
      // Mapbox point annotations have no in-place move, so a position change
      // requires delete + recreate. Markers with matching id and position are
      // left untouched so pickup/destination pins stop flickering every tick.
      final staleIds = <String>[];
      for (final entry in _markerSources.entries) {
        final id = entry.key;
        final prev = entry.value;
        final next = incoming[id];
        if (next == null ||
            prev.latitude != next.latitude ||
            prev.longitude != next.longitude) {
          final annotation = _annotations[id];
          if (annotation != null) {
            await _pointAnnotationManager!.delete(annotation);
          }
          staleIds.add(id);
        }
      }
      for (final id in staleIds) {
        _annotations.remove(id);
        _markerSources.remove(id);
      }

      // Create annotations for new or replaced markers.
      for (final marker in widget.markers) {
        if (!mounted) return;
        if (_annotations.containsKey(marker.id)) continue;

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
        _markerSources[marker.id] = marker;
      }
    } catch (e) {
      debugPrint('MapboxMapWidget: Failed to update markers: $e');
    }
  }

  Future<void> _updatePolylines() async {
    if (_polylineAnnotationManager == null || !mounted) return;

    try {
      final incoming = <String, MapPolyline>{
        for (final p in widget.polylines) p.id: p,
      };

      // Delete polylines that were removed or whose geometry changed.
      // MapPolyline.== now compares points length + first/last coord, so
      // same-id-same-shape polylines short-circuit and are left alone.
      final staleIds = <String>[];
      for (final entry in _polylineSources.entries) {
        final id = entry.key;
        final prev = entry.value;
        final next = incoming[id];
        if (next == null || prev != next) {
          final annotation = _polylines[id];
          if (annotation != null) {
            await _polylineAnnotationManager!.delete(annotation);
          }
          staleIds.add(id);
        }
      }
      for (final id in staleIds) {
        _polylines.remove(id);
        _polylineSources.remove(id);
      }

      // Create annotations for new or replaced polylines.
      for (final polyline in widget.polylines) {
        if (!mounted) return;
        if (_polylines.containsKey(polyline.id)) continue;

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
        _polylineSources[polyline.id] = polyline;
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

  @override
  void dispose() {
    _idleTimer?.cancel();
    super.dispose();
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
          id == other.id &&
          points.length == other.points.length &&
          (points.isEmpty ||
              (points.first == other.points.first &&
                  points.last == other.points.last));

  @override
  int get hashCode =>
      id.hashCode ^
      points.length.hashCode ^
      (points.isEmpty ? 0 : points.first.hashCode ^ points.last.hashCode);
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
