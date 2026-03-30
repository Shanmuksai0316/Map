<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves tenant branding logos when the static file at /storage/branding/logos/{filename} is missing
 * (e.g. file in tenant storage but symlink points to central). Tries central first, then tenant storage.
 */
class BrandingLogoController extends Controller
{
    public function __invoke(Request $request, string $filename): mixed
    {
        $path = 'branding/logos/' . $filename;

        // 1. Try central (public_central) storage
        if (Storage::disk('public_central')->exists($path)) {
            return $this->serveFile(Storage::disk('public_central'), $path, $filename);
        }

        // 2. Find a tenant that has this logo path and serve from tenant storage
        $tenants = Tenant::all();
        foreach ($tenants as $tenant) {
            $logoPath = data_get($tenant->settings, 'branding.logo_path');
            if (! $logoPath) {
                continue;
            }
            $pathString = is_array($logoPath) ? ($logoPath[0] ?? $logoPath['path'] ?? null) : $logoPath;
            if (! is_string($pathString) || $pathString === '') {
                continue;
            }
            $normalized = preg_replace('#^storage/#', '', ltrim($pathString, '/'));
            if ($normalized !== $path && ! str_ends_with($normalized, '/' . $filename)) {
                continue;
            }
            $pathToTry = $normalized;

            tenancy()->initialize($tenant);
            try {
                if (Storage::disk('public')->exists($pathToTry)) {
                    return $this->serveFile(Storage::disk('public'), $pathToTry, $filename);
                }
            } finally {
                tenancy()->end();
            }
        }

        return response('Not found', Response::HTTP_NOT_FOUND, $this->corsHeaders($request));
    }

    private function serveFile($disk, string $path, string $filename): mixed
    {
        $contents = $disk->get($path);
        $mime = $disk->mimeType($path) ?: 'image/webp';

        return response($contents, 200, array_merge([
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ], $this->corsHeaders(request())));
    }

    private function corsHeaders(Request $request): array
    {
        $origin = (string) $request->headers->get('Origin', '*');
        $allowed = [
            'https://admin.mapservices.in',
            'https://api.mapservices.in',
        ];

        if (
            $origin !== '' &&
            preg_match('#^https://[a-z0-9-]+\.mapservices\.in$#i', $origin)
        ) {
            $allowed[] = $origin;
        }

        $allowOrigin = in_array($origin, $allowed, true) ? $origin : '*';

        return [
            'Access-Control-Allow-Origin' => $allowOrigin,
            'Vary' => 'Origin',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Origin, Content-Type, Accept, Authorization',
        ];
    }
}
