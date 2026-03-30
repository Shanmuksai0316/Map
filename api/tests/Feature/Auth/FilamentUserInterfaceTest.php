<?php

use App\Models\User;
use Filament\Models\Contracts\FilamentUser;
use Filament\Facades\Filament;

/*
|--------------------------------------------------------------------------
| Filament User Interface Test
|--------------------------------------------------------------------------
|
| This test prevents the 403 error regression by ensuring the User model
| always implements the required FilamentUser interface.
|
| This was the root cause of the October 28, 2025 production 403 issue.
|
*/

it('user model implements FilamentUser interface', function () {
    $user = new User();
    
    expect($user)->toBeInstanceOf(FilamentUser::class)
        ->and(method_exists($user, 'canAccessPanel'))->toBeTrue();
});

it('user model has canAccessPanel method with correct signature', function () {
    $reflection = new ReflectionMethod(User::class, 'canAccessPanel');
    
    expect($reflection->isPublic())->toBeTrue()
        ->and($reflection->getNumberOfParameters())->toBe(1)
        ->and($reflection->getReturnType()->getName())->toBe('bool');
});

it('super admin can access admin panel', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);
    $user->assignRole('Super Admin');
    
    $panel = Filament::getPanel('admin');
    
    expect($user->canAccessPanel($panel))->toBeTrue();
});

it('non-super admin cannot access admin panel', function () {
    $user = User::factory()->create(['email' => 'regular@example.com']);
    
    $panel = Filament::getPanel('admin');
    
    expect($user->canAccessPanel($panel))->toBeFalse();
});

it('user with wrong role cannot access admin panel', function () {
    $user = User::factory()->create(['email' => 'campus@example.com']);
    $user->assignRole('Campus Manager');
    
    $panel = Filament::getPanel('admin');
    
    expect($user->canAccessPanel($panel))->toBeFalse();
});

it('campus manager can access campus manager panel', function () {
    $user = User::factory()->create(['email' => 'campus@example.com']);
    $user->assignRole('Campus Manager');
    
    $panel = Filament::getPanel('campus-manager');
    
    expect($user->canAccessPanel($panel))->toBeTrue();
})->skip('Campus Manager panel not yet implemented');

it('rector can access rector panel', function () {
    $user = User::factory()->create(['email' => 'rector@example.com']);
    $user->assignRole('Rector');
    
    $panel = Filament::getPanel('rector');
    
    expect($user->canAccessPanel($panel))->toBeTrue();
})->skip('Rector panel not yet implemented');

it('user database connection is environment-aware', function () {
    $user = new User();
    
    // Should use default connection (pgsql in production, sqlite in local)
    expect($user->getConnectionName())->toBe(config('database.default'));
});


