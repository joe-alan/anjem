# TODO

## Mobile

- add info screen/popup after ride cancels for rider and driver (decide popup vs screen), include admin reason if present
- driver active ride screen does not dismiss when cancel arrives via WebSocket — ref.listen needs to handle cancelled/completed status and call _handleRideCompletion
- global 401 handler while app is open mid-session — currently only handles stale token at startup, if token is invalidated while on home screen the user sees API errors instead of being sent to login
- stale ride request stays on driver screen after rider cancels or another driver accepts — should vanish via WebSocket instead of erroring on accept
- driver home screen stats (total rides, today earnings, rating) do not refresh after a ride is completed
- cancellation flow not yet implemented on flutter side for both rider and driver
- driver goes online, closes app, reopens — app shows offline but backend still has them online. closing app should set driver offline and clear queue (or app reopen should sync state from backend correctly)
- same driver account can be active on two devices simultaneously — enforce single active session per driver

## Backend

- cancelRide() and completeRide() in AdminController monitoring section do not broadcast RideStatusUpdated — active sessions never know
- cancelRequest() in AdminController does not broadcast anything when a pending ride request is force-cancelled by admin
- driver status 400 on ride status update intermittently — root cause unknown, needs logging/investigation
- rework queue system entirely — current implementation breaks with multiple concurrent riders, too many edge cases. do not polish, redesign from scratch
- investigate driver_sessions and failed_job tables — appear unused, determine if they can be dropped or if they serve a purpose
