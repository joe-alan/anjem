import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile/l10n/app_localizations.dart';
import '../../core/providers/kyc_provider.dart';
import '../../core/config/app_config.dart';
import 'driver_home_screen.dart';

// Paste handler for verification code
class PasteTextInputFormatter extends TextInputFormatter {
  final List<TextEditingController> controllers;
  final List<FocusNode> focusNodes;
  final int currentIndex;
  final VoidCallback? onAllDigitsFilled;

  PasteTextInputFormatter({
    required this.controllers,
    required this.focusNodes,
    required this.currentIndex,
    this.onAllDigitsFilled,
  });

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    // Detect paste operation (newValue has multiple characters)
    if (newValue.text.length > 1) {
      _handlePaste(newValue.text);
      return oldValue; // Keep old value since we handle paste manually
    }
    return newValue;
  }

  void _handlePaste(String pastedText) {
    // Remove non-digits
    final digits = pastedText.replaceAll(RegExp(r'[^0-9]'), '');

    // Distribute digits across boxes
    for (int i = 0; i < digits.length && i < controllers.length; i++) {
      controllers[i].text = digits[i];
    }

    // Focus on the next empty box or last box
    final nextIndex = digits.length < controllers.length
        ? digits.length
        : controllers.length - 1;
    if (nextIndex < focusNodes.length) {
      focusNodes[nextIndex].requestFocus();
    }

    // Auto-verify if all 6 digits are filled
    if (digits.length >= controllers.length) {
      focusNodes.last.unfocus();
      onAllDigitsFilled?.call();
    }
  }
}

class EmailVerificationScreen extends ConsumerStatefulWidget {
  final String studentEmail;

  const EmailVerificationScreen({
    super.key,
    required this.studentEmail,
  });

  @override
  ConsumerState<EmailVerificationScreen> createState() =>
      _EmailVerificationScreenState();
}

class _EmailVerificationScreenState
    extends ConsumerState<EmailVerificationScreen> {
  final List<TextEditingController> _codeControllers = List.generate(
    6,
    (_) => TextEditingController(),
  );

  final List<FocusNode> _focusNodes = List.generate(
    6,
    (_) => FocusNode(),
  );

  bool _codeSent = false;
  int _resendCountdown = 0;
  bool _initialLoading = true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _initCooldownAndSend();
    });
  }

  Future<void> _initCooldownAndSend() async {
    try {
      // Check if a cooldown is already active (e.g. user force-quit and reopened)
      final remaining = await ref
          .read(kycStateProvider.notifier)
          .getResendCooldown(widget.studentEmail);

      if (!mounted) return;

      if (remaining > 0) {
        // Code was already sent recently — just resume the countdown
        setState(() {
          _codeSent = true;
          _resendCountdown = remaining;
          _initialLoading = false;
        });
        _startCountdown();
      } else {
        setState(() => _initialLoading = false);
        _sendVerificationCode();
      }
    } catch (_) {
      if (mounted) {
        setState(() => _initialLoading = false);
        _sendVerificationCode();
      }
    }
  }

  @override
  void dispose() {
    for (var controller in _codeControllers) {
      controller.dispose();
    }
    for (var node in _focusNodes) {
      node.dispose();
    }
    super.dispose();
  }

  Future<void> _sendVerificationCode() async {
    final cooldown = await ref
        .read(kycStateProvider.notifier)
        .sendVerificationCode(widget.studentEmail);

    if (cooldown > 0 && mounted) {
      setState(() {
        _codeSent = true;
        _resendCountdown = cooldown;
      });
      _startCountdown();
    }
  }

  void _startCountdown() {
    Future.delayed(const Duration(seconds: 1), () {
      if (mounted && _resendCountdown > 0) {
        setState(() {
          _resendCountdown--;
        });
        _startCountdown();
      }
    });
  }

  String _getCode() {
    return _codeControllers.map((c) => c.text).join();
  }

  Future<void> _verifyCode() async {
    final l10n = AppLocalizations.of(context);
    final code = _getCode();

    if (code.length != 6) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(l10n.pleaseEnterCode),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    final success = await ref.read(kycStateProvider.notifier).verifyEmail(
          studentEmail: widget.studentEmail,
          code: code,
        );

    if (success && mounted) {
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(
          builder: (context) => const DriverHomeScreen(),
        ),
        (route) => false,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;
    final kycState = ref.watch(kycStateProvider);
    final l10n = AppLocalizations.of(context);

    // Show error and success messages
    ref.listen<KycState>(kycStateProvider, (previous, next) {
      if (next.error != null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(next.error!),
            backgroundColor: Colors.red,
            action: SnackBarAction(
              label: l10n.dismiss,
              textColor: Colors.white,
              onPressed: () {
                if (mounted) ref.read(kycStateProvider.notifier).clearError();
              },
            ),
          ),
        );
      }

      if (next.successMessage != null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(next.successMessage!),
            backgroundColor: Colors.green,
            action: SnackBarAction(
              label: l10n.dismiss,
              textColor: Colors.white,
              onPressed: () {
                if (mounted) ref.read(kycStateProvider.notifier).clearSuccessMessage();
              },
            ),
          ),
        );
      }
    });

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.emailVerificationTitle),
        backgroundColor: config.primaryColor,
        foregroundColor: Colors.white,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            const SizedBox(height: 32),

            // Email icon
            Container(
              width: 100,
              height: 100,
              decoration: BoxDecoration(
                color: config.primaryColor.withOpacity(0.1),
                shape: BoxShape.circle,
              ),
              child: Icon(
                Icons.email_outlined,
                size: 50,
                color: config.primaryColor,
              ),
            ),

            const SizedBox(height: 32),

            // Title
            Text(
              l10n.verifyYourEmailHeading,
              style: const TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.bold,
              ),
              textAlign: TextAlign.center,
            ),

            const SizedBox(height: 16),

            // Description
            Text(
              l10n.verificationCodeSentTo,
              style: TextStyle(
                fontSize: 16,
                color: Colors.grey[600],
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 8),
            Text(
              widget.studentEmail,
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w600,
                color: config.primaryColor,
              ),
              textAlign: TextAlign.center,
            ),

            const SizedBox(height: 48),

            // Code input boxes
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceEvenly,
              children: List.generate(
                6,
                (index) => _buildCodeBox(index),
              ),
            ),

            const SizedBox(height: 48),

            // Verify button
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: kycState.isLoading ? null : _verifyCode,
                style: ElevatedButton.styleFrom(
                  backgroundColor: config.primaryColor,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                ),
                child: kycState.isLoading
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          valueColor:
                              AlwaysStoppedAnimation<Color>(Colors.white),
                        ),
                      )
                    : Text(
                        l10n.verifyEmailButton,
                        style: const TextStyle(fontSize: 16),
                      ),
              ),
            ),

            const SizedBox(height: 24),

            // Resend code
            if (_codeSent)
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    l10n.didntReceiveCode,
                    style: TextStyle(
                      fontSize: 14,
                      color: Colors.grey[600],
                    ),
                  ),
                  if (_resendCountdown > 0)
                    Text(
                      l10n.resendCountdown(_resendCountdown),
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                        color: Colors.grey[400],
                      ),
                    )
                  else
                    GestureDetector(
                      onTap: (kycState.isLoading || _initialLoading)
                          ? null
                          : _sendVerificationCode,
                      child: Text(
                        l10n.resendCode,
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                          color: (kycState.isLoading || _initialLoading)
                              ? Colors.grey[400]
                              : config.primaryColor,
                          decoration: TextDecoration.underline,
                        ),
                      ),
                    ),
                ],
              ),

            const SizedBox(height: 32),

            // Info box
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.blue[50],
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.blue[200]!),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.info_outline, color: Colors.blue[700]),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      l10n.verificationCodeExpiry,
                      style: TextStyle(
                        fontSize: 14,
                        color: Colors.blue[700],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCodeBox(int index) {
    final config = AppConfig.instance;

    return SizedBox(
      width: 45,
      child: TextField(
        controller: _codeControllers[index],
        focusNode: _focusNodes[index],
        textAlign: TextAlign.center,
        keyboardType: TextInputType.number,
        maxLength: 1,
        style: const TextStyle(
          fontSize: 24,
          fontWeight: FontWeight.bold,
        ),
        decoration: InputDecoration(
          counterText: '',
          enabledBorder: OutlineInputBorder(
            borderSide: BorderSide(color: Colors.grey[300]!, width: 2),
            borderRadius: BorderRadius.circular(8),
          ),
          focusedBorder: OutlineInputBorder(
            borderSide: BorderSide(color: config.primaryColor, width: 2),
            borderRadius: BorderRadius.circular(8),
          ),
        ),
        inputFormatters: [
          PasteTextInputFormatter(
            controllers: _codeControllers,
            focusNodes: _focusNodes,
            currentIndex: index,
            onAllDigitsFilled: _verifyCode,
          ),
          FilteringTextInputFormatter.digitsOnly,
        ],
        onChanged: (value) {
          if (value.isNotEmpty) {
            // Move to next field
            if (index < 5) {
              _focusNodes[index + 1].requestFocus();
            } else {
              // Last field — dismiss keyboard and auto-verify
              _focusNodes[index].unfocus();
              final code = _getCode();
              if (code.length == 6) {
                _verifyCode();
              }
            }
          } else {
            // Move to previous field on backspace
            if (index > 0) {
              _focusNodes[index - 1].requestFocus();
            }
          }
        },
      ),
    );
  }
}
