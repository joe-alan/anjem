import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/providers/kyc_provider.dart';
import '../../core/config/app_config.dart';
import 'driver_home_screen.dart';

// Paste handler for verification code
class PasteTextInputFormatter extends TextInputFormatter {
  final List<TextEditingController> controllers;
  final List<FocusNode> focusNodes;
  final int currentIndex;

  PasteTextInputFormatter({
    required this.controllers,
    required this.focusNodes,
    required this.currentIndex,
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

  @override
  void initState() {
    super.initState();
    // Automatically send code when screen loads
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _sendVerificationCode();
    });
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
    final success = await ref
        .read(kycStateProvider.notifier)
        .sendVerificationCode(widget.studentEmail);

    if (success) {
      setState(() {
        _codeSent = true;
        _resendCountdown = 60;
      });

      // Start countdown
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
    final code = _getCode();

    if (code.length != 6) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please enter the 6-digit code'),
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

    // Show error and success messages
    ref.listen<KycState>(kycStateProvider, (previous, next) {
      if (next.error != null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(next.error!),
            backgroundColor: Colors.red,
            action: SnackBarAction(
              label: 'Dismiss',
              textColor: Colors.white,
              onPressed: () {
                ref.read(kycStateProvider.notifier).clearError();
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
              label: 'Dismiss',
              textColor: Colors.white,
              onPressed: () {
                ref.read(kycStateProvider.notifier).clearSuccessMessage();
              },
            ),
          ),
        );
      }
    });

    return Scaffold(
      appBar: AppBar(
        title: const Text('Email Verification'),
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
            const Text(
              'Verify Your Email',
              style: TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.bold,
              ),
              textAlign: TextAlign.center,
            ),

            const SizedBox(height: 16),

            // Description
            Text(
              'We\'ve sent a 6-digit verification code to',
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
                    : const Text(
                        'Verify Email',
                        style: TextStyle(fontSize: 16),
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
                    'Didn\'t receive the code? ',
                    style: TextStyle(
                      fontSize: 14,
                      color: Colors.grey[600],
                    ),
                  ),
                  if (_resendCountdown > 0)
                    Text(
                      'Resend in ${_resendCountdown}s',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                        color: Colors.grey[400],
                      ),
                    )
                  else
                    GestureDetector(
                      onTap: kycState.isLoading ? null : _sendVerificationCode,
                      child: Text(
                        'Resend Code',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                          color: config.primaryColor,
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
                      'The verification code expires in 10 minutes. Please check your spam folder if you don\'t see the email.',
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
          ),
          FilteringTextInputFormatter.digitsOnly,
        ],
        onChanged: (value) {
          if (value.isNotEmpty) {
            // Move to next field
            if (index < 5) {
              _focusNodes[index + 1].requestFocus();
            } else {
              // Last field, dismiss keyboard
              _focusNodes[index].unfocus();
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
