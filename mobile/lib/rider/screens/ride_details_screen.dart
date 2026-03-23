import 'package:action_slider/action_slider.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile/l10n/app_localizations.dart';
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
  final _specialRequestsController = TextEditingController();

  @override
  void dispose() {
    _specialRequestsController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final l10n = AppLocalizations.of(context);
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
                title: Text(l10n.rideDetailsTitle),
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
                            color: config.primaryColor.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Column(
                            children: [
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Text(
                                    l10n.estimatedFareLabel,
                                    style: const TextStyle(fontSize: 14),
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

                      // Motorcycle passenger note
                      Row(
                        children: [
                          Icon(Icons.motorcycle, size: 18, color: Colors.grey[600]),
                          const SizedBox(width: 8),
                          Text(
                            l10n.motorcycleOnlyNote,
                            style: TextStyle(fontSize: 13, color: Colors.grey[600]),
                          ),
                        ],
                      ),

                      const SizedBox(height: 20),

                      // Special requests
                      Text(
                        l10n.specialRequestsHint,
                        style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600),
                      ),
                      const SizedBox(height: 8),
                      TextField(
                        controller: _specialRequestsController,
                        maxLines: 2,
                        decoration: InputDecoration(
                          hintText: l10n.specialRequestsPlaceholder,
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(8),
                          ),
                          contentPadding: const EdgeInsets.all(12),
                        ),
                      ),

                      const SizedBox(height: 24),

                      // Confirm button
                      ActionSlider.standard(
                        sliderBehavior: SliderBehavior.stretch,
                        backgroundColor: config.primaryColor.withOpacity(0.1),
                        toggleColor: config.primaryColor,
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
                          l10n.confirmRequest,
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w600,
                            color: config.primaryColor,
                          ),
                        ),
                        action: (controller) async {
                          if (requestState.isLoading) {
                            controller.reset();
                            return;
                          }

                          // Validate IDs before submission
                          if (widget.pickupLocation.id == null ||
                              widget.destinationLocation.id == null) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text(l10n.locationResolveFailed),
                                backgroundColor: Colors.red,
                              ),
                            );
                            controller.reset();
                            return;
                          }

                          controller.loading();

                          try {
                            final pickupId = widget.pickupLocation.id!;
                            final destinationId = widget.destinationLocation.id!;

                            await ref
                                .read(rideRequestProvider.notifier)
                                .createRequest(
                                  pickupBeaconId: pickupId,
                                  destinationBeaconId: destinationId,
                                  passengerCount: 1,
                                  specialRequests:
                                      _specialRequestsController.text.trim(),
                                );

                            final updatedState = ref.read(rideRequestProvider);

                            if (!mounted) return;

                            if (updatedState.request != null) {
                              controller.success();
                              Navigator.of(context).pushReplacement(
                                MaterialPageRoute(
                                  builder: (context) => const WaitingScreen(),
                                ),
                              );
                            } else if (updatedState.error != null) {
                              controller.failure();
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(
                                  content: Text(updatedState.error!),
                                  backgroundColor: Colors.red,
                                ),
                              );
                              await Future.delayed(const Duration(seconds: 2));
                              if (mounted) controller.reset();
                            }
                          } catch (e) {
                            if (!mounted) return;
                            controller.failure();
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text(l10n.failedGeneric(e.toString())),
                                backgroundColor: Colors.red,
                              ),
                            );
                            await Future.delayed(const Duration(seconds: 2));
                            if (mounted) controller.reset();
                          }
                        },
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
