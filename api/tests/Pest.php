<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in(
        'Feature/AdminPanelAccessTest.php',
        'Feature/Attachments',
        'Feature/Attendance',
        'Feature/AttendanceV2',
        'Feature/Auth',
        'Feature/Campus',
        'Feature/CampusManager',
        'Feature/Checklists',
        'Feature/Dashboard',
        'Feature/ExampleTest.php',
        'Feature/Feature',
        'Feature/FeatureFlags',
        'Feature/Filament',
        'Feature/Gate',
        'Feature/Hostel',
        'Feature/Imports',
        'Feature/Infra',
        'Feature/Laundry',
        'Feature/Manual',
        'Feature/Middleware',
        'Feature/MobileP0',
        'Feature/Observability',
        'Feature/Onboarding',
        'Feature/OtpThrottleTest.php',
        'Feature/OutPass',
        'Feature/Payments',
        'Feature/RLS',
        'Feature/Rector',
        'Feature/Room',
        'Feature/RoomChanges',
        'Feature/AutoAllocation',
        'Feature/Sports',
        'Feature/StaffAssignmentIntegrationTest.php',
        'Feature/StaffAssignmentTest.php',
        'Feature/Student',
        'Feature/SuperAdmin',
        'Feature/Tenancy',
        'Feature/Tickets',
        'Feature/Uat',
        'Feature/Visitors'
    );

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
