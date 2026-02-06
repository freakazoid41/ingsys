import 'package:flutter/material.dart';

class HomePage extends StatelessWidget {
  const HomePage({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return const Center(child: Text('Home Page', style: TextStyle(fontSize: 18)));
  }
}

// Dashboard helper widgets moved here so they can be reused across pages.

Widget buildSidebarHeader(BuildContext context, String apartmentName) {
  return Row(
    children: [
      CircleAvatar(backgroundColor: Theme.of(context).colorScheme.primary, radius: 20, child: Icon(Icons.home, color: Colors.black)),
      const SizedBox(width: 12),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('aptSYS', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
            Text(apartmentName, style: Theme.of(context).textTheme.bodySmall),
          ],
        ),
      ),
    ],
  );
}

Widget buildSidebarMenu(BuildContext context) {
  const items = [
    Icons.dashboard, Icons.people, Icons.insert_chart, Icons.shopping_bag, Icons.mail,
  ];
  const labels = ['Project Management', 'CRM', 'Analytics', 'E-Commerce', 'Email'];
  return ListView.separated(
    itemCount: items.length,
    separatorBuilder: (_, __) => const SizedBox(height: 6),
    itemBuilder: (context, i) {
      return InkWell(
        borderRadius: BorderRadius.circular(8),
        onTap: () {},
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 12),
          decoration: BoxDecoration(
            color: Colors.transparent,
            borderRadius: BorderRadius.circular(8),
          ),
          child: Row(
            children: [
              Icon(items[i], color: Theme.of(context).iconTheme.color),
              const SizedBox(width: 12),
              Expanded(child: Text(labels[i], style: Theme.of(context).textTheme.bodyMedium)),
            ],
          ),
        ),
      );
    },
  );
}

Widget buildKpiRow(BuildContext context, bool isNarrow) {
  const kpis = [
    {'label': 'Total Projects', 'value': '687', 'delta': '-2.01%', 'positive': false},
    {'label': 'Total Expenses', 'value': '\$284.92K', 'delta': '+8.98%', 'positive': true},
    {'label': 'Budget Spent', 'value': '28.35%', 'delta': '+13.45%', 'positive': true},
    {'label': 'Total Budget', 'value': '\$982.12K', 'delta': '-0.54%', 'positive': false},
  ];

  return Container(
    height: isNarrow ? 220 : 92,
    child: isNarrow
        ? ListView(
            padding: EdgeInsets.zero,
            children: kpis.map((k) => Padding(padding: const EdgeInsets.only(bottom: 8), child: kpiCard(context, k))).toList(),
          )
        : Row(
            children: kpis.map((k) => Expanded(child: Padding(padding: const EdgeInsets.only(right: 12), child: kpiCard(context, k)))).toList(),
          ),
  );
}

Widget kpiCard(BuildContext context, Map k) {
  final positive = k['positive'] as bool;
  return Card(
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
    child: Padding(
      padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 14),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(k['label'], style: Theme.of(context).textTheme.bodySmall),
              const SizedBox(height: 6),
              Text(k['value'], style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
            ],
          ),
          Container(
            padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 10),
            decoration: BoxDecoration(
              color: (positive ? Theme.of(context).colorScheme.secondary.withValues(alpha: 0.08) : Theme.of(context).colorScheme.error.withValues(alpha: 0.08)),
              borderRadius: BorderRadius.circular(999),
            ),
            child: Text(k['delta'], style: Theme.of(context).textTheme.bodySmall?.copyWith(color: positive ? Theme.of(context).colorScheme.secondary : Theme.of(context).colorScheme.error)),
          ),
        ],
      ),
    ),
  );
}

Widget buildMainChartCard(BuildContext context) {
  return Card(
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('Budget and Expenses', style: Theme.of(context).textTheme.titleMedium),
              Row(children: [Icon(Icons.download, size: 18), const SizedBox(width: 8), Icon(Icons.print, size: 18)]),
            ],
          ),
          const SizedBox(height: 12),
          Expanded(
            child: Container(
              decoration: BoxDecoration(
                color: Colors.transparent,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Center(child: Text('Area chart placeholder', style: Theme.of(context).textTheme.bodySmall)),
            ),
          ),
        ],
      ),
    ),
  );
}

Widget buildSecondaryChartCard(BuildContext context) {
  return Card(
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('Budget Utilization', style: Theme.of(context).textTheme.titleMedium),
              Icon(Icons.bar_chart, size: 18),
            ],
          ),
          const SizedBox(height: 12),
          Expanded(
            child: Center(child: Text('Bar chart placeholder', style: Theme.of(context).textTheme.bodySmall)),
          ),
        ],
      ),
    ),
  );
}

Widget buildStatusCard(BuildContext context) {
  return Card(
    child: Padding(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Projects by Status', style: Theme.of(context).textTheme.titleMedium),
          Expanded(child: Center(child: Text('Column chart placeholder', style: Theme.of(context).textTheme.bodySmall))),
        ],
      ),
    ),
  );
}

Widget buildTicketsCard(BuildContext context) {
  return Card(
    child: Padding(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Tickets Reopened', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          Text('202 Reopened Tickets', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
          Expanded(child: Center(child: Text('Sparkline placeholder', style: Theme.of(context).textTheme.bodySmall))),
        ],
      ),
    ),
  );
}

Widget buildOverdueCard(BuildContext context) {
  return Card(
    child: Padding(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Overdue Projects', style: Theme.of(context).textTheme.titleMedium),
          SizedBox(
            height: 180,
            child: ListView(
              children: List.generate(4, (i) => ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text('Task ${i+1} — short description', style: Theme.of(context).textTheme.bodyMedium),
                subtitle: Row(children: [
                  Container(padding: const EdgeInsets.symmetric(horizontal:8, vertical:4), decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.04), borderRadius: BorderRadius.circular(6)), child: Text('Assignee', style: Theme.of(context).textTheme.bodySmall)),
                  const SizedBox(width: 8),
                  Container(padding: const EdgeInsets.symmetric(horizontal:8, vertical:4), decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.02), borderRadius: BorderRadius.circular(6)), child: Text('Deadline: 2023-09-01', style: Theme.of(context).textTheme.bodySmall)),
                ],),
              )),
            ),
          ),
        ],
      ),
    ),
  );
}

Widget buildCurrentTasksCard(BuildContext context) {
  return Card(
    child: Padding(
      padding: EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Current Tasks', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          SizedBox(height: 120, child: Center(child: Text('Compact tasks table', style: Theme.of(context).textTheme.bodySmall))),
        ],
      ),
    ),
  );
}
