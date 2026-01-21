import 'package:flutter_test/flutter_test.dart';
import 'package:apt_sys/providers/auth_provider.dart';

void main() {
  late AuthProvider authProvider;

  setUp(() {
    authProvider = AuthProvider('http://localhost:8000');
  });

  group('AuthProvider', () {
    test('initial state is not logged in', () {
      expect(authProvider.isLoggedIn, false);
      expect(authProvider.isLoading, false);
      expect(authProvider.token, null);
      expect(authProvider.user, null);
    });

    test('logout clears state', () {
      // Test that logout clears the state (without making HTTP calls)
      authProvider.logout();
      expect(authProvider.isLoggedIn, false);
      expect(authProvider.token, null);
      expect(authProvider.user, null);
    });

    // Note: login and register tests would require HTTP mocking
    // For integration testing, these would be tested with a real backend
  });
}