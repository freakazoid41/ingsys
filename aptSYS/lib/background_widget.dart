import 'package:flutter/material.dart';

class BackgroundWidget extends StatelessWidget {
  const BackgroundWidget({super.key});

  @override
  Widget build(BuildContext context) {
    return Stack(
      fit: StackFit.expand,
      children: [
        Container(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [Color(0xFF3a3f66), Color(0xFF8b6b7a)],
            ),
          ),
        ),
        Positioned(
          left: -100,
          top: -80,
          child: Container(
            width: 300,
            height: 300,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: RadialGradient(colors: [Colors.purple.withValues(alpha: 0.25), Colors.transparent]),
            ),
          ),
        ),
        Positioned(
          right: -120,
          bottom: -100,
          child: Container(
            width: 360,
            height: 360,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: RadialGradient(colors: [Colors.teal.withValues(alpha: 0.22), Colors.transparent]),
            ),
          ),
        ),
      ],
    );
  }
}