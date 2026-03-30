<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CampusResource;
use App\Models\Campus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CampusController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Campus::class);

        $tenantId = Auth::user()?->tenant_id;

        $campuses = Campus::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return CampusResource::collection($campuses)->response();
    }
}
