import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'api_provider.dart';
import '../services/credit/credit_service.dart';
import '../models/credit_transaction.dart';

final creditServiceProvider = Provider<CreditService>((ref) {
  return CreditService(apiService: ref.read(apiServiceProvider));
});

// Balance provider
class CreditsNotifier extends AutoDisposeAsyncNotifier<int> {
  @override
  Future<int> build() => _fetch();

  Future<int> _fetch() async {
    final svc = ref.read(creditServiceProvider);
    return svc.getBalance();
  }

  Future<void> refresh() async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(_fetch);
  }
}

final creditsProvider =
    AutoDisposeAsyncNotifierProvider<CreditsNotifier, int>(
  () => CreditsNotifier(),
);

// Transaction history provider
class CreditTransactionsNotifier
    extends AutoDisposeAsyncNotifier<List<CreditTransaction>> {
  @override
  Future<List<CreditTransaction>> build() => _fetch();

  Future<List<CreditTransaction>> _fetch() async {
    final svc = ref.read(creditServiceProvider);
    return svc.getTransactions();
  }

  Future<void> refresh() async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(_fetch);
  }
}

final creditTransactionsProvider = AutoDisposeAsyncNotifierProvider<
    CreditTransactionsNotifier, List<CreditTransaction>>(
  () => CreditTransactionsNotifier(),
);
