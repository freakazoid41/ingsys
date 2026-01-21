import 'dart:ui';

import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'background_widget.dart';
import 'providers/auth_provider.dart';

class LoginScreen extends StatefulWidget {
  @override
  _LoginScreenState createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  String? _errorMessage;

  void _login() async {
    final authProvider = context.read<AuthProvider>();
    setState(() => _errorMessage = null); // Clear previous errors

    try {
      await authProvider.login(_emailController.text, _passwordController.text);
      // Navigation is handled by AuthWrapper
    } catch (e) {
      setState(() {
        if (e is AuthException) {
          _errorMessage = _getUserFriendlyErrorMessage(e);
        } else {
          _errorMessage = 'Bir hata oluştu. Lütfen tekrar deneyin.';
        }
      });
    }
  }

  String _getUserFriendlyErrorMessage(AuthException e) {
    final message = e.message.toLowerCase();
    final statusCode = e.statusCode;

    // Check status codes first
    if (statusCode == 401) {
      return 'Geçersiz kullanıcı adı veya şifre. Lütfen bilgilerinizi kontrol edin.';
    } else if (statusCode == 422) {
      return 'Girdiğiniz bilgiler geçersiz. Lütfen e-posta formatınızı ve şifrenizi kontrol edin.';
    } else if (statusCode == 429) {
      return 'Çok fazla giriş denemesi yapıldı. Lütfen bir süre bekleyin.';
    } else if (statusCode == 500) {
      return 'Sunucu hatası. Lütfen daha sonra tekrar deneyin.';
    }

    // Check message content for specific errors
    if (message.contains('user') && message.contains('not found')) {
      return 'Bu e-posta adresi ile kayıtlı kullanıcı bulunamadı.';
    } else if (message.contains('password') || message.contains('credential')) {
      return 'Şifre yanlış. Lütfen şifrenizi kontrol edin.';
    } else if (message.contains('email') && message.contains('invalid')) {
      return 'Geçersiz e-posta formatı. Lütfen geçerli bir e-posta adresi girin.';
    } else if (message.contains('network') || message.contains('connection')) {
      return 'İnternet bağlantınızı kontrol edin ve tekrar deneyin.';
    } else if (message.contains('timeout')) {
      return 'İstek zaman aşımına uğradı. Lütfen tekrar deneyin.';
    }

    // Default fallback
    return 'Giriş yapılamadı. Lütfen bilgilerinizi kontrol edip tekrar deneyin.';
  }

  Widget _buildTextField({required IconData icon, required String placeholder, required TextEditingController controller, bool obscure = false}) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.95),
        borderRadius: BorderRadius.circular(8),
      ),
      padding: EdgeInsets.symmetric(horizontal: 12),
      child: Row(
        children: [
          Icon(icon, color: Colors.blueGrey[700]),
          SizedBox(width: 12),
          Expanded(
            child: CupertinoTextField(
              controller: controller,
              obscureText: obscure,
              placeholder: placeholder,
              decoration: null,
              cursorColor: Colors.black87,
              style: TextStyle(color: Colors.black87, fontSize: 15),
              placeholderStyle: TextStyle(color: Colors.black45, fontSize: 15),
              padding: EdgeInsets.symmetric(vertical: 14),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      // Use a Scaffold to make layering easier; still use Cupertino widgets inside
      body: Stack(
        fit: StackFit.expand,
        children: [
          BackgroundWidget(),

          // Frosted glass panel
          Center(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 28.0),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: BackdropFilter(
                  filter: ImageFilter.blur(sigmaX: 8.0, sigmaY: 8.0),
                  child: Container(
                    width: double.infinity,
                    constraints: BoxConstraints(maxWidth: 520),
                    padding: EdgeInsets.symmetric(horizontal: 24, vertical: 26),
                    decoration: BoxDecoration(
                      color: Colors.black.withOpacity(0.35),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: Colors.white.withOpacity(0.06)),
                    ),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text('Hoşgeldiniz!', style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w600)),
                        SizedBox(height: 6),
                        Text('Sisteme giriş yapmak için bilgilerinizi giriniz..', style: TextStyle(color: Colors.white70, fontSize: 13)),
                        SizedBox(height: 18),
                        _buildTextField(icon: CupertinoIcons.person_solid, placeholder: 'test@example.com', controller: _emailController),
                        SizedBox(height: 12),
                        _buildTextField(icon: CupertinoIcons.lock_fill, placeholder: 'password123', controller: _passwordController, obscure: true),
                        SizedBox(height: 12),
                        // Error message display
                        if (_errorMessage != null)
                          Container(
                            padding: EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: Colors.red.withOpacity(0.1),
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(color: Colors.red.withOpacity(0.3)),
                            ),
                            child: Row(
                              children: [
                                Icon(CupertinoIcons.exclamationmark_triangle, color: Colors.red, size: 20),
                                SizedBox(width: 8),
                                Expanded(
                                  child: Text(
                                    _errorMessage!,
                                    style: TextStyle(color: Colors.red, fontSize: 14),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        if (_errorMessage != null) SizedBox(height: 12),
                        SizedBox(height: 6),
                        // Translucent button
                        Consumer<AuthProvider>(
                          builder: (context, authProvider, child) => GestureDetector(
                            onTap: authProvider.isLoading ? null : _login,
                            child: Container(
                              height: 44,
                              decoration: BoxDecoration(
                                color: Colors.white.withOpacity(0.06),
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(color: Colors.white.withOpacity(0.12)),
                              ),
                              alignment: Alignment.center,
                              child: authProvider.isLoading
                                  ? CupertinoActivityIndicator(color: Colors.white70)
                                  : Text('Giriş Yap', style: TextStyle(color: Colors.white70, fontSize: 16)),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}