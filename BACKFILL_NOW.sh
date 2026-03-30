#!/bin/sh
# Direct backfill - no migration file needed

docker exec map-hms-app php artisan db:statement "
UPDATE leaves l
SET tenant_id = s.tenant_id
FROM students s
WHERE l.student_id = s.id
AND l.tenant_id IS NULL
AND s.tenant_id IS NOT NULL;
"

docker exec map-hms-app php artisan db:statement "
UPDATE out_passes op
SET tenant_id = s.tenant_id
FROM students s
WHERE op.student_id = s.id
AND op.tenant_id IS NULL
AND s.tenant_id IS NOT NULL;
"

docker exec map-hms-app php artisan db:statement "
UPDATE guest_entries ge
SET tenant_id = s.tenant_id
FROM students s
WHERE ge.student_id = s.id
AND ge.tenant_id IS NULL
AND s.tenant_id IS NOT NULL;
"

docker exec map-hms-app php artisan db:statement "
UPDATE sick_leaves sl
SET tenant_id = s.tenant_id
FROM students s
WHERE sl.student_id = s.id
AND sl.tenant_id IS NULL
AND s.tenant_id IS NOT NULL;
"

echo "✅ Backfill complete!"
