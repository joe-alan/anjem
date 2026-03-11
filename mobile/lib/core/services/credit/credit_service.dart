import 'package:dio/dio.dart';
import '../api/api_service.dart';
import '../api/api_exception.dart';
import '../../models/credit_transaction.dart';

class CreditService {
  final ApiService _apiService;

  CreditService({required ApiService apiService}) : _apiService = apiService;

  Future<int> getBalance() async {
    try {
      final response = await _apiService.get('/driver/credits/balance');
      final data = response.data['data'];
      if (data == null || data['balance'] == null) return 0;
      return (data['balance'] as num).toInt();
    } on DioException catch (e) {
      throw ApiException.fromDioError(e);
    }
  }

  Future<List<CreditTransaction>> getTransactions() async {
    try {
      final response = await _apiService.get('/driver/credits/transactions');
      final raw = response.data['data'];
      if (raw is! List) return [];
      return raw
          .map((e) => CreditTransaction.fromJson(e as Map<String, dynamic>))
          .toList();
    } on DioException catch (e) {
      throw ApiException.fromDioError(e);
    }
  }
}
