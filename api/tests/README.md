# MAP-HMS Test Suite

This document describes the testing strategy and execution for the MAP-HMS system.

## Test Structure

### Unit Tests
- **Location**: `tests/Unit/`
- **Purpose**: Test individual classes and methods in isolation
- **Speed**: Fast (< 1s per test)

### Feature Tests
- **Location**: `tests/Feature/`
- **Purpose**: Test complete user workflows and API endpoints
- **Speed**: Medium (1-5s per test)

### E2E Tests
- **Location**: `tests/e2e/`
- **Purpose**: Full browser automation testing
- **Speed**: Slow (5-30s per test)

## Test Execution

### Quick Test Run (Default CI)
```bash
# Run all fast tests (excludes @slow)
vendor/bin/pest --exclude-group=slow

# Run with parallel execution
vendor/bin/pest --parallel --exclude-group=slow
```

### Full Test Suite
```bash
# Run all tests including slow ones
vendor/bin/pest

# Run with parallel execution
vendor/bin/pest --parallel
```

### Specific Test Groups
```bash
# Run only Super Admin tests
vendor/bin/pest --group=super-admin

# Run only slow tests (nightly)
vendor/bin/pest --group=slow

# Run specific test file
vendor/bin/pest tests/Feature/SuperAdmin/StaffAssignmentTest.php
```

## Test Configuration

### Schema Dump
For faster test execution, we use Laravel's schema dump:

```bash
# Generate schema dump (run after migrations)
php artisan schema:dump

# Tests will load the dump instead of running migrations
```

### Test Bootstrap
All tests use `TestBootstrapSeeder` to ensure minimal required data:
- Roles and permissions
- Test tenant, campus, and hostel
- No heavy demo data seeding

### Database Strategy
- **Fast tests**: Use `DatabaseTransactions` (rollback after each test)
- **Schema tests**: Use `RefreshDatabase` (rebuild schema)
- **E2E tests**: Use `RefreshDatabase` with fresh seeding

## Test Tags

### @slow
Heavy tests that should not run in default CI:
- Report generation tests
- E2E browser tests
- Large dataset tests

### @super-admin
Super Admin specific functionality:
- Staff management
- Dashboard KPIs
- Reports engine

## CI Configuration

### GitHub Actions
```yaml
# Fast tests (default)
- name: Run fast tests
  run: vendor/bin/pest --exclude-group=slow --parallel

# Slow tests (nightly)
- name: Run slow tests
  run: vendor/bin/pest --group=slow
```

### Local Development
```bash
# Quick feedback loop
vendor/bin/pest --exclude-group=slow --parallel

# Before commit
vendor/bin/pest

# Full validation
vendor/bin/pest --parallel
```

## Test Data

### Factories
All factories use lowercase `kind` values to match the normalized LoginPolicy:
- `student`, `guard`, `warden`, `superadmin`, etc.

### Seeding
- **TestBootstrapSeeder**: Minimal required data
- **DemoIndiaSeeder**: Full demo data (E2E only)

## Performance Tips

1. **Use schema dump**: `php artisan schema:dump`
2. **Parallel execution**: `vendor/bin/pest --parallel`
3. **Exclude slow tests**: `--exclude-group=slow`
4. **Database transactions**: Prefer over RefreshDatabase where possible

## Troubleshooting

### Migration Issues
```bash
# Fresh start
php artisan migrate:fresh --seed
php artisan schema:dump
```

### Permission Issues
```bash
# Ensure roles exist
php artisan db:seed --class=RolesAndPermissionsSeeder
```

### Queue Issues
```bash
# Clear failed jobs
php artisan queue:clear
php artisan queue:retry all
```
