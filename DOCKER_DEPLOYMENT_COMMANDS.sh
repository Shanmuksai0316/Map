#!/bin/bash
# Docker Deployment Commands for Mobile App Fixes
# Run these commands on your Hostinger server

# ============================================
# STEP 1: Copy files into Docker container
# ============================================

# Set your container name (production or staging)
CONTAINER_NAME="map-hms-app"  # Change to "map-hms-staging" for staging

# Copy routes file
docker cp routes/api.php ${CONTAINER_NAME}:/var/www/html/routes/api.php

# Copy Campus Manager controller
docker cp app/Http/Controllers/Api/V1/CampusManager/StaffController.php ${CONTAINER_NAME}:/var/www/html/app/Http/Controllers/Api/V1/CampusManager/StaffController.php

# Copy Warden controller
docker cp app/Http/Controllers/Api/V1/Staff/WardenController.php ${CONTAINER_NAME}:/var/www/html/app/Http/Controllers/Api/V1/Staff/WardenController.php

# Copy Supervisor controller
docker cp app/Http/Controllers/Api/V1/Staff/SupervisorController.php ${CONTAINER_NAME}:/var/www/html/app/Http/Controllers/Api/V1/Staff/SupervisorController.php

# Copy Student SickLeave controller
docker cp app/Http/Controllers/Api/V1/Student/SickLeaveController.php ${CONTAINER_NAME}:/var/www/html/app/Http/Controllers/Api/V1/Student/SickLeaveController.php

# Copy new migration file
docker cp database/migrations/2025_01_15_000001_backfill_tenant_id_for_requests.php ${CONTAINER_NAME}:/var/www/html/database/migrations/2025_01_15_000001_backfill_tenant_id_for_requests.php

# ============================================
# STEP 2: Run migration inside container
# ============================================

docker exec ${CONTAINER_NAME} php artisan migrate --path=database/migrations/2025_01_15_000001_backfill_tenant_id_for_requests.php

# ============================================
# STEP 3: Clear all caches
# ============================================

docker exec ${CONTAINER_NAME} php artisan optimize:clear
docker exec ${CONTAINER_NAME} php artisan config:clear
docker exec ${CONTAINER_NAME} php artisan route:clear
docker exec ${CONTAINER_NAME} php artisan cache:clear

# ============================================
# STEP 4: Restart container (optional, if needed)
# ============================================

# Uncomment if you need to restart the container
# docker restart ${CONTAINER_NAME}

echo "✅ Deployment complete!"
