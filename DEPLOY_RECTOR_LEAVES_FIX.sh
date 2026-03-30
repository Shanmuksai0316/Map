#!/bin/bash
# Deploy Rector Leaves Fix

echo "Deploying RectorDashboardController fix..."

# Copy the fixed controller
docker cp app/Http/Controllers/Api/V1/RectorDashboardController.php map-hms-app:/var/www/html/app/Http/Controllers/Api/V1/RectorDashboardController.php

# Clear caches
docker exec map-hms-app php artisan optimize:clear
docker exec map-hms-app php artisan route:clear
docker exec map-hms-app php artisan config:clear

echo "✅ Deployment complete!"
echo ""
echo "Now test the mobile app and check logs with:"
echo "docker exec map-hms-app tail -f /var/www/html/storage/logs/laravel.log | grep -i 'RectorDashboardController'"
