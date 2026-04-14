import 'dart:async';
import 'package:action_slider/action_slider.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:mobile/l10n/app_localizations.dart';
import '../../core/config/app_config.dart';
import '../../core/models/lat_lng.dart';
import '../../core/models/ride_request.dart';
import '../../core/providers/api_provider.dart';
import '../../core/services/api/api_exception.dart';
import '../../core/services/mapbox/mapbox_directions_service.dart';
import '../../core/providers/driver_incoming_request_provider.dart';
import '../../core/services/fcm/local_notification_service.dart';
import '../../core/providers/driver_status_provider.dart';
import '../../core/providers/ride_request_provider.dart';
import 'active_ride_screen.dart';

class RideRequestScreen extends ConsumerStatefulWidget {
  final RideRequest request;

  const RideRequestScreen({
    super.key,
    required this.request,
  });

  @override
  ConsumerState<RideRequestScreen> createState() => _RideRequestScreenState();
}

class _RideRequestScreenState extends ConsumerState<RideRequestScreen>
    with SingleTickerProviderStateMixin {
  static const int timeoutSeconds = 15;
  late int _secondsRemaining;
  Timer? _timer;
  bool _isProcessing = false;
  bool _isDismissing = false;
  String? _errorMessage;

  // Distance/ETA to pickup
  double? _distanceKm;
  int? _etaMinutes;

  // Smooth progress bar animation
  late AnimationController _progressController;

  @override
  void initState() {
    super.initState();
    final timerStart = widget.request.dispatchedAt ?? widget.request.createdAt;
    final elapsed = DateTime.now().difference(timerStart).inSeconds;
    _secondsRemaining = (timeoutSeconds - elapsed).clamp(0, timeoutSeconds);

    // Animate from current progress to 0 over remaining seconds
    _progressController = AnimationController(
      vsync: this,
      duration: Duration(seconds: _secondsRemaining),
      value: _secondsRemaining / timeoutSeconds,
    )..animateTo(0, curve: Curves.linear);

    // Haptic alert — driver feels the incoming request
    HapticFeedback.heavyImpact();

    _fetchPickupDistance();
    _startCountdown();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _progressController.dispose();
    super.dispose();
  }

  void _startCountdown() {
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (mounted) {
        setState(() {
          _secondsRemaining--;

          // Haptic warning at 5 seconds
          if (_secondsRemaining == 5) {
            HapticFeedback.mediumImpact();
          }

          if (_secondsRemaining <= 0) {
            _timer?.cancel();
            _autoDecline();
          }
        });
      }
    });
  }

  Future<void> _autoDecline() async {
    if (!mounted) return;

    _isDismissing = true;
    LocalNotificationService.instance.cancelRideRequestNotification();

    // Notify backend of timeout so the request passes to the next driver.
    // Ignore errors — the server-side HandleRequestTimeout job is the safety-net.
    try {
      final service = ref.read(rideRequestServiceProvider);
      await service.declineRequest(widget.request.id);
    } catch (_) {}

    _clearIncomingRequest();
    if (mounted) {
      final l10n = AppLocalizations.of(context);
      Navigator.of(context).pop();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(l10n.requestTimedOut),
          backgroundColor: Colors.orange,
        ),
      );
    }
  }

  Future<void> _acceptRide() async {
    if (_isProcessing) return;

    setState(() {
      _isProcessing = true;
      _errorMessage = null;
    });

    _timer?.cancel();
    LocalNotificationService.instance.cancelRideRequestNotification();

    try {
      final apiService = ref.read(apiServiceProvider);

      if (kDebugMode) print('RideRequestScreen: Accepting ride request ${widget.request.id}');

      final response = await apiService.post(
        '/rides/${widget.request.id}/accept',
      );

      if (kDebugMode) print('RideRequestScreen: Accept response - ${response.data}');

      if (response.data['success'] == true && response.data['data'] != null) {
        final rideId = response.data['data']['id'] as int;

        // Update driver status to indicate active ride
        ref.read(driverStatusProvider.notifier).setActiveRide(rideId);

        // Set flag before clearing so the ref.listen below does not also pop
        _isDismissing = true;
        _clearIncomingRequest();

        if (mounted) {
          final l10n = AppLocalizations.of(context);
          // Navigate to ActiveRideScreen
          Navigator.of(context).pushReplacement(
            MaterialPageRoute(
              builder: (context) => ActiveRideScreen(rideId: rideId),
            ),
          );

          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(l10n.rideAccepted),
              backgroundColor: Colors.green,
            ),
          );
        }
      } else {
        throw Exception('Invalid response from server');
      }
    } catch (e) {
      // Set _isDismissing synchronously before any async work so ref.listen
      // cannot fire a competing pop while we handle the error.
      _isDismissing = true;
      _timer?.cancel();

      if (mounted) {
        final l10n = AppLocalizations.of(context);
        // Show snackbar first — it persists on the parent screen after pop.
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(_parseError(e, l10n)),
            backgroundColor: Colors.red,
            duration: const Duration(seconds: 4),
          ),
        );
        // Pop immediately — no delayed pop, which could fire against the wrong
        // route if the WS listener already removed this screen from the stack.
        Navigator.of(context).pop();
      }
    }
  }

  String _parseError(Object error, AppLocalizations l10n) {
    if (error is ApiException) {
      if (error.statusCode == 410) {
        return l10n.errorRideCancelledByRider;
      } else if (error.statusCode == 409) {
        return l10n.errorRideAlreadyAccepted;
      } else if (error.statusCode == 402) {
        return l10n.errorInsufficientCredits;
      } else if (error.statusCode == 400) {
        return l10n.errorAlreadyActiveRide;
      } else if (error.statusCode == 404) {
        return l10n.errorRideNoLongerAvailable;
      }
      return error.message;
    }
    return l10n.errorFailedToAccept;
  }

  Future<void> _declineRide() async {
    if (_isProcessing) return;
    _timer?.cancel();
    LocalNotificationService.instance.cancelRideRequestNotification();
    _isDismissing = true;

    setState(() => _isProcessing = true);

    // Notify backend — triggers next driver dispatch.
    // Errors are non-fatal: the 18s server-side timeout is the safety-net.
    try {
      final service = ref.read(rideRequestServiceProvider);
      await service.declineRequest(widget.request.id);
    } catch (e) {
      if (kDebugMode) print('RideRequestScreen: decline error (ignored) - $e');
    }

    _clearIncomingRequest();

    if (mounted) {
      final l10n = AppLocalizations.of(context);
      Navigator.of(context).pop();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(l10n.rideDeclined),
          backgroundColor: Colors.grey,
        ),
      );
    }
  }

  void _clearIncomingRequest() {
    ref.read(driverIncomingRequestProvider.notifier).clear();
  }

  Future<void> _fetchPickupDistance() async {
    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 5),
        ),
      );
      final origin = LatLng(position.latitude, position.longitude);
      final destination = LatLng(
        widget.request.pickupLocation.coordinates.latitude,
        widget.request.pickupLocation.coordinates.longitude,
      );

      final routeInfo = await MapboxDirectionsService().getRouteInfo(
        origin: origin,
        destination: destination,
      );

      if (mounted) {
        setState(() {
          _distanceKm = routeInfo.distanceKm;
          _etaMinutes = routeInfo.durationMinutes.ceil().clamp(1, 60);
        });
      }
    } catch (e) {
      if (kDebugMode) print('RideRequestScreen: failed to fetch pickup distance: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final l10n = AppLocalizations.of(context);
    // Dismiss if the rider/admin cancelled or the backend re-dispatched to another driver.
    // _isDismissing guards against a double-pop when _acceptRide/_declineRide already
    // cleared the provider and initiated their own navigation.
    ref.listen<RideRequest?>(driverIncomingRequestProvider, (previous, next) {
      if (previous != null && next == null && mounted && !_isDismissing) {
        _isDismissing = true;
        _timer?.cancel();
        final cancelledBy = ref.read(driverIncomingRequestProvider.notifier).consumeCancelledBy();
        final message = cancelledBy == 'system' ? l10n.requestTimedOut : l10n.rideCancelledByRider;
        Navigator.of(context).pop();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(message),
            backgroundColor: Colors.orange,
          ),
        );
      }
    });

    return PopScope(
      canPop: !_isProcessing,
      onPopInvokedWithResult: (bool didPop, dynamic result) {
        if (didPop) {
          if (kDebugMode) print('RideRequestScreen: User popped screen');
        }
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(l10n.newRideRequestTitle),
          backgroundColor: config.primaryColor,
          foregroundColor: Colors.white,
          automaticallyImplyLeading: !_isProcessing,
        ),
        body: Column(
          children: [
            // Countdown Progress Bar (smooth)
            AnimatedBuilder(
              animation: _progressController,
              builder: (_, __) => LinearProgressIndicator(
                value: _progressController.value,
                backgroundColor: Colors.grey[200],
                valueColor: AlwaysStoppedAnimation<Color>(
                  _secondsRemaining <= 5 ? Colors.red : config.primaryColor,
                ),
                minHeight: 8,
              ),
            ),

            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(16.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Countdown Timer
                    Center(
                      child: Column(
                        children: [
                          Text(
                            l10n.acceptWithinLabel,
                            style: TextStyle(
                              fontSize: 14,
                              color: Colors.grey[600],
                            ),
                          ),
                          const SizedBox(height: 4),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 24,
                              vertical: 12,
                            ),
                            decoration: BoxDecoration(
                              color: _secondsRemaining <= 5
                                  ? Colors.red[50]
                                  : Colors.blue[50],
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(
                                color: _secondsRemaining <= 5
                                    ? Colors.red
                                    : Colors.blue,
                                width: 2,
                              ),
                            ),
                            child: Text(
                              '0:${_secondsRemaining.toString().padLeft(2, '0')}',
                              style: TextStyle(
                                fontSize: 36,
                                fontWeight: FontWeight.bold,
                                color: _secondsRemaining <= 5
                                    ? Colors.red
                                    : Colors.blue,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 24),

                    // Ride Details Card
                    Card(
                      elevation: 2,
                      child: Padding(
                        padding: const EdgeInsets.all(16.0),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              l10n.rideDetailsTitle,
                              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                                    fontWeight: FontWeight.bold,
                                  ),
                            ),
                            const Divider(height: 24),

                            // Pickup Location
                            _buildLocationRow(
                              Icons.radio_button_checked,
                              Colors.green,
                              l10n.pickupLabel,
                              widget.request.pickupLocation.name,
                              widget.request.pickupLocation.description,
                            ),

                            const SizedBox(height: 16),

                            // Destination Location
                            _buildLocationRow(
                              Icons.location_on,
                              Colors.red,
                              l10n.destinationLabel,
                              widget.request.destinationLocation.name,
                              widget.request.destinationLocation.description,
                            ),

                            const Divider(height: 24),

                            // Fare, Distance, ETA
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceAround,
                              children: [
                                _buildInfoItem(
                                  Icons.attach_money,
                                  l10n.currencyFormat(_formatCurrency(widget.request.estimatedFare)),
                                  l10n.fareLabel,
                                ),
                                _buildInfoItem(
                                  Icons.person,
                                  '${widget.request.passengerCount}',
                                  widget.request.passengerCount == 1
                                      ? l10n.passengerSingular
                                      : l10n.passengerPlural,
                                ),
                                if (_distanceKm != null && _etaMinutes != null)
                                  _buildInfoItem(
                                    Icons.near_me,
                                    l10n.pickupDistanceKm(_distanceKm!.toStringAsFixed(1)),
                                    l10n.etaToPickup(_etaMinutes!),
                                  ),
                              ],
                            ),

                            // Special Requests
                            if (widget.request.specialRequests != null &&
                                widget.request.specialRequests!.isNotEmpty) ...[
                              const Divider(height: 24),
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Icon(Icons.notes, size: 20, color: Colors.orange),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          l10n.specialRequestsTitle,
                                          style: const TextStyle(
                                            fontSize: 12,
                                            color: Colors.grey,
                                          ),
                                        ),
                                        const SizedBox(height: 4),
                                        Text(
                                          widget.request.specialRequests!,
                                          style: const TextStyle(fontSize: 14),
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ],
                        ),
                      ),
                    ),

                    const SizedBox(height: 16),

                    // Error Message
                    if (_errorMessage != null)
                      Card(
                        color: Colors.red[50],
                        child: Padding(
                          padding: const EdgeInsets.all(12.0),
                          child: Row(
                            children: [
                              const Icon(Icons.error, color: Colors.red),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Text(
                                  _errorMessage!,
                                  style: const TextStyle(color: Colors.red),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),

                  ],
                ),
              ),
            ),

            // Fixed bottom buttons
            SafeArea(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    OutlinedButton.icon(
                      onPressed: _isProcessing ? null : _declineRide,
                      icon: const Icon(Icons.cancel),
                      label: Text(l10n.declineButton),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: Colors.grey[700],
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        side: BorderSide(color: Colors.grey[400]!),
                        textStyle: const TextStyle(
                          fontSize: 16,
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),
                    ActionSlider.standard(
                      sliderBehavior: SliderBehavior.stretch,
                      backgroundColor: Colors.green.shade50,
                      toggleColor: Colors.green,
                      icon: const Icon(Icons.arrow_forward_ios, color: Colors.white),
                      loadingIcon: const SizedBox(
                        width: 24,
                        height: 24,
                        child: CircularProgressIndicator(
                          strokeWidth: 2.5,
                          color: Colors.white,
                        ),
                      ),
                      successIcon: const Icon(Icons.check_rounded, color: Colors.white),
                      child: Text(
                        l10n.acceptRideButton,
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                          color: Colors.green,
                        ),
                      ),
                      action: (controller) async {
                        controller.loading();
                        await _acceptRide();
                        // _acceptRide pops the screen on both success and error;
                        // reset only if still mounted (e.g. unexpected path).
                        if (mounted) controller.reset();
                      },
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLocationRow(
    IconData icon,
    Color color,
    String label,
    String name,
    String? description,
  ) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: color, size: 24),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: TextStyle(
                  fontSize: 12,
                  color: Colors.grey[600],
                ),
              ),
              const SizedBox(height: 4),
              Text(
                name,
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                ),
              ),
              if (description != null && description.isNotEmpty) ...[
                const SizedBox(height: 2),
                Text(
                  description,
                  style: TextStyle(
                    fontSize: 12,
                    color: Colors.grey[600],
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildInfoItem(IconData icon, String value, String label) {
    return Column(
      children: [
        Icon(icon, size: 28, color: AppConfig.instance.primaryColor),
        const SizedBox(height: 4),
        Text(
          value,
          style: const TextStyle(
            fontSize: 20,
            fontWeight: FontWeight.bold,
          ),
        ),
        Text(
          label,
          style: TextStyle(
            fontSize: 12,
            color: Colors.grey[600],
          ),
        ),
      ],
    );
  }

  String _formatCurrency(double amount) {
    if (amount >= 1000000) {
      return '${(amount / 1000000).toStringAsFixed(1)}M';
    } else if (amount >= 1000) {
      return '${(amount / 1000).toStringAsFixed(1)}K';
    }
    return amount.toStringAsFixed(0);
  }
}
