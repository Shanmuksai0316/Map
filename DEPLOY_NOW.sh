#!/bin/bash
# Deployment Commands with Container Name
# Run these commands on your Hostinger server

# ============================================
# PRODUCTION DEPLOYMENT
# ============================================

# Step 1: Run migration (backfill existing data)
docker exec map-hms-app php artisan migrate --path=database/migrations/2025_01_15_000001_backfill_tenant_id_for_requests.php

# Step 2: Clear all caches
docker exec map-hms-app php artisan optimize:clear
docker exec map-hms-app php artisan config:clear
docker exec map-hms-app php artisan route:clear
docker exec map-hms-app php artisan cache:clear

echo "✅ Production deployment complete!"
