import 'dart:convert';
import 'package:http/http.dart' as http;
import '../../config/mapbox_config.dart';
import '../../models/lat_lng.dart';

class ReverseGeocodeResult {
  final String name;
  final String? address;
  final LatLng coordinates;

  const ReverseGeocodeResult({
    required this.name,
    this.address,
    required this.coordinates,
  });
}

class MapboxReverseGeocodingService {
  Future<ReverseGeocodeResult> reverseGeocode({
    required double latitude,
    required double longitude,
    String language = 'id',
  }) async {
    try {
      final uri = Uri.parse(
        'https://api.mapbox.com/search/geocode/v6/reverse',
      ).replace(queryParameters: {
        'longitude': longitude.toString(),
        'latitude': latitude.toString(),
        'access_token': MapboxConfig.accessToken,
        'language': language,
        'limit': '1',
      });

      final response = await http.get(uri).timeout(
        const Duration(seconds: 10),
        onTimeout: () => throw Exception('Reverse geocode timed out'),
      );

      if (response.statusCode != 200) {
        throw Exception('Reverse geocode failed: ${response.statusCode}');
      }

      final data = json.decode(response.body);
      final features = data['features'] as List?;

      if (features == null || features.isEmpty) {
        return ReverseGeocodeResult(
          name: 'Dropped Pin',
          coordinates: LatLng(latitude, longitude),
        );
      }

      final feature = features[0];
      final properties = feature['properties'] as Map<String, dynamic>;

      // Use the most specific name available
      final name = properties['name'] as String? ??
          properties['full_address'] as String? ??
          'Dropped Pin';

      final address = properties['full_address'] as String? ??
          properties['place_formatted'] as String?;

      return ReverseGeocodeResult(
        name: name,
        address: address != name ? address : null,
        coordinates: LatLng(latitude, longitude),
      );
    } catch (e) {
      // Fallback on any error
      return ReverseGeocodeResult(
        name: 'Dropped Pin',
        coordinates: LatLng(latitude, longitude),
      );
    }
  }
}
