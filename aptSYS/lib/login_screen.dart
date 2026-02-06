import 'dart:ui';
import 'package:flutter/foundation.dart';

import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'background_widget.dart';
import 'providers/auth_provider.dart';
import 'components/connectivity_status.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  _LoginScreenState createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  String? _errorMessage;

  void _login() async {
    final authProvider = context.read<AuthProvider>();
    setState(() => _errorMessage = null);

    try {
      await authProvider.login(_emailController.text, _passwordController.text);
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

    if (statusCode == 401) return 'Geçersiz kullanıcı adı veya şifre. Lütfen bilgilerinizi kontrol edin.';
    if (statusCode == 422) return 'Girdiğiniz bilgiler geçersiz. Lütfen e-posta formatınızı ve şifrenizi kontrol edin.';
    if (statusCode == 429) return 'Çok fazla giriş denemesi yapıldı. Lütfen bir süre bekleyin.';
    if (statusCode == 500) return 'Sunucu hatası. Lütfen daha sonra tekrar deneyin.';

    if (message.contains('user') && message.contains('not found')) return 'Bu e-posta adresi ile kayıtlı kullanıcı bulunamadı.';
    if (message.contains('password') || message.contains('credential')) return 'Şifre yanlış. Lütfen şifrenizi kontrol edin.';
    if (message.contains('email') && message.contains('invalid')) return 'Geçersiz e-posta formatı. Lütfen geçerli bir e-posta adresi girin.';
    if (message.contains('network') || message.contains('connection')) return 'İnternet bağlantınızı kontrol edin ve tekrar deneyin.';
    if (message.contains('timeout')) return 'İstek zaman aşımına uğradı. Lütfen tekrar deneyin.';

    return 'Giriş yapılamadı. Lütfen bilgilerinizi kontrol edip tekrar deneyin.';
  }

  Widget _buildTextField({required IconData icon, required String placeholder, required TextEditingController controller, bool obscure = false}) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.95),
        borderRadius: const BorderRadius.all(Radius.circular(8)),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 12),
      child: Row(
        children: [
          Icon(icon, color: Colors.blueGrey[700]),
          const SizedBox(width: 12),
          Expanded(
            child: CupertinoTextField(
              controller: controller,
              obscureText: obscure,
              placeholder: placeholder,
              decoration: null,
              cursorColor: Colors.black87,
              style: const TextStyle(color: Colors.black87, fontSize: 15),
              placeholderStyle: const TextStyle(color: Colors.black45, fontSize: 15),
              padding: const EdgeInsets.symmetric(vertical: 14),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(
        fit: StackFit.expand,
        children: [
          BackgroundWidget(),
          Center(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 28.0),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: kIsWeb
                    ? Container(
                        width: double.infinity,
                        constraints: BoxConstraints(maxWidth: 520),
                        padding: EdgeInsets.symmetric(horizontal: 24, vertical: 26),
                        decoration: BoxDecoration(
                          color: Colors.black.withValues(alpha: 0.35),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: Colors.white.withValues(alpha: 0.06)),
                        ),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            ConnectivityStatus(),
                            const SizedBox(height: 12),
                            Text('Hoşgeldiniz!', style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w600)),
                            const SizedBox(height: 6),
                            Text('Sisteme giriş yapmak için bilgilerinizi giriniz..', style: TextStyle(color: Colors.white70, fontSize: 13)),
                            const SizedBox(height: 18),
                            _buildTextField(icon: CupertinoIcons.person_solid, placeholder: 'test@example.com', controller: _emailController),
                            SizedBox(height: 12),
                            _buildTextField(icon: CupertinoIcons.lock_fill, placeholder: 'password123', controller: _passwordController, obscure: true),
                            const SizedBox(height: 12),
                            if (_errorMessage != null)
                              Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: Colors.red.withValues(alpha: 0.1),
                                  borderRadius: BorderRadius.circular(8),
                                  border: Border.all(color: Colors.red.withValues(alpha: 0.3)),
                                ),
                                child: Row(
                                  children: [
                                    Icon(CupertinoIcons.exclamationmark_triangle, color: Colors.red, size: 20),
                                    const SizedBox(width: 8),
                                    Expanded(
                                      child: Text(
                                        _errorMessage!,
                                        style: const TextStyle(color: Colors.red, fontSize: 14),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            if (_errorMessage != null) const SizedBox(height: 12),
                            const SizedBox(height: 6),
                            Consumer<AuthProvider>(
                              builder: (context, authProvider, child) => GestureDetector(
                                onTap: authProvider.isLoading ? null : _login,
                                child: Container(
                                  height: 44,
                                  decoration: BoxDecoration(
                                    color: Colors.white.withValues(alpha: 0.06),
                                    borderRadius: BorderRadius.circular(8),
                                    border: Border.all(color: Colors.white.withValues(alpha: 0.12)),
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
                      )
                    : BackdropFilter(
                        filter: ImageFilter.blur(sigmaX: 8.0, sigmaY: 8.0),
                        child: Container(
                          width: double.infinity,
                          constraints: BoxConstraints(maxWidth: 520),
                          padding: EdgeInsets.symmetric(horizontal: 24, vertical: 26),
                          decoration: BoxDecoration(
                            color: Colors.black.withValues(alpha: 0.35),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: Colors.white.withValues(alpha: 0.06)),
                          ),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              ConnectivityStatus(),
                              const SizedBox(height: 12),
                              Text('Hoşgeldiniz!', style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w600)),
                              const SizedBox(height: 6),
                              Text('Sisteme giriş yapmak için bilgilerinizi giriniz..', style: TextStyle(color: Colors.white70, fontSize: 13)),
                              const SizedBox(height: 18),
                              _buildTextField(icon: CupertinoIcons.person_solid, placeholder: 'test@example.com', controller: _emailController),
                              const SizedBox(height: 12),
                              _buildTextField(icon: CupertinoIcons.lock_fill, placeholder: 'password123', controller: _passwordController, obscure: true),
                              const SizedBox(height: 12),
                              if (_errorMessage != null)
                                Container(
                                  padding: const EdgeInsets.all(12),
                                  decoration: BoxDecoration(
                                    color: Colors.red.withValues(alpha: 0.1),
                                    borderRadius: BorderRadius.circular(8),
                                    border: Border.all(color: Colors.red.withValues(alpha: 0.3)),
                                  ),
                                  child: Row(
                                    children: [
                                      Icon(CupertinoIcons.exclamationmark_triangle, color: Colors.red, size: 20),
                                      const SizedBox(width: 8),
                                      Expanded(
                                        child: Text(
                                          _errorMessage!,
                                          style: TextStyle(color: Colors.red, fontSize: 14),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              if (_errorMessage != null) const SizedBox(height: 12),
                              const SizedBox(height: 6),
                              Consumer<AuthProvider>(
                                builder: (context, authProvider, child) => GestureDetector(
                                  onTap: authProvider.isLoading ? null : _login,
                                  child: Container(
                                    height: 44,
                                    decoration: BoxDecoration(
                                      color: Colors.white.withValues(alpha: 0.06),
                                      borderRadius: BorderRadius.circular(8),
                                      border: Border.all(color: Colors.white.withValues(alpha: 0.12)),
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