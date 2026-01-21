import 'package:flutter/material.dart';
import '../pages/settings_page.dart';

Widget buildMenuSheet(BuildContext context) {
  return Column(
    mainAxisSize: MainAxisSize.min,
    children: [
      ListTile(
        title: Text('Refresh Dashboard'),
        onTap: () {
          Navigator.of(context).pop();
          // TODO: Implement refresh logic
        },
      ),
      ListTile(
        title: Text('Settings'),
        onTap: () {
          Navigator.of(context).pop();
          Navigator.of(context).push(
            MaterialPageRoute(builder: (context) => SettingsPage()),
          );
        },
      ),
      ListTile(
        title: Text('About'),
        onTap: () {
          Navigator.of(context).pop();
          showDialog(
            context: context,
            builder: (context) => AlertDialog(
              title: Text('About aptSYS'),
              content: Text('Apartment Management System\nVersion 1.0.0'),
              actions: [
                TextButton(
                  child: Text('OK'),
                  onPressed: () => Navigator.of(context).pop(),
                ),
              ],
            ),
          );
        },
      ),
      ListTile(
        title: Text('Cancel'),
        onTap: () {
          Navigator.of(context).pop();
        },
      ),
    ],
  );
}