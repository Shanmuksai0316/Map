<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

trait IdempotentController
{
    /**
     * Handle idempotent requests with caching
     * 
     * @param Request $request
     * @param int $tenantId
     * @param callable $callback Function to execute if not cached
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleIdempotentRequest(Request $request, int $tenantId, callable $callback)
    {
        $key = $request->header('X-Idempotency-Key');
        
        if ($key) {
            $cacheKey = "idem:{$tenantId}:{$key}";
            
            if ($cached = Cache::get($cacheKey)) {
                return response()->json([
                    'data' => $cached,
                    'idempotent' => true
                ], 200);
            }
        }
        
        // Execute the callback to get the result
        $result = $callback();
        
        // Cache the result if we have an idempotency key
        if ($key) {
            Cache::put($cacheKey, $result, 900); // 15 minutes
        }
        
        return response()->json([
            'data' => $result
        ], 201);
    }

    /**
     * Generate a stable idempotency key for mobile clients
     * 
     * @param array $params Parameters to include in the key
     * @return string
     */
    protected function generateIdempotencyKey(array $params): string
    {
        $now = now();
        $minuteKey = $now->format('Y-m-d-H-i');
        
        return implode('-', array_merge($params, [$minuteKey]));
    }
}
