class CommentModel {
  final int id;
  final int? videoId;
  final int? livestreamId;
  final int userId;
  final String userName;
  final String? userAvatar;
  final String content;
  final DateTime createdAt;

  CommentModel({
    required this.id,
    this.videoId,
    this.livestreamId,
    required this.userId,
    required this.userName,
    this.userAvatar,
    required this.content,
    required this.createdAt,
  });

  factory CommentModel.fromJson(Map<String, dynamic> json) {
    return CommentModel(
      id: json['id'] is String ? int.parse(json['id']) : (json['id'] ?? 0),
      videoId: json['video_id'] != null ? (json['video_id'] is String ? int.parse(json['video_id']) : json['video_id']) : null,
      livestreamId: json['livestream_id'] != null ? (json['livestream_id'] is String ? int.parse(json['livestream_id']) : json['livestream_id']) : null,
      userId: json['user_id'] is String ? int.parse(json['user_id']) : (json['user_id'] ?? 0),
      userName: json['user_name'] ?? json['username'] ?? 'Anonymous',
      userAvatar: json['user_avatar'] ?? json['profile_picture'],
      content: json['content'] ?? '',
      createdAt: json['created_at'] != null 
          ? DateTime.parse(json['created_at']) 
          : DateTime.now(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'video_id': videoId,
      'user_id': userId,
      'content': content,
      'created_at': createdAt.toIso8601String(),
    };
  }
}
