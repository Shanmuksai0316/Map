<?php

namespace Tests;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Providers\Filament\CampusManagerPanelProvider;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;
    use WithFaker;

    /**
     * Whether to run the TestBootstrapSeeder before each test.
     */
    protected bool $seedTestBootstrap = true;

    /**
     * Whether to create a fallback testing tenant if none exists.
     */
    protected bool $ensureDefaultTestingTenant = true;

    protected function refreshTestDatabase()
    {
        // Always run migrations (don't rely on static flag since we drop schema)
        // This ensures migrations run even if RefreshDatabaseState thinks they've run
        $this->migrateDatabases();

        $this->app[\Illuminate\Contracts\Console\Kernel::class]->setArtisan(null);

        \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = true;

        // Get a fresh connection to verify migrations are committed
        $connection = \Illuminate\Support\Facades\DB::connection();
        
        // Ensure we're not in a transaction when checking
        if ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }

        // Verify migrations completed successfully before starting transaction
        // Use raw query to bypass any schema caching
        $tableExists = $connection->selectOne("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'campuses')");
        if (!$tableExists || !($tableExists->exists ?? false)) {
            throw new \RuntimeException('Campuses table missing after migrations in refreshTestDatabase()');
        }

        // Now start the transaction (after migrations are committed)
        $this->beginDatabaseTransaction();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (empty(config('app.key'))) {
            config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        }

        config()->set('auth.defaults.guard', 'sanctum');

        // ✅ FIX: Disable tenant database creation during tests
        // PostgreSQL doesn't allow CREATE DATABASE inside transactions
        $this->disableTenantDatabaseCreation();
        
        // ✅ FIX: Disable tenancy middleware for tests
        $this->disableTenancyMiddleware();

        // ✅ FIX: Load student routes directly for tests (bypass tenant middleware)
        $this->loadStudentRoutesForTests();
        
        // ✅ FIX: Load attendance routes directly for tests (bypass tenant middleware)
        $this->loadAttendanceRoutesForTests();

        // ✅ FIX: Load ticket routes directly for tests (bypass tenant middleware)
        $this->loadTicketRoutesForTests();

        // ✅ FIX: Load visitor routes directly for tests (bypass tenant middleware)
        $this->loadVisitorRoutesForTests();

        // Seed minimal test data (roles, permissions, tenant, campus, hostel, staff, students)
        if ($this->seedTestBootstrap) {
            $this->seed(\Tests\Seeds\TestBootstrapSeeder::class);
        }

        // Disable step-up OTP in tests to avoid gating flows
        config(['features.otp_stepup' => false]);
        // Enable dashboard/reports features in tests
        config(['features.super_admin_reports' => true]);

        // TestingBaselineSeeder creates the default tenant and stores it in app instance.
        // If it doesn't exist (e.g., seeder skipped or failed), optionally create a fallback tenant.
        if ($this->ensureDefaultTestingTenant && !app()->bound('testing.default_tenant_id')) {
            $defaultTenant = Tenant::factory()->create([
                'status' => TenantStatus::ACTIVE,
            ]);
            app()->instance('testing.default_tenant_id', $defaultTenant->id);
        }

        $this->bootFilamentPanels();
    }

    protected function migrateDatabases()
    {
        $database = config('database.default');
        $connection = \Illuminate\Support\Facades\DB::connection($database);
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite test mode: fresh migration is the safest cross-platform reset.
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
            $this->artisan('migrate:fresh', [
                '--database' => $database,
                '--force' => true,
            ]);
        } else {
            // Ensure we're not in a transaction when dropping schema
            // PostgreSQL doesn't allow DROP SCHEMA inside a transaction
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }

            // Manually drop and recreate schema to ensure clean state
            // This is necessary because migrate:fresh doesn't include tenant migrations
            $connection->statement('DROP SCHEMA IF EXISTS public CASCADE;');
            $connection->statement('CREATE SCHEMA public;');
            $connection->statement('GRANT ALL ON SCHEMA public TO public;');

            // Reset the static migration flag since we've dropped everything
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;

            // Ensure migrations are committed (not in a transaction)
            // Run tenant migrations first (they create tenant-scoped tables)
            $this->artisan('migrate', [
                '--database' => $database,
                '--force' => true,
                '--path' => 'database/migrations/tenant',
            ]);

            // Then run central migrations
            $this->artisan('migrate', [
                '--database' => $database,
                '--force' => true,
            ]);
        }

        // Verify migrations completed successfully
        if (! Schema::hasTable('campuses')) {
            throw new \RuntimeException('Campuses table missing after migrations');
        }
        if (! Schema::hasTable('migrations')) {
            throw new \RuntimeException('Migrations table missing after migrations');
        }

        // Force connection to see the new tables (clear any cached schema info)
        try {
            $connection->getDoctrineSchemaManager()->clearCache();
        } catch (\Exception $e) {
            // Cache clearing might fail, that's okay
        }

        // Verify the table is actually accessible from this connection
        $tableExists = $connection->getSchemaBuilder()->hasTable('campuses');
        if (!$tableExists) {
            throw new \RuntimeException('Campuses table exists in DB but not visible to connection - possible transaction isolation issue');
        }
    }

    /**
     * Disable automatic tenant database creation for tests
     * This prevents PostgreSQL "CREATE DATABASE cannot run inside a transaction block" errors
     */
    protected function disableTenantDatabaseCreation(): void
    {
        // Unbind the tenant creation jobs that try to create databases
        \Illuminate\Support\Facades\Event::forget(\Stancl\Tenancy\Events\TenantCreated::class);
        \Illuminate\Support\Facades\Event::forget(\Stancl\Tenancy\Events\TenantDeleted::class);
        
        // Instead, we'll use the central database for tenant data during tests
        // All tenant models will work with the central test database
    }

    /**
     * Disable tenancy middleware for tests
     * This allows tests to run without tenant context
     */
    protected function disableTenancyMiddleware(): void
    {
        // Disable tenancy middleware for tests
        config(['tenancy.enabled' => false]);
        
        // This is a more aggressive approach - disable tenancy entirely for tests
        app()->instance('tenancy', null);
        
        // Override tenancy helper functions
        if (!function_exists('tenancy')) {
            function tenancy() {
                return new class {
                    public function tenant() { return null; }
                    public function initialize($tenant) { return $this; }
                };
            }
        }
    }

    /**
     * Load student routes directly for tests (bypass tenant middleware)
     * This allows tests to access student API routes without tenant context
     */
    protected function loadStudentRoutesForTests(): void
    {
        // Load student routes directly without tenant middleware
        \Illuminate\Support\Facades\Route::middleware(['auth:sanctum'])
            ->prefix('api/v1/student')
            ->group(function () {
                require base_path('routes/api/student.php');
            });
    }

    /**
     * Load attendance routes directly for tests (bypass tenant middleware)
     * This allows tests to access attendance API routes without tenant context
     */
    protected function loadAttendanceRoutesForTests(): void
    {
        // Load attendance routes directly without tenant middleware
        \Illuminate\Support\Facades\Route::middleware(['auth:sanctum'])
            ->prefix('api/v1')
            ->group(function () {
                require base_path('routes/api/attendance.php');
            });
    }

    /**
     * Load ticket routes directly for tests (bypass tenant middleware).
     */
    protected function loadTicketRoutesForTests(): void
    {
        \Illuminate\Support\Facades\Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api/v1')
            ->group(function () {
                require base_path('routes/api/tickets.php');
            });
    }

    /**
     * Load visitor routes directly for tests (bypass tenant middleware).
     */
    protected function loadVisitorRoutesForTests(): void
    {
        \Illuminate\Support\Facades\Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api/v1')
            ->group(function () {
                require base_path('routes/api/visitors.php');
                require base_path('routes/api/gate.php');
                \Illuminate\Support\Facades\Route::prefix('visitors')->group(function () {
                    \Illuminate\Support\Facades\Route::post('/', [\App\Http\Controllers\VisitorsController::class, 'store']);
                    \Illuminate\Support\Facades\Route::get('/mine/today', [\App\Http\Controllers\VisitorsController::class, 'mineToday']);
                    \Illuminate\Support\Facades\Route::delete('/{guestVisit}', [\App\Http\Controllers\VisitorsController::class, 'cancel']);
                });
            });
    }

    protected function bootFilamentPanels(): void
    {
        $panel = Filament::getPanel('campus-manager');

        Filament::setCurrentPanel($panel);
    }
}
