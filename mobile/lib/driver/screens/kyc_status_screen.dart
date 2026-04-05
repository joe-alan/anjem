import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile/l10n/app_localizations.dart';
import '../../core/config/app_config.dart';
import '../../core/models/kyc_submission.dart';
import '../../core/providers/kyc_provider.dart';
import '../../core/providers/driver_status_provider.dart';
import 'kyc_form_screen.dart';

class KycStatusScreen extends ConsumerStatefulWidget {
  const KycStatusScreen({super.key});

  @override
  ConsumerState<KycStatusScreen> createState() => _KycStatusScreenState();
}

class _KycStatusScreenState extends ConsumerState<KycStatusScreen> {
  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final l10n = AppLocalizations.of(context);
    final kycState = ref.watch(kycStateProvider);
    final kyc = kycState.kycSubmission;

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.kycStatusTitle),
        backgroundColor: config.primaryColor,
        foregroundColor: Colors.white,
      ),
      body: kycState.isLoading
          ? const Center(child: CircularProgressIndicator())
          : kyc == null || !kyc.kycSubmitted
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.verified_user_outlined,
                          size: 64, color: Colors.grey[400]),
                      const SizedBox(height: 16),
                      Text(l10n.noKycData,
                          style: TextStyle(color: Colors.grey[600])),
                    ],
                  ),
                )
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    // Status badge
                    _StatusBadge(kyc: kyc),
                    const SizedBox(height: 16),

                    // Suspension reason
                    if (kyc.suspendReason != null) ...[
                      Card(
                        color: Colors.red[50],
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Row(
                            children: [
                              const Icon(Icons.warning, color: Colors.red),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Text(kyc.suspendReason!,
                                    style: const TextStyle(color: Colors.red)),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 16),
                    ],

                    // KYC details
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Profile photo
                            if (kyc.profilePhotoUrl != null) ...[
                              Center(
                                child: CircleAvatar(
                                  radius: 40,
                                  backgroundImage:
                                      NetworkImage(kyc.profilePhotoUrl!),
                                  onBackgroundImageError: (_, __) {},
                                ),
                              ),
                              const SizedBox(height: 16),
                            ],
                            Text(l10n.kycDetailsTitle,
                                style: const TextStyle(
                                    fontSize: 16,
                                    fontWeight: FontWeight.bold)),
                            const SizedBox(height: 16),
                            _InfoRow(
                                label: l10n.kycStudentEmail,
                                value: kyc.studentEmail ?? '-'),
                            _InfoRow(
                                label: l10n.kycStudentId,
                                value: kyc.studentId ?? '-'),
                            _InfoRow(
                                label: l10n.kycStudentName,
                                value: kyc.studentName ?? '-'),
                            _InfoRow(
                                label: l10n.whatsappNumberLabel.replaceAll(' *', ''),
                                value: kyc.phoneNumber ?? '-'),
                            const Divider(height: 24),
                            _InfoRow(
                                label: l10n.kycVehicleType,
                                value: kyc.vehicleType ?? '-'),
                            _InfoRow(
                                label: l10n.kycVehiclePlate,
                                value: kyc.vehiclePlate ?? '-'),
                            _InfoRow(
                                label: l10n.kycVehicleColor,
                                value: kyc.vehicleColor ?? '-'),
                          ],
                        ),
                      ),
                    ),

                    // KTM Photo
                    if (kyc.ktmUrl != null) ...[
                      const SizedBox(height: 16),
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(l10n.kycKtmPhoto,
                                  style: const TextStyle(
                                      fontSize: 16,
                                      fontWeight: FontWeight.bold)),
                              const SizedBox(height: 12),
                              ClipRRect(
                                borderRadius: BorderRadius.circular(8),
                                child: Image.network(
                                  kyc.ktmUrl!,
                                  width: double.infinity,
                                  fit: BoxFit.cover,
                                  errorBuilder: (_, __, ___) => Container(
                                    height: 120,
                                    color: Colors.grey[200],
                                    child: const Center(
                                      child: Icon(Icons.broken_image,
                                          size: 48, color: Colors.grey),
                                    ),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],

                    const SizedBox(height: 32),

                    // Edit KYC button
                    OutlinedButton.icon(
                      onPressed: () => _confirmEdit(context),
                      icon: const Icon(Icons.edit, color: Colors.orange),
                      label: Text(l10n.editKycButton,
                          style: const TextStyle(color: Colors.orange)),
                      style: OutlinedButton.styleFrom(
                        side: const BorderSide(color: Colors.orange),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                      ),
                    ),
                  ],
                ),
    );
  }

  void _confirmEdit(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final driverStatus = ref.read(driverStatusProvider);

    // Guard: must be offline
    if (driverStatus.isOnline) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(l10n.mustBeOfflineToEdit),
          backgroundColor: Colors.orange,
        ),
      );
      return;
    }

    final confirmController = TextEditingController();

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDialogState) => AlertDialog(
          title: Text(l10n.editKycConfirmTitle),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(l10n.editKycConfirmMessage),
              const SizedBox(height: 16),
              TextField(
                controller: confirmController,
                decoration: InputDecoration(
                  hintText: l10n.typeWarjoToConfirm,
                  border: const OutlineInputBorder(),
                ),
                onChanged: (_) => setDialogState(() {}),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(ctx).pop(),
              child: Text(l10n.cancel),
            ),
            FilledButton(
              onPressed:
                  confirmController.text.toUpperCase() == 'WARJO'
                      ? () async {
                          Navigator.of(ctx).pop();
                          await _executeEdit();
                        }
                      : null,
              style: FilledButton.styleFrom(
                backgroundColor: Colors.orange,
              ),
              child: Text(l10n.editKycButton),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _executeEdit() async {
    final l10n = AppLocalizations.of(context);

    final success = await ref.read(kycStateProvider.notifier).revokeKyc();

    if (!mounted) return;

    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(l10n.editKycSuccess),
          backgroundColor: Colors.green,
        ),
      );

      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const KycFormScreen()),
        (route) => false,
      );
    } else {
      final error = ref.read(kycStateProvider).error;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(error ?? l10n.failedToEditKyc),
          backgroundColor: Colors.red,
        ),
      );
    }
  }
}

class _StatusBadge extends StatelessWidget {
  final KycSubmission kyc;

  const _StatusBadge({required this.kyc});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final config = AppConfig.instance;

    IconData icon;
    Color color;
    String label;

    if (kyc.suspendReason != null) {
      icon = Icons.block;
      color = Colors.red;
      label = l10n.kycSuspended;
    } else if (kyc.isVerified) {
      icon = Icons.verified;
      color = config.primaryColor;
      label = l10n.kycVerified;
    } else if (kyc.emailVerified) {
      icon = Icons.hourglass_top;
      color = Colors.orange;
      label = l10n.kycPendingApproval;
    } else if (kyc.kycSubmitted) {
      icon = Icons.email_outlined;
      color = Colors.blue;
      label = l10n.kycEmailPending;
    } else {
      icon = Icons.pending_outlined;
      color = Colors.grey;
      label = l10n.kycNotSubmitted;
    }

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Row(
          children: [
            Icon(icon, size: 40, color: color),
            const SizedBox(width: 16),
            Text(label,
                style: TextStyle(
                    fontSize: 18, fontWeight: FontWeight.bold, color: color)),
          ],
        ),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final String label;
  final String value;

  const _InfoRow({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 130,
            child: Text(label,
                style: TextStyle(color: Colors.grey[600], fontSize: 13)),
          ),
          Expanded(
            child: Text(value,
                style: const TextStyle(fontWeight: FontWeight.w600)),
          ),
        ],
      ),
    );
  }
}
