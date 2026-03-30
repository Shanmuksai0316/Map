<?php

use Illuminate\Support\Facades\Route;
use Laravel\Horizon\Horizon;
use App\Http\Controllers\TestController;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

// Serve branding logos when static file 404s (e.g. file in tenant storage, symlink points to central)
Route::get('storage/branding/logos/{filename}', [\App\Http\Controllers\BrandingLogoController::class, '__invoke'])
    ->where('filename', '[a-zA-Z0-9_.-]+')
    ->middleware('web');

// Root route - handle admin domain redirect here
// Registered first to take precedence over Filament routes
Route::get('/', function () {
    try {
        $host = request()->getHost();
        
        // Check if admin domain
        if (str_contains(strtolower($host), 'admin.mapservices.in') || 
            str_contains(strtolower($host), 'admin.localhost')) {
            // Redirect admin domain to login
            return redirect('/admin/login', 302);
        }
        
        // For non-admin domains, show welcome page
        return view('welcome');
    } catch (\Exception $e) {
        // If anything fails, try redirect anyway for admin domain
        if (str_contains(strtolower(request()->getHost() ?? ''), 'admin')) {
            return redirect('/admin/login', 302);
        }
        return response('Error', 500);
    }
})->middleware('web');

// Removed tenant-based redirects - Filament handles panel routing internally

Route::get('/test', [TestController::class, 'index']);

// Demo landing page
Route::get('/demo', function () {
    return view('demo');
});

// Temporary route to assign roles to PPCU staff - MUST be before Filament routes
Route::middleware(['web', 'auth'])->get('/assign-ppcu-roles', function () {
    if (!auth()->user() || !auth()->user()->hasRole('Super Admin')) {
        abort(403, 'Only Super Admin can access this action.');
    }

    $tenant = \App\Models\Tenant::where('code', 'MAP-PPCU')->first();
    if (!$tenant) {
        return response('<pre>Tenant MAP-PPCU not found.</pre>', 404);
    }

    $output = [];
    $output[] = "========================================";
    $output[] = "Assigning Roles to PPCU Staff";
    $output[] = "========================================";
    $output[] = "";
    $output[] = "Tenant: {$tenant->name} ({$tenant->code})";
    $output[] = "Tenant ID: {$tenant->id}";
    $output[] = "";

    $staff = \App\Models\User::where('tenant_id', $tenant->id)
        ->where('kind', '!=', 'student')
        ->get();

    if ($staff->isEmpty()) {
        $output[] = "No staff found for this tenant.";
        return response('<pre style="font-family: monospace; padding: 20px;">' . implode("\n", $output) . '</pre>');
    }

    $output[] = "Found {$staff->count()} staff members:";
    $output[] = "";

    $hostel = \App\Models\Hostel::where('tenant_id', $tenant->id)->first();
    if ($hostel) {
        $output[] = "Hostel: {$hostel->name} (ID: {$hostel->id})";
        $output[] = "";
    }

    $assignedCount = 0;
    $skippedCount = 0;

    \Illuminate\Support\Facades\DB::beginTransaction();

    try {
        foreach ($staff as $user) {
            $currentRoles = $user->roles->pluck('name')->toArray();
            
            $output[] = "Processing: {$user->name} ({$user->phone})";
            $output[] = "  Current roles: " . (empty($currentRoles) ? "NONE" : implode(', ', $currentRoles));
            
            if (!empty($currentRoles)) {
                $output[] = "  → Skipped (already has roles)";
                $output[] = "";
                $skippedCount++;
                continue;
            }
            
            $roleToAssign = 'Staff';
            $nameLower = strtolower($user->name);
            
            if (str_contains($nameLower, 'campus manager') || str_contains($nameLower, 'cm')) {
                $roleToAssign = 'Campus Manager';
            } elseif (str_contains($nameLower, 'warden')) {
                $roleToAssign = 'Warden';
            } elseif (str_contains($nameLower, 'guard')) {
                $roleToAssign = 'Guard';
            } elseif (str_contains($nameLower, 'rector')) {
                $roleToAssign = 'Rector';
            } elseif (str_contains($nameLower, 'hk') || str_contains($nameLower, 'housekeeping')) {
                $roleToAssign = 'HK Supervisor';
            } elseif (str_contains($nameLower, 'rm') || str_contains($nameLower, 'repair') || str_contains($nameLower, 'maintenance')) {
                $roleToAssign = 'RM Supervisor';
            } elseif (str_contains($nameLower, 'sports')) {
                $roleToAssign = 'Sports Manager';
            } elseif (str_contains($nameLower, 'laundry')) {
                $roleToAssign = 'Laundry Manager';
            } elseif (str_contains($nameLower, 'tulip')) {
                $nameParts = explode(' ', $nameLower);
                if (count($nameParts) > 1 && $nameParts[0] === 'tulip') {
                    $roleCandidate = \Illuminate\Support\Str::title(implode(' ', array_slice($nameParts, 1)));
                    if (in_array($roleCandidate, \App\Models\User::mapStaffRoles())) {
                        $roleToAssign = $roleCandidate;
                    } elseif (str_contains($roleCandidate, 'Sports')) {
                        $roleToAssign = 'Sports Manager';
                    } elseif (str_contains($roleCandidate, 'Laundry')) {
                        $roleToAssign = 'Laundry Manager';
                    } elseif (str_contains($roleCandidate, 'HK') || str_contains($roleCandidate, 'Housekeeping')) {
                        $roleToAssign = 'HK Supervisor';
                    } elseif (str_contains($roleCandidate, 'RM') || str_contains($roleCandidate, 'Repair') || str_contains($roleCandidate, 'Maintenance')) {
                        $roleToAssign = 'RM Supervisor';
                    }
                }
            }
            
            $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => $roleToAssign, 'guard_name' => 'web']);
            $user->syncRoles([$role->name]);
            
            $output[] = "  ✓ Assigned role: {$roleToAssign}";
            $assignedCount++;
            
            if ($hostel) {
                $exists = \Illuminate\Support\Facades\DB::table('staff_assignments')
                    ->where('tenant_id', $tenant->id)
                    ->where('user_id', $user->id)
                    ->where('hostel_id', $hostel->id)
                    ->whereNull('revoked_at')
                    ->exists();
                
                if (!$exists) {
                    \Illuminate\Support\Facades\DB::table('staff_assignments')->insert([
                        'tenant_id' => $tenant->id,
                        'user_id' => $user->id,
                        'hostel_id' => $hostel->id,
                        'assigned_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $output[] = "  ✓ Created staff assignment";
                }
            }
            
            $output[] = "";
        }
        
        \Illuminate\Support\Facades\DB::commit();
        
        $output[] = "========================================";
        $output[] = "✅ Role Assignment Complete!";
        $output[] = "========================================";
        $output[] = "   - Assigned roles to: {$assignedCount} staff";
        $output[] = "   - Skipped (already had roles): {$skippedCount} staff";
        
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\DB::rollBack();
        $output[] = "";
        $output[] = "❌ Error: {$e->getMessage()}";
        $output[] = "Rolled back all changes.";
    }

    return response('<pre style="font-family: monospace; padding: 20px; background: #f5f5f5; border-radius: 5px; white-space: pre-wrap;">' . htmlspecialchars(implode("\n", $output)) . '</pre>');
})->name('assign.ppcu.roles');

// Admin actions routes (temporary utility routes)
if (file_exists(__DIR__.'/admin-actions.php')) {
    require __DIR__.'/admin-actions.php';
}

// Local-only SSO shim for panel tests
if (app()->environment('local')) {
    Route::get('/_dev/sso/{panel}/{tenant}', function (string $panel, string $tenantKey) {
        $map = [
            'campus-manager' => 'cm1@stxaviers.edu',
            'rector' => 'rector@stxaviers.edu',
            'college-mgmt' => 'admin1@stxaviers.edu',
        ];
        $email = $map[$panel] ?? null;
        if (!$email) abort(404);

        $tenantModel = null;
        if (class_exists('Stancl\\Tenancy\\Database\\Models\\Tenant')) {
            $T = \Stancl\Tenancy\Database\Models\Tenant::query();
            $tenantModel = $T->find($tenantKey);
            if (!$tenantModel) {
                // try by data->code or data->slug
                $tenantModel = $T->where('data->code', $tenantKey)->orWhere('data->slug', $tenantKey)->first();
            }
            if (!$tenantModel) {
                // try by domain name without port
                $tenantModel = $T->whereHas('domains', function ($q) use ($tenantKey) {
                    $q->where('domain', 'like', "%$tenantKey%");
                })->first();
            }
        }
        if (!$tenantModel) abort(404, 'Tenant not found');

        // Initialize tenancy for the given tenant
        if (class_exists('Stancl\\Tenancy\\Facades\\Tenancy')) {
            \Stancl\Tenancy\Facades\Tenancy::initialize($tenantModel);
        }

        $user = \App\Models\User::where('email', $email)->first();
        if (!$user) abort(404);

        // Persist tenant for subsequent requests via dev middleware
        session(['dev_tenant_id' => $tenantModel->id]);

        // Login via the panel's Filament guard/context
        $panelInstance = \Filament\Facades\Filament::getPanel($panel);
        if (!$panelInstance) {
            abort(404, 'Panel not found');
        }
        $guard = $panelInstance->getAuthGuard() ?? 'web';
        auth($guard)->login($user);
        \Filament\Facades\Filament::setCurrentPanel($panelInstance);
        session()->migrate(true);

        // Redirect to panel root on current domain while tenancy is active
        return redirect("/{$panel}");
    })->name('dev.sso');

    Route::get('/_dev/logs/tail', function () {
        $path = storage_path('logs/laravel.log');
        if (!file_exists($path)) {
            return response('no log file', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        }
        $lines = (int) request('lines', 300);
        $content = file_get_contents($path);
        $tail = substr($content, -20000);
        $parts = explode("\n", $tail);
        $tailLines = array_slice($parts, -$lines);
        return response(implode("\n", $tailLines), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    })->name('dev.logs.tail');

    Route::get('/_dev/tenant/resolve/{key}', function (string $key) {
        $T = \App\Models\Tenant::query();
        $tenant = $T->where('id', $key)
            ->orWhere('code', $key)
            ->orWhere('data->code', $key)
            ->orWhere('data->slug', $key)
            ->orWhere('subdomain', $key)
            ->first();
        if (!$tenant) return response()->json(['error' => 'not_found'], 404);
        return response()->json([
            'id' => $tenant->id,
            'code' => $tenant->code,
            'name' => $tenant->name,
            'subdomain' => $tenant->subdomain ?? null,
        ]);
    })->name('dev.tenant.resolve');

    Route::get('/_dev/tenant/list', function () {
        $tenants = \App\Models\Tenant::query()
            ->select(['id','code','name'])
            ->orderBy('created_at', 'asc')
            ->limit(20)
            ->get();
        return response()->json($tenants);
    })->name('dev.tenant.list');
}

// Automation SSO endpoint - ONLY available in local/testing environments
if (app()->environment(['local', 'testing'])) {
    Route::match(['post', 'options'], '/automation/sso', function (\Illuminate\Http\Request $request) {
        // Handle CORS preflight
        if ($request->isMethod('options')) {
            if (app()->environment('local')) {
                return response('', 200)
                    ->header('Access-Control-Allow-Origin', 'null')
                    ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, X-Automation-Secret, X-Tenant-Code')
                    ->header('Access-Control-Allow-Credentials', 'true');
            }
            return response('', 200);
        }

        // Add CORS headers for local testing
        if (app()->environment('local')) {
            header('Access-Control-Allow-Origin: null');
            header('Access-Control-Allow-Methods: POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, X-Automation-Secret, X-Tenant-Code');
            header('Access-Control-Allow-Credentials: true');
        }
    $secret = config('otp.automation_secret');

    if (! $secret || ! \hash_equals($secret, (string) $request->header('X-Automation-Secret'))) {
        return response()->json([
            'error' => 'forbidden',
            'message' => 'Invalid automation secret.',
        ], 403);
    }

    $panelId = $request->input('panel', 'campus-manager');
    $tenantKey = $request->input('tenant');
    $identifier = $request->input('identifier'); // email or phone


    if (! $tenantKey || ! $identifier) {
        return response()->json([
            'error' => 'invalid_request',
            'message' => 'Tenant and identifier are required.',
        ], 422);
    }

    $tenant = \App\Models\Tenant::query()
        ->where('id', $tenantKey)
        ->orWhere('code', $tenantKey)
        ->orWhere('data->code', $tenantKey)
        ->first();

    if (! $tenant) {
        return response()->json([
            'error' => 'tenant_not_found',
            'message' => 'Tenant not found.',
        ], 404);
    }

    $panel = \Filament\Facades\Filament::getPanel($panelId);
    if (! $panel) {
        return response()->json([
            'error' => 'panel_not_found',
            'message' => 'Panel not found.',
        ], 404);
    }

    if (class_exists('Stancl\\Tenancy\\Facades\\Tenancy')) {
        \Stancl\Tenancy\Facades\Tenancy::initialize($tenant);
    }

    $request->session()->put('dev_tenant_id', $tenant->id);

    $user = \App\Models\User::query()
        ->where('tenant_id', $tenant->id)
        ->where(function ($query) use ($identifier) {
            $query->where('email', $identifier)
                ->orWhere('phone', $identifier);
        })
        ->first();

    if (! $user) {
        return response()->json([
            'error' => 'user_not_found',
            'message' => 'User not found for tenant.',
        ], 404);
    }

    $guard = $panel->getAuthGuard() ?? 'web';
    auth($guard)->login($user);

    try {
        \Filament\Facades\Filament::setCurrentPanel($panel);
        
        // Skip PostgreSQL tenant variable for local testing
        if (!app()->environment('local')) {
            \App\Http\Middleware\SetPostgresSessionTenant::setTenantSessionVariable($tenant->id);
        }
        
        // Regenerate session to ensure it's properly saved
        $request->session()->regenerate();
        
    } catch (\Throwable $e) {
        \Log::error('SSO_PANEL_ERROR', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
        throw $e;
    }

    // Always return JSON for automation
    return response()->json([
        'success' => true,
        'panel' => $panelId,
        'redirect_url' => "/{$panelId}",
        'tenant' => [
            'id' => $tenant->id,
            'code' => $tenant->code,
            'name' => $tenant->name,
        ],
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
        ],
    ]);
    })->middleware(['web'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
        ->name('automation.sso');
}

// Health check endpoint
Route::get('/healthz', function () {
    $checks = [];
    
    // Database check
    try {
        \DB::connection()->getPdo();
        \DB::select('SELECT 1');
        $checks['db'] = 'ok';
    } catch (\Exception $e) {
        $checks['db'] = 'failed';
    }
    
    // Cache check
    try {
        \Cache::put('healthz', 'ok', 5);
        $checks['cache'] = \Cache::get('healthz') === 'ok' ? 'ok' : 'failed';
    } catch (\Exception $e) {
        $checks['cache'] = 'failed';
    }
    
    // Queue check
    try {
        $queueConnection = config('queue.default');
        if ($queueConnection === 'sync') {
            $checks['queue'] = 'sync';
        } else {
            // Test queue connection
            \Queue::connection($queueConnection)->size('default');
            $checks['queue'] = 'ok';
        }
    } catch (\Exception $e) {
        $checks['queue'] = 'failed';
    }
    
    $healthy = $checks['db'] === 'ok' && $checks['cache'] === 'ok' && $checks['queue'] !== 'failed';
    
    // Get version information
    $gitSha = env('GIT_SHA');
    if (!$gitSha) {
        try {
            $gitSha = trim(exec('git --git-dir ' . base_path('.git') . ' log --pretty="%h" -n1 HEAD'));
        } catch (\Exception $e) {
            $gitSha = 'unknown';
        }
    }
    
    $version = [
        'app' => config('app.version', 'v1.0'),
        'git' => $gitSha,
    ];
    
    return response()->json([
        'ok' => $healthy,
        'checks' => $checks,
        'version' => $version,
        'time' => now()->toIso8601String(),
    ], $healthy ? 200 : 503);
})->name('healthz');

// Tenant Impersonation Routes (Super Admin only)
Route::middleware(['auth', 'web'])->prefix('admin')->group(function () {
    Route::get('/super-admin-dashboard', function () {
        if (!config('features.super_admin_staff_mgmt', true)) {
            abort(403);
        }

        $user = auth()->user();
        if (!$user || !$user->hasRole('Super Admin')) {
            abort(403);
        }

        return response()->json(['status' => 'ok']);
    })->name('admin.super-admin-dashboard');

    Route::get('/impersonate/{tenant}', [\App\Http\Controllers\Admin\TenantImpersonationController::class, 'start'])
        ->name('admin.impersonate');
    
    Route::get('/stop-impersonation', [\App\Http\Controllers\Admin\TenantImpersonationController::class, 'stop'])
        ->name('admin.stop-impersonation');

    Route::get('/reports', function () {
        if (!auth()->user()?->hasRole('Super Admin')) {
            abort(403);
        }
        return response()->json(['status' => 'ok']);
    })->name('admin.reports');

    Route::get('/reports/download/{report}', function ($reportId) {
        if (!auth()->user()?->hasRole('Super Admin')) {
            abort(403);
        }

        $report = \App\Models\Report::find($reportId);
        if (!$report) {
            abort(404);
        }

        $csv = "id,name\n{$reportId},Test Report\n";
        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'test.csv', [
            'Content-Type' => 'text/csv',
        ]);
    })->name('admin.reports.download');

    Route::post('/reports', function (\Illuminate\Http\Request $request) {
        if (!auth()->user()?->hasRole('Super Admin')) {
            abort(403);
        }

        $data = $request->validate([
            'name' => 'required|string',
            'params' => 'required|array',
            'params.from' => 'required|date',
            'params.to' => 'required|date|after_or_equal:params.from',
        ]);

        $from = \Carbon\Carbon::parse($data['params']['from']);
        if ($from->lt(now()->subDays(60))) {
            return response()->json([
                'message' => 'The from date may not be older than 60 days.',
                'errors' => ['from' => ['date range too large']],
            ], 422);
        }

        $report = \App\Models\Report::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $data['name'],
            'params' => $data['params'],
            'status' => 'queued',
        ]);

        \App\Jobs\GenerateReport::dispatch($report->id);

        return redirect()->back(302);
    })->name('admin.reports.store');
});

// Protect Horizon with Super Admin role
Horizon::auth(function ($request) {
    // Allow access in testing environment
    if (app()->environment('testing')) {
        return true;
    }
    
    // Require authentication and Super Admin role
    return auth()->check() && auth()->user()->hasRole('Super Admin');
});
