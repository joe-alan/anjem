import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';

import '../../core/config/app_config.dart';
import '../../core/config/mapbox_config.dart';
import '../../core/models/lat_lng.dart';
import '../../core/providers/ride_request_provider.dart';
import '../../core/widgets/mapbox_map_widget.dart';
import '../widgets/retry_progress_arc.dart';
import '../widgets/sonar_pulse.dart';
import 'rider_active_ride_screen.dart';
import 'rider_home_screen.dart';

// ---------------------------------------------------------------------------
// Driver pin states — ready for backend events (not yet broadcast to rider).
// ---------------------------------------------------------------------------
enum DriverPinState { active, notified, unavailable }

class DriverPin {
  final String id;
  final LatLng location;
  final DriverPinState state;
  const DriverPin({
    required this.id,
    required this.location,
    required this.state,
  });

  Color get color {
    switch (state) {
      case DriverPinState.active:
        return const Color(0xFF4CAF50);
      case DriverPinState.notified:
        return const Color(0xFF2196F3);
      case DriverPinState.unavailable:
        return const Color(0xFF9E9E9E);
    }
  }
}

// ===========================================================================
// WaitingScreen — full-screen live map during driver matching
// ===========================================================================
class WaitingScreen extends ConsumerStatefulWidget {
  const WaitingScreen({super.key});

  @override
  ConsumerState<WaitingScreen> createState() => _WaitingScreenState();
}

class _WaitingScreenState extends ConsumerState<WaitingScreen>
    with TickerProviderStateMixin {
  // Map
  MapboxMapController? _mapController;
  LatLng? _riderLocation;

  // Driver pins (populated from backend events when available)
  final List<DriverPin> _driverPins = [];
  Map<String, Offset> _pinPositions = {};

  // Cancel
  bool _isCancelling = false;

  // Countdown / retry
  Timer? _countdownTimer;
  int _countdownSeconds = 0;
  int _countdownTotal = 60;
  int _retryAttempt = 0;
  static const _maxRetries = 3;

  // Entry animation
  bool _entryDone = false;
  late AnimationController _entryController;
  late Animation<double> _sheetSlide;

  // Status messages
  String _statusMessage = '';
  String _statusSubMessage = '';
  bool _wasNoDrivers = false;

  @override
  void initState() {
    super.initState();

    _entryController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1800),
    );
    _sheetSlide = CurvedAnimation(
      parent: _entryController,
      curve: const Interval(0.6, 1.0, curve: Curves.easeOut),
    );

    _initLocation();
  }

  Future<void> _initLocation() async {
    try {
      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 5),
        ),
      );
      if (!mounted) return;
      setState(() => _riderLocation = LatLng(pos.latitude, pos.longitude));
    } catch (_) {
      final req = ref.read(rideRequestProvider).request;
      if (req != null && mounted) {
        setState(() => _riderLocation = req.pickupLocation.coordinates);
      }
    }

    if (mounted) _playEntryAnimation();
  }

  // ---------------------------------------------------------------------------
  // Camera & entry animation
  // ---------------------------------------------------------------------------
  void _playEntryAnimation() {
    _fitCameraToDrivers();
    _entryController.forward().then((_) {
      if (!mounted) return;
      setState(() => _entryDone = true);
      Future.delayed(const Duration(milliseconds: 400), () {
        _computePinPositions();
      });
    });
  }

  /// Center camera on rider, zoom out enough to show all driver pins.
  void _fitCameraToDrivers() {
    if (_mapController == null || _riderLocation == null) return;

    double zoom = 16; // default street-level if no drivers

    if (_driverPins.isNotEmpty) {
      double maxSpan = 0;
      for (final d in _driverPins) {
        final latDiff =
            (d.location.latitude - _riderLocation!.latitude).abs();
        final lngDiff =
            (d.location.longitude - _riderLocation!.longitude).abs();
        final dist = latDiff > lngDiff ? latDiff : lngDiff;
        if (dist > maxSpan) maxSpan = dist;
      }

      final fullSpan = maxSpan * 2;
      if (fullSpan < 0.004) {
        zoom = 16;
      } else if (fullSpan < 0.008) {
        zoom = 15;
      } else if (fullSpan < 0.016) {
        zoom = 14.5;
      } else if (fullSpan < 0.03) {
        zoom = 13.5;
      } else {
        zoom = 13;
      }
    }

    _mapController!.animateCamera(
      CameraPosition(
        latitude: _riderLocation!.latitude,
        longitude: _riderLocation!.longitude,
        zoom: zoom,
      ),
      duration: const Duration(milliseconds: 1800),
    );
  }

  /// Convert each driver's lat/lng to screen pixel position.
  Future<void> _computePinPositions() async {
    if (_mapController == null || !mounted) return;

    final positions = <String, Offset>{};
    for (final pin in _driverPins) {
      try {
        final offset = await _mapController!.pixelForCoordinate(
          pin.location.latitude,
          pin.location.longitude,
        );
        positions[pin.id] = offset;
      } catch (e) {
        debugPrint('pixelForCoordinate failed for ${pin.id}: $e');
      }
    }

    if (mounted) setState(() => _pinPositions = positions);
  }

  @override
  void dispose() {
    _countdownTimer?.cancel();
    _entryController.dispose();
    _mapController?.dispose();
    super.dispose();
  }

  // ---------------------------------------------------------------------------
  // Countdown
  // ---------------------------------------------------------------------------
  void _startCountdown(DateTime until) {
    _countdownTimer?.cancel();
    final total = until.difference(DateTime.now()).inSeconds.clamp(1, 120);
    _countdownTotal = total;
    _countdownSeconds = total;
    _retryAttempt++;

    _countdownTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) return;
      final remaining = until.difference(DateTime.now()).inSeconds;
      if (remaining <= 0) {
        _countdownTimer?.cancel();
        if (mounted && !_isCancelling) {
          Navigator.of(context).pushAndRemoveUntil(
            MaterialPageRoute(builder: (_) => const RiderHomeScreen()),
            (route) => false,
          );
        }
      } else {
        setState(() => _countdownSeconds = remaining);
      }
    });
    setState(() {});
  }

  // ---------------------------------------------------------------------------
  // Cancel
  // ---------------------------------------------------------------------------
  Future<void> _showCancelDialog() async {
    if (_isCancelling) return;
    final nav = Navigator.of(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Cancel Request?'),
        content:
            const Text('Are you sure you want to cancel this ride request?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('No'),
          ),
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            child:
                const Text('Yes, Cancel', style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );
    if (confirmed == true && mounted) {
      setState(() => _isCancelling = true);
      await ref.read(rideRequestProvider.notifier).cancelRequest();
      if (mounted) {
        nav.pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => const RiderHomeScreen()),
          (route) => false,
        );
      }
    }
  }

  // ---------------------------------------------------------------------------
  // Status messages
  // ---------------------------------------------------------------------------
  void _updateStatusMessages(RideRequestState state) {
    final noDrivers = state.noDriversAvailableUntil != null;

    if (noDrivers) {
      if (_retryAttempt >= _maxRetries) {
        _statusMessage = 'No active drivers available right now.';
        _statusSubMessage = "You can wait or cancel \u2014 we won\u2019t hold you.";
      } else {
        _statusMessage = 'No drivers accepted this round.';
        _statusSubMessage =
            'Retrying in $_countdownSeconds seconds\u2026 (attempt $_retryAttempt of $_maxRetries)';
      }
    } else if (_wasNoDrivers && !noDrivers) {
      _statusMessage = "That driver\u2019s unavailable \u2014 trying the next one";
      _statusSubMessage = '';
    } else {
      final waitMin = state.request?.estimatedWaitTime;
      if (waitMin != null && waitMin > 0) {
        _statusMessage = 'Notifying a driver $waitMin minutes away\u2026';
      } else {
        _statusMessage = 'Finding a driver for you\u2026';
      }
      _statusSubMessage = '';
    }
    _wasNoDrivers = noDrivers;
  }

  // ---------------------------------------------------------------------------
  // Build
  // ---------------------------------------------------------------------------
  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final requestState = ref.watch(rideRequestProvider);

    ref.listen<RideRequestState>(rideRequestProvider, (previous, next) {
      if (_isCancelling) return;

      if (next.isMatched && next.matchedRide != null && mounted) {
        _countdownTimer?.cancel();
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (_) =>
                RiderActiveRideScreen(initialRide: next.matchedRide!),
          ),
        );
        return;
      }

      if (next.noDriversAvailableUntil != null &&
          next.noDriversAvailableUntil != previous?.noDriversAvailableUntil) {
        _startCountdown(next.noDriversAvailableUntil!);
        return;
      }

      if (previous?.noDriversAvailableUntil != null &&
          next.noDriversAvailableUntil == null &&
          next.request != null &&
          mounted) {
        _countdownTimer?.cancel();
        setState(() => _countdownSeconds = 0);
        return;
      }

      if (previous?.request != null &&
          next.request == null &&
          !next.isMatched &&
          mounted) {
        _countdownTimer?.cancel();
        Navigator.of(context).pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => const RiderHomeScreen()),
          (route) => false,
        );
      }
    });

    _updateStatusMessages(requestState);

    final bool noDrivers = requestState.noDriversAvailableUntil != null;
    final bool poolExhausted = noDrivers && _retryAttempt >= _maxRetries;

    final loc = _riderLocation ??
        requestState.request?.pickupLocation.coordinates ??
        const LatLng(
            MapboxConfig.uiCampusLatitude, MapboxConfig.uiCampusLongitude);

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _showCancelDialog();
      },
      child: Scaffold(
        body: Stack(
          children: [
            // ===== 1. MAP (non-interactive) =====
            AbsorbPointer(
              child: MapboxMapWidget(
                initialCameraPosition: CameraPosition(
                  latitude: loc.latitude,
                  longitude: loc.longitude,
                  zoom: 13,
                ),
                myLocationEnabled: true,
                myLocationButtonEnabled: false,
                zoomControlsEnabled: false,
                markers: const {},
                polylines: const {},
                onMapCreated: (controller) {
                  _mapController = controller;
                  if (_riderLocation != null && !_entryDone) {
                    _playEntryAnimation();
                  }
                },
              ),
            ),

            // ===== 2. DRIVER PINS (Flutter overlays) =====
            for (final pin in _driverPins)
              if (_pinPositions.containsKey(pin.id))
                Positioned(
                  left: _pinPositions[pin.id]!.dx - 20,
                  top: _pinPositions[pin.id]!.dy - 20,
                  child: _DriverPinWidget(pin: pin),
                ),

            // ===== 3. SONAR PULSE (centered = rider location) =====
            Center(
              child: IgnorePointer(
                child: SonarPulse(
                  active: !noDrivers,
                  color: config.primaryColor.withValues(alpha: 0.7),
                  maxRadius: 100,
                ),
              ),
            ),

            // ===== 4. SEARCH RADIUS INDICATOR =====
            Center(
              child: IgnorePointer(
                child: AnimatedOpacity(
                  opacity: noDrivers ? 0.15 : 0.25,
                  duration: const Duration(milliseconds: 600),
                  child: Container(
                    width: 240,
                    height: 240,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: config.primaryColor.withValues(alpha: 0.3),
                        width: 1.5,
                      ),
                    ),
                  ),
                ),
              ),
            ),

            // ===== 5. CANCEL WARNING BANNER =====
            if (requestState.cancelCount >= 1)
              Positioned(
                top: 0,
                left: 0,
                right: 0,
                child: SafeArea(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 12, vertical: 10),
                      decoration: BoxDecoration(
                        color: Colors.orange.shade50,
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: Colors.orange.shade300),
                      ),
                      child: Row(
                        children: [
                          Icon(Icons.warning_amber_rounded,
                              color: Colors.orange.shade700, size: 20),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              requestState.cancelCount >= 2
                                  ? 'Warning: One more cancellation will suspend your account.'
                                  : 'Warning: Repeated cancellations may result in a temporary ban.',
                              style: TextStyle(
                                  color: Colors.orange.shade800, fontSize: 13),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),

            // ===== 6. BOTTOM STATUS SHEET =====
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              child: SlideTransition(
                position: Tween<Offset>(
                  begin: const Offset(0, 1),
                  end: Offset.zero,
                ).animate(_sheetSlide),
                child: _buildBottomSheet(
                  config: config,
                  noDrivers: noDrivers,
                  poolExhausted: poolExhausted,
                  requestState: requestState,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // Bottom sheet
  // ---------------------------------------------------------------------------
  Widget _buildBottomSheet({
    required AppConfig config,
    required bool noDrivers,
    required bool poolExhausted,
    required RideRequestState requestState,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).scaffoldBackgroundColor,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.1),
            blurRadius: 10,
            offset: const Offset(0, -2),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Drag handle
              Container(
                width: 36,
                height: 4,
                decoration: BoxDecoration(
                  color: Colors.grey[300],
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const SizedBox(height: 16),

              // Status message (animated crossfade)
              AnimatedSwitcher(
                duration: const Duration(milliseconds: 400),
                switchInCurve: Curves.easeOut,
                switchOutCurve: Curves.easeIn,
                transitionBuilder: (child, anim) {
                  return FadeTransition(
                    opacity: anim,
                    child: SlideTransition(
                      position: Tween<Offset>(
                        begin: const Offset(0, 0.15),
                        end: Offset.zero,
                      ).animate(anim),
                      child: child,
                    ),
                  );
                },
                child: Column(
                  key: ValueKey(_statusMessage),
                  children: [
                    Text(
                      _statusMessage,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    if (_statusSubMessage.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Text(
                        _statusSubMessage,
                        style: TextStyle(fontSize: 13, color: Colors.grey[600]),
                        textAlign: TextAlign.center,
                      ),
                    ],
                  ],
                ),
              ),

              const SizedBox(height: 16),

              // Retry progress arc (only during countdown)
              if (noDrivers && !poolExhausted) ...[
                RetryProgressArc(
                  progress: _countdownTotal > 0
                      ? 1.0 - (_countdownSeconds / _countdownTotal)
                      : 0,
                  size: 44,
                  color: config.primaryColor,
                ),
                const SizedBox(height: 12),
              ],

              // Searching indicator (only while actively searching)
              if (!noDrivers)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: SizedBox(
                    height: 3,
                    child: LinearProgressIndicator(
                      backgroundColor: Colors.grey[200],
                      color: config.primaryColor,
                    ),
                  ),
                ),

              // Color legend
              _buildLegend(),

              const SizedBox(height: 16),

              // Cancel button
              SizedBox(
                width: double.infinity,
                child: poolExhausted
                    ? ElevatedButton.icon(
                        onPressed:
                            requestState.isLoading ? null : _showCancelDialog,
                        icon: const Icon(Icons.close),
                        label: const Text('Cancel Request'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.red,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                        ),
                      )
                    : OutlinedButton.icon(
                        onPressed:
                            requestState.isLoading ? null : _showCancelDialog,
                        icon: const Icon(Icons.close, size: 18),
                        label: const Text('Cancel Request'),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: Colors.red,
                          side: const BorderSide(color: Colors.red),
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                        ),
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // Color legend
  // ---------------------------------------------------------------------------
  Widget _buildLegend() {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        _legendItem(const Color(0xFF2196F3), 'Being notified'),
        const SizedBox(width: 16),
        _legendItem(const Color(0xFF4CAF50), 'Active driver'),
        const SizedBox(width: 16),
        _legendItem(const Color(0xFF9E9E9E), 'Unavailable'),
      ],
    );
  }

  Widget _legendItem(Color color, String label) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 8,
          height: 8,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        const SizedBox(width: 4),
        Text(label, style: TextStyle(fontSize: 11, color: Colors.grey[600])),
      ],
    );
  }
}

// =============================================================================
// Driver pin widget — bare motorbike icon in pin color, blue pins pulse
// =============================================================================
class _DriverPinWidget extends StatefulWidget {
  final DriverPin pin;

  const _DriverPinWidget({required this.pin});

  @override
  State<_DriverPinWidget> createState() => _DriverPinWidgetState();
}

class _DriverPinWidgetState extends State<_DriverPinWidget>
    with SingleTickerProviderStateMixin {
  AnimationController? _pulseController;

  @override
  void initState() {
    super.initState();
    if (widget.pin.state == DriverPinState.notified) {
      _pulseController = AnimationController(
        vsync: this,
        duration: const Duration(milliseconds: 700),
        lowerBound: 0.8,
        upperBound: 1.3,
      )..repeat(reverse: true);
    }
  }

  @override
  void dispose() {
    _pulseController?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final icon = Icon(
      Icons.two_wheeler,
      color: widget.pin.color,
      size: 36,
      shadows: [
        Shadow(
          color: widget.pin.color.withValues(alpha: 0.5),
          blurRadius: 6,
        ),
        const Shadow(
          color: Colors.black26,
          blurRadius: 3,
          offset: Offset(0, 1),
        ),
      ],
    );

    if (_pulseController != null) {
      return AnimatedBuilder(
        animation: _pulseController!,
        builder: (_, child) {
          return Transform.scale(
            scale: _pulseController!.value,
            child: child,
          );
        },
        child: icon,
      );
    }

    return icon;
  }
}
