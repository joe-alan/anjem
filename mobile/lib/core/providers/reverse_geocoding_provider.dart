import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../services/mapbox/mapbox_reverse_geocoding_service.dart';

final reverseGeocodingServiceProvider =
    Provider<MapboxReverseGeocodingService>((ref) {
  return MapboxReverseGeocodingService();
});
