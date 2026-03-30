<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notice\StoreNoticeRequest;
use App\Http\Requests\Notice\UpdateNoticeRequest;
use App\Http\Resources\NoticeResource;
use App\Models\Attachment;
use App\Models\Notice;
use App\Services\Notifications\NotificationRecipients;
use App\Services\Notify\PushNotifier;
use App\Support\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class NoticeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('notices_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('viewAny', Notice::class);

        $notices = Notice::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->boolean('active_only', false), fn ($query) => $query
                ->where(function ($inner): void {
                    $inner->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->where(fn ($inner) => $inner->where('status', 'published')->orWhere('publish_at', '<=', now())))
            ->latest('publish_at')
            ->with('attachments')
            ->paginate($request->integer('per_page', 25));

        return NoticeResource::collection($notices)->response();
    }

    public function show(Notice $notice): JsonResponse
    {
        abort_unless(Feature::isEnabled('notices_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('view', $notice);

        return NoticeResource::make($notice->load('attachments'))->response();
    }

    public function store(StoreNoticeRequest $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('notices_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('create', Notice::class);

        $data = $request->validated();

        $notice = Notice::query()->create([
            'tenant_id' => Auth::user()->tenant_id,
            'campus_id' => $data['campus_id'] ?? null,
            'hostel_id' => $data['hostel_id'] ?? null,
            'title' => $data['title'],
            'body' => $data['body'],
            'audience' => $data['audience'],
            'channels' => $data['channels'] ?? [],
            'publish_at' => $data['publish_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return NoticeResource::make($notice)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(Notice $notice, UpdateNoticeRequest $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('notices_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('update', $notice);

        if ($notice->tenant_id !== Auth::user()->tenant_id) {
            abort(Response::HTTP_FORBIDDEN, 'Cannot update notice from another tenant.');
        }

        $notice->fill($request->validated());
        $notice->save();

        return NoticeResource::make($notice)
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function publish(Notice $notice): JsonResponse
    {
        abort_unless(Feature::isEnabled('notices_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('update', $notice);

        if ($notice->tenant_id !== Auth::user()->tenant_id) {
            abort(Response::HTTP_FORBIDDEN, 'Cannot publish notice from another tenant.');
        }

        $notice->publish();

        // Dispatch push notifications if channels include push
        if ($notice->shouldSendPush()) {
            $this->sendPushNotifications($notice);
        }

        return NoticeResource::make($notice)
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    /**
     * Send role-based push notifications for a published notice.
     */
    public function sendPushNotifications(Notice $notice): void
    {
        try {
            $push       = app(PushNotifier::class);
            $recipients = app(NotificationRecipients::class);

            $tenantId = (string) $notice->tenant_id;
            $hostelId = $notice->hostel_id ? (int) $notice->hostel_id : null;

            $title = $notice->title;
            $body  = $notice->body ?? $notice->content ?? '';

            $snippet = mb_substr($body, 0, 140);

            // Rector
            $rector = $hostelId ? $recipients->rectorForHostel($tenantId, $hostelId) : null;
            if ($rector) {
                $push->toUserTemplate(
                    $rector->id,
                    'rector.notice_published',
                    [
                        'notice_title' => $title,
                        'notice_body'  => $snippet,
                    ],
                    [
                        'type'      => 'notice_published',
                        'notice_id' => (string) $notice->id,
                    ]
                );
            }

            // Campus Manager
            $campusManager = $recipients->campusManagerForTenant($tenantId);
            if ($campusManager) {
                $push->toUserTemplate(
                    $campusManager->id,
                    'campus_manager.notice_published',
                    [
                        'notice_title' => $title,
                        'notice_body'  => $snippet,
                    ],
                    [
                        'type'      => 'notice_published',
                        'notice_id' => (string) $notice->id,
                    ]
                );
            }

            // Warden (hostel-scoped notices)
            $warden = $hostelId ? $recipients->wardenForHostel($tenantId, $hostelId) : null;
            if ($warden) {
                $push->toUserTemplate(
                    $warden->id,
                    'warden.notice_published',
                    [
                        'notice_title' => $title,
                        'notice_body'  => $snippet,
                    ],
                    [
                        'type'      => 'notice_published',
                        'notice_id' => (string) $notice->id,
                    ]
                );
            }

            // Hostel-scoped roles: HK Supervisor, RM Supervisor, Guards
            if ($hostelId) {
                // HK Supervisor
                $hkSupervisor = $recipients->hkSupervisorForHostel($tenantId, $hostelId);
                if ($hkSupervisor) {
                    $push->toUserTemplate(
                        $hkSupervisor->id,
                        'hk_supervisor.notice_published',
                        [
                            'notice_title' => $title,
                            'notice_body'  => $snippet,
                        ],
                        [
                            'type'      => 'notice_published',
                            'notice_id' => (string) $notice->id,
                        ]
                    );
                }

                // RM Supervisor
                $rmSupervisor = $recipients->rmSupervisorForHostel($tenantId, $hostelId);
                if ($rmSupervisor) {
                    $push->toUserTemplate(
                        $rmSupervisor->id,
                        'rm_supervisor.notice_published',
                        [
                            'notice_title' => $title,
                            'notice_body'  => $snippet,
                        ],
                        [
                            'type'      => 'notice_published',
                            'notice_id' => (string) $notice->id,
                        ]
                    );
                }

                // Guards (all guards mapped to this hostel)
                foreach ($recipients->guardsForHostel($tenantId, $hostelId) as $guard) {
                    $push->toUserTemplate(
                        $guard->id,
                        'guard.notice_published',
                        [
                            'notice_title' => $title,
                            'notice_body'  => $snippet,
                        ],
                        [
                            'type'      => 'notice_published',
                            'notice_id' => (string) $notice->id,
                        ]
                    );
                }
            }

            // Tenant-scoped roles: Laundry Manager, Sports Manager
            foreach ($recipients->laundryManagersForTenant($tenantId) as $laundryManager) {
                $push->toUserTemplate(
                    $laundryManager->id,
                    'laundry_manager.notice_published',
                    [
                        'notice_title' => $title,
                        'notice_body'  => $snippet,
                    ],
                    [
                        'type'      => 'notice_published',
                        'notice_id' => (string) $notice->id,
                    ]
                );
            }

            foreach ($recipients->sportsManagersForTenant($tenantId) as $sportsManager) {
                $push->toUserTemplate(
                    $sportsManager->id,
                    'sports_manager.notice_published',
                    [
                        'notice_title' => $title,
                        'notice_body'  => $snippet,
                    ],
                    [
                        'type'      => 'notice_published',
                        'notice_id' => (string) $notice->id,
                    ]
                );
            }

            // All staff (Staff All template)
            foreach ($recipients->staffAllForTenant($tenantId) as $staff) {
                $push->toUserTemplate(
                    $staff->id,
                    'staff_all.notice_published',
                    [
                        'notice_title' => $title,
                        'notice_body'  => $snippet,
                    ],
                    [
                        'type'      => 'notice_published',
                        'notice_id' => (string) $notice->id,
                    ]
                );
            }

            // All students
            foreach ($recipients->studentsForTenant($tenantId) as $studentUser) {
                $push->toUserTemplate(
                    $studentUser->id,
                    'student.notice_published',
                    [
                        'notice_title' => $title,
                        'notice_body'  => $snippet,
                    ],
                    [
                        'type'      => 'notice_published',
                        'notice_id' => (string) $notice->id,
                    ]
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to send notice push notifications', [
                'notice_id' => $notice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function schedule(Notice $notice, Request $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('notices_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('update', $notice);

        if ($notice->tenant_id !== Auth::user()->tenant_id) {
            abort(Response::HTTP_FORBIDDEN, 'Cannot schedule notice from another tenant.');
        }

        $validated = $request->validate([
            'publish_at' => 'required|date|after:now',
        ]);

        $publishAt = \Illuminate\Support\Carbon::parse($validated['publish_at']);
        $notice->scheduleFor($publishAt);

        return NoticeResource::make($notice)
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function attachments(Notice $notice): JsonResponse
    {
        abort_unless(Feature::isEnabled('notices_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('view', $notice);

        return response()->json([
            'data' => $notice->attachments()->get()->map(function (Attachment $attachment) {
                return [
                    'id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                    'download_url' => $attachment->status === 'clean'
                        ? ($attachment->key ? url('/storage/' . $attachment->key) : null)
                        : null,
                ];
            }),
        ]);
    }

    public function attach(Notice $notice, Request $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('notices_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('update', $notice);

        if ($notice->tenant_id !== Auth::user()->tenant_id) {
            abort(Response::HTTP_FORBIDDEN, 'Cannot attach to notice from another tenant.');
        }

        $validated = $request->validate([
            'attachment_id' => 'required|exists:attachments,id',
        ]);

        $attachment = Attachment::where('tenant_id', Auth::user()->tenant_id)
            ->where('user_id', Auth::id())
            ->findOrFail($validated['attachment_id']);

        $notice->attachments()->syncWithoutDetaching([$attachment->id]);

        return response()->json([
            'message' => 'Attachment added successfully',
        ]);
    }

    public function detach(Notice $notice, Request $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('notices_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('update', $notice);

        if ($notice->tenant_id !== Auth::user()->tenant_id) {
            abort(Response::HTTP_FORBIDDEN, 'Cannot detach from notice from another tenant.');
        }

        $validated = $request->validate([
            'attachment_id' => 'required|exists:attachments,id',
        ]);

        $attachment = Attachment::where('tenant_id', Auth::user()->tenant_id)
            ->where('user_id', Auth::id())
            ->findOrFail($validated['attachment_id']);

        $notice->attachments()->detach($attachment->id);

        return response()->json([
            'message' => 'Attachment removed successfully',
        ]);
    }

    public function destroy(Notice $notice): JsonResponse
    {
        abort_unless(Feature::isEnabled('notices_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('delete', $notice);

        if ($notice->tenant_id !== Auth::user()->tenant_id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $notice->forceFill(['status' => 'archived'])->save();
        $notice->delete();

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }
}
