import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import '../models/lat_lng.dart';

// User Location State
class UserLocationState {
  final LatLng? location;
  final bool isLoading;
  final String? error;
  final bool permissionGranted;

  const UserLocationState({
    this.location,
    this.isLoading = false,
    this.error,
    this.permissionGranted = false,
  });

  UserLocationState copyWith({
    LatLng? location,
    bool? isLoading,
    String? error,
    bool? permissionGranted,
  }) {
    return UserLocationState(
      location: location ?? this.location,
      isLoading: isLoading ?? this.isLoading,
      error: error,
      permissionGranted: permissionGranted ?? this.permissionGranted,
    );
  }
}

// User Location Notifier
class UserLocationNotifier extends StateNotifier<UserLocationState> {
  UserLocationNotifier() : super(const UserLocationState());

  Future<void> checkPermission() async {
    LocationPermission permission = await Geolocator.checkPermission();

    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }

    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      state = state.copyWith(
        permissionGranted: false,
        error: 'Location permission denied',
      );
      return;
    }

    state = state.copyWith(permissionGranted: true, error: null);
    getCurrentLocation();
  }

  Future<void> getCurrentLocation() async {
    if (!state.permissionGranted) {
      await checkPermission();
      if (!state.permissionGranted) return;
    }

    state = state.copyWith(isLoading: true, error: null);

    try {
      final position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );

      state = state.copyWith(
        location: LatLng(position.latitude, position.longitude),
        isLoading: false,
        error: null,
      );
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.toString(),
      );
    }
  }

  void updateLocation(LatLng location) {
    state = state.copyWith(location: location, error: null);
  }
}

// User Location Provider
final userLocationProvider =
    StateNotifierProvider<UserLocationNotifier, UserLocationState>((ref) {
  final notifier = UserLocationNotifier();
  notifier.checkPermission();
  return notifier;
});
