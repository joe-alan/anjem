import 'package:flutter/material.dart';
import 'package:mobile/l10n/app_localizations.dart';
import 'package:url_launcher/url_launcher.dart';

/// Opens WhatsApp for the given [phone] number after a confirmation dialog.
/// Shows a snackbar if [phone] is null/empty or launch fails.
/// Must be called from a widget that is still mounted.
Future<void> openWhatsApp(BuildContext context, String? phone, String name) async {
  final l10n = AppLocalizations.of(context);
  if (phone == null || phone.isEmpty) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(l10n.whatsAppUnavailable), backgroundColor: Colors.orange),
    );
    return;
  }
  final confirmed = await showDialog<bool>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: Text(l10n.openWhatsAppTitle),
      content: Text(l10n.openWhatsAppMessage(name)),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(ctx).pop(false),
          child: Text(l10n.cancel),
        ),
        TextButton(
          onPressed: () => Navigator.of(ctx).pop(true),
          child: const Text('WhatsApp', style: TextStyle(color: Color(0xFF25D366), fontWeight: FontWeight.w600)),
        ),
      ],
    ),
  );
  if (confirmed == true && context.mounted) {
    try {
      await launchUrl(Uri.parse('https://wa.me/$phone'), mode: LaunchMode.externalApplication);
    } catch (_) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(l10n.whatsAppUnavailable), backgroundColor: Colors.red),
        );
      }
    }
  }
}
