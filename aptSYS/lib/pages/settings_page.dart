import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../settings.dart';

class SettingsPage extends StatefulWidget {
  @override
  _SettingsPageState createState() => _SettingsPageState();
}

class _SettingsPageState extends State<SettingsPage> {
  late AppSettings _settings;

  @override
  void initState() {
    super.initState();
    _settings = context.read<AppSettings>();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Settings'),
      ),
      body: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              'Base API URL',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
            ),
            SizedBox(height: 8),
            TextField(
              controller: TextEditingController(text: _settings.baseUrl),
              readOnly: true,
              decoration: InputDecoration(
                border: OutlineInputBorder(),
                hintText: 'Configured in assets/settings.yaml',
              ),
            ),
            SizedBox(height: 16),
            Text(
              'To change the base API URL, edit the assets/settings.yaml file.',
              style: TextStyle(color: Colors.grey),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }
}