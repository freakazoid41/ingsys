import 'package:flutter/cupertino.dart';

import 'apartments_screen.dart';

void main() {
  runApp(MyApp());
}

class MyApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return CupertinoApp(
      title: 'Flutter Login Demo',
      debugShowCheckedModeBanner: false,
      home: ApartmentsScreen(),
    );
  }
}