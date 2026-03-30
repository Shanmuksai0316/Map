<?php

namespace App\Http\Controllers\Api\V1\Guard;

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\Checklists\Models\ChecklistItem;
use App\Domain\Checklists\Models\ChecklistTemplate;
use App\Domain\Checklists\Repositories\ChecklistInstanceRepository;
use App\Domain\Checklists\Support\ChecklistRole;
use App\Http\Controllers\Controller;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Leave;
use App\Services\FeatureFlagsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checklist Controller for Security Guards
 *
 * Uses the shared checklist domain models to serve guard-specific flows.
 */
class ChecklistController extends Controller
{
    public function __construct(
        private readonly FeatureFlagsService $featureFlags
    ) {}

    private function findChecklistItem(ChecklistInstance $instance, int $taskIdentifier): ?ChecklistItem
    {
        $byId = $instance->items()->where('id', $taskIdentifier)->first();
        if ($byId) {
            return $byId;
        }

        return $instance->items()
            ->orderBy('id')
            ->skip(max(0, $taskIdentifier))
            ->take(1)
            ->first();
    }

    /**
     * List guard templates (active only).
     */
    public function index(Request $request): JsonResponse
    {
        $this->ensureEnabled($request);

        $user = $request->user();

        $templates = ChecklistTemplate::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('role', 'Guard')
            ->where('active', true)
            ->get()
            ->map(fn (ChecklistTemplate $template) => [
                'id' => $template->id,
                'title' => $template->title,
                'tasks' => $template->tasks ?? [],
            ]);

        return response()->json(['data' => $templates]);
    }

    /**
     * Get (or create) today's checklist for the guard.
     */
    public function current(Request $request, ChecklistInstanceRepository $repository): JsonResponse
    {
        $this->ensureEnabled($request);

        $instance = $this->getOrCreatePersistentInstance($request, $repository);

        if (! $instance) {
            return response()->json([
                'data' => null,
                'message' => 'No checklist assigned for today',
            ]);
        }

        $tasks = $this->mapItemsToTasks($instance->items);

        return response()->json([
            'data' => [
                'id' => $instance->id,
                'template_name' => $instance->template?->title ?? 'Daily Checklist',
                'due_at' => $instance->date?->toDateString(),
                'status' => $instance->status,
                'tasks' => $tasks,
                'completed_count' => collect($tasks)->filter(fn ($t) => $t['completed'])->count(),
                'total_count' => count($tasks),
            ],
        ]);
    }

    /**
     * Mark a task done (by index).
     */
    public function completeTask(Request $request, int $taskIndex): JsonResponse
    {
        $this->ensureEnabled($request);

        $validated = $request->validate([
            'comment' => 'nullable|string|max:500',
            'photo_url' => 'nullable|string',
        ]);

        $instance = $this->requirePersistentInstance($request, app(ChecklistInstanceRepository::class));

        $item = $this->findChecklistItem($instance, $taskIndex);

        if (! $item) {
            return response()->json([
                'error' => 'Task not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Enforce requirements (photo/comment) before allowing completion.
        $incomingComment = $validated['comment'] ?? null;
        $incomingPhotoUrl = $validated['photo_url'] ?? null;
        $existingPhotos = $item->photo_urls ?? [];
        $hasPhoto = ! empty($incomingPhotoUrl) || ! empty($existingPhotos);
        $hasComment = ! empty(trim((string) ($incomingComment ?? $item->comment ?? '')));

        if (($item->require_photo ?? false) && ! $hasPhoto) {
            return response()->json([
                'error' => 'photo_required',
                'message' => 'Photo is required to complete this task',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (($item->require_comment ?? false) && ! $hasComment) {
            return response()->json([
                'error' => 'comment_required',
                'message' => 'Comment is required to complete this task',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $photos = $item->photo_urls ?? [];
        if (! empty($validated['photo_url'])) {
            $photos[] = $validated['photo_url'];
        }

        $item->forceFill([
            'state' => 'Done',
            'comment' => $validated['comment'] ?? $item->comment,
            'photo_urls' => $photos ?: null,
            'completed_at' => now(),
        ])->save();

        $completedCount = $instance->items()->where('state', 'Done')->count();
        $instance->forceFill(['completed_tasks' => $completedCount])->save();

        $allCompleted = $completedCount >= ($instance->total_tasks ?? $instance->items()->count());

        return response()->json([
            'message' => 'Task completed',
            'data' => [
                'task_index' => $taskIndex,
                'completed_at' => $item->completed_at,
                'checklist_status' => $allCompleted ? 'completed' : 'in_progress',
            ],
        ]);
    }

    /**
     * Upload a photo for a task (by index).
     */
    public function uploadPhoto(Request $request, int $taskIndex): JsonResponse
    {
        $this->ensureEnabled($request);

        $request->validate([
            'photo' => 'required|image|max:5120', // 5MB max
        ]);

        $instance = $this->requirePersistentInstance($request, app(ChecklistInstanceRepository::class));

        $item = $this->findChecklistItem($instance, $taskIndex);

        if (! $item) {
            return response()->json([
                'error' => 'Task not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $path = $request->file('photo')->store(
            "checklists/{$instance->tenant_id}/{$instance->id}",
            'public'
        );

        $photoUrl = Storage::url($path);

        $photos = $item->photo_urls ?? [];
        $photos[] = $photoUrl;

        $item->forceFill([
            'photo_urls' => $photos,
        ])->save();

        return response()->json([
            'message' => 'Photo uploaded',
            'data' => [
                'task_index' => $taskIndex,
                'photo_url' => $photoUrl,
            ],
        ]);
    }

    /**
     * Submit the checklist "form".
     *
     * Creates a submission snapshot for Campus Manager review
     * and resets the persistent instance for the next submission.
     */
    public function submit(Request $request, ChecklistInstanceRepository $repository): JsonResponse
    {
        $this->ensureEnabled($request);

        $user = $request->user();

        return DB::transaction(function () use ($request, $repository, $user) {
            $persistent = $this->requirePersistentInstance($request, $repository)->load(['items', 'template']);

            $items = $persistent->items()->orderBy('id')->get();
            $total = $items->count();
            $completed = $items->where('state', 'Done')->count();

            if ($total === 0) {
                return response()->json([
                    'error' => 'no_tasks',
                    'message' => 'No checklist fields configured',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($completed < $total) {
                return response()->json([
                    'error' => 'incomplete',
                    'message' => 'Please complete all fields before submitting',
                    'data' => [
                        'completed' => $completed,
                        'total' => $total,
                    ],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $nowIst = Carbon::now('Asia/Kolkata');

            // Create a submission instance snapshot (this is what Campus Manager sees).
            $submission = ChecklistInstance::query()->create([
                'tenant_id' => $user->tenant_id,
                'template_id' => $persistent->template_id,
                'date' => $nowIst->toDateString(),
                'shift' => 'Submission',
                'role' => 'Guard',
                'assignee_user_id' => $user->id,
                'status' => 'Submitted',
                'review_status' => 'Pending',
                'total_tasks' => $total,
                'completed_tasks' => $completed,
                'submitted_at' => $nowIst,
                'completed_at' => $nowIst,
            ]);

            $rows = [];
            foreach ($items as $item) {
                $rows[] = [
                    'tenant_id' => $user->tenant_id,
                    'instance_id' => $submission->id,
                    'code' => $item->code,
                    'label' => $item->label,
                    'require_photo' => (bool) ($item->require_photo ?? false),
                    'require_comment' => (bool) ($item->require_comment ?? false),
                    'state' => $item->state,
                    'comment' => $item->comment,
                    'photo_urls' => $item->photo_urls,
                    'completed_at' => $item->completed_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($rows !== []) {
                ChecklistItem::query()->insert($rows);
            }

            // Reset the persistent "form" for next submission.
            $persistent->items()->update([
                'state' => 'Pending',
                'comment' => null,
                'photo_urls' => null,
                'completed_at' => null,
                'updated_at' => now(),
            ]);
            $persistent->forceFill([
                'status' => 'Pending',
                'completed_tasks' => 0,
                'submitted_at' => null,
                'completed_at' => null,
            ])->save();

            return response()->json([
                'message' => 'Checklist submitted',
                'data' => [
                    'submission_id' => $submission->id,
                    'submitted_at' => $submission->submitted_at,
                ],
            ]);
        }, 3);
    }

    /**
     * Paginated history for the guard.
     */
    public function history(Request $request): JsonResponse
    {
        $this->ensureEnabled($request);

        $user = $request->user();

        $history = ChecklistInstance::query()
            ->with('template')
            ->where('tenant_id', $user->tenant_id)
            ->where('assignee_user_id', $user->id)
            ->orderByDesc('date')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $history->getCollection()->map(function (ChecklistInstance $instance) {
                $tasksCompleted = $instance->items()->where('state', 'Done')->count();
                $tasksTotal = $instance->items()->count();

                return [
                    'id' => $instance->id,
                    'template_name' => $instance->template?->title ?? 'Daily Checklist',
                    'due_at' => $instance->date,
                    'status' => $instance->status,
                    'completed_at' => $instance->completed_at,
                    'tasks_completed' => $tasksCompleted,
                    'tasks_total' => $tasksTotal,
                ];
            }),
            'meta' => [
                'current_page' => $history->currentPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
            ],
        ]);
    }

    /**
     * Verify time for gate entry/exit (existing behavior).
     */
    public function verifyTime(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'type' => 'required|in:outpass,leave',
            'id' => 'required|integer',
            'direction' => 'required|in:out,in',
            'timestamp' => 'nullable|date',
        ]);

        $timestamp = $validated['timestamp'] ?? now();

        DB::beginTransaction();
        try {
            if ($validated['type'] === 'outpass') {
                $outpass = OutPass::where('tenant_id', $user->tenant_id)
                    ->findOrFail($validated['id']);

                if ($validated['direction'] === 'out') {
                    $outpass->update([
                        'actual_out_time' => $timestamp,
                        'verified_out_by' => $user->id,
                    ]);
                } else {
                    $outpass->update([
                        'actual_in_time' => $timestamp,
                        'verified_in_by' => $user->id,
                        'returned_at' => $timestamp,
                    ]);
                }

                DB::table('gate_entries')->insert([
                    'tenant_id' => $user->tenant_id,
                    'student_id' => $outpass->student_id,
                    'outpass_id' => $outpass->id,
                    'hostel_id' => $outpass->hostel_id,
                    'direction' => $validated['direction'],
                    $validated['direction'] === 'out' ? 'out_time' : 'in_time' => $timestamp,
                    'recorded_by_user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            } else {
                $leave = Leave::where('tenant_id', $user->tenant_id)
                    ->findOrFail($validated['id']);

                if ($validated['direction'] === 'out') {
                    $leave->update([
                        'actual_departure_time' => $timestamp,
                        'verified_out_by' => $user->id,
                    ]);
                } else {
                    $leave->update([
                        'actual_return_time' => $timestamp,
                        'verified_in_by' => $user->id,
                    ]);
                }

                DB::table('gate_entries')->insert([
                    'tenant_id' => $user->tenant_id,
                    'student_id' => $leave->student_id,
                    'leave_id' => $leave->id,
                    'hostel_id' => $leave->hostel_id,
                    'direction' => $validated['direction'],
                    $validated['direction'] === 'out' ? 'out_time' : 'in_time' => $timestamp,
                    'recorded_by_user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Time verified successfully',
                'data' => [
                    'type' => $validated['type'],
                    'id' => $validated['id'],
                    'direction' => $validated['direction'],
                    'timestamp' => $timestamp,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to verify time: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Verify gate time (alias for verifyTime)
     */
    public function verifyGateTime(Request $request): JsonResponse
    {
        return $this->verifyTime($request);
    }

    private function ensureEnabled(Request $request): void
    {
        if (! $this->featureFlags->enabled('checklists_module', $request->user()?->tenant_id)) {
            abort(404);
        }
    }

    private function requirePersistentInstance(Request $request, ChecklistInstanceRepository $repository): ChecklistInstance
    {
        $instance = $this->getOrCreatePersistentInstance($request, $repository);
        if (! $instance) {
            abort(404);
        }
        return $instance;
    }

    private function getOrCreatePersistentInstance(Request $request, ChecklistInstanceRepository $repository): ?ChecklistInstance
    {
        $user = $request->user();
        // Persistent checklist: one instance reused (does not reset daily).
        // We key it by a fixed date + fixed shift.
        $fixedDateIst = Carbon::create(2000, 1, 1, 0, 0, 0, 'Asia/Kolkata')->startOfDay();

        $template = ChecklistTemplate::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('role', 'Guard')
            ->where('active', true)
            ->first();

        if (! $template) {
            return null;
        }

        $rawTasks = is_array($template->tasks) ? $template->tasks : [];
        if ($rawTasks === []) {
            $rawTasks = ChecklistRole::defaultTasksForRole('Guard');
            $template->forceFill([
                'tasks' => $rawTasks,
                'active' => true,
            ])->save();
        }

        $tasks = $this->normalizeTasks($rawTasks);

        $instance = $repository->firstOrCreateDaily(
            tenantId: $user->tenant_id,
            templateId: $template->id,
            role: 'Guard',
            assigneeUserId: $user->id,
            dateIst: $fixedDateIst,
            shift: 'Persistent',
            tasks: $tasks
        );

        // Keep items in sync with template fields:
        // - Insert any missing tasks (newly added in Campus Manager)
        // - Update label/requirements for existing tasks by code
        $this->syncInstanceItemsFromTemplate($instance, $tasks);

        return $instance->load(['items', 'template']);
    }

    /**
     * Ensure all template tasks are visible on the persistent instance.
     *
     * @param  array<int, array{code:string,label:string,require_photo?:bool,require_comment?:bool}>  $tasks
     */
    private function syncInstanceItemsFromTemplate(ChecklistInstance $instance, array $tasks): void
    {
        if ($tasks === []) {
            return;
        }

        $existing = $instance->items()->get()->keyBy('code');
        $now = now();

        $toInsert = [];
        foreach ($tasks as $task) {
            $code = (string) ($task['code'] ?? '');
            $label = (string) ($task['label'] ?? '');
            if ($code === '' || $label === '') {
                continue;
            }

            $requirePhoto = (bool) ($task['require_photo'] ?? false);
            $requireComment = (bool) ($task['require_comment'] ?? false);

            /** @var ChecklistItem|null $item */
            $item = $existing->get($code);
            if (! $item) {
                $toInsert[] = [
                    'tenant_id' => $instance->tenant_id,
                    'instance_id' => $instance->id,
                    'code' => $code,
                    'label' => $label,
                    'require_photo' => $requirePhoto,
                    'require_comment' => $requireComment,
                    'state' => 'Pending',
                    'comment' => null,
                    'photo_urls' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                continue;
            }

            // Keep metadata aligned with template without touching completion state/comments/photos.
            if (
                $item->label !== $label ||
                (bool) $item->require_photo !== $requirePhoto ||
                (bool) $item->require_comment !== $requireComment
            ) {
                $item->forceFill([
                    'label' => $label,
                    'require_photo' => $requirePhoto,
                    'require_comment' => $requireComment,
                ])->save();
            }
        }

        if ($toInsert !== []) {
            ChecklistItem::query()->insert($toInsert);
        }

        // Recalculate totals.
        $instance->forceFill([
            'total_tasks' => $instance->items()->count(),
            'completed_tasks' => $instance->items()->where('state', 'Done')->count(),
        ])->save();
    }

    private function mapItemsToTasks($items): array
    {
        return collect($items)
            ->values()
            ->map(function (ChecklistItem $item, int $index) {
                $photoUrls = $item->photo_urls ?? [];

                return [
                    'index' => $index,
                    // Mobile apps expect a stable numeric id; for guard flows we keep id=index
                    'id' => $index,
                    'title' => $item->label,
                    'description' => null,
                    'requires_photo' => (bool) ($item->require_photo ?? false),
                    'requires_comment' => (bool) ($item->require_comment ?? false),
                    'completed' => $item->state === 'Done',
                    'is_completed' => $item->state === 'Done',
                    'completed_at' => $item->completed_at,
                    'photo_url' => $photoUrls[0] ?? null,
                    'comment' => $item->comment,
                ];
            })
            ->all();
    }

    /**
     * Normalize tasks to ensure each entry has code/label/require flags.
     *
     * @param  array<int, mixed>  $rawTasks
     * @return array<int, array{code:string,label:string,require_photo?:bool,require_comment?:bool}>
     */
    private function normalizeTasks(array $rawTasks): array
    {
        $normalized = [];

        foreach ($rawTasks as $index => $task) {
            if (is_string($task)) {
                $label = $task;
                $code = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $label));
                $code = trim(substr($code, 0, 30), '_');
                if ($code === '') {
                    $code = 'TASK_' . ($index + 1);
                }

                $normalized[] = [
                    'code' => $code,
                    'label' => $label,
                    'require_photo' => false,
                    'require_comment' => false,
                ];
                continue;
            }

            if (! is_array($task)) {
                continue;
            }

            if (isset($task['code'], $task['label'])) {
                $normalized[] = [
                    'code' => (string) $task['code'],
                    'label' => (string) $task['label'],
                    'require_photo' => (bool) ($task['require_photo'] ?? false),
                    'require_comment' => (bool) ($task['require_comment'] ?? false),
                ];
                continue;
            }

            $label = (string) ($task['label'] ?? $task['title'] ?? ('Task ' . ($index + 1)));
            $code = (string) ($task['code'] ?? '');
            if ($code === '') {
                $code = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $label));
                $code = trim(substr($code, 0, 30), '_');
                if ($code === '') {
                    $code = 'TASK_' . ($index + 1);
                }
            }

            $normalized[] = [
                'code' => $code,
                'label' => $label,
                'require_photo' => (bool) ($task['require_photo'] ?? false),
                'require_comment' => (bool) ($task['require_comment'] ?? false),
            ];
        }

        return $normalized;
    }
}
