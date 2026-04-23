class PrayerRequestModel {
  final int id;
  final int? userId;
  final String fullName;
  final String email;
  final String? phone;
  final String category;
  final String requestText;
  final String status;
  final String? adminResponse;
  final int? respondedBy;
  final DateTime? respondedAt;
  final DateTime createdAt;
  final String? responderFirst;
  final String? responderLast;

  PrayerRequestModel({
    required this.id,
    this.userId,
    required this.fullName,
    required this.email,
    this.phone,
    required this.category,
    required this.requestText,
    required this.status,
    this.adminResponse,
    this.respondedBy,
    this.respondedAt,
    required this.createdAt,
    this.responderFirst,
    this.responderLast,
  });

  String get responderName {
    if (responderFirst == null) return 'Ministry Team';
    return '$responderFirst ${responderLast ?? ''}'.trim();
  }

  factory PrayerRequestModel.fromJson(Map<String, dynamic> json) {
    return PrayerRequestModel(
      id: json['id'] is String ? int.parse(json['id']) : json['id'],
      userId: json['user_id'] != null 
          ? (json['user_id'] is String ? int.parse(json['user_id']) : json['user_id']) 
          : null,
      fullName: json['full_name'] ?? '',
      email: json['email'] ?? '',
      phone: json['phone'],
      category: json['category'] ?? 'General',
      requestText: json['request_text'] ?? '',
      status: json['status'] ?? 'pending',
      adminResponse: json['admin_response'],
      respondedBy: json['responded_by'] != null 
          ? (json['responded_by'] is String ? int.parse(json['responded_by']) : json['responded_by']) 
          : null,
      respondedAt: json['responded_at'] != null ? DateTime.parse(json['responded_at']) : null,
      createdAt: json['created_at'] != null ? DateTime.parse(json['created_at']) : DateTime.now(),
      responderFirst: json['responder_first'],
      responderLast: json['responder_last'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'user_id': userId,
      'full_name': fullName,
      'email': email,
      'phone': phone,
      'category': category,
      'request_text': requestText,
      'status': status,
      'admin_response': adminResponse,
      'responded_by': respondedBy,
      'responded_at': respondedAt?.toIso8601String(),
      'created_at': createdAt.toIso8601String(),
    };
  }
}
