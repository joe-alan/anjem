import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile/l10n/app_localizations.dart';
import '../../core/config/app_config.dart';
import '../../core/models/ride.dart';
import '../../core/providers/ride_history_provider.dart';
import 'rider_home_screen.dart';

class CompletedScreen extends ConsumerStatefulWidget {
  final Ride ride;
  /// When true, pops back instead of replacing the entire stack (used from ride history)
  final bool fromHistory;

  const CompletedScreen({
    super.key,
    required this.ride,
    this.fromHistory = false,
  });

  @override
  ConsumerState<CompletedScreen> createState() => _CompletedScreenState();
}

class _CompletedScreenState extends ConsumerState<CompletedScreen> {
  int _rating = 0;
  final Set<String> _selectedTags = {};
  final _feedbackController = TextEditingController();

  // Backend keys for tags — display names resolved via l10n at build time
  static const List<String> _tagKeys = [
    'clean_vehicle',
    'safe_driving',
    'friendly',
    'on_time',
    'professional',
    'smooth_ride',
    'helpful',
  ];

  @override
  void dispose() {
    _feedbackController.dispose();
    super.dispose();
  }

  String _tagLabel(AppLocalizations l10n, String key) {
    switch (key) {
      case 'clean_vehicle':
        return l10n.tagCleanVehicle;
      case 'safe_driving':
        return l10n.tagSafeDriving;
      case 'friendly':
        return l10n.tagFriendlyDriver;
      case 'on_time':
        return l10n.tagOnTime;
      case 'professional':
        return l10n.tagProfessional;
      case 'smooth_ride':
        return l10n.tagSmoothRide;
      case 'helpful':
        return l10n.tagHelpful;
      default:
        return key;
    }
  }

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final l10n = AppLocalizations.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.rateYourRideTitle),
        backgroundColor: config.primaryColor,
        foregroundColor: Colors.white,
        automaticallyImplyLeading: false,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            // Success icon
            Container(
              width: 100,
              height: 100,
              decoration: BoxDecoration(
                color: Colors.green.withOpacity(0.1),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.check_circle,
                size: 60,
                color: Colors.green,
              ),
            ),

            const SizedBox(height: 24),

            Text(
              l10n.rideCompletedHeading,
              style: const TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.bold,
              ),
            ),

            const SizedBox(height: 32),

            // Ride summary
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Column(
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(l10n.fareLabel),
                        Text(
                          l10n.currencyFormat(widget.ride.fare.toStringAsFixed(0)),
                          style: const TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ],
                    ),
                    const Divider(height: 24),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(l10n.fromLabel),
                        Expanded(
                          child: Text(
                            widget.ride.pickupLocation.name,
                            textAlign: TextAlign.right,
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(l10n.toLabel),
                        Expanded(
                          child: Text(
                            widget.ride.destinationLocation.name,
                            textAlign: TextAlign.right,
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 32),

            // Rating
            Text(
              l10n.howWasYourRide,
              style: const TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w600,
              ),
            ),

            const SizedBox(height: 16),

            // Star rating
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: List.generate(5, (index) {
                return IconButton(
                  icon: Icon(
                    index < _rating ? Icons.star : Icons.star_border,
                    size: 40,
                  ),
                  color: Colors.amber,
                  onPressed: () {
                    setState(() {
                      _rating = index + 1;
                    });
                  },
                );
              }),
            ),

            if (_rating > 0) ...[
              const SizedBox(height: 24),

              // Tags
              Text(
                l10n.whatDidYouLike,
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                ),
              ),

              const SizedBox(height: 12),

              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: _tagKeys.map((key) {
                  final isSelected = _selectedTags.contains(key);
                  return FilterChip(
                    label: Text(_tagLabel(l10n, key)),
                    selected: isSelected,
                    onSelected: (selected) {
                      setState(() {
                        if (selected) {
                          _selectedTags.add(key);
                        } else {
                          _selectedTags.remove(key);
                        }
                      });
                    },
                    selectedColor: config.primaryColor.withOpacity(0.3),
                    checkmarkColor: config.primaryColor,
                  );
                }).toList(),
              ),

              const SizedBox(height: 24),

              // Feedback
              Text(
                l10n.additionalFeedbackOptional,
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                ),
              ),

              const SizedBox(height: 12),

              TextField(
                controller: _feedbackController,
                maxLines: 3,
                decoration: InputDecoration(
                  hintText: l10n.shareFeedbackHint,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
              ),
            ],

            const SizedBox(height: 32),

            // Submit button
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: _rating > 0
                    ? () async {
                        await ref.read(rideHistoryProvider.notifier).rateRide(
                              rideId: widget.ride.id,
                              rating: _rating,
                              tags: _selectedTags.toList(),
                              feedback: _feedbackController.text.trim(),
                            );

                        if (mounted) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(content: Text(l10n.thankYouFeedback)),
                          );
                          if (widget.fromHistory) {
                            Navigator.of(context).pop();
                          } else {
                            Navigator.of(context).pushAndRemoveUntil(
                              MaterialPageRoute(
                                builder: (context) => const RiderHomeScreen(),
                              ),
                              (route) => false,
                            );
                          }
                        }
                      }
                    : null,
                style: ElevatedButton.styleFrom(
                  backgroundColor: config.primaryColor,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                ),
                child: Text(
                  l10n.submitRatingButton,
                  style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
                ),
              ),
            ),

            const SizedBox(height: 16),

            // Skip button
            TextButton(
              onPressed: () {
                if (widget.fromHistory) {
                  Navigator.of(context).pop();
                } else {
                  Navigator.of(context).pushAndRemoveUntil(
                    MaterialPageRoute(
                      builder: (context) => const RiderHomeScreen(),
                    ),
                    (route) => false,
                  );
                }
              },
              child: Text(l10n.skipForNow),
            ),
          ],
        ),
      ),
    );
  }
}
