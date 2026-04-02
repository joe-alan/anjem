import 'dart:math';
import 'package:flutter/material.dart';

/// Thin circular arc that fills over a countdown duration.
/// Not a spinner — has defined start and end so the rider knows how long they wait.
class RetryProgressArc extends StatelessWidget {
  /// 0.0 (empty) to 1.0 (full circle).
  final double progress;
  final double size;
  final Color color;
  final Color trackColor;

  const RetryProgressArc({
    super.key,
    required this.progress,
    this.size = 40,
    this.color = const Color(0xFF2196F3),
    this.trackColor = const Color(0xFFE0E0E0),
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: CustomPaint(
        painter: _ArcPainter(
          progress: progress.clamp(0.0, 1.0),
          color: color,
          trackColor: trackColor,
        ),
      ),
    );
  }
}

class _ArcPainter extends CustomPainter {
  final double progress;
  final Color color;
  final Color trackColor;

  _ArcPainter({
    required this.progress,
    required this.color,
    required this.trackColor,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final center = Offset(size.width / 2, size.height / 2);
    final radius = (size.width / 2) - 2;

    // Track (full circle, faint)
    final trackPaint = Paint()
      ..color = trackColor
      ..style = PaintingStyle.stroke
      ..strokeWidth = 3.0
      ..strokeCap = StrokeCap.round;
    canvas.drawCircle(center, radius, trackPaint);

    // Progress arc (starts from top, clockwise)
    if (progress > 0) {
      final arcPaint = Paint()
        ..color = color
        ..style = PaintingStyle.stroke
        ..strokeWidth = 3.0
        ..strokeCap = StrokeCap.round;

      canvas.drawArc(
        Rect.fromCircle(center: center, radius: radius),
        -pi / 2, // start at 12 o'clock
        2 * pi * progress,
        false,
        arcPaint,
      );
    }
  }

  @override
  bool shouldRepaint(_ArcPainter old) => old.progress != progress;
}
