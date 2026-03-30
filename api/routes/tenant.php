<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| These routes are loaded for each tenant's subdomain (e.g., jnu.yourapp.com)
| Middleware automatically initializes tenant context and sets PostgreSQL session
| variable for Row Level Security (RLS) policies.
|
| All tenant data is in single shared database with tenant_id scoping.
|
*/

// API Routes for Tenants (Subdomain access)
Route::middleware([
    'api',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    \App\Http\Middleware\SetPostgresSessionTenant::class, // Set PostgreSQL session variable for RLS
    \App\Http\Middleware\EnsureTenantScope::class, // Enforce tenant scope validation
    'tenant.validate',
])->prefix('api/v1')->group(function () {
    
    // Staff checklist summary – tenant-domain route
    Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureCampusManager::class])
        ->get('campus-manager/checklists/staff-summary', [App\Http\Controllers\Api\V1\CampusManager\StaffController::class, 'staffChecklistSummary']);

    // Health check
    Route::get('/healthz', function () {
        return response()->json([
            'status' => 'ok',
            'tenant' => tenant('code'),
            'timestamp' => now()->toISOString(),
        ]);
    });
    
    // Demo session endpoints (for client demonstrations)
    Route::prefix('demo')->group(function () {
        Route::get('/nonce', [App\Http\Controllers\Api\V1\DemoSessionController::class, 'nonce']);
        Route::post('/session', [App\Http\Controllers\Api\V1\DemoSessionController::class, 'issue'])
            ->middleware('throttle:20,1'); // Rate limit: 20 requests per minute
    });
    
    // All tenant-specific API routes
    require __DIR__.'/api/attendance.php';
    require __DIR__.'/api/auth.php';
    require __DIR__.'/api/gate.php';
    require __DIR__.'/api/laundry.php';
    require __DIR__.'/api/sports.php';
    require __DIR__.'/api/tickets.php';
    require __DIR__.'/api/outpass.php';
    require __DIR__.'/api/room_changes.php';
    require __DIR__.'/api/auto_allocation.php';
    require __DIR__.'/api/checkouts.php';
    require __DIR__.'/api/checklists.php';

    // Dashboard - role-specific metrics (requires authentication)
    Route::middleware('auth:sanctum')->get('/dashboard', [App\Http\Controllers\Api\V1\DashboardController::class, 'index']);
    require __DIR__.'/api/notices.php';
    require __DIR__.'/modules/attendance_v2.php';
    require __DIR__.'/api/rector.php';

    // Mobile staff app: rector guest-entries (same pattern as leave/outpass: list, show, approve, reject)
    Route::prefix('mobile')->middleware(['auth:sanctum', \App\Http\Middleware\EnsureRector::class])->group(function () {
        Route::get('/rector/guest-entries', function () {
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
        Route::get('/rector/guest-entries/{id}', function ($id) {
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
        Route::put('/rector/guest-entries/{id}/approve', function ($id) {
            $user = auth()->user();
            $g = \App\Domain\GuestEntries\Models\GuestEntry::where('tenant_id', $user->tenant_id)->findOrFail($id);
            $g->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);
            return response()->json(['success' => true, 'message' => 'Guest entry approved successfully']);
        });
        Route::put('/rector/guest-entries/{id}/reject', function ($id) {
            $user = auth()->user();
            $g = \App\Domain\GuestEntries\Models\GuestEntry::where('tenant_id', $user->tenant_id)->findOrFail($id);
            $g->update([
                'status' => 'rejected',
                'rejection_reason' => request()->input('rejection_reason'),
            ]);
            return response()->json(['success' => true, 'message' => 'Guest entry rejected']);
        });
    });

    // Audit & PII Reveal
    Route::prefix('audit')->middleware(['auth:sanctum'])->group(function () {
        Route::post('/pii/reveal', [App\Http\Controllers\Api\V1\AuditController::class, 'revealPii'])
            ->name('api.audit.pii.reveal');
        Route::get('/pii/logs', [App\Http\Controllers\Api\V1\AuditController::class, 'getPiiLogs'])
            ->name('api.audit.pii.logs');
    });
    
    // Payment status routes (read-only, manual payments only)
    Route::get('/students/{student}/payment-status', [App\Http\Controllers\Api\V1\PaymentController::class, 'getStudentPaymentStatus']);
    Route::get('/payments/summary', [App\Http\Controllers\Api\V1\PaymentController::class, 'getPaymentSummary']);
    
    // Student-facing API routes
    require __DIR__.'/api/student.php';
    
    // Staff-facing API routes
    require __DIR__.'/api/warden.php';
    require __DIR__.'/api/supervisor.php';
    
    // Security and Visitor Management
    Route::prefix('security')->group(function () {
        Route::apiResource('incidents', App\Http\Controllers\Api\V1\Staff\SecurityIncidentController::class);
        Route::post('incidents/{incident}/assign', [App\Http\Controllers\Api\V1\Staff\SecurityIncidentController::class, 'assign']);
        Route::post('incidents/{incident}/close', [App\Http\Controllers\Api\V1\Staff\SecurityIncidentController::class, 'close']);
        Route::get('incidents/dashboard/stats', [App\Http\Controllers\Api\V1\Staff\SecurityIncidentController::class, 'dashboard']);
    });
    
    Route::prefix('visitors')->group(function () {
        Route::apiResource('visitors', App\Http\Controllers\Api\V1\Staff\VisitorController::class);
        Route::post('visitors/{visitor}/allow', [App\Http\Controllers\Api\V1\Staff\VisitorController::class, 'allow']);
        Route::post('visitors/{visitor}/deny', [App\Http\Controllers\Api\V1\Staff\VisitorController::class, 'deny']);
        Route::post('visitors/{visitor}/exit', [App\Http\Controllers\Api\V1\Staff\VisitorController::class, 'exit']);
        Route::get('visitors/today/list', [App\Http\Controllers\Api\V1\Staff\VisitorController::class, 'today']);
        Route::get('visitors/statistics', [App\Http\Controllers\Api\V1\Staff\VisitorController::class, 'statistics']);
    });
    
    // Ticket Management
        Route::apiResource('tickets', App\Http\Controllers\Api\V1\Staff\TicketController::class);
        Route::post('tickets/{ticket}/assign', [App\Http\Controllers\Api\V1\Staff\TicketController::class, 'assign']);
        Route::post('tickets/{ticket}/comments', [App\Http\Controllers\Api\V1\Staff\TicketController::class, 'addComment']);
    Route::get('tickets/{ticket}/comments', [App\Http\Controllers\Api\V1\Staff\TicketController::class, 'comments']);
        Route::get('tickets/dashboard/stats', [App\Http\Controllers\Api\V1\Staff\TicketController::class, 'dashboard']);
    
    // Offline queue sync (Guard + Warden)
    require __DIR__.'/api/offline.php';
    
    // Admin routes are available per tenant
    // All tenant admins are in single shared database with tenant_id scoping
    require __DIR__.'/api/admin.php';
    
    // Staff-specific routes
    require __DIR__.'/api/campus-manager.php';
    // Note: Do NOT re-register campus-manager routes under /mobile here.
    // The canonical mobile campus-manager API lives in routes/api.php and must
    // stay usable from central API domains (api.mapservices.in).
    require __DIR__.'/api/guard.php';
    require __DIR__.'/api/notifications.php';
});

// Web Routes for Tenants (Campus Manager Panel via subdomain)
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    \App\Http\Middleware\SetPostgresSessionTenant::class, // Set PostgreSQL session variable for RLS
])->group(function () {
    Route::get('/', function () {
        $tenant = tenant();
        return view('welcome', [
            'tenant_name' => $tenant->name,
            'tenant_code' => $tenant->code,
        ]);
    });
});
