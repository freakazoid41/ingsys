import 'package:flutter/cupertino.dart';

import '../login_screen.dart';

CupertinoNavigationBar appNavigationBar(String title, BuildContext context) {
  return CupertinoNavigationBar(
    middle: Text(title),
    trailing: CupertinoButton(
      padding: EdgeInsets.zero,
      child: Icon(CupertinoIcons.power),
      onPressed: () => Navigator.of(context).pushAndRemoveUntil(
        CupertinoPageRoute(builder: (context) => LoginScreen()),
        (route) => false,
      ),
    ),
  );
}