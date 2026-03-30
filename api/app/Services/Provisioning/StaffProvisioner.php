<?php

namespace App\Services\Provisioning;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class StaffProvisioner
{
    public function provision(string|int $tenantId, array $staffData): array
    {
        $created = [];

        foreach ($staffData as $staff) {
            $role = $staff['role'] ?? 'Staff';
            
            // Validate role is MAP staff role
            if (!\App\Support\Roles::isMapStaffRole($role)) {
                throw new \Exception("Role '{$role}' cannot be provisioned via Staff Provisioner. Only MAP staff roles are allowed. Use Tenant Onboarding Wizard for college representatives (Rector, College Management).");
            }
            
            $user = $this->createStaffUser($tenantId, $staff);
            
            // Ensure is_map_staff is set to true for all provisioned staff
            $user->update(['is_map_staff' => true]);

            $created[] = [
                'user' => $user->fresh(['roles']),
                'role' => $role,
            ];
        }

        return $created;
    }

    private function createStaffUser(string|int $tenantId, array $staff): User
    {
        $role = $staff['role'] ?? 'Staff';
        Role::findOrCreate($role);

        $phone = $staff['phone'] ?? '+91'.str_pad((string) random_int(1000000000, 9999999999), 10, '0');
        $username = $staff['username'] ?? null;

        $attrs = [
            'tenant_id' => (string) $tenantId, // Required for cross-tenant queries and policies
            'name' => $staff['name'] ?? $this->generateNameFromRole($role),
            'kind' => $this->mapRoleToKind($role),
            'password' => Hash::make($staff['password'] ?? Str::random(16)),
            'phone' => $phone,
            'username' => $username,
            'email' => $staff['email'] ?? null,
            'profile_photo_path' => $staff['profile_photo_path'] ?? null,
            'gender' => $staff['gender'] ?? null,
            'dob' => isset($staff['dob']) ? \Carbon\Carbon::createFromFormat('d-m-Y', $staff['dob'])->toDateString() : null,
            'id_type' => $staff['id_type'] ?? null,
            'id_number' => $staff['id_number'] ?? null,
            'address' => $staff['address'] ?? null,
            'status' => $staff['status'] ?? 'Active',
            'date_of_joining' => isset($staff['date_of_joining']) ? \Carbon\Carbon::createFromFormat('d-m-Y', $staff['date_of_joining'])->toDateString() : null,
            'emergency_contact_name' => $staff['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $staff['emergency_contact_phone'] ?? null,
            'is_map_staff' => true, // All provisioned staff are MAP staff
        ];

        // Use updateOrCreate with tenant_id + phone to ensure uniqueness per tenant
        $user = User::updateOrCreate(
            [
                'tenant_id' => (string) $tenantId,
                'phone' => $phone,
            ],
            $attrs
        );

        $user->syncRoles([$role]);

        if (!empty($staff['hostel_ids'])) {
            // Sync hostels with tenant_id in pivot data
            $syncData = [];
            foreach ($staff['hostel_ids'] as $hostelId) {
                $syncData[$hostelId] = [
                    'tenant_id' => (string) $tenantId,
                    'assigned_at' => now(),
                ];
            }
            $user->staffHostels()->sync($syncData);
        }

        return $user;
    }

    private function generateNameFromRole(string $role): string
    {
        return match ($role) {
            'Rector' => 'Rector ' . Str::random(4),
            'Campus Manager' => 'Campus Manager ' . Str::random(4),
            default => Str::headline($role) . ' ' . Str::random(4),
        };
    }

    private function mapRoleToKind(string $role): string
    {
        return match ($role) {
            'Rector' => 'Rector',
            'Campus Manager' => 'CampusManager',
            'Warden' => 'Warden',
            'Guard' => 'Guard',
            'Laundry Manager' => 'LaundryManager',
            'Sports Manager' => 'SportsManager',
            default => Str::of($role)->replace(' ', '')->toString(),
        };
    }
}



