#!/bin/bash
# Check Rector leaves logs

echo "=== Checking recent RectorDashboardController::leaves logs ==="
docker exec map-hms-app tail -200 /var/www/html/storage/logs/laravel.log | grep -A 10 -B 5 "RectorDashboardController::leaves"

echo ""
echo "=== Checking for any errors ==="
docker exec map-hms-app tail -200 /var/www/html/storage/logs/laravel.log | grep -i "error\|exception" | tail -20
