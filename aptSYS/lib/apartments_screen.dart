import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'background_widget.dart';
import 'dashboard_screen.dart';
import 'components/navigation_bar.dart';
import 'providers/apartments_provider.dart';
import 'providers/auth_provider.dart';
import 'providers/global_loading_provider.dart';
import 'models/apartment.dart';

class ApartmentsScreen extends StatefulWidget {
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
                  final desiredItemWidth = 120.0;
                  int crossAxis = (width / desiredItemWidth).floor();
                  if (crossAxis < 2) crossAxis = 2;
                  if (crossAxis > 4) crossAxis = 4;

                  // Make items wider than tall to reduce row height
                  final itemWidth = (width - (crossAxis - 1) * 4) / crossAxis;
                  final itemHeight = 210.0; // target item height

                  return Consumer<ApartmentsProvider>(builder: (context, provider, _) {
                    if (provider.isLoading) {
                      return Center(child: Container(
                        height: 280,
                        padding: EdgeInsets.all(120),
                        child: CircularProgressIndicator(
                          valueColor: AlwaysStoppedAnimation<Color>(Theme.of(context).colorScheme.primary),
                        ),
                      ));
                    }

                    if (provider.error != null) {
                      final token = context.read<AuthProvider>().token;
                      return Center(child: Padding(
                        padding: EdgeInsets.all(24),
                        child: Column(
                          children: [
                            Text('Hata: ${provider.error}', style: TextStyle(color: Colors.white70)),
                            SizedBox(height: 8),
                            ElevatedButton(onPressed: () => provider.fetchApartments(token), child: Text('Tekrar Dene'))
                          ],
                        ),
                      ));
                    }

                    final apartments = provider.items;
                    if (apartments.isEmpty) {
                      return Center(child: Padding(padding: EdgeInsets.all(24), child: Text('Kayıtlı apartman yok.', style: TextStyle(color: Colors.white70))));
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
                                    margin: EdgeInsets.all(2),
                                    child: Material(
                                      color: Colors.transparent,
                                      child: InkWell(
                                        onTap: () {
                                          setState(() {
                                            selectedIndex = row * crossAxis + col;
                                          });
                                          final name = apartments[row * crossAxis + col].title;
                                          context.read<GlobalLoadingProvider>().showLoading();
                                          Future.delayed(Duration(milliseconds: 300), () {
                                            Navigator.push(
                                              context,
                                              MaterialPageRoute(builder: (_) => DashboardScreen(apartmentName: name)),
                                            ).then((_) {
                                              context.read<GlobalLoadingProvider>().hideLoading();
                                            });
                                          });
                                        },
                                        splashColor: Colors.white.withOpacity(0.1),
                                        highlightColor: Colors.white.withOpacity(0.1),
                                        customBorder: CircleBorder(),
                                        child: Column(
                                          mainAxisAlignment: MainAxisAlignment.center,
                                          crossAxisAlignment: CrossAxisAlignment.center,
                                          children: [
                                            Icon(Icons.apartment, size: 88, color: Colors.white70),
                                            SizedBox(height: 8),
                                            Text(
                                              apartments[row * crossAxis + col].title,
                                              style: TextStyle(color: Colors.white70, fontSize: 14),
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