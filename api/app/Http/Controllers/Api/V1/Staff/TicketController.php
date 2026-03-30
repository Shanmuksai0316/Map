<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    /**
     * Display a listing of tickets.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }
            $tenantId = $user->tenant_id;

            $query = Ticket::where('tenant_id', $tenantId)
                ->with(['createdBy:id,name,role', 'assignedTo:id,name,role']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by priority
            if ($request->has('priority')) {
                $query->where('priority', $request->priority);
            }

            // Filter by category
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            // Filter by assigned user
            if ($request->has('assigned')) {
                if ($request->assigned === 'me') {
                    $query->where('assigned_to', $user->id);
                } elseif ($request->assigned === 'unassigned') {
                    $query->whereNull('assigned_to');
                } else {
                    $query->where('assigned_to', $request->assigned);
                }
            }

            // Filter by date range
            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('location', 'like', "%{$search}%")
                      ->orWhereJsonContains('tags', $search);
                });
            }

            $tickets = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $tickets->items(),
                'pagination' => [
                    'current_page' => $tickets->currentPage(),
                    'last_page' => $tickets->lastPage(),
                    'per_page' => $tickets->perPage(),
                    'total' => $tickets->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tickets',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created ticket.
     */
    public function store(StoreTicketRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            DB::beginTransaction();

            $ticketData = $request->validated();
            $ticketData['tenant_id'] = $tenantId;
            $ticketData['created_by'] = $user->id;
            $ticketData['status'] = 'open';

            $ticket = Ticket::create($ticketData);

            // Log the ticket creation
            activity()
                ->causedBy($user)
                ->performedOn($ticket)
                ->log('Ticket created');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket created successfully',
                'data' => $ticket->load(['createdBy:id,name,role', 'assignedTo:id,name,role']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified ticket.
     */
    public function show(Ticket $ticket): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            // Check if ticket belongs to tenant
            if ($ticket->tenant_id !== $tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found',
                ], 404);
            }

            $ticket->load(['createdBy:id,name,role', 'assignedTo:id,name,role', 'comments']);

            return response()->json([
                'success' => true,
                'data' => $ticket,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified ticket.
     */
    public function update(UpdateTicketRequest $request, Ticket $ticket): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            // Check if ticket belongs to tenant
            if ($ticket->tenant_id !== $tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found',
                ], 404);
            }

            // Check permissions - only assigned user or supervisors+ can update
            if ($ticket->assigned_to !== $user->id && 
                !in_array($user->role, ['hk_supervisor', 'rm_supervisor', 'warden', 'campus_manager', 'rector'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions to update ticket',
                ], 403);
            }

            DB::beginTransaction();

            $ticketData = $request->validated();
            $ticket->update($ticketData);

            // Log the ticket update
            activity()
                ->causedBy($user)
                ->performedOn($ticket)
                ->log('Ticket updated');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket updated successfully',
                'data' => $ticket->load(['createdBy:id,name,role', 'assignedTo:id,name,role']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign ticket to a user.
     */
    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            // Check if ticket belongs to tenant
            if ($ticket->tenant_id !== $tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found',
                ], 404);
            }

            // Check permissions - only supervisors+ can assign tickets
            if (!in_array($user->role, ['hk_supervisor', 'rm_supervisor', 'warden', 'campus_manager', 'rector'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions to assign ticket',
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

            DB::beginTransaction();

            $ticket->update([
                'assigned_to' => $request->assigned_to,
                'status' => 'in_progress',
            ]);

            // Log the assignment
            activity()
                ->causedBy($user)
                ->performedOn($ticket)
                ->log("Ticket assigned to {$assignedUser->name}");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket assigned successfully',
                'data' => $ticket->load(['createdBy:id,name,role', 'assignedTo:id,name,role']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add comment to ticket.
     */
    public function addComment(Request $request, Ticket $ticket): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            // Check if ticket belongs to tenant
            if ($ticket->tenant_id !== $tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found',
                ], 404);
            }

            $request->validate([
                'body' => 'required|string|max:1000',
                'is_internal' => 'boolean',
                'attachments' => 'nullable|array',
                'attachments.*' => 'string',
            ]);

            DB::beginTransaction();

            $comment = $ticket->comments()->create([
                'user_id' => $user->id,
                'body' => $request->body,
                'attachments' => $request->input('attachments', []),
                'is_internal' => $request->boolean('is_internal', false),
            ]);

            // Log the comment
            activity()
                ->causedBy($user)
                ->performedOn($ticket)
                ->log('Comment added to ticket');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Comment added successfully',
                'data' => $comment->load('user'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to add comment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List comments for the ticket.
     */
    public function comments(Ticket $ticket): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            if ($ticket->tenant_id !== $tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found',
                ], 404);
            }

            $comments = $ticket->comments()
                ->with('user:id,name,role')
                ->orderBy('created_at')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $comments,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load comments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get dashboard statistics for tickets.
     */
    public function dashboard(): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            $stats = [
                'total_tickets' => Ticket::where('tenant_id', $tenantId)->count(),
                'open_tickets' => Ticket::where('tenant_id', $tenantId)
                    ->where('status', 'open')->count(),
                'in_progress_tickets' => Ticket::where('tenant_id', $tenantId)
                    ->where('status', 'in_progress')->count(),
                'resolved_tickets' => Ticket::where('tenant_id', $tenantId)
                    ->where('status', 'resolved')->count(),
                'closed_tickets' => Ticket::where('tenant_id', $tenantId)
                    ->where('status', 'closed')->count(),
                'my_tickets' => Ticket::where('tenant_id', $tenantId)
                    ->where('assigned_to', $user->id)->count(),
                'urgent_tickets' => Ticket::where('tenant_id', $tenantId)
                    ->where('priority', 'urgent')->count(),
                'recent_tickets' => Ticket::where('tenant_id', $tenantId)
                    ->with(['createdBy:id,name,role', 'assignedTo:id,name,role'])
                    ->orderBy('created_at', 'desc')
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
                'message' => 'Failed to fetch ticket dashboard',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
