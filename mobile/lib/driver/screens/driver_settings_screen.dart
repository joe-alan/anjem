import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile/l10n/app_localizations.dart';
import '../../core/config/app_config.dart';
import '../../core/providers/api_provider.dart';
import '../../core/providers/driver_status_provider.dart';
import '../../core/providers/locale_provider.dart';

class DriverSettingsScreen extends ConsumerStatefulWidget {
  const DriverSettingsScreen({super.key});

  @override
  ConsumerState<DriverSettingsScreen> createState() =>
      _DriverSettingsScreenState();
}

class _DriverSettingsScreenState extends ConsumerState<DriverSettingsScreen> {
  double _maxPickupRadius = 5.0;
  bool _isLoading = false;
  bool _isSaving = false;
  String? _error;
  String? _successMessage;

  @override
  void initState() {
    super.initState();
    // Initialise from current state
    _maxPickupRadius =
        ref.read(driverStatusProvider).maxPickupRadiusKm;
    _loadCurrentSettings();
  }

  Future<void> _loadCurrentSettings() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final apiService = ref.read(apiServiceProvider);
      final response = await apiService.get('/driver/queue-position');

      if (response.data['success'] == true) {
        final data = response.data['data'] as Map<String, dynamic>;
        final radius =
            (data['max_pickup_radius_km'] as num?)?.toDouble() ?? 5.0;

        setState(() {
          _maxPickupRadius = radius;
          _isLoading = false;
        });

        // Also update provider state
        ref.read(driverStatusProvider.notifier).updateMaxRadius(radius);
      }
    } catch (e) {
      if (mounted) {
        final l10n = AppLocalizations.of(context);
        setState(() {
          _isLoading = false;
          _error = l10n.failedToLoadSettings;
        });
      }
    }
  }

  Future<void> _saveSettings() async {
    setState(() {
      _isSaving = true;
      _error = null;
      _successMessage = null;
    });

    try {
      final apiService = ref.read(apiServiceProvider);
      final response = await apiService.patch('/driver/settings', data: {
        'max_pickup_radius_km': _maxPickupRadius,
      });

      if (response.data['success'] == true) {
        ref
            .read(driverStatusProvider.notifier)
            .updateMaxRadius(_maxPickupRadius);

        if (mounted) {
          final l10n = AppLocalizations.of(context);
          setState(() {
            _isSaving = false;
            _successMessage = l10n.settingsSavedSuccess;
          });
        }

        Future.delayed(const Duration(seconds: 2), () {
          if (mounted) {
            setState(() => _successMessage = null);
          }
        });
      } else {
        throw Exception(response.data['message'] ?? 'Failed to save settings');
      }
    } catch (e) {
      if (mounted) {
        final l10n = AppLocalizations.of(context);
        setState(() {
          _isSaving = false;
          _error = l10n.failedToSaveSettings;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final l10n = AppLocalizations.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.driverSettingsTitle),
        backgroundColor: config.primaryColor,
        foregroundColor: Colors.white,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // Max Pickup Radius
                  Card(
                    elevation: 2,
                    child: Padding(
                      padding: const EdgeInsets.all(16.0),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            l10n.maxPickupRadiusTitle,
                            style: Theme.of(context)
                                .textTheme
                                .titleMedium
                                ?.copyWith(fontWeight: FontWeight.bold),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            l10n.maxPickupRadiusDesc,
                            style: TextStyle(
                              fontSize: 13,
                              color: Colors.grey[600],
                            ),
                          ),
                          const SizedBox(height: 16),
                          Row(
                            children: [
                              const Icon(Icons.location_on, color: Colors.blue),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Slider(
                                  value: _maxPickupRadius,
                                  min: 0.5,
                                  max: 20.0,
                                  divisions: 39,
                                  label:
                                      '${_maxPickupRadius.toStringAsFixed(1)} km',
                                  activeColor: config.primaryColor,
                                  onChanged: (value) {
                                    setState(() {
                                      _maxPickupRadius =
                                          double.parse(value.toStringAsFixed(1));
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
                                color: config.primaryColor.withOpacity(0.1),
                                borderRadius: BorderRadius.circular(20),
                                border: Border.all(
                                    color: config.primaryColor.withOpacity(0.3)),
                              ),
                              child: Text(
                                l10n.radiusCurrentLabel(_maxPickupRadius.toStringAsFixed(1)),
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
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(l10n.radiusMinLabel,
                                  style: TextStyle(
                                      fontSize: 12, color: Colors.grey[500])),
                              Text(l10n.radiusMaxLabel,
                                  style: TextStyle(
                                      fontSize: 12, color: Colors.grey[500])),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),

                  const SizedBox(height: 16),

                  // Language
                  _LanguageCard(config: config),

                  const SizedBox(height: 16),

                  // Error / Success
                  if (_error != null)
                    Card(
                      color: Colors.red[50],
                      child: Padding(
                        padding: const EdgeInsets.all(12.0),
                        child: Row(
                          children: [
                            const Icon(Icons.error, color: Colors.red),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(_error!,
                                  style: const TextStyle(color: Colors.red)),
                            ),
                          ],
                        ),
                      ),
                    ),

                  if (_successMessage != null)
                    Card(
                      color: Colors.green[50],
                      child: Padding(
                        padding: const EdgeInsets.all(12.0),
                        child: Row(
                          children: [
                            const Icon(Icons.check_circle,
                                color: Colors.green),
                            const SizedBox(width: 8),
                            Text(_successMessage!,
                                style:
                                    const TextStyle(color: Colors.green)),
                          ],
                        ),
                      ),
                    ),

                  const SizedBox(height: 24),

                  ElevatedButton.icon(
                    onPressed: _isSaving ? null : _saveSettings,
                    icon: _isSaving
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.save),
                    label: Text(_isSaving ? l10n.saving : l10n.saveSettingsButton),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: config.primaryColor,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      textStyle: const TextStyle(fontSize: 16),
                    ),
                  ),
                ],
              ),
            ),
    );
  }
}

class _LanguageCard extends ConsumerWidget {
  final AppConfig config;
  const _LanguageCard({required this.config});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final currentLocale = ref.watch(localeProvider);

    return Card(
      elevation: 2,
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              l10n.languageSectionTitle,
              style: Theme.of(context)
                  .textTheme
                  .titleMedium
                  ?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 4),
            Text(
              l10n.languageSectionDesc,
              style: TextStyle(fontSize: 13, color: Colors.grey[600]),
            ),
            const SizedBox(height: 16),
            SegmentedButton<String>(
              segments: [
                ButtonSegment(
                  value: 'id',
                  label: Text(l10n.languageIndonesian),
                  icon: const Icon(Icons.language),
                ),
                ButtonSegment(
                  value: 'en',
                  label: Text(l10n.languageEnglish),
                  icon: const Icon(Icons.language),
                ),
              ],
              selected: {currentLocale.languageCode},
              onSelectionChanged: (selected) {
                ref.read(localeProvider.notifier).state =
                    Locale(selected.first);
              },
              style: ButtonStyle(
                backgroundColor: WidgetStateProperty.resolveWith((states) {
                  if (states.contains(WidgetState.selected)) {
                    return config.primaryColor;
                  }
                  return null;
                }),
                foregroundColor: WidgetStateProperty.resolveWith((states) {
                  if (states.contains(WidgetState.selected)) {
                    return Colors.white;
                  }
                  return null;
                }),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
