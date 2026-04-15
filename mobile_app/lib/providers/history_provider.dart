import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../models/video_model.dart';
import 'package:dio/dio.dart';

class HistoryProvider with ChangeNotifier {
  final ApiService _apiService = ApiService();
  List<VideoModel> _history = [];
  bool _isLoading = false;
  String? _error;

  List<VideoModel> get history => _history;
  bool get isLoading => _isLoading;
  String? get error => _error;
  int get historyCount => _history.length;

  Future<void> fetchHistory() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _apiService.get('/users/history');
      final bool isSuccess = response.data['success'] == true || response.data['status'] == 'success';
      
      if (isSuccess) {
        final dynamic data = response.data['data'];
        if (data is List) {
          _history = data.map((v) => VideoModel.fromJson(v)).toList();
        } else {
          _history = [];
        }
      }
    } catch (e) {
      _error = 'Failed to load watch history';
      print('fetchHistory error: $e');
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> addToHistory(int videoId) async {
    try {
      final response = await _apiService.post('/users/history', data: {'video_id': videoId});
      final bool isSuccess = response.data['success'] == true || response.data['status'] == 'success';
      
      if (isSuccess) {
        // Refresh history to update the local list and counter
        await fetchHistory();
      }
    } catch (e) {
      print('addToHistory error: $e');
    }
  }

  Future<void> removeFromHistory(int videoId) async {
    try {
      await _apiService.delete('/users/history/$videoId');
      _history.removeWhere((v) => v.id == videoId);
      notifyListeners();
    } catch (e) {
      print('removeFromHistory error: $e');
    }
  }

  Future<void> clearHistory() async {
    try {
      await _apiService.delete('/users/history');
      _history.clear();
      notifyListeners();
    } catch (e) {
      print('clearHistory error: $e');
    }
  }
}
