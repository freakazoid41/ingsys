import 'package:flutter/material.dart';
import 'package:flutter/scheduler.dart';
import 'package:provider/provider.dart';

import '../login_screen.dart';
import 'menu_sheet.dart';
import '../providers/global_loading_provider.dart';

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
        onPressed: () async {
          try {
            context.read<GlobalLoadingProvider>().showLoading();
          } catch (_) {}
          await Future.delayed(const Duration(milliseconds: 500));
          try {
            context.read<GlobalLoadingProvider>().hideLoading();
          } catch (_) {}
          Navigator.of(context).pushAndRemoveUntil(
            MaterialPageRoute(builder: (context) => LoginScreen()),
            (route) => false,
          );
        },
      ),
    ],
  );
}