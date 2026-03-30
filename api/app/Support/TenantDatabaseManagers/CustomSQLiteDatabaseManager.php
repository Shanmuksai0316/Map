<?php

namespace App\Support\TenantDatabaseManagers;

use Stancl\Tenancy\Contracts\TenantDatabaseManager;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Illuminate\Support\Facades\File;

/**
 * Custom SQLite Database Manager
 * 
 * Stores tenant databases in database/tenants/ directory.
 */
class CustomSQLiteDatabaseManager implements TenantDatabaseManager
{
    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        $path = $this->databasePath($tenant);
        
        // Ensure directory exists
        $directory = dirname($path);
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // Create empty SQLite database file
        if (!File::exists($path)) {
            touch($path);
        }

        return true;
    }

    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        $path = $this->databasePath($tenant);

        if (File::exists($path)) {
            return File::delete($path);
        }

        return true;
    }

    public function databaseExists(string $name): bool
    {
        return File::exists($this->databasePathFromName($name));
    }

    public function makeConnectionConfig(array $baseConfig, string $databaseName): array
    {
        $baseConfig['database'] = $this->databasePathFromName($databaseName);

        return $baseConfig;
    }

    public function setConnection(string $connection): void
    {
        // Not needed for SQLite
    }

    protected function databasePath(TenantWithDatabase $tenant): string
    {
        return $this->databasePathFromName($this->databaseName($tenant));
    }

    protected function databasePathFromName(string $name): string
    {
        return database_path("tenants/{$name}.sqlite");
    }

    protected function databaseName(TenantWithDatabase $tenant): string
    {
        return config('tenancy.database.prefix') . $tenant->getTenantKey() . config('tenancy.database.suffix');
    }
}

