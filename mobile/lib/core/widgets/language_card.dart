import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile/l10n/app_localizations.dart';
import '../config/app_config.dart';
import '../providers/locale_provider.dart';

class LanguageCard extends ConsumerWidget {
  const LanguageCard({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final config = AppConfig.instance;
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
                ref
                    .read(localeProvider.notifier)
                    .setLocale(Locale(selected.first));
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
