import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

/// Custom exception for authentication errors
class AuthException implements Exception {
  final String message;
  final int? statusCode;

  AuthException(this.message, {this.statusCode});

  @override
  String toString() => 'AuthException: $message${statusCode != null ? ' (Status: $statusCode)' : ''}';
}

/// Provider class managing authentication state and API calls
class AuthProvider with ChangeNotifier {
  final String baseUrl;
  final http.Client _client;
  String? _sessionCookie;

  bool _isLoading = false;
  bool _isLoggedIn = false;
  String? _token;
  Map<String, dynamic>? _user;

  AuthProvider(this.baseUrl) : _client = http.Client();

  // Getters for state
  bool get isLoading => _isLoading;
  bool get isLoggedIn => _isLoggedIn;
  String? get token => _token;
  Map<String, dynamic>? get user => _user;

  /// Fetches CSRF cookie from Laravel Sanctum
  Future<void> _ensureCsrfCookie() async {
    if (_sessionCookie != null) return;

    try {
      final response = await _client.get(
        Uri.parse('$baseUrl/sanctum/csrf-cookie'),
        headers: {
          'Accept': 'application/json',
        },
      );
      if (response.statusCode == 204) {
        // CSRF request successful - cookies should be handled by HTTP client
        _sessionCookie = 'initialized';
      } else {
        throw AuthException('Failed to fetch CSRF cookie: HTTP ${response.statusCode}', statusCode: response.statusCode);
      }
    } catch (e) {
      if (e is AuthException) rethrow;
      throw AuthException('CSRF cookie fetch failed: $e');
    }
  }

  /// Parses JSON response and throws AuthException on error
  Map<String, dynamic> _parseResponse(http.Response response) {
    if (response.statusCode >= 200 && response.statusCode < 300) {
      return jsonDecode(response.body);
    }
    throw AuthException('Request failed: ${response.body}', statusCode: response.statusCode);
  }

  /// Attempts to log in with email and password
  Future<void> login(String email, String password) async {
    await _performAuthAction(() async {
      await _ensureCsrfCookie();

      final request = http.MultipartRequest('POST', Uri.parse('$baseUrl/api/v1/auth/login'))
        ..fields['email'] = email
        ..fields['password'] = password
        ..headers['Accept'] = 'application/json'
        ..headers['X-Requested-With'] = 'XMLHttpRequest';

      final streamedResponse = await _client.send(request);
      final responseBody = await streamedResponse.stream.bytesToString();

      if (streamedResponse.statusCode == 200) {
        try {
          final response = jsonDecode(responseBody);
          if (response is Map<String, dynamic> &&
              response['success'] == true &&
              response['token'] != null &&
              response['token'] is String) {
            _token = response['token'] as String;
            _user = null; // User data not provided in this response format
            _isLoggedIn = true;
          } else {
            throw AuthException('Login failed: Invalid response format - $responseBody', statusCode: streamedResponse.statusCode);
          }
        } catch (e) {
          if (e is AuthException) rethrow;
          throw AuthException('Login failed: JSON parsing error - $responseBody', statusCode: streamedResponse.statusCode);
        }
      } else {
        throw AuthException('Login failed: HTTP ${streamedResponse.statusCode} - $responseBody', statusCode: streamedResponse.statusCode);
      }
    });
  }

  /// Logs out the current user
  Future<void> logout() async {
    try {
      if (_token != null) {
        final response = await _client.post(
          Uri.parse('$baseUrl/api/logout'),
          headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer $_token',
          },
        );

        if (response.statusCode != 200) {
          throw AuthException('Logout failed: ${response.body}', statusCode: response.statusCode);
        }
      }
    } finally {
      _clearAuthState();
    }
  }

  /// Registers a new user account
  Future<void> register(String username, String password, String email) async {
    await _performAuthAction(() async {
      final response = await _client.post(
        Uri.parse('$baseUrl/api/register'),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: jsonEncode({
          'username': username,
          'password': password,
          'email': email,
        }),
      );

      final data = _parseResponse(response);
      if (data['success'] == true && data['token'] != null) {
        _token = data['token'];
        _user = null; // User data not provided in this response format
        _isLoggedIn = true;
      } else {
        throw AuthException('Registration failed: Invalid response format', statusCode: response.statusCode);
      }
    });
  }

  /// Common method for auth actions (login/register)
  Future<void> _performAuthAction(Future<void> Function() action) async {
    _isLoading = true;
    notifyListeners();

    try {
      await action();
    } catch (e) {
      _clearAuthState();
      rethrow;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Clears all authentication state
  void _clearAuthState() {
    _isLoggedIn = false;
    _token = null;
    _user = null;
    notifyListeners();
  }
}