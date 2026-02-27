import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../services/api/api_service.dart';

final apiServiceProvider = Provider<ApiService>((ref) {
  // onUnauthorized is set by authStateProvider after it creates its notifier,
  // to avoid a circular import (auth_provider already imports api_provider).
  return ApiService();
});
