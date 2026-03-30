# Database Backups and Restore Guide

## Overview

This guide provides scripts and procedures for backing up and restoring the single shared PostgreSQL database, including per-tenant logical backups for compliance and data portability.

## Architecture

- **Single Shared Database**: All tenant data in one PostgreSQL database
- **Tenant Isolation**: Via `tenant_id` column + TenantScope + RLS policies
- **Backup Strategy**: Full database backups + per-tenant logical exports

## Full Database Backup

### Automated Daily Backup Script

```bash
#!/bin/bash
# scripts/backup-full-db.sh

# Configuration
BACKUP_DIR="/var/backups/map-hms"
DB_NAME="map_hms"
DB_USER="postgres"
DB_HOST="localhost"
RETENTION_DAYS=30

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Generate backup filename with timestamp
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/full_backup_${TIMESTAMP}.sql.gz"

# Perform backup
pg_dump -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" \
    --format=custom \
    --compress=9 \
    --file="$BACKUP_FILE"

# Verify backup
if [ $? -eq 0 ]; then
    echo "Backup successful: $BACKUP_FILE"
    
    # Cleanup old backups
    find "$BACKUP_DIR" -name "full_backup_*.sql.gz" -mtime +$RETENTION_DAYS -delete
    
    # Log backup size
    BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    echo "Backup size: $BACKUP_SIZE"
else
    echo "Backup failed!"
    exit 1
fi
```

### Restore Full Database

```bash
#!/bin/bash
# scripts/restore-full-db.sh

BACKUP_FILE=$1
DB_NAME="map_hms"
DB_USER="postgres"
DB_HOST="localhost"

if [ -z "$BACKUP_FILE" ]; then
    echo "Usage: $0 <backup_file>"
    exit 1
fi

# Restore from backup
pg_restore -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" \
    --clean \
    --if-exists \
    "$BACKUP_FILE"

if [ $? -eq 0 ]; then
    echo "Restore successful!"
else
    echo "Restore failed!"
    exit 1
fi
```

## Per-Tenant Logical Backup

### Export Single Tenant Data

```bash
#!/bin/bash
# scripts/backup-tenant.sh

TENANT_ID=$1
BACKUP_DIR="/var/backups/map-hms/tenants"
DB_NAME="map_hms"
DB_USER="postgres"
DB_HOST="localhost"

if [ -z "$TENANT_ID" ]; then
    echo "Usage: $0 <tenant_id>"
    exit 1
fi

mkdir -p "$BACKUP_DIR"

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/tenant_${TENANT_ID}_${TIMESTAMP}.sql"

# Export tenant data (all tables filtered by tenant_id)
pg_dump -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" \
    --data-only \
    --table=users \
    --table=students \
    --table=campuses \
    --table=hostels \
    --table=rooms \
    --table=room_beds \
    --table=room_allocations \
    --table=tickets \
    --table=ticket_comments \
    --table=gate_entries \
    --table=attendance_sessions \
    --table=attendance_logs \
    --table=out_passes \
    --table=laundry_cycles \
    --table=laundry_requests \
    --table=sports_events \
    --table=sports_enrollments \
    --table=facilities \
    --table=facility_bookings \
    --table=leaves \
    --table=sick_leaves \
    --table=guest_entries \
    --table=visitors \
    --table=visitor_logs \
    --table=notices \
    --table=incidents \
    --where="tenant_id='$TENANT_ID'" \
    > "$BACKUP_FILE"

# Compress backup
gzip "$BACKUP_FILE"

echo "Tenant backup created: ${BACKUP_FILE}.gz"
```

### Restore Single Tenant Data

```bash
#!/bin/bash
# scripts/restore-tenant.sh

BACKUP_FILE=$1
TENANT_ID=$2
DB_NAME="map_hms"
DB_USER="postgres"
DB_HOST="localhost"

if [ -z "$BACKUP_FILE" ] || [ -z "$TENANT_ID" ]; then
    echo "Usage: $0 <backup_file> <tenant_id>"
    exit 1
fi

# Decompress if needed
if [[ "$BACKUP_FILE" == *.gz ]]; then
    gunzip -c "$BACKUP_FILE" | psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME"
else
    psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" < "$BACKUP_FILE"
fi

echo "Tenant data restored for tenant: $TENANT_ID"
```

## Laravel Artisan Commands

### Create Artisan Command for Tenant Backup

```php
<?php
// app/Console/Commands/BackupTenant.php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackupTenant extends Command
{
    protected $signature = 'tenant:backup {tenant_id}';
    protected $description = 'Create a logical backup of a single tenant';

    public function handle()
    {
        $tenantId = $this->argument('tenant_id');
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("Tenant not found: {$tenantId}");
            return 1;
        }

        $this->info("Backing up tenant: {$tenant->name} ({$tenant->code})");

        $tables = [
            'users', 'students', 'campuses', 'hostels', 'rooms',
            'room_beds', 'room_allocations', 'tickets', 'ticket_comments',
            'gate_entries', 'attendance_sessions', 'attendance_logs',
            'out_passes', 'laundry_cycles', 'laundry_requests',
            'sports_events', 'sports_enrollments', 'facilities',
            'facility_bookings', 'leaves', 'sick_leaves', 'guest_entries',
            'visitors', 'visitor_logs', 'notices', 'incidents',
        ];

        $backupData = [];
        foreach ($tables as $table) {
            $data = DB::table($table)
                ->where('tenant_id', $tenantId)
                ->get()
                ->toArray();
            
            if (!empty($data)) {
                $backupData[$table] = $data;
            }
        }

        $filename = "tenants/{$tenant->code}_{$tenantId}_" . now()->format('Ymd_His') . '.json';
        Storage::disk('local')->put($filename, json_encode($backupData, JSON_PRETTY_PRINT));

        $this->info("Backup saved to: storage/app/{$filename}");
        return 0;
    }
}
```

## Scheduled Backups

### Add to Laravel Scheduler (app/Console/Kernel.php)

```php
protected function schedule(Schedule $schedule)
{
    // Full database backup daily at 2 AM
    $schedule->command('db:backup')
        ->dailyAt('02:00')
        ->onFailure(function () {
            // Send alert
        });

    // Per-tenant backups weekly (optional)
    $schedule->call(function () {
        Tenant::active()->each(function ($tenant) {
            Artisan::call('tenant:backup', ['tenant_id' => $tenant->id]);
        });
    })->weekly();
}
```

## Best Practices

1. **Backup Frequency**:
   - Full DB: Daily (automated)
   - Per-tenant: Weekly or on-demand

2. **Retention**:
   - Full backups: 30 days
   - Per-tenant backups: 90 days (compliance)

3. **Testing**:
   - Test restore procedures monthly
   - Verify backup integrity

4. **Security**:
   - Encrypt backups at rest
   - Store backups off-site
   - Limit access to backup files

5. **Monitoring**:
   - Alert on backup failures
   - Monitor backup sizes
   - Track backup completion times

## Quick Reference

```bash
# Full backup
./scripts/backup-full-db.sh

# Restore full backup
./scripts/restore-full-db.sh /path/to/backup.sql.gz

# Backup single tenant
./scripts/backup-tenant.sh <tenant_id>

# Restore single tenant
./scripts/restore-tenant.sh /path/to/tenant_backup.sql.gz <tenant_id>

# Laravel artisan
php artisan tenant:backup <tenant_id>
```

