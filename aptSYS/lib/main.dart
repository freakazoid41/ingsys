import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'apartments_screen.dart';
import 'login_screen.dart';
import 'providers/auth_provider.dart';
import 'settings.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final settings = AppSettings();
  await settings.loadSettings();

  runApp(MyApp(settings: settings));
}

class MyApp extends StatelessWidget {
  final AppSettings settings;

  const MyApp({Key? key, required this.settings}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        Provider<AppSettings>.value(value: settings),
        ChangeNotifierProvider<AuthProvider>(
          create: (context) => AuthProvider(settings.baseUrl),
        ),
      ],
      child: MaterialApp(
        title: 'Flutter Login Demo',
        debugShowCheckedModeBanner: false,
        home: AuthWrapper(),
      ),
    );
  }
}

class AuthWrapper extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final authProvider = Provider.of<AuthProvider>(context);
    return authProvider.isLoggedIn ? ApartmentsScreen() : LoginScreen();
  }
}