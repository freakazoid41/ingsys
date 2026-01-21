import 'package:flutter/services.dart';
import 'package:yaml/yaml.dart';

class AppSettings {
  static const String _settingsAssetPath = 'assets/settings.yaml';
  static const String _defaultBaseUrl = 'http://localhost:8000';

  String _baseUrl = _defaultBaseUrl;

  String get baseUrl => _baseUrl;

  Future<void> loadSettings() async {
    try {
      final yamlString = await rootBundle.loadString(_settingsAssetPath);
      final yamlMap = loadYaml(yamlString) as YamlMap?;
      if (yamlMap != null && yamlMap.containsKey('baseUrl')) {
        _baseUrl = yamlMap['baseUrl'] as String;
      }
    } catch (e) {
      // If loading fails, use defaults
      _baseUrl = _defaultBaseUrl;
    }
  }

  // Singleton pattern
  static final AppSettings _instance = AppSettings._internal();

  factory AppSettings() {
    return _instance;
  }

  AppSettings._internal();
}