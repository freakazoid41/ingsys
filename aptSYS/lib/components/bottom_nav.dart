import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_snake_navigationbar/flutter_snake_navigationbar.dart';
import 'package:flutter_keyboard_visibility/flutter_keyboard_visibility.dart';

typedef OnNavTap = void Function(int index);

class FloatingSnakeNav extends StatefulWidget {
  final int currentIndex;
  final OnNavTap onTap;

  const FloatingSnakeNav({Key? key, required this.currentIndex, required this.onTap}) : super(key: key);

  @override
  _FloatingSnakeNavState createState() => _FloatingSnakeNavState();
}

class _FloatingSnakeNavState extends State<FloatingSnakeNav> with SingleTickerProviderStateMixin {
  bool _centerPressed = false;
  late final AnimationController _blinkController;
  Animation<double>? _blinkAnim;
  Animation<double>? _blinkScale;
  bool _keyboardVisible = false;
  late final KeyboardVisibilityController _keyboardController;

  @override
  void initState() {
    super.initState();
    _blinkController = AnimationController(vsync: this, duration: const Duration(milliseconds: 700));
    _blinkAnim = Tween<double>(begin: 1.0, end: 0.1).animate(CurvedAnimation(parent: _blinkController, curve: Curves.easeInOut));
    _blinkScale = Tween<double>(begin: 1.0, end: 1.5).animate(CurvedAnimation(parent: _blinkController, curve: Curves.easeInOut));
    _blinkController.repeat(reverse: true);
    _keyboardController = KeyboardVisibilityController();
    _keyboardController.onChange.listen((bool visible) {
      setState(() => _keyboardVisible = visible);
    });
  }

  @override
  void dispose() {
    _blinkController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final pillColor = Theme.of(context).colorScheme.surface;
    // final keyboardVisible = MediaQuery.of(context).viewInsets.bottom > 0;
    final keyboardVisible = _keyboardVisible;

    return AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      height: keyboardVisible ? 0 : 120,
      child: AnimatedOpacity(
        opacity: keyboardVisible ? 0.0 : 1.0,
        duration: const Duration(milliseconds: 200),
        child: Stack(
        alignment: Alignment.bottomCenter,
        children: [
          // pill-shaped snake bar
          Positioned(
            bottom: 20,
            left: 0,
            right: 0,
            child: Center(
              child: Container(
                child: SnakeNavigationBar.color(
                  behaviour: SnakeBarBehaviour.floating,
                  snakeShape: SnakeShape.circle,
                  // color used for the snake-shaped selected background
                  snakeViewColor: Theme.of(context).colorScheme.primary,
                  backgroundColor: pillColor,
                  selectedItemColor: Theme.of(context).colorScheme.onPrimary,
                  unselectedItemColor: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.6),
                  shape: RoundedRectangleBorder(borderRadius: const BorderRadius.all(Radius.circular(40))),
                  padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 10),
                  elevation: 8,
                  currentIndex: widget.currentIndex,
                  onTap: widget.onTap,
                  items: [
                    // start: plus icon
                    BottomNavigationBarItem(icon: Icon(CupertinoIcons.add, size: 28), label: ''),
                    // next: calendar icon
                    BottomNavigationBarItem(icon: Icon(CupertinoIcons.calendar, size: 28), label: ''),
                    // center placeholder (actual center button is elevated)
                    BottomNavigationBarItem(icon: SizedBox.shrink(), label: ''),
                    // notifications with blinking dot
                    BottomNavigationBarItem(
                      icon: Stack(
                        clipBehavior: Clip.none,
                        children: [
                          Icon(CupertinoIcons.bell, size: 28),
                          Positioned(
                            right: -2,
                            top: -6,
                            child: ScaleTransition(
                              scale: _blinkScale ?? AlwaysStoppedAnimation<double>(1.0),
                              child: FadeTransition(
                                opacity: _blinkAnim ?? AlwaysStoppedAnimation<double>(1.0),
                                child: Container(
                                  width: 12,
                                  height: 12,
                                  decoration: BoxDecoration(
                                    color: Theme.of(context).colorScheme.secondary,
                                    shape: BoxShape.circle,
                                    boxShadow: [
                                      BoxShadow(color: Theme.of(context).colorScheme.secondary.withValues(alpha: 0.35), blurRadius: 6, spreadRadius: 1)
                                    ],
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                      label: '',
                    ),
                    // end: contact icon
                    BottomNavigationBarItem(icon: Icon(CupertinoIcons.person, size: 28), label: ''),
                  ],
                ),
              ),
            ),
          ),

          // elevated center button
          Positioned(
            bottom: 20,
            left: 0,
            right: 0,
            child: Center(
              child: GestureDetector(
                onTap: () => widget.onTap(2),
                onTapDown: (_) => setState(() => _centerPressed = true),
                onTapUp: (_) => setState(() => _centerPressed = false),
                onTapCancel: () => setState(() => _centerPressed = false),
                child: AnimatedScale(
                  scale: (widget.currentIndex == 2 ? 1.14 : 1.0) * (_centerPressed ? 0.88 : 1.0),
                  duration: Duration(milliseconds: 220),
                  curve: Curves.easeOutBack,
                  child: Container(
                    width: 80,
                    height: 80,
                    decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.primary,
                      shape: BoxShape.circle,
                      boxShadow: [
                        BoxShadow(
                          color: Theme.of(context).colorScheme.primary.withValues(alpha: 0.26),
                          blurRadius: 20,
                          offset: Offset(0, 12),
                        )
                      ],
                    ),
                      child: Center(
                      child: Icon(CupertinoIcons.home, color: Theme.of(context).colorScheme.onPrimary, size: 40),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    ),
  );

  }
}