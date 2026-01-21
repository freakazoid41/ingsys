import 'package:flutter/material.dart';
import 'package:flutter/scheduler.dart';

import '../login_screen.dart';
import 'menu_sheet.dart';

AppBar appAppBar(String title, BuildContext context) {
  return AppBar(
    title: Text(title),
    leading: title == 'Dashboard' ? IconButton(
      icon: Icon(Icons.more_vert),
      onPressed: () {
        SchedulerBinding.instance.addPostFrameCallback((_) {
          showModalBottomSheet(
            context: context,
            isDismissible: false,
            useRootNavigator: true,
            builder: (BuildContext context) => buildMenuSheet(context),
          );
        });
      },
    ) : null,
    actions: [
      if (title == 'Dashboard')
        IconButton(
          icon: Icon(Icons.business),
          onPressed: () => Navigator.of(context).pop(),
        ),
      IconButton(
        icon: Icon(Icons.power_settings_new),
        onPressed: () {
          showDialog(
            
            context: context,
            builder: (context) => Dialog(
              backgroundColor: Colors.transparent,
              child: Container(
                height: 280,
                
                padding: EdgeInsets.all(120),
                child: CircularProgressIndicator(),
              ),
            ),
          );
          Future.delayed(Duration(milliseconds: 500), () {
            /*Navigator.of(context).pop(); // dismiss dialog
            Navigator.of(context).pushAndRemoveUntil(
              MaterialPageRoute(builder: (context) => LoginScreen()),
              (route) => false,
            );*/
          });
        },
      ),
    ],
  );
}