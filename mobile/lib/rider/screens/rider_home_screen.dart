import 'package:flutter/material.dart';
import '../../core/config/app_config.dart';

class RiderHomeScreen extends StatelessWidget {
  const RiderHomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final config = AppConfig.instance;

    return Scaffold(
      appBar: AppBar(
        title: Text(config.appName),
        backgroundColor: config.primaryColor,
        foregroundColor: Colors.white,
      ),
      body: const Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.directions_car,
              size: 100,
              color: Colors.blue,
            ),
            SizedBox(height: 20),
            Text(
              'Welcome to Anjem Rider!',
              style: TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.bold,
              ),
            ),
            SizedBox(height: 10),
            Text(
              'Request a ride and get to your destination safely.',
              style: TextStyle(fontSize: 16),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () {
          // TODO: Implement ride request
        },
        icon: const Icon(Icons.add_location),
        label: const Text('Request Ride'),
        backgroundColor: AppConfig.instance.primaryColor,
        foregroundColor: Colors.white,
      ),
    );
  }
}