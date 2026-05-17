import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class ApiService with ChangeNotifier {
  // Update this with your actual production URL
  static const String baseUrl = "http://yourdomain.com/api/mobile"; 
  
  String? _token;
  Map<String, dynamic>? _user;

  String? get token => _token;
  Map<String, dynamic>? get user => _user;

  Future<bool> login(String username, String password, String role) async {
    try {
      final response = await http.post(
        Uri.parse("$baseUrl/login"),
        headers: {"Content-Type": "application/json"},
        body: jsonEncode({
          "username": username,
          "password": password,
          "role": role,
        }),
      );

      final data = jsonDecode(response.body);

      if (data['success'] == true) {
        _token = data['token'];
        _user = data['user'];
        
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('token', _token!);
        await prefs.setString('user', jsonEncode(_user));
        
        notifyListeners();
        return true;
      }
      return false;
    } catch (e) {
      debugPrint("Login error: $e");
      return false;
    }
  }

  Future<void> logout() async {
    _token = null;
    _user = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('token');
    await prefs.remove('user');
    notifyListeners();
  }

  Future<Map<String, dynamic>?> getDashboardStats() async {
    if (_token == null) return null;

    try {
      final response = await http.get(
        Uri.parse("$baseUrl/dashboard"),
        headers: {
          "Content-Type": "application/json",
          "Authorization": "Bearer $_token",
        },
      );

      final data = jsonDecode(response.body);
      if (data['success'] == true) {
        return data['stats'];
      }
      return null;
    } catch (e) {
      debugPrint("Dashboard error: $e");
      return null;
    }
  }

  Future<void> tryAutoLogin() async {
    final prefs = await SharedPreferences.getInstance();
    if (!prefs.containsKey('token')) return;

    _token = prefs.getString('token');
    _user = jsonDecode(prefs.getString('user')!);
    notifyListeners();
  }
}
