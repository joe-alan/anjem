// LEGACY: Replaced by LocationSearchScreen (Gojek-style map-first flow).
// Kept for reference — do not use in new code.
import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile/l10n/app_localizations.dart';
import '../../core/config/app_config.dart';
import '../../core/models/lat_lng.dart';
import '../../core/models/place_search_result.dart';
import '../../core/providers/place_search_provider.dart';
import '../../core/providers/user_location_provider.dart';
import '../../core/providers/ride_request_provider.dart';
import 'ride_details_screen.dart';

class LocationSelectionScreen extends ConsumerStatefulWidget {
  const LocationSelectionScreen({super.key});

  @override
  ConsumerState<LocationSelectionScreen> createState() =>
      _LocationSelectionScreenState();
}

class _LocationSelectionScreenState
    extends ConsumerState<LocationSelectionScreen> {
  PlaceSearchResult? _pickupLocation;
  PlaceSearchResult? _destinationLocation;
  final _searchController = TextEditingController();
  Timer? _debounceTimer;
  bool _isSearching = false;
  bool _isLoadingEstimate = false;

  @override
  void initState() {
    super.initState();
    _searchController.addListener(_onSearchChanged);
  }

  @override
  void dispose() {
    _searchController.dispose();
    _debounceTimer?.cancel();
    super.dispose();
  }

  void _onSearchChanged() {
    // Debounce search to avoid too many API calls
    _debounceTimer?.cancel();

    if (_searchController.text.trim().isEmpty) {
      ref.read(placeSearchProvider.notifier).clear();
      return;
    }

    if (mounted) {
      setState(() {
        _isSearching = true;
      });
    }

    _debounceTimer = Timer(const Duration(milliseconds: 500), () {
      if (mounted) _performSearch();
    });
  }

  Future<void> _performSearch() async {
    final query = _searchController.text.trim();
    if (query.length < 2) {
      if (mounted) setState(() => _isSearching = false);
      return;
    }

    final userLocation = ref.read(userLocationProvider).location;
    await ref.read(placeSearchProvider.notifier).search(
      query: query,
      userLocation: userLocation,
      radius: 15.0,
      limit: 20,
    );

    if (mounted) setState(() => _isSearching = false);
  }

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final l10n = AppLocalizations.of(context);
    final searchState = ref.watch(placeSearchProvider);
    final searchResults = searchState.results;

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.selectLocationsTitle),
        backgroundColor: config.primaryColor,
        foregroundColor: Colors.white,
      ),
      body: Column(
        children: [
          // Search bar
          Padding(
            padding: const EdgeInsets.all(16.0),
            child: TextField(
              controller: _searchController,
              decoration: InputDecoration(
                hintText: l10n.searchLocationsHint,
                prefixIcon: _isSearching || searchState.isLoading
                    ? const Padding(
                        padding: EdgeInsets.all(12.0),
                        child: SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                      )
                    : const Icon(Icons.search),
                suffixIcon: _searchController.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear),
                        onPressed: () {
                          _searchController.clear();
                          ref.read(placeSearchProvider.notifier).clear();
                        },
                      )
                    : null,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          ),

          // Selected locations summary
          if (_pickupLocation != null || _destinationLocation != null)
            Container(
              margin: const EdgeInsets.symmetric(horizontal: 16),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: config.primaryColor.withOpacity(0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (_pickupLocation != null)
                    Row(
                      children: [
                        Icon(Icons.trip_origin, color: config.primaryColor),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                '${l10n.pickupLabel}: ${_pickupLocation!.name}',
                                style: const TextStyle(fontWeight: FontWeight.w600),
                              ),
                              if (_pickupLocation!.address != null)
                                Text(
                                  _pickupLocation!.address!,
                                  style: TextStyle(
                                    fontSize: 12,
                                    color: Colors.grey[600],
                                  ),
                                ),
                            ],
                          ),
                        ),
                        IconButton(
                          icon: const Icon(Icons.close, size: 20),
                          onPressed: () {
                            setState(() {
                              _pickupLocation = null;
                            });
                          },
                        ),
                      ],
                    ),
                  if (_destinationLocation != null) ...[
                    if (_pickupLocation != null) const SizedBox(height: 8),
                    Row(
                      children: [
                        Icon(Icons.location_on, color: config.primaryColor),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                '${l10n.dropOffLabel}: ${_destinationLocation!.name}',
                                style: const TextStyle(fontWeight: FontWeight.w600),
                              ),
                              if (_destinationLocation!.address != null)
                                Text(
                                  _destinationLocation!.address!,
                                  style: TextStyle(
                                    fontSize: 12,
                                    color: Colors.grey[600],
                                  ),
                                ),
                            ],
                          ),
                        ),
                        IconButton(
                          icon: const Icon(Icons.close, size: 20),
                          onPressed: () {
                            setState(() {
                              _destinationLocation = null;
                            });
                          },
                        ),
                      ],
                    ),
                  ],
                ],
              ),
            ),

          const SizedBox(height: 8),

          // Error message
          if (searchState.hasError)
            Padding(
              padding: const EdgeInsets.all(16.0),
              child: Text(
                searchState.error!,
                style: TextStyle(color: Colors.red[700]),
                textAlign: TextAlign.center,
              ),
            ),

          // Search results list
          Expanded(
            child: searchResults.isEmpty
                ? Center(
                    child: Text(
                      searchState.isLoading || _isSearching
                          ? l10n.searchingText
                          : _searchController.text.isEmpty
                              ? l10n.startTypingHint
                              : l10n.noResultsFound,
                      style: TextStyle(color: Colors.grey[600]),
                    ),
                  )
                : ListView.builder(
                    itemCount: searchResults.length,
                    itemBuilder: (context, index) {
                      final place = searchResults[index];
                      final isPickup = _pickupLocation?.id == place.id &&
                          _pickupLocation?.name == place.name;
                      final isDestination =
                          _destinationLocation?.id == place.id &&
                              _destinationLocation?.name == place.name;

                      return ListTile(
                        leading: Icon(
                          isPickup
                              ? Icons.trip_origin
                              : isDestination
                                  ? Icons.location_on
                                  : place.isBeacon
                                      ? Icons.place
                                      : Icons.pin_drop,
                          color: isPickup || isDestination
                              ? config.primaryColor
                              : place.isBeacon
                                  ? Colors.blue
                                  : Colors.grey,
                        ),
                        title: Text(place.displayName),
                        subtitle: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            if (place.subtitle != null) Text(place.subtitle!),
                            Row(
                              children: [
                                if (place.isBeacon)
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 6,
                                      vertical: 2,
                                    ),
                                    decoration: BoxDecoration(
                                      color: Colors.blue[100],
                                      borderRadius: BorderRadius.circular(4),
                                    ),
                                    child: Text(
                                      l10n.beaconLabel,
                                      style: TextStyle(
                                        fontSize: 10,
                                        color: Colors.blue[900],
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                  ),
                                if (place.category != null) ...[
                                  if (place.isBeacon) const SizedBox(width: 4),
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 6,
                                      vertical: 2,
                                    ),
                                    decoration: BoxDecoration(
                                      color: Colors.grey[200],
                                      borderRadius: BorderRadius.circular(4),
                                    ),
                                    child: Text(
                                      place.category!,
                                      style: TextStyle(
                                        fontSize: 10,
                                        color: Colors.grey[700],
                                      ),
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          ],
                        ),
                        trailing: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            if (!isPickup && _pickupLocation == null)
                              TextButton(
                                onPressed: () {
                                  setState(() {
                                    _pickupLocation = place;
                                  });
                                },
                                child: Text(l10n.pickupLabel),
                              ),
                            if (!isDestination &&
                                _destinationLocation == null &&
                                _pickupLocation != place)
                              TextButton(
                                onPressed: () {
                                  setState(() {
                                    _destinationLocation = place;
                                  });
                                },
                                child: Text(l10n.dropOffLabel),
                              ),
                          ],
                        ),
                      );
                    },
                  ),
          ),

          // Continue button
          if (_pickupLocation != null && _destinationLocation != null)
            Padding(
      padding: const EdgeInsets.all(16.0),
      child: SizedBox(
        width: double.infinity,
        child: ElevatedButton(
          onPressed: _isLoadingEstimate
              ? null
              : () async {
                  if (_pickupLocation!.id == null ||
                      _destinationLocation!.id == null) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text(l10n.locationResolveFailed),
                        backgroundColor: Colors.orange,
                      ),
                    );
                    return;
                  }

                  setState(() {
                    _isLoadingEstimate = true;
                  });

                  try {
                    final pickupId = _pickupLocation!.id;
                    final destinationId = _destinationLocation!.id;

                    if (pickupId == null || destinationId == null) {
                      throw Exception('Location IDs are null. Please select valid beacons.');
                    }

                    await ref
                        .read(rideRequestProvider.notifier)
                        .getEstimate(
                          pickupBeaconId: pickupId,
                          destinationBeaconId: destinationId,
                        );

                    if (!mounted) return;

                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (context) => RideDetailsScreen(
                          pickupLocation: _pickupLocation!,
                          destinationLocation: _destinationLocation!,
                        ),
                      ),
                    );
                  } catch (e) {
                    if (!mounted) return;
                    final l10nErr = AppLocalizations.of(context);
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text(l10nErr.estimateFailed(e.toString())),
                        backgroundColor: Colors.red,
                      ),
                    );
                  } finally {
                    if (mounted) {
                      setState(() {
                        _isLoadingEstimate = false;
                      });
                    }
                  }
                },
          style: ElevatedButton.styleFrom(
            backgroundColor: config.primaryColor,
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(vertical: 16),
          ),
          child: _isLoadingEstimate
              ? const SizedBox(
                  height: 20,
                  width: 20,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                  ),
                )
              : Text(
                  l10n.continueButton,
                  style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
                ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
