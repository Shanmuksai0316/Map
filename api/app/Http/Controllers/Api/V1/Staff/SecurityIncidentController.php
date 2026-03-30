<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSecurityIncidentRequest;
use App\Http\Requests\UpdateSecurityIncidentRequest;
use App\Models\SecurityIncident;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class SecurityIncidentController extends Controller
{
    /**
     * Display a listing of security incidents.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            $query = SecurityIncident::where('tenant_id', $tenantId)
                ->with(['reportedBy:id,name,role', 'assignedTo:id,name,role']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by severity
            if ($request->has('severity')) {
                $query->where('severity', $request->severity);
            }

            // Filter by date range
            if ($request->has('date_from')) {
                $query->whereDate('incident_date', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->whereDate('incident_date', '<=', $request->date_to);
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('location', 'like', "%{$search}%");
                });
            }

            $incidents = $query->orderBy('incident_date', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $incidents->items(),
                'pagination' => [
                    'current_page' => $incidents->currentPage(),
                    'last_page' => $incidents->lastPage(),
                    'per_page' => $incidents->perPage(),
                    'total' => $incidents->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch security incidents',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created security incident.
     */
    public function store(StoreSecurityIncidentRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            DB::beginTransaction();

            $incidentData = $request->validated();
            $incidentData['tenant_id'] = $tenantId;
            $incidentData['reported_by'] = $user->id;
            $incidentData['status'] = 'open';
            $incidentData['incident_date'] = now();

            // Handle photo upload (stored on local server)
            if ($request->hasFile('photo')) {
                $photo = $request->file('photo');
                $filename = time() . '_' . $photo->getClientOriginalName();
                $path = $photo->storeAs('security_incidents', $filename, 'public');
                $incidentData['photo_url'] = Storage::disk('public')->url($path);
            }

            $incident = SecurityIncident::create($incidentData);

            // Log the incident creation
            activity()
                ->causedBy($user)
                ->performedOn($incident)
                ->log('Security incident reported');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Security incident reported successfully',
                'data' => $incident->load(['reportedBy:id,name,role']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to report security incident',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified security incident.
     */
    public function show(SecurityIncident $securityIncident): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            // Check if incident belongs to tenant
            if ($securityIncident->tenant_id !== $tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Security incident not found',
                ], 404);
            }

            $securityIncident->load(['reportedBy:id,name,role', 'assignedTo:id,name,role']);

            return response()->json([
                'success' => true,
                'data' => $securityIncident,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch security incident',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified security incident.
     */
    public function update(UpdateSecurityIncidentRequest $request, SecurityIncident $securityIncident): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            // Check if incident belongs to tenant
            if ($securityIncident->tenant_id !== $tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Security incident not found',
                ], 404);
            }

            // Check permissions - only guards and above can update incidents
            if (!in_array($user->role, ['guard', 'warden', 'campus_manager', 'rector'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions to update security incident',
                ], 403);
            }

            DB::beginTransaction();

            $incidentData = $request->validated();

            // Handle photo upload (stored on local server)
            if ($request->hasFile('photo')) {
                // Delete old photo if exists (key is path after /storage/)
                if ($securityIncident->photo_url) {
                    $pathPart = parse_url($securityIncident->photo_url, PHP_URL_PATH);
                    $prefix = '/storage/';
                    $oldKey = $pathPart && str_starts_with($pathPart, $prefix)
                        ? substr($pathPart, strlen($prefix))
                        : null;
                    if ($oldKey && Storage::disk('public')->exists($oldKey)) {
                        Storage::disk('public')->delete($oldKey);
                    }
                }

                $photo = $request->file('photo');
                $filename = time() . '_' . $photo->getClientOriginalName();
                $path = $photo->storeAs('security_incidents', $filename, 'public');
                $incidentData['photo_url'] = Storage::disk('public')->url($path);
            }

            $securityIncident->update($incidentData);

            // Log the incident update
            activity()
                ->causedBy($user)
                ->performedOn($securityIncident)
                ->log('Security incident updated');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Security incident updated successfully',
                'data' => $securityIncident->load(['reportedBy:id,name,role', 'assignedTo:id,name,role']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update security incident',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign incident to a staff member.
     */
    public function assign(Request $request, SecurityIncident $securityIncident): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            // Check if incident belongs to tenant
            if ($securityIncident->tenant_id !== $tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Security incident not found',
                ], 404);
            }

            // Check permissions - only warden and above can assign incidents
            if (!in_array($user->role, ['warden', 'campus_manager', 'rector'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions to assign security incident',
                ], 403);
            }

            $request->validate([
                'assigned_to' => 'required|exists:users,id',
            ]);

            $assignedUser = User::where('id', $request->assigned_to)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$assignedUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assigned user not found',
                ], 404);
            }

            $securityIncident->update([
                'assigned_to' => $request->assigned_to,
                'status' => 'assigned',
            ]);

            // Log the assignment
            activity()
                ->causedBy($user)
                ->performedOn($securityIncident)
                ->log("Security incident assigned to {$assignedUser->name}");

            return response()->json([
                'success' => true,
                'message' => 'Security incident assigned successfully',
                'data' => $securityIncident->load(['reportedBy:id,name,role', 'assignedTo:id,name,role']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign security incident',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Close the security incident.
     */
    public function close(Request $request, SecurityIncident $securityIncident): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            // Check if incident belongs to tenant
            if ($securityIncident->tenant_id !== $tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Security incident not found',
                ], 404);
            }

            // Check permissions - only assigned user or warden+ can close
            if ($securityIncident->assigned_to !== $user->id && 
                !in_array($user->role, ['warden', 'campus_manager', 'rector'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions to close security incident',
                ], 403);
            }

            $request->validate([
                'resolution' => 'required|string|max:1000',
            ]);

            $securityIncident->update([
                'status' => 'closed',
                'resolution' => $request->resolution,
                'closed_at' => now(),
                'closed_by' => $user->id,
            ]);

            // Log the closure
            activity()
                ->causedBy($user)
                ->performedOn($securityIncident)
                ->log('Security incident closed');

            return response()->json([
                'success' => true,
                'message' => 'Security incident closed successfully',
                'data' => $securityIncident->load(['reportedBy:id,name,role', 'assignedTo:id,name,role']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to close security incident',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get dashboard statistics for security incidents.
     */
    public function dashboard(): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            $stats = [
                'total_incidents' => SecurityIncident::where('tenant_id', $tenantId)->count(),
                'open_incidents' => SecurityIncident::where('tenant_id', $tenantId)
                    ->where('status', 'open')->count(),
                'assigned_incidents' => SecurityIncident::where('tenant_id', $tenantId)
                    ->where('status', 'assigned')->count(),
                'closed_incidents' => SecurityIncident::where('tenant_id', $tenantId)
                    ->where('status', 'closed')->count(),
                'high_severity' => SecurityIncident::where('tenant_id', $tenantId)
                    ->where('severity', 'high')->count(),
                'recent_incidents' => SecurityIncident::where('tenant_id', $tenantId)
                    ->with(['reportedBy:id,name,role'])
                    ->orderBy('incident_date', 'desc')
                    ->limit(5)
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch security incident dashboard',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

