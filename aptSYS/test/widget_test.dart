// This is a basic Flutter widget test.
//
// To perform an interaction with a widget in your test, use the WidgetTester
// utility in the flutter_test package. For example, you can send tap and scroll
// gestures. You can also use WidgetTester to find child widgets in the widget
// tree, read text, and verify that the values of widget properties are correct.

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:apt_sys/main.dart';
import 'package:apt_sys/settings.dart';

void main() {
  testWidgets('App starts without crashing', (WidgetTester tester) async {
    // Create mock settings
    final settings = AppSettings();
    await settings.loadSettings(); // This will use default values in test

    // Build our app and trigger a frame.
    await tester.pumpWidget(MyApp(settings: settings));

    // Verify that the app builds without errors
    expect(find.byType(MaterialApp), findsOneWidget);
  });
}
