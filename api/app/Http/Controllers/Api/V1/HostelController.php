<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\HostelResource;
use App\Models\Hostel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HostelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Hostel::class);

        $tenantId = Auth::user()?->tenant_id;

        $hostels = Hostel::query()
            ->where('tenant_id', $tenantId)
            ->when($request->filled('campus_id'), fn ($query) => $query->where('campus_id', $request->integer('campus_id')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return HostelResource::collection($hostels)->response();
    }
}
