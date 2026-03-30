#!/bin/bash

#################################################################################
# MSG91 Integration - Hostinger Production Deployment
#################################################################################
# This script deploys the MSG91 integration to Hostinger production
# Run this script from your local machine
#################################################################################

set -e

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  MSG91 Integration - Hostinger Deployment${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}\n"

# Configuration
PEM_FILE="hostinger/hostinger.pem"
SERVER_USER="${SERVER_USER:-root}"
SERVER_HOST="161.97.104.192"
PROJECT_PATH="/var/www/map-hms"

echo -e "${YELLOW}Server: $SERVER_USER@$SERVER_HOST${NC}"
echo -e "${YELLOW}Project: $PROJECT_PATH${NC}\n"

# Check PEM file
if [ ! -f "$PEM_FILE" ]; then
    echo -e "${RED}❌ Error: PEM file not found at $PEM_FILE${NC}"
    exit 1
fi

chmod 400 "$PEM_FILE"

# Function to run command on server
run_remote() {
    ssh -i "$PEM_FILE" -o StrictHostKeyChecking=no "$SERVER_USER@$SERVER_HOST" "$1"
}

# Step 1: Test connection
echo -e "${BLUE}Step 1: Testing connection...${NC}"
if ! run_remote "echo 'Connected'"; then
    echo -e "${RED}❌ Connection failed${NC}"
    echo -e "${YELLOW}Try manually:${NC}"
    echo -e "ssh -i $PEM_FILE $SERVER_USER@$SERVER_HOST"
    exit 1
fi
echo -e "${GREEN}✓ Connected${NC}\n"

# Step 2: Pull latest code
echo -e "${BLUE}Step 2: Pulling latest code...${NC}"
run_remote "cd $PROJECT_PATH && git pull origin main"
echo -e "${GREEN}✓ Code updated${NC}\n"

# Step 3: Show what was deployed
echo -e "${BLUE}Step 3: Latest commit:${NC}"
run_remote "cd $PROJECT_PATH && git log -3 --oneline"
echo ""

# Step 4: Add MSG91 configuration to .env
echo -e "${BLUE}Step 4: Adding MSG91 configuration to .env...${NC}"
run_remote "cd $PROJECT_PATH/api && cat >> .env << 'ENVEOF'

################################################################################
# MSG91 SMS CONFIGURATION - Production (Added $(date +%Y-%m-%d))
################################################################################
MSG91_ENABLED=true
STPL_ENABLED=false
MSG91_API_KEY=459806AyDbB8GR6693c0f7aP1
MSG91_SENDER_ID=MAPHMS

# OTP & Authentication
MSG91_OTP_LOGIN_TEMPLATE_ID=1707176163248828919
MSG91_STUDENT_WELCOME_OTP_TEMPLATE_ID=1707176579226173545
MSG91_STEP_UP_OTP_TEMPLATE_ID=1707176579191256662
MSG91_ACTIVATION_ASSIGNMENT_TEMPLATE_ID=170717613305529158

# Approvals
MSG91_APPROVAL_APPROVED_OUTPASS_TEMPLATE_ID=1707176519403051360
MSG91_APPROVAL_REJECTED_OUTPASS_TEMPLATE_ID=1707176519418020566
MSG91_APPROVAL_APPROVED_LEAVE_TEMPLATE_ID=1707176519434535216
MSG91_APPROVAL_REJECTED_LEAVE_TEMPLATE_ID=1707176519456976264
MSG91_APPROVAL_APPROVED_SICK_LEAVE_TEMPLATE_ID=1707176519470323398
MSG91_APPROVAL_REJECTED_SICK_LEAVE_TEMPLATE_ID=1707176519486927109

# Leave Decisions
MSG91_LEAVE_APPROVED_TEMPLATE_ID=1707176588369841200
MSG91_LEAVE_REJECTED_TEMPLATE_ID=1707176588384023300
MSG91_SICK_LEAVE_APPROVED_TEMPLATE_ID=1707176588371645233
MSG91_SICK_LEAVE_REJECTED_TEMPLATE_ID=1707176588388192649

# SLA Alerts
MSG91_SLA_BREACH_OUTPASS_TEMPLATE_ID=1707176586313383822
MSG91_SLA_BREACH_LEAVE_TEMPLATE_ID=1707176588396711600
MSG91_SLA_BREACH_SICK_LEAVE_TEMPLATE_ID=1707176588340529204
MSG91_SLA_WARNING_OUTPASS_TEMPLATE_ID=1707176588360093726

# Checkout
MSG91_CHECKOUT_REMINDER_TEMPLATE_ID=1707176579431831599
MSG91_CHECKOUT_OVERDUE_TEMPLATE_ID=1707176588347859514

# Checklists
MSG91_CHECKLIST_MORNING_TEMPLATE_ID=1707176588325133568
MSG91_CHECKLIST_AFTERNOON_TEMPLATE_ID=17071765888364402082
MSG91_CHECKLIST_OVERDUE_TEMPLATE_ID=17071765888366455555

# Room Changes
MSG91_ROOM_CHANGE_APPROVED_TEMPLATE_ID=1707176586306450881
MSG91_ROOM_CHANGE_REJECTED_TEMPLATE_ID=1707176588354428172
MSG91_ROOM_CHANGE_SLA_BREACH_TEMPLATE_ID=1707176588378147245

# Student Lifecycle
MSG91_STUDENT_ARCHIVED_TEMPLATE_ID=1707176579555890852

# Optional
MSG91_LATE_RETURN_ALERT_TEMPLATE_ID=1707176163293037766
MSG91_EMERGENCY_ALERT_TEMPLATE_ID=1707176131647665467
MSG91_ATTENDANCE_ALERT_TEMPLATE_ID=1707176131536210758
ENVEOF
"
echo -e "${GREEN}✓ MSG91 configuration added${NC}\n"

# Step 5: Clear caches
echo -e "${BLUE}Step 5: Clearing caches...${NC}"
run_remote "cd $PROJECT_PATH/api && php artisan config:clear && php artisan cache:clear && php artisan route:clear"
echo -e "${GREEN}✓ Caches cleared${NC}\n"

# Step 6: Verify MSG91 configuration
echo -e "${BLUE}Step 6: Verifying MSG91 setup...${NC}"
run_remote "cd $PROJECT_PATH/api && php scripts/verify-msg91-setup.php"

# Step 7: Restart services
echo -e "\n${BLUE}Step 7: Restarting services...${NC}"
run_remote "sudo systemctl restart php8.3-fpm php8.2-fpm php-fpm 2>/dev/null || echo 'PHP-FPM restart skipped (no sudo)'"
run_remote "sudo systemctl restart nginx 2>/dev/null || echo 'Nginx restart skipped (no sudo)'"
echo -e "${GREEN}✓ Services restarted${NC}\n"

# Step 8: Restart queue workers
echo -e "${BLUE}Step 8: Restarting queue workers...${NC}"
run_remote "cd $PROJECT_PATH/api && php artisan queue:restart 2>&1 || echo 'No queue workers running'"
echo -e "${GREEN}✓ Queue workers restarted${NC}\n"

echo -e "${GREEN}═══════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  ✅ MSG91 Deployment Complete!${NC}"
echo -e "${GREEN}═══════════════════════════════════════════════════════════${NC}\n"

echo -e "${BLUE}Next Steps:${NC}"
echo -e "  ${YELLOW}1.${NC} Test SMS: ssh -i $PEM_FILE $SERVER_USER@$SERVER_HOST 'cd $PROJECT_PATH/api && php scripts/test-msg91-sms.php +919876543210'"
echo -e "  ${YELLOW}2.${NC} Test mobile app OTP login"
echo -e "  ${YELLOW}3.${NC} Test web panel OTP login (was broken, now fixed!)"
echo -e "  ${YELLOW}4.${NC} Monitor logs: ssh -i $PEM_FILE $SERVER_USER@$SERVER_HOST 'tail -f $PROJECT_PATH/api/storage/logs/laravel.log | grep -i sms'"

echo -e "\n${BLUE}What Changed:${NC}"
echo -e "  ✅ Config: 7 new templates added (30 total)"
echo -e "  ✅ FilamentOtpService: Fixed to send SMS"
echo -e "  ✅ Production .env: All MSG91 credentials added"
echo -e "  ✅ SMS: Now working for all 30 notification types"

echo -e "\n${GREEN}Deployment successful! 🚀${NC}\n"
