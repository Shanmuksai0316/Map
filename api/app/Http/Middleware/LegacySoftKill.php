<?php

namespace App\Http\Middleware;

use App\Services\FeatureFlagsService;
use Closure;
use Illuminate\Http\Request;

class LegacySoftKill
{
    public function handle(Request $request, Closure $next, string $flag, string $location)
    {
        $tenantId = $request->user()?->tenant_id;
        
        if (app(FeatureFlagsService::class)->enabled($flag, $tenantId)) {
            return response()->json([
                'message' => 'This endpoint is deprecated',
                'location' => $location
            ], 410, [
                'Location' => $location
            ]);
        }

        return $next($request);
    }
}
