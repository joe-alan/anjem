#!/bin/bash

# Script to update LAN IP in Flutter app configuration
# This replaces all instances of 10.0.2.2 with your current LAN IP
# Usage: ./update_lan_ip.sh [revert]

set -e

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Files to update
RIDER_FILE="lib/main_rider.dart"
DRIVER_FILE="lib/main_driver.dart"

# Function to update IP
update_ip() {
    local new_ip=$1

    echo -e "${BLUE}📝 Updating IP to: $new_ip${NC}"
    echo ""

    # Backup files first
    cp "$RIDER_FILE" "$RIDER_FILE.bak"
    cp "$DRIVER_FILE" "$DRIVER_FILE.bak"

    # Replace 10.0.2.2 with new IP in both files
    sed -i '' "s/10\.0\.2\.2/$new_ip/g" "$RIDER_FILE"
    sed -i '' "s/10\.0\.2\.2/$new_ip/g" "$DRIVER_FILE"

    echo -e "${GREEN}✓ Updated $RIDER_FILE${NC}"
    echo -e "${GREEN}✓ Updated $DRIVER_FILE${NC}"
    echo ""
    echo -e "${YELLOW}Changed lines:${NC}"
    grep -n "$new_ip" "$RIDER_FILE" | head -3
    echo ""

    # Clean up backups
    rm "$RIDER_FILE.bak" "$DRIVER_FILE.bak"
}

# Function to revert to default
revert_ip() {
    echo -e "${BLUE}🔄 Reverting to default IP (10.0.2.2)...${NC}"

    # Get current IP to replace
    current_ip=$(grep "defaultValue: 'http://" "$RIDER_FILE" | head -1 | sed -n "s/.*http:\/\/\([0-9.]*\):.*/\1/p")

    if [ "$current_ip" != "10.0.2.2" ]; then
        sed -i '' "s/$current_ip/10.0.2.2/g" "$RIDER_FILE"
        sed -i '' "s/$current_ip/10.0.2.2/g" "$DRIVER_FILE"
        echo -e "${GREEN}✓ Reverted to 10.0.2.2${NC}"
    else
        echo -e "${YELLOW}Already using default IP${NC}"
    fi
}

# Main logic
if [ "$1" == "revert" ]; then
    revert_ip
else
    # Get LAN IP using myip alias
    echo -e "${BLUE}🔍 Detecting LAN IP using 'myip' command...${NC}"
    LAN_IP=$(myip 2>/dev/null)

    if [ -z "$LAN_IP" ]; then
        echo -e "${RED}❌ Failed to get LAN IP from 'myip' command${NC}"
        echo -e "${YELLOW}Please ensure 'myip' alias is configured${NC}"
        exit 1
    fi

    echo -e "${GREEN}✓ Detected IP: $LAN_IP${NC}"
    echo ""

    # Check if IP is already set
    current_ip=$(grep "defaultValue: 'http://" "$RIDER_FILE" | head -1 | sed -n "s/.*http:\/\/\([0-9.]*\):.*/\1/p")

    if [ "$current_ip" == "$LAN_IP" ]; then
        echo -e "${YELLOW}⚠️  IP is already set to $LAN_IP${NC}"
        echo -e "${YELLOW}No changes needed${NC}"
        exit 0
    fi

    update_ip "$LAN_IP"

    echo -e "${GREEN}✅ Done! You can now build APKs for stress testing${NC}"
    echo -e "${BLUE}Next steps:${NC}"
    echo "  1. flutter build apk --flavor rider -t lib/main_rider.dart"
    echo "  2. flutter build apk --flavor driver -t lib/main_driver.dart"
    echo "  3. Install APKs on all devices"
    echo ""
    echo -e "${YELLOW}To revert back to 10.0.2.2: ./update_lan_ip.sh revert${NC}"
fi
