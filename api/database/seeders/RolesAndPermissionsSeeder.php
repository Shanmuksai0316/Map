<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Clear cache first
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define all permissions referenced in the application
        $permissions = [
            // Out-pass permissions
            'outpass.view',
            'outpass.create',
            'outpass.decide',
            'outpass.export',
            
            // Tenant management
            'tenant.manage',
            'tenant.view',
            
            // Campus management
            'campus.manage',
            'campus.view',
            
            // Hostel management
            'hostel.manage',
            'hostel.view',
            
            // Room & allocation permissions
            'room.view',
            'room.manage',
            'room.allocation.view',
            'room.allocation.manage',
            
            // Student management
            'student.view',
            'student.manage',
            'student.import',
            'student.export',
            
            // Attendance permissions
            'attendance.view',
            'attendance.manage',
            'attendance.edit',
            
            // Ticket permissions
            'ticket.view',
            'ticket.create',
            'ticket.assign',
            'ticket.resolve',
            'ticket.close',
            
            // Checklist permissions
            'checklist.view',
            'checklist.create',
            'checklist.submit',
            'checklist.approve',
            
            // Notice permissions
            'notice.view',
            'notice.create',
            'notice.publish',
            
            // Gate permissions (Security add-on)
            'gate.view',
            'gate.manage',
            'gate.device.register',
            'gate.visitor.manage',
            
            // Laundry permissions (add-on)
            'laundry.view',
            'laundry.manage',
            'laundry.process',
            
            // Sports permissions (add-on)
            'sports.view',
            'sports.manage',
            'sports.book',
            
            // Payment permissions
            'payment.view',
            'payment.manage',
            'payment.mark_paid',
            
            // Dashboard permissions
            'dashboard.view',
            'dashboard.analytics',
            
            // Export permissions
            'export.view',
            'export.create',
            
            // Audit permissions
            'audit.view',
        ];

        // Create all permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Define roles with their permissions
        $this->createSuperAdminRole();
        $this->createCampusManagerRole();
        $this->createRectorRole();
        $this->createCollegeMgmtRole();
        $this->createWardenRole();
        $this->createHKSupervisorRole();
        $this->createRMSupervisorRole();
        $this->createGuardRole();
        $this->createLaundryManagerRole();
        $this->createSportsManagerRole();
        $this->createStudentRole();

        // Clear cache again after seeding
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function createSuperAdminRole(): void
    {
        $role = Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo(Permission::all());
    }

    private function createCampusManagerRole(): void
    {
        $role = Role::firstOrCreate([
            'name' => 'Campus Manager',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo([
            'tenant.view',
            'campus.view',
            'hostel.view',
            'hostel.manage',
            'room.view',
            'room.manage',
            'room.allocation.view',
            'room.allocation.manage',
            'student.view',
            'student.manage',
            'student.import',
            'student.export',
            'outpass.view',
            'attendance.view',
            'attendance.manage',
            'attendance.edit',
            'ticket.view',
            'ticket.assign',
            'ticket.resolve',
            'ticket.close',
            'checklist.view',
            'checklist.approve',
            'notice.view',
            'notice.create',
            'notice.publish',
            'payment.view',
            'payment.manage',
            'payment.mark_paid',
            'dashboard.view',
            'dashboard.analytics',
            'export.view',
            'export.create',
            'audit.view',
        ]);
    }

    private function createRectorRole(): void
    {
        $role = Role::firstOrCreate([
            'name' => 'Rector',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo([
            'outpass.view',
            'outpass.decide',
            'outpass.export',
            'room.allocation.view',
            'student.view',
            'attendance.view',
            'ticket.view',
            'notice.view',
            'dashboard.view',
            'dashboard.analytics',
            'export.view',
            'audit.view',
        ]);
    }

    private function createCollegeMgmtRole(): void
    {
        $role = Role::firstOrCreate([
            'name' => 'College Management',
            'guard_name' => 'web',
        ]);
        // View-only permissions - no create, edit, delete, or approve
        $role->givePermissionTo([
            'campus.view',
            'hostel.view',
            'room.view',
            'room.allocation.view',
            'student.view',
            'student.export',
            'attendance.view',
            'outpass.view',
            'outpass.export',
            'ticket.view',
            'checklist.view',
            'notice.view',
            'gate.view',
            'gate.visitor.manage', // Can view visitor logs
            'laundry.view',
            'sports.view',
            'dashboard.view',
            'dashboard.analytics',
            'export.view',
            'audit.view',
        ]);
    }

    private function createWardenRole(): void
    {
        $role = Role::firstOrCreate([
            'name' => 'Warden',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo([
            'student.view',
            'attendance.view',
            'attendance.manage',
            'ticket.view',
            'notice.view',
            'dashboard.view',
        ]);
    }

    private function createHKSupervisorRole(): void
    {
        $role = Role::firstOrCreate([
            'name' => 'HK Supervisor',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo([
            'ticket.view',
            'ticket.create',
            'ticket.assign',
            'ticket.resolve',
            'checklist.view',
            'checklist.create',
            'checklist.submit',
            'notice.view',
        ]);
    }

    private function createRMSupervisorRole(): void
    {
        $role = Role::firstOrCreate([
            'name' => 'RM Supervisor',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo([
            'ticket.view',
            'ticket.create',
            'ticket.assign',
            'ticket.resolve',
            'checklist.view',
            'checklist.create',
            'checklist.submit',
            'notice.view',
        ]);
    }

    private function createGuardRole(): void
    {
        $role = Role::firstOrCreate([
            'name' => 'Guard',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo([
            'student.view',
            'gate.view',
            'gate.manage',
            'gate.visitor.manage',
            'ticket.view',
            'ticket.create',
            'notice.view',
        ]);
    }

    private function createLaundryManagerRole(): void
    {
        $role = Role::firstOrCreate([
            'name' => 'Laundry Manager',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo([
            'student.view',
            'laundry.view',
            'laundry.manage',
            'laundry.process',
            'notice.view',
        ]);
    }

    private function createSportsManagerRole(): void
    {
        $role = Role::firstOrCreate([
            'name' => 'Sports Manager',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo([
            'student.view',
            'sports.view',
            'sports.manage',
            'notice.view',
        ]);
    }

    private function createStudentRole(): void
    {
        $role = Role::firstOrCreate([
            'name' => 'Student',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo([
            'outpass.create',
            'ticket.create',
            'checklist.submit',
            'notice.view',
            'sports.book',
            'payment.view',
        ]);
    }
}