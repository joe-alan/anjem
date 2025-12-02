import 'package:logger/logger.dart';

/// Global logger instance for the application
///
/// Usage:
/// ```dart
/// import '../../core/utils/logger.dart';
///
/// appLogger.d('Debug message');
/// appLogger.i('Info message');
/// appLogger.w('Warning message');
/// appLogger.e('Error message', error: error, stackTrace: stackTrace);
/// ```
final appLogger = Logger(
  printer: PrettyPrinter(
    methodCount: 2,
    errorMethodCount: 8,
    lineLength: 120,
    colors: true,
    printEmojis: true,
    printTime: true,
  ),
  level: Level.debug, // Change to Level.info for production
);

/// Simple logger for production (less verbose)
final productionLogger = Logger(
  printer: SimplePrinter(
    printTime: true,
  ),
  level: Level.info,
);
