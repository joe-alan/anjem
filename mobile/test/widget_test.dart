// This is a basic Flutter widget test.
//
// To perform an interaction with a widget in your test, use the WidgetTester
// utility in the flutter_test package. For example, you can send tap and scroll
// gestures. You can also use WidgetTester to find child widgets in the widget
// tree, read text, and verify that the values of widget properties are correct.

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:mobile/core/app.dart';
import 'package:mobile/core/config/app_config.dart';

void main() {
  testWidgets('Anjem Rider app smoke test', (WidgetTester tester) async {
    // Initialize app config for testing
    AppConfig.initialize(
      flavor: AppFlavor.rider,
      appName: 'Anjem Rider Test',
      apiBaseUrl: 'http://localhost:8000/api/v1',
      wsUrl: 'ws://localhost:8000',
      primaryColor: const Color(0xFF2196F3),
    );

    // Build our app and trigger a frame.
    await tester.pumpWidget(const AnjerApp());

    // Verify that our app shows the welcome message
    expect(find.text('Welcome to Anjem Rider!'), findsOneWidget);
    expect(find.text('Request a ride and get to your destination safely.'), findsOneWidget);

    // Verify the floating action button exists
    expect(find.byIcon(Icons.add_location), findsOneWidget);
    expect(find.text('Request Ride'), findsOneWidget);
  });

  testWidgets('Anjem Driver app smoke test', (WidgetTester tester) async {
    // Initialize app config for testing
    AppConfig.initialize(
      flavor: AppFlavor.driver,
      appName: 'Anjem Driver Test',
      apiBaseUrl: 'http://localhost:8000/api/v1',
      wsUrl: 'ws://localhost:8000',
      primaryColor: const Color(0xFF4CAF50),
    );

    // Build our app and trigger a frame.
    await tester.pumpWidget(const AnjerApp());

    // Verify that our app shows the welcome message
    expect(find.text('Welcome to Anjem Driver!'), findsOneWidget);
    expect(find.text('Start accepting rides and earn money.'), findsOneWidget);

    // Verify the floating action button exists
    expect(find.byIcon(Icons.play_arrow), findsOneWidget);
    expect(find.text('Go Online'), findsOneWidget);
  });
}
