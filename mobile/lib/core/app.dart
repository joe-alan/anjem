import 'package:flutter/material.dart';
import 'config/app_config.dart';
import '../rider/screens/rider_home_screen.dart';
import '../driver/screens/driver_home_screen.dart';

class AnjerApp extends StatelessWidget {
  const AnjerApp({super.key});

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;

    return MaterialApp(
      title: config.appName,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: config.primaryColor,
          brightness: Brightness.light,
        ),
        useMaterial3: true,
      ),
      home: config.isRiderApp
          ? const RiderHomeScreen()
          : const DriverHomeScreen(),
    );
  }
}