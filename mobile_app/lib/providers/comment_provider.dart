import 'dart:async';
import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../models/comment_model.dart';

class CommentProvider with ChangeNotifier {
  final ApiService _apiService = ApiService();
  
  List<CommentModel> _comments = [];
  bool _isLoading = false;
  bool _isLivestream = false;
  Timer? _pollingTimer;
  int? _currentVideoId;

  List<CommentModel> get comments => _comments;
  bool get isLoading => _isLoading;

  void startPolling(int videoId, {bool isLivestream = false}) {
    _currentVideoId = videoId;
    _isLivestream = isLivestream;
    _fetchComments();
    _pollingTimer?.cancel();
    _pollingTimer = Timer.periodic(const Duration(seconds: 5), (timer) {
      _fetchComments(isBackground: true);
    });
  }

  void stopPolling() {
    _pollingTimer?.cancel();
    _currentVideoId = null;
    _isLivestream = false;
    _comments = [];
  }

  Future<void> _fetchComments({bool isBackground = false}) async {
    if (_currentVideoId == null) return;
    
    if (!isBackground) {
      _isLoading = true;
      notifyListeners();
    }

    try {
      final endpoint = _isLivestream 
          ? '/comments/livestream/$_currentVideoId'
          : '/comments/video/$_currentVideoId';
          
      final response = await _apiService.get(endpoint);
      final bool isSuccess = response.data['success'] == true || response.data['status'] == 'success' || response.statusCode == 200;
      
      if (isSuccess) {
        final Map<String, dynamic> responseData = response.data['data'] is Map ? response.data['data'] : {};
        final List<dynamic> data = responseData['comments'] ?? [];
        _comments = data.map((c) => CommentModel.fromJson(c)).toList();
        // Sort by date descending
        _comments.sort((a, b) => b.createdAt.compareTo(a.createdAt));
        notifyListeners();
      }
    } catch (e) {
      print('Fetch comments error: $e');
    } finally {
      if (!isBackground) {
        _isLoading = false;
        notifyListeners();
      }
    }
  }

  Future<bool> postComment(int videoId, String content) async {
    try {
      final Map<String, dynamic> data = {
        'content': content,
      };
      
      if (_isLivestream) {
        data['livestream_id'] = videoId;
      } else {
        data['video_id'] = videoId;
      }

      final response = await _apiService.post('/comments', data: data);
      final bool isSuccess = response.data['success'] == true || response.data['status'] == 'success' || response.statusCode == 201 || response.statusCode == 200;
      
      if (isSuccess) {
        // Refresh immediately
        await _fetchComments(isBackground: true);
        return true;
      }
      return false;
    } catch (e) {
      print('Post comment error: $e');
      return false;
    }
  }

  @override
  void dispose() {
    _pollingTimer?.cancel();
    super.dispose();
  }
}
