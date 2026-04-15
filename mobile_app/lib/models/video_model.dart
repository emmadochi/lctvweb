class VideoModel {
  final int id;
  final String title;
  final String? description;
  final String thumbnailUrl;
  final String videoUrl;
  final String? youtubeId; // Explicit YouTube ID for dedicated player
  final int viewCount;
  final DateTime? publishedAt;
  final String? duration;
  final String? channelTitle;
  final List<String> tags;
  final int? categoryId;
  final String targetRole;

  VideoModel({
    required this.id,
    required this.title,
    this.description,
    required this.thumbnailUrl,
    required this.videoUrl,
    this.youtubeId,
    this.viewCount = 0,
    this.publishedAt,
    this.duration,
    this.channelTitle,
    this.tags = const [],
    this.categoryId,
    this.targetRole = 'general',
  });

  factory VideoModel.fromJson(Map<String, dynamic> json) {
    // Extract Category ID from different possible formats
    int? catId;
    if (json['category_id'] != null) {
      catId = json['category_id'] is String ? int.parse(json['category_id']) : json['category_id'];
    } else if (json['category'] != null && json['category'] is Map) {
      catId = json['category']['id'] is String ? int.parse(json['category']['id']) : json['category']['id'];
    }

    // Extract YouTube ID
    String? yId = json['youtube_id'];
    
    // Handle Video URL / YouTube ID construction
    String vUrl = json['video_url'] ?? '';
    if (vUrl.isEmpty && yId != null) {
      vUrl = 'https://www.youtube.com/watch?v=$yId';
    }

    // Handle Duration (Int vs String)
    String? dur;
    if (json['duration'] != null) {
      dur = json['duration'].toString();
    }

    return VideoModel(
      id: json['id'] is String ? int.parse(json['id']) : json['id'],
      title: json['title'] ?? '',
      description: json['description'],
      thumbnailUrl: json['thumbnail_url'] ?? '',
      videoUrl: vUrl,
      youtubeId: yId,
      viewCount: json['view_count'] is String ? int.parse(json['view_count']) : (json['view_count'] ?? 0),
      publishedAt: json['published_at'] != null ? DateTime.parse(json['published_at']) : null,
      duration: dur,
      channelTitle: json['channel_title'] ?? json['author'],
      tags: json['tags'] is List ? List<String>.from(json['tags']) : [],
      categoryId: catId,
      targetRole: json['target_role'] ?? 'general',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'title': title,
      'description': description,
      'thumbnail_url': thumbnailUrl,
      'video_url': videoUrl,
      'youtube_id': youtubeId,
      'view_count': viewCount,
      'published_at': publishedAt?.toIso8601String(),
      'duration': duration,
      'channel_title': channelTitle,
      'tags': tags,
      'category_id': categoryId,
      'target_role': targetRole,
    };
  }
}
