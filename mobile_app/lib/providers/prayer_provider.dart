import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../models/prayer_request_model.dart';
import 'package:dio/dio.dart';

class PrayerProvider with ChangeNotifier {
  final ApiService _apiService = ApiService();
  
  List<PrayerRequestModel> _requests = [];
  bool _isLoading = false;
  String? _error;

  List<PrayerRequestModel> get requests => _requests;
  bool get isLoading => _isLoading;
  String? get error => _error;

  Future<void> fetchUserRequests() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _apiService.get('/prayer-requests/my');
      final bool isSuccess = response.data['success'] == true || response.statusCode == 200;
      
      if (isSuccess) {
        final List<dynamic> data = response.data['data'] ?? [];
        _requests = data.map((json) => PrayerRequestModel.fromJson(json)).toList();
      }
    } catch (e) {
      _error = 'Failed to load prayer requests';
      print('Load prayer requests error: $e');
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<bool> submitRequest(Map<String, dynamic> prayerData) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      dynamic payload;
      
      // If there's an image path, send as multipart FormData
      if (prayerData.containsKey('attachment_path') && prayerData['attachment_path'] != null) {
        final formData = FormData.fromMap({
          'full_name': prayerData['full_name'],
          'email': prayerData['email'],
          'city': prayerData['city'],
          'country': prayerData['country'],
          'phone': prayerData['phone'] ?? '',
          'category': prayerData['category'],
          'request_text': prayerData['request_text'],
          'attachment': await MultipartFile.fromFile(
            prayerData['attachment_path'],
            filename: prayerData['attachment_name'] ?? 'attachment.jpg',
          ),
        });
        payload = formData;
      } else {
        // No attachment — send as plain JSON
        payload = {
          'full_name': prayerData['full_name'],
          'email': prayerData['email'],
          'city': prayerData['city'],
          'country': prayerData['country'],
          'phone': prayerData['phone'] ?? '',
          'category': prayerData['category'],
          'request_text': prayerData['request_text'],
        };
      }

      final response = await _apiService.post('/prayer-requests', data: payload);
      final bool isSuccess = response.data['success'] == true || response.statusCode == 200 || response.statusCode == 201;
      
      if (isSuccess) {
        await fetchUserRequests();
      }
      
      _isLoading = false;
      notifyListeners();
      return isSuccess;
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
    return 'Failed to submit prayer request. Please try again.';
  }
}
