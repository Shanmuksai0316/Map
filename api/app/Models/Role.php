<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    /**
     * The database connection that should be used by the model.
     * 
     * Roles are ALWAYS in the central database, even when tenancy is active.
     * Use default connection from .env (pgsql in production, sqlite in dev)
     */
    protected $connection = null;
}

