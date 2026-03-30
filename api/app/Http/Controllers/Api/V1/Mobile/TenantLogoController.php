<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a tenant's logo from tenant storage (or central).
 * Called with a signed URL so the mobile Image component can load it without sending auth.
 * GET /api/v1/mobile/tenant-logo/{tenant_id}?expires=...&signature=...
 */
class TenantLogoController extends Controller
{
    public function __invoke(Request $request, string $tenantId)
    {
        if (!$request->hasValidSignature()) {
            return response()->json(['error' => 'Invalid signature'], Response::HTTP_FORBIDDEN);
        }

        $tenant = Tenant::find($tenantId);
        if (!$tenant || !$tenant->settings) {
            return response()->json(['error' => 'Tenant not found'], Response::HTTP_NOT_FOUND);
        }

        // Optional: restrict to same tenant as user when authenticated (extra safety)
        $user = $request->user();
        if ($user && $user->tenant_id && $user->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $logoPath = data_get($tenant->settings, 'branding.logo_path');
        if (!$logoPath) {
            return response()->json(['error' => 'No logo'], Response::HTTP_NOT_FOUND);
        }

        $pathString = is_array($logoPath)
            ? ($logoPath[0] ?? $logoPath['path'] ?? null)
            : $logoPath;
        if (!is_string($pathString) || $pathString === '') {
            return response()->json(['error' => 'Invalid logo path'], Response::HTTP_NOT_FOUND);
        }

        $storagePath = preg_replace('#^storage/#', '', ltrim($pathString, '/'));

        $currentTenant = tenancy()->tenant;
        $didInitialize = !$currentTenant || $currentTenant->id !== $tenant->id;
        if ($didInitialize) {
            tenancy()->initialize($tenant);
        }

        try {
            if (!Storage::disk('public')->exists($storagePath)) {
                return response()->json(['error' => 'Logo file not found'], Response::HTTP_NOT_FOUND);
            }
            $contents = Storage::disk('public')->get($storagePath);
            $mime = Storage::disk('public')->mimeType($storagePath) ?: 'image/png';
            return response($contents, 200, [
                'Content-Type' => $mime,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        } finally {
            if ($didInitialize && tenancy()->tenant) {
                tenancy()->end();
            }
        }
    }
}
