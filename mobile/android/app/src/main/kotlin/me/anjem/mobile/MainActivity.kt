package me.anjem.mobile

import android.app.NotificationManager
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    private val CHANNEL = "me.anjem.driver/notifications"

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, CHANNEL)
            .setMethodCallHandler { call, result ->
                if (call.method == "cancelGeolocatorNotification") {
                    // ID 75415 is ONGOING_NOTIFICATION_ID from geolocator_android 4.6.2
                    // (GeolocatorLocationService.java:31). Update if the plugin changes it.
                    val nm = getSystemService(NOTIFICATION_SERVICE) as? NotificationManager
                    nm?.cancel(75415)
                    result.success(null)
                } else {
                    result.notImplemented()
                }
            }
    }
}
