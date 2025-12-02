# Testing Scripts

This directory contains helper scripts for testing the Anjem mobile apps without requiring both rider and driver apps to be fully built.

## test-rider-flow.sh

Simulates driver actions via backend API to test the rider app flow without building the driver UI.

### Prerequisites

- **jq** (JSON processor) - Install with `brew install jq` (macOS) or `sudo apt-get install jq` (Linux)
- **curl** (should be pre-installed)
- Backend API running on `localhost:8000` (or custom URL)
- Driver test account created in database

### Quick Start

```bash
# 1. Start backend services
cd backend
php artisan serve                 # Terminal 1
php artisan reverb:start          # Terminal 2

# 2. Start rider app
cd mobile
flutter run --flavor rider        # Terminal 3

# 3. Create a ride request in the rider app
# Note the request ID from the console or UI

# 4. Simulate driver actions
cd scripts
./test-rider-flow.sh simulate 123  # Replace 123 with actual request ID
```

### Commands

#### Full Simulation
Simulates complete ride flow from acceptance to completion:
```bash
./test-rider-flow.sh simulate <request_id>
```

This will:
1. Accept the ride request
2. Send location updates (en route to pickup)
3. Mark as "arrived" at pickup
4. Start the ride (status: in_progress)
5. Send location updates (en route to destination)
6. Mark ride as "completed"

Expected duration: ~40 seconds

#### Accept Only
Just accept the ride without completing it:
```bash
./test-rider-flow.sh accept <request_id>
```

Use this when you want to manually control the ride flow or test the "waiting for driver" state.

#### Complete Ride
Complete a previously accepted ride:
```bash
./test-rider-flow.sh complete <ride_id>
```

Use this after using `accept` command, when you're ready to complete the ride.

### Environment Variables

Customize the script behavior with environment variables:

```bash
# Custom API URL (for testing on physical device)
API_URL=http://192.168.1.100:8000/api/v1 ./test-rider-flow.sh simulate 123

# Custom driver credentials
DRIVER_EMAIL=mydriver@test.com \
DRIVER_PASSWORD=mypassword \
./test-rider-flow.sh simulate 123
```

### Expected Rider App Behavior

When you run the simulation script, the rider app should:

1. **After Accept**:
   - Navigate from WaitingScreen → DriverMatchedScreen
   - Show driver details (name, photo, vehicle)
   - Display ETA to pickup

2. **During Location Updates**:
   - See driver marker moving on map
   - ETA updates in real-time

3. **After "Arrived" Status**:
   - Show "Driver has arrived" notification
   - Button changes to "Start Ride" or similar

4. **During "In Progress" Status**:
   - Navigate to ActiveRideTrackingScreen
   - See live route and driver location

5. **After "Completed" Status**:
   - Navigate to RatingScreen
   - Show ride summary and fare

### Troubleshooting

#### Script fails with "jq not found"
```bash
# Install jq
brew install jq              # macOS
sudo apt-get install jq      # Linux
```

#### Script fails with "Failed to get driver token"
- Check that driver account exists in database
- Verify `DRIVER_EMAIL` and `DRIVER_PASSWORD` are correct
- Ensure backend API is running

#### Rider app doesn't update
- Check Laravel Reverb is running (`php artisan reverb:start`)
- Verify WebSocket connection in rider app console logs
- Check backend logs for WebSocket broadcast events

#### "Request not found" or "Already accepted"
- Verify the request ID is correct
- Check request hasn't been cancelled
- Ensure request is in "pending" status

### Testing Checklist

Use this checklist when testing rider flow:

- [ ] Ride request creation succeeds
- [ ] WaitingScreen shows correct details
- [ ] Driver match triggers navigation
- [ ] DriverMatchedScreen shows driver info
- [ ] Location updates move driver marker smoothly
- [ ] ETA updates correctly
- [ ] "Arrived" status triggers notification
- [ ] "In Progress" status navigates to tracking screen
- [ ] "Completed" status navigates to rating screen
- [ ] Can submit rating successfully
- [ ] Can cancel ride before driver arrives

### Example Session

```bash
# Full test session
$ ./test-rider-flow.sh simulate 45

ℹ  Authenticating as driver...
✓ Driver authenticated
========================================
Simulating Driver Flow for Request #45
========================================

ℹ  STEP 1: Accepting ride request...
ℹ  Accepting ride request #45...
✓ Ride accepted! Ride ID: 67

ℹ  STEP 2: Simulating driver en route to pickup...
✓ Location updated: (-6.361050, 106.824050)
✓ Location updated: (-6.361100, 106.824100)
...

ℹ  STEP 3: Arriving at pickup location...
✓ Ride status updated: arrived
⚠  Driver should now be marked as 'Arrived' in rider app

...

========================================
✓ Ride simulation completed!
  Request ID: 45
  Ride ID: 67
========================================

ℹ  Check the rider app for the rating screen
```

### Tips

1. **Multiple Test Accounts**: Create multiple driver accounts to test concurrent rides
2. **Custom Delays**: Edit sleep values in script for faster/slower simulations
3. **Debug Mode**: Add `set -x` at top of script to see all curl commands
4. **Physical Device Testing**: Use your computer's local IP instead of localhost

### Advanced Usage

#### Test Multiple Rides Concurrently
```bash
# Terminal 1
./test-rider-flow.sh simulate 45 &

# Terminal 2
DRIVER_EMAIL=driver2@test.com ./test-rider-flow.sh simulate 46 &
```

#### Custom Location Path
Edit the script to modify location update coordinates for testing specific routes.

#### Integration with CI/CD
The script returns exit code 0 on success, 1 on failure, making it suitable for automated testing:

```bash
#!/bin/bash
# ci-test.sh
flutter run --flavor rider &
FLUTTER_PID=$!

sleep 30  # Wait for app to start

# Create request via API
REQUEST_ID=$(curl -s -X POST ... | jq -r '.data.id')

# Simulate driver
./test-rider-flow.sh simulate $REQUEST_ID

if [ $? -eq 0 ]; then
  echo "Test passed!"
  kill $FLUTTER_PID
  exit 0
else
  echo "Test failed!"
  kill $FLUTTER_PID
  exit 1
fi
```

## Future Scripts

Planned scripts for Phase 10+:
- `test-driver-flow.sh` - Simulate rider actions for driver app testing
- `load-test.sh` - Create multiple concurrent ride requests
- `reset-test-data.sh` - Reset database to clean testing state

---

**Last Updated**: October 29, 2025
**Phase**: 9 (Rider/Driver Core Flow)
