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
      return (response.data['data']['balance'] as num).toInt();
    } on DioException catch (e) {
      throw ApiException.fromDioError(e);
    }
  }

  Future<List<CreditTransaction>> getTransactions() async {
    try {
      final response = await _apiService.get('/driver/credits/transactions');
      final list = response.data['data'] as List<dynamic>;
      return list
          .map((e) => CreditTransaction.fromJson(e as Map<String, dynamic>))
          .toList();
    } on DioException catch (e) {
      throw ApiException.fromDioError(e);
    }
  }
}
