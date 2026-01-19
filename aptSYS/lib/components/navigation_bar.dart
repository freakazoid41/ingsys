import 'package:flutter/cupertino.dart';

import '../login_screen.dart';

CupertinoNavigationBar appNavigationBar(String title, BuildContext context) {
  return CupertinoNavigationBar(
    middle: Text(title),
    trailing: CupertinoButton(
      padding: EdgeInsets.zero,
      child: Icon(CupertinoIcons.power),
      onPressed: () {
        showCupertinoDialog(
          context: context,
          builder: (context) => CupertinoAlertDialog(
            content: CupertinoActivityIndicator(),
          ),
        );
        Future.delayed(Duration(milliseconds: 500), () {
          Navigator.of(context).pop(); // dismiss dialog
          Navigator.of(context).pushAndRemoveUntil(
            CupertinoPageRoute(builder: (context) => LoginScreen()),
            (route) => false,
          );
        });
      },
    ),
  );
}