# Tenant Migrations

## ⚠️ IMPORTANT: Single Database Architecture

As of November 2025, MAP-HMS uses a **single shared PostgreSQL database** for all tenants.

**ALL table definitions are in central migrations** (`database/migrations/`).  
**Tenant migrations are for tenant-specific operations only** (RLS policies, backfill operations, deprecations).

## What Goes Where?

### ✅ Central Migrations (`database/migrations/`)
- **ALL table creation** (`CREATE TABLE`)
- **ALL schema changes** (add columns, indexes, foreign keys)
- **All tables include `tenant_id`** for multi-tenant isolation
- Examples:
  - `create_users_table.php`
  - `create_campuses_table.php`
  - `create_hostels_table.php`
  - `create_students_table.php`
  - `create_rooms_table.php`
  - ... all business tables

### ✅ Tenant Migrations (`database/migrations/tenant/`)
- **Row-Level Security (RLS) policies** (PostgreSQL tenant isolation)
- **Tenant-specific indexes** (performance optimization)
- **Backfill operations** (e.g., `add_tenant_id_to_existing_tables.php`)
- **Deprecation markers** (e.g., `deprecate_sports_equipment_loans.php`)
- **Data migrations** (tenant-specific data transformations)

### ❌ DO NOT Put in Tenant Migrations
- Table creation (`CREATE TABLE`) - belongs in central
- Schema changes (add columns, modify types) - belongs in central
- Foreign key constraints - belongs in central
- Duplicate definitions - causes `SQLSTATE[42P07]: Duplicate table` errors

## Current Tenant Migrations (Tenant-Specific Only)

```
2025_10_30_000001_deprecate_sports_equipment_loans.php
2025_11_01_000001_drop_sports_equipment_loan_tables.php
2025_11_03_122457_add_missing_columns_to_attendance_sessions_table.php
2025_11_04_161102_add_tenant_id_to_existing_tables.php
```

## Migration Flow

### Development / Testing
```bash
# 1. Drop all tables and start fresh
php artisan migrate:fresh

# 2. Run central migrations (creates ALL tables with tenant_id)
php artisan migrate

# 3. Run tenant-specific operations (RLS, backfills, etc.)
php artisan migrate --path=database/migrations/tenant

# 4. Seed data
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan db:seed --class=TestingBaselineSeeder
```

### Production
```bash
# 1. Run central migrations (safe, idempotent)
php artisan migrate --force

# 2. Run tenant-specific operations
php artisan migrate --path=database/migrations/tenant --force

# 3. Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

## Tenant Isolation Strategy

### Application-Level (Laravel)
- **Global Scope**: `TenantScoped` trait on all models
- **Middleware**: `InitializeTenancyByDomain` sets `tenant_id` context
- **Policies**: Spatie permissions scoped by `tenant_id`

### Database-Level (PostgreSQL)
- **Row-Level Security (RLS)**: Enforced via policies in tenant migrations
- **Session Variables**: `current_setting('app.current_tenant_id')` per request
- **Composite Indexes**: `(tenant_id, ...)` for query performance

## Testing

### Unit/Feature Tests
```bash
# Tests use TestCase which:
# 1. Runs migrate:fresh (drops schema)
# 2. Runs central migrations
# 3. Runs tenant migrations
# 4. Seeds TestingBaselineSeeder
php artisan test
```

### Playwright E2E Tests
```bash
cd api
npx playwright test tests/e2e/production-onboarding-verification.spec.ts
```

## Troubleshooting

### "Duplicate table" errors
**Cause**: Table defined in both central and tenant migrations.  
**Fix**: Delete the tenant migration. ALL tables belong in central.

### "Campuses table does not exist"
**Cause**: Migrations not committed before test transaction starts.  
**Fix**: `TestCase::refreshTestDatabase()` ensures migrations commit first.

### Foreign key errors
**Cause**: Tenant migrations run before central migrations.  
**Fix**: Always run central migrations first (they create the tables).

## Historical Note

MAP-HMS originally used **database-per-tenant** architecture (Stancl/Tenancy default).  
We migrated to **single-database-multi-tenant** in November 2025 for:
- ✅ Simpler backups (one database)
- ✅ Easier cross-tenant queries (analytics, reporting)
- ✅ Lower infrastructure costs
- ✅ Faster tenant provisioning (no `CREATE DATABASE`)

The tenant migrations folder remains for tenant-specific operations, but **no longer contains table definitions**.
