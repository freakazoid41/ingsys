import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

import 'background_widget.dart';
import 'dashboard_screen.dart';
import 'components/navigation_bar.dart';

class ApartmentsScreen extends StatefulWidget {
  @override
  _ApartmentsScreenState createState() => _ApartmentsScreenState();
}

class _ApartmentsScreenState extends State<ApartmentsScreen> {
  final List<String> apartments = List.generate(11, (i) => 'Apt ${100 + i}');
  int? selectedIndex;

  @override
  Widget build(BuildContext context) {
    return CupertinoPageScaffold(
      navigationBar: appNavigationBar('Apartments', context),
      child: Stack(
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
                                  child: GestureDetector(
                                    onTap: () {
                                      setState(() {
                                        selectedIndex = row * crossAxis + col;
                                      });
                                      Navigator.push(
                                        context,
                                        CupertinoPageRoute(builder: (_) => DashboardScreen(apartmentName: apartments[row * crossAxis + col])),
                                      ).then((_) {
                                        setState(() {
                                          selectedIndex = null;
                                        });
                                      });
                                    },
                                    child: Column(
                                      mainAxisAlignment: MainAxisAlignment.center,
                                      crossAxisAlignment: CrossAxisAlignment.center,
                                      children: [
                                        Icon(Icons.apartment, size: 88, color: Colors.white70),
                                        SizedBox(height: 8),
                                        Text(
                                          apartments[row * crossAxis + col],
                                          style: TextStyle(color: Colors.white70, fontSize: 14),
                                          textAlign: TextAlign.center,
                                          overflow: TextOverflow.ellipsis,
                                        ),
                                      ],
                                    ),
                                  ),
                                )
                          ],
                        )
                    ],
                  );
                }),
              ),
            ),
          
        ],
      ),
    );
  }
}