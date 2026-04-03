import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
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

  Map<String, dynamic> toJson() => {
    'name': name,
    'address': address,
    'lat': coordinates.latitude,
    'lng': coordinates.longitude,
  };

  factory ReverseGeocodeResult.fromJson(Map<String, dynamic> json) =>
      ReverseGeocodeResult(
        name: json['name'] as String,
        address: json['address'] as String?,
        coordinates: LatLng(
          (json['lat'] as num).toDouble(),
          (json['lng'] as num).toDouble(),
        ),
      );
}

class MapboxReverseGeocodingService {
  static const _cachePrefix = 'poi_cache:';

  /// Round to ~11m grid cell for cache key.
  static String _cacheKey(double lat, double lng) {
    return '$_cachePrefix${lat.toStringAsFixed(4)},${lng.toStringAsFixed(4)}';
  }

  Future<ReverseGeocodeResult?> _getFromCache(String key) async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(key);
    if (raw == null) return null;
    try {
      return ReverseGeocodeResult.fromJson(
        json.decode(raw) as Map<String, dynamic>,
      );
    } catch (_) {
      return null;
    }
  }

  Future<void> _saveToCache(String key, ReverseGeocodeResult result) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(key, json.encode(result.toJson()));
  }

  /// Reverse geocode using Geocoding API (returns street addresses).
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
      return ReverseGeocodeResult(
        name: 'Dropped Pin',
        coordinates: LatLng(latitude, longitude),
      );
    }
  }

  /// Reverse search using Search Box API (returns nearby POIs/places).
  /// Results are cached in SharedPreferences by ~11m grid cell.
  Future<ReverseGeocodeResult> reverseSearchPOI({
    required double latitude,
    required double longitude,
    String language = 'id',
  }) async {
    final key = _cacheKey(latitude, longitude);

    // Check cache first
    final cached = await _getFromCache(key);
    if (cached != null) return cached;

    try {
      final uri = Uri.parse(
        '${MapboxConfig.searchApiBaseUrl}/reverse',
      ).replace(queryParameters: {
        'longitude': longitude.toString(),
        'latitude': latitude.toString(),
        'access_token': MapboxConfig.accessToken,
        'language': language,
        'limit': '1',
        'types': 'poi',
      });

      final response = await http.get(uri).timeout(
        const Duration(seconds: 10),
        onTimeout: () => throw Exception('Search Box reverse timed out'),
      );

      if (response.statusCode != 200) {
        throw Exception('Search Box reverse failed: ${response.statusCode}');
      }

      final data = json.decode(response.body);
      final features = data['features'] as List?;

      if (features == null || features.isEmpty) {
        final result = ReverseGeocodeResult(
          name: 'Dropped Pin',
          coordinates: LatLng(latitude, longitude),
        );
        await _saveToCache(key, result);
        return result;
      }

      final feature = features[0];
      final properties = feature['properties'] as Map<String, dynamic>;

      final name = properties['name'] as String? ?? 'Dropped Pin';
      final address = properties['full_address'] as String? ??
          properties['place_formatted'] as String?;

      LatLng coords = LatLng(latitude, longitude);
      final geometry = feature['geometry'] as Map<String, dynamic>?;
      if (geometry != null) {
        final coordList = geometry['coordinates'] as List?;
        if (coordList != null && coordList.length >= 2) {
          coords = LatLng(
            (coordList[1] as num).toDouble(),
            (coordList[0] as num).toDouble(),
          );
        }
      }

      final result = ReverseGeocodeResult(
        name: name,
        address: address,
        coordinates: coords,
      );
      await _saveToCache(key, result);
      return result;
    } catch (e) {
      return ReverseGeocodeResult(
        name: 'Dropped Pin',
        coordinates: LatLng(latitude, longitude),
      );
    }
  }
}
