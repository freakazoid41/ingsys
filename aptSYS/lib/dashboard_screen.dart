import 'package:apt_sys/pages/home_page.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

import 'background_widget.dart';
import 'components/navigation_bar.dart';
import 'components/bottom_nav.dart';
import 'pages/add_page.dart';
import 'pages/calendar_page.dart';
import 'pages/contact_page.dart';
import 'pages/notifications_page.dart';

class DashboardScreen extends StatefulWidget {
  final String apartmentName;

  DashboardScreen({required this.apartmentName});

  @override
  _DashboardScreenState createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  int _currentIndex = 2; // center home active by default

  @override
  Widget build(BuildContext context) {
    final pages = <Widget>[
      AddPage(),
      CalendarPage(),
      // index 2 — dashboard content
      HomePage(),
      NotificationsPage(),
      ContactPage(),
    ];

    return Scaffold(
      appBar: appAppBar('Dashboard', context),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: Stack(
                fit: StackFit.expand,
                children: [
                  BackgroundWidget(),
                  // show selected page content
                  Center(
                    child: Container(
                      constraints: BoxConstraints(maxWidth: 760),
                      margin: EdgeInsets.all(24),
                      child: IndexedStack(
                        index: _currentIndex,
                        children: pages.map((w) => Container(padding: EdgeInsets.all(20), child: w)).toList(),
                      ),
                    ),
                  ),

                  // Combined floating navigation (snake bar + center button)
                  Positioned(
                    left: 0,
                    right: 0,
                    bottom: 0,
                    child: Center(
                      child: FloatingSnakeNav(
                        currentIndex: _currentIndex,
                        onTap: (index) {
                          setState(() { _currentIndex = index; });
                        },
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}