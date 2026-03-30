#!/bin/bash
# Deploy Rector leaves fix

# Copy the fixed controller
docker cp app/Http/Controllers/Api/V1/RectorDashboardController.php map-hms-app:/var/www/html/app/Http/Controllers/Api/V1/RectorDashboardController.php

# Clear caches
docker exec map-hms-app php artisan optimize:clear
docker exec map-hms-app php artisan route:clear
docker exec map-hms-app php artisan config:clear

echo "✅ Rector leaves fix deployed!"
