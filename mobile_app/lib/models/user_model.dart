import 'dart:convert';

class UserModel {
  final int id;
  final String email;
  final String firstName;
  final String lastName;
  final String? role;
  final List<int> myChannels;

  UserModel({
    required this.id,
    required this.email,
    required this.firstName,
    required this.lastName,
    this.role,
    this.myChannels = const [],
  });

  String get fullName => '$firstName $lastName'.trim();

  factory UserModel.fromJson(Map<String, dynamic> json) {
    // Parse my_channels from JSON string or list
    List<int> channels = [];
    if (json['my_channels'] != null) {
      if (json['my_channels'] is String) {
        try {
          final decoded = jsonDecode(json['my_channels']);
          if (decoded is List) {
            channels = decoded.map((e) => int.tryParse(e.toString()) ?? 0).where((e) => e > 0).toList();
          }
        } catch (e) {
          print('Error parsing my_channels string: $e');
        }
      } else if (json['my_channels'] is List) {
        channels = (json['my_channels'] as List).map((e) => int.tryParse(e.toString()) ?? 0).where((e) => e > 0).toList();
      }
    }

    return UserModel(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      email: json['email']?.toString() ?? '',
      firstName: json['first_name']?.toString() ?? '',
      lastName: json['last_name']?.toString() ?? '',
      role: json['role']?.toString(),
      myChannels: channels,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'email': email,
      'first_name': firstName,
      'last_name': lastName,
      'role': role,
      'my_channels': myChannels,
    };
  }

  UserModel copyWith({
    int? id,
    String? email,
    String? firstName,
    String? lastName,
    String? role,
    List<int>? myChannels,
  }) {
    return UserModel(
      id: id ?? this.id,
      email: email ?? this.email,
      firstName: firstName ?? this.firstName,
      lastName: lastName ?? this.lastName,
      role: role ?? this.role,
      myChannels: myChannels ?? this.myChannels,
    );
  }
}
