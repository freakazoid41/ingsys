import 'package:flutter/material.dart';
// using system fonts to avoid google_fonts dependency conflicts

class AppTheme {
  // Palette A - image-accurate tokens
  static const Color bg = Color(0xFF0B0C14);
  static final Color surface = Color.fromRGBO(20, 18, 30, 0.75);
  static final Color cardBorder = Color.fromRGBO(255, 255, 255, 0.04);
  static const Color textPrimary = Color(0xFFE8ECF8);
  static const Color muted = Color(0xFF9AA0B4);
  static const Color accentPrimary = Color(0xFF8B5CF6);
  static const Color accentSecondary = Color(0xFFFF6B6B);
  static const Color accent2 = Color(0xFF5CC8FF);
  static const Color success = Color(0xFF4ADE80);
  static const Color warning = Color(0xFFF59E0B);
  static const Color danger = Color(0xFFFF6B6B);

  static ThemeData get theme {
    final base = ThemeData.dark();
    return base.copyWith(
      scaffoldBackgroundColor: bg,
      primaryColor: accentPrimary,
      colorScheme: base.colorScheme.copyWith(
        primary: accentPrimary,
        secondary: accentSecondary,
        background: bg,
        surface: surface,
        onPrimary: Colors.white,
        onSurface: textPrimary,
      ),
      textTheme: base.textTheme.apply(
        bodyColor: textPrimary,
        displayColor: textPrimary,
      ),
      cardColor: surface,
      cardTheme: base.cardTheme.copyWith(
        color: surface,
        elevation: 6,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12.0)),
        shadowColor: Colors.black.withOpacity(0.6),
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: surface.withOpacity(0.8),
        elevation: 0,
        titleTextStyle: TextStyle(fontSize: 20, fontWeight: FontWeight.w600, color: textPrimary),
        iconTheme: IconThemeData(color: muted),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: accentPrimary,
          foregroundColor: Colors.black,
          textStyle: TextStyle(fontWeight: FontWeight.w600),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
      ),
      dividerColor: Colors.white.withOpacity(0.04),
      iconTheme: IconThemeData(color: muted),
    );
  }
}
