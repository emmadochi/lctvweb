import 'dart:io';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:dio/dio.dart';
import 'package:shared_preferences/shared_preferences.dart';

class PushNotificationService {
  static final FirebaseMessaging _fcm = FirebaseMessaging.instance;
  static final FlutterLocalNotificationsPlugin _localNotifications = FlutterLocalNotificationsPlugin();

  // Replace with your actual API base URL
  static final String _baseUrl = "http://localhost/LCMTVWebNew/backend/api";

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

    await _localNotifications.initialize(initializationSettings);

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
      // Here you could navigate to a specific screen based on message.data['type']
    });

    // Get and save the token
    _updateToken();
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

  static Future<void> _saveTokenToBackend(String token) async {
    final prefs = await SharedPreferences.getInstance();
    final jwtToken = prefs.getString('token');
    
    // Only register if user is logged in
    if (jwtToken == null) return;

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
        payload: message.data['id'],
      );
    }
  }
}

// Global background handler
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  print("Handling a background message: ${message.messageId}");
}
