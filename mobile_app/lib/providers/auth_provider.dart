import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:google_sign_in/google_sign_in.dart';
import '../services/api_service.dart';
import '../models/user_model.dart';
import '../utils/constants.dart';
import '../services/push_notification_service.dart';

class AuthProvider with ChangeNotifier {
  UserModel? _user;
  bool _isLoading = false;
  String? _error;
   final ApiService _apiService = ApiService();
  final _storage = const FlutterSecureStorage();
  final FirebaseAuth _auth = FirebaseAuth.instance;
  final GoogleSignIn _googleSignIn = GoogleSignIn();

  UserModel? get user => _user;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get isAuthenticated => _user != null;

  int get roleRank {
    if (_user == null || _user!.role == null) return 0;
    switch (_user!.role!.toLowerCase()) {
      case 'director': return 4;
      case 'pastor': return 3;
      case 'leader': return 2;
      case 'user': return 1;
      case 'member': return 1;
      default: return 0;
    }
  }

  bool get isLeader => roleRank >= 2;
  bool get isPastor => roleRank >= 3;
  bool get isDirector => roleRank >= 4;

  AuthProvider() {
    _loadUser();
  }

  Future<void> _loadUser() async {
    final userData = await _storage.read(key: AppConstants.userKey);
    if (userData != null) {
      _user = UserModel.fromJson(jsonDecode(userData));
      notifyListeners();
    }
  }

  Future<bool> login(String email, String password) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _apiService.post('/users', data: {
        'action': 'login',
        'email': email,
        'password': password,
      });

      if (response.statusCode == 200 || response.statusCode == 201) {
        final data = response.data['data'];
        final token = data['token'];
        final userJson = data['user'];

        // Store token and user data
        await _storage.write(key: AppConstants.tokenKey, value: token);
        await _storage.write(key: AppConstants.userKey, value: jsonEncode(userJson));

        _user = UserModel.fromJson(userJson);
        _isLoading = false;
        notifyListeners();
        
        // Register push notification token now that user is logged in
        PushNotificationService.registerDevice();
        
        return true;
      }
    } catch (e) {
      if (e is DioException && e.response?.data != null) {
        final resData = e.response!.data;
        if (resData is Map && resData['message'] != null) {
          _error = resData['message'].toString();
          if (resData['errors'] != null && resData['errors'] is Map) {
             _error = resData['errors'].values.first.toString();
          }
        } else {
          _error = 'Login failed. Please verify your credentials.';
        }
      } else {
        _error = 'Network error. Please try again later.';
      }
      print('Login error: $e');
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  Future<bool> register(String email, String password, String firstName, String lastName) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _apiService.post('/users', data: {
        'action': 'register',
        'email': email,
        'password': password,
        'first_name': firstName,
        'last_name': lastName,
      });

      if (response.statusCode == 201 || response.statusCode == 200) {
        final data = response.data['data'];
        final token = data['token'];
        final userJson = data['user'];

        await _storage.write(key: AppConstants.tokenKey, value: token);
        await _storage.write(key: AppConstants.userKey, value: jsonEncode(userJson));

        _user = UserModel.fromJson(userJson);
        _isLoading = false;
        notifyListeners();
        
        // Register push notification token now that user is logged in
        PushNotificationService.registerDevice();
        
        return true;
      }
    } catch (e) {
      if (e is DioException && e.response?.data != null) {
        final resData = e.response!.data;
        if (resData is Map && resData['message'] != null) {
          _error = resData['message'].toString();
          if (resData['errors'] != null && resData['errors'] is Map) {
             _error = resData['errors'].values.first.toString();
          }
        } else {
          _error = 'Registration failed. Please try again.';
        }
      } else {
        _error = 'Network error. Please try again later.';
      }
      print('Registration error: $e');
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  Future<bool> signInWithGoogle() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      // Trigger the authentication flow
      final GoogleSignInAccount? googleUser = await _googleSignIn.signIn();

      if (googleUser == null) {
        _isLoading = false;
        notifyListeners();
        return false; // User cancelled
      }

      // Obtain the auth details from the request
      final GoogleSignInAuthentication googleAuth = await googleUser.authentication;

      // Create a new credential
      final OAuthCredential credential = GoogleAuthProvider.credential(
        accessToken: googleAuth.accessToken,
        idToken: googleAuth.idToken,
      );

      // Once signed in, return the UserCredential
      final UserCredential userCredential = await _auth.signInWithCredential(credential);
      final User? firebaseUser = userCredential.user;

      if (firebaseUser != null) {
        // Send to backend
        final response = await _apiService.post('/users', data: {
          'action': 'google_login',
          'google_id': firebaseUser.uid,
          'email': firebaseUser.email,
          'first_name': firebaseUser.displayName?.split(' ').first ?? '',
          'last_name': firebaseUser.displayName?.split(' ').skip(1).join(' ') ?? '',
          'id_token': await firebaseUser.getIdToken(),
        });

        if (response.statusCode == 200 || response.statusCode == 201) {
          final data = response.data['data'];
          final token = data['token'];
          final userJson = data['user'];

          await _storage.write(key: AppConstants.tokenKey, value: token);
          await _storage.write(key: AppConstants.userKey, value: jsonEncode(userJson));

          _user = UserModel.fromJson(userJson);
          _isLoading = false;
          notifyListeners();
          
          // Register push notification token now that user is logged in
          PushNotificationService.registerDevice();
          
          return true;
        }
      }
    } catch (e) {
      _error = 'Google Sign-In failed: ${e.toString()}';
      print('Google Sign-In error: $e');
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  Future<bool> requestPasswordReset(String email) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _apiService.post('/users/forgot-password', data: {
        'email': email,
      });

      if (response.data['success'] == true) {
        _isLoading = false;
        notifyListeners();
        return true;
      }
    } catch (e) {
      _error = _handleError(e);
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  Future<bool> resetPassword(String token, String newPassword) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _apiService.post('/users/reset-password', data: {
        'token': token,
        'password': newPassword,
      });

      if (response.data['success'] == true) {
        _isLoading = false;
        notifyListeners();
        return true;
      }
    } catch (e) {
      _error = _handleError(e);
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  Future<void> logout() async {
    await _storage.delete(key: AppConstants.tokenKey);
    await _storage.delete(key: AppConstants.userKey);
    _user = null;
    notifyListeners();
  }

  Future<bool> updateProfile(String firstName, String lastName) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _apiService.put('/users/profile', data: {
        'first_name': firstName,
        'last_name': lastName,
      });

      final bool isSuccess = response.data['success'] == true || response.data['status'] == 'success';

      if (isSuccess) {
        final userData = response.data['data'];
        _user = UserModel.fromJson(userData);
        await _storage.write(key: AppConstants.userKey, value: jsonEncode(_user!.toJson()));
        _isLoading = false;
        notifyListeners();
        return true;
      }
    } catch (e) {
      _error = _handleError(e);
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  Future<bool> updatePassword(String currentPassword, String newPassword) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _apiService.put('/users/password', data: {
        'current_password': currentPassword,
        'new_password': newPassword,
      });

      final bool isSuccess = response.data['success'] == true || response.data['status'] == 'success';

      if (isSuccess) {
        _isLoading = false;
        notifyListeners();
        return true;
      }
    } catch (e) {
      _error = _handleError(e);
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  // --- Category Subscription Management ---

  bool isSubscribed(int categoryId) {
    return _user?.myChannels.contains(categoryId) ?? false;
  }

  Future<bool> toggleCategorySubscription(int categoryId) async {
    if (_user == null) return false;

    if (isSubscribed(categoryId)) {
      return await removeFromMyChannels(categoryId);
    } else {
      return await addToMyChannels(categoryId);
    }
  }

  Future<bool> addToMyChannels(int categoryId) async {
    if (_user == null) return false;
    _error = null;

    try {
      final response = await _apiService.post('/users/channels', data: {
        'category_id': categoryId,
      });

      if (response.data['success'] == true) {
        // Update local user state
        final List<int> newChannels = List.from(_user!.myChannels);
        if (!newChannels.contains(categoryId)) {
          newChannels.add(categoryId);
        }
        _user = _user!.copyWith(myChannels: newChannels);
        await _storage.write(key: AppConstants.userKey, value: jsonEncode(_user!.toJson()));
        notifyListeners();
        return true;
      }
    } catch (e) {
      _error = _handleError(e);
    }
    return false;
  }

  Future<bool> removeFromMyChannels(int categoryId) async {
    if (_user == null) return false;
    _error = null;

    try {
      final response = await _apiService.delete('/users/channels', data: {
        'category_id': categoryId,
      });

      if (response.data['success'] == true) {
        // Update local user state
        final List<int> newChannels = List.from(_user!.myChannels);
        newChannels.remove(categoryId);
        _user = _user!.copyWith(myChannels: newChannels);
        await _storage.write(key: AppConstants.userKey, value: jsonEncode(_user!.toJson()));
        notifyListeners();
        return true;
      }
    } catch (e) {
      _error = _handleError(e);
    }
    return false;
  }

  String _handleError(dynamic e) {
    if (e is DioException && e.response?.data != null) {
      final resData = e.response!.data;
      if (resData is Map) {
        if (resData['message'] != null) return resData['message'].toString();
        if (resData['errors'] != null && resData['errors'] is Map) {
          return resData['errors'].values.first.toString();
        }
      }
    }
    return 'Operation failed. Please try again.';
  }
}
