<?php

namespace App\Support;

final class Roles
{
    public const SUPER_ADMIN = 'Super Admin';
    public const CAMPUS_MANAGER = 'Campus Manager';
    public const RECTOR = 'Rector';
    public const COLLEGE_MANAGEMENT = 'College Management';
    public const WARDEN = 'Warden';
    public const GUARD = 'Guard';
    public const HK_SUPERVISOR = 'HK Supervisor';
    public const RM_SUPERVISOR = 'RM Supervisor';
    public const LAUNDRY_MANAGER = 'Laundry Manager';
    public const SPORTS_MANAGER = 'Sports Manager';
    public const STUDENT = 'Student';
    
    /**
     * Get all role constants as an array
     */
    public static function all(): array
    {
        return [
            self::SUPER_ADMIN,
            self::CAMPUS_MANAGER,
            self::RECTOR,
            self::COLLEGE_MANAGEMENT,
            self::WARDEN,
            self::GUARD,
            self::HK_SUPERVISOR,
            self::RM_SUPERVISOR,
            self::LAUNDRY_MANAGER,
            self::SPORTS_MANAGER,
            self::STUDENT,
        ];
    }
    
    /**
     * Check if a role is valid
     */
    public static function isValid(string $role): bool
    {
        return in_array($role, self::all());
    }
    
    /**
     * Get roles that can manage attendance
     */
    public static function attendanceManagers(): array
    {
        return [
            self::WARDEN,
            self::CAMPUS_MANAGER,
            self::STUDENT,
        ];
    }
    
    /**
     * Get roles that can manage laundry
     */
    public static function laundryManagers(): array
    {
        return [
            self::LAUNDRY_MANAGER,
        ];
    }
    
    /**
     * Get roles that can manage sports
     */
    public static function sportsManagers(): array
    {
        return [
            self::SPORTS_MANAGER,
        ];
    }
    
    /**
     * Get supervisor roles
     */
    public static function supervisors(): array
    {
        return [
            self::HK_SUPERVISOR,
            self::RM_SUPERVISOR,
        ];
    }

    /**
     * Get MAP staff roles (assigned by Super Admin, require hostel assignment)
     */
    public static function mapStaffRoles(): array
    {
        return [
            self::CAMPUS_MANAGER,
            self::WARDEN,
            self::GUARD,
            self::HK_SUPERVISOR,
            self::RM_SUPERVISOR,
            self::LAUNDRY_MANAGER,
            self::SPORTS_MANAGER,
        ];
    }

    /**
     * Get college representative roles (created during onboarding, no hostel assignment)
     */
    public static function collegeRepresentativeRoles(): array
    {
        return [
            self::RECTOR,
            self::COLLEGE_MANAGEMENT,
        ];
    }

    /**
     * Check if role is MAP staff
     */
    public static function isMapStaffRole(string $role): bool
    {
        return in_array($role, self::mapStaffRoles());
    }

    /**
     * Check if role is college representative
     */
    public static function isCollegeRepresentativeRole(string $role): bool
    {
        return in_array($role, self::collegeRepresentativeRoles());
    }
}
