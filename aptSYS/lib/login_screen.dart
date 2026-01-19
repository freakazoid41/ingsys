import 'dart:ui';

import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

import 'background_widget.dart';
import 'apartments_screen.dart';

class LoginScreen extends StatefulWidget {
  @override
  _LoginScreenState createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();

  void _login() {
    // Simple check, in real app use authentication
    if (_usernameController.text == 'admin' && _passwordController.text == 'password') {
      Navigator.push(
        context,
        CupertinoPageRoute(builder: (context) => ApartmentsScreen()),
      );
    } else {
      showCupertinoDialog(
        context: context,
        builder: (context) => CupertinoAlertDialog(
          title: Text('Hata'),
          content: Text('Geçersiz bilgiler'),
          actions: [
            CupertinoDialogAction(
              child: Text('Tamam'),
              onPressed: () => Navigator.of(context).pop(),
            ),
          ],
        ),
      );
    }
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
                        _buildTextField(icon: CupertinoIcons.person_solid, placeholder: 'tulay@picklecan.me', controller: _usernameController),
                        SizedBox(height: 12),
                        _buildTextField(icon: CupertinoIcons.lock_fill, placeholder: '••••••••••', controller: _passwordController, obscure: true),
                        SizedBox(height: 18),
                        // Translucent button
                        GestureDetector(
                          onTap: _login,
                          child: Container(
                            height: 44,
                            decoration: BoxDecoration(
                              color: Colors.white.withOpacity(0.06),
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(color: Colors.white.withOpacity(0.12)),
                            ),
                            alignment: Alignment.center,
                            child: Text('Giriş Yap', style: TextStyle(color: Colors.white70, fontSize: 16)),
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