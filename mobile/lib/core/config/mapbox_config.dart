/// Mapbox configuration for the Anjem mobile app
///
/// This file contains Mapbox access tokens and configuration settings.
/// The public access token is used for map rendering, search, and routing.
class MapboxConfig {
  // Private constructor to prevent instantiation
  MapboxConfig._();

  /// Mapbox public access token
  /// Used for map tiles, geocoding, search, and directions API
  static const String accessToken =
      'pk.eyJ1Ijoiam9lLWFsYW4iLCJhIjoiY21nbTQ4NXdjMTU5NzJpb2t5MTd2eWM4eSJ9.Hx95EL3r7f9xqfEiX2yYeQ';

  /// Default map style URL (Mapbox Streets)
  static const String defaultStyleUrl = 'mapbox://styles/mapbox/streets-v12';

  /// Alternative style: Satellite Streets
  static const String satelliteStyleUrl =
      'mapbox://styles/mapbox/satellite-streets-v12';

  /// Alternative style: Dark mode
  static const String darkStyleUrl = 'mapbox://styles/mapbox/dark-v11';

  /// Alternative style: Light mode
  static const String lightStyleUrl = 'mapbox://styles/mapbox/light-v11';

  /// Mapbox Search Box API base URL
  static const String searchApiBaseUrl =
      'https://api.mapbox.com/search/searchbox/v1';

  /// Mapbox Directions API base URL
  static const String directionsApiBaseUrl =
      'https://api.mapbox.com/directions/v5/mapbox';

  /// Default profile for directions (driving, walking, cycling)
  static const String defaultDirectionsProfile = 'driving-traffic';

  /// Default camera settings
  static const double defaultZoom = 15.0;
  static const double defaultPitch = 0.0;
  static const double defaultBearing = 0.0;

  /// UI Campus center (default location for Indonesia University)
  static const double uiCampusLatitude = -6.3615;
  static const double uiCampusLongitude = 106.8242;

  /// Search radius for nearby beacons (in meters)
  static const double searchRadiusMeters = 5000.0; // 5km

  /// Marker icon sizes
  static const double beaconMarkerSize = 40.0;
  static const double userMarkerSize = 30.0;
  static const double destinationMarkerSize = 35.0;
}
