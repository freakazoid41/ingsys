import 'package:flutter/material.dart';

class ContactPage extends StatelessWidget {
  const ContactPage({Key? key}) : super(key: key);

  static final List<Map<String, String>> _contacts = [
    {"name": "Ece Erinalcik", "phone": "(537) 426-31-71", "apt": "A-1", "group": "apartments"},
    {"name": "Levent Gür", "phone": "(532) 232-33-21", "apt": "A-2", "group": "apartments"},
    {"name": "Murat Günel", "phone": "(532) 424-57-17", "apt": "A-3", "group": "apartments"},
    {"name": "Muharrem Işık", "phone": "(532) 681-07-21", "apt": "A-4", "group": "apartments"},
    {"name": "Seher Ergin", "phone": "(537) 711-32-09", "apt": "A-5", "group": "apartments"},
    {"name": "Sevin Özdöker", "phone": "(538) 250-68-55", "apt": "A-6", "group": "apartments"},
    {"name": "Tuncer Tokgöz", "phone": "(532) 465-37-37", "apt": "A-7", "group": "apartments"},
    {"name": "Orçun Bakır (Kiracı)", "phone": "(530) 102-39-06", "apt": "A-7", "group": "apartments"},
    {"name": "Türkan Önermitürk", "phone": "(532) 436-57-65", "apt": "A-8", "group": "apartments"},
    {"name": "Nagehan Çelik (Kiracı)", "phone": "(537) 673-97-98", "apt": "A-8", "group": "apartments"},
    {"name": "Zafer Ergin", "phone": "(533) 351-23-12", "apt": "B-1", "group": "management"},
    {"name": "Kadir Bozat", "phone": "(543) 882-69-76", "apt": "B-2", "group": "management"},
    {"name": "Levent İşçi", "phone": "(555) 111-22-33", "apt": "-", "group": "workers"},
    {"name": "Merve Usta", "phone": "(555) 444-55-66", "apt": "-", "group": "workers"},
  ];

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 4,
      child: Scaffold(
        backgroundColor: Colors.transparent,
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(16.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.02),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
                  child: _CustomTabSelector(),
                ),
                SizedBox(height: 12),
                Expanded(
                  child: TabBarView(
                    children: [
                      _buildGridFor(context, 'all'),
                      _buildGridFor(context, 'management'),
                      _buildGridFor(context, 'apartments'),
                      _buildGridFor(context, 'workers'),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildGridFor(BuildContext context, String group) {
    final constraints = MediaQuery.of(context).size.width;
    int crossAxisCount = 4;
    if (constraints < 600) {
      crossAxisCount = 1;
    } else if (constraints < 900) {
      crossAxisCount = 2;
    } else if (constraints < 1200) {
      crossAxisCount = 3;
    }

    final items = group == 'all'
        ? _contacts
        : _contacts.where((c) => c['group'] == group).toList();

    if (items.isEmpty) {
      return Center(child: Text('No contacts', style: TextStyle(color: Colors.white70)));
    }

    return GridView.builder(
      itemCount: items.length,
      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: crossAxisCount,
        crossAxisSpacing: 16,
        mainAxisSpacing: 16,
        childAspectRatio: 3.1,
      ),
      itemBuilder: (context, index) {
        final c = items[index];
        return ContactCard(
          name: c['name']!,
          phone: c['phone']!,
          apt: c['apt']!,
        );
      },
    );
  }

}

class _CustomTabSelector extends StatelessWidget {
  final List<String> _labels = const [
    'Bütün Bağlantılar',
    'Yönetim',
    'Daireler',
    'Proje Çalışanları',
  ];

  @override
  Widget build(BuildContext context) {
    final TabController? controller = DefaultTabController.of(context);
    if (controller == null) return SizedBox.shrink();

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: List.generate(_labels.length, (i) {
          return Padding(
            padding: const EdgeInsets.symmetric(horizontal: 6.0),
            child: GestureDetector(
              onTap: () {
                controller.animateTo(i);
              },
              child: AnimatedBuilder(
                animation: controller,
                builder: (context, _) {
                  final selected = controller.index == i;
                  return Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    decoration: BoxDecoration(
                      color: selected ? Colors.white.withOpacity(0.06) : Colors.transparent,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      _labels[i],
                      style: TextStyle(
                        color: selected ? Colors.white : Colors.white70,
                        fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
                      ),
                    ),
                  );
                },
              ),
            ),
          );
        }),
      ),
    );
  }
}

class ContactCard extends StatelessWidget {
  final String name;
  final String phone;
  final String apt;

  const ContactCard({Key? key, required this.name, required this.phone, required this.apt}) : super(key: key);

  String _initials(String fullName) {
    final parts = fullName.split(RegExp(r"\s+"));
    if (parts.length == 1) return parts[0].substring(0, parts[0].length >= 2 ? 2 : 1).toUpperCase();
    return (parts.first[0] + parts.last[0]).toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(12),
      child: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: [Colors.white.withOpacity(0.03), Colors.white.withOpacity(0.01)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.white.withOpacity(0.06)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.25),
              blurRadius: 8,
              offset: Offset(0, 4),
            ),
          ],
        ),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: Colors.black.withOpacity(0.55),
                shape: BoxShape.circle,
              ),
              alignment: Alignment.center,
              child: Text(
                _initials(name),
                style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
              ),
            ),
            SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    name,
                    style: TextStyle(color: Colors.white.withOpacity(0.95), fontWeight: FontWeight.w600),
                    overflow: TextOverflow.ellipsis,
                  ),
                  SizedBox(height: 8),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: Container(
                      padding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(6),
                        border: Border.all(color: Colors.white.withOpacity(0.25)),
                        color: Colors.transparent,
                      ),
                      child: Text(
                        phone,
                        style: TextStyle(color: Colors.white.withOpacity(0.9), fontSize: 12),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            SizedBox(width: 12),
            Text(
              apt,
              style: TextStyle(color: Colors.white.withOpacity(0.7), fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }
}
