<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures Livewire temporary upload directory exists and is writable before handling /livewire/upload-file.
 * Fixes 500 when storage/app/private/livewire-tmp is missing or not writable (e.g. Docker volume permissions).
 */
class EnsureLivewireUploadDir
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('livewire/upload-file') && ! str_contains($request->path(), 'livewire/upload-file')) {
            return $next($request);
        }

        $livewireTmp = storage_path('app/private/livewire-tmp');
        $privateRoot = storage_path('app/private');

        try {
            if (! File::isDirectory($privateRoot)) {
                File::ensureDirectoryExists($privateRoot, 0775);
            }
            if (! File::isDirectory($livewireTmp)) {
                File::ensureDirectoryExists($livewireTmp, 0775);
            }
            if (! is_writable($livewireTmp)) {
                @chmod($livewireTmp, 0775);
            }
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'message' => 'File upload directory is not available. Please contact support.',
            ], 503);
        }

        return $next($request);
    }
}
