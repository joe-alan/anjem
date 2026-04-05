import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile/l10n/app_localizations.dart';
import 'package:package_info_plus/package_info_plus.dart';
import '../../core/config/app_config.dart';
import '../../core/providers/api_provider.dart';
import '../../core/providers/auth_provider.dart';
import '../../core/providers/driver_status_provider.dart';
import '../../core/widgets/account_section.dart';
import '../../core/widgets/language_card.dart';

class DriverSettingsScreen extends ConsumerStatefulWidget {
  const DriverSettingsScreen({super.key});

  @override
  ConsumerState<DriverSettingsScreen> createState() =>
      _DriverSettingsScreenState();
}

class _DriverSettingsScreenState extends ConsumerState<DriverSettingsScreen> {
  double _maxPickupRadius = 1.0;
  bool _noMaxRadius = false;
  bool _isLoadingSettings = false;
  bool _isSavingSettings = false;
  String? _settingsError;
  String? _settingsSuccess;
  String _appVersion = '';

  static const double _noMaxSentinel = 50.0;

  @override
  void initState() {
    super.initState();

    final savedRadius = ref.read(driverStatusProvider).maxPickupRadiusKm;
    _noMaxRadius = savedRadius >= _noMaxSentinel;
    _maxPickupRadius = _noMaxRadius ? 1.0 : savedRadius.clamp(0.5, 5.0);

    _loadCurrentSettings();
    _loadAppVersion();
  }

  Future<void> _loadAppVersion() async {
    final info = await PackageInfo.fromPlatform();
    if (mounted) setState(() => _appVersion = info.version);
  }

  Future<void> _loadCurrentSettings() async {
    setState(() {
      _isLoadingSettings = true;
      _settingsError = null;
    });

    try {
      final apiService = ref.read(apiServiceProvider);
      final response = await apiService.get('/driver/queue-position');

      if (response.data['success'] == true) {
        final data = response.data['data'] as Map<String, dynamic>;
        final radius =
            (data['max_pickup_radius_km'] as num?)?.toDouble() ?? 1.0;

        setState(() {
          _noMaxRadius = radius >= _noMaxSentinel;
          _maxPickupRadius = _noMaxRadius ? 5.0 : radius.clamp(0.5, 5.0);
          _isLoadingSettings = false;
        });

        ref.read(driverStatusProvider.notifier).updateMaxRadius(radius);
      }
    } catch (e) {
      if (mounted) {
        final l10n = AppLocalizations.of(context);
        setState(() {
          _isLoadingSettings = false;
          _settingsError = l10n.failedToLoadSettings;
        });
      }
    }
  }

  Future<void> _saveSettings() async {
    setState(() {
      _isSavingSettings = true;
      _settingsError = null;
      _settingsSuccess = null;
    });

    try {
      final apiService = ref.read(apiServiceProvider);
      final response = await apiService.patch('/driver/settings', data: {
        'max_pickup_radius_km':
            _noMaxRadius ? _noMaxSentinel : _maxPickupRadius,
      });

      if (response.data['success'] == true) {
        ref
            .read(driverStatusProvider.notifier)
            .updateMaxRadius(_maxPickupRadius);

        if (mounted) {
          final l10n = AppLocalizations.of(context);
          setState(() {
            _isSavingSettings = false;
            _settingsSuccess = l10n.settingsSavedSuccess;
          });
        }

        Future.delayed(const Duration(seconds: 2), () {
          if (mounted) setState(() => _settingsSuccess = null);
        });
      } else {
        throw Exception(
            response.data['message'] ?? 'Failed to save settings');
      }
    } catch (e) {
      if (mounted) {
        final l10n = AppLocalizations.of(context);
        setState(() {
          _isSavingSettings = false;
          _settingsError = l10n.failedToSaveSettings;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final l10n = AppLocalizations.of(context);
    final user = ref.watch(authStateProvider).user;

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.driverSettingsTitle),
        backgroundColor: config.primaryColor,
        foregroundColor: Colors.white,
      ),
      body: _isLoadingSettings
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // Ride settings
                  Card(
                    elevation: 2,
                    child: Padding(
                      padding: const EdgeInsets.all(16.0),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            l10n.rideSettingsSectionTitle,
                            style: Theme.of(context)
                                .textTheme
                                .titleMedium
                                ?.copyWith(fontWeight: FontWeight.bold),
                          ),
                          const SizedBox(height: 8),
                          SwitchListTile(
                            contentPadding: EdgeInsets.zero,
                            title: Text(
                              l10n.noMaxRadiusLabel,
                              style: const TextStyle(
                                  fontWeight: FontWeight.bold),
                            ),
                            subtitle: Text(
                              l10n.noMaxRadiusDesc,
                              style: TextStyle(
                                  fontSize: 13, color: Colors.grey[600]),
                            ),
                            value: _noMaxRadius,
                            activeColor: config.primaryColor,
                            onChanged: (value) =>
                                setState(() => _noMaxRadius = value),
                          ),
                          const SizedBox(height: 8),
                          Opacity(
                            opacity: _noMaxRadius ? 0.38 : 1.0,
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  l10n.maxPickupRadiusTitle,
                                  style: const TextStyle(
                                      fontWeight: FontWeight.bold),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  l10n.maxPickupRadiusDesc,
                                  style: TextStyle(
                                      fontSize: 13,
                                      color: Colors.grey[600]),
                                ),
                                Row(
                                  children: [
                                    const Icon(Icons.location_on,
                                        color: Colors.blue),
                                    const SizedBox(width: 8),
                                    Expanded(
                                      child: Slider(
                                        value: _maxPickupRadius,
                                        min: 0.5,
                                        max: 5.0,
                                        divisions: 9,
                                        label:
                                            '${_maxPickupRadius.toStringAsFixed(1)} km',
                                        activeColor: config.primaryColor,
                                        onChanged: _noMaxRadius
                                            ? null
                                            : (value) {
                                                setState(() {
                                                  _maxPickupRadius =
                                                      double.parse(value
                                                          .toStringAsFixed(
                                                              1));
                                                });
                                              },
                                      ),
                                    ),
                                  ],
                                ),
                                Center(
                                  child: Container(
                                    padding: const EdgeInsets.symmetric(
                                        horizontal: 16, vertical: 8),
                                    decoration: BoxDecoration(
                                      color: config.primaryColor
                                          .withValues(alpha: 0.1),
                                      borderRadius:
                                          BorderRadius.circular(20),
                                      border: Border.all(
                                          color: config.primaryColor
                                              .withValues(alpha: 0.3)),
                                    ),
                                    child: Text(
                                      l10n.radiusCurrentLabel(
                                          _maxPickupRadius
                                              .toStringAsFixed(1)),
                                      style: TextStyle(
                                        fontSize: 18,
                                        fontWeight: FontWeight.bold,
                                        color: config.primaryColor,
                                      ),
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Row(
                                  mainAxisAlignment:
                                      MainAxisAlignment.spaceBetween,
                                  children: [
                                    Text(l10n.radiusMinLabel,
                                        style: TextStyle(
                                            fontSize: 12,
                                            color: Colors.grey[500])),
                                    Text(l10n.radiusMaxLabel,
                                        style: TextStyle(
                                            fontSize: 12,
                                            color: Colors.grey[500])),
                                  ],
                                ),
                              ],
                            ),
                          ),
                          if (_settingsError != null) ...[
                            const SizedBox(height: 8),
                            Row(
                              children: [
                                const Icon(Icons.error,
                                    color: Colors.red, size: 16),
                                const SizedBox(width: 4),
                                Text(_settingsError!,
                                    style:
                                        const TextStyle(color: Colors.red)),
                              ],
                            ),
                          ],
                          if (_settingsSuccess != null) ...[
                            const SizedBox(height: 8),
                            Row(
                              children: [
                                const Icon(Icons.check_circle,
                                    color: Colors.green, size: 16),
                                const SizedBox(width: 4),
                                Text(_settingsSuccess!,
                                    style: const TextStyle(
                                        color: Colors.green)),
                              ],
                            ),
                          ],
                          const SizedBox(height: 16),
                          SizedBox(
                            width: double.infinity,
                            child: ElevatedButton.icon(
                              onPressed:
                                  _isSavingSettings ? null : _saveSettings,
                              icon: _isSavingSettings
                                  ? const SizedBox(
                                      width: 20,
                                      height: 20,
                                      child: CircularProgressIndicator(
                                          strokeWidth: 2),
                                    )
                                  : const Icon(Icons.save),
                              label: Text(_isSavingSettings
                                  ? l10n.saving
                                  : l10n.saveSettingsButton),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: config.primaryColor,
                                foregroundColor: Colors.white,
                                padding:
                                    const EdgeInsets.symmetric(vertical: 14),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),

                  const SizedBox(height: 16),

                  // Vehicle info (read-only)
                  if (user?.driverProfile != null)
                    Card(
                      elevation: 2,
                      child: Padding(
                        padding: const EdgeInsets.all(16.0),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              l10n.vehicleInfoSectionTitle,
                              style: Theme.of(context)
                                  .textTheme
                                  .titleMedium
                                  ?.copyWith(fontWeight: FontWeight.bold),
                            ),
                            const SizedBox(height: 12),
                            _VehicleInfoRow(
                              label: l10n.vehicleTypeLabel,
                              value: user!.driverProfile!.vehicleType,
                            ),
                            _VehicleInfoRow(
                              label: l10n.colorLabel,
                              value: user.driverProfile!.vehicleColor,
                            ),
                            _VehicleInfoRow(
                              label: l10n.plateNumberLabel,
                              value: user.driverProfile!.vehiclePlate,
                            ),
                          ],
                        ),
                      ),
                    ),

                  const SizedBox(height: 16),

                  // Language
                  const LanguageCard(),

                  const SizedBox(height: 16),

                  // Account
                  const AccountSection(),

                  const SizedBox(height: 16),

                  // About
                  Card(
                    elevation: 2,
                    child: Padding(
                      padding: const EdgeInsets.all(16.0),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            l10n.aboutSectionTitle,
                            style: Theme.of(context)
                                .textTheme
                                .titleMedium
                                ?.copyWith(fontWeight: FontWeight.bold),
                          ),
                          const SizedBox(height: 8),
                          Row(
                            mainAxisAlignment:
                                MainAxisAlignment.spaceBetween,
                            children: [
                              Text(l10n.appVersionLabel),
                              Text(
                                _appVersion,
                                style:
                                    TextStyle(color: Colors.grey[600]),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),

                  const SizedBox(height: 24),
                ],
              ),
            ),
    );
  }
}

class _VehicleInfoRow extends StatelessWidget {
  final String label;
  final String value;

  const _VehicleInfoRow({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: TextStyle(color: Colors.grey[600])),
          Text(value, style: const TextStyle(fontWeight: FontWeight.w500)),
        ],
      ),
    );
  }
}
