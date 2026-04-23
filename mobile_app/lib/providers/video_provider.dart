import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../services/api_service.dart';
import '../models/video_model.dart';
import '../models/category_model.dart';

class VideoProvider with ChangeNotifier {
  final ApiService _apiService = ApiService();

  List<VideoModel> _featuredVideos = [];
  List<VideoModel> _recentVideos = [];
  List<VideoModel> _favoriteVideos = []; // User's favorites
  List<CategoryModel> _categories = [];
  bool _isLoading = false;
  bool _isOffline = false;
  String? _error;

  List<VideoModel> get featuredVideos => _featuredVideos;
  List<VideoModel> get recentVideos => _recentVideos;
  List<VideoModel> get favoriteVideos => _favoriteVideos;
  List<CategoryModel> get categories => _categories;
  bool get isLoading => _isLoading;
  bool get isOffline => _isOffline;
  String? get error => _error;

  VideoProvider() {
    loadFavorites(); // Load user favorites on initialization
  }

  Future<void> loadHomeData() async {
    _isLoading = true;
    _isOffline = false;
    _error = null;
    notifyListeners();

    try {
      // 1. Load Categories first so they are available for the UI
      await loadCategories();

      // 2. Load Home Videos
      final response = await _apiService.get('/videos/home');
      
      // Handle production schema (success: true) or local schema (status: 'success')
      final bool isSuccess = response.data['success'] == true || response.data['status'] == 'success';
      
      if (isSuccess) {
        final dynamic data = response.data['data'];
        
        if (data is Map) {
          final List<dynamic> featured = data['featured'] ?? [];
          _featuredVideos = featured.map((v) => VideoModel.fromJson(v)).toList();

          final List<dynamic> recent = data['recent'] ?? [];
          _recentVideos = recent.map((v) => VideoModel.fromJson(v)).toList();
        } else if (data is List) {
          final List<VideoModel> allVideos = data.map((v) => VideoModel.fromJson(v)).toList();
          if (allVideos.isNotEmpty) {
            _featuredVideos = allVideos.take(5).toList();
            _recentVideos = allVideos.skip(5).toList();
          }
        }
        _saveToCache('home_data', data);
      }
    } catch (e) {
      print('Load home data error: $e');
      _isOffline = true;
      _error = 'Unable to connect to server. Showing offline content.';
      await _loadFromCache('home_data');
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  // --- Favorites Management ---

  bool isFavorite(int videoId) {
    return _favoriteVideos.any((v) => v.id == videoId);
  }

  Future<void> toggleFavorite(VideoModel video) async {
    final index = _favoriteVideos.indexWhere((v) => v.id == video.id);
    final isAdding = index < 0;

    if (isAdding) {
      _favoriteVideos.add(video);
    } else {
      _favoriteVideos.removeAt(index);
    }
    notifyListeners();
    
    // Sync with backend
    try {
      if (isAdding) {
        await _apiService.post('/users/favorites', data: {'video_id': video.id});
      } else {
        await _apiService.delete('/users/favorites', data: {'video_id': video.id});
      }
    } catch (e) {
      print('Sync favorite error: $e');
      // On error, we could revert local state, but usually better to just log
    }
    
    await _saveFavorites();
  }

  Future<void> _saveFavorites() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final String json = jsonEncode(_favoriteVideos.map((v) => v.toJson()).toList());
      await prefs.setString('favorite_videos', json);
    } catch (e) {
      print('Save favorites error: $e');
    }
  }

  Future<void> loadFavorites() async {
    // 1. Try to load from backend first if possible
    try {
      final response = await _apiService.get('/users/favorites');
      final bool isSuccess = response.data['success'] == true || response.data['status'] == 'success';
      if (isSuccess) {
        final List<dynamic> data = response.data['data'] ?? [];
        _favoriteVideos = data.map((v) => VideoModel.fromJson(v)).toList();
        notifyListeners();
        await _saveFavorites(); // Update cache
        return;
      }
    } catch (e) {
      print('Load favorites from backend error: $e');
    }

    // 2. Fallback to local cache
    try {
      final prefs = await SharedPreferences.getInstance();
      final String? json = prefs.getString('favorite_videos');
      if (json != null) {
        final List<dynamic> list = jsonDecode(json);
        _favoriteVideos = list.map((v) => VideoModel.fromJson(v)).toList();
        notifyListeners();
      }
    } catch (e) {
      print('Load favorites from cache error: $e');
    }
  }

  // --- Categories & Other Actions ---

  Future<void> loadCategories() async {
    try {
      final response = await _apiService.get('/categories');
      final bool isSuccess = response.data['success'] == true || response.data['status'] == 'success';
      if (isSuccess) {
        final List<dynamic> catData = response.data['data'] ?? [];
        _categories = catData.map((c) => CategoryModel.fromJson(c)).toList();
        _saveToCache('categories_data', catData);
      }
    } catch (e) {
      print('loadCategories error: $e');
      final prefs = await SharedPreferences.getInstance();
      final cached = prefs.getString('categories_data');
      if (cached != null) {
        final List<dynamic> catData = jsonDecode(cached);
        _categories = catData.map((c) => CategoryModel.fromJson(c)).toList();
      }
    }
  }

  Future<List<VideoModel>> searchVideos(String query) async {
    try {
      final response = await _apiService.get('/search', queryParameters: {'q': query});
      final bool isSuccess = response.data['success'] == true || response.data['status'] == 'success';
      if (isSuccess) {
        final dynamic data = response.data['data'];
        if (data is List) return data.map((v) => VideoModel.fromJson(v)).toList();
        if (data is Map && data.containsKey('videos')) return _parseVideos(data['videos']);
      }
      return [];
    } catch (e) {
      print('searchVideos error: $e');
      return [];
    }
  }

  Future<List<VideoModel>> fetchExclusiveVideos() async {
    try {
      final response = await _apiService.get('/videos/exclusive');
      final bool isSuccess = response.data['success'] == true || response.data['status'] == 'success';
      if (isSuccess) {
        final dynamic data = response.data['data'];
        if (data is List) return data.map((v) => VideoModel.fromJson(v)).toList();
      }
      return [];
    } catch (e) {
      print('fetchExclusiveVideos error: $e');
      return [];
    }
  }

  Future<void> _saveToCache(String key, dynamic data) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(key, jsonEncode(data));
    } catch (e) {
      print('Cache save error: $e');
    }
  }

  Future<void> _loadFromCache(String key) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final cachedString = prefs.getString(key);
      if (cachedString != null) {
        final data = jsonDecode(cachedString);
        if (data is Map) {
          _featuredVideos = (data['featured'] as List? ?? []).map((v) => VideoModel.fromJson(v)).toList();
          _recentVideos = (data['recent'] as List? ?? []).map((v) => VideoModel.fromJson(v)).toList();
        } else if (data is List) {
          final List<VideoModel> allVideos = data.map((v) => VideoModel.fromJson(v)).toList();
          _featuredVideos = allVideos.take(5).toList();
          _recentVideos = allVideos.skip(5).toList();
        }
      }
    } catch (e) {
      print('Cache load error: $e');
    }
  }

  List<VideoModel> _parseVideos(dynamic data) {
    if (data == null) return [];
    if (data is List) return data.map((v) => VideoModel.fromJson(v)).toList();
    return [];
  }

  Future<Map<String, dynamic>?> initiatePurchase(int videoId, {String currency = 'USD'}) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _apiService.post('/videos/purchase', data: {
        'video_id': videoId,
        'currency': currency
      });
      _isLoading = false;
      notifyListeners();
      return response.data['success'] == true ? response.data['data'] : null;
    } catch (e) {
      _isLoading = false;
      if (e is DioException && e.response?.data != null) {
        _error = e.response?.data['message'] ?? 'Server error during purchase';
      } else {
        _error = 'Network error: Failed to initiate purchase';
      }
      print('initiatePurchase Error: $e');
      notifyListeners();
      return null;
    }
  }

  Future<bool> verifyPurchase(String reference) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _apiService.post('/videos/verify-purchase', data: {'reference': reference});
      _isLoading = false;
      notifyListeners();
      return (response.data['success'] == true && response.data['data']['status'] == 'completed');
    } catch (e) {
      _isLoading = false;
      if (e is DioException && e.response?.data != null) {
        _error = e.response?.data['message'] ?? 'Verification failed';
      } else {
        _error = 'Network error: Failed to verify purchase';
      }
      print('verifyPurchase Error: $e');
      notifyListeners();
      return false;
    }
  }

  Future<VideoModel?> getVideoById(int id) async {
    try {
      final response = await _apiService.get('/videos/$id');
      final bool isSuccess = response.data['success'] == true || response.data['status'] == 'success';
      if (isSuccess) {
        return VideoModel.fromJson(response.data['data']);
      }
      return null;
    } catch (e) {
      print('getVideoById Error: $e');
      return null;
    }
  }
}
