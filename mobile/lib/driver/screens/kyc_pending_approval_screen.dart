import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/models/kyc_submission.dart';
import '../../core/providers/kyc_provider.dart';

class KycPendingApprovalScreen extends ConsumerStatefulWidget {
  const KycPendingApprovalScreen({super.key});

  @override
  ConsumerState<KycPendingApprovalScreen> createState() =>
      _KycPendingApprovalScreenState();
}

class _KycPendingApprovalScreenState
    extends ConsumerState<KycPendingApprovalScreen> {
  Timer? _pollTimer;

  @override
  void initState() {
    super.initState();
    // Poll every 30 seconds so the AuthenticationWrapper picks up the approval
    // without the driver having to manually tap "Check Status".
    _pollTimer = Timer.periodic(const Duration(seconds: 30), (_) {
      if (mounted) {
        ref.read(kycStateProvider.notifier).refreshKycStatus();
      }
    });
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    super.dispose();
  }

  void _checkNow() {
    ref.read(kycStateProvider.notifier).refreshKycStatus();
  }

  @override
  Widget build(BuildContext context) {
    final kycState = ref.watch(kycStateProvider);
    final submission = kycState.kycSubmission;

    // The AuthenticationWrapper watches kycStateProvider and will automatically
    // swap this screen for SessionCheckWrapper once isVerified becomes true.
    // The PopScope below prevents the driver from navigating backwards.

    return PopScope(
      canPop: false,
      child: Scaffold(
        body: SafeArea(
          child: Center(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 32.0),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 96,
                    height: 96,
                    decoration: BoxDecoration(
                      color: Colors.orange.shade50,
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      Icons.hourglass_empty_rounded,
                      size: 52,
                      color: Colors.orange.shade700,
                    ),
                  ),
                  const SizedBox(height: 24),
                  const Text(
                    'Pending Admin Review',
                    style: TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'Your KYC documents have been submitted. Our team will review your application and notify you once approved.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 15,
                      color: Colors.grey.shade600,
                      height: 1.5,
                    ),
                  ),
                  if (submission != null) ...[
                    const SizedBox(height: 28),
                    _buildInfoCard(submission),
                  ],
                  const SizedBox(height: 32),
                  if (kycState.isLoading)
                    const CircularProgressIndicator()
                  else
                    OutlinedButton.icon(
                      onPressed: _checkNow,
                      icon: const Icon(Icons.refresh),
                      label: const Text('Check Status'),
                    ),
                  if (kycState.error != null) ...[
                    const SizedBox(height: 12),
                    Text(
                      kycState.error!,
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: Colors.red.shade700,
                        fontSize: 13,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildInfoCard(KycSubmission submission) {
    final rows = <({String label, String value})>[
      if (submission.studentName != null)
        (label: 'Name', value: submission.studentName!),
      if (submission.studentEmail != null)
        (label: 'Student email', value: submission.studentEmail!),
      if (submission.studentId != null)
        (label: 'Student ID', value: submission.studentId!),
      if (submission.vehicleType != null)
        (label: 'Vehicle', value: submission.vehicleType!),
    ];

    if (rows.isEmpty) return const SizedBox.shrink();

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.grey.shade50,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: rows.map((row) {
          return Padding(
            padding: const EdgeInsets.symmetric(vertical: 3),
            child: Row(
              children: [
                SizedBox(
                  width: 110,
                  child: Text(
                    row.label,
                    style: TextStyle(
                      color: Colors.grey.shade600,
                      fontSize: 13,
                    ),
                  ),
                ),
                Expanded(
                  child: Text(
                    row.value,
                    style: const TextStyle(
                      fontWeight: FontWeight.w500,
                      fontSize: 13,
                    ),
                  ),
                ),
              ],
            ),
          );
        }).toList(),
      ),
    );
  }
}
