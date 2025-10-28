import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/config/app_config.dart';
import '../../core/models/place_search_result.dart';
import '../../core/providers/ride_request_provider.dart';
import '../../core/widgets/route_map_widget.dart';
import 'waiting_screen.dart';

/// Ride details screen with map background showing route
///
/// Displays fare estimate, route visualization, and allows user to
/// configure passenger count and special requests before confirming.
class RideDetailsScreen extends ConsumerStatefulWidget {
  final PlaceSearchResult pickupLocation;
  final PlaceSearchResult destinationLocation;

  const RideDetailsScreen({
    super.key,
    required this.pickupLocation,
    required this.destinationLocation,
  });

  @override
  ConsumerState<RideDetailsScreen> createState() => _RideDetailsScreenState();
}

class _RideDetailsScreenState extends ConsumerState<RideDetailsScreen> {
  int _passengerCount = 1;
  final _specialRequestsController = TextEditingController();
  bool _isSubmitting = false;

  @override
  void dispose() {
    _specialRequestsController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final requestState = ref.watch(rideRequestProvider);
    final fareEstimate = requestState.fareEstimate;

    return Scaffold(
      body: Stack(
        children: [
          // Map background showing route (full screen)
          if (fareEstimate != null)
            RouteMapWidget(
              pickupLocation: widget.pickupLocation,
              dropoffLocation: widget.destinationLocation,
              routeGeometry: fareEstimate.routeGeometry,
              height: MediaQuery.of(context).size.height,
            ),

          // Semi-transparent overlay
          Container(
            color: Colors.black.withOpacity(0.3),
          ),

          // Content overlay at bottom
          Column(
            children: [
              // AppBar
              AppBar(
                title: const Text('Ride Details'),
                backgroundColor: Colors.transparent,
                elevation: 0,
                foregroundColor: Colors.white,
              ),

              const Spacer(),

              // Details card at bottom
              Container(
                decoration: const BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.only(
                    topLeft: Radius.circular(24),
                    topRight: Radius.circular(24),
                  ),
                ),
                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(20.0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Route summary
                      Row(
                        children: [
                          Icon(Icons.trip_origin, color: config.primaryColor),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              widget.pickupLocation.name,
                              style: const TextStyle(fontWeight: FontWeight.w600),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Padding(
                        padding: const EdgeInsets.only(left: 11),
                        child: Icon(Icons.more_vert, size: 20, color: Colors.grey[400]),
                      ),
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          Icon(Icons.location_on, color: config.primaryColor),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              widget.destinationLocation.name,
                              style: const TextStyle(fontWeight: FontWeight.w600),
                            ),
                          ),
                        ],
                      ),

                      const SizedBox(height: 20),

                      // Fare estimate
                      if (fareEstimate != null) ...[
                        Container(
                          padding: const EdgeInsets.all(16.0),
                          decoration: BoxDecoration(
                            color: config.primaryColor.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Column(
                            children: [
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  const Text(
                                    'Estimated Fare:',
                                    style: TextStyle(fontSize: 14),
                                  ),
                                  Text(
                                    fareEstimate.formattedFare,
                                    style: TextStyle(
                                      fontSize: 28,
                                      fontWeight: FontWeight.bold,
                                      color: config.primaryColor,
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 8),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Row(
                                    children: [
                                      Icon(Icons.straighten, size: 16, color: Colors.grey[600]),
                                      const SizedBox(width: 4),
                                      Text(
                                        fareEstimate.formattedDistance,
                                        style: const TextStyle(fontSize: 13),
                                      ),
                                    ],
                                  ),
                                  Row(
                                    children: [
                                      Icon(Icons.schedule, size: 16, color: Colors.grey[600]),
                                      const SizedBox(width: 4),
                                      Text(
                                        fareEstimate.formattedDuration,
                                        style: const TextStyle(fontSize: 13),
                                      ),
                                    ],
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),

                        const SizedBox(height: 20),
                      ],

                      // Passenger count
                      const Text(
                        'Passenger Count',
                        style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600),
                      ),
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          IconButton(
                            onPressed: _passengerCount > 1
                                ? () => setState(() => _passengerCount--)
                                : null,
                            icon: const Icon(Icons.remove_circle_outline),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 8),
                            decoration: BoxDecoration(
                              border: Border.all(color: Colors.grey[300]!),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Text(
                              '$_passengerCount',
                              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                            ),
                          ),
                          IconButton(
                            onPressed: _passengerCount < 4
                                ? () => setState(() => _passengerCount++)
                                : null,
                            icon: const Icon(Icons.add_circle_outline),
                          ),
                          const SizedBox(width: 8),
                          Text(
                            'Max: 4 passengers',
                            style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                          ),
                        ],
                      ),

                      const SizedBox(height: 20),

                      // Special requests
                      const Text(
                        'Special Requests (Optional)',
                        style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600),
                      ),
                      const SizedBox(height: 8),
                      TextField(
                        controller: _specialRequestsController,
                        maxLines: 2,
                        decoration: InputDecoration(
                          hintText: 'e.g., "Please wait at gate 2"',
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(8),
                          ),
                          contentPadding: const EdgeInsets.all(12),
                        ),
                      ),

                      const SizedBox(height: 24),

                      // Confirm button
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton(
                          onPressed: _isSubmitting || requestState.isLoading
                              ? null
                              : () async {
                                  if (_isSubmitting) return;

                                  // Validate IDs before submission
                                  if (widget.pickupLocation.id == null ||
                                      widget.destinationLocation.id == null) {
                                    ScaffoldMessenger.of(context).showSnackBar(
                                      const SnackBar(
                                        content: Text(
                                          'Invalid location selection. Please go back and select valid beacons.',
                                        ),
                                        backgroundColor: Colors.red,
                                      ),
                                    );
                                    return;
                                  }

                                  setState(() => _isSubmitting = true);

                                  try {
                                    // Safe extraction of IDs (already validated above)
                                    final pickupId = widget.pickupLocation.id;
                                    final destinationId = widget.destinationLocation.id;

                                    // This should never happen due to validation above, but be extra safe
                                    if (pickupId == null || destinationId == null) {
                                      throw Exception('Internal error: Location IDs are null');
                                    }

                                    await ref
                                        .read(rideRequestProvider.notifier)
                                        .createRequest(
                                          pickupBeaconId: pickupId,
                                          destinationBeaconId: destinationId,
                                          passengerCount: _passengerCount,
                                          specialRequests:
                                              _specialRequestsController.text.trim(),
                                        );

                                    final updatedState = ref.read(rideRequestProvider);

                                    if (!mounted) return;

                                    if (updatedState.request != null) {
                                      Navigator.of(context).pushReplacement(
                                        MaterialPageRoute(
                                          builder: (context) => const WaitingScreen(),
                                        ),
                                      );
                                    } else if (updatedState.error != null) {
                                      ScaffoldMessenger.of(context).showSnackBar(
                                        SnackBar(
                                          content: Text(updatedState.error!),
                                          backgroundColor: Colors.red,
                                        ),
                                      );
                                      setState(() => _isSubmitting = false);
                                    }
                                  } catch (e) {
                                    if (!mounted) return;

                                    ScaffoldMessenger.of(context).showSnackBar(
                                      SnackBar(
                                        content: Text('Failed: ${e.toString()}'),
                                        backgroundColor: Colors.red,
                                      ),
                                    );
                                    setState(() => _isSubmitting = false);
                                  }
                                },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: config.primaryColor,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 16),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                          ),
                          child: _isSubmitting || requestState.isLoading
                              ? const SizedBox(
                                  height: 20,
                                  width: 20,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                    valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                                  ),
                                )
                              : const Text(
                                  'Confirm Request',
                                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
                                ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
