import 'dart:io';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../settings.dart';

// When running `flutter test`, the environment variable FLUTTER_TEST is set.
// Use a compile-time constant to avoid starting network timers during widget tests.
const bool _kInFlutterTest = bool.fromEnvironment('FLUTTER_TEST', defaultValue: false);

class ConnectivityStatus extends StatefulWidget {
  const ConnectivityStatus({Key? key}) : super(key: key);

  @override
  _ConnectivityStatusState createState() => _ConnectivityStatusState();
}

class _ConnectivityStatusState extends State<ConnectivityStatus> {
  bool? _connected;
  String? _error;
  DateTime? _lastChecked;
  bool _checking = false;

  @override
  void initState() {
    super.initState();
    // Do not run automatic network checks on init to avoid starting sockets/timers
    // during widget tests. User can tap the refresh button to trigger a check.
  }

  Future<void> _check() async {
    setState(() {
      _checking = true;
      _error = null;
    });

    try {
      final settings = context.read<AppSettings>();
      final uri = Uri.parse(settings.baseUrl);
      final host = uri.host;
      final port = uri.hasPort ? uri.port : (uri.scheme == 'https' ? 443 : 80);

      final socket = await Socket.connect(host, port, timeout: Duration(seconds: 3));
      socket.destroy();

      setState(() {
        _connected = true;
        _lastChecked = DateTime.now();
      });
    } catch (e) {
      setState(() {
        _connected = false;
        _error = e.toString();
        _lastChecked = DateTime.now();
      });
    } finally {
      setState(() => _checking = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final settings = context.watch<AppSettings>();

    Color color;
    String statusText;
    if (_checking) {
      color = Colors.grey;
      statusText = 'Checking...';
    } else if (_connected == true) {
      color = Colors.greenAccent;
      statusText = 'Reachable';
    } else if (_connected == false) {
      color = Colors.redAccent;
      statusText = 'Unreachable';
    } else {
      color = Colors.grey;
      statusText = 'Unknown';
    }

    return Container(
      padding: EdgeInsets.symmetric(vertical: 8, horizontal: 10),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.03),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        children: [
          Container(width: 10, height: 10, decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
          SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('baseUrl: ${settings.baseUrl}', style: TextStyle(color: Colors.white70, fontSize: 12)),
                SizedBox(height: 4),
                Row(
                  children: [
                    Text('Status: $statusText', style: TextStyle(color: Colors.white70, fontSize: 12)),
                    if (_lastChecked != null) ...[
                      SizedBox(width: 8),
                      Text('(${_lastChecked!.toIso8601String().split('T').last.split('.').first})', style: TextStyle(color: Colors.white54, fontSize: 11)),
                    ]
                  ],
                ),
                if (_error != null) SizedBox(height: 4),
                if (_error != null)
                  Text(_error!, style: TextStyle(color: Colors.redAccent.withOpacity(0.9), fontSize: 11), maxLines: 2, overflow: TextOverflow.ellipsis),
              ],
            ),
          ),
          SizedBox(width: 8),
          IconButton(
            icon: _checking ? SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : Icon(Icons.refresh, color: Colors.white70, size: 18),
            onPressed: _checking ? null : _check,
            tooltip: 'Check connectivity',
          ),
        ],
      ),
    );
  }
}
