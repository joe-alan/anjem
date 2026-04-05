import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:mobile/l10n/app_localizations.dart';
import 'package:url_launcher/url_launcher.dart';
import '../config/app_config.dart';
import '../providers/auth_provider.dart';

class LoginScreen extends ConsumerWidget {
  const LoginScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final config = AppConfig.instance;
    final authState = ref.watch(authStateProvider);
    final l10n = AppLocalizations.of(context);

    // Show error if any
    if (authState.error != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(authState.error!),
            backgroundColor: Colors.red,
            action: SnackBarAction(
              label: l10n.dismiss,
              textColor: Colors.white,
              onPressed: () {
                ref.read(authStateProvider.notifier).clearError();
              },
            ),
          ),
        );
      });
    }

    return Scaffold(
      body: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              config.primaryColor,
              config.primaryColor.withOpacity(0.7),
            ],
          ),
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(24.0),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Spacer(),

                // App Logo
                SvgPicture.asset(
                  'assets/images/logo.svg',
                  width: 120,
                  height: 120,
                  colorFilter: const ColorFilter.mode(
                    Colors.white,
                    BlendMode.srcIn,
                  ),
                ),
                const SizedBox(height: 24),

                // App Name
                Text(
                  config.appName,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 36,
                    fontWeight: FontWeight.bold,
                    color: Colors.white,
                  ),
                ),
                const SizedBox(height: 16),

                // Tagline
                Text(
                  config.isRiderApp ? l10n.riderTagline : l10n.driverTagline,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 18,
                    color: Colors.white.withOpacity(0.9),
                  ),
                ),

                const Spacer(),

                // Sign In Button
                ElevatedButton.icon(
                  onPressed: authState.isLoading
                      ? null
                      : () {
                          ref
                              .read(authStateProvider.notifier)
                              .signInWithGoogle();
                        },
                  icon: authState.isLoading
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            valueColor:
                                AlwaysStoppedAnimation<Color>(Colors.grey),
                          ),
                        )
                      : Image.asset(
                          'assets/images/google_logo.png',
                          height: 24,
                          errorBuilder: (context, error, stackTrace) {
                            return const Icon(Icons.login, size: 24);
                          },
                        ),
                  label: Text(
                    authState.isLoading ? l10n.signingIn : l10n.signInWithGoogle,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.white,
                    foregroundColor: Colors.black87,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                  ),
                ),

                const SizedBox(height: 24),

                // Terms and Privacy
                Text.rich(
                  TextSpan(
                    style: TextStyle(
                      fontSize: 12,
                      color: Colors.white.withOpacity(0.7),
                    ),
                    children: [
                      TextSpan(text: l10n.termsNoticePrefix),
                      TextSpan(
                        text: l10n.termsAndConditions,
                        style: const TextStyle(
                          decoration: TextDecoration.underline,
                          color: Colors.white,
                        ),
                        recognizer: TapGestureRecognizer()
                          ..onTap = () => launchUrl(
                                Uri.parse('https://www.anjem.me/syarat-dan-ketentuan/'),
                                mode: LaunchMode.externalApplication,
                              ),
                      ),
                      TextSpan(text: l10n.termsNoticeConnector),
                      TextSpan(
                        text: l10n.privacyPolicy,
                        style: const TextStyle(
                          decoration: TextDecoration.underline,
                          color: Colors.white,
                        ),
                        recognizer: TapGestureRecognizer()
                          ..onTap = () => launchUrl(
                                Uri.parse('https://www.anjem.me/kebijakan-privasi'),
                                mode: LaunchMode.externalApplication,
                              ),
                      ),
                      TextSpan(text: l10n.termsNoticeSuffix),
                    ],
                  ),
                  textAlign: TextAlign.center,
                ),

                const SizedBox(height: 48),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
