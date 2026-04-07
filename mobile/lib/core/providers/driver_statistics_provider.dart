import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'api_provider.dart';

// Vehicle Information Model
class VehicleInfo {
  final String type;
  final String model;
  final String year;
  final String plateNumber;

  const VehicleInfo({
    this.type = '',
    this.model = '',
    this.year = '',
    this.plateNumber = '',
  });

  factory VehicleInfo.fromJson(Map<String, dynamic> json) {
    return VehicleInfo(
      type: json['type'] ?? '',
      model: json['model'] ?? '',
      year: json['year']?.toString() ?? '',
      plateNumber: json['plate_number'] ?? '',
    );
  }
}

// Driver Statistics Model
class DriverStatistics {
  final int totalRides;
  final double rating;
  final int todayRides;
  final double todayEarnings;
  final bool isVerified;
  final bool isAvailable;
  final VehicleInfo vehicleInfo;

  const DriverStatistics({
    this.totalRides = 0,
    this.rating = 0.0,
    this.todayRides = 0,
    this.todayEarnings = 0.0,
    this.isVerified = false,
    this.isAvailable = false,
    this.vehicleInfo = const VehicleInfo(),
  });

  factory DriverStatistics.fromJson(Map<String, dynamic> json) {
    return DriverStatistics(
      totalRides: json['total_rides'] ?? 0,
      rating: (json['rating'] ?? 0.0).toDouble(),
      todayRides: json['today_rides'] ?? 0,
      todayEarnings: (json['today_earnings'] ?? 0.0).toDouble(),
      isVerified: json['is_verified'] ?? false,
      isAvailable: json['is_available'] ?? false,
      vehicleInfo: json['vehicle_info'] != null
          ? VehicleInfo.fromJson(json['vehicle_info'])
          : const VehicleInfo(),
    );
  }
}

// Driver Statistics Provider (AsyncNotifier for auto-refresh)
class DriverStatisticsNotifier extends AutoDisposeAsyncNotifier<DriverStatistics> {
  @override
  Future<DriverStatistics> build() async {
    return _fetchStatistics();
  }

  Future<DriverStatistics> _fetchStatistics() async {
    try {
      final apiService = ref.read(apiServiceProvider);
      final response = await apiService.get('/driver/statistics');

      debugPrint('DriverStatistics: Fetched statistics - ${response.data}');

      if (response.data['success'] == true && response.data['data'] != null) {
        return DriverStatistics.fromJson(response.data['data']);
      }

      // Return default if no data
      return const DriverStatistics();
    } catch (e) {
      debugPrint('DriverStatistics: Error fetching statistics - $e');

      // Check if the error is because driver profile doesn't exist (404)
      if (e.toString().contains('404') || e.toString().contains('not found')) {
        debugPrint('DriverStatistics: Driver profile not found - user likely hasn\'t completed KYC');
      }

      // Return default stats on error instead of throwing
      // This allows the app to function even if KYC is not complete
      return const DriverStatistics();
    }
  }

  Future<void> refresh() async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(() => _fetchStatistics());
  }
}

// Provider
final driverStatisticsProvider =
    AutoDisposeAsyncNotifierProvider<DriverStatisticsNotifier, DriverStatistics>(
  () => DriverStatisticsNotifier(),
);
