import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/config/app_config.dart';
import '../../core/models/ride.dart';
import '../../core/providers/ride_history_provider.dart';
import 'rider_home_screen.dart';

class CompletedScreen extends ConsumerStatefulWidget {
  final Ride ride;

  const CompletedScreen({
    super.key,
    required this.ride,
  });

  @override
  ConsumerState<CompletedScreen> createState() => _CompletedScreenState();
}

class _CompletedScreenState extends ConsumerState<CompletedScreen> {
  int _rating = 0;
  final Set<String> _selectedTags = {};
  final _feedbackController = TextEditingController();

  // ✅ Display-friendly tag labels
  final List<String> _availableTags = [
    'Clean Vehicle',
    'Safe Driving',
    'Friendly Driver',
    'On Time',
    'Professional',
    'Smooth Ride',
    'Helpful',
  ];

  // ✅ Map display labels to backend snake_case keys
  final Map<String, String> _tagMapping = {
    'Clean Vehicle': 'clean_vehicle',
    'Safe Driving': 'safe_driving',
    'Friendly Driver': 'friendly',
    'On Time': 'on_time',
    'Professional': 'professional',
    'Smooth Ride': 'smooth_ride',
    'Helpful': 'helpful',
  };

  @override
  void dispose() {
    _feedbackController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Rate Your Ride'),
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

            const Text(
              'Ride Completed!',
              style: TextStyle(
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
                        const Text('Fare:'),
                        Text(
                          'Rp ${widget.ride.fare.toStringAsFixed(0)}',
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
                        const Text('From:'),
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
                        const Text('To:'),
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
            const Text(
              'How was your ride?',
              style: TextStyle(
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
              const Text(
                'What did you like?',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                ),
              ),

              const SizedBox(height: 12),

              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: _availableTags.map((tag) {
                  final isSelected = _selectedTags.contains(tag);
                  return FilterChip(
                    label: Text(tag),
                    selected: isSelected,
                    onSelected: (selected) {
                      setState(() {
                        if (selected) {
                          _selectedTags.add(tag);
                        } else {
                          _selectedTags.remove(tag);
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
              const Text(
                'Additional Feedback (Optional)',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                ),
              ),

              const SizedBox(height: 12),

              TextField(
                controller: _feedbackController,
                maxLines: 3,
                decoration: InputDecoration(
                  hintText: 'Share your experience...',
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
                        // ✅ Convert display tags to backend format
                        final backendTags = _selectedTags
                            .map((tag) => _tagMapping[tag] ?? tag)
                            .toList();

                        await ref.read(rideHistoryProvider.notifier).rateRide(
                              rideId: widget.ride.id,
                              rating: _rating,
                              tags: backendTags,  // ✅ Send converted tags
                              feedback: _feedbackController.text.trim(),
                            );

                        if (mounted) {
                          Navigator.of(context).pushAndRemoveUntil(
                            MaterialPageRoute(
                              builder: (context) => const RiderHomeScreen(),
                            ),
                            (route) => false,
                          );
                        }
                      }
                    : null,
                style: ElevatedButton.styleFrom(
                  backgroundColor: config.primaryColor,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                ),
                child: const Text(
                  'Submit Rating',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
                ),
              ),
            ),

            const SizedBox(height: 16),

            // Skip button
            TextButton(
              onPressed: () {
                Navigator.of(context).pushAndRemoveUntil(
                  MaterialPageRoute(
                    builder: (context) => const RiderHomeScreen(),
                  ),
                  (route) => false,
                );
              },
              child: const Text('Skip for now'),
            ),
          ],
        ),
      ),
    );
  }
}
