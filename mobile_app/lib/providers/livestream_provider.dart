import 'dart:async';
import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../models/livestream_model.dart';

class LivestreamProvider with ChangeNotifier {
  final ApiService _apiService = ApiService();

  LivestreamModel? _featuredStream;
  List<LivestreamModel> _activeStreams = [];
  bool _isLoading = false;
  String? _error;

  final String _sessionId = "${DateTime.now().millisecondsSinceEpoch}-${(1000 + (9000 * (1 / (1 + (DateTime.now().microsecond % 100))))).toInt()}";
  Timer? _heartbeatTimer;
  int? _currentWatchingId;

  LivestreamModel? get featuredStream => _featuredStream;
  List<LivestreamModel> get activeStreams => _activeStreams;
  bool get isLoading => _isLoading;
  String? get error => _error;

  void startHeartbeat(int streamId) {
    if (_currentWatchingId == streamId) return;
    
    _currentWatchingId = streamId;
    _sendHeartbeat(); // Immediate first ping
    
    _heartbeatTimer?.cancel();
    _heartbeatTimer = Timer.periodic(const Duration(seconds: 30), (timer) {
      _sendHeartbeat();
    });
  }

  void stopHeartbeat() {
    _heartbeatTimer?.cancel();
    _heartbeatTimer = null;
    _currentWatchingId = null;
  }

  Future<void> _sendHeartbeat() async {
    if (_currentWatchingId == null) return;
    
    try {
      await _apiService.post('/livestreams/$_currentWatchingId/heartbeat', data: {
        'session_id': _sessionId,
      });
      // Optionally update local viewer count if API returns it
    } catch (e) {
      print('Heartbeat error: $e');
    }
  }

  Future<void> fetchLivestreams() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final results = await Future.wait([
        _apiService.get('/livestreams/featured'),
        _apiService.get('/livestreams'),
      ]);

      final featuredData = results[0].data;
      final listData = results[1].data;

      final featuredRaw = featuredData['data'];
      if (featuredRaw != null && featuredRaw is Map<String, dynamic>) {
        _featuredStream = LivestreamModel.fromJson(featuredRaw);
      } else if (featuredRaw is List && featuredRaw.isNotEmpty) {
        // Defensive fallback: API returned a list — use the first item
        _featuredStream = LivestreamModel.fromJson(featuredRaw[0]);
      }

      if (listData['data'] != null && listData['data'] is List) {
        _activeStreams = (listData['data'] as List)
            .map((s) => LivestreamModel.fromJson(s))
            .toList();
      }
    } catch (e) {
      _error = 'Failed to load livestreams';
      print('LivestreamProvider error: $e');
    }

    _isLoading = false;
    notifyListeners();
  }

  @override
  void dispose() {
    _heartbeatTimer?.cancel();
    super.dispose();
  }
}
