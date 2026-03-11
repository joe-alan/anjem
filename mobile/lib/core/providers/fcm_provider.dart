import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../services/fcm/fcm_service.dart';
import 'auth_provider.dart';

final fcmServiceProvider = Provider<FcmService>((ref) {
  final authService = ref.watch(authServiceProvider);
  return FcmService(authService: authService);
});
