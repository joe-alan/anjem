package me.anjem.mobile

import android.app.NotificationManager
import android.content.Intent
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
                    // Stop the zombie geolocator service that START_STICKY may have
                    // restarted after an OEM/swipe kill. stopService() triggers
                    // onDestroy() → stopForeground(REMOVE) inside the plugin.
                    // Without this, NotificationManager.cancel() is a no-op because
                    // Android re-posts notifications owned by a foreground service.
                    try {
                        val serviceIntent = Intent(
                            this,
                            Class.forName("com.baseflow.geolocator.GeolocatorLocationService")
                        )
                        stopService(serviceIntent)
                    } catch (_: Exception) {
                        // Service class not found or not running — fall through to cancel
                    }
                    // Belt-and-suspenders: cancel the notification in case
                    // stopService didn't clean it (e.g., service already stopped
                    // but notification orphaned).
                    val nm = getSystemService(NOTIFICATION_SERVICE) as? NotificationManager
                    nm?.cancel(75415) // geolocator_android 4.6.2 ONGOING_NOTIFICATION_ID
                    result.success(null)
                } else {
                    result.notImplemented()
                }
            }
    }
}
