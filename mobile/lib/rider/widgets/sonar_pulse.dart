import 'package:flutter/material.dart';

/// Animated sonar pulse — 3 rings expanding outward from center, continuously.
///
/// Uses a single AnimationController with addListener+setState to guarantee
/// per-frame rebuilds. Ring positions computed mathematically from controller
/// value so they stay perfectly staggered.
class SonarPulse extends StatefulWidget {
  final bool active;
  final Color color;
  final double maxRadius;

  const SonarPulse({
    super.key,
    this.active = true,
    this.color = const Color(0xFF2196F3),
    this.maxRadius = 80,
  });

  @override
  State<SonarPulse> createState() => _SonarPulseState();
}

class _SonarPulseState extends State<SonarPulse>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  static const _ringCount = 3;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 3000),
    )..addListener(() {
        if (mounted) setState(() {});
      });
    if (widget.active) _controller.repeat();
  }

  @override
  void didUpdateWidget(SonarPulse old) {
    super.didUpdateWidget(old);
    if (widget.active && !_controller.isAnimating) {
      _controller.repeat();
    } else if (!widget.active && _controller.isAnimating) {
      _controller.stop();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final diameter = widget.maxRadius * 2;
    return SizedBox(
      width: diameter,
      height: diameter,
      child: Stack(
        alignment: Alignment.center,
        children: List.generate(_ringCount, (i) {
          // Each ring offset by 1/3 of the cycle
          final t = (_controller.value + i / _ringCount) % 1.0;
          final size = t * diameter;
          final opacity = (1.0 - t).clamp(0.0, 0.45);
          if (size < 2) return const SizedBox.shrink();
          return Container(
            width: size,
            height: size,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(
                color: widget.color.withValues(alpha: opacity),
                width: 2.0,
              ),
            ),
          );
        }),
      ),
    );
  }
}
