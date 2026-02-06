Future<bool> checkSocket(String host, int port, {Duration timeout = const Duration(seconds: 3)}) async {
  // Socket checks are unsupported on this platform (web). Return false.
  return false;
}
