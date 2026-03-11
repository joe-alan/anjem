import 'package:flutter/material.dart';

/// Global navigator key used by FcmService to navigate without a BuildContext
/// (e.g. when a notification tap opens the app from a terminated state).
final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();
