import 'dart:io';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:dio/dio.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../utils/constants.dart';
import '../main.dart';
import '../providers/video_provider.dart';
import '../screens/video/video_player_screen.dart';

class PushNotificationService {
  static final FirebaseMessaging _fcm = FirebaseMessaging.instance;
  static final FlutterLocalNotificationsPlugin _localNotifications = FlutterLocalNotificationsPlugin();
  static final _storage = const FlutterSecureStorage();

  // Use the central constant instead of a hardcoded URL
  static final String _baseUrl = AppConstants.baseUrl;

  static Future<void> initialize() async {
    // Request permissions for iOS and Android 13+
    NotificationSettings settings = await _fcm.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );

    if (settings.authorizationStatus == AuthorizationStatus.authorized) {
      print('User granted notification permissions');
    }

    // Initialize local notifications for foreground display
    const AndroidInitializationSettings initializationSettingsAndroid =
        AndroidInitializationSettings('@mipmap/ic_launcher');
    const DarwinInitializationSettings initializationSettingsIOS = DarwinInitializationSettings();
    const InitializationSettings initializationSettings = InitializationSettings(
      android: initializationSettingsAndroid,
      iOS: initializationSettingsIOS,
    );

    await _localNotifications.initialize(
      initializationSettings,
      onDidReceiveNotificationResponse: (NotificationResponse response) {
        if (response.payload != null) {
          try {
            final data = jsonDecode(response.payload!);
            _handleNotificationClick(data);
          } catch(e){}
        }
      },
    );

    // Create the high importance channel for Android 8+
    if (Platform.isAndroid) {
      const AndroidNotificationChannel channel = AndroidNotificationChannel(
        'high_importance_channel',
        'High Importance Notifications',
        importance: Importance.max,
      );
      await _localNotifications
          .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
          ?.createNotificationChannel(channel);
    }

    // Handle background messages
    FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

    // Handle foreground messages
    FirebaseMessaging.onMessage.listen((RemoteMessage message) {
      print('Got a message whilst in the foreground!');
      _showLocalNotification(message);
    });

    // Handle notification clicks
    FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
      print('Notification clicked! Data: ${message.data}');
      _handleNotificationClick(message.data);
    });

    // Check if the app was opened from a terminated state via a notification
    RemoteMessage? initialMessage = await _fcm.getInitialMessage();
    if (initialMessage != null) {
      _handleNotificationClick(initialMessage.data);
    }

    // Get and save the token
    _updateToken();
  }

  static Future<void> _handleNotificationClick(Map<String, dynamic> data) async {
    if (data['type'] == 'video' && data['id'] != null) {
      // Wait for navigator context to be ready if app is just launching
      int retries = 0;
      while (navigatorKey.currentContext == null && retries < 10) {
        await Future.delayed(const Duration(milliseconds: 500));
        retries++;
      }
      
      final context = navigatorKey.currentContext;
      if (context != null) {
        final videoId = int.tryParse(data['id'].toString());
        if (videoId != null) {
          final videoProvider = Provider.of<VideoProvider>(context, listen: false);
          final video = await videoProvider.getVideoById(videoId);
          if (video != null && context.mounted) {
            Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => VideoPlayerScreen(video: video),
              ),
            );
          }
        }
      }
    }
  }

  static Future<void> _updateToken() async {
    String? token = await _fcm.getToken();
    if (token != null) {
      print("FCM Token: $token");
      await _saveTokenToBackend(token);
    }

    // Listen to token refreshes
    _fcm.onTokenRefresh.listen((newToken) {
      _saveTokenToBackend(newToken);
    });
  }

  static Future<void> registerDevice() async {
    String? token = await _fcm.getToken();
    if (token != null) {
      await _saveTokenToBackend(token);
    }
  }

  static Future<void> _saveTokenToBackend(String token) async {
    // Correctly retrieve the token from secure storage
    final jwtToken = await _storage.read(key: AppConstants.tokenKey);
    
    // Only register if user is logged in
    if (jwtToken == null) {
      print("FCM Registration skipped: User not logged in");
      return;
    }

    try {
      final dio = Dio();
      dio.options.headers['Authorization'] = 'Bearer $jwtToken';
      
      await dio.post(
        "$_baseUrl/push/subscribe",
        data: {
          'endpoint': token, // We use endpoint column for simplicity
          'keys': {
            'p256dh': 'fcm',
            'auth': 'fcm'
          }
        },
      );
      print("FCM Token successfully registered with backend");
    } catch (e) {
      print("Error registering FCM token: $e");
    }
  }

  static Future<void> _showLocalNotification(RemoteMessage message) async {
    RemoteNotification? notification = message.notification;
    AndroidNotification? android = message.notification?.android;

    if (notification != null && android != null) {
      await _localNotifications.show(
        notification.hashCode,
        notification.title,
        notification.body,
        const NotificationDetails(
          android: AndroidNotificationDetails(
            'high_importance_channel',
            'High Importance Notifications',
            importance: Importance.max,
            priority: Priority.high,
            icon: '@mipmap/ic_launcher',
          ),
          iOS: DarwinNotificationDetails(),
        ),
        payload: jsonEncode(message.data),
      );
    }
  }
}

// Global background handler
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  print("Handling a background message: ${message.messageId}");
}
