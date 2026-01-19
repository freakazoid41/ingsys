import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

import 'background_widget.dart';
import 'components/navigation_bar.dart';

class DashboardScreen extends StatelessWidget {
  final String apartmentName;

  DashboardScreen({required this.apartmentName});

  @override
  Widget build(BuildContext context) {
    return CupertinoPageScaffold(
      navigationBar: appNavigationBar('Dashboard', context),
      child: Stack(
        fit: StackFit.expand,
        children: [
          BackgroundWidget(),
          SafeArea(
            child: Center(
              child: Container(
                constraints: BoxConstraints(maxWidth: 600),
                margin: EdgeInsets.all(24),
                padding: EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: Colors.black.withOpacity(0.45),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: Colors.white.withOpacity(0.06)),
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(apartmentName, style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w600)),
                    SizedBox(height: 12),
                    Text('Simple dashboard for the selected apartment.', style: TextStyle(color: Colors.white70)),
                    SizedBox(height: 18),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                      children: [
                        _infoTile('Rooms', '3'),
                        _infoTile('Area', '120 m²'),
                        _infoTile('Status', 'Active'),
                      ],
                    ),
                    SizedBox(height: 16),
                    CupertinoButton.filled(
                      child: Text('Back'),
                      onPressed: () => Navigator.of(context).pop(),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _infoTile(String title, String value) {
    return Column(
      children: [
        Text(value, style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w600)),
        SizedBox(height: 6),
        Text(title, style: TextStyle(color: Colors.white70, fontSize: 12)),
      ],
    );
  }
}