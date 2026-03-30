#!/bin/bash
# Run migration with --force flag for production

docker exec map-hms-app php artisan migrate --path=database/migrations/2025_01_15_000001_backfill_tenant_id_for_requests.php --force
