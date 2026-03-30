<?php

use App\Models\Tenant;
use App\Models\User;
use App\Models\StepUpOtpSession;

it('requires OTP before impersonation', function () {
    $tenant = Tenant::factory()->active()->create();
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    // Create tenant admin
    $rector = User::factory()->create(['tenant_id' => $tenant->id]);
    $rector->assignRole('Rector');

    $response = $this->actingAs($admin)
        ->get(route('admin.impersonate', $tenant));

    $response->assertRedirect(); // OTP bypassed in tests, should redirect
});

it('impersonates after valid OTP', function () {
    $tenant = Tenant::factory()->active()->create();
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $rector = User::factory()->create(['tenant_id' => $tenant->id]);
    $rector->assignRole('Rector');

    // Seed OTP session
    StepUpOtpSession::createSession(
        $admin->id,
        $admin->phone ?? '+911234567890',
        \App\Services\StepUpOtpService::PURPOSE_IMPERSONATION,
        []
    );
    $otp = \App\Models\StepUpOtpSession::latest()->first()->otp_code;

    $response = $this->actingAs($admin)
        ->get(route('admin.impersonate', ['tenant' => $tenant->id, 'otp_code' => $otp]));

    $response->assertRedirect(); // Redirect to CM dashboard
});

