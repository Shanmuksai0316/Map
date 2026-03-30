<?php

/*
|--------------------------------------------------------------------------
| Central API Routes (Super Admin Only)
|--------------------------------------------------------------------------
|
| These routes run on the CENTRAL database (not tenant databases).
| Used for Super Admin operations like tenant management.
|
| Tenant-specific routes have been moved to routes/tenant.php
| and are accessed via subdomains (e.g., jnu.yourapp.com/v1/...)
|
*/

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\PreventAccessFromTenantDomains;

// Central Health check (admin domain) - public endpoint, optional auth
Route::get('/v1/healthz', function () {
    $checks = [];
    $healthy = true;
    
    // Database check
    try {
        \DB::connection()->getPdo();
        $checks['db'] = 'ok';
    } catch (\Exception $e) {
        $checks['db'] = 'error';
        $healthy = false;
    }
    
    // Cache check
    try {
        \Cache::put('healthz_check', 'ok', 5);
        $checks['cache'] = \Cache::get('healthz_check') === 'ok' ? 'ok' : 'error';
        if ($checks['cache'] !== 'ok') {
            $healthy = false;
        }
    } catch (\Exception $e) {
        $checks['cache'] = 'error';
        $healthy = false;
    }
    
    // Optional: Check auth if token is provided
    $bearer = request()->bearerToken();
    if ($bearer !== null) {
        try {
            $token = \Laravel\Sanctum\PersonalAccessToken::findToken($bearer);
            $checks['auth'] = $token ? 'ok' : 'invalid_token';
        } catch (\Exception $e) {
            $checks['auth'] = 'error';
        }
    } else {
        $checks['auth'] = 'not_provided';
    }

    return response()->json([
        'status' => $healthy ? 'ok' : 'degraded',
        'context' => 'central',
        'checks' => $checks,
        'timestamp' => now()->toISOString(),
    ], $healthy ? 200 : 503);
});

Route::get('/v1/tenant-healthz', function () {
    return response()->json([
        'status' => 'ok',
        'tenant' => tenant()->code ?? null,
        'timestamp' => now()->toISOString(),
    ]);
})->middleware([
    'api',
    \App\Http\Middleware\SetPostgresSessionTenant::class,
    \App\Http\Middleware\EnsureTenantScope::class,
]);

// Resolve tenancy middleware only if package provides it (tests disable tenancy)
$preventAccessFromTenantDomains = class_exists(\Stancl\Tenancy\Middleware\PreventAccessFromTenantDomains::class)
    ? \Stancl\Tenancy\Middleware\PreventAccessFromTenantDomains::class
    : null;

// Tenant onboarding routes (Super Admin only)
Route::prefix('v1/tenants')->middleware(array_values(array_filter([
    'api',
    $preventAccessFromTenantDomains,
    'auth:sanctum',
    \App\Http\Middleware\IdempotencyMiddleware::class,
])))->group(function () {
    Route::post('/', [App\Http\Controllers\Api\V1\TenantOnboardingController::class, 'store']);
    Route::put('/{tenant}/wizard', [App\Http\Controllers\Api\V1\TenantOnboardingController::class, 'updateWizard']);
    Route::post('/{tenant}/activate', [App\Http\Controllers\Api\V1\TenantOnboardingController::class, 'activate']);
    Route::post('/{tenant}/rollback', [App\Http\Controllers\Api\V1\TenantOnboardingController::class, 'rollback']);
    Route::get('/', [App\Http\Controllers\Api\V1\TenantOnboardingController::class, 'index'])->withoutMiddleware([\App\Http\Middleware\IdempotencyMiddleware::class]);
});

// Tenant listing for mobile app
Route::get('/v1/tenants', [App\Http\Controllers\Api\V1\TenantController::class, 'index']);

// Upload routes (Super Admin only)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/v1/uploads/presigned', [App\Http\Controllers\Api\V1\UploadController::class, 'presigned']);
});

// Admin API (Super Admin only) - used by tests to verify authz
Route::prefix('v1/admin')->middleware([
    'api',
    'auth:sanctum',
    \App\Http\Middleware\EnsureUserHasRole::class . ':Super Admin',
])->group(function () {
    Route::get('/campuses', function () {
        return \App\Models\Campus::query()->limit(50)->get();
    });
});

// Super Admin Routes (Central Database)
Route::prefix('v1/super-admin')->middleware(array_values(array_filter([
    'api',
    $preventAccessFromTenantDomains,
    'auth:sanctum',
    \App\Http\Middleware\EnsureUserHasRole::class . ':Super Admin',
])))->group(function () {
    
    // Tenant Management
    Route::get('/tenants', function () {
        return \App\Models\Tenant::with('domains')->get();
    });
    
    Route::post('/tenants', function (\Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'code' => 'required|string|unique:tenants,code',
            'name' => 'required|string',
            'addon_security' => 'boolean',
            'addon_sports' => 'boolean',
            'addon_laundry' => 'boolean',
        ]);
        
        $tenant = \App\Models\Tenant::create($validated);
        $tenant->domains()->create([
            'domain' => $validated['code'] . '.localhost',
        ]);
        
        return response()->json($tenant->load('domains'), 201);
    });
    
    // More super admin routes...
});

// Supervisor routes at /v1/supervisor (mobile app may call /supervisor/tickets when using central base URL)
Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureTenantScope::class])->prefix('v1/supervisor')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Api\V1\Staff\SupervisorController::class, 'dashboard']);
    Route::get('/tickets', [\App\Http\Controllers\Api\V1\Staff\SupervisorController::class, 'tickets']);
    Route::post('/tickets/{ticket}/status', [\App\Http\Controllers\TicketController::class, 'updateStatus']);
});

// Campus Manager staff checklist summary – also under v1 (no /mobile) so first path app tries matches on central
Route::prefix('v1')->middleware(['auth:sanctum', \App\Http\Middleware\EnsureCampusManager::class])->group(function () {
    Route::get('campus-manager/staff', [App\Http\Controllers\Api\V1\CampusManager\StaffController::class, 'index']);
    Route::get('campus-manager/staff/{user}', [App\Http\Controllers\Api\V1\CampusManager\StaffController::class, 'show']);
    Route::get('campus-manager/checklists/staff-summary', [App\Http\Controllers\Api\V1\CampusManager\StaffController::class, 'staffChecklistSummary']);
    Route::get('campus-manager/checklists/staff/{user}', [App\Http\Controllers\Api\V1\CampusManager\StaffController::class, 'staffChecklistDetail']);
});

// Mobile API routes (bypass tenancy for mobile app)
// These routes require X-Tenant-Code header and manually initialize tenancy + RLS
Route::prefix('v1/mobile')->group(function () {
    Route::post('/auth/tenant-lookup', \App\Http\Controllers\Api\V1\MobileTenantLookupController::class);
    
    // TEMP: Disable strict rate limit for mobile OTP send/verify during testing
    // (Route-level throttle removed; backend still has basic safeguards)
    Route::post('/auth/send-otp', [\App\Http\Controllers\Api\V1\MobileAuthController::class, 'sendOtp']);
    Route::post('/auth/verify-otp', [\App\Http\Controllers\Api\V1\MobileAuthController::class, 'verifyOtp']);
    
    // Logout endpoint for mobile app
    Route::middleware(['auth:sanctum'])->post('/auth/logout', function () {
        $user = auth()->user();
        if ($user) {
            $user->currentAccessToken()?->delete();
        }
        return response()->json(['success' => true, 'message' => 'Logged out successfully']);
    });
    
    Route::get('/healthz', function () {
        $tenant = \App\Models\Tenant::first();
        return response()->json([
            'status' => 'ok',
            'tenant' => $tenant ? $tenant->code : 'none',
            'timestamp' => now()->toISOString(),
        ]);
    });

    // Campus Manager staff checklist summary (explicit early route so it always matches)
    Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureCampusManager::class])
        ->get('campus-manager/checklists/staff-summary', [App\Http\Controllers\Api\V1\CampusManager\StaffController::class, 'staffChecklistSummary']);
    Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureCampusManager::class])
        ->get('campus-manager/checklists/staff/{user}', [App\Http\Controllers\Api\V1\CampusManager\StaffController::class, 'staffChecklistDetail']);
    Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureCampusManager::class])
        ->get('campus-manager/staff', [App\Http\Controllers\Api\V1\CampusManager\StaffController::class, 'index']);
    Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureCampusManager::class])
        ->get('campus-manager/staff/{user}', [App\Http\Controllers\Api\V1\CampusManager\StaffController::class, 'show']);

    // Campus Manager / Warden emergency unread count (explicit early route for mobile app)
    Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureCampusManager::class])
        ->get('campus-manager/emergency/incidents/unread-count', [App\Http\Controllers\Api\V1\CampusManager\EmergencyController::class, 'unreadCount']);
    Route::middleware(['auth:sanctum'])
        ->get('warden/emergency/incidents/unread-count', [App\Http\Controllers\Api\V1\CampusManager\EmergencyController::class, 'unreadCount']);

    // Gate Pass routes
    Route::post('/gate-pass/verify', [App\Http\Controllers\Api\V1\GatePassController::class, 'verify']);
    Route::post('/gate-pass/scan', [App\Http\Controllers\Api\V1\GatePassController::class, 'scan']);
    Route::get('/gate-pass/stats', [App\Http\Controllers\Api\V1\GatePassController::class, 'stats']);
    
    // Feature Flags routes
    Route::get('/tenant/feature-flags', [App\Http\Controllers\Api\V1\FeatureFlagsController::class, 'index']);
    Route::post('/tenant/feature-flags', [App\Http\Controllers\Api\V1\FeatureFlagsController::class, 'update']);
    Route::get('/tenant/feature-flags/defaults', [App\Http\Controllers\Api\V1\FeatureFlagsController::class, 'defaults']);
    Route::post('/tenant/feature-flags/initialize', [App\Http\Controllers\Api\V1\FeatureFlagsController::class, 'initialize']);
    
    // Tenant logo: signed URL so mobile Image can load without auth header
    Route::get('/tenant-logo/{tenant_id}', [App\Http\Controllers\Api\V1\Mobile\TenantLogoController::class, '__invoke'])
        ->name('api.mobile.tenant-logo');

    // Hostels list (for Campus Manager posting notices)
    Route::middleware(['auth:sanctum'])->get('/hostels', function () {
        $user = auth()->user();
        if (!$user || !$user->tenant_id) {
            return response()->json(['data' => []], 200);
        }
        
        $hostels = \App\Models\Hostel::where('tenant_id', $user->tenant_id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
        
        return response()->json(['data' => $hostels]);
    });
    
    // Offline Sync routes
    Route::post('/offline/sync', [App\Http\Controllers\Api\V1\OfflineSyncController::class, 'sync']);
    Route::get('/offline/status', [App\Http\Controllers\Api\V1\OfflineSyncController::class, 'status']);
    Route::post('/offline/process', [App\Http\Controllers\Api\V1\OfflineSyncController::class, 'process']);
    
    // Student Mobile API Routes (require auth + tenant context)
    Route::middleware([
        'auth:sanctum',
        \App\Http\Middleware\SetPostgresSessionTenant::class,
        \App\Http\Middleware\EnsureTenantScope::class,
    ])->group(function () {
        // Gate Passes (Student)
        Route::get('/gate-passes', [App\Http\Controllers\Api\V1\Student\GatePassController::class, 'index']);
        Route::post('/gate-passes', [App\Http\Controllers\Api\V1\Student\GatePassController::class, 'store']);
        Route::get('/gate-passes/{id}', [App\Http\Controllers\Api\V1\Student\GatePassController::class, 'show']);
        Route::post('/gate-passes/{id}/cancel', [App\Http\Controllers\Api\V1\Student\GatePassController::class, 'cancel']);
        
        // Mobile Profile (Student + Staff)
        Route::get('/profile', [App\Http\Controllers\Api\V1\Mobile\ProfileController::class, 'show']);

        // Account deletion request (Apple 5.1.1(v) - in-app initiation)
        Route::post('/account/deletion-request', [App\Http\Controllers\Api\V1\Mobile\AccountDeletionController::class, 'request']);
        
        // Leaves
        Route::get('/leaves', [App\Http\Controllers\Api\V1\Student\LeaveController::class, 'index']);
        Route::post('/leaves', [App\Http\Controllers\Api\V1\Student\LeaveController::class, 'store']);
        Route::get('/leaves/{id}', [App\Http\Controllers\Api\V1\Student\LeaveController::class, 'show']);
        
        // Sick Leaves
        Route::get('/sick-leaves', [App\Http\Controllers\Api\V1\Student\SickLeaveController::class, 'index']);
        Route::post('/sick-leaves', [App\Http\Controllers\Api\V1\Student\SickLeaveController::class, 'store']);
        Route::get('/sick-leaves/{id}', [App\Http\Controllers\Api\V1\Student\SickLeaveController::class, 'show']);
        
        // Guest Entries
        Route::get('/guest-entries', [App\Http\Controllers\Api\V1\Student\GuestEntryController::class, 'index']);
        Route::post('/guest-entries', [App\Http\Controllers\Api\V1\Student\GuestEntryController::class, 'store']);
        Route::get('/guest-entries/{id}', [App\Http\Controllers\Api\V1\Student\GuestEntryController::class, 'show']);
        
        // Room Changes
        Route::get('/room-changes', [App\Http\Controllers\Api\V1\Student\RoomChangeController::class, 'index']);
        Route::post('/room-changes', [App\Http\Controllers\Api\V1\Student\RoomChangeController::class, 'store']);
        Route::get('/room-changes/{id}', [App\Http\Controllers\Api\V1\Student\RoomChangeController::class, 'show']);
        
        // Dashboard & Stats
        Route::get('/dashboard', [App\Http\Controllers\Api\V1\DashboardController::class, 'index']);
        
        // Notices
        Route::get('/notices', [App\Http\Controllers\Api\V1\Student\NoticeController::class, 'index']);
        Route::get('/notices/{id}', [App\Http\Controllers\Api\V1\Student\NoticeController::class, 'show']);
        Route::post('/notices', [App\Http\Controllers\Api\V1\Mobile\NoticeController::class, 'store']);
        
        // Attendance
        Route::get('/attendance', [App\Http\Controllers\Api\V1\Student\AttendanceController::class, 'index']);
        Route::get('/attendance/stats', [App\Http\Controllers\Api\V1\Student\AttendanceController::class, 'stats']);
        
        // Notifications
        Route::get('/notifications', [App\Http\Controllers\Api\V1\NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [App\Http\Controllers\Api\V1\NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{notification}/read', [App\Http\Controllers\Api\V1\NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [App\Http\Controllers\Api\V1\NotificationController::class, 'markAllAsRead']);
        
        // Device registration for push notifications
        Route::post('/devices/register', [App\Http\Controllers\Api\V1\DeviceController::class, 'register']);
        
        // Laundry (read-only for students)
        Route::get('/laundry/requests', [\App\Http\Controllers\Api\V1\Mobile\LaundryController::class, 'getRequests']);
        Route::get('/laundry/requests/{id}', [\App\Http\Controllers\Api\V1\Mobile\LaundryController::class, 'show']);
        Route::post('/laundry/requests/raise', [\App\Http\Controllers\Api\V1\Mobile\LaundryController::class, 'raiseRequest']);
        Route::patch('/laundry/requests/{id}/status', [\App\Http\Controllers\Api\V1\Mobile\LaundryController::class, 'updateStatus']);
        Route::post('/laundry/requests/{id}/ready-for-pickup', [\App\Http\Controllers\Api\V1\Mobile\LaundryController::class, 'markReadyForPickup']);
        Route::post('/laundry/requests/{id}/verify-code', [\App\Http\Controllers\Api\V1\Mobile\LaundryController::class, 'verifyCode']);
        Route::post('/laundry/requests/{id}/manual-verify', [\App\Http\Controllers\Api\V1\Mobile\LaundryController::class, 'manualVerify']);

        // Parcels (Student): list my parcels and show 4-digit code
        Route::get('/parcels', [App\Http\Controllers\Api\V1\ParcelController::class, 'myParcels']);

        // Sports Facilities - use sports_activities table (sports_facilities does not exist)
        Route::get('/sports/facilities', function () {
            try {
                $activities = \DB::table('sports_activities')
                    ->where('is_active', true)
                    ->get();
                return response()->json(['data' => $activities]);
            } catch (\Exception $e) {
                return response()->json(['data' => []]);
            }
        });

        // Sports Bookings - use sports_enrollments table (sports_bookings does not exist)
        Route::get('/sports/bookings', function () {
            $user = auth()->user();
            if (!$user || !$user->student) {
                return response()->json(['data' => []]);
            }
            try {
                $enrollments = \DB::table('sports_enrollments')
                    ->where('student_id', $user->student->id)
                    ->orderBy('created_at', 'desc')
                    ->limit(50)
                    ->get();
                return response()->json(['data' => $enrollments]);
            } catch (\Exception $e) {
                return response()->json(['data' => []]);
            }
        });
        
        Route::post('/sports/bookings', function (\Illuminate\Http\Request $request) {
            $user = auth()->user();
            if (!$user || !$user->student) {
                return response()->json(['error' => 'Student not found'], 403);
            }

            $data = $request->validate([
                'facility_id' => 'required|integer',
                'booking_date' => 'required|date|after:today',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
            ]);

            try {
                $bookingId = \DB::table('sports_enrollments')->insertGetId([
                    'student_id' => $user->student->id,
                    'tenant_id' => $user->tenant_id,
                    'sports_activity_id' => $data['facility_id'],
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $booking = \DB::table('sports_enrollments')->find($bookingId);
                return response()->json(['data' => $booking], 201);
            } catch (\Exception $e) {
                \Log::error('Sports booking creation failed', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id,
                ]);
                return response()->json(['error' => 'Failed to create booking'], 500);
            }
        });
        
        // Comm Box / Messages
        Route::get('/messages', function () {
            $user = auth()->user();
            $messages = \App\Models\Notice::where('tenant_id', $user->tenant_id)
                ->where(function($q) use ($user) {
                    $q->where('audience', 'all')
                      ->orWhere('audience', 'students');
                    if ($user->student && $user->student->hostel_id) {
                        $q->orWhere('hostel_id', $user->student->hostel_id);
                    }
                })
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();
            return response()->json(['data' => $messages]);
        });
        
        // Feedback
        Route::post('/feedback', function (\Illuminate\Http\Request $request) {
            $user = auth()->user();
            $data = $request->validate([
                'department' => 'required|string|in:housekeeping,repair_maintenance,laundry,sports,food,other',
                'rating' => 'required|integer|min:1|max:5',
                'details' => 'nullable|string|max:1000',
            ]);
            
            // Map 'department' to 'category' for model compatibility
            // Map repair_maintenance to maintenance for existing category
            $category = $data['department'];
            if ($category === 'repair_maintenance') {
                $category = 'maintenance';
            }
            
            $feedback = \App\Models\Feedback::create([
                'tenant_id' => $user->tenant_id,
                'student_id' => $user->student?->id,
                'hostel_id' => $user->student?->hostel_id,
                'category' => $category,
                'rating' => $data['rating'],
                'comments' => $data['details'] ?? null,
                'submitted_at' => now(),
            ]);
            
            return response()->json(['data' => $feedback, 'message' => 'Feedback submitted successfully'], 201);
        });
        
        // Tickets (Housekeeping & Repair/Maintenance requests)
        Route::get('/tickets', function (\Illuminate\Http\Request $request) {
            // #region agent log
            $logData = json_encode([
                'sessionId' => 'debug-session',
                'runId' => 'run1',
                'hypothesisId' => 'J',
                'location' => 'routes/api.php:/tickets GET',
                'message' => 'Student tickets endpoint called',
                'data' => [
                    'url' => $request->fullUrl(),
                    'path' => $request->path(),
                    'has_user' => auth()->check(),
                    'user_id' => auth()->id(),
                    'has_student' => auth()->check() && auth()->user()->student !== null,
                    'has_auth_header' => $request->hasHeader('Authorization'),
                ],
                'timestamp' => time() * 1000,
            ]);
            @file_put_contents('/tmp/debug.log', $logData . "\n", FILE_APPEND);
            \Log::channel('single')->error('🔍 Student /tickets GET endpoint called', [
                'user_id' => auth()->id(),
                'has_student' => auth()->check() && auth()->user()->student !== null,
                'has_auth_header' => $request->hasHeader('Authorization'),
                'url' => $request->fullUrl(),
            ]);
            // #endregion agent log
            
            $user = auth()->user();
            if (!$user) {
                \Log::warning('Student tickets: User not authenticated', [
                    'has_auth_header' => $request->hasHeader('Authorization'),
                    'url' => $request->fullUrl(),
                ]);
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/unauthenticated',
                    'title' => 'Unauthenticated',
                    'status' => 401,
                    'detail' => 'Authentication required. Please log in again.',
                ], 401);
            }
            
            if (!$user->student) {
                \Log::warning('Student tickets: User has no student record', [
                    'user_id' => $user->id,
                    'roles' => $user->roles->pluck('name')->toArray(),
                ]);
                return response()->json(['data' => []]);
            }
            
            $query = \App\Domain\Tickets\Models\Ticket::where('tenant_id', $user->tenant_id)
                ->where('reporter_student_id', $user->student->id);
            
            // Filter by category if provided
            if ($request->has('category')) {
                $category = $request->input('category');
                // Map 'repair_maintenance' to 'maintenance' for database query
                if ($category === 'repair_maintenance') {
                    $query->where('category', 'maintenance');
                } else {
                    $query->where('category', $category);
                }
            }
            
            $tickets = $query->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();
            
            \Illuminate\Support\Facades\Log::info('Tickets fetched', [
                'user_id' => $user->id,
                'student_id' => $user->student->id,
                'category' => $request->input('category'),
                'count' => $tickets->count(),
            ]);
            
            $data = $tickets->map(fn ($ticket) => array_merge($ticket->toArray(), ['is_delayed' => $ticket->isDelayed()]));
            return response()->json(['data' => $data]);
        });
        
        Route::post('/tickets', function (\Illuminate\Http\Request $request) {
            // #region agent log
            $logData = json_encode([
                'sessionId' => 'debug-session',
                'runId' => 'run1',
                'hypothesisId' => 'K',
                'location' => 'routes/api.php:/tickets POST',
                'message' => 'Student create ticket endpoint called',
                'data' => [
                    'url' => $request->fullUrl(),
                    'path' => $request->path(),
                    'has_user' => auth()->check(),
                    'user_id' => auth()->id(),
                    'has_student' => auth()->check() && auth()->user()->student !== null,
                    'has_auth_header' => $request->hasHeader('Authorization'),
                ],
                'timestamp' => time() * 1000,
            ]);
            @file_put_contents('/tmp/debug.log', $logData . "\n", FILE_APPEND);
            \Log::channel('single')->error('🔍 Student /tickets POST endpoint called', [
                'user_id' => auth()->id(),
                'has_student' => auth()->check() && auth()->user()->student !== null,
                'has_auth_header' => $request->hasHeader('Authorization'),
                'url' => $request->fullUrl(),
            ]);
            // #endregion agent log
            
            $user = auth()->user();
            if (!$user) {
                \Log::warning('Student create ticket: User not authenticated', [
                    'has_auth_header' => $request->hasHeader('Authorization'),
                    'url' => $request->fullUrl(),
                ]);
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/unauthenticated',
                    'title' => 'Unauthenticated',
                    'status' => 401,
                    'detail' => 'Authentication required. Please log in again.',
                ], 401);
            }
            
            if (!$user->student) {
                \Log::warning('Student create ticket: User has no student record', [
                    'user_id' => $user->id,
                    'roles' => $user->roles->pluck('name')->toArray(),
                ]);
                return response()->json(['error' => 'Student not found'], 403);
            }
            
            $data = $request->validate([
                'title' => 'required|string|max:255',
                'issue' => 'required|string|max:255',
                'description' => 'nullable|string|max:2000',
                'request_type' => 'required|string|in:housekeeping,repair_maintenance',
                'category' => 'nullable|string',
                'photos' => 'nullable|array',
                'photos.*' => 'nullable|string', // Base64 or URLs
            ]);
            
            try {
                // Get hostel name for location field
                $hostel = \App\Models\Hostel::find($user->student->hostel_id);
                $location = $hostel ? $hostel->name : ('Hostel ' . $user->student->hostel_id);
                
                // Map request_type to category: repair_maintenance -> maintenance
                $category = $data['request_type'];
                if ($category === 'repair_maintenance') {
                    $category = 'maintenance';
                }
                
                $ticket = \App\Domain\Tickets\Models\Ticket::create([
                    'tenant_id' => $user->tenant_id,
                    'hostel_id' => $user->student->hostel_id,
                    'reporter_student_id' => $user->student->id,
                    'title' => $data['title'],
                    'description' => $data['description'] ?? $data['issue'],
                    'category' => $category, // Use mapped category
                    'priority' => 'medium',
                    'status' => 'open',
                    'location' => $location, // Required field
                    'created_by_user_id' => $user->id,
                    'created_by' => $user->id,
                    'photos' => $data['photos'] ?? null,
                    'sla_due_at' => now()->addHours(4),
                ]);
                
                return response()->json(['data' => $ticket, 'message' => 'Ticket created successfully'], 201);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Ticket creation failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'user_id' => $user->id,
                    'data' => $data,
                ]);
                
                return response()->json([
                    'error' => 'Failed to create ticket',
                    'message' => $e->getMessage(),
                ], 500);
            }
        });
        
        // Student emergency reporting (mobile app)
        Route::post('/emergency/medical', [App\Http\Controllers\Api\V1\Student\EmergencyController::class, 'reportMedical']);
        Route::post('/emergency/incident', [App\Http\Controllers\Api\V1\Student\EmergencyController::class, 'reportIncident']);
        
        Route::get('/tickets/{id}', function ($id) {
            $user = auth()->user();
            if (!$user || !$user->student) {
                return response()->json(['error' => 'Student not found'], 403);
            }
            
            $ticket = \App\Domain\Tickets\Models\Ticket::where('tenant_id', $user->tenant_id)
                ->where('reporter_student_id', $user->student->id)
                ->findOrFail($id);
            $data = array_merge($ticket->toArray(), ['is_delayed' => $ticket->isDelayed()]);
            return response()->json(['data' => $data]);
        });
        
        // Notifications (using proper controller)
        Route::get('/notifications', [App\Http\Controllers\Api\V1\NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [App\Http\Controllers\Api\V1\NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{notification}/read', [App\Http\Controllers\Api\V1\NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [App\Http\Controllers\Api\V1\NotificationController::class, 'markAllAsRead']);
        Route::get('/notifications/comm-box', [App\Http\Controllers\Api\V1\NotificationController::class, 'commBox']);
        Route::get('/notifications/comm-box/unread', [App\Http\Controllers\Api\V1\NotificationController::class, 'commBoxUnread']);
        
        // Device registration for push notifications
        Route::post('/devices/register', [App\Http\Controllers\Api\V1\DeviceController::class, 'register']);
    });
    
    // Staff Mobile API Routes (require auth + tenant context)
    Route::middleware([
        'auth:sanctum',
        \App\Http\Middleware\SetPostgresSessionTenant::class,
        \App\Http\Middleware\EnsureTenantScope::class,
    ])->group(function () {
        // Staff Profile handled by Mobile\ProfileController above (single /profile endpoint)

        // Laundry Manager routes (staff only)
        // Note: Role checks are done in controller methods
        Route::prefix('laundry-staff')->group(function () {
            Route::get('/requests', [App\Http\Controllers\Api\V1\LaundryRequestController::class, 'index']);
            Route::post('/requests/raise', [App\Http\Controllers\Api\V1\LaundryRequestController::class, 'raiseForStudent']);
            Route::get('/requests/{laundryRequest}', [App\Http\Controllers\Api\V1\LaundryRequestController::class, 'show']);
            Route::post('/requests/{laundryRequest}/ready-for-pickup', [App\Http\Controllers\Api\V1\LaundryRequestController::class, 'markReadyForPickup']);
        });

        // Campus Manager routes (staff only) – mobile app uses /mobile/campus-manager/emergency/...
        Route::prefix('campus-manager')->middleware(\App\Http\Middleware\EnsureCampusManager::class)->group(function () {
            Route::get('/emergency/incidents', [App\Http\Controllers\Api\V1\CampusManager\EmergencyController::class, 'incidentList']);
            Route::get('/emergency/incidents/unread-count', [App\Http\Controllers\Api\V1\CampusManager\EmergencyController::class, 'unreadCount']);
            Route::post('/emergency/incidents/{incident}/acknowledge', [App\Http\Controllers\Api\V1\CampusManager\EmergencyController::class, 'acknowledgeIncident']);
            Route::get('/emergency/medical', [App\Http\Controllers\Api\V1\CampusManager\EmergencyController::class, 'medicalList']);
            Route::get('/emergency/medical/{id}', [App\Http\Controllers\Api\V1\CampusManager\EmergencyController::class, 'medicalShow']);
            Route::post('/emergency/medical/{id}/acknowledge', [App\Http\Controllers\Api\V1\CampusManager\EmergencyController::class, 'acknowledgeMedical']);
        });

        // Warden routes (staff only)
        // Note: Role check is done in controller methods, so no middleware needed here
        Route::prefix('warden')->group(function () {
            Route::get('/students', [App\Http\Controllers\Api\V1\Staff\WardenController::class, 'students']);
            Route::get('/rooms', [App\Http\Controllers\Api\V1\Staff\WardenController::class, 'rooms']);
            Route::get('/rooms/{roomId}/students', [App\Http\Controllers\Api\V1\Staff\WardenController::class, 'roomStudents']);
            Route::get('/requests', [App\Http\Controllers\Api\V1\Staff\WardenController::class, 'requests']);
            Route::get('/checklist', [App\Http\Controllers\Api\V1\Staff\WardenController::class, 'checklist']);
            Route::post('/checklist/{itemId}/toggle', [App\Http\Controllers\Api\V1\Staff\WardenController::class, 'toggleChecklistItem']);
            Route::post('/checklist/{itemId}/photo', [App\Http\Controllers\Api\V1\Staff\WardenController::class, 'uploadChecklistPhoto']);
            Route::post('/checklist/submit', [App\Http\Controllers\Api\V1\Staff\WardenController::class, 'submitChecklist']);
            Route::get('/unmarked', [App\Http\Controllers\Api\V1\Staff\WardenController::class, 'unmarkedStudents']);
            Route::post('/rooms/{roomId}/attendance', [App\Http\Controllers\Api\V1\Staff\WardenController::class, 'submitAttendance']);

            // Emergency / Incidents (student-reported – same as Campus Manager)
            Route::get('/emergency/incidents', [App\Http\Controllers\Api\V1\CampusManager\EmergencyController::class, 'incidentList']);
            Route::get('/emergency/incidents/unread-count', [App\Http\Controllers\Api\V1\CampusManager\EmergencyController::class, 'unreadCount']);
            Route::post('/emergency/incidents/{incident}/acknowledge', [App\Http\Controllers\Api\V1\CampusManager\EmergencyController::class, 'acknowledgeIncident']);
            Route::get('/emergency/medical', [App\Http\Controllers\Api\V1\CampusManager\EmergencyController::class, 'medicalList']);
            Route::get('/emergency/medical/{id}', [App\Http\Controllers\Api\V1\CampusManager\EmergencyController::class, 'medicalShow']);
            Route::post('/emergency/medical/{id}/acknowledge', [App\Http\Controllers\Api\V1\CampusManager\EmergencyController::class, 'acknowledgeMedical']);

            // Parcels (Warden): search students, list pending, create (inform student), receive (verify code)
            Route::get('/parcels/students-search', [App\Http\Controllers\Api\V1\Staff\WardenController::class, 'parcelStudentsSearch']);
            Route::get('/parcels', [App\Http\Controllers\Api\V1\ParcelController::class, 'index']);
            Route::post('/parcels', [App\Http\Controllers\Api\V1\ParcelController::class, 'store']);
            Route::post('/parcels/{parcel}/receive', [App\Http\Controllers\Api\V1\ParcelController::class, 'receive']);
        });

        // Guard routes (staff only) - mobile app endpoints
        // These routes are registered under /v1/guard to match mobile app expectations
        Route::prefix('guard')->middleware('role:Guard')->group(function () {
            // Guard Checklist
            Route::get('/checklist', [App\Http\Controllers\Api\V1\Guard\ChecklistController::class, 'index']);
            Route::get('/checklist/current', [App\Http\Controllers\Api\V1\Guard\ChecklistController::class, 'current']);
            Route::post('/checklist/{task}/complete', [App\Http\Controllers\Api\V1\Guard\ChecklistController::class, 'completeTask']);
            Route::post('/checklist/{task}/photo', [App\Http\Controllers\Api\V1\Guard\ChecklistController::class, 'uploadPhoto']);
            Route::post('/checklist/submit', [App\Http\Controllers\Api\V1\Guard\ChecklistController::class, 'submit']);
            Route::get('/checklist/history', [App\Http\Controllers\Api\V1\Guard\ChecklistController::class, 'history']);

            // Compatibility aliases (older apps)
            Route::get('/checklists', [App\Http\Controllers\Api\V1\Guard\ChecklistController::class, 'index']);
            Route::get('/checklists/current', [App\Http\Controllers\Api\V1\Guard\ChecklistController::class, 'current']);

            // Time Verification
            Route::post('/verify-time', [App\Http\Controllers\Api\V1\Guard\ChecklistController::class, 'verifyTime']);
            Route::post('/gate/verify-time', [App\Http\Controllers\Api\V1\Guard\ChecklistController::class, 'verifyTime']);

            // Active requests for Guard gate workflow
            Route::get('/outpasses/active', [App\Http\Controllers\Api\V1\Staff\GuardController::class, 'activeOutpasses']);
            Route::get('/leaves/active', [App\Http\Controllers\Api\V1\Staff\GuardController::class, 'activeLeaves']);
            Route::get('/guest-entries/active', [App\Http\Controllers\Api\V1\Staff\GuardController::class, 'activeGuestEntries']);
            
            // Dashboard Stats
            Route::get('/dashboard/stats', [App\Http\Controllers\Api\V1\Staff\GuardController::class, 'dashboardStats']);
            
            // History for profile
            Route::get('/history', [App\Http\Controllers\Api\V1\Staff\GuardController::class, 'history']);
            Route::get('/history/leave', [App\Http\Controllers\Api\V1\Staff\GuardController::class, 'leaveHistory']);
            Route::get('/history/outpass', [App\Http\Controllers\Api\V1\Staff\GuardController::class, 'outpassHistory']);
            Route::get('/history/guest-entry', [App\Http\Controllers\Api\V1\Staff\GuardController::class, 'guestEntryHistory']);
        });
        
        // Rector routes (mobile staff)
        Route::prefix('rector')->middleware(\App\Http\Middleware\EnsureRector::class)->group(function () {
            // Dashboard stats
            Route::get('/dashboard', function () {
                $user = auth()->user();
                $tenantId = $user->tenant_id;
                
                // Get counts for rector dashboard
                $ticketsRaised = \App\Domain\Tickets\Models\Ticket::where('tenant_id', $tenantId)
                    ->whereDate('created_at', '>=', now()->subDays(30))
                    ->count();
                $ticketsPending = \App\Domain\Tickets\Models\Ticket::where('tenant_id', $tenantId)
                    ->where('status', 'pending')
                    ->count();
                $ticketsCompleted = \App\Domain\Tickets\Models\Ticket::where('tenant_id', $tenantId)
                    ->where('status', 'resolved')
                    ->whereDate('updated_at', '>=', now()->subDays(30))
                    ->count();
                $totalTickets = max($ticketsRaised, 1); // Avoid division by zero
                
                return response()->json([
                    'data' => [
                        'tickets_raised' => $ticketsRaised,
                        'tickets_pending' => $ticketsPending,
                        'tickets_completed' => $ticketsCompleted,
                        'total_tickets' => $totalTickets,
                    ]
                ]);
            });
            
            // Outpass list
            Route::get('/outpasses', function () {
                $user = auth()->user();
                $tenantId = $user->tenant_id;
                
                if (!$tenantId) {
                    \Log::error('Rector outpasses: No tenant_id for user', ['user_id' => $user->id]);
                    return response()->json(['data' => []]);
                }
                
                $status = request()->query('status');
                
                $query = \App\Domain\OutPass\Models\OutPass::where('tenant_id', $tenantId)
                    ->with(['student.user', 'student.roomAllocations.roomBed.room', 'hostel']);
                
                // Apply status filter if provided and not 'all'
                if ($status && $status !== 'all') {
                    if (is_string($status)) {
                        // Map UI "rejected" to both declined and rejected (backend stores "declined" on reject)
                        if (strtolower($status) === 'rejected') {
                            $query->whereIn('status', [
                                \App\Enums\OutPassStatus::DECLINED,
                                \App\Enums\OutPassStatus::REJECTED,
                            ]);
                        } else {
                            try {
                                $statusEnum = \App\Enums\OutPassStatus::from($status);
                                $query->where('status', $statusEnum);
                            } catch (\ValueError $e) {
                                \Log::warning('Rector outpasses: Invalid status filter', ['status' => $status]);
                            }
                        }
                    } else {
                        $query->where('status', $status);
                    }
                }
                
                $outpasses = $query->latest()->take(100)->get()->map(function ($outpass) {
                    // Get room number from active room allocation
                    $roomNumber = null;
                    if ($outpass->student) {
                        $activeAllocation = $outpass->student->roomAllocations
                            ->where('is_active', true)
                            ->first();
                        if ($activeAllocation && $activeAllocation->roomBed && $activeAllocation->roomBed->room) {
                            $room = $activeAllocation->roomBed->room;
                            $roomNumber = $room->number ?? ($room->block_code . '-' . $room->floor_code . $room->room_no) ?? null;
                        }
                    }
                    
                    // Convert status enum to string; normalize declined -> rejected for UI
                    $statusValue = $outpass->status instanceof \App\Enums\OutPassStatus
                        ? $outpass->status->value
                        : (string) $outpass->status;
                    if ($statusValue === \App\Enums\OutPassStatus::DECLINED->value) {
                        $statusValue = 'rejected';
                    }
                    
                    // Convert reason enum to string if needed
                    $reasonValue = $outpass->reason instanceof \App\Enums\OutPassType
                        ? $outpass->reason->value
                        : (string) ($outpass->reason ?? 'normal');
                    
                    return [
                        'id' => (string) $outpass->id,
                        'unique_id' => $outpass->unique_id ?? "OP-{$outpass->id}",
                        'student_name' => $outpass->student->user->name ?? 'Unknown',
                        'hostel' => $outpass->hostel->name ?? null,
                        'room' => $roomNumber,
                        'reason' => $reasonValue,
                        'requested_at' => $outpass->requested_at?->toIso8601String() ?? $outpass->created_at->toIso8601String(),
                        'valid_until' => $outpass->valid_until?->toIso8601String() ?? $outpass->requested_at?->toIso8601String(),
                        'status' => $statusValue,
                        'overnight' => $outpass->overnight ?? false,
                        'actual_out_time' => $outpass->actual_out_time?->toIso8601String(),
                        'actual_in_time' => $outpass->actual_in_time?->toIso8601String(),
                        'created_at' => $outpass->created_at->toIso8601String(),
                        'updated_at' => $outpass->updated_at->toIso8601String(),
                    ];
                });
                
                \Log::info('Rector outpasses result', [
                    'tenant_id' => $tenantId,
                    'status_filter' => $status,
                    'count' => $outpasses->count(),
                ]);
                
                return response()->json(['data' => $outpasses]);
            });
            
            // Approve outpass
            Route::put('/outpasses/{id}/approve', function ($id) {
                $user = auth()->user();
                $outpass = \App\Domain\OutPass\Models\OutPass::where('tenant_id', $user->tenant_id)
                    ->findOrFail($id);
                
                $outpass->update([
                    'status' => \App\Enums\OutPassStatus::APPROVED->value,
                    'decision_by' => $user->id,
                    'decided_at' => now(),
                ]);

                $studentUserId = $outpass->student?->user?->id;
                if ($studentUserId) {
                    dispatch(new \App\Jobs\SendApprovalNotification(
                        approvalType: 'outpass',
                        recordId: (int) $outpass->id,
                        decision: 'approved',
                        note: request()->input('note'),
                        studentId: (int) $studentUserId,
                        rectorId: (int) $user->id,
                        tenantId: (string) $user->tenant_id
                    ));
                }
                
                return response()->json(['success' => true, 'message' => 'Outpass approved successfully']);
            });
            
            // Reject outpass
            Route::put('/outpasses/{id}/reject', function ($id) {
                $user = auth()->user();
                $outpass = \App\Domain\OutPass\Models\OutPass::where('tenant_id', $user->tenant_id)
                    ->findOrFail($id);
                
                $note = request()->input('rejection_reason') ?? request()->input('note');
                $outpass->update([
                    'status' => \App\Enums\OutPassStatus::DECLINED->value,
                    'decision_by' => $user->id,
                    'decided_at' => now(),
                    'note' => $note,
                ]);

                $studentUserId = $outpass->student?->user?->id;
                if ($studentUserId) {
                    dispatch(new \App\Jobs\SendApprovalNotification(
                        approvalType: 'outpass',
                        recordId: (int) $outpass->id,
                        decision: 'rejected',
                        note: $note,
                        studentId: (int) $studentUserId,
                        rectorId: (int) $user->id,
                        tenantId: (string) $user->tenant_id
                    ));
                }
                
                return response()->json(['success' => true, 'message' => 'Outpass rejected']);
            });
            
            // Leave list (combined Leave + SickLeave, matches web panel)
            Route::get('/leaves', [App\Http\Controllers\Api\V1\RectorDashboardController::class, 'leaves']);
            
            // Approve leave
            Route::put('/leaves/{id}/approve', function ($id) {
                $user = auth()->user();
                $leave = \App\Domain\Leaves\Models\Leave::where('tenant_id', $user->tenant_id)
                    ->findOrFail($id);
                
                $leave->update([
                    'status' => 'approved',
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                ]);
                
                return response()->json(['success' => true, 'message' => 'Leave approved successfully']);
            });
            
            // Reject leave
            Route::put('/leaves/{id}/reject', function ($id) {
                $user = auth()->user();
                $leave = \App\Domain\Leaves\Models\Leave::where('tenant_id', $user->tenant_id)
                    ->findOrFail($id);
                
                $leave->update([
                    'status' => 'rejected',
                    'rejected_by' => $user->id,
                    'rejected_at' => now(),
                    'rejection_reason' => request()->input('rejection_reason'),
                ]);
                
                return response()->json(['success' => true, 'message' => 'Leave rejected']);
            });

            // Guest entry list + show + approve + reject (same pattern as leave/outpass)
            Route::get('/guest-entries', function () {
                $user = auth()->user();
                $tenantId = $user->tenant_id;
                if (!$tenantId) {
                    return response()->json(['data' => []]);
                }
                $status = request()->query('status', 'all');
                $query = \App\Domain\GuestEntries\Models\GuestEntry::where('tenant_id', $tenantId)
                    ->with(['student.user', 'student.roomAllocations.roomBed.room']);
                if ($status && $status !== 'all') {
                    $query->where('status', $status);
                }
                $entries = $query->latest()->take(100)->get()->map(function ($g) {
                    $student = $g->student;
                    $roomNumber = null;
                    if ($student) {
                        $active = $student->roomAllocations?->firstWhere('is_active', true);
                        $roomNumber = $active?->roomBed?->room?->display_name ?? null;
                    }
                    $firstGuest = is_array($g->guests) && !empty($g->guests) ? ($g->guests[0] ?? []) : [];
                    return [
                        'id' => $g->id,
                        'unique_id' => $g->unique_id ?? 'GST-' . $g->id,
                        'student_name' => $student?->user?->name ?? 'Unknown',
                        'student_id' => $student?->student_uid ?? null,
                        'room_number' => $roomNumber,
                        'guest_name' => is_array($firstGuest) ? ($firstGuest['name'] ?? '') : '',
                        'guest_relation' => is_array($firstGuest) ? ($firstGuest['relationship'] ?? null) : null,
                        'visit_date' => $g->visit_date?->format('Y-m-d'),
                        'purpose_to_visit' => $g->purpose_to_visit,
                        'status' => (string) ($g->status ?? 'pending'),
                        'created_at' => $g->created_at?->toIso8601String(),
                    ];
                });
                return response()->json(['data' => $entries]);
            });
            Route::get('/guest-entries/{id}', function ($id) {
                $user = auth()->user();
                $g = \App\Domain\GuestEntries\Models\GuestEntry::where('tenant_id', $user->tenant_id)
                    ->with(['student.user', 'student.roomAllocations.roomBed.room', 'hostel'])
                    ->findOrFail($id);
                $student = $g->student;
                $roomNumber = null;
                if ($student) {
                    $active = $student->roomAllocations?->firstWhere('is_active', true);
                    $roomNumber = $active?->roomBed?->room?->display_name ?? null;
                }
                $guests = is_array($g->guests) ? $g->guests : [];
                return response()->json([
                    'id' => $g->id,
                    'unique_id' => $g->unique_id ?? 'GST-' . $g->id,
                    'student_name' => $student?->user?->name ?? 'Unknown',
                    'student_id' => $student?->student_uid ?? null,
                    'room_number' => $roomNumber,
                    'hostel' => $g->hostel?->name ?? null,
                    'guests' => $guests,
                    'guest_name' => isset($guests[0]['name']) ? $guests[0]['name'] : '',
                    'guest_relation' => isset($guests[0]['relationship']) ? $guests[0]['relationship'] : null,
                    'visit_date' => $g->visit_date?->format('Y-m-d'),
                    'check_in_time' => $g->check_in_time,
                    'check_out_time' => $g->check_out_time,
                    'purpose_to_visit' => $g->purpose_to_visit,
                    'description' => $g->description,
                    'status' => (string) ($g->status ?? 'pending'),
                    'rejection_reason' => $g->rejection_reason,
                    'created_at' => $g->created_at?->toIso8601String(),
                    'approved_at' => $g->approved_at?->toIso8601String(),
                ]);
            });
            Route::put('/guest-entries/{id}/approve', function ($id) {
                $user = auth()->user();
                $g = \App\Domain\GuestEntries\Models\GuestEntry::where('tenant_id', $user->tenant_id)->findOrFail($id);
                $g->update([
                    'status' => 'approved',
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                ]);
                return response()->json(['success' => true, 'message' => 'Guest entry approved successfully']);
            });
            Route::put('/guest-entries/{id}/reject', function ($id) {
                $user = auth()->user();
                $g = \App\Domain\GuestEntries\Models\GuestEntry::where('tenant_id', $user->tenant_id)->findOrFail($id);
                $g->update([
                    'status' => 'rejected',
                    'rejection_reason' => request()->input('rejection_reason'),
                ]);
                return response()->json(['success' => true, 'message' => 'Guest entry rejected']);
            });
        });

        // Supervisor routes for mobile (HK & RM Supervisors)
        Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureTenantScope::class])->prefix('supervisor')->group(function () {
            // Dashboard stats (reuse existing controller logic)
            Route::get('/dashboard', [\App\Http\Controllers\Api\V1\Staff\SupervisorController::class, 'dashboard']);

            // Tickets assigned to the supervisor
            Route::get('/tickets', [\App\Http\Controllers\Api\V1\Staff\SupervisorController::class, 'tickets']);
        });


        
        // Supervisor routes (HK, RM, etc.)
        Route::prefix('supervisor')->group(function () {
            Route::get('/dashboard', function () {
                $user = auth()->user();
                $tenantId = $user->tenant_id;
                $type = request()->query('type', 'housekeeping'); // housekeeping, rm, or maintenance
                
                // Map type to category
                $category = $type === 'rm' ? 'maintenance' : ($type === 'housekeeping' ? 'housekeeping' : $type);
                
                // Get supervisor-specific stats (filter by assignee for supervisor's own tickets)
                $pendingRequests = \App\Domain\Tickets\Models\Ticket::where('tenant_id', $tenantId)
                    ->where('category', $category)
                    ->where('status', 'pending')
                    ->where(function($q) use ($user) {
                        $q->where('assignee_user_id', $user->id)
                          ->orWhere('assigned_to', $user->id);
                    })
                    ->count();
                
                $inProgressRequests = \App\Domain\Tickets\Models\Ticket::where('tenant_id', $tenantId)
                    ->where('category', $category)
                    ->where('status', 'in_progress')
                    ->where(function($q) use ($user) {
                        $q->where('assignee_user_id', $user->id)
                          ->orWhere('assigned_to', $user->id);
                    })
                    ->count();
                
                $completedToday = \App\Domain\Tickets\Models\Ticket::where('tenant_id', $tenantId)
                    ->where('category', $category)
                    ->where('status', 'resolved')
                    ->where(function($q) use ($user) {
                        $q->where('assignee_user_id', $user->id)
                          ->orWhere('assigned_to', $user->id);
                    })
                    ->whereDate('updated_at', today())
                    ->count();
                
                $totalAssigned = \App\Domain\Tickets\Models\Ticket::where('tenant_id', $tenantId)
                    ->where('category', $category)
                    ->where(function($q) use ($user) {
                        $q->where('assignee_user_id', $user->id)
                          ->orWhere('assigned_to', $user->id);
                    })
                    ->count();
                
                return response()->json([
                    'data' => [
                        'pending_requests' => $pendingRequests,
                        'in_progress_requests' => $inProgressRequests,
                        'completed_today' => $completedToday,
                        'total_assigned' => $totalAssigned,
                    ]
                ]);
            });
        });
        
        // Campus Manager routes (staff only)
        // Use explicit middleware that checks for Campus Manager role, instead of relying on 'role' alias
        Route::prefix('campus-manager')->middleware(\App\Http\Middleware\EnsureCampusManager::class)->group(function () {
            // Dashboard stats
            Route::get('/dashboard/stats', function () {
                $user = auth()->user();
                $tenantId = $user->tenant_id;
                
                return response()->json([
                    'data' => [
                        'active_hostels' => \App\Models\Hostel::where('tenant_id', $tenantId)->count(),
                        'resident_students' => \App\Models\Student::where('tenant_id', $tenantId)
                            ->whereHas('roomAllocations', function($q) {
                                $q->where('is_active', true);
                            })
                            ->count(),
                        'open_requests' => \App\Domain\Tickets\Models\Ticket::where('tenant_id', $tenantId)->whereIn('status', ['open', 'in_progress'])->count(),
                        'completed_requests_today' => \App\Domain\Tickets\Models\Ticket::where('tenant_id', $tenantId)->where('status', 'resolved')->whereDate('updated_at', today())->count(),
                    ]
                ]);
            });
            
        // Staff list
        Route::get('/staff', [\App\Http\Controllers\Api\V1\CampusManager\StaffController::class, 'index']);
            
            // My Checklist (assigned to current user) – for mobile "My Checklist" tab
            Route::get('/checklists/current', [App\Http\Controllers\Api\V1\CampusManager\MyChecklistController::class, 'current']);
            Route::post('/checklists/submit', [App\Http\Controllers\Api\V1\CampusManager\MyChecklistController::class, 'submit']);
            Route::post('/checklists/items/{taskIndex}/complete', [App\Http\Controllers\Api\V1\CampusManager\MyChecklistController::class, 'completeTask']);
            Route::post('/checklists/items/{taskIndex}/photo', [App\Http\Controllers\Api\V1\CampusManager\MyChecklistController::class, 'uploadPhoto']);
            // Staff Checklist tab – summary of staff checklists for today/yesterday
            Route::get('/checklists/staff-summary', [App\Http\Controllers\Api\V1\CampusManager\StaffController::class, 'staffChecklistSummary']);
            // Detailed checklist for a specific staff member (used by mobile when tapping a staff row)
            Route::get('/checklists/staff/{user}', [App\Http\Controllers\Api\V1\CampusManager\StaffController::class, 'staffChecklistDetail']);
            
            // Requests by type
            Route::get('/requests/{type}', function ($type) {
                $user = auth()->user();
                $tenantId = $user->tenant_id;

                if (!$tenantId) {
                    \Log::warning('Campus manager requests: missing tenant_id', ['user_id' => $user->id]);
                    return response()->json(['data' => []], 200);
                }

                $query = null;

                try {
                    switch ($type) {
                        case 'housekeeping':
                        case 'maintenance':
                            $categories = $type === 'housekeeping'
                                ? ['cleaning', 'housekeeping']
                                : ['maintenance', 'repair_maintenance', 'room_maintenance', 'electrical', 'plumbing', 'furniture'];
                            $query = \App\Domain\Tickets\Models\Ticket::where('tenant_id', $tenantId)
                                ->whereIn('category', $categories)
                                ->orderBy('created_at', 'desc')
                                ->limit(50)
                                ->get()
                                ->map(fn($t) => [
                                    'id' => $t->id,
                                    'student_name' => $t->reporterStudent?->user?->name ?? $t->reporterUser?->name,
                                    'room' => $t->location,
                                    'description' => $t->description,
                                    'status' => $t->status,
                                    'created_at' => $t->created_at?->toISOString(),
                                ]);
                            break;
                    case 'outpass':
                        $query = \App\Domain\OutPass\Models\OutPass::where('tenant_id', $tenantId)
                            ->with(['student.user', 'hostel'])
                            ->orderBy('created_at', 'desc')
                            ->limit(50)
                            ->get()
                            ->map(function($g) {
                                // Convert status enum to string
                                $statusValue = $g->status instanceof \App\Enums\OutPassStatus 
                                    ? $g->status->value 
                                    : (string) $g->status;
                                
                                // Convert reason enum to string if needed
                                $reasonValue = $g->reason instanceof \App\Enums\OutPassType
                                    ? $g->reason->value
                                    : (string) ($g->reason ?? 'normal');
                                
                                return [
                                    'id' => (string) $g->id,
                                    'unique_id' => $g->unique_id ?? "OP-{$g->id}",
                                    'student_name' => $g->student?->user?->name,
                                    'reason' => $reasonValue,
                                    'status' => $statusValue,
                                    'requested_at' => $g->requested_at?->toISOString(),
                                    'valid_until' => $g->valid_until?->toISOString(),
                                    'created_at' => $g->created_at?->toISOString(),
                                ];
                            });
                        break;
                    case 'leave':
                        $query = \App\Domain\Leaves\Models\Leave::where('tenant_id', $tenantId)
                            ->with(['student.user', 'hostel'])
                            ->orderBy('created_at', 'desc')
                            ->limit(50)
                            ->get()
                            ->map(function($l) {
                                $status = $l->status;
                                if ($status instanceof \BackedEnum) {
                                    $status = $status->value;
                                }
                                return [
                                    'id' => (string) $l->id,
                                    'unique_id' => $l->unique_id ?? "LEV-{$l->id}",
                                    'student_name' => $l->student?->user?->name,
                                    'reason_for_leave' => $l->reason_for_leave,
                                    'title' => $l->title,
                                    'status' => (string) $status,
                                    'from_date' => $l->from_date?->format('Y-m-d'),
                                    'to_date' => $l->to_date?->format('Y-m-d'),
                                    'created_at' => $l->created_at?->toISOString(),
                                ];
                            });
                        break;
                    case 'guest-entry':
                        $query = \App\Domain\GuestEntries\Models\GuestEntry::where('tenant_id', $tenantId)
                            ->with(['student.user', 'hostel'])
                            ->orderBy('created_at', 'desc')
                            ->limit(50)
                            ->get()
                            ->map(function($g) {
                                // Extract first guest info from guests array
                                $guests = $g->guests ?? [];
                                $firstGuest = !empty($guests) && is_array($guests) ? $guests[0] : null;
                                
                                $status = $g->status;
                                if ($status instanceof \BackedEnum) {
                                    $status = $status->value;
                                }
                                return [
                                    'id' => (string) $g->id,
                                    'unique_id' => $g->unique_id ?? "GST-{$g->id}",
                                    'student_name' => $g->student?->user?->name,
                                    'guest_name' => $firstGuest['name'] ?? null,
                                    'guest_relation' => $firstGuest['relationship'] ?? null,
                                    'purpose_to_visit' => $g->purpose_to_visit,
                                    'status' => (string) $status,
                                    'visit_date' => $g->visit_date?->format('Y-m-d'),
                                    'created_at' => $g->created_at?->toISOString(),
                                ];
                            });
                        break;
                    case 'sports':
                        if (!class_exists(\App\Models\SportsBooking::class)) {
                            $query = [];
                        } else {
                            $query = \App\Models\SportsBooking::where('tenant_id', $tenantId)
                                ->orderBy('created_at', 'desc')
                                ->limit(50)
                                ->get()
                                ->map(fn($s) => [
                                    'id' => $s->id,
                                    'student_name' => $s->student?->user?->name,
                                    'status' => $s->status,
                                    'created_at' => $s->created_at?->toISOString(),
                                ]);
                        }
                        break;
                    case 'laundry':
                        $query = \App\Models\LaundryRequest::where('tenant_id', $tenantId)
                            ->orderBy('created_at', 'desc')
                            ->limit(50)
                            ->get()
                            ->map(fn($l) => [
                                'id' => $l->id,
                                'student_name' => $l->student?->user?->name,
                                'status' => $l->status,
                                'created_at' => $l->created_at?->toISOString(),
                            ]);
                        break;
                        default:
                            return response()->json(['data' => []]);
                    }

                    return response()->json(['data' => $query ?? []]);
                } catch (\Throwable $e) {
                    \Log::error('Campus manager requests failed', [
                        'type' => $type,
                        'user_id' => $user->id ?? null,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    return response()->json(['data' => []], 200);
                }
            });
            
            // Post notice
            Route::post('/notices', function (\Illuminate\Http\Request $request) {
                \Log::info('Notice creation endpoint called', [
                    'has_user' => auth()->check(),
                    'user_id' => auth()->id(),
                    'all_inputs' => $request->all(),
                    'hostel_ids_input' => $request->input('hostel_ids'),
                ]);
                
                $user = auth()->user();
                if (!$user) {
                    \Log::error('No authenticated user in notice creation');
                    return response()->json(['error' => 'Unauthenticated'], 401);
                }
                
                $tenantId = $user->tenant_id;
                
                if (!$tenantId) {
                    \Log::error('No tenant_id for user', ['user_id' => $user->id]);
                    return response()->json(['error' => 'Tenant not found'], 400);
                }
                
                // Handle hostel_ids - can be JSON string from FormData or array
                $hostelIdsInput = $request->input('hostel_ids');
                \Log::info('Hostel IDs input', [
                    'raw' => $hostelIdsInput,
                    'type' => gettype($hostelIdsInput),
                ]);
                if (is_string($hostelIdsInput)) {
                    $hostelIds = json_decode($hostelIdsInput, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return response()->json(['error' => 'Invalid hostel_ids format'], 400);
                    }
                } else {
                    $hostelIds = $hostelIdsInput;
                }
                
                try {
                    $validated = $request->validate([
                        'title' => 'required|string|max:255',
                        'description' => 'required|string',
                        'type' => 'nullable|string|in:general,urgent,event',
                        'scheduled_at' => 'nullable|date',
                        'image' => 'nullable|image|max:5120', // 5MB max
                    ]);
                } catch (\Illuminate\Validation\ValidationException $e) {
                    \Log::error('Notice creation validation failed', [
                        'errors' => $e->errors(),
                        'input' => $request->all(),
                    ]);
                    return response()->json([
                        'error' => 'Validation failed',
                        'errors' => $e->errors(),
                    ], 422);
                }
                
                // Validate hostel_ids separately
                if (empty($hostelIds) || !is_array($hostelIds)) {
                    return response()->json(['error' => 'At least one hostel must be selected'], 400);
                }
                
                foreach ($hostelIds as $id) {
                    if (!is_numeric($id)) {
                        return response()->json(['error' => 'Invalid hostel ID format'], 400);
                    }
                }
                
                // Verify all hostels belong to the tenant
                $hostels = \App\Models\Hostel::where('tenant_id', $tenantId)
                    ->whereIn('id', $hostelIds)
                    ->get();
                
                if ($hostels->count() !== count($hostelIds)) {
                    return response()->json(['error' => 'One or more hostels not found or do not belong to your tenant'], 400);
                }
                
                // Map type to target_role/audience
                $audience = 'students'; // Default
                $targetRole = 'all';
                
                // Create notices for each hostel
                $notices = [];
                foreach ($hostels as $hostel) {
                    try {
                        $noticeData = [
                            'title' => $validated['title'],
                            'body' => $validated['description'],
                            'tenant_id' => $tenantId,
                            'hostel_id' => $hostel->id,
                            'created_by_user_id' => $user->id,
                            'audience' => 'students',
                            'status' => 'published',
                        ];
                        
                        // Handle scheduling
                        if (!empty($validated['scheduled_at'])) {
                            $scheduledDate = \Carbon\Carbon::parse($validated['scheduled_at']);
                            $noticeData['publish_at'] = $scheduledDate;
                            $noticeData['published_at'] = $scheduledDate;
                            $noticeData['status'] = 'scheduled';
                        } else {
                            $now = now();
                            $noticeData['publish_at'] = $now;
                            $noticeData['published_at'] = $now;
                        }
                        
                        // Handle image upload if provided
                        if ($request->hasFile('image')) {
                            $image = $request->file('image');
                            $path = $image->store('notices', 'public');
                            $noticeData['attachment_url'] = \Storage::url($path);
                        }
                        
                        \Log::info('Creating notice with data', [
                            'notice_data' => $noticeData,
                            'hostel_id' => $hostel->id,
                            'tenant_id' => $tenantId,
                        ]);
                        
                        $notice = \App\Models\Notice::create($noticeData);
                        $notices[] = $notice;
                    } catch (\Exception $e) {
                        \Log::error('Failed to create notice', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'notice_data' => $noticeData ?? null,
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                        ]);
                        return response()->json([
                            'error' => 'Failed to create notice: ' . $e->getMessage(),
                            'details' => config('app.debug') ? $e->getTraceAsString() : null,
                        ], 500);
                    }
                }
                
                return response()->json([
                    'success' => true,
                    'message' => count($notices) . ' notice(s) created successfully',
                    'data' => $notices,
                ], 201);
            });
            
            // Emergency incidents
            Route::get('/emergency/incidents', function (\Illuminate\Http\Request $request) {
                $user = auth()->user();
                $tenantId = $user->tenant_id;
                $page = $request->input('page', 1);
                $perPage = $request->input('per_page', 20);
                
                $incidents = \App\Models\Incident::where('tenant_id', $tenantId)
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);
                
                return response()->json([
                    'data' => $incidents->items(),
                    'meta' => [
                        'current_page' => $incidents->currentPage(),
                        'total' => $incidents->total(),
                        'per_page' => $incidents->perPage(),
                    ]
                ]);
            });
            
            Route::get('/emergency/incidents/unread-count', function () {
                $user = auth()->user();
                $tenantId = $user->tenant_id;
                
                $count = \App\Models\Incident::where('tenant_id', $tenantId)
                    ->whereNull('acknowledged_at')
                    ->count();
                
                return response()->json(['data' => ['unread_count' => $count]]);
            });
            
            Route::post('/emergency/incidents/{incident}/acknowledge', function ($incidentId) {
                $user = auth()->user();
                $incident = \App\Models\Incident::where('tenant_id', $user->tenant_id)->findOrFail($incidentId);
                $incident->update([
                    'acknowledged_at' => now(),
                    'acknowledged_by' => $user->id,
                ]);
                return response()->json(['data' => $incident]);
            });
            
            Route::get('/emergency/medical', function (\Illuminate\Http\Request $request) {
                $user = auth()->user();
                $tenantId = $user->tenant_id;
                $page = $request->input('page', 1);
                $perPage = $request->input('per_page', 20);
                
                // Medical emergencies - using incidents with type 'medical' or a separate table if exists
                $emergencies = \App\Models\Incident::where('tenant_id', $tenantId)
                    ->where('type', 'medical')
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);
                
                return response()->json([
                    'data' => $emergencies->items(),
                    'meta' => [
                        'current_page' => $emergencies->currentPage(),
                        'total' => $emergencies->total(),
                        'per_page' => $emergencies->perPage(),
                    ]
                ]);
            });
        });
        
        // Dashboard route for staff (warden, etc.)
        Route::get('/dashboard', [App\Http\Controllers\Api\V1\DashboardController::class, 'index']);
    });
});

// Minimal staff user routes for Super Admin tests
Route::middleware(['web', 'auth'])->prefix('v1')->group(function () {
    Route::post('/reports', function (\Illuminate\Http\Request $request) {
        $user = auth()->user();
        if (!$user || !$user->hasRole('Super Admin')) {
            abort(403);
        }

        $data = $request->validate([
            'report_name' => 'required|string',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'hostel_id' => 'nullable|exists:hostels,id',
            'params' => 'array',
        ]);

        // Enforce date range limit: max 60 days back
        $from = \Carbon\Carbon::parse($data['from_date']);
        if ($from->lt(now()->subDays(60))) {
            return response()->json([
                'message' => 'The from_date may not be older than 60 days.',
                'errors' => ['from_date' => ['date range too large']],
            ], 422);
        }

        $tenantId = $user->tenant_id;

        $id = \DB::table('reports')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => $data['report_name'],
            'params' => json_encode([
                'from_date' => $data['from_date'],
                'to_date' => $data['to_date'],
                'hostel_id' => $data['hostel_id'] ?? null,
            ]),
            'status' => 'queued',
            'storage_path' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Jobs\GenerateReport::dispatch($id);

        return response()->json(['id' => $id], 201);
    });

    Route::post('/staff-users', function (\Illuminate\Http\Request $request) {
        $data = $request->validate([
            'name' => 'required|string',
            'email' => 'nullable|email',
            'phone' => 'required|string',
            'password' => 'required|string',
            'role_hint' => 'required|string',
            'hostels' => 'array',
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        
        $hostelIds = $data['hostels'] ?? [];
        $tenantId = app()->bound('testing.default_tenant_id') ? app('testing.default_tenant_id') : null;
        if (!empty($hostelIds)) {
            $firstHostel = \App\Models\Hostel::find($hostelIds[0]);
            $tenantId = $firstHostel?->tenant_id ?? $tenantId;
        }
        
        // Create user
        $user = \App\Models\User::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => \Illuminate\Support\Facades\Hash::make($data['password']),
            'kind' => 'staff',
            'is_map_staff' => true,
        ]);
        
        // Ensure role exists and assign it directly via pivot table
        $role = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => $data['role_hint'], 'guard_name' => 'web']
        );
        \DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_type' => \App\Models\User::class,
            'model_id' => $user->id,
        ]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $user->staffHostels()->sync([]);
        foreach ($hostelIds as $hostelId) {
            $user->staffHostels()->attach($hostelId, [
                'tenant_id' => $user->tenant_id ?? $tenantId,
                'assigned_at' => now(),
            ]);
        }

        event(new \App\Events\UserRoleChanged($user->id, auth()->id() ?? 1, now()->toISOString()));
        event(new \App\Events\StaffAssignmentChanged($user->id, auth()->id() ?? 1, now()->toISOString()));

        return response()->json($user->fresh(), 201);
    });

    Route::put('/staff-users/{user}', function (\Illuminate\Http\Request $request, \App\Models\User $user) {
        $data = $request->validate([
            'name' => 'required|string',
            'email' => 'nullable|email',
            'role_hint' => 'required|string',
            'hostels' => 'array',
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $role = \Spatie\Permission\Models\Role::findOrCreate($data['role_hint'], 'web');
        $hostelIds = $data['hostels'] ?? [];
        $tenantId = $user->tenant_id;
        if (!empty($hostelIds)) {
            $firstHostel = \App\Models\Hostel::find($hostelIds[0]);
            $tenantId = $firstHostel?->tenant_id ?? $tenantId;
        }
        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'is_map_staff' => true,
            'tenant_id' => $tenantId,
        ]);
        $user->syncRoles([$role->name]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $user->staffHostels()->sync([]);
        foreach ($hostelIds as $hostelId) {
            $user->staffHostels()->attach($hostelId, [
                'tenant_id' => $user->tenant_id ?? $tenantId,
                'assigned_at' => now(),
            ]);
        }

        // revoke tokens on change
        $user->tokens()->delete();
        event(new \App\Events\UserRoleChanged($user->id, auth()->id() ?? 1, now()->toISOString()));
        event(new \App\Events\StaffAssignmentChanged($user->id, auth()->id() ?? 1, now()->toISOString()));

        return response()->json($user->fresh(), 200);
    });
});

// NOTE: All tenant-specific API routes have moved to routes/tenant.php
// Access them via tenant subdomains: https://{tenant}.yourapp.com/v1/...
