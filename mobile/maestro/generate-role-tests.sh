#!/bin/bash

# ============================================
# Generate Role-Specific E2E Tests
# ============================================
#
# This script generates demo tokens for all 8 staff roles
# and creates Maestro test files for each role.
#
# Prerequisites:
# - Node.js installed
# - DEMO_HMAC_SECRET environment variable OR --hmac-secret argument
#
# Usage:
#   ./generate-role-tests.sh --hmac-secret YOUR_SECRET
#   
#   OR set environment variable:
#   export DEMO_HMAC_SECRET=your_secret
#   ./generate-role-tests.sh
#
# ============================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Parse arguments
HMAC_SECRET="${DEMO_HMAC_SECRET:-}"
TENANT="${DEMO_TENANT:-STXAV}"
API_BASE="${DEMO_API_BASE:-https://stxaviers.mapservices.in}"

while [[ $# -gt 0 ]]; do
    case $1 in
        --hmac-secret)
            HMAC_SECRET="$2"
            shift 2
            ;;
        --tenant)
            TENANT="$2"
            shift 2
            ;;
        --api)
            API_BASE="$2"
            shift 2
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            exit 1
            ;;
    esac
done

if [ -z "$HMAC_SECRET" ]; then
    echo -e "${RED}Error: HMAC secret is required${NC}"
    echo ""
    echo "Usage: $0 --hmac-secret YOUR_SECRET"
    echo ""
    echo "Or set environment variable:"
    echo "  export DEMO_HMAC_SECRET=your_secret"
    echo "  $0"
    exit 1
fi

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  Generating Role-Specific E2E Tests${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""
echo -e "Tenant: ${YELLOW}$TENANT${NC}"
echo -e "API: ${YELLOW}$API_BASE${NC}"
echo ""

# Generate tokens
echo -e "${YELLOW}⏳ Generating demo tokens for all roles...${NC}"
TOKEN_OUTPUT=$(node "$PROJECT_ROOT/scripts/generate-demo-tokens.js" \
    --tenant "$TENANT" \
    --hmac-secret "$HMAC_SECRET" \
    --api "$API_BASE" 2>&1)

if [ $? -ne 0 ]; then
    echo -e "${RED}Failed to generate tokens:${NC}"
    echo "$TOKEN_OUTPUT"
    exit 1
fi

echo -e "${GREEN}✅ Tokens generated successfully${NC}"
echo ""

# Save tokens to file
TOKENS_FILE="$SCRIPT_DIR/generated-tokens.json"
echo "$TOKEN_OUTPUT" | grep -A 1000 "JSON for Maestro:" | tail -n +2 | head -n -3 > "$TOKENS_FILE"
echo -e "Tokens saved to: ${GREEN}$TOKENS_FILE${NC}"

# Extract deep links and create test files
ROLES=("campus_manager" "rector" "warden" "guard" "hk_supervisor" "rm_supervisor" "laundry_manager" "sports_manager")
ROLE_NAMES=("Campus Manager" "Rector" "Warden" "Guard" "HK Supervisor" "RM Supervisor" "Laundry Manager" "Sports Manager")

echo ""
echo -e "${YELLOW}📝 Creating role-specific test files...${NC}"

for i in "${!ROLES[@]}"; do
    role="${ROLES[$i]}"
    role_name="${ROLE_NAMES[$i]}"
    
    # Extract deep link for this role
    deep_link=$(echo "$TOKEN_OUTPUT" | grep -A 1 "^${role}:" | tail -1 | xargs)
    
    if [ -n "$deep_link" ]; then
        # Create test file
        test_file="$SCRIPT_DIR/staff/role-${role}-test.yaml"
        
        cat > "$test_file" << EOF
appId: com.mapmars.hmsstaff
name: "${role_name} E2E Test"
tags:
  - ${role}
  - e2e
  - role-specific

---

# Auto-generated test for ${role_name}
# Deep Link: ${deep_link}

- launchApp:
    appId: com.mapmars.hmsstaff
    clearState: true
    clearKeychain: true

- openLink: "${deep_link}"

- runFlow:
    when:
      visible: "Open with"
    commands:
      - tapOn: "MAP HMS Staff"
      - runFlow:
          when:
            visible: "(Just once|Always)"
          commands:
            - tapOn: "Just once"

- extendedWaitUntil:
    visible: "(${role_name}|Good Morning|Good Afternoon|Good Evening|Dashboard)"
    timeout: 30000

- takeScreenshot: "${role}/01_dashboard"

- assertVisible: "(${role_name}|Dashboard|Quick Actions)"

- takeScreenshot: "${role}/02_verified"

# Test navigation
- runFlow:
    when:
      visible: "Profile"
    commands:
      - tapOn: "Profile"
      - extendedWaitUntil:
          visible: "(Profile|Logout|Settings)"
          timeout: 10000
      - takeScreenshot: "${role}/03_profile"

- takeScreenshot: "${role}/04_complete"
EOF
        
        echo -e "  ${GREEN}✅${NC} Created: role-${role}-test.yaml"
    else
        echo -e "  ${RED}❌${NC} No deep link found for ${role}"
    fi
done

# Create master test runner
RUNNER_FILE="$SCRIPT_DIR/staff/run-all-role-tests.yaml"
cat > "$RUNNER_FILE" << 'EOF'
appId: com.mapmars.hmsstaff
name: "All Roles - Sequential Test"
tags:
  - all-roles
  - e2e
  - comprehensive

---

# Run all role-specific tests
EOF

for role in "${ROLES[@]}"; do
    if [ -f "$SCRIPT_DIR/staff/role-${role}-test.yaml" ]; then
        echo "- runFlow:" >> "$RUNNER_FILE"
        echo "    file: role-${role}-test.yaml" >> "$RUNNER_FILE"
        echo "" >> "$RUNNER_FILE"
    fi
done

echo -e "  ${GREEN}✅${NC} Created: run-all-role-tests.yaml"

echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  Generation Complete!${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""
echo -e "To run all role tests:"
echo -e "  ${YELLOW}cd $SCRIPT_DIR${NC}"
echo -e "  ${YELLOW}maestro test staff/run-all-role-tests.yaml${NC}"
echo ""
echo -e "To run individual role test:"
echo -e "  ${YELLOW}maestro test staff/role-<role>-test.yaml${NC}"
echo ""

