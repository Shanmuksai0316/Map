<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Tenant Impersonation Controller
 * 
 * Allows Super Admin to impersonate tenant admin users for support purposes.
 * All impersonation sessions are logged for security audit.
 */
class TenantImpersonationController extends Controller
{
    /**
     * Start impersonating a tenant's admin user
     */
    public function start(Request $request, Tenant $tenant)
    {
        // Verify Super Admin
        if (!auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Only Super Admins can impersonate tenants.');
        }

        // Prevent nested impersonation
        if (session('impersonating_from')) {
            return back()->with('error', 'You are already impersonating another user. Stop current impersonation first.');
        }
        
        // Find tenant's primary admin (Rector or Campus Manager)
        $tenantAdmin = User::where('tenant_id', $tenant->id)
            ->whereHas('roles', fn($q) => $q->whereIn('name', ['Rector', 'Campus Manager']))
            ->first();
        
        if (!$tenantAdmin) {
            return back()->with('error', 'No admin user found for this tenant. Ensure Rector or Campus Manager exists.');
        }
        
        // Log impersonation start
        DB::table('tenant_impersonation_logs')->insert([
            'super_admin_id' => (string) auth()->id(),
            'tenant_id' => $tenant->id,
            'impersonated_user_id' => (string) $tenantAdmin->id,
            'started_at' => now(),
            'ip_address' => $request->ip(),
            'reason' => $request->input('reason', 'Support/Debugging'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Store original user in session
        session([
            'impersonating_from' => auth()->id(),
            'impersonation_started_at' => now()->toIso8601String(),
            'impersonated_tenant_name' => $tenant->name,
        ]);

        // Set default tenant parameter for campus manager panel routes
        URL::defaults(['tenant' => $tenant->id]);
        
        // Login as tenant admin
        auth()->setUser($tenantAdmin);
        
        return redirect("/campus-manager/{$tenant->id}")
            ->with('warning', 'IMPERSONATION MODE: You are viewing as ' . $tenantAdmin->name . '. Click "Stop Impersonation" to return to Super Admin.');
    }
    
    /**
     * Stop impersonating and return to Super Admin
     */
    public function stop()
    {
        $originalUserId = session('impersonating_from');
        
        if (!$originalUserId) {
            // Not impersonating, redirect to admin dashboard
            return redirect()->route('filament.admin.pages.dashboard');
        }
        
        // Update impersonation log
        DB::table('tenant_impersonation_logs')
            ->where('super_admin_id', (string) $originalUserId)
            ->whereNull('ended_at')
            ->orderBy('started_at', 'desc')
            ->limit(1)
            ->update(['ended_at' => now()]);
        
        // Restore original Super Admin user
        $originalUser = User::find($originalUserId);
        
        if (!$originalUser) {
            // Original user not found, logout and redirect
            auth()->logout();
            return redirect()->route('filament.admin.auth.login')
                ->with('error', 'Original user not found. Please login again.');
        }
        
        // Clear impersonation session
        session()->forget(['impersonating_from', 'impersonation_started_at', 'impersonated_tenant_name']);
        
        // Login as original user
        auth()->setUser($originalUser);
        
        return redirect()
            ->route('filament.admin.pages.dashboard')
            ->with('success', 'Impersonation ended successfully. Welcome back!');
    }
}
