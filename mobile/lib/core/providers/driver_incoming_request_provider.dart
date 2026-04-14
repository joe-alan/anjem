import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/ride_request.dart';

class DriverIncomingRequestNotifier extends StateNotifier<RideRequest?> {
  DriverIncomingRequestNotifier() : super(null);

  String? _cancelledBy;

  void setRequest(RideRequest request) {
    _cancelledBy = null;
    state = request;
  }

  void clear() {
    _cancelledBy = null;
    state = null;
  }

  /// Clear with a reason so the UI can show the right dismissal message.
  void clearWithReason(String? cancelledBy) {
    _cancelledBy = cancelledBy;
    state = null;
  }

  /// Consume the cancellation reason (returns once, then clears).
  String? consumeCancelledBy() {
    final reason = _cancelledBy;
    _cancelledBy = null;
    return reason;
  }
}

final driverIncomingRequestProvider =
    StateNotifierProvider<DriverIncomingRequestNotifier, RideRequest?>((ref) {
  return DriverIncomingRequestNotifier();
});
