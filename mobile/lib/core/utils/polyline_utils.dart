import '../models/lat_lng.dart';
import 'distance_utils.dart';

/// Trim a polyline by removing points the driver has already passed.
///
/// Finds the point on [polyline] closest to [driverLocation], then returns
/// the sub-list from that point onward (including the closest point).
/// Returns the original list if it has fewer than 2 points.
List<LatLng> trimPolyline(List<LatLng> polyline, LatLng driverLocation) {
  if (polyline.length < 2) return polyline;

  var minDist = double.infinity;
  var closestIndex = 0;

  for (var i = 0; i < polyline.length; i++) {
    final d = haversineKm(
      driverLocation.latitude,
      driverLocation.longitude,
      polyline[i].latitude,
      polyline[i].longitude,
    );
    if (d < minDist) {
      minDist = d;
      closestIndex = i;
    }
  }

  return polyline.sublist(closestIndex);
}

/// Returns the minimum distance in km from [point] to any point on [polyline].
double distanceToPolylineKm(LatLng point, List<LatLng> polyline) {
  if (polyline.isEmpty) return double.infinity;

  var minDist = double.infinity;
  for (final p in polyline) {
    final d = haversineKm(
      point.latitude,
      point.longitude,
      p.latitude,
      p.longitude,
    );
    if (d < minDist) minDist = d;
  }
  return minDist;
}
