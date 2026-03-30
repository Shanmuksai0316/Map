<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    /**
     * The database connection that should be used by the model.
     * 
     * Permissions are ALWAYS in the central database, even when tenancy is active.
     * Use default connection from .env (pgsql in production, sqlite in dev)
     */
    protected $connection = null;
}

