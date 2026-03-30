<?php

// Diagnostic routes - only active when DEBUG_403=true
if (env('DEBUG_403', false)) {
    Route::middleware(['auth:web'])->group(function () {
        Route::get('/__diag/admin', [App\Http\Controllers\DiagController::class, 'admin']);
    });
    
    Route::get('/__diag/session-ping', [App\Http\Controllers\DiagController::class, 'sessionPing']);
}


// Local-only SSO shim for E2E: switch user for panel tests
if (app()->environment('local')) {
    Route::get('/_dev/sso/{panel}/{tenant}', function (string $panel, string $tenant) {
        $map = [
            'campus-manager' => 'cm1@stxaviers.edu',
            'rector' => 'rector@stxaviers.edu',
            'college-mgmt' => 'admin1@stxaviers.edu',
        ];
        $email = $map[$panel] ?? null;
        if (!$email) {
            abort(404);
        }
        $user = \App\Models\User::where('email', $email)->first();
        if (!$user) {
            abort(404, 'User not found');
        }
        \Auth::login($user);
        return redirect("/{$panel}/{$tenant}");
    });
}



