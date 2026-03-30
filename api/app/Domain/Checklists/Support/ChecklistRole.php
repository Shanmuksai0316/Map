<?php

namespace App\Domain\Checklists\Support;

final class ChecklistRole
{
    /**
     * Canonical role keys used by checklists domain (templates/instances).
     *
     * @return array<string, string> map canonical => human label
     */
    public static function options(): array
    {
        return [
            'CampusManager' => 'Campus Manager',
            'Warden' => 'Warden',
            'HKSupervisor' => 'HK Supervisor',
            'RMSupervisor' => 'RM Supervisor',
            'Guard' => 'Security Guard',
            'LaundryManager' => 'Laundry Manager',
            'SportsManager' => 'Sports Manager',
        ];
    }

    /**
     * Normalize any incoming role string to a canonical role key.
     */
    public static function canonical(?string $role): string
    {
        $role = trim((string) $role);
        if ($role === '') {
            return 'Warden';
        }

        // Strip non-alphanumerics and lowercase for matching.
        $key = strtolower(preg_replace('/[^a-z0-9]+/i', '', $role) ?? '');

        return match ($key) {
            'campusmanager', 'campusmanagerdashboard', 'campusmanagerpanel', 'campusmanagerrole', 'campusmanageruser', 'campusmanagerstaff', 'campusmanagerapp',
            'campusmanager', 'campusmanager' => 'CampusManager',
            'warden' => 'Warden',
            'hksupervisor', 'housekeeping', 'housekeepingsupervisor' => 'HKSupervisor',
            'rmsupervisor', 'repairsupervisor', 'maintenancesupervisor', 'roomsupervisor' => 'RMSupervisor',
            'guard', 'security', 'securityguard' => 'Guard',
            'laundrymanager', 'laundry' => 'LaundryManager',
            'sportsmanager', 'sports' => 'SportsManager',
            default => self::fallbackCanonical($role),
        };
    }

    private static function fallbackCanonical(string $role): string
    {
        // If already one of our canonical keys, keep it.
        if (array_key_exists($role, self::options())) {
            return $role;
        }

        // Try to match by label.
        foreach (self::options() as $canonical => $label) {
            if (strcasecmp($role, $label) === 0) {
                return $canonical;
            }
        }

        return 'Warden';
    }

    /**
     * Default checklist tasks per role (distinct per role for e2e testing and editing).
     *
     * @return array<int, array{code: string, label: string, require_photo: bool, require_comment: bool}>
     */
    public static function defaultTasksForRole(string $canonical): array
    {
        $canonical = self::canonical($canonical);

        return match ($canonical) {
            'CampusManager' => [
                ['code' => 'CM_BOARD', 'label' => '[Campus] Check notice board', 'require_photo' => false, 'require_comment' => false],
                ['code' => 'CM_OFFICE', 'label' => '[Campus] Office cleanliness', 'require_photo' => false, 'require_comment' => false],
                ['code' => 'CM_MEETING', 'label' => '[Campus] Staff meeting log', 'require_photo' => false, 'require_comment' => true],
            ],
            'Warden' => [
                ['code' => 'WARDEN_WATER', 'label' => '[Warden] Check water tanker', 'require_photo' => false, 'require_comment' => false],
                ['code' => 'WARDEN_HYGIENE', 'label' => '[Warden] Hygiene & washrooms', 'require_photo' => false, 'require_comment' => false],
                ['code' => 'WARDEN_LIGHTS', 'label' => '[Warden] Lights in corridors', 'require_photo' => false, 'require_comment' => false],
                ['code' => 'WARDEN_SECURITY', 'label' => '[Warden] Security post log book', 'require_photo' => false, 'require_comment' => false],
                ['code' => 'WARDEN_NOTICES', 'label' => '[Warden] New notices / announcements', 'require_photo' => false, 'require_comment' => false],
            ],
            'HKSupervisor' => [
                ['code' => 'HK_ROOMS', 'label' => '[HK] Room cleaning status', 'require_photo' => true, 'require_comment' => false],
                ['code' => 'HK_CORRIDORS', 'label' => '[HK] Corridor & common areas', 'require_photo' => false, 'require_comment' => false],
                ['code' => 'HK_SUPPLIES', 'label' => '[HK] Cleaning supplies stock', 'require_photo' => false, 'require_comment' => true],
            ],
            'RMSupervisor' => [
                ['code' => 'RM_REPAIRS', 'label' => '[RM] Pending repairs log', 'require_photo' => true, 'require_comment' => true],
                ['code' => 'RM_EQUIPMENT', 'label' => '[RM] Equipment check', 'require_photo' => false, 'require_comment' => false],
            ],
            'Guard' => [
                ['code' => 'GUARD_GATE', 'label' => '[Guard] Gate log & visitor register', 'require_photo' => false, 'require_comment' => false],
                ['code' => 'GUARD_ROUNDS', 'label' => '[Guard] Night rounds completed', 'require_photo' => false, 'require_comment' => true],
            ],
            'LaundryManager' => [
                ['code' => 'LAUNDRY_MACHINES', 'label' => '[Laundry] Machine status', 'require_photo' => false, 'require_comment' => false],
                ['code' => 'LAUNDRY_PICKUP', 'label' => '[Laundry] Pickup/delivery log', 'require_photo' => false, 'require_comment' => true],
            ],
            'SportsManager' => [
                ['code' => 'SPORTS_EQUIPMENT', 'label' => '[Sports] Equipment inventory', 'require_photo' => false, 'require_comment' => false],
                ['code' => 'SPORTS_VENUE', 'label' => '[Sports] Venue readiness', 'require_photo' => true, 'require_comment' => false],
            ],
            default => [
                ['code' => 'CHECK_WATER', 'label' => 'Check water tanker', 'require_photo' => false, 'require_comment' => false],
                ['code' => 'CHECK_HYGIENE', 'label' => 'Check hygiene & washrooms', 'require_photo' => false, 'require_comment' => false],
                ['code' => 'CHECK_LIGHTS', 'label' => 'Check lights in corridors', 'require_photo' => false, 'require_comment' => false],
            ],
        };
    }

    /**
     * Map canonical checklist role to Spatie role names stored on users.
     *
     * @return array<int, string>
     */
    public static function spatieRoleNames(string $canonical): array
    {
        return match ($canonical) {
            'CampusManager' => ['Campus Manager'],
            'HKSupervisor' => ['HK Supervisor'],
            'RMSupervisor' => ['RM Supervisor'],
            'LaundryManager' => ['Laundry Manager'],
            'SportsManager' => ['Sports Manager'],
            'Guard' => ['Guard'],
            'Warden' => ['Warden'],
            default => [$canonical],
        };
    }
}

