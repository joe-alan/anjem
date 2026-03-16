import 'package:flutter/material.dart';

/// Animated sonar pulse rings expanding outward from center.
///
/// Communicates system state:
/// - [active] = true  → steady calm pulse (searching)
/// - [active] = false → pulse stops (pool exhausted / idle)
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

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 3000),
    );
    if (widget.active) _controller.repeat();
  }

  @override
  void didUpdateWidget(SonarPulse old) {
    super.didUpdateWidget(old);
    if (widget.active && !_controller.isAnimating) {
      _controller.repeat();
    } else if (!widget.active && _controller.isAnimating) {
      _controller.stop();
      // Animate to 0 so rings fade out gracefully
      _controller.animateTo(0, duration: const Duration(milliseconds: 600));
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, _) {
        return CustomPaint(
          size: Size(widget.maxRadius * 2, widget.maxRadius * 2),
          painter: _SonarPainter(
            progress: _controller.value,
            color: widget.color,
            maxRadius: widget.maxRadius,
            ringCount: 3,
          ),
        );
      },
    );
  }
}

class _SonarPainter extends CustomPainter {
  final double progress;
  final Color color;
  final double maxRadius;
  final int ringCount;

  _SonarPainter({
    required this.progress,
    required this.color,
    required this.maxRadius,
    required this.ringCount,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final center = Offset(size.width / 2, size.height / 2);

    for (int i = 0; i < ringCount; i++) {
      // Stagger rings evenly across the cycle
      final ringProgress = (progress + i / ringCount) % 1.0;
      final radius = ringProgress * maxRadius;
      // Fade out as ring expands
      final opacity = (1.0 - ringProgress).clamp(0.0, 0.4);

      final paint = Paint()
        ..color = color.withValues(alpha: opacity)
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2.0;

      canvas.drawCircle(center, radius, paint);
    }
  }

  @override
  bool shouldRepaint(_SonarPainter old) =>
      old.progress != progress || old.color != color;
}
