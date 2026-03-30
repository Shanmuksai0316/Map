<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Support\Roles;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Backfill is_map_staff for existing users based on their roles
     */
    public function up(): void
    {
        // Set MAP staff roles to is_map_staff = true
        $mapStaffRoles = Roles::mapStaffRoles();
        
        DB::table('users')
            ->whereIn('id', function ($query) use ($mapStaffRoles) {
                $query->select('model_id')
                    ->from('model_has_roles')
                    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                    ->whereIn('roles.name', $mapStaffRoles);
            })
            ->update(['is_map_staff' => true]);

        // Explicitly set college representatives to is_map_staff = false
        $collegeRepRoles = Roles::collegeRepresentativeRoles();
        
        DB::table('users')
            ->whereIn('id', function ($query) use ($collegeRepRoles) {
                $query->select('model_id')
                    ->from('model_has_roles')
                    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                    ->whereIn('roles.name', $collegeRepRoles);
            })
            ->update(['is_map_staff' => false]);
    }

    /**
     * Reverse the migrations.
     * Reset is_map_staff to default (false) for all users
     */
    public function down(): void
    {
        DB::table('users')->update(['is_map_staff' => false]);
    }
};
