import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile/l10n/app_localizations.dart';
import 'config/app_config.dart';
import 'navigation/navigator_key.dart';
import 'providers/auth_provider.dart';
import 'providers/fcm_provider.dart';
import 'providers/kyc_provider.dart';
import 'providers/locale_provider.dart';
import 'models/kyc_submission.dart';
import 'widgets/splash_screen.dart';
import 'widgets/login_screen.dart';
import 'widgets/session_check_wrapper.dart';
import '../rider/screens/rider_home_screen.dart';
import '../driver/screens/driver_home_screen.dart';
import '../driver/screens/kyc_form_screen.dart';
import '../driver/screens/email_verification_screen.dart';

class AnjerApp extends ConsumerWidget {
  const AnjerApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final config = AppConfig.instance;

    return MaterialApp(
      title: config.appName,
      debugShowCheckedModeBanner: false,
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: AppLocalizations.supportedLocales,
      locale: ref.watch(localeProvider),
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: config.primaryColor,
          brightness: Brightness.light,
        ),
        useMaterial3: true,
        appBarTheme: const AppBarTheme(
          elevation: 0,
          centerTitle: true,
        ),
        elevatedButtonTheme: ElevatedButtonThemeData(
          style: ElevatedButton.styleFrom(
            minimumSize: const Size.fromHeight(50),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
          ),
        ),
      ),
      navigatorKey: navigatorKey,
      home: const FcmInitializer(),
    );
  }
}

class AuthenticationWrapper extends ConsumerWidget {
  const AuthenticationWrapper({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authStateProvider);
    final kycState = ref.watch(kycStateProvider);
    final config = AppConfig.instance;

    print('AuthWrapper: Building - isDriverApp=${config.isDriverApp}');
    print('AuthWrapper: authState - isAuthenticated=${authState.isAuthenticated}, isLoading=${authState.isLoading}');
    print('AuthWrapper: kycState - isLoading=${kycState.isLoading}, kycSubmission=${kycState.kycSubmission}, isVerified=${kycState.kycSubmission?.isVerified}');

    // Show splash screen while checking authentication
    if (authState.isLoading) {
      print('AuthWrapper: Showing splash - auth loading');
      return const SplashScreen();
    }

    // Show login screen if not authenticated
    if (!authState.isAuthenticated) {
      print('AuthWrapper: Showing login - not authenticated');
      return const LoginScreen();
    }

    // For driver app, check KYC status
    if (config.isDriverApp) {
      // Still loading KYC status
      if (kycState.isLoading && kycState.kycSubmission == null) {
        print('AuthWrapper: Showing splash - KYC loading');
        return const SplashScreen();
      }

      // KYC fetch failed (e.g. no server connection) — do NOT fall through to
      // KycFormScreen, which would incorrectly send a verified driver to
      // re-submit their KYC. Show a retry screen instead.
      if (kycState.error != null && kycState.kycSubmission == null) {
        print('AuthWrapper: KYC load failed - showing retry: ${kycState.error}');
        return _KycLoadErrorScreen(error: kycState.error!);
      }

      // Route based on the four KYC states so session resume always lands
      // the driver at the right screen without losing their progress.
      final kycSubmission = kycState.kycSubmission;
      final kycStatus = kycSubmission?.status;
      print('AuthWrapper: kycStatus=$kycStatus');

      switch (kycStatus) {
        // Not submitted yet — show the full KYC form.
        case null:
        case KycStatus.notSubmitted:
          print('AuthWrapper: Showing KYC form');
          return const KycFormScreen();

        // KYC submitted but student email not yet verified — resume at the
        // email verification screen using the stored student email.
        case KycStatus.submitted:
          final email = kycSubmission!.studentEmail;
          if (email != null) {
            print('AuthWrapper: Resuming email verification for $email');
            return EmailVerificationScreen(studentEmail: email);
          }
          // Fallback: email missing in profile, restart the form.
          return const KycFormScreen();

        // Email verified (awaiting admin) or fully approved — go to home.
        // The home screen handles displaying unverified status.
        case KycStatus.emailVerified:
        case KycStatus.verified:
          print('AuthWrapper: Showing driver home - verified');
          return const SessionCheckWrapper(
            defaultHomeScreen: DriverHomeScreen(),
          );
      }
    }

    // For rider app, check for active session
    print('AuthWrapper: Showing rider home');
    return const SessionCheckWrapper(
      defaultHomeScreen: RiderHomeScreen(),
    );
  }
}

/// Initializes FCM when the user authenticates, cleans up on sign-out,
/// and handles terminated-state notification taps once auth completes.
class FcmInitializer extends ConsumerStatefulWidget {
  const FcmInitializer({super.key});

  @override
  ConsumerState<FcmInitializer> createState() => _FcmInitializerState();
}

class _FcmInitializerState extends ConsumerState<FcmInitializer> {
  bool _fcmInitialized = false;

  @override
  Widget build(BuildContext context) {
    ref.listen<AuthState>(authStateProvider, (previous, next) async {
      final fcm = ref.read(fcmServiceProvider);

      if (!_fcmInitialized && next.isAuthenticated) {
        _fcmInitialized = true;
        await fcm.initialize();
        await fcm.checkInitialMessage();
      }

      if ((previous?.isAuthenticated ?? false) && !next.isAuthenticated) {
        _fcmInitialized = false;
        await fcm.deleteToken();
      }
    });

    return const AuthenticationWrapper();
  }
}

/// Shown when the KYC status fetch fails (e.g. server unreachable).
/// Prevents a verified driver from being mistakenly routed to KycFormScreen.
class _KycLoadErrorScreen extends ConsumerWidget {
  final String error;

  const _KycLoadErrorScreen({required this.error});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(32.0),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.cloud_off, size: 64, color: Colors.grey),
              const SizedBox(height: 16),
              const Text(
                'Unable to connect',
                style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              Text(
                'Could not reach the server. Please check your connection and try again.',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.grey[600]),
              ),
              const SizedBox(height: 24),
              ElevatedButton.icon(
                onPressed: () {
                  ref.read(kycStateProvider.notifier).refreshKycStatus();
                },
                icon: const Icon(Icons.refresh),
                label: const Text('Retry'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}