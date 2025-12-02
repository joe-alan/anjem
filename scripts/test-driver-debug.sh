#!/bin/bash

# Debug Driver Flow - Test WebSocket and API synchronization
# This script helps debug why riders aren't receiving WebSocket events

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[0;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
API_URL="${API_URL:-http://localhost:8000/api/v1}"
DRIVER_TOKEN=""
RIDER_TOKEN=""
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="$(dirname "$SCRIPT_DIR")/backend"

# Function to print colored messages
print_step() {
  echo ""
  echo -e "${CYAN}═══════════════════════════════════════${NC}"
  echo -e "${CYAN}$1${NC}"
  echo -e "${CYAN}═══════════════════════════════════════${NC}"
}

print_info() {
  echo -e "${BLUE}ℹ  $1${NC}"
}

print_success() {
  echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
  echo -e "${RED}✗ $1${NC}"
}

print_warning() {
  echo -e "${YELLOW}⚠  $1${NC}"
}

print_db() {
  echo -e "${CYAN}🗄  $1${NC}"
}

# Function to check dependencies
check_dependencies() {
  if ! command -v jq &> /dev/null; then
    print_error "jq is not installed. Install: brew install jq"
    exit 1
  fi
  print_success "Dependencies OK"
}

# Function to get driver token
get_driver_token() {
  print_info "Getting driver token..."

  DRIVER_TOKEN=$(cd "$BACKEND_DIR" && php artisan tinker --execute="
    \$driver = App\Models\User::where('user_type', 'driver')
      ->orWhere('user_type', 'both')
      ->first();
    if (!\$driver) {
      echo 'ERROR: No driver found';
      exit(1);
    }
    \$token = \$driver->createToken('debug-driver-token', [
      'driver:accept-ride',
      'driver:complete-ride',
      'driver:update-location'
    ])->plainTextToken;
    echo \$token;
  " 2>/dev/null)

  if [[ "$DRIVER_TOKEN" == ERROR* ]]; then
    print_error "Failed to get driver token"
    exit 1
  fi

  print_success "Driver token: ${DRIVER_TOKEN:0:30}..."
}

# Function to check database state
check_db_state() {
  local REQUEST_ID=$1
  local LABEL=$2

  print_db "Database State - $LABEL"

  cd "$BACKEND_DIR" && psql -U jonathanalanohasiholan -d anjemme -c "
    SELECT
      rr.id as req_id,
      rr.status as req_status,
      r.id as ride_id,
      r.status as ride_status,
      r.driver_accepted_at,
      r.arrived_at,
      r.pickup_time
    FROM ride_requests rr
    LEFT JOIN rides r ON r.ride_request_id = rr.id
    WHERE rr.id = $REQUEST_ID;
  " 2>/dev/null
}

# Function to check Laravel logs for broadcasts
check_broadcast_logs() {
  print_info "Checking Laravel logs for broadcast events..."

  RECENT_BROADCASTS=$(cd "$BACKEND_DIR" && tail -n 50 storage/logs/laravel.log | grep -E "RideRequestMatched|RideStatusUpdated|broadcast" | tail -n 5)

  if [ -z "$RECENT_BROADCASTS" ]; then
    print_warning "No recent broadcast events found in logs"
  else
    echo "$RECENT_BROADCASTS"
  fi
}

# Function to accept ride
accept_ride() {
  local REQUEST_ID=$1

  print_step "STEP 1: ACCEPTING RIDE REQUEST #$REQUEST_ID"

  print_info "Before accept - checking database..."
  check_db_state $REQUEST_ID "Before Accept"

  print_info "Sending POST /rides/$REQUEST_ID/accept..."
  RESPONSE=$(curl -s -X POST "$API_URL/rides/$REQUEST_ID/accept" \
    -H "Authorization: Bearer $DRIVER_TOKEN" \
    -H "Content-Type: application/json")

  echo "Response: $RESPONSE" | jq . 2>/dev/null || echo "$RESPONSE"

  RIDE_ID=$(echo $RESPONSE | jq -r '.data.id // empty')
  SUCCESS=$(echo $RESPONSE | jq -r '.success // empty')

  if [ "$SUCCESS" != "true" ]; then
    print_error "Failed to accept ride"
    return 1
  fi

  print_success "Ride accepted! Ride ID: $RIDE_ID"

  sleep 1

  print_info "After accept - checking database..."
  check_db_state $REQUEST_ID "After Accept"

  check_broadcast_logs

  print_warning "CHECK RIDER APP: Should transition to 'Driver Matched' screen"
  echo ""
  read -p "Press Enter when you've checked the rider app..."

  echo $RIDE_ID
}

# Function to mark as arrived
mark_arrived() {
  local RIDE_ID=$1
  local REQUEST_ID=$2

  print_step "STEP 2: MARKING AS ARRIVED AT PICKUP"

  print_info "Sending PATCH /rides/$RIDE_ID/status (driver_arrived)..."
  RESPONSE=$(curl -s -X PATCH "$API_URL/rides/$RIDE_ID/status" \
    -H "Authorization: Bearer $DRIVER_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"status":"driver_arrived"}')

  echo "Response: $RESPONSE" | jq . 2>/dev/null || echo "$RESPONSE"

  SUCCESS=$(echo $RESPONSE | jq -r '.success // empty')

  if [ "$SUCCESS" != "true" ]; then
    print_error "Failed to mark as arrived"
    return 1
  fi

  print_success "Marked as arrived!"

  sleep 1
  check_db_state $REQUEST_ID "After Mark Arrived"
  check_broadcast_logs

  print_warning "CHECK RIDER APP: Should show 'Driver Arrived' status"
  echo ""
  read -p "Press Enter when you've checked the rider app..."
}

# Function to start ride
start_ride() {
  local RIDE_ID=$1
  local REQUEST_ID=$2

  print_step "STEP 3: STARTING RIDE"

  print_info "Sending PATCH /rides/$RIDE_ID/status (in_progress)..."
  RESPONSE=$(curl -s -X PATCH "$API_URL/rides/$RIDE_ID/status" \
    -H "Authorization: Bearer $DRIVER_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"status":"in_progress"}')

  echo "Response: $RESPONSE" | jq . 2>/dev/null || echo "$RESPONSE"

  SUCCESS=$(echo $RESPONSE | jq -r '.success // empty')

  if [ "$SUCCESS" != "true" ]; then
    print_error "Failed to start ride"
    return 1
  fi

  print_success "Ride started!"

  sleep 1
  check_db_state $REQUEST_ID "After Start Ride"
  check_broadcast_logs

  print_warning "CHECK RIDER APP: Should show 'Ride In Progress' screen"
  echo ""
  read -p "Press Enter when you've checked the rider app..."
}

# Function to complete ride
complete_ride() {
  local RIDE_ID=$1
  local REQUEST_ID=$2

  print_step "STEP 4: COMPLETING RIDE"

  print_info "Sending PATCH /rides/$RIDE_ID/status (completed)..."
  RESPONSE=$(curl -s -X PATCH "$API_URL/rides/$RIDE_ID/status" \
    -H "Authorization: Bearer $DRIVER_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"status":"completed","actual_distance_km":0.74,"actual_duration_minutes":5,"actual_fare_rp":5000}')

  echo "Response: $RESPONSE" | jq . 2>/dev/null || echo "$RESPONSE"

  SUCCESS=$(echo $RESPONSE | jq -r '.success // empty')

  if [ "$SUCCESS" != "true" ]; then
    print_error "Failed to complete ride"
    return 1
  fi

  print_success "Ride completed!"

  sleep 1
  check_db_state $REQUEST_ID "After Complete Ride"
  check_broadcast_logs

  print_warning "CHECK RIDER APP: Should show Rating screen"
}

# Debug mode - step through everything
debug_mode() {
  local REQUEST_ID=$1

  echo ""
  echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
  echo -e "${GREEN}║  DEBUG MODE: WebSocket Flow Testing   ║${NC}"
  echo -e "${GREEN}╚════════════════════════════════════════╝${NC}"
  echo ""

  print_info "This script will step through the driver flow"
  print_info "It will pause after each step for you to check the rider app"
  print_info "Watch for WebSocket events and screen transitions"
  echo ""
  print_warning "Make sure:"
  print_warning "  1. Backend is running (php artisan serve)"
  print_warning "  2. Reverb is running (php artisan reverb:start --debug)"
  print_warning "  3. Rider app is on 'Finding Driver' screen"
  echo ""
  read -p "Press Enter to start debugging request #$REQUEST_ID..."

  # Accept
  RIDE_ID=$(accept_ride $REQUEST_ID)

  if [ -z "$RIDE_ID" ]; then
    print_error "Cannot continue without ride ID"
    exit 1
  fi

  # Mark arrived
  mark_arrived $RIDE_ID $REQUEST_ID

  # Start ride
  start_ride $RIDE_ID $REQUEST_ID

  # Complete ride
  complete_ride $RIDE_ID $REQUEST_ID

  echo ""
  echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
  echo -e "${GREEN}║     Debug Session Complete!           ║${NC}"
  echo -e "${GREEN}╚════════════════════════════════════════╝${NC}"
  echo ""
  print_info "Summary:"
  print_info "  Request ID: $REQUEST_ID"
  print_info "  Ride ID: $RIDE_ID"
  echo ""
  print_warning "Review rider app behavior at each step"
  print_warning "Check Reverb terminal for WebSocket broadcasts"
}

# Quick accept mode
quick_accept() {
  local REQUEST_ID=$1

  print_info "Quick Accept Mode - Request #$REQUEST_ID"

  RESPONSE=$(curl -s -X POST "$API_URL/rides/$REQUEST_ID/accept" \
    -H "Authorization: Bearer $DRIVER_TOKEN" \
    -H "Content-Type: application/json")

  RIDE_ID=$(echo $RESPONSE | jq -r '.data.id // empty')
  SUCCESS=$(echo $RESPONSE | jq -r '.success // empty')

  if [ "$SUCCESS" = "true" ]; then
    print_success "Ride accepted! Ride ID: $RIDE_ID"
    check_db_state $REQUEST_ID "After Quick Accept"
  else
    print_error "Failed to accept ride"
    echo "$RESPONSE" | jq .
  fi
}

# Main script
main() {
  check_dependencies

  case "$1" in
    debug)
      if [ -z "$2" ]; then
        print_error "Usage: ./test-driver-debug.sh debug <request_id>"
        echo "Example: ./test-driver-debug.sh debug 123"
        exit 1
      fi
      get_driver_token
      debug_mode "$2"
      ;;

    accept)
      if [ -z "$2" ]; then
        print_error "Usage: ./test-driver-debug.sh accept <request_id>"
        exit 1
      fi
      get_driver_token
      quick_accept "$2"
      ;;

    help|--help|-h|"")
      echo "Debug Driver Flow - WebSocket Testing Script"
      echo ""
      echo "Usage:"
      echo "  ./test-driver-debug.sh debug <request_id>    # Step-by-step debug mode"
      echo "  ./test-driver-debug.sh accept <request_id>   # Quick accept only"
      echo ""
      echo "Debug Mode:"
      echo "  - Pauses after each step"
      echo "  - Shows database state"
      echo "  - Shows broadcast events in logs"
      echo "  - Prompts you to check rider app"
      echo "  - Uses correct status names (driver_arrived)"
      echo ""
      echo "Example:"
      echo "  ./test-driver-debug.sh debug 10"
      ;;

    *)
      print_error "Unknown command: $1"
      echo "Run './test-driver-debug.sh help' for usage"
      exit 1
      ;;
  esac
}

main "$@"
