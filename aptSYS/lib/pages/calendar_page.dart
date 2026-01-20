import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

class CalendarPage extends StatefulWidget {
  const CalendarPage({Key? key}) : super(key: key);

  @override
  State<CalendarPage> createState() => _CalendarPageState();
}

class _CalendarPageState extends State<CalendarPage> {
  DateTime _visibleMonth = DateTime.now();
  DateTime _selectedDate = DateTime.now();
  bool _calendarExpanded = true;

  // Simple in-memory events map keyed by yyyy-MM-dd
  final Map<String, List<String>> _events = {
    _ymdKeyStaticOffset(0): ['Meeting with Alex — 10:00', 'Lunch — 12:30'],
    _ymdKeyStaticOffset(1): ['Project review — 09:00'],
    _ymdKeyStaticOffset(-1): ['Gym — 18:00'],
  };

  static String _ymdKeyStaticOffset(int offsetDays) {
    final d = DateTime.now().add(Duration(days: offsetDays));
    return '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
  }

  String _ymdKey(DateTime d) => '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  void _prevMonth() {
    setState(() {
      _visibleMonth = DateTime(_visibleMonth.year, _visibleMonth.month - 1, 1);
    });
  }

  void _nextMonth() {
    setState(() {
      _visibleMonth = DateTime(_visibleMonth.year, _visibleMonth.month + 1, 1);
    });
  }

  int _daysInMonth(DateTime d) {
    final next = (d.month == 12) ? DateTime(d.year + 1, 1, 1) : DateTime(d.year, d.month + 1, 1);
    return next.subtract(const Duration(days: 1)).day;
  }

  @override
  Widget build(BuildContext context) {
    final firstOfMonth = DateTime(_visibleMonth.year, _visibleMonth.month, 1);
    final weekdayOfFirst = firstOfMonth.weekday; // 1 (Mon) - 7 (Sun)
    final daysInMonth = _daysInMonth(_visibleMonth);
    final locale = Localizations.localeOf(context).toLanguageTag();

    // build a list of DateTime (including leading/trailing days) for a 6x7 grid
    final gridDays = <DateTime>[];
    final leading = weekdayOfFirst % 7; // convert Mon=1..Sun=7 to SunBased leading
    final start = firstOfMonth.subtract(Duration(days: leading));
    for (var i = 0; i < 42; i++) gridDays.add(start.add(Duration(days: i)));

    final selectedKey = _ymdKey(_selectedDate);

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 12.0, horizontal: 8.0),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              IconButton(onPressed: _prevMonth, icon: const Icon(Icons.chevron_left, color: Colors.white)),
              Expanded(
                child: Center(
                  child: Text(
                    DateFormat.yMMMM(locale).format(_visibleMonth),
                    style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600, color: Colors.white),
                  ),
                ),
              ),
              IconButton(
                onPressed: () => setState(() => _calendarExpanded = !_calendarExpanded),
                icon: Icon(_calendarExpanded ? Icons.expand_less : Icons.expand_more, color: Colors.white),
              ),
              IconButton(onPressed: _nextMonth, icon: const Icon(Icons.chevron_right, color: Colors.white)),
            ],
          ),
        ),
        if (_calendarExpanded) ...[
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8.0),
            child: Builder(builder: (ctx) {
              // generate localized short weekday names starting from Sunday
              DateTime sunday = DateTime.now();
              while (sunday.weekday != DateTime.sunday) {
                sunday = sunday.subtract(const Duration(days: 1));
              }
              final weekdayLabels = List.generate(7, (i) => DateFormat.E(locale).format(sunday.add(Duration(days: i))));
              return Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: weekdayLabels
                    .map((w) => Expanded(
                          child: Center(child: Text(w, style: const TextStyle(fontWeight: FontWeight.w600, color: Colors.white))),
                        ))
                    .toList(),
              );
            }),
          ),
          const SizedBox(height: 8),
          // Calendar grid
          SizedBox(
            height: 320,
            child: GridView.builder(
              physics: const NeverScrollableScrollPhysics(),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 7),
            itemCount: gridDays.length,
            itemBuilder: (context, idx) {
              final day = gridDays[idx];
              final isToday = _isSameDay(day, DateTime.now());
              final isSelected = _isSameDay(day, _selectedDate);
              final inMonth = day.month == _visibleMonth.month;
              final dayEvents = _events[_ymdKey(day)];

              return GestureDetector(
                onTap: () => setState(() => _selectedDate = day),
                child: Container(
                  margin: const EdgeInsets.all(4.0),
                  decoration: isSelected
                      ? BoxDecoration(
                          color: Theme.of(context).colorScheme.primary.withOpacity(0.15),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: Colors.white, width: 2),
                        )
                      : null,
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                      Text('${day.day}', style: TextStyle(color: inMonth ? Colors.white : Colors.white54, fontSize: 16)),
                      const SizedBox(height: 4),
                      if (dayEvents != null && dayEvents.isNotEmpty)
                        Container(
                          width: 4,
                          height: 4,
                          decoration: BoxDecoration(color: Colors.redAccent, shape: BoxShape.circle),
                        ),
                      if (isToday)
                        Padding(
                          padding: const EdgeInsets.only(top: 2.0),
                          child: Text('Today', style: TextStyle(fontSize: 8, color: Colors.white)),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            },
          ),
        ),
        ],
        const Divider(),
        // Events list for selected date
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12.0, vertical: 8.0),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('Events — ${DateFormat.yMMMd(locale).format(_selectedDate)}', style: const TextStyle(fontWeight: FontWeight.w600, color: Colors.white)),
              TextButton(onPressed: _addSampleEventForSelectedDay, child: const Text('Add sample', style: TextStyle(color: Colors.white))),
            ],
          ),
        ),
        Expanded(
          child: _buildEventsForSelectedDay(selectedKey),
        ),
      ],
    );
  }

  void _addSampleEventForSelectedDay() {
    final key = _ymdKey(_selectedDate);
    setState(() {
      _events.putIfAbsent(key, () => []);
      _events[key]!.add('New event — ${TimeOfDay.now().format(context)}');
    });
  }

  Widget _buildEventsForSelectedDay(String key) {
    final items = _events[key] ?? [];
    if (items.isEmpty) {
      return const Center(child: Text('No events for this day', style: TextStyle(color: Colors.white)));
    }
    return ListView.separated(
      padding: const EdgeInsets.symmetric(horizontal: 12.0),
      itemBuilder: (c, i) => Material(
        color: Colors.transparent,
        child: ListTile(title: Text(items[i], style: const TextStyle(color: Colors.white))),
      ),
      separatorBuilder: (_, __) => const Divider(height: 1),
      itemCount: items.length,
    );
  }

  // month name helper removed; using intl DateFormat for localization

  static bool _isSameDay(DateTime a, DateTime b) => a.year == b.year && a.month == b.month && a.day == b.day;
}
