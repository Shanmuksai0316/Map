<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    /**
     * Get list of available tenants for mobile app
     */
    public function index(): JsonResponse
    {
        $tenants = Tenant::with('domains')
            ->whereIn('status', ['active', 'provisioning'])
            ->get()
            ->map(function ($tenant) {
                $domain = $tenant->domains->first();
                
                // Generate production-ready apiUrl based on environment
                $apiUrl = null;
                if ($domain) {
                    $isProduction = app()->environment('production');
                    $protocol = $isProduction ? 'https' : 'http';
                    
                    // In production, use subdomain: https://{domain}.mapservices.in/api/v1
                    // In development, use: http://{domain}.localhost:8000/api/v1 or http://localhost:8000/api/v1
                    if ($isProduction) {
                        $apiUrl = "{$protocol}://{$domain->domain}.mapservices.in/api/v1";
                    } else {
                        // For development, use localhost with domain subdomain
                        $apiUrl = "{$protocol}://{$domain->domain}.localhost:8000/api/v1";
                    }
                }
                
                return [
                    'id' => $tenant->id,
                    'code' => $tenant->code,
                    'name' => $tenant->name,
                    'domain' => $domain ? $domain->domain : null,
                    'apiUrl' => $apiUrl,
                ];
            });

        // Return tenants at top level to match mobile expectations
        return response()->json([
            'tenants' => $tenants,
        ]);
    }
}
