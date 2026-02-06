import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:flutter/foundation.dart';

import 'background_widget.dart';
import 'dashboard_screen.dart';
import 'components/navigation_bar.dart';
import 'providers/apartments_provider.dart';
import 'providers/auth_provider.dart';
import 'providers/global_loading_provider.dart';

class ApartmentsScreen extends StatefulWidget {
  const ApartmentsScreen({super.key});

  @override
  _ApartmentsScreenState createState() => _ApartmentsScreenState();
}

class _ApartmentsScreenState extends State<ApartmentsScreen> {
  int? selectedIndex;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      // Trigger initial fetch
      final provider = context.read<ApartmentsProvider>();
      final token = context.read<AuthProvider>().token;
      provider.fetchApartments(token);
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: appAppBar('Apartments', context),
      body: Stack(
        fit: StackFit.expand,
        children: [
          BackgroundWidget(),
          SafeArea(
            child: Center(
              child: LayoutBuilder(builder: (context, constraints) {
                  final width = constraints.maxWidth - 24;
                  // desired item width (including spacing)
                  const desiredItemWidth = 120.0;
                  int crossAxis = (width / desiredItemWidth).floor();
                  if (crossAxis < 2) crossAxis = 2;
                  if (crossAxis > 4) crossAxis = 4;

                  // Make items wider than tall to reduce row height
                  final itemWidth = (width - (crossAxis - 1) * 4) / crossAxis;
                  const itemHeight = 210.0; // target item height

                  return Consumer<ApartmentsProvider>(builder: (context, provider, _) {
                    if (provider.isLoading) {
                      return Center(child: Container(
                        height: 280,
                        padding: const EdgeInsets.all(120),
                        child: CircularProgressIndicator(
                          valueColor: AlwaysStoppedAnimation<Color>(Theme.of(context).colorScheme.primary),
                        ),
                      ));
                    }

                    if (provider.error != null) {
                      final token = context.read<AuthProvider>().token;
                      return Center(child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: Column(
                          children: [
                            Text('Hata: ${provider.error}', style: const TextStyle(color: Colors.white70)),
                            const SizedBox(height: 8),
                            ElevatedButton(onPressed: () => provider.fetchApartments(token), child: const Text('Tekrar Dene'))
                          ],
                        ),
                      ));
                    }

                    final apartments = provider.items;
                    if (apartments.isEmpty) {
                      return const Center(child: Padding(padding: EdgeInsets.all(24), child: Text('Kayıtlı apartman yok.', style: TextStyle(color: Colors.white70))));
                    }

                    return ListView(
                      shrinkWrap: true,
                      children: [
                        for (int row = 0; row < (apartments.length / crossAxis).ceil(); row++)
                          Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              for (int col = 0; col < crossAxis; col++)
                                if (row * crossAxis + col < apartments.length)
                                  Container(
                                    width: itemWidth,
                                    height: itemHeight,
                                    margin: const EdgeInsets.all(2),
                                    child: Material(
                                      color: Colors.transparent,
                                      child: InkWell(
                                        onTap: () {
                                          setState(() {
                                            selectedIndex = row * crossAxis + col;
                                          });
                                          final apartment = apartments[row * crossAxis + col];
                                          // store selected apartment in provider so other pages can access grp_code
                                          context.read<ApartmentsProvider>().setSelectedApartment(apartment);
                                          if (kDebugMode) {
                                            try {
                                              print('ApartmentsScreen: tapped apartment id=${apartment.id} op_key=${apartment.opKey} code=${apartment.code}');
                                            } catch (_) {}
                                          }
                                          final name = apartment.title;
                                          context.read<GlobalLoadingProvider>().showLoading();
                                          Future.delayed(const Duration(milliseconds: 300), () {
                                            Navigator.push(
                                              context,
                                              MaterialPageRoute(builder: (_) => DashboardScreen(apartmentName: name)),
                                            ).then((_) {
                                              if (context.mounted) context.read<GlobalLoadingProvider>().hideLoading();
                                            });
                                          });
                                        },
                                        splashColor: Colors.white.withValues(alpha: 0.1),
                                        highlightColor: Colors.white.withValues(alpha: 0.1),
                                        customBorder: const CircleBorder(),
                                        child: Column(
                                          mainAxisAlignment: MainAxisAlignment.center,
                                          crossAxisAlignment: CrossAxisAlignment.center,
                                          children: [
                                            Icon(Icons.apartment, size: 88, color: Colors.white70),
                                            const SizedBox(height: 8),
                                            Text(
                                              apartments[row * crossAxis + col].title,
                                              style: const TextStyle(color: Colors.white70, fontSize: 14),
                                              textAlign: TextAlign.center,
                                              overflow: TextOverflow.ellipsis,
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                  )
                            ],
                          )
                      ],
                    );
                  });
                }),
              ),
            ),
          
        ],
      ),
    );
  }
}