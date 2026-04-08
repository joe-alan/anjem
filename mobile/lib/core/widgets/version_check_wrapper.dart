import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';
import '../config/app_config.dart';
import 'force_update_screen.dart';
import 'splash_screen.dart';

class VersionCheckWrapper extends StatefulWidget {
  final Widget child;

  const VersionCheckWrapper({super.key, required this.child});

  @override
  State<VersionCheckWrapper> createState() => _VersionCheckWrapperState();
}

class _VersionCheckWrapperState extends State<VersionCheckWrapper> {
  bool _checking = true;
  bool _needsUpdate = false;
  String _updateUrl = '';

  @override
  void initState() {
    super.initState();
    _checkVersion();
  }

  Future<void> _checkVersion() async {
    try {
      final packageInfo = await PackageInfo.fromPlatform();
      final currentVersion = packageInfo.version;

      final dio = Dio(BaseOptions(
        baseUrl: AppConfig.instance.apiBaseUrl,
        connectTimeout: const Duration(seconds: 10),
        receiveTimeout: const Duration(seconds: 10),
      ));

      final response = await dio.get('/app/config');
      final data = response.data['data'] as Map<String, dynamic>;

      final minVersion = data['min_version'] as String? ?? '1.0.0';
      final forceUpdate = data['force_update'] as bool? ?? false;
      final updateUrl = data['update_url'] as String? ?? '';

      if (forceUpdate && _isVersionBelow(currentVersion, minVersion)) {
        if (mounted) {
          setState(() {
            _needsUpdate = true;
            _updateUrl = updateUrl;
            _checking = false;
          });
        }
        return;
      }
    } catch (e) {
      debugPrint('VersionCheck: Failed to check version - $e');
    }

    if (mounted) {
      setState(() => _checking = false);
    }
  }

  bool _isVersionBelow(String current, String minimum) {
    try {
      final currentParts = current.split('.').map(int.parse).toList();
      final minimumParts = minimum.split('.').map(int.parse).toList();

      while (currentParts.length < 3) {
        currentParts.add(0);
      }
      while (minimumParts.length < 3) {
        minimumParts.add(0);
      }

      for (var i = 0; i < 3; i++) {
        if (currentParts[i] < minimumParts[i]) return true;
        if (currentParts[i] > minimumParts[i]) return false;
      }
      return false;
    } catch (e) {
      debugPrint('VersionCheck: Failed to parse versions - $e');
      return false;
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_checking) {
      return const SplashScreen();
    }
    if (_needsUpdate) {
      return ForceUpdateScreen(updateUrl: _updateUrl);
    }
    return widget.child;
  }
}
