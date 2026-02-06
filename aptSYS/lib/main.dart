import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:flutter_libphonenumber/flutter_libphonenumber.dart' as flnp;

import 'apartments_screen.dart';
import 'login_screen.dart';
import 'providers/auth_provider.dart';
import 'settings.dart';
import 'providers/apartments_provider.dart';
import 'providers/global_loading_provider.dart';
import 'providers/flat_provider.dart';
import 'theme.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final settings = AppSettings();
  await settings.loadSettings();

  // Initialize native phone number formatting data
  try {
    await flnp.init();
  } catch (e) {
    // ignore init errors; formatter will fallback to basic formatting
  }

  runApp(MyApp(settings: settings));
}

class MyApp extends StatelessWidget {
  final AppSettings settings;

  const MyApp({super.key, required this.settings});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        Provider<AppSettings>.value(value: settings),
        ChangeNotifierProvider<AuthProvider>(
          create: (context) => AuthProvider(settings.baseUrl),
        ),
        ChangeNotifierProvider<ApartmentsProvider>(
          create: (context) => ApartmentsProvider(settings.baseUrl),
        ),
        ChangeNotifierProvider<FlatProvider>(
          create: (context) => FlatProvider(settings.baseUrl),
        ),
        ChangeNotifierProvider<GlobalLoadingProvider>(
          create: (context) => GlobalLoadingProvider(),
        ),
      ],
      child: Consumer<GlobalLoadingProvider>(
        builder: (context, loadingProvider, child) {
          return Directionality(
            textDirection: TextDirection.ltr,
            child: Stack(
              children: [
                MaterialApp(
                  title: 'aptSYS',
                  debugShowCheckedModeBanner: false,
                  theme: AppTheme.theme,
                  darkTheme: AppTheme.theme,
                  themeMode: ThemeMode.dark,
                  home: AuthWrapper(),
                ),
                if (loadingProvider.isLoading)
                  Positioned.fill(
                    child: Container(
                      color: Colors.black.withValues(alpha: 0.5),
                      child: Center(
                        child: CircularProgressIndicator(
                          valueColor: AlwaysStoppedAnimation<Color>(AppTheme.accentPrimary),
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class AuthWrapper extends StatelessWidget {
  const AuthWrapper({super.key});

  @override
  Widget build(BuildContext context) {
    final authProvider = Provider.of<AuthProvider>(context);
    //return ApartmentsScreen();
    return authProvider.isLoggedIn ? ApartmentsScreen() : LoginScreen();
  }
}