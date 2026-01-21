import 'package:flutter/services.dart';
import 'package:yaml/yaml.dart';

class AppSettings {
  static const String _settingsAssetPath = 'assets/settings.yaml';
  static const String _defaultBaseUrl = 'http://localhost:8000';

  // Allow overriding the base URL at build/run time with --dart-define
  static const String _envBaseUrl = String.fromEnvironment('BASE_URL', defaultValue: '');

  String _baseUrl = _defaultBaseUrl;

  String get baseUrl => _baseUrl;

  Future<void> loadSettings() async {
    // 1) If a compile-time override was provided, use it (recommended for device testing)
    if (_envBaseUrl.isNotEmpty) {
      _baseUrl = _envBaseUrl;
      // Log resolved base URL for debugging on-device
      // This helps confirm the app is using the runtime override you pass with --dart-define
      // (will appear in `flutter run` logs)
      // ignore: avoid_print
      print('AppSettings: resolved baseUrl => $_baseUrl (from --dart-define)');
      return;
    }

    // 2) Otherwise, try loading from assets/settings.yaml
    try {
      final yamlString = await rootBundle.loadString(_settingsAssetPath);
      final yamlMap = loadYaml(yamlString) as YamlMap?;
      if (yamlMap != null && yamlMap.containsKey('baseUrl')) {
        _baseUrl = yamlMap['baseUrl'] as String;
      }
    } catch (e) {
      // If loading fails, fall back to defaults
      _baseUrl = _defaultBaseUrl;
    }
    // Log resolved base URL for debugging on-device
    // ignore: avoid_print
    print('AppSettings: resolved baseUrl => $_baseUrl (from assets or default)');
  }

  // Singleton pattern
  static final AppSettings _instance = AppSettings._internal();

  factory AppSettings() {
    return _instance;
  }

  AppSettings._internal();
}