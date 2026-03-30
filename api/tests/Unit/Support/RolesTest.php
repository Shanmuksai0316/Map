<?php

namespace Tests\Unit\Support;

use App\Support\Roles;
use Tests\TestCase;

class RolesTest extends TestCase
{
    public function test_all_roles_are_defined(): void
    {
        $expectedRoles = [
            'Super Admin',
            'Campus Manager',
            'Rector',
            'Warden',
            'Guard',
            'HK Supervisor',
            'RM Supervisor',
            'Laundry Manager',
            'Sports Manager',
        ];

        $actualRoles = Roles::all();

        $this->assertEquals($expectedRoles, $actualRoles);
    }

    public function test_is_valid_returns_true_for_valid_roles(): void
    {
        $this->assertTrue(Roles::isValid(Roles::SUPER_ADMIN));
        $this->assertTrue(Roles::isValid(Roles::CAMPUS_MANAGER));
        $this->assertTrue(Roles::isValid(Roles::WARDEN));
        $this->assertTrue(Roles::isValid(Roles::LAUNDRY_MANAGER));
    }

    public function test_is_valid_returns_false_for_invalid_roles(): void
    {
        $this->assertFalse(Roles::isValid('Invalid Role'));
        $this->assertFalse(Roles::isValid(''));
        $this->assertFalse(Roles::isValid('Student')); // Not in our role constants
    }

    public function test_attendance_managers_includes_correct_roles(): void
    {
        $managers = Roles::attendanceManagers();

        $this->assertContains(Roles::WARDEN, $managers);
        $this->assertContains(Roles::CAMPUS_MANAGER, $managers);
        $this->assertNotContains(Roles::GUARD, $managers);
        $this->assertNotContains(Roles::LAUNDRY_MANAGER, $managers);
    }

    public function test_laundry_managers_includes_correct_roles(): void
    {
        $managers = Roles::laundryManagers();

        $this->assertContains(Roles::LAUNDRY_MANAGER, $managers);
        $this->assertNotContains(Roles::WARDEN, $managers);
        $this->assertNotContains(Roles::SPORTS_MANAGER, $managers);
    }

    public function test_sports_managers_includes_correct_roles(): void
    {
        $managers = Roles::sportsManagers();

        $this->assertContains(Roles::SPORTS_MANAGER, $managers);
        $this->assertNotContains(Roles::WARDEN, $managers);
        $this->assertNotContains(Roles::LAUNDRY_MANAGER, $managers);
    }

    public function test_supervisors_includes_correct_roles(): void
    {
        $supervisors = Roles::supervisors();

        $this->assertContains(Roles::HK_SUPERVISOR, $supervisors);
        $this->assertContains(Roles::RM_SUPERVISOR, $supervisors);
        $this->assertNotContains(Roles::WARDEN, $supervisors);
        $this->assertNotContains(Roles::LAUNDRY_MANAGER, $supervisors);
    }

    public function test_role_constants_have_expected_values(): void
    {
        $this->assertEquals('Super Admin', Roles::SUPER_ADMIN);
        $this->assertEquals('Campus Manager', Roles::CAMPUS_MANAGER);
        $this->assertEquals('Rector', Roles::RECTOR);
        $this->assertEquals('Warden', Roles::WARDEN);
        $this->assertEquals('Guard', Roles::GUARD);
        $this->assertEquals('HK Supervisor', Roles::HK_SUPERVISOR);
        $this->assertEquals('RM Supervisor', Roles::RM_SUPERVISOR);
        $this->assertEquals('Laundry Manager', Roles::LAUNDRY_MANAGER);
        $this->assertEquals('Sports Manager', Roles::SPORTS_MANAGER);
    }
}
