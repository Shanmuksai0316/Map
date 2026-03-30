<?php

namespace App\Http\Controllers;

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\Checklists\Models\ChecklistItem;
use App\Domain\Checklists\Models\ChecklistTemplate;
use App\Domain\Checklists\Repositories\ChecklistInstanceRepository;
use App\Domain\Checklists\Support\ChecklistRole;
use App\Http\Requests\Checklists\MarkItemRequest;
use App\Http\Requests\Checklists\SubmitChecklistRequest;
use App\Policies\ChecklistPolicy;
use App\Services\AuditLogger;
use App\Services\FeatureFlagsService;
use App\Services\Notifications\ChecklistNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ChecklistsController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ChecklistNotifier $notifier
    ) {}

    /**
     * List all checklist templates for the current tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $this->ensureFeatureEnabled($request);

        $user = $request->user();
        $templates = ChecklistTemplate::query()
            ->where('tenant_id', $user->tenant_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $templates]);
    }

    /**
     * Store a new checklist template.
     */
    public function store(Request $request): JsonResponse
    {
        $this->ensureFeatureEnabled($request);

        $user = $request->user();
        $validated = $request->validate([
            'role' => ['required', 'string'],
            'shift' => ['nullable', 'string'],
            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*.label' => ['required', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);

        $tasks = $this->normalizeTasks($validated['tasks']);

        $template = ChecklistTemplate::create([
            'tenant_id' => $user->tenant_id,
            'role' => $validated['role'],
            'shift' => $validated['shift'] ?? 'Daily',
            'tasks' => $tasks,
            'active' => $validated['active'] ?? true,
        ]);

        return response()->json(['data' => $template], 201);
    }

    /**
     * Show a specific checklist template.
     */
    public function show(Request $request, $checklist): JsonResponse
    {
        $this->ensureFeatureEnabled($request);

        $user = $request->user();
        $template = ChecklistTemplate::where('tenant_id', $user->tenant_id)
            ->findOrFail($checklist);

        return response()->json(['data' => $template]);
    }

    /**
     * Update a checklist template.
     */
    public function update(Request $request, $checklist): JsonResponse
    {
        $this->ensureFeatureEnabled($request);

        $user = $request->user();
        $template = ChecklistTemplate::where('tenant_id', $user->tenant_id)
            ->findOrFail($checklist);

        $validated = $request->validate([
            'role' => ['nullable', 'string'],
            'shift' => ['nullable', 'string'],
            'tasks' => ['nullable', 'array'],
            'tasks.*.label' => ['required_with:tasks', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);

        if (isset($validated['tasks'])) {
            $validated['tasks'] = $this->normalizeTasks($validated['tasks']);
        }

        $template->update(array_filter($validated, fn ($v) => $v !== null));

        return response()->json(['data' => $template->fresh()]);
    }

    /**
     * Delete a checklist template.
     */
    public function destroy(Request $request, $checklist): JsonResponse
    {
        $this->ensureFeatureEnabled($request);

        $user = $request->user();
        $template = ChecklistTemplate::where('tenant_id', $user->tenant_id)
            ->findOrFail($checklist);

        $template->delete();

        return response()->json(['message' => 'Checklist template deleted'], 200);
    }

    public function today(Request $request, ChecklistInstanceRepository $repository): JsonResponse
    {
        $this->ensureFeatureEnabled($request);

        $user = $request->user();

        $role = $request->filled('role')
            ? ChecklistRole::canonical((string) $request->input('role'))
            : null;

        $shift = trim((string) $request->input('shift', 'Daily'));
        $shift = $shift === '' ? 'Daily' : $shift;

        $query = ChecklistInstance::query()
            ->with(['items', 'template'])
            ->where('tenant_id', $user->tenant_id)
            ->whereDate('date', now()->timezone('Asia/Kolkata')->toDateString());

        if ($role) {
            $query->where('role', $role);
        }

        $query->where('shift', $shift);

        if (! $user->hasAnyRole(['Campus Manager', 'Rector', 'Super Admin'])) {
            $query->where('assignee_user_id', $user->id);
        }

        $instances = $query->get();

        // On-demand creation: if instance is missing and a role filter is provided, create from active template.
        // This makes mobile usable even if the scheduler/job isn't running.
        if (
            $instances->isEmpty()
            && $role
            && ! $user->hasAnyRole(['Campus Manager', 'Rector', 'Super Admin'])
        ) {
            $template = ChecklistTemplate::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('role', $role)
                ->where('active', true)
                ->latest('id')
                ->first();

            if ($template) {
                $tasks = $this->normalizeTasks(is_array($template->tasks) ? $template->tasks : []);
                $todayIst = Carbon::now('Asia/Kolkata')->startOfDay();

                $repository->firstOrCreateDaily(
                    tenantId: $user->tenant_id,
                    templateId: (int) $template->id,
                    role: $template->role,
                    assigneeUserId: (int) $user->id,
                    dateIst: $todayIst,
                    shift: $shift,
                    tasks: $tasks,
                );

                $instances = $query->get();
            }
        }

        $instances = $instances->filter(
            fn (ChecklistInstance $instance) => app(ChecklistPolicy::class)->view($user, $instance)
        );

        return response()->json($instances->values());
    }

    public function markItem(MarkItemRequest $request, ChecklistInstance $instance, string $code): JsonResponse
    {
        $this->ensureFeatureEnabled($request);

        $this->authorize('mark', $instance);

        $item = $instance->items()->where('code', $code)->firstOrFail();

        $data = $request->validated();

        // Enforce requirements before marking as Done.
        if (($data['state'] ?? null) === 'Done') {
            $hasPhoto = ! empty(($data['photo_urls'] ?? null)) || ! empty(($item->photo_urls ?? null));
            $hasComment = ! empty(trim((string) ($data['comment'] ?? $item->comment ?? '')));

            if (($item->require_photo ?? false) && ! $hasPhoto) {
                throw ValidationException::withMessages([
                    'photo_urls' => ['Photo is required to complete this item.'],
                ]);
            }

            if (($item->require_comment ?? false) && ! $hasComment) {
                throw ValidationException::withMessages([
                    'comment' => ['Comment is required to complete this item.'],
                ]);
            }
        }

        $item->forceFill([
            'state' => $data['state'],
            'comment' => $data['comment'] ?? null,
            'photo_urls' => $data['photo_urls'] ?? null,
            'completed_at' => $data['state'] === 'Done' ? now() : null,
        ])->save();

        $instance->recalcCompleted();

        $this->auditLogger->log('checklist.item_marked', $instance, [
            'item_code' => $item->code,
            'state' => $item->state,
        ]);

        return response()->json([
            'status' => 'ok',
            'completed_tasks' => $instance->completed_tasks,
            'total_tasks' => $instance->total_tasks,
        ]);
    }

    public function submit(SubmitChecklistRequest $request, ChecklistInstance $instance): JsonResponse
    {
        $this->ensureFeatureEnabled($request);

        $this->authorize('submit', $instance);

        if ($instance->status !== 'Pending') {
            throw ValidationException::withMessages([
                'status' => ['Checklist already submitted.'],
            ]);
        }

        $instance->forceFill([
            'status' => 'Submitted',
            'submitted_at' => now(),
        ])->save();

        $this->auditLogger->log('checklist.submitted', $instance, []);

        return response()->json([
            'status' => 'Submitted',
            'submitted_at' => $instance->submitted_at,
        ]);
    }

    public function approve(Request $request, ChecklistInstance $instance): JsonResponse
    {
        $this->ensureFeatureEnabled($request);

        $this->authorize('approve', $instance);

        $instance->forceFill([
            'review_status' => 'Approved',
            'reviewed_at' => now(),
            'manager_user_id' => $request->user()->id,
            'manager_note' => null,
        ])->save();

        $this->auditLogger->log('checklist.approved', $instance, [
            'instance_id' => $instance->id,
        ]);

        return response()->json([
            'review_status' => $instance->review_status,
            'reviewed_at' => $instance->reviewed_at,
            'manager_user_id' => $instance->manager_user_id,
        ]);
    }

    public function sendBack(Request $request, ChecklistInstance $instance): JsonResponse
    {
        $this->ensureFeatureEnabled($request);

        $this->authorize('sendBack', $instance);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $note = $validated['note'] ?? null;

        $instance->forceFill([
            'review_status' => 'SentBack',
            'reviewed_at' => now(),
            'manager_user_id' => $request->user()->id,
            'manager_note' => $note,
        ])->save();

        $this->auditLogger->log('checklist.sent_back', $instance, [
            'instance_id' => $instance->id,
            'note' => $note,
        ]);

        $this->notifier->sentBack($instance, $note);

        return response()->json([
            'review_status' => $instance->review_status,
            'manager_note' => $instance->manager_note,
            'reviewed_at' => $instance->reviewed_at,
        ]);
    }

    public function uploadPhoto(Request $request, ChecklistInstance $instance, string $code): JsonResponse
    {
        $this->ensureFeatureEnabled($request);

        $this->authorize('mark', $instance);

        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:5120'], // 5MB
        ]);

        $item = $instance->items()->where('code', $code)->firstOrFail();

        $path = $request->file('photo')->store(
            "checklists/{$instance->tenant_id}/{$instance->id}",
            'public'
        );

        $photoUrl = Storage::url($path);

        $photos = $item->photo_urls ?? [];
        $photos[] = $photoUrl;

        $item->forceFill(['photo_urls' => $photos])->save();

        return response()->json([
            'status' => 'ok',
            'photo_url' => $photoUrl,
        ]);
    }

    private function ensureFeatureEnabled(Request $request): void
    {
        $enabled = app(FeatureFlagsService::class)->enabled('checklists_module', $request->user()?->tenant_id);

        if (! $enabled) {
            abort(404);
        }
    }

    /**
     * Normalize tasks to ensure each has code and label.
     *
     * @param  array<int, array<string, mixed>>  $rawTasks
     * @return array<int, array{code: string, label: string, require_photo?: bool, require_comment?: bool}>
     */
    private function normalizeTasks(array $rawTasks): array
    {
        $normalized = [];

        foreach ($rawTasks as $index => $task) {
            if (isset($task['code']) && isset($task['label'])) {
                $normalized[] = [
                    'code' => (string) $task['code'],
                    'label' => (string) $task['label'],
                    'require_photo' => (bool) ($task['require_photo'] ?? false),
                    'require_comment' => (bool) ($task['require_comment'] ?? false),
                ];
                continue;
            }

            $label = (string) ($task['label'] ?? $task['title'] ?? ("Task " . ($index + 1)));
            $code = (string) ($task['code'] ?? $this->generateCodeFromLabel($label, (int) $index));

            $normalized[] = [
                'code' => $code,
                'label' => $label,
                'require_photo' => (bool) ($task['require_photo'] ?? false),
                'require_comment' => (bool) ($task['require_comment'] ?? false),
            ];
        }

        return $normalized;
    }

    private function generateCodeFromLabel(string $label, int $index): string
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $label) ?? '');
        $code = trim($code, '_');
        $code = substr($code, 0, 30);

        if ($code === '') {
            $code = 'TASK_' . ($index + 1);
        }

        return $code;
    }
}

