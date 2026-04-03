import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile/l10n/app_localizations.dart';
import '../../core/config/app_config.dart';
import '../../core/models/place_search_result.dart';
import 'dart:math';
import '../../core/providers/beacons_provider.dart';
import '../../core/providers/place_search_provider.dart';
import '../../core/providers/recent_destinations_provider.dart';
import '../../core/providers/user_location_provider.dart';
import 'map_confirm_screen.dart';

enum _ActiveField { pickup, dropoff }

class LocationSearchScreen extends ConsumerStatefulWidget {
  const LocationSearchScreen({super.key});

  @override
  ConsumerState<LocationSearchScreen> createState() =>
      _LocationSearchScreenState();
}

class _LocationSearchScreenState extends ConsumerState<LocationSearchScreen> {
  final _pickupController = TextEditingController();
  final _dropoffController = TextEditingController();
  final _pickupFocus = FocusNode();
  final _dropoffFocus = FocusNode();
  Timer? _debounce;
  _ActiveField _activeField = _ActiveField.dropoff;

  PlaceSearchResult? _pickup;
  PlaceSearchResult? _dropoff;

  @override
  void initState() {
    super.initState();
    _pickupController.addListener(_onPickupChanged);
    _dropoffController.addListener(_onDropoffChanged);
    _pickupFocus.addListener(_onFocusChanged);
    _dropoffFocus.addListener(_onFocusChanged);

    // Auto-detect pickup after first frame
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _autoDetectPickup();
      _dropoffFocus.requestFocus();
    });
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _pickupFocus.removeListener(_onFocusChanged);
    _dropoffFocus.removeListener(_onFocusChanged);
    _pickupController.dispose();
    _dropoffController.dispose();
    _pickupFocus.dispose();
    _dropoffFocus.dispose();
    super.dispose();
  }

  void _onFocusChanged() {
    if (!mounted) return;
    // Only act when focus moves between the two text fields
    _ActiveField? newField;
    if (_pickupFocus.hasFocus) {
      newField = _ActiveField.pickup;
    } else if (_dropoffFocus.hasFocus) {
      newField = _ActiveField.dropoff;
    }
    if (newField != null && newField != _activeField) {
      _debounce?.cancel();
      ref.read(placeSearchProvider.notifier).clear();
      setState(() => _activeField = newField!);
    }
  }

  void _autoDetectPickup() {
    final location = ref.read(userLocationProvider).location;
    if (location == null) return;

    final beacons = ref.read(beaconsProvider).beacons;
    final l10n = AppLocalizations.of(context);

    // Find closest beacon from already-loaded list
    PlaceSearchResult? closest;
    double closestDist = double.infinity;

    for (final beacon in beacons) {
      final dist = _haversineKm(
        location.latitude, location.longitude,
        beacon.coordinates.latitude, beacon.coordinates.longitude,
      );
      if (dist < closestDist && dist <= 2.0) {
        closestDist = dist;
        closest = PlaceSearchResult(
          id: beacon.id,
          name: beacon.name,
          address: beacon.description,
          coordinates: beacon.coordinates,
          isBeacon: true,
          distanceKm: dist,
          source: 'database',
        );
      }
    }

    _pickupController.removeListener(_onPickupChanged);
    _pickup = closest ?? PlaceSearchResult(
      name: l10n.yourLocation,
      coordinates: location,
      source: 'mapbox',
    );
    _pickupController.text = _pickup!.name;
    _pickupController.addListener(_onPickupChanged);
    setState(() {});
  }

  static double _haversineKm(double lat1, double lng1, double lat2, double lng2) {
    const r = 6371.0;
    final dLat = (lat2 - lat1) * pi / 180;
    final dLng = (lng2 - lng1) * pi / 180;
    final a = sin(dLat / 2) * sin(dLat / 2) +
        cos(lat1 * pi / 180) * cos(lat2 * pi / 180) *
        sin(dLng / 2) * sin(dLng / 2);
    return r * 2 * atan2(sqrt(a), sqrt(1 - a));
  }

  void _onPickupChanged() {
    // Skip cursor/selection-only changes — only act when text actually changes
    if (_pickup != null && _pickupController.text == _pickup!.name) return;
    setState(() {
      _activeField = _ActiveField.pickup;
      _pickup = null;
    });
    _debouncedSearch(_pickupController.text);
  }

  void _onDropoffChanged() {
    if (_dropoff != null && _dropoffController.text == _dropoff!.name) return;
    setState(() {
      _activeField = _ActiveField.dropoff;
      _dropoff = null;
    });
    _debouncedSearch(_dropoffController.text);
  }

  void _debouncedSearch(String query) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), () {
      if (query.trim().length >= 2) {
        final location = ref.read(userLocationProvider).location;
        ref.read(placeSearchProvider.notifier).search(
              query: query,
              userLocation: location,
              radius: 15.0,
            );
      } else {
        ref.read(placeSearchProvider.notifier).clear();
      }
    });
  }

  void _onResultTap(PlaceSearchResult result) {
    // Kill pending search and remove listeners to prevent nullifying selections
    _debounce?.cancel();
    _pickupController.removeListener(_onPickupChanged);
    _dropoffController.removeListener(_onDropoffChanged);

    if (_activeField == _ActiveField.pickup) {
      _pickup = result;
      _pickupController.text = result.name;
      // Re-add listeners then move focus
      _pickupController.addListener(_onPickupChanged);
      _dropoffController.addListener(_onDropoffChanged);
      _dropoffFocus.requestFocus();
      setState(() {});
    } else {
      _dropoff = result;
      _dropoffController.text = result.name;
      // Re-add listeners
      _pickupController.addListener(_onPickupChanged);
      _dropoffController.addListener(_onDropoffChanged);
      setState(() {});
    }

    ref.read(placeSearchProvider.notifier).clear();

    // If both selected, navigate to map confirm
    if (_pickup != null && _dropoff != null) {
      _navigateToMapConfirm();
    }
  }

  void _swapLocations() {
    _debounce?.cancel();
    _pickupController.removeListener(_onPickupChanged);
    _dropoffController.removeListener(_onDropoffChanged);

    final tmpResult = _pickup;
    _pickup = _dropoff;
    _dropoff = tmpResult;
    _pickupController.text = _pickup?.name ?? '';
    _dropoffController.text = _dropoff?.name ?? '';

    _pickupController.addListener(_onPickupChanged);
    _dropoffController.addListener(_onDropoffChanged);
    setState(() {});

    if (_pickup != null && _dropoff != null) {
      _navigateToMapConfirm();
    }
  }

  void _navigateToMapConfirm() {
    if (_pickup == null || _dropoff == null) return;

    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (context) => MapConfirmScreen(
          mode: MapConfirmMode.pickup,
          initialCenter: _pickup!.coordinates,
          initialName: _pickup!.name,
          initialLocationId: _pickup!.id,
          confirmedDropoff: _dropoff,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final l10n = AppLocalizations.of(context);
    final searchState = ref.watch(placeSearchProvider);
    final recentDestinations = ref.watch(recentDestinationsProvider);

    // Show recent destinations when either field is focused and empty
    final showRecent = ((_activeField == _ActiveField.dropoff && _dropoffController.text.isEmpty) ||
        (_activeField == _ActiveField.pickup && _pickupController.text.isEmpty)) &&
        recentDestinations.isNotEmpty;

    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Column(
          children: [
            // Search fields
            Container(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
              decoration: BoxDecoration(
                color: Colors.white,
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.05),
                    blurRadius: 6,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Column(
                children: [
                  // Back button + fields row
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Back button + swap button
                      Column(
                        children: [
                          Padding(
                            padding: const EdgeInsets.only(top: 8),
                            child: IconButton(
                              icon: const Icon(Icons.arrow_back),
                              onPressed: () => Navigator.of(context).pop(),
                            ),
                          ),
                          IconButton(
                            icon: Icon(Icons.swap_vert, size: 20,
                              color: (_pickup != null || _dropoff != null)
                                  ? config.primaryColor
                                  : Colors.grey[400],
                            ),
                            padding: EdgeInsets.zero,
                            constraints: const BoxConstraints(minWidth: 48, minHeight: 32),
                            onPressed: (_pickup != null || _dropoff != null) ? _swapLocations : null,
                          ),
                        ],
                      ),

                      // Input fields
                      Expanded(
                        child: Column(
                          children: [
                            // Pickup field
                            _buildInputField(
                              controller: _pickupController,
                              focusNode: _pickupFocus,
                              hint: l10n.pickupFieldHint,
                              icon: Icons.trip_origin,
                              iconColor: Colors.green,
                              onTap: () {},
                              onClear: () {
                                _pickupController.removeListener(_onPickupChanged);
                                _pickup = null;
                                _pickupController.clear();
                                _pickupController.addListener(_onPickupChanged);
                                ref.read(placeSearchProvider.notifier).clear();
                                setState(() => _activeField = _ActiveField.pickup);
                                _pickupFocus.requestFocus();
                              },
                            ),

                            // Vertical dots divider
                            Padding(
                              padding: const EdgeInsets.only(left: 14),
                              child: Align(
                                alignment: Alignment.centerLeft,
                                child: Icon(
                                  Icons.more_vert,
                                  size: 16,
                                  color: Colors.grey[400],
                                ),
                              ),
                            ),

                            // Dropoff field
                            _buildInputField(
                              controller: _dropoffController,
                              focusNode: _dropoffFocus,
                              hint: l10n.dropoffFieldHint,
                              icon: Icons.location_on,
                              iconColor: config.primaryColor,
                              onTap: () {},
                              onClear: () {
                                _dropoffController.removeListener(_onDropoffChanged);
                                _dropoff = null;
                                _dropoffController.clear();
                                _dropoffController.addListener(_onDropoffChanged);
                                ref.read(placeSearchProvider.notifier).clear();
                                setState(() => _activeField = _ActiveField.dropoff);
                                _dropoffFocus.requestFocus();
                              },
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            // Results list
            Expanded(
              child: searchState.isLoading
                  ? Center(
                      child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: config.primaryColor,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Text(l10n.searchingText),
                          ],
                        ),
                      ),
                    )
                  : showRecent
                      ? _buildRecentDestinations(l10n, recentDestinations)
                      : searchState.hasResults
                          ? _buildSearchResults(searchState.results)
                          : _buildEmptyState(l10n, searchState),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildInputField({
    required TextEditingController controller,
    required FocusNode focusNode,
    required String hint,
    required IconData icon,
    required Color iconColor,
    required VoidCallback onTap,
    required VoidCallback onClear,
  }) {
    return GestureDetector(
      onTap: () {
        focusNode.requestFocus();
        onTap();
      },
      child: Container(
        height: 44,
        decoration: BoxDecoration(
          color: Colors.grey[100],
          borderRadius: BorderRadius.circular(8),
        ),
        child: Row(
          children: [
            const SizedBox(width: 12),
            Icon(icon, size: 18, color: iconColor),
            const SizedBox(width: 10),
            Expanded(
              child: TextField(
                controller: controller,
                focusNode: focusNode,
                onTap: onTap,
                decoration: InputDecoration(
                  hintText: hint,
                  hintStyle: TextStyle(color: Colors.grey[500], fontSize: 14),
                  border: InputBorder.none,
                  isDense: true,
                  contentPadding: const EdgeInsets.symmetric(vertical: 10),
                ),
                style: const TextStyle(fontSize: 14),
              ),
            ),
            if (controller.text.isNotEmpty)
              IconButton(
                icon: Icon(Icons.close, size: 18, color: Colors.grey[500]),
                onPressed: onClear,
                padding: EdgeInsets.zero,
                constraints: const BoxConstraints(),
              ),
            const SizedBox(width: 8),
          ],
        ),
      ),
    );
  }

  Widget _buildRecentDestinations(
    AppLocalizations l10n,
    List<PlaceSearchResult> destinations,
  ) {
    return ListView(
      padding: const EdgeInsets.symmetric(vertical: 8),
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
          child: Text(
            l10n.recentDestinations,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: Colors.grey[600],
            ),
          ),
        ),
        ...destinations.map((dest) => _buildResultTile(dest, isRecent: true)),
      ],
    );
  }

  Widget _buildSearchResults(List<PlaceSearchResult> results) {
    return ListView.builder(
      padding: const EdgeInsets.symmetric(vertical: 8),
      itemCount: results.length,
      itemBuilder: (context, index) => _buildResultTile(results[index]),
    );
  }

  Widget _buildResultTile(PlaceSearchResult result, {bool isRecent = false}) {
    return ListTile(
      leading: CircleAvatar(
        backgroundColor: Colors.grey[100],
        child: Icon(
          isRecent ? Icons.history : _iconForResult(result),
          color: Colors.grey[700],
          size: 20,
        ),
      ),
      title: Text(
        result.name,
        style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
      ),
      subtitle: result.subtitle != null
          ? Text(
              result.subtitle!,
              style: TextStyle(fontSize: 12, color: Colors.grey[600]),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            )
          : null,
      dense: true,
      onTap: () => _onResultTap(result),
    );
  }

  Widget _buildEmptyState(AppLocalizations l10n, PlaceSearchState searchState) {
    if (searchState.lastQuery != null &&
        searchState.lastQuery!.length >= 2 &&
        !searchState.isLoading) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Text(
            l10n.noResultsFound,
            style: TextStyle(color: Colors.grey[600]),
          ),
        ),
      );
    }
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Text(
          l10n.startTypingHint,
          style: TextStyle(color: Colors.grey[500]),
        ),
      ),
    );
  }

  IconData _iconForResult(PlaceSearchResult result) {
    if (result.isBeacon) return Icons.radio_button_checked;
    switch (result.category?.toLowerCase()) {
      case 'gate':
        return Icons.door_front_door_outlined;
      case 'faculty':
        return Icons.school_outlined;
      case 'canteen':
        return Icons.restaurant_outlined;
      case 'library':
        return Icons.local_library_outlined;
      case 'dormitory':
        return Icons.home_outlined;
      default:
        return Icons.place_outlined;
    }
  }
}
