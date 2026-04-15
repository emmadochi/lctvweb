import 'package:flutter/material.dart';
import '../services/api_service.dart';

class NotificationModel {
  final int id;
  final String title;
  final String message;
  final String? type;
  final DateTime createdAt;
  final bool isRead;

  NotificationModel({
    required this.id,
    required this.title,
    required this.message,
    this.type,
    required this.createdAt,
    this.isRead = false,
  });

  factory NotificationModel.fromJson(Map<String, dynamic> json) {
    return NotificationModel(
      id: json['id'] is String ? int.parse(json['id']) : json['id'],
      title: json['title'] ?? '',
      message: json['message'] ?? '',
      type: json['type'],
      createdAt: json['created_at'] != null ? DateTime.parse(json['created_at']) : DateTime.now(),
      isRead: (json['is_read'] == 1 || json['is_read'] == true),
    );
  }
}

class NotificationProvider with ChangeNotifier {
  final ApiService _apiService = ApiService();
  List<NotificationModel> _notifications = [];
  int _unreadCount = 0;
  bool _isLoading = false;

  List<NotificationModel> get notifications => _notifications;
  int get unreadCount => _unreadCount;
  bool get isLoading => _isLoading;

  Future<void> fetchNotifications() async {
    _isLoading = true;
    notifyListeners();

    try {
      final response = await _apiService.get('/notifications');
      final bool isSuccess = response.data['success'] == true || response.data['status'] == 'success';
      
      if (isSuccess) {
        final List<dynamic> data = response.data['data'] ?? [];
        _notifications = data.map((n) => NotificationModel.fromJson(n)).toList();
        _unreadCount = _notifications.where((n) => !n.isRead).length;
      }
    } catch (e) {
      print('fetchNotifications error: $e');
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> fetchUnreadCount() async {
    try {
      final response = await _apiService.get('/notifications/unread-count');
      if (response.data['success'] == true || response.data['status'] == 'success') {
        _unreadCount = int.parse(response.data['data']?['unread_count']?.toString() ?? '0');
        notifyListeners();
      }
    } catch (e) {
      print('fetchUnreadCount error: $e');
    }
  }

  Future<void> markAsRead(int notificationId) async {
    try {
      await _apiService.post('/notifications/mark-read', data: {'id': notificationId});
      final index = _notifications.indexWhere((n) => n.id == notificationId);
      if (index != -1) {
        // Since NotificationModel is immutable, we'd need a copyWith or just re-fetch
        fetchNotifications();
      }
    } catch (e) {
      print('markAsRead error: $e');
    }
  }

  Future<void> markAllAsRead() async {
    try {
      await _apiService.post('/notifications/mark-all-read');
      fetchNotifications();
    } catch (e) {
      print('markAllAsRead error: $e');
    }
  }
}
