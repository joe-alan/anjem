#!/bin/bash

# Test Rider Flow - Simulate Driver Actions
# This script simulates driver actions to test the rider app without building the driver UI

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[0;33m'
NC='\033[0m' # No Color

# Configuration
API_URL="${API_URL:-http://localhost:8000/api/v1}"
DRIVER_EMAIL="${DRIVER_EMAIL:-driver@test.com}"
DRIVER_PASSWORD="${DRIVER_PASSWORD:-password}"
DRIVER_TOKEN=""

# Function to print colored messages
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

# Function to check if jq is installed
check_dependencies() {
  if ! command -v jq &> /dev/null; then
    print_error "jq is not installed. Please install it:"
    echo "  macOS: brew install jq"
    echo "  Linux: sudo apt-get install jq"
    exit 1
  fi

  if ! command -v curl &> /dev/null; then
    print_error "curl is not installed"
    exit 1
  fi

  print_success "Dependencies checked"
}

# Function to get driver token
get_driver_token() {
  print_info "Getting driver token via database..."

  # Change to backend directory if not already there
  SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
  BACKEND_DIR="$(dirname "$SCRIPT_DIR")/backend"

  if [ ! -f "$BACKEND_DIR/artisan" ]; then
    print_error "Cannot find Laravel artisan file at $BACKEND_DIR"
    exit 1
  fi

  # Get a driver user from the database and create a token
  DRIVER_TOKEN=$(cd "$BACKEND_DIR" && php artisan tinker --execute="
    \$driver = App\Models\User::where('user_type', 'driver')->first();
    if (!\$driver) {
      echo 'ERROR: No driver found in database';
      exit(1);
    }
    \$token = \$driver->createToken('test-token', [
      'driver:accept-ride',
      'driver:complete-ride',
      'driver:update-location',
      'driver:cancel-ride'
    ])->plainTextToken;
    echo \$token;
  " 2>/dev/null)

  if [ -z "$DRIVER_TOKEN" ] || [[ "$DRIVER_TOKEN" == ERROR* ]]; then
    print_error "Failed to get driver token"
    echo "Response: $DRIVER_TOKEN"
    exit 1
  fi

  print_success "Driver token obtained: ${DRIVER_TOKEN:0:20}..."
}

# Function to accept ride request
accept_ride() {
  local REQUEST_ID=$1
  print_info "Accepting ride request #$REQUEST_ID..."

  RESPONSE=$(curl -s -X POST "$API_URL/rides/$REQUEST_ID/accept" \
    -H "Authorization: Bearer $DRIVER_TOKEN" \
    -H "Content-Type: application/json")

  # Try multiple possible JSON paths for ride ID
  RIDE_ID=$(echo $RESPONSE | jq -r '.data.id // .ride.id // .id // empty')

  if [ -z "$RIDE_ID" ]; then
    print_error "Failed to accept ride"
    echo "Response: $RESPONSE"
    return 1
  fi

  print_success "Ride accepted! Ride ID: $RIDE_ID"
  echo $RIDE_ID
}

# Function to simulate driver location updates
update_location() {
  local LAT=$1
  local LNG=$2

  RESPONSE=$(curl -s -X POST "$API_URL/driver/location" \
    -H "Authorization: Bearer $DRIVER_TOKEN" \
    -H "Content-Type: application/json" \
    -d "{\"latitude\": $LAT, \"longitude\": $LNG}")

  SUCCESS=$(echo $RESPONSE | jq -r '.success // empty')

  if [ "$SUCCESS" = "true" ] || [ -z "$SUCCESS" ]; then
    print_success "Location updated: ($LAT, $LNG)"
  else
    print_warning "Location update may have failed: $RESPONSE"
  fi
}

# Function to update ride status
update_ride_status() {
  local RIDE_ID=$1
  local STATUS=$2

  print_info "Updating ride #$RIDE_ID status to: $STATUS"

  RESPONSE=$(curl -s -X PATCH "$API_URL/rides/$RIDE_ID/status" \
    -H "Authorization: Bearer $DRIVER_TOKEN" \
    -H "Content-Type: application/json" \
    -d "{\"status\": \"$STATUS\"}")

  SUCCESS=$(echo $RESPONSE | jq -r '.success // empty')

  if [ "$SUCCESS" = "true" ] || [ -z "$SUCCESS" ]; then
    print_success "Ride status updated: $STATUS"
  else
    print_error "Failed to update ride status"
    echo "Response: $RESPONSE"
    return 1
  fi
}

# Function to simulate complete ride flow
simulate_ride() {
  local REQUEST_ID=$1

  echo ""
  echo -e "${BLUE}========================================${NC}"
  echo -e "${BLUE}Simulating Driver Flow for Request #$REQUEST_ID${NC}"
  echo -e "${BLUE}========================================${NC}"
  echo ""

  # Step 1: Accept ride
  print_info "STEP 1: Accepting ride request..."
  RIDE_ID=$(accept_ride $REQUEST_ID)

  if [ -z "$RIDE_ID" ]; then
    print_error "Cannot continue without ride ID"
    exit 1
  fi

  sleep 2

  # Step 2: Simulate driving to pickup (5 location updates over 10 seconds)
  print_info "STEP 2: Simulating driver en route to pickup..."
  START_LAT=-6.3610
  START_LNG=106.8240

  for i in {1..5}; do
    LAT=$(awk "BEGIN {print $START_LAT + ($i * 0.0005)}")
    LNG=$(awk "BEGIN {print $START_LNG + ($i * 0.0005)}")
    update_location $LAT $LNG
    sleep 2
  done

  # Step 3: Mark as arrived at pickup
  print_info "STEP 3: Arriving at pickup location..."
  update_ride_status $RIDE_ID "driver_arrived"
  print_warning "Driver should now be marked as 'Arrived' in rider app"
  sleep 5

  # Step 4: Start ride
  print_info "STEP 4: Starting the ride..."
  update_ride_status $RIDE_ID "in_progress"
  print_warning "Rider should now see 'Ride in Progress' screen"
  sleep 2

  # Step 5: Simulate driving to destination (10 location updates over 20 seconds)
  print_info "STEP 5: Simulating driver en route to destination..."
  for i in {1..10}; do
    LAT=$(awk "BEGIN {print $START_LAT + 0.0025 + ($i * 0.0008)}")
    LNG=$(awk "BEGIN {print $START_LNG + 0.0025 + ($i * 0.0008)}")
    update_location $LAT $LNG
    sleep 2
  done

  # Step 6: Complete ride
  print_info "STEP 6: Completing the ride..."
  update_ride_status $RIDE_ID "completed"
  print_warning "Rider should now see the Rating screen"

  echo ""
  echo -e "${GREEN}========================================${NC}"
  echo -e "${GREEN}✓ Ride simulation completed!${NC}"
  echo -e "${GREEN}  Request ID: $REQUEST_ID${NC}"
  echo -e "${GREEN}  Ride ID: $RIDE_ID${NC}"
  echo -e "${GREEN}========================================${NC}"
  echo ""
  print_info "Check the rider app for the rating screen"
}

# Function to quick accept (just accept, don't complete)
quick_accept() {
  local REQUEST_ID=$1

  print_info "Quick accept mode - accepting request #$REQUEST_ID"
  RIDE_ID=$(accept_ride $REQUEST_ID)

  if [ -z "$RIDE_ID" ]; then
    print_error "Failed to accept ride"
    exit 1
  fi

  print_success "Ride accepted! Ride ID: $RIDE_ID"
  print_info "Use './test-rider-flow.sh complete $RIDE_ID' to complete the ride"
}

# Function to complete an already accepted ride
complete_ride() {
  local RIDE_ID=$1

  print_info "Completing ride #$RIDE_ID..."

  # Arrived
  update_ride_status $RIDE_ID "driver_arrived"
  sleep 2

  # In progress
  update_ride_status $RIDE_ID "in_progress"
  sleep 2

  # Some location updates
  for i in {1..3}; do
    LAT=$(awk "BEGIN {print -6.3610 + ($i * 0.001)}")
    LNG=$(awk "BEGIN {print 106.8240 + ($i * 0.001)}")
    update_location $LAT $LNG
    sleep 1
  done

  # Completed
  update_ride_status $RIDE_ID "completed"
  print_success "Ride completed!"
}

# Main script
main() {
  check_dependencies

  case "$1" in
    simulate|"")
      if [ -z "$2" ]; then
        print_error "Usage: ./test-rider-flow.sh simulate <request_id>"
        exit 1
      fi
      get_driver_token
      simulate_ride "$2"
      ;;

    accept)
      if [ -z "$2" ]; then
        print_error "Usage: ./test-rider-flow.sh accept <request_id>"
        exit 1
      fi
      get_driver_token
      quick_accept "$2"
      ;;

    complete)
      if [ -z "$2" ]; then
        print_error "Usage: ./test-rider-flow.sh complete <ride_id>"
        exit 1
      fi
      get_driver_token
      complete_ride "$2"
      ;;

    help|--help|-h)
      echo "Test Rider Flow - Simulate Driver Actions"
      echo ""
      echo "Usage:"
      echo "  ./test-rider-flow.sh simulate <request_id>  # Full flow (accept → complete)"
      echo "  ./test-rider-flow.sh accept <request_id>    # Just accept the ride"
      echo "  ./test-rider-flow.sh complete <ride_id>     # Complete an accepted ride"
      echo ""
      echo "Environment Variables:"
      echo "  API_URL           API base URL (default: http://localhost:8000/api/v1)"
      echo "  DRIVER_EMAIL      Driver email (default: driver@test.com)"
      echo "  DRIVER_PASSWORD   Driver password (default: password)"
      echo ""
      echo "Examples:"
      echo "  # Simulate complete ride flow"
      echo "  ./test-rider-flow.sh simulate 123"
      echo ""
      echo "  # Just accept ride and pause"
      echo "  ./test-rider-flow.sh accept 123"
      echo ""
      echo "  # Complete a previously accepted ride"
      echo "  ./test-rider-flow.sh complete 456"
      echo ""
      echo "  # Use custom API URL"
      echo "  API_URL=http://192.168.1.100:8000/api/v1 ./test-rider-flow.sh simulate 123"
      ;;

    *)
      print_error "Unknown command: $1"
      echo "Run './test-rider-flow.sh help' for usage information"
      exit 1
      ;;
  esac
}

main "$@"
