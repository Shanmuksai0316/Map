<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVisitorRequest;
use App\Http\Requests\UpdateVisitorRequest;
use App\Models\Visitor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VisitorController extends Controller
{
    /**
     * Display a listing of visitors.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            $query = Visitor::where('tenant_id', $tenantId)
                ->with(['student:id,name,roll_no,hostel_name', 'guard:id,name']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by date
            if ($request->has('date')) {
                $query->whereDate('visit_date', $request->date);
            }

            // Filter by date range
            if ($request->has('date_from')) {
                $query->whereDate('visit_date', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->whereDate('visit_date', '<=', $request->date_to);
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('visitor_name', 'like', "%{$search}%")
                      ->orWhere('visitor_phone', 'like', "%{$search}%")
                      ->orWhere('purpose', 'like', "%{$search}%")
                      ->orWhereHas('student', function ($studentQuery) use ($search) {
                          $studentQuery->where('name', 'like', "%{$search}%")
                                     ->orWhere('roll_no', 'like', "%{$search}%");
                      });
                });
            }

            $visitors = $query->orderBy('visit_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $visitors->items(),
                'pagination' => [
                    'current_page' => $visitors->currentPage(),
                    'last_page' => $visitors->lastPage(),
                    'per_page' => $visitors->perPage(),
                    'total' => $visitors->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch visitors',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created visitor.
     */
    public function store(StoreVisitorRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            DB::beginTransaction();

            $visitorData = $request->validated();
            $visitorData['tenant_id'] = $tenantId;
            $visitorData['guard_id'] = $user->id;
            $visitorData['status'] = 'pending';
            $visitorData['visit_date'] = now()->toDateString();

            $visitor = Visitor::create($visitorData);

            // Log the visitor registration
            activity()
                ->causedBy($user)
                ->performedOn($visitor)
                ->log('Visitor registered');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Visitor registered successfully',
                'data' => $visitor->load(['student:id,name,roll_no,hostel_name', 'guard:id,name']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to register visitor',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified visitor.
     */
    public function show(Visitor $visitor): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            // Check if visitor belongs to tenant
            if ($visitor->tenant_id !== $tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Visitor not found',
                ], 404);
            }

            $visitor->load(['student:id,name,roll_no,hostel_name', 'guard:id,name']);

            return response()->json([
                'success' => true,
                'data' => $visitor,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch visitor',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified visitor.
     */
    public function update(UpdateVisitorRequest $request, Visitor $visitor): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            // Check if visitor belongs to tenant
            if ($visitor->tenant_id !== $tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Visitor not found',
                ], 404);
            }

            // Check permissions - only guards and above can update visitors
            if (!in_array($user->role, ['guard', 'warden', 'campus_manager', 'rector'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions to update visitor',
                ], 403);
            }

            DB::beginTransaction();

            $visitorData = $request->validated();
            $visitor->update($visitorData);

            // Log the visitor update
            activity()
                ->causedBy($user)
                ->performedOn($visitor)
                ->log('Visitor updated');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Visitor updated successfully',
                'data' => $visitor->load(['student:id,name,roll_no,hostel_name', 'guard:id,name']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update visitor',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Allow visitor entry.
     */
    public function allow(Visitor $visitor): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            // Check if visitor belongs to tenant
            if ($visitor->tenant_id !== $tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Visitor not found',
                ], 404);
            }

            // Check permissions - only guards and above can allow visitors
            if (!in_array($user->role, ['guard', 'warden', 'campus_manager', 'rector'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions to allow visitor',
                ], 403);
            }

            if ($visitor->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Visitor status cannot be changed',
                ], 400);
            }

            DB::beginTransaction();

            $visitor->update([
                'status' => 'allowed',
                'allowed_at' => now(),
                'allowed_by' => $user->id,
            ]);

            // Log the visitor allowance
            activity()
                ->causedBy($user)
                ->performedOn($visitor)
                ->log('Visitor allowed entry');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Visitor allowed entry successfully',
                'data' => $visitor->load(['student:id,name,roll_no,hostel_name', 'guard:id,name']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to allow visitor',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deny visitor entry.
     */
    public function deny(Request $request, Visitor $visitor): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            // Check if visitor belongs to tenant
            if ($visitor->tenant_id !== $tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Visitor not found',
                ], 404);
            }

            // Check permissions - only guards and above can deny visitors
            if (!in_array($user->role, ['guard', 'warden', 'campus_manager', 'rector'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions to deny visitor',
                ], 403);
            }

            if ($visitor->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Visitor status cannot be changed',
                ], 400);
            }

            $request->validate([
                'denial_reason' => 'required|string|max:500',
            ]);

            DB::beginTransaction();

            $visitor->update([
                'status' => 'denied',
                'denied_at' => now(),
                'denied_by' => $user->id,
                'denial_reason' => $request->denial_reason,
            ]);

            // Log the visitor denial
            activity()
                ->causedBy($user)
                ->performedOn($visitor)
                ->log('Visitor denied entry');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Visitor denied entry successfully',
                'data' => $visitor->load(['student:id,name,roll_no,hostel_name', 'guard:id,name']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to deny visitor',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Record visitor exit.
     */
    public function exit(Visitor $visitor): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            // Check if visitor belongs to tenant
            if ($visitor->tenant_id !== $tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Visitor not found',
                ], 404);
            }

            // Check permissions - only guards and above can record visitor exit
            if (!in_array($user->role, ['guard', 'warden', 'campus_manager', 'rector'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions to record visitor exit',
                ], 403);
            }

            if ($visitor->status !== 'allowed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Visitor must be allowed before recording exit',
                ], 400);
            }

            DB::beginTransaction();

            $visitor->update([
                'status' => 'exited',
                'exited_at' => now(),
                'exited_by' => $user->id,
            ]);

            // Log the visitor exit
            activity()
                ->causedBy($user)
                ->performedOn($visitor)
                ->log('Visitor exit recorded');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Visitor exit recorded successfully',
                'data' => $visitor->load(['student:id,name,roll_no,hostel_name', 'guard:id,name']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to record visitor exit',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get today's visitors.
     */
    public function today(): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            $visitors = Visitor::where('tenant_id', $tenantId)
                ->whereDate('visit_date', today())
                ->with(['student:id,name,roll_no,hostel_name', 'guard:id,name'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $visitors,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch today\'s visitors',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get visitor statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            $dateFrom = $request->get('date_from', now()->subDays(30)->toDateString());
            $dateTo = $request->get('date_to', now()->toDateString());

            $stats = [
                'total_visitors' => Visitor::where('tenant_id', $tenantId)
                    ->whereBetween('visit_date', [$dateFrom, $dateTo])
                    ->count(),
                'pending_visitors' => Visitor::where('tenant_id', $tenantId)
                    ->where('status', 'pending')
                    ->whereBetween('visit_date', [$dateFrom, $dateTo])
                    ->count(),
                'allowed_visitors' => Visitor::where('tenant_id', $tenantId)
                    ->where('status', 'allowed')
                    ->whereBetween('visit_date', [$dateFrom, $dateTo])
                    ->count(),
                'denied_visitors' => Visitor::where('tenant_id', $tenantId)
                    ->where('status', 'denied')
                    ->whereBetween('visit_date', [$dateFrom, $dateTo])
                    ->count(),
                'exited_visitors' => Visitor::where('tenant_id', $tenantId)
                    ->where('status', 'exited')
                    ->whereBetween('visit_date', [$dateFrom, $dateTo])
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch visitor statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

