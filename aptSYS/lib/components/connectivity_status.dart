import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../settings.dart';
import 'connectivity_socket_stub.dart'
  if (dart.library.io) 'connectivity_socket_io.dart' as socket_impl;
import 'package:http/http.dart' as http;

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

      _error = null;
      final ok = await socket_impl.checkSocket(host, port, timeout: const Duration(seconds: 3));
      if (ok) {
        setState(() {
          _connected = true;
          _lastChecked = DateTime.now();
        });
      } else {
        // Try HTTP GET as a fallback (works on web). May fail due to CORS.
        try {
          final resp = await http.get(Uri.parse(settings.baseUrl)).timeout(const Duration(seconds: 4));
          final success = resp.statusCode >= 200 && resp.statusCode < 400;
          setState(() {
            _connected = success;
            _lastChecked = DateTime.now();
            if (!success) _error = 'HTTP ping failed: ${resp.statusCode}';
          });
        } catch (e) {
          setState(() {
            _connected = false;
            _error = e.toString();
            _lastChecked = DateTime.now();
          });
        }
      }
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
        color: Colors.white.withValues(alpha: 0.03),
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
                  Text(_error!, style: TextStyle(color: Colors.redAccent.withValues(alpha: 0.9), fontSize: 11), maxLines: 2, overflow: TextOverflow.ellipsis),
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
