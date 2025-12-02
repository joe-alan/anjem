import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/ride_request.dart';

class DriverIncomingRequestNotifier extends StateNotifier<RideRequest?> {
  DriverIncomingRequestNotifier() : super(null);

  void setRequest(RideRequest request) {
    state = request;
  }

  void clear() {
    state = null;
  }
}

final driverIncomingRequestProvider =
    StateNotifierProvider<DriverIncomingRequestNotifier, RideRequest?>((ref) {
  return DriverIncomingRequestNotifier();
});
