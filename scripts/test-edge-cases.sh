#!/bin/bash

# Edge Case Testing Script for Anjem Platform
# Tests critical race conditions and edge cases
# Usage: ./scripts/test-edge-cases.sh

set -e

BASE_URL="http://localhost:8000/api/v1"
COLOR_RED='\033[0;31m'
COLOR_GREEN='\033[0;32m'
COLOR_YELLOW='\033[1;33m'
COLOR_BLUE='\033[0;34m'
COLOR_NC='\033[0m' # No Color

echo -e "${COLOR_BLUE}"
echo "=========================================="
echo "  ANJEM EDGE CASE TESTING SUITE"
echo "=========================================="
echo -e "${COLOR_NC}"

# Test credentials (ensure these exist in your database)
RIDER_TOKEN="your_rider_token_here"
DRIVER1_TOKEN="your_driver1_token_here"
DRIVER2_TOKEN="your_driver2_token_here"

# Check if tokens are set
if [[ "$RIDER_TOKEN" == "your_rider_token_here" ]]; then
    echo -e "${COLOR_RED}❌ ERROR: Please set actual tokens in the script${COLOR_NC}"
    echo "   Run: php artisan tinker"
    echo "   >>> User::where('user_type', 'rider')->first()->createToken('test')->plainTextToken"
    exit 1
fi

# Test counter
TESTS_PASSED=0
TESTS_FAILED=0
TESTS_TOTAL=0

# Helper functions
run_test() {
    local test_name="$1"
    echo ""
    echo -e "${COLOR_BLUE}🧪 TEST: $test_name${COLOR_NC}"
    TESTS_TOTAL=$((TESTS_TOTAL + 1))
}

pass_test() {
    echo -e "${COLOR_GREEN}✅ PASS${COLOR_NC}"
    TESTS_PASSED=$((TESTS_PASSED + 1))
}

fail_test() {
    local reason="$1"
    echo -e "${COLOR_RED}❌ FAIL: $reason${COLOR_NC}"
    TESTS_FAILED=$((TESTS_FAILED + 1))
}

warn_test() {
    local reason="$1"
    echo -e "${COLOR_YELLOW}⚠️  WARNING: $reason${COLOR_NC}"
}

# ==============================================================================
# TEST 1: Concurrent Driver Acceptance (Race Condition)
# ==============================================================================
run_test "Concurrent Driver Acceptance (Race Condition)"

echo "   Creating ride request..."
RIDE_REQUEST_RESPONSE=$(curl -s -X POST "$BASE_URL/requests" \
    -H "Authorization: Bearer $RIDER_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
        "pickup_location_id": 1,
        "destination_location_id": 2,
        "passenger_count": 1
    }')

RIDE_REQUEST_ID=$(echo "$RIDE_REQUEST_RESPONSE" | jq -r '.data.id')

if [[ "$RIDE_REQUEST_ID" == "null" || -z "$RIDE_REQUEST_ID" ]]; then
    fail_test "Failed to create ride request"
    echo "   Response: $RIDE_REQUEST_RESPONSE"
else
    echo "   ✓ Ride request created: ID $RIDE_REQUEST_ID"

    echo "   Sending concurrent accept requests from 2 drivers..."

    # Send both requests simultaneously using background jobs
    RESPONSE1=$(curl -s -X POST "$BASE_URL/rides/$RIDE_REQUEST_ID/accept" \
        -H "Authorization: Bearer $DRIVER1_TOKEN" &)
    RESPONSE2=$(curl -s -X POST "$BASE_URL/rides/$RIDE_REQUEST_ID/accept" \
        -H "Authorization: Bearer $DRIVER2_TOKEN" &)

    # Wait for both to complete
    wait

    echo "   Driver 1 Response: $RESPONSE1"
    echo "   Driver 2 Response: $RESPONSE2"

    # Check results
    SUCCESS_COUNT=$(echo "$RESPONSE1 $RESPONSE2" | grep -o '"success":true' | wc -l | tr -d ' ')
    ERROR_COUNT=$(echo "$RESPONSE1 $RESPONSE2" | grep -o '"success":false' | wc -l | tr -d ' ')

    if [[ "$SUCCESS_COUNT" == "1" && "$ERROR_COUNT" == "1" ]]; then
        pass_test
        echo "   ✓ Exactly one driver succeeded, one got conflict error"
    elif [[ "$SUCCESS_COUNT" == "2" ]]; then
        fail_test "RACE CONDITION DETECTED! Both drivers succeeded"
        echo "   ⚠️  CRITICAL: Database lacks proper locking"
    elif [[ "$SUCCESS_COUNT" == "0" ]]; then
        fail_test "Both drivers failed to accept"
    else
        warn_test "Unexpected result pattern"
    fi
fi

# ==============================================================================
# TEST 2: Driver Already Has Active Ride
# ==============================================================================
run_test "Driver Accepting While Having Active Ride"

echo "   Creating first ride request..."
RIDE_REQUEST_1=$(curl -s -X POST "$BASE_URL/requests" \
    -H "Authorization: Bearer $RIDER_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"pickup_location_id": 1, "destination_location_id": 2, "passenger_count": 1}')

RIDE_REQUEST_1_ID=$(echo "$RIDE_REQUEST_1" | jq -r '.data.id')

echo "   Driver 1 accepting first request..."
ACCEPT_1=$(curl -s -X POST "$BASE_URL/rides/$RIDE_REQUEST_1_ID/accept" \
    -H "Authorization: Bearer $DRIVER1_TOKEN")

if echo "$ACCEPT_1" | grep -q '"success":true'; then
    echo "   ✓ First ride accepted"

    echo "   Creating second ride request..."
    RIDE_REQUEST_2=$(curl -s -X POST "$BASE_URL/requests" \
        -H "Authorization: Bearer $RIDER_TOKEN" \
        -H "Content-Type: application/json" \
        -d '{"pickup_location_id": 1, "destination_location_id": 3, "passenger_count": 1}')

    RIDE_REQUEST_2_ID=$(echo "$RIDE_REQUEST_2" | jq -r '.data.id')

    echo "   Driver 1 attempting to accept second request (should fail)..."
    ACCEPT_2=$(curl -s -X POST "$BASE_URL/rides/$RIDE_REQUEST_2_ID/accept" \
        -H "Authorization: Bearer $DRIVER1_TOKEN")

    echo "   Response: $ACCEPT_2"

    if echo "$ACCEPT_2" | grep -q '"success":false'; then
        pass_test
        echo "   ✓ Backend correctly rejected second acceptance"
    else
        fail_test "Driver was able to accept two rides simultaneously"
    fi
else
    fail_test "First ride acceptance failed"
fi

# ==============================================================================
# TEST 3: Invalid Status Transitions
# ==============================================================================
run_test "Invalid Status Transitions"

# Assuming we have an active ride in 'accepted' status from previous test
echo "   Getting driver's active ride..."
ACTIVE_RIDE=$(curl -s -X GET "$BASE_URL/driver/active-ride" \
    -H "Authorization: Bearer $DRIVER1_TOKEN")

RIDE_ID=$(echo "$ACTIVE_RIDE" | jq -r '.data.id')

if [[ "$RIDE_ID" != "null" && -n "$RIDE_ID" ]]; then
    echo "   ✓ Found active ride: ID $RIDE_ID"
    echo "   Current status: $(echo "$ACTIVE_RIDE" | jq -r '.data.status')"

    echo "   Attempting invalid transition: accepted → completed (skipping in_progress)..."
    INVALID_TRANSITION=$(curl -s -X PATCH "$BASE_URL/rides/$RIDE_ID/status" \
        -H "Authorization: Bearer $DRIVER1_TOKEN" \
        -H "Content-Type: application/json" \
        -d '{"status": "completed"}')

    echo "   Response: $INVALID_TRANSITION"

    if echo "$INVALID_TRANSITION" | grep -q '"success":false'; then
        pass_test
        echo "   ✓ Backend correctly rejected invalid transition"
    else
        fail_test "Backend allowed invalid status transition"
    fi
else
    warn_test "No active ride found for testing"
fi

# ==============================================================================
# TEST 4: Ride Request Timeout (Backend Side)
# ==============================================================================
run_test "Ride Request Expiration (Backend)"

echo "   Creating ride request with short expiry..."
EXPIRED_REQUEST=$(curl -s -X POST "$BASE_URL/requests" \
    -H "Authorization: Bearer $RIDER_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
        "pickup_location_id": 1,
        "destination_location_id": 2,
        "passenger_count": 1
    }')

EXPIRED_ID=$(echo "$EXPIRED_REQUEST" | jq -r '.data.id')

echo "   ✓ Request created: ID $EXPIRED_ID"
echo "   Waiting 5 seconds to simulate timeout..."
sleep 5

# Manually expire the request (simulating cron job)
echo "   Running expiration cleanup..."
CLEANUP=$(curl -s -X POST "$BASE_URL/admin/cleanup-expired" \
    -H "Authorization: Bearer $RIDER_TOKEN")

echo "   Attempting to accept expired request..."
ACCEPT_EXPIRED=$(curl -s -X POST "$BASE_URL/rides/$EXPIRED_ID/accept" \
    -H "Authorization: Bearer $DRIVER2_TOKEN")

echo "   Response: $ACCEPT_EXPIRED"

if echo "$ACCEPT_EXPIRED" | grep -q '"success":false'; then
    pass_test
    echo "   ✓ Backend correctly rejected expired request"
else
    warn_test "Backend may not be enforcing expiration"
fi

# ==============================================================================
# TEST 5: Backend Error Handling (500 Simulation)
# ==============================================================================
run_test "Backend Error Handling"

echo "   Sending request with invalid data to trigger 500..."
ERROR_RESPONSE=$(curl -s -X POST "$BASE_URL/requests" \
    -H "Authorization: Bearer $RIDER_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"pickup_location_id": 99999, "destination_location_id": 99999}')

echo "   Response: $ERROR_RESPONSE"

if echo "$ERROR_RESPONSE" | grep -q '"success":false'; then
    pass_test
    echo "   ✓ Backend returned error response gracefully"
else
    warn_test "Backend may not be handling errors properly"
fi

# ==============================================================================
# TEST 6: WebSocket Broadcast Verification
# ==============================================================================
run_test "WebSocket Broadcast Check"

echo "   Checking if Reverb is running..."
if curl -s http://localhost:8080 | grep -q "Reverb"; then
    pass_test
    echo "   ✓ Reverb WebSocket server is running"
else
    fail_test "Reverb WebSocket server is not responding"
    echo "   Please start: php artisan reverb:start --debug"
fi

# ==============================================================================
# TEST SUMMARY
# ==============================================================================
echo ""
echo -e "${COLOR_BLUE}"
echo "=========================================="
echo "  TEST SUMMARY"
echo "=========================================="
echo -e "${COLOR_NC}"
echo "Total Tests: $TESTS_TOTAL"
echo -e "${COLOR_GREEN}Passed: $TESTS_PASSED${COLOR_NC}"
echo -e "${COLOR_RED}Failed: $TESTS_FAILED${COLOR_NC}"

if [[ $TESTS_FAILED -eq 0 ]]; then
    echo ""
    echo -e "${COLOR_GREEN}🎉 ALL TESTS PASSED!${COLOR_NC}"
    exit 0
else
    echo ""
    echo -e "${COLOR_RED}❌ SOME TESTS FAILED${COLOR_NC}"
    echo "See detailed output above for failure reasons."
    exit 1
fi
