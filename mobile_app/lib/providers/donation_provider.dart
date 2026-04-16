import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../models/donation_model.dart';
import 'package:dio/dio.dart';

class DonationProvider with ChangeNotifier {
  final ApiService _apiService = ApiService();
  
  List<DonationCampaign> _campaigns = [];
  Map<String, dynamic> _paymentSettings = {};
  bool _isLoading = false;
  String? _error;

  List<DonationCampaign> get campaigns => _campaigns;
  Map<String, dynamic> get paymentSettings => _paymentSettings;
  bool get isLoading => _isLoading;
  String? get error => _error;

  Future<void> loadCampaigns() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _apiService.get('/donations/campaigns');
      if (response.data != null && response.data['success'] == true) {
        final List<dynamic> data = response.data['data']['campaigns'] ?? [];
        _campaigns = data.map((json) => DonationCampaign.fromJson(json)).toList();
      }
    } catch (e) {
      _error = 'Failed to load campaigns';
      print('Load campaigns error: $e');
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> loadPaymentSettings() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _apiService.get('/donations/settings');
      if (response.data != null && response.data['success'] == true) {
        _paymentSettings = response.data['data'] ?? {};
      }
    } catch (e) {
      _error = 'Failed to load payment settings';
      print('Load settings error: $e');
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<bool> submitDonation(Map<String, dynamic> donationData) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _apiService.post('/donations', data: donationData);
      _isLoading = false;
      notifyListeners();
      return response.data['success'] == true;
    } catch (e) {
      _error = _handleError(e);
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<bool> reportManualTransfer(Map<String, dynamic> data, {dynamic receiptFile}) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      FormData formData = FormData.fromMap(data);
      
      if (receiptFile != null) {
        String fileName = receiptFile.path.split('/').last;
        formData.files.add(MapEntry(
          'receipt',
          await MultipartFile.fromFile(receiptFile.path, filename: fileName),
        ));
      }

      final response = await _apiService.post('/donations/report-transfer', data: formData);
      _isLoading = false;
      notifyListeners();
      return response.data['success'] == true;
    } catch (e) {
      _error = _handleError(e);
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  String _handleError(dynamic e) {
    if (e is DioException && e.response?.data != null) {
      final resData = e.response!.data;
      if (resData is Map && resData['message'] != null) {
        return resData['message'].toString();
      }
    }
    return 'Operation failed. Please try again.';
  }
}
