# Database Migration Guide - Student App 21 Points

## Overview

This guide provides step-by-step instructions for running the 4 new database migrations required for the 21 UI/UX points implementation.

---

## Migration Files

1. `2025_11_03_120000_create_leaves_table.php`
2. `2025_11_03_120001_create_sick_leaves_table.php`
3. `2025_11_03_120002_create_guest_entries_table.php`
4. `2025_11_03_120003_create_room_changes_table.php`

**Important**: These are **tenant-specific** migrations and must be run in the tenant database context.

---

## Pre-Migration Checklist

### ✅ Environment Preparation
- [ ] Backup all tenant databases
- [ ] Verify Laravel environment configuration
- [ ] Ensure database connection is working
- [ ] Test migrations on a single tenant first (staging)
- [ ] Verify no conflicting migrations exist

### ✅ Code Verification
- [ ] All migration files are in `api/database/migrations/tenant/`
- [ ] Migration class names are unique
- [ ] All foreign key references exist
- [ ] No syntax errors in migration files

---

## Migration Execution

### Step 1: Backup Databases

```bash
# Backup all tenant databases
# For each tenant, create a backup
pg_dump -U postgres tenant_db_name > backup_tenant_db_name_$(date +%Y%m%d_%H%M%S).sql
```

Or using Laravel backup command if available.

### Step 2: Test Migration (Single Tenant)

```bash
# Navigate to API directory
cd api

# Run migrations for a specific tenant (staging)
php artisan tenants:migrate --tenant=<tenant_id> --path=database/migrations/tenant
```

Or if using automatic tenant context:

```bash
# Set tenant context
php artisan tenants:list
# Select a test tenant

# Run migrations
php artisan migrate --path=database/migrations/tenant
```

### Step 3: Verify Migration Success

```bash
# Check migration status
php artisan migrate:status --path=database/migrations/tenant

# Verify tables created
php artisan tinker
# Then in tinker:
DB::select("SHOW TABLES LIKE 'leaves'");
DB::select("SHOW TABLES LIKE 'sick_leaves'");
DB::select("SHOW TABLES LIKE 'guest_entries'");
DB::select("SHOW TABLES LIKE 'room_changes'");
```

### Step 4: Verify Table Structure

```sql
-- Check leaves table
\d leaves

-- Check sick_leaves table
\d sick_leaves

-- Check guest_entries table
\d guest_entries

-- Check room_changes table
\d room_changes

-- Verify indexes
SELECT indexname, indexdef FROM pg_indexes WHERE tablename IN ('leaves', 'sick_leaves', 'guest_entries', 'room_changes');
```

### Step 5: Run for All Tenants (Production)

```bash
# If using Laravel Tenancy package with auto-migration
php artisan tenants:migrate

# Or manually for each tenant
php artisan tenants:migrate --tenant=tenant1
php artisan tenants:migrate --tenant=tenant2
# ... and so on
```

---

## Migration Verification

### Verify Each Table

#### Leaves Table
```sql
-- Check structure
SELECT column_name, data_type, is_nullable 
FROM information_schema.columns 
WHERE table_name = 'leaves';

-- Verify indexes
SELECT * FROM pg_indexes WHERE tablename = 'leaves';

-- Test insert (should work)
INSERT INTO leaves (student_id, hostel_id, unique_id, title, description, reason_for_leave, from_date, to_date, status, submitted_at)
VALUES (1, 1, 'LEV-TEST123', 'Test Leave', 'Test Description', 'Test Reason', '2025-11-01', '2025-11-03', 'pending', NOW());

-- Verify unique_id constraint
INSERT INTO leaves (student_id, hostel_id, unique_id, title, description, reason_for_leave, from_date, to_date, status, submitted_at)
VALUES (1, 1, 'LEV-TEST123', 'Test Leave 2', 'Test Description', 'Test Reason', '2025-11-01', '2025-11-03', 'pending', NOW());
-- Should fail with unique constraint error

-- Clean up test data
DELETE FROM leaves WHERE unique_id = 'LEV-TEST123';
```

#### Sick Leaves Table
```sql
-- Similar verification as leaves
SELECT column_name, data_type, is_nullable 
FROM information_schema.columns 
WHERE table_name = 'sick_leaves';

-- Test insert
INSERT INTO sick_leaves (student_id, hostel_id, unique_id, title, description, illness, illness_details, need_medical_attention, contact_parents, status, submitted_at)
VALUES (1, 1, 'SLK-TEST123', 'Test Sick Leave', 'Test Description', 'Fever', 'High fever', true, false, 'pending', NOW());

DELETE FROM sick_leaves WHERE unique_id = 'SLK-TEST123';
```

#### Guest Entries Table
```sql
-- Verify JSON column
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'guest_entries' AND column_name = 'guests';

-- Test insert with JSON
INSERT INTO guest_entries (student_id, hostel_id, unique_id, title, description, guests, primary_contact_mobile, visit_date, check_in_time, check_out_time, purpose_to_visit, status, submitted_at)
VALUES (1, 1, 'GST-TEST123', 'Parents Visit', 'Test', '[{"name":"John Doe","relationship":"Father","id_type":"aadhar_card","id_number":"123456789012"}]', '9876543210', '2025-11-05', '10:00', '18:00', 'Visit', 'pending', NOW());

DELETE FROM guest_entries WHERE unique_id = 'GST-TEST123';
```

#### Room Changes Table
```sql
-- Verify enum
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'room_changes' AND column_name = 'sharing_preference';

-- Test insert
INSERT INTO room_changes (student_id, hostel_id, unique_id, title, description, preferred_room_number, sharing_preference, status, submitted_at)
VALUES (1, 1, 'RMC-TEST123', 'Room Change Request', 'Test Description', '205', 'double', 'pending', NOW());

DELETE FROM room_changes WHERE unique_id = 'RMC-TEST123';
```

---

## Rollback Instructions

### If Migration Fails

```bash
# Rollback last migration
php artisan migrate:rollback --path=database/migrations/tenant --step=1

# Or rollback specific migration
php artisan migrate:rollback --path=database/migrations/tenant
```

### If Data Issues Found

```bash
# Restore from backup
psql -U postgres tenant_db_name < backup_tenant_db_name_timestamp.sql
```

---

## Post-Migration Verification

### ✅ Functionality Tests

1. **Leaves**
   - [ ] Can create leave request via API
   - [ ] Unique ID generated automatically
   - [ ] Status defaults to 'pending'
   - [ ] Foreign keys work correctly

2. **Sick Leaves**
   - [ ] Can create sick leave request via API
   - [ ] Boolean fields work correctly
   - [ ] Unique ID generated automatically

3. **Guest Entries**
   - [ ] Can create guest entry via API
   - [ ] JSON guests array stored correctly
   - [ ] Time fields work correctly
   - [ ] Maximum 4 guests enforced

4. **Room Changes**
   - [ ] Can create room change request via API
   - [ ] Enum values work correctly
   - [ ] Optional fields can be null

---

## Troubleshooting

### Issue: Migration fails with foreign key error
**Solution**: Ensure referenced tables (students, hostels) exist and have data

### Issue: Unique constraint violation
**Solution**: Check existing data, ensure unique_id generation is working

### Issue: JSON column not supported
**Solution**: Verify PostgreSQL version >= 9.4 (or use TEXT with JSON encoding)

### Issue: Enum type error
**Solution**: PostgreSQL doesn't support ENUM natively - verify using check constraints

---

## Migration Script

Create a helper script for easier migration:

```bash
#!/bin/bash
# migrate-21-points.sh

echo "Starting migrations for 21 UI/UX Points..."
echo ""

# Backup reminder
echo "⚠️  Ensure backups are created before proceeding!"
read -p "Press Enter to continue..."

# List tenants
echo "Available tenants:"
php artisan tenants:list

# Get tenant ID
read -p "Enter tenant ID to migrate (or 'all' for all tenants): " tenant_id

if [ "$tenant_id" = "all" ]; then
    echo "Migrating all tenants..."
    php artisan tenants:migrate
else
    echo "Migrating tenant: $tenant_id"
    php artisan tenants:migrate --tenant=$tenant_id --path=database/migrations/tenant
fi

echo ""
echo "✅ Migration complete!"
echo ""
echo "Next steps:"
echo "1. Verify tables created"
echo "2. Test API endpoints"
echo "3. Test mobile app"
```

---

## Sign-Off

**Migrated By**: _________________  
**Date**: _________________  
**Tenants Migrated**: _________________  
**Status**: _________________

---

**Document End**

