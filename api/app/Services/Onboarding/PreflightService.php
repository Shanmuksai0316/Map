<?php

namespace App\Services\Onboarding;

use App\Models\Tenant;
use App\Models\Hostel;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * PreflightService - Validates tenant activation readiness
 * 
 * Validates all pre-flight checks before tenant activation:
 * - All mandatory roles assigned (or marked N/A)
 * - Rector & College Mgmt contacts valid
 * - ≥1 hostel with curfew
 * - Rooms/beds generated
 * - Campus Manager tenant-scoped
 */
class PreflightService
{
    /**
     * Required roles for each hostel (mandatory)
     */
    private const MANDATORY_ROLES = [
        'rector',
        'warden',
        'guard',
        'hk_supervisor',
        'rm_supervisor',
    ];

    /**
     * Optional roles (can be marked N/A)
     */
    private const OPTIONAL_ROLES = [
        'laundry_manager',
        'sports_manager',
    ];

    /**
     * Evaluate pre-flight checks for tenant activation
     * 
     * @param Tenant $tenant
     * @param array $wizardData Wizard data from steps 2-4
     * @return array ['passed' => bool, 'errors' => array]
     */
    public function evaluate(Tenant $tenant, array $wizardData): array
    {
        $errors = [];

        if (empty($wizardData)) {
            $dynamicWizard = $tenant->getAttribute('wizard');
            if (is_array($dynamicWizard) && !empty($dynamicWizard)) {
                $wizardData = $dynamicWizard;
            }
        }

        if (empty($wizardData)) {
            $raw = \DB::table('tenants')
                ->where('id', $tenant->id)
                ->value('data');
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $wizardData = $decoded['wizard'] ?? $decoded;
                }
            }
        }

        // Check tenant code starts with MAP
        if (!str_starts_with($tenant->code, 'MAP')) {
            $errors[] = [
                'field' => 'code',
                'message' => 'Tenant code must start with "MAP"',
            ];
        }

        // Check Campus Manager assigned (tenant-scope)
        $campusManager = $this->getCampusManager($tenant->id);
        $wizardCampusManagerId = data_get($wizardData, 'staff.campus_manager_id');
        if (!$campusManager && empty($wizardCampusManagerId)) {
            $errors[] = [
                'field' => 'roles.campus_manager',
                'message' => 'Campus Manager must be assigned at tenant scope',
            ];
        }

        // Check hostels
        $hostels = Hostel::where('tenant_id', $tenant->id)->get();
        if ($hostels->isEmpty()) {
            $errors[] = [
                'field' => 'hostels',
                'message' => 'At least one hostel must be configured',
            ];
        }

        // Check each hostel
        foreach ($hostels as $hostel) {
            $hostelErrors = $this->validateHostel($hostel, $wizardData);
            foreach ($hostelErrors as $error) {
                $error['hostel_code'] = $hostel->code;
                $errors[] = $error;
            }
        }

        // Check Rector contact
        $rectorContact = $this->getRectorContact($tenant->id, $wizardData);
        if (!$rectorContact || !$rectorContact['phone']) {
            $errors[] = [
                'field' => 'contacts.rector.phone',
                'message' => 'Rector user must have a phone number for OTP login',
            ];
        }

        // Check College Management contact
        $collegeMgmtContact = $this->getCollegeMgmtContact($tenant->id, $wizardData);
        if (!$collegeMgmtContact || !$collegeMgmtContact['phone']) {
            $errors[] = [
                'field' => 'contacts.college_mgmt.phone',
                'message' => 'College Management user must have a phone number for OTP login',
            ];
        }

        $amenities = data_get($wizardData, 'amenities.selected', []);
        if (!is_array($amenities) || empty($amenities)) {
            $errors[] = [
                'field' => 'amenities',
                'message' => 'Select at least one amenity before activation',
            ];
        }

        return [
            'passed' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate a single hostel
     */
    private function validateHostel(Hostel $hostel, array $wizardData): array
    {
        $errors = [];

        // Check curfew
        if (!$hostel->curfew_time) {
            $errors[] = [
                'field' => 'curfew',
                'message' => 'Curfew time is required',
            ];
        }

        // Check rooms/beds generated
        $roomCount = $hostel->rooms()->count();
        $bedCount = $hostel->rooms()->withCount('beds')->get()->sum('beds_count');
        
        $hasRoomConfigInWizard = collect(data_get($wizardData, 'room_config', []))
            ->contains(function ($config) use ($hostel) {
                if (! is_array($config)) {
                    return false;
                }

                $hostelId = (int) ($config['hostel_id'] ?? 0);
                // Support both legacy key (`floor_config`) and current key (`floors`).
                $floors = $config['floors'] ?? $config['floor_config'] ?? [];

                return $hostelId === (int) $hostel->id && is_array($floors) && ! empty($floors);
            });

        if ($roomCount === 0 && ! $hasRoomConfigInWizard) {
            $errors[] = [
                'field' => 'rooms',
                'message' => 'Generate rooms & beds before activation',
            ];
        }

        $hasStaffMapping = collect(data_get($wizardData, 'staff.hostel_assignments', []))
            ->contains(function ($assignment) use ($hostel) {
                return is_array($assignment) && (int) ($assignment['hostel_id'] ?? 0) === (int) $hostel->id;
            });

        if (! $hasStaffMapping) {
            $errors[] = [
                'field' => 'staff',
                'message' => 'Hostel staff mapping is required for each hostel',
            ];
        }

        return $errors;
    }

    /**
     * Get Campus Manager for tenant (tenant-scoped, no hostel)
     */
    private function getCampusManager(string $tenantId): ?User
    {
        // Campus Manager is tenant-scoped, check staff_assignments with NULL hostel_id
        // OR check users table directly for role = Campus Manager
        return User::where('tenant_id', $tenantId)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'Campus Manager');
            })
            ->first();
    }

    /**
     * Get hostel staff assignments
     */
    private function getHostelStaffAssignments(int $hostelId): array
    {
        return DB::table('staff_assignments')
            ->where('hostel_id', $hostelId)
            ->whereNull('revoked_at')
            ->get()
            ->map(function ($assignment) {
                $user = User::find($assignment->user_id);
                return [
                    'user_id' => $assignment->user_id,
                    'role' => $user?->roles->first()?->name,
                ];
            })
            ->toArray();
    }

    /**
     * Check if role is assigned
     */
    private function isRoleAssigned(array $assignments, string $role): bool
    {
        $roleName = ucwords(str_replace('_', ' ', $role));
        foreach ($assignments as $assignment) {
            if (strtolower($assignment['role'] ?? '') === strtolower($roleName)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if role is marked N/A in wizard data
     */
    private function isRoleMarkedNa(array $wizardData, int $hostelId, string $role): bool
    {
        $hostelData = collect($wizardData['hostels'] ?? [])
            ->firstWhere('id', $hostelId);
        
        if (!$hostelData) {
            return false;
        }

        $naRoles = $hostelData['roles_na'] ?? [];
        return in_array($role, $naRoles);
    }

    /**
     * Get Rector contact from wizard data
     */
    private function getRectorContact(string $tenantId, array $wizardData): ?array
    {
        $wizardPhone = data_get($wizardData, 'tenant_info.rector_phone');
        $wizardEmail = data_get($wizardData, 'tenant_info.rector_email');
        if (is_string($wizardPhone) && $wizardPhone !== '') {
            return [
                'phone' => $wizardPhone,
                'email' => is_string($wizardEmail) ? $wizardEmail : null,
            ];
        }

        $rectorUserId = $wizardData['rector_user_id'] ?? null;
        if (!$rectorUserId) {
            return $this->findContactByRole($tenantId, 'Rector');
        }

        $user = User::find($rectorUserId);
        if (!$user) {
            return $this->findContactByRole($tenantId, 'Rector');
        }

        return [
            'phone' => $user->phone,
            'email' => $user->email,
        ];
    }

    /**
     * Get College Management contact from wizard data
     */
    private function getCollegeMgmtContact(string $tenantId, array $wizardData): ?array
    {
        $wizardPhone = data_get($wizardData, 'tenant_info.college_mgmt_phone');
        $wizardEmail = data_get($wizardData, 'tenant_info.college_mgmt_email');
        if (is_string($wizardPhone) && $wizardPhone !== '') {
            return [
                'phone' => $wizardPhone,
                'email' => is_string($wizardEmail) ? $wizardEmail : null,
            ];
        }

        $collegeMgmtUserId = $wizardData['college_mgmt_user_id'] ?? null;
        if (!$collegeMgmtUserId) {
            return $this->findContactByRole($tenantId, 'College Management');
        }

        $user = User::find($collegeMgmtUserId);
        if (!$user) {
            return $this->findContactByRole($tenantId, 'College Management');
        }

        return [
            'phone' => $user->phone,
            'email' => $user->email,
        ];
    }

    private function findContactByRole(string $tenantId, string $roleName): ?array
    {
        $user = User::where('tenant_id', $tenantId)
            ->whereHas('roles', function ($query) use ($roleName) {
                $query->where('name', $roleName);
            })
            ->first();

        if (!$user) {
            return null;
        }

        return [
            'phone' => $user->phone,
            'email' => $user->email,
        ];
    }
}
