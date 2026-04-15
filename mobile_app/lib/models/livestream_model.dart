class LivestreamModel {
  final int id;
  final String title;
  final String? description;
  final String thumbnailUrl;
  final String youtubeId;
  final int viewerCount;
  final bool isLive;
  final DateTime? startedAt;
  final String? channelTitle;
  final int? categoryId;
  final String targetRole;

  final String? hlsUrl;

  LivestreamModel({
    required this.id,
    required this.title,
    this.description,
    required this.thumbnailUrl,
    required this.youtubeId,
    this.hlsUrl,
    this.viewerCount = 0,
    this.isLive = true,
    this.startedAt,
    this.channelTitle,
    this.categoryId,
    this.targetRole = 'general',
  });

  factory LivestreamModel.fromJson(Map<String, dynamic> json) {
    return LivestreamModel(
      id: json['id'] is String ? int.parse(json['id']) : json['id'],
      title: json['title'] ?? '',
      description: json['description'],
      thumbnailUrl: json['thumbnail_url'] ?? '',
      youtubeId: json['youtube_id'] ?? '',
      hlsUrl: json['hls_url'],
      viewerCount: json['viewer_count'] is String ? int.parse(json['viewer_count']) : (json['viewer_count'] ?? 0),
      isLive: (json['is_live'] is int ? json['is_live'] == 1 : (json['is_live'] == true)),
      startedAt: json['started_at'] != null ? DateTime.parse(json['started_at']) : null,
      channelTitle: json['channel_title'] ?? json['author'],
      categoryId: json['category_id'] is String ? int.parse(json['category_id']) : json['category_id'],
      targetRole: json['target_role'] ?? 'general',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'title': title,
      'description': description,
      'thumbnail_url': thumbnailUrl,
      'youtube_id': youtubeId,
      'hls_url': hlsUrl,
      'viewer_count': viewerCount,
      'is_live': isLive ? 1 : 0,
      'started_at': startedAt?.toIso8601String(),
      'channel_title': channelTitle,
      'category_id': categoryId,
      'target_role': targetRole,
    };
  }
}
