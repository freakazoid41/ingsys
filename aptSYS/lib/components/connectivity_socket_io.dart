import 'dart:io';

Future<bool> checkSocket(String host, int port, {Duration timeout = const Duration(seconds: 3)}) async {
  try {
    final socket = await Socket.connect(host, port, timeout: timeout);
    socket.destroy();
    return true;
  } catch (e) {
    return false;
  }
}
