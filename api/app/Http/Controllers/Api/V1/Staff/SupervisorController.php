<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SupervisorController extends Controller
{
    /**
     * Get supervisor dashboard (works for HK and RM supervisors)
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Log authentication status for debugging
        if (!$user) {
            \Log::error('SupervisorController::dashboard - User not authenticated', [
                'has_auth_header' => $request->hasHeader('Authorization'),
                'auth_header_present' => !empty($request->header('Authorization')),
                'auth_header_preview' => $request->hasHeader('Authorization') ? substr($request->header('Authorization'), 0, 20) . '...' : 'missing',
                'tenant_code' => $request->header('X-Tenant-Code'),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);
            
            return response()->json([
                'type' => 'https://map-hms.dev/errors/unauthenticated',
                'title' => 'Unauthenticated',
                'status' => Response::HTTP_UNAUTHORIZED,
                'detail' => 'Authentication required. Please log in again.',
            ], Response::HTTP_UNAUTHORIZED);
        }
        
        if (!$user->hasAnyRole(['HK Supervisor', 'RM Supervisor'])) {
            \Log::warning('SupervisorController::dashboard - User does not have supervisor role', [
                'user_id' => $user->id,
                'user_roles' => $user->roles->pluck('name')->toArray(),
            ]);
            
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only supervisors can access this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            // Determine supervisor type based on role
            $isHKSupervisor = $user->hasRole('HK Supervisor');
            $isRMSupervisor = $user->hasRole('RM Supervisor');
            
            // Get relevant ticket categories
            $categories = [];
            if ($isHKSupervisor) {
                $categories = ['housekeeping', 'cleaning', 'maintenance'];
            } elseif ($isRMSupervisor) {
                $categories = ['maintenance', 'repair_maintenance', 'electrical', 'plumbing', 'furniture'];
            }
            
            $tenantId = $user->tenant_id;
            
            if (!$tenantId) {
                \Log::error('SupervisorController::dashboard: No tenant_id for user', ['user_id' => $user->id]);
                return response()->json([
                    'data' => [
                        'assigned_tickets' => 0,
                        'pending_tickets' => 0,
                        'in_progress_tickets' => 0,
                        'hostel_tickets' => 0,
                        'supervisor_type' => $isHKSupervisor ? 'HK' : 'RM',
                    ],
                ], Response::HTTP_OK);
            }
            
            // Get assigned tickets
            $assignedTickets = Ticket::where('tenant_id', $tenantId)
                ->where('assignee_user_id', $user->id)
                ->where('status', '!=', 'closed')
                ->count();
            
            $pendingTickets = Ticket::where('tenant_id', $tenantId)
                ->where('assignee_user_id', $user->id)
                ->where('status', 'open')
                ->count();
            
            $inProgressTickets = Ticket::where('tenant_id', $tenantId)
                ->where('assignee_user_id', $user->id)
                ->where('status', 'in_progress')
                ->count();
            
            // Get hostel-specific stats if assigned
            // Note: Role check is already done above, so we just get assigned hostels
            $hostelIds = $user->staffHostels()
                ->pluck('hostels.id')
                ->toArray();
            
            $hostelTickets = !empty($hostelIds) 
                ? Ticket::where('tenant_id', $tenantId)
                    ->whereIn('hostel_id', $hostelIds)
                    ->whereIn('category', $categories)
                    ->where('status', '!=', 'closed')
                    ->count()
                : 0;

            $stats = [
                'assigned_tickets' => $assignedTickets,
                'pending_tickets' => $pendingTickets,
                'in_progress_tickets' => $inProgressTickets,
                'hostel_tickets' => $hostelTickets,
                'supervisor_type' => $isHKSupervisor ? 'HK' : 'RM',
            ];

            return response()->json([
                'data' => $stats,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch supervisor dashboard', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/fetch_failed',
                'title' => 'Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve dashboard data. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get tickets assigned to supervisor
     */
    public function tickets(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/unauthenticated',
                'title' => 'Unauthenticated',
                'status' => Response::HTTP_UNAUTHORIZED,
                'detail' => 'Authentication required. Please log in again.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->hasAnyRole(['HK Supervisor', 'RM Supervisor'])) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only supervisors can access this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $tenantId = $user->tenant_id;
            if (! $tenantId) {
                return response()->json(['data' => ['data' => []]], Response::HTTP_OK);
            }

            $isHKSupervisor = $user->hasRole('HK Supervisor');
            $isRMSupervisor = $user->hasRole('RM Supervisor');

            $query = Ticket::where('tenant_id', $tenantId)
                ->with(['hostel', 'reporterStudent.user', 'reporterUser', 'createdByUser', 'assigneeUser']);

            if ($isHKSupervisor) {
                $query->whereIn('category', ['cleaning', 'housekeeping']);
            } elseif ($isRMSupervisor) {
                $query->whereIn('category', ['maintenance', 'repair_maintenance', 'electrical', 'plumbing', 'furniture']);
            } else {
                return response()->json(['data' => ['data' => []]], Response::HTTP_OK);
            }

            // Tickets assigned to this supervisor OR unassigned tickets in their category
            $query->where(function ($q) use ($user) {
                $q->where(function ($subQ) use ($user) {
                    $subQ->where('assignee_user_id', $user->id)
                        ->orWhere('assigned_to', $user->id);
                })
                ->orWhere(function ($subQ) {
                    $subQ->whereNull('assignee_user_id')
                        ->whereNull('assigned_to');
                });
            });

            if ($request->filled('status')) {
                $query->where('status', (string) $request->input('status'));
            }

            $limit = (int) $request->integer('limit', 50);

            $tickets = $query->latest()
                ->limit($limit)
                ->get()
                ->map(function (Ticket $ticket) {
                    // category -> mobile type
                    $type = 'repair_maintenance';
                    if (in_array($ticket->category, ['housekeeping', 'cleaning'], true)) {
                        $type = 'housekeeping';
                    } elseif (in_array($ticket->category, ['maintenance', 'repair_maintenance'], true)) {
                        $type = 'repair_maintenance';
                    }

                    // status -> mobile status
                    $status = 'pending';
                    if ($ticket->status === 'in_progress') {
                        $status = 'in_progress';
                    } elseif ($ticket->status === 'resolved') {
                        $status = 'completed';
                    } elseif ($ticket->status === 'closed') {
                        $status = 'cancelled';
                    }

                    $studentName = 'Unknown';
                    if ($ticket->reporterUser) {
                        $studentName = $ticket->reporterUser->name ?? 'Unknown';
                    } elseif ($ticket->reporterStudent && $ticket->reporterStudent->user) {
                        $studentName = $ticket->reporterStudent->user->name ?? 'Unknown';
                    }

                    return [
                        'id' => (int) $ticket->id,
                        'type' => $type,
                        'title' => $ticket->title,
                        'description' => $ticket->description,
                        'status' => $status,
                        'priority' => $ticket->priority,
                        'student_id' => (int) ($ticket->reporter_student_id ?? 0),
                        'student_name' => $studentName,
                        'hostel_name' => $ticket->hostel ? ($ticket->hostel->name ?? 'Unknown') : 'Unknown',
                        'created_by' => $ticket->createdByUser ? ($ticket->createdByUser->name ?? 'System') : 'System',
                        'assigned_to' => $ticket->assigneeUser ? ($ticket->assigneeUser->name ?? null) : null,
                        'tenant_id' => $ticket->tenant_id,
                        'created_at' => $ticket->created_at ? $ticket->created_at->toIso8601String() : null,
                        'updated_at' => $ticket->updated_at ? $ticket->updated_at->toIso8601String() : null,
                        'resolved_at' => $ticket->closed_at ? $ticket->closed_at->toIso8601String() : null,
                    ];
                })
                ->values();

            return response()->json([
                'data' => [
                    'data' => $tickets,
                ],
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            Log::error('SupervisorController::tickets failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/fetch_failed',
                'title' => 'Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve tickets. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
