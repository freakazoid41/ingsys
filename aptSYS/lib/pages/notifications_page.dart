import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

class NotificationsPage extends StatelessWidget {
  const NotificationsPage({Key? key}) : super(key: key);

  // Simple sample notifications
  static final List<_Notif> _sample = List.generate(
    12,
    (i) => _Notif(
      title: 'Notification ${i + 1}',
      body: 'This is the body for notification ${i + 1}.',
      time: DateTime.now().subtract(Duration(hours: i * 3 + (i % 4))),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final locale = Localizations.localeOf(context).toLanguageTag();
    return Scaffold(
      backgroundColor: Colors.transparent,
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 12.0),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('Notifications', style: Theme.of(context).textTheme.titleLarge?.copyWith(color: Colors.white)),
                  Text('${_sample.length}', style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: Colors.white70)),
                ],
              ),
            ),
            const SizedBox(height: 4),
            Expanded(
              child: ListView.separated(
                padding: const EdgeInsets.symmetric(horizontal: 12.0, vertical: 8.0),
                itemCount: _sample.length,
                separatorBuilder: (_, __) => const SizedBox(height: 8),
                itemBuilder: (c, i) {
                  final n = _sample[i];
                  return InkWell(
                    borderRadius: BorderRadius.circular(12),
                    onTap: () {
                      showDialog(
                        context: context,
                        builder: (_) => AlertDialog(
                          title: Text(n.title),
                          content: Text('${n.body}\n\n${DateFormat.yMMMMd(locale).add_jm().format(n.time)}'),
                          actions: [TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Close'))],
                        ),
                      );
                    },
                    child: Container(
                      decoration: BoxDecoration(
                        color: Theme.of(context).colorScheme.surface.withValues(alpha: 0.04),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: Theme.of(context).dividerColor.withValues(alpha: 0.06)),
                      ),
                      padding: const EdgeInsets.symmetric(horizontal: 12.0, vertical: 12.0),
                      child: Row(
                        children: [
                          CircleAvatar(
                            radius: 20,
                            backgroundColor: Theme.of(context).colorScheme.primary.withValues(alpha: 0.12),
                            child: Icon(Icons.notifications, size: 18, color: Theme.of(context).colorScheme.primary),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(n.title, style: Theme.of(context).textTheme.titleMedium?.copyWith(color: Colors.white)),
                                const SizedBox(height: 6),
                                Text(n.body, style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: Colors.white70)),
                              ],
                            ),
                          ),
                          const SizedBox(width: 8),
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              Text(DateFormat.jm(locale).format(n.time), style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.white60)),
                              const SizedBox(height: 6),
                              Text(DateFormat.yMMMd(locale).format(n.time), style: Theme.of(context).textTheme.bodySmall?.copyWith(fontSize: 11, color: Colors.white60)),
                            ],
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Notif {
  final String title;
  final String body;
  final DateTime time;
  _Notif({required this.title, required this.body, required this.time});
}
