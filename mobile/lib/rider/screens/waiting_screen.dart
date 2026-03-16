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
        return const Color(0xFF4CAF50); // green
      case DriverPinState.notified:
        return const Color(0xFF2196F3); // blue
      case DriverPinState.unavailable:
        return const Color(0xFF9E9E9E); // grey
    }
  }

  String get iconName {
    switch (state) {
      case DriverPinState.notified:
        return 'car';
      default:
        return 'circle';
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

  // Cancel
  bool _isCancelling = false;

  // Countdown / retry
  Timer? _countdownTimer;
  int _countdownSeconds = 0;
  int _countdownTotal = 60; // total seconds for arc progress
  int _retryAttempt = 0;
  static const _maxRetries = 3;

  // Entry animation
  bool _entryDone = false;
  late AnimationController _entryController;
  late Animation<double> _sheetSlide;

  // Status message crossfade key
  String _statusMessage = '';
  String _statusSubMessage = '';

  // Track previous state for "driver declined" message
  bool _wasNoDrivers = false;

  @override
  void initState() {
    super.initState();

    // Entry animation controller (2 sec total)
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
      setState(() {
        _riderLocation = LatLng(pos.latitude, pos.longitude);
      });
    } catch (_) {
      // Fallback: use pickup location from the request
      final req = ref.read(rideRequestProvider).request;
      if (req != null && mounted) {
        setState(() {
          _riderLocation = req.pickupLocation.coordinates;
        });
      }
    }

    // Start entry animation after location is set
    if (mounted) _playEntryAnimation();
  }

  void _playEntryAnimation() {
    // Camera: start zoomed out, fly to street level
    if (_mapController != null && _riderLocation != null) {
      _mapController!.animateCamera(
        CameraPosition(
          latitude: _riderLocation!.latitude,
          longitude: _riderLocation!.longitude,
          zoom: 16,
        ),
        duration: const Duration(milliseconds: 1800),
      );
    }
    _entryController.forward().then((_) {
      if (mounted) setState(() => _entryDone = true);
    });
  }

  @override
  void dispose() {
    _countdownTimer?.cancel();
    _entryController.dispose();
    _mapController?.dispose();
    super.dispose();
  }

  // ---------------------------------------------------------------------------
  // Countdown logic
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
        _statusMessage =
            "No active drivers available right now.";
        _statusSubMessage =
            "You can wait or cancel \u2014 we won\u2019t hold you.";
      } else {
        _statusMessage =
            "No drivers accepted this round.";
        _statusSubMessage =
            "Retrying in $_countdownSeconds seconds\u2026 (attempt $_retryAttempt of $_maxRetries)";
      }
    } else if (_wasNoDrivers && !noDrivers) {
      // Search just resumed after no-drivers
      _statusMessage = "That driver\u2019s unavailable \u2014 trying the next one";
      _statusSubMessage = '';
    } else {
      // Normal searching
      final waitMin = state.request?.estimatedWaitTime;
      if (waitMin != null && waitMin > 0) {
        _statusMessage = "Notifying a driver $waitMin minutes away\u2026";
      } else {
        _statusMessage = "Finding a driver for you\u2026";
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

    // --- Listener for state transitions ---
    ref.listen<RideRequestState>(rideRequestProvider, (previous, next) {
      if (_isCancelling) return;

      // Matched → navigate to active ride
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

      // No drivers → start countdown
      if (next.noDriversAvailableUntil != null &&
          next.noDriversAvailableUntil != previous?.noDriversAvailableUntil) {
        _startCountdown(next.noDriversAvailableUntil!);
        return;
      }

      // Search resumed → cancel countdown
      if (previous?.noDriversAvailableUntil != null &&
          next.noDriversAvailableUntil == null &&
          next.request != null &&
          mounted) {
        _countdownTimer?.cancel();
        setState(() => _countdownSeconds = 0);
        return;
      }

      // Request cleared externally
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

    // Rider location fallback
    final loc = _riderLocation ??
        requestState.request?.pickupLocation.coordinates ??
        const LatLng(MapboxConfig.uiCampusLatitude,
            MapboxConfig.uiCampusLongitude);

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _showCancelDialog();
      },
      child: Scaffold(
        body: Stack(
          children: [
            // ===== 1. FULL-SCREEN MAP =====
            MapboxMapWidget(
              initialCameraPosition: CameraPosition(
                latitude: loc.latitude,
                longitude: loc.longitude,
                zoom: 13, // start zoomed out; entry anim zooms in
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

            // ===== 2. SONAR PULSE (centered on screen) =====
            Center(
              child: IgnorePointer(
                child: SonarPulse(
                  active: !noDrivers,
                  color: config.primaryColor.withValues(alpha: 0.6),
                  maxRadius: 100,
                ),
              ),
            ),

            // ===== 3. SEARCH RADIUS INDICATOR (faint ring) =====
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

            // ===== 4. CANCEL WARNING BANNER =====
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

            // ===== 5. BOTTOM STATUS SHEET =====
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

              // --- Status message (animated crossfade) ---
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

              // --- Retry progress arc (only during countdown) ---
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

              // --- Searching indicator (only while actively searching) ---
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

              // --- Color legend ---
              _buildLegend(),

              const SizedBox(height: 16),

              // --- Cancel button ---
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
