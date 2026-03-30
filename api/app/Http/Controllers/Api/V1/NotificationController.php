<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\Notice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Notification Controller
 *
 * Handles in-app notifications (bell list) and Comm Box.
 */
class NotificationController extends Controller
{
    /**
     * Get unread notification count for bell badge
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['data' => ['unread_count' => 0]]);
        }

        $count = DB::table('user_notifications')
            ->where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('type')
                    ->orWhere('type', '!=', 'notice_published');
            })
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'data' => ['unread_count' => $count],
        ]);
    }

    /**
     * Get list of in-app notifications (paginated)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'data' => [],
                'meta' => ['current_page' => 1, 'per_page' => 20, 'total' => 0, 'last_page' => 1],
            ]);
        }

        $perPage = $request->integer('per_page', 20);
        $page = max(1, $request->integer('page', 1));
        $offset = ($page - 1) * $perPage;

        $query = DB::table('user_notifications')
            ->where('user_id', $user->id)
            ->where(function ($inner) {
                $inner->whereNull('type')
                    ->orWhere('type', '!=', 'notice_published');
            })
            ->orderByDesc('created_at');

        $total = (clone $query)->count();
        $rows = (clone $query)->offset($offset)->limit($perPage)->get();

        $data = $rows->map(function ($row) {
            $isRead = $row->read_at !== null;
            return [
                'id' => (int) $row->id,
                'title' => $row->title,
                'message' => $row->message,
                'body' => $row->message,
                'type' => $row->type ?? 'general',
                'created_at' => $row->created_at,
                'read' => $isRead,
                'read_at' => $row->read_at,
            ];
        })->values()->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) max(1, ceil($total / $perPage)),
            ],
        ]);
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(Request $request, int $notification): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            DB::table('user_notifications')
                ->where('id', $notification)
                ->where('user_id', $user->id)
                ->update(['read_at' => now()]);
        }

        return response()->json(['message' => 'Notification marked as read']);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = 0;
        if ($user) {
            $count = DB::table('user_notifications')
                ->where('user_id', $user->id)
                ->where(function ($query) {
                    $query->whereNull('type')
                        ->orWhere('type', '!=', 'notice_published');
                })
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return response()->json([
            'message' => 'All notifications marked as read',
            'data' => ['count' => $count],
        ]);
    }

    /**
     * Get comm box notifications (role-specific notifications)
     */
    public function commBox(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['data' => []]);
        }

        $perPage = $request->integer('per_page', 20);
        $page = max(1, $request->integer('page', 1));
        $offset = ($page - 1) * $perPage;
        $tenantId = $user->tenant_id;
        $hostelIds = $user->staffHostels()->pluck('hostels.id')->toArray();

        // Determine role context (staff vs student) so we can include the right audiences.
        $roleNames = $user->roles->pluck('name')->map(fn ($name) => strtolower((string) $name))->all();
        $isStaffRole = !empty(array_intersect($roleNames, [
            'campus manager',
            'rector',
            'warden',
            'hk supervisor',
            'rm supervisor',
            'sports manager',
            'laundry manager',
            'guard',
        ]));

        $hostelNames = [];
        if (!empty($hostelIds)) {
            $hostelNames = Hostel::whereIn('id', $hostelIds)->pluck('name', 'id')->toArray();
        }

        // For staff (Campus Manager, Rector, Warden, etc.), include both student- and staff-targeted notices.
        // For non-staff (e.g. student app), keep student audiences only.
        $audienceFilter = $isStaffRole
            ? ['all', 'students', 'all_students', 'specific_hostel', 'staff', 'both', null]
            : ['all', 'students', 'all_students', 'specific_hostel', null];

        $query = Notice::query()
            ->active()
            ->where('status', 'published')
            ->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id')
                  ->orWhere('tenant_id', $tenantId);
            })
            ->where(function ($q) use ($audienceFilter) {
                $q->whereNull('audience')
                  ->orWhereIn('audience', $audienceFilter);
            })
            // Campus Manager and Rector should see notices for all hostels in the tenant, even if they
            // don't have explicit staffHostels assignments. Other staff are scoped to assigned hostels.
            ->when(!empty($hostelIds) && !($user->hasRole('Rector') || $user->hasRole('Campus Manager')), function ($q) use ($hostelIds) {
                $q->where(function ($inner) use ($hostelIds) {
                    $inner->whereNull('hostel_id')->orWhereIn('hostel_id', $hostelIds);
                });
            })
            ->orderByDesc('publish_at');

        $total = (clone $query)->count();

        $notices = $query
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(fn (Notice $notice) => [
            'id' => $notice->id,
            'title' => $notice->title,
            'body' => $notice->body ?? $notice->content,
            'audience' => $notice->audience,
            'hostel_id' => $notice->hostel_id,
            'hostel_name' => $notice->hostel_id ? ($hostelNames[$notice->hostel_id] ?? null) : null,
            'publish_at' => $notice->publish_at?->toISOString(),
            'created_at' => $notice->created_at->toISOString(),
            'images' => $notice->images ?? [],
            'attachment_url' => $notice->attachment_url,
            'status' => $notice->status,
            ]);

        return response()->json([
            'data' => $notices,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) max(1, ceil($total / $perPage)),
            ],
        ]);
    }

    /**
     * Get unread comm box notifications
     * 
     * Note: Currently returns 0 as user notifications table doesn't exist yet.
     */
    public function commBoxUnread(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'data' => [
                    'unread_count' => 0,
                ],
            ]);
        }

        $count = DB::table('user_notifications')
            ->where('user_id', $user->id)
            ->where('type', 'notice_published')
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'data' => [
                'unread_count' => $count,
            ],
        ]);
    }
}
