<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    // RefreshDatabase trait already handles migrations
    // Calling migrate:fresh causes VACUUM to run inside transaction
    $this->artisan('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class]);
    $this->artisan('db:seed', ['--class' => \Database\Seeders\DemoTenantSeeder::class]);
});

test('super admin can access admin panel', function () {
    $superAdmin = User::where('email', 'super@demo.map.ac.in')->first();
    expect($superAdmin)->not->toBeNull();
    expect($superAdmin->hasRole('Super Admin'))->toBeTrue();
    
    $response = $this->actingAs($superAdmin)->get('/admin');
    $response->assertStatus(200);
});

test('campus manager can access campus manager panel', function () {
    $campusManager = User::where('email', 'campus@demo.map.ac.in')->first();
    expect($campusManager)->not->toBeNull();
    expect($campusManager->hasRole('Campus Manager'))->toBeTrue();
    
    $response = $this->actingAs($campusManager)->get('/campus-manager');
    $response->assertStatus(200);
});

test('rector can access admin panel', function () {
    $rector = User::where('email', 'rector@demo.map.ac.in')->first();
    expect($rector)->not->toBeNull();
    expect($rector->hasRole('Rector'))->toBeTrue();
    
    $response = $this->actingAs($rector)->get('/admin');
    $response->assertStatus(200);
});

test('warden has correct role assignment', function () {
    $wardenH1 = User::where('email', 'warden.h1@demo.map.ac.in')->first();
    $wardenH2 = User::where('email', 'warden.h2@demo.map.ac.in')->first();
    
    expect($wardenH1)->not->toBeNull();
    expect($wardenH2)->not->toBeNull();
    
    expect($wardenH1->hasRole('Warden'))->toBeTrue();
    expect($wardenH2->hasRole('Warden'))->toBeTrue();
});

test('supervisors have correct role assignments', function () {
    $hkSupervisor = User::where('email', 'hk@demo.map.ac.in')->first();
    $rmSupervisor = User::where('email', 'rm@demo.map.ac.in')->first();
    
    expect($hkSupervisor)->not->toBeNull();
    expect($rmSupervisor)->not->toBeNull();
    
    expect($hkSupervisor->hasRole('HK Supervisor'))->toBeTrue();
    expect($rmSupervisor->hasRole('RM Supervisor'))->toBeTrue();
});

test('add-on managers have correct role assignments', function () {
    $guard = User::where('email', 'guard@demo.map.ac.in')->first();
    $laundryManager = User::where('email', 'laundry@demo.map.ac.in')->first();
    $sportsManager = User::where('email', 'sports@demo.map.ac.in')->first();
    
    expect($guard)->not->toBeNull();
    expect($laundryManager)->not->toBeNull();
    expect($sportsManager)->not->toBeNull();
    
    expect($guard->hasRole('Guard'))->toBeTrue();
    expect($laundryManager->hasRole('Laundry Manager'))->toBeTrue();
    expect($sportsManager->hasRole('Sports Manager'))->toBeTrue();
});

test('users have proper permissions assigned', function () {
    $superAdmin = User::where('email', 'super@demo.map.ac.in')->first();
    $campusManager = User::where('email', 'campus@demo.map.ac.in')->first();
    $rector = User::where('email', 'rector@demo.map.ac.in')->first();
    
    // Super Admin should have all permissions
    expect($superAdmin->can('tenant.manage'))->toBeTrue();
    expect($superAdmin->can('campus.manage'))->toBeTrue();
    expect($superAdmin->can('outpass.decide'))->toBeTrue();
    
    // Campus Manager should have campus management permissions
    expect($campusManager->can('campus.manage'))->toBeTrue();
    expect($campusManager->can('room.allocation.manage'))->toBeTrue();
    expect($campusManager->can('student.manage'))->toBeTrue();
    
    // Rector should have outpass decision permissions
    expect($rector->can('outpass.decide'))->toBeTrue();
    expect($rector->can('outpass.view'))->toBeTrue();
    expect($rector->cannot('campus.manage'))->toBeTrue();
});

test('filament resources are accessible based on permissions', function () {
    $campusManager = User::where('email', 'campus@demo.map.ac.in')->first();
    
    // Test that Campus Manager can access their panel resources
    $response = $this->actingAs($campusManager)->get('/campus-manager/students');
    $response->assertStatus(200);
    
    $response = $this->actingAs($campusManager)->get('/campus-manager/rooms');
    $response->assertStatus(200);
    
    $response = $this->actingAs($campusManager)->get('/campus-manager/out-passes');
    $response->assertStatus(200);
});

test('demo users have correct tenant association', function () {
    $demoTenant = \App\Models\Tenant::where('code', 'DEMO-COLLEGE')->first();
    expect($demoTenant)->not->toBeNull();
    
    $users = User::whereIn('email', [
        'super@demo.map.ac.in',
        'campus@demo.map.ac.in',
        'rector@demo.map.ac.in',
    ])->get();
    
    foreach ($users as $user) {
        expect($user->tenant_id)->toBe($demoTenant->id);
    }
});

test('role hierarchy is properly established', function () {
    $superAdmin = User::where('email', 'super@demo.map.ac.in')->first();
    $campusManager = User::where('email', 'campus@demo.map.ac.in')->first();
    
    // Super Admin should have more permissions than Campus Manager
    $superAdminPermissions = $superAdmin->getAllPermissions()->pluck('name');
    $campusManagerPermissions = $campusManager->getAllPermissions()->pluck('name');
    
    expect($superAdminPermissions->count())->toBeGreaterThan($campusManagerPermissions->count());
    expect($superAdminPermissions->contains('tenant.manage'))->toBeTrue();
    expect($campusManagerPermissions->contains('tenant.manage'))->toBeFalse();
});
