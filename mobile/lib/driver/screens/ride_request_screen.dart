import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/config/app_config.dart';
import '../../core/models/ride_request.dart';
import '../../core/providers/api_provider.dart';
import '../../core/services/api/api_exception.dart';
import '../../core/providers/driver_incoming_request_provider.dart';
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

class _RideRequestScreenState extends ConsumerState<RideRequestScreen> {
  static const int timeoutSeconds = 30;
  int _secondsRemaining = timeoutSeconds;
  Timer? _timer;
  bool _isProcessing = false;
  bool _isDismissing = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _startCountdown();
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  void _startCountdown() {
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (mounted) {
        setState(() {
          _secondsRemaining--;

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

    // Notify backend of timeout so the request passes to the next driver.
    // Ignore errors — the server-side HandleRequestTimeout job is the safety-net.
    try {
      final service = ref.read(rideRequestServiceProvider);
      await service.declineRequest(widget.request.id);
    } catch (_) {}

    _clearIncomingRequest();
    if (mounted) {
      Navigator.of(context).pop();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Request timed out'),
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

    try {
      final apiService = ref.read(apiServiceProvider);

      print('RideRequestScreen: Accepting ride request ${widget.request.id}');

      final response = await apiService.post(
        '/rides/${widget.request.id}/accept',
      );

      print('RideRequestScreen: Accept response - ${response.data}');

      if (response.data['success'] == true && response.data['data'] != null) {
        final rideId = response.data['data']['id'] as int;

        // Update driver status to indicate active ride
        ref.read(driverStatusProvider.notifier).setActiveRide(rideId);

        // Set flag before clearing so the ref.listen below does not also pop
        _isDismissing = true;
        _clearIncomingRequest();

        if (mounted) {
          // Navigate to ActiveRideScreen
          Navigator.of(context).pushReplacement(
            MaterialPageRoute(
              builder: (context) => ActiveRideScreen(rideId: rideId),
            ),
          );

          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Ride accepted!'),
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
        // Show snackbar first — it persists on the parent screen after pop.
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(_parseError(e)),
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

  String _parseError(Object error) {
    if (error is ApiException) {
      if (error.statusCode == 410) {
        return 'This ride request was cancelled by the rider';
      } else if (error.statusCode == 409) {
        return 'This ride was already accepted by another driver';
      } else if (error.statusCode == 400) {
        return 'You already have an active ride';
      } else if (error.statusCode == 404) {
        return 'Ride request no longer available';
      }
      return error.message;
    }
    return 'Failed to accept ride. Please try again.';
  }

  Future<void> _declineRide() async {
    if (_isProcessing) return;
    _timer?.cancel();

    setState(() => _isProcessing = true);

    // Notify backend — triggers next driver dispatch.
    // Errors are non-fatal: the 35s server-side timeout is the safety-net.
    try {
      final service = ref.read(rideRequestServiceProvider);
      await service.declineRequest(widget.request.id);
    } catch (e) {
      print('RideRequestScreen: decline error (ignored) - $e');
    }

    _isDismissing = true;
    _clearIncomingRequest();

    if (mounted) {
      Navigator.of(context).pop();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Ride declined'),
          backgroundColor: Colors.grey,
        ),
      );
    }
  }

  void _clearIncomingRequest() {
    ref.read(driverIncomingRequestProvider.notifier).clear();
  }

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final progress = _secondsRemaining / timeoutSeconds;

    // Dismiss if the rider/admin cancelled or the backend re-dispatched to another driver.
    // _isDismissing guards against a double-pop when _acceptRide/_declineRide already
    // cleared the provider and initiated their own navigation.
    ref.listen<RideRequest?>(driverIncomingRequestProvider, (previous, next) {
      if (previous != null && next == null && mounted && !_isDismissing) {
        _isDismissing = true;
        _timer?.cancel();
        Navigator.of(context).pop();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Ride request was cancelled'),
            backgroundColor: Colors.orange,
          ),
        );
      }
    });

    // ✅ FIX: Replace deprecated WillPopScope with PopScope (Flutter 3.12+)
    return PopScope(
      canPop: !_isProcessing,
      onPopInvokedWithResult: (bool didPop, dynamic result) {
        if (didPop) {
          print('RideRequestScreen: User popped screen');
        }
      },
      child: Scaffold(
        appBar: AppBar(
          title: const Text('New Ride Request'),
          backgroundColor: config.primaryColor,
          foregroundColor: Colors.white,
          automaticallyImplyLeading: !_isProcessing,
        ),
        body: Column(
          children: [
            // Countdown Progress Bar
            LinearProgressIndicator(
              value: progress,
              backgroundColor: Colors.grey[200],
              valueColor: AlwaysStoppedAnimation<Color>(
                _secondsRemaining <= 10 ? Colors.red : config.primaryColor,
              ),
              minHeight: 8,
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
                            'Accept within',
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
                              color: _secondsRemaining <= 10
                                  ? Colors.red[50]
                                  : Colors.blue[50],
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(
                                color: _secondsRemaining <= 10
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
                                color: _secondsRemaining <= 10
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
                              'Ride Details',
                              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                                    fontWeight: FontWeight.bold,
                                  ),
                            ),
                            const Divider(height: 24),

                            // Pickup Location
                            _buildLocationRow(
                              Icons.radio_button_checked,
                              Colors.green,
                              'Pickup',
                              widget.request.pickupLocation.name,
                              widget.request.pickupLocation.description,
                            ),

                            const SizedBox(height: 16),

                            // Destination Location
                            _buildLocationRow(
                              Icons.location_on,
                              Colors.red,
                              'Destination',
                              widget.request.destinationLocation.name,
                              widget.request.destinationLocation.description,
                            ),

                            const Divider(height: 24),

                            // Fare and Distance
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceAround,
                              children: [
                                _buildInfoItem(
                                  Icons.attach_money,
                                  'Rp ${_formatCurrency(widget.request.estimatedFare)}',
                                  'Fare',
                                ),
                                _buildInfoItem(
                                  Icons.person,
                                  '${widget.request.passengerCount}',
                                  widget.request.passengerCount == 1 ? 'Passenger' : 'Passengers',
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
                                        const Text(
                                          'Special Requests',
                                          style: TextStyle(
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

                    const SizedBox(height: 24),

                    // Action Buttons
                    if (!_isProcessing) ...[
                      ElevatedButton.icon(
                        onPressed: _acceptRide,
                        icon: const Icon(Icons.check_circle),
                        label: const Text('Accept Ride'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.green,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          textStyle: const TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),
                      OutlinedButton.icon(
                        onPressed: _declineRide,
                        icon: const Icon(Icons.cancel),
                        label: const Text('Decline'),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: Colors.grey[700],
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          side: BorderSide(color: Colors.grey[400]!),
                          textStyle: const TextStyle(
                            fontSize: 16,
                          ),
                        ),
                      ),
                    ] else
                      const Center(
                        child: Column(
                          children: [
                            CircularProgressIndicator(),
                            SizedBox(height: 16),
                            Text(
                              'Accepting ride...',
                              style: TextStyle(fontSize: 16),
                            ),
                          ],
                        ),
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
