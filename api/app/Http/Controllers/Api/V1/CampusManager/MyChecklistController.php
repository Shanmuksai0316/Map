<?php

namespace App\Http\Controllers\Api\V1\CampusManager;

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\Checklists\Models\ChecklistItem;
use App\Domain\Checklists\Models\ChecklistTemplate;
use App\Domain\Checklists\Repositories\ChecklistInstanceRepository;
use App\Domain\Checklists\Support\ChecklistRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves "My Checklist" for Campus Manager in the mobile app.
 * Returns the checklist instance assigned to the current user (assignee_user_id) for today,
 * in the same shape as Guard checklist so the app can reuse the UI.
 */
class MyChecklistController extends Controller
{
    /**
     * Get today's checklist assigned to the current user (Campus Manager).
     * Same response shape as Guard ChecklistController::current().
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $instance = $this->resolveOrCreateTodayInstance($request, app(ChecklistInstanceRepository::class));

        if (! $instance) {
            return response()->json([
                'data' => null,
                'message' => 'No checklist assigned for today',
            ]);
        }

        if (! Gate::forUser($user)->allows('view', $instance)) {
            return response()->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $tasks = $this->mapItemsToTasks($instance->items()->orderBy('id')->get());

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
     * Submit the current user's today checklist.
     */
    public function submit(Request $request): JsonResponse
    {
        $instance = $this->resolveMyCurrentInstance($request);
        if ($instance instanceof JsonResponse) {
            return $instance;
        }

        $this->authorize('submit', $instance);

        if ($instance->status !== 'Pending') {
            return response()->json([
                'error' => 'Checklist already submitted.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $instance->forceFill([
            'status' => 'Submitted',
            'submitted_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Checklist submitted',
            'data' => [
                'submission_id' => $instance->id,
                'submitted_at' => $instance->submitted_at,
            ],
        ]);
    }

    /**
     * Mark a task complete by index (0-based).
     */
    public function completeTask(Request $request, int $taskIndex): JsonResponse
    {
        $instance = $this->resolveMyCurrentInstance($request);
        if ($instance instanceof JsonResponse) {
            return $instance;
        }

        $this->authorize('mark', $instance);

        $item = $instance->items()->orderBy('id')->skip($taskIndex)->take(1)->first();
        if (! $item) {
            return response()->json(['error' => 'Task not found'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'comment' => 'nullable|string|max:500',
            'photo_url' => 'nullable|string',
        ]);

        $incomingComment = $validated['comment'] ?? null;
        $incomingPhotoUrl = $validated['photo_url'] ?? null;
        $existingPhotos = $item->photo_urls ?? [];
        $hasPhoto = ! empty($incomingPhotoUrl) || ! empty($existingPhotos);
        $hasComment = ! empty(trim((string) ($incomingComment ?? $item->comment ?? '')));

        if (($item->require_photo ?? false) && ! $hasPhoto) {
            return response()->json([
                'error' => 'Photo is required to complete this task',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (($item->require_comment ?? false) && ! $hasComment) {
            return response()->json([
                'error' => 'Comment is required to complete this task',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $item->forceFill([
            'state' => 'Done',
            'comment' => $incomingComment ?? $item->comment,
            'photo_urls' => $incomingPhotoUrl ? array_merge($existingPhotos, [$incomingPhotoUrl]) : $existingPhotos,
            'completed_at' => now(),
        ])->save();

        $instance->recalcCompleted();

        $tasks = $this->mapItemsToTasks($instance->items()->orderBy('id')->get());

        return response()->json([
            'message' => 'Task completed',
            'data' => [
                'task_index' => $taskIndex,
                'completed_at' => $item->completed_at,
                'checklist_status' => $instance->fresh()->status,
                'completed_count' => collect($tasks)->filter(fn ($t) => $t['completed'])->count(),
                'total_count' => count($tasks),
            ],
        ]);
    }

    /**
     * Upload photo for a task by index (0-based).
     */
    public function uploadPhoto(Request $request, int $taskIndex): JsonResponse
    {
        $instance = $this->resolveMyCurrentInstance($request);
        if ($instance instanceof JsonResponse) {
            return $instance;
        }

        $this->authorize('mark', $instance);

        $item = $instance->items()->orderBy('id')->skip($taskIndex)->take(1)->first();
        if (! $item) {
            return response()->json(['error' => 'Task not found'], Response::HTTP_NOT_FOUND);
        }

        $request->validate(['photo' => 'required|image|max:5120']);

        $path = $request->file('photo')->store(
            "checklists/{$instance->tenant_id}/{$instance->id}",
            'public'
        );
        $photoUrl = Storage::url($path);

        $photos = $item->photo_urls ?? [];
        $photos[] = $photoUrl;
        $item->forceFill(['photo_urls' => $photos])->save();

        return response()->json([
            'message' => 'Photo uploaded',
            'data' => [
                'task_index' => $taskIndex,
                'photo_url' => $photoUrl,
            ],
        ]);
    }

    private function resolveMyCurrentInstance(Request $request): ChecklistInstance|JsonResponse
    {
        $instance = $this->resolveOrCreateTodayInstance($request, app(ChecklistInstanceRepository::class));

        if (! $instance) {
            return response()->json([
                'error' => 'No checklist assigned for today',
            ], Response::HTTP_NOT_FOUND);
        }

        return $instance;
    }

    private function mapItemsToTasks($items): array
    {
        return $items->values()
            ->map(function (ChecklistItem $item, int $index) {
                $photoUrls = $item->photo_urls ?? [];
                return [
                    'index' => $index,
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

    private function resolveOrCreateTodayInstance(Request $request, ChecklistInstanceRepository $repository): ?ChecklistInstance
    {
        $user = $request->user();
        $role = ChecklistRole::canonical('CampusManager');

        $template = ChecklistTemplate::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('role', $role)
            ->where('active', true)
            ->first();

        if (! $template) {
            return null;
        }

        $rawTasks = is_array($template->tasks) ? $template->tasks : [];
        if ($rawTasks === []) {
            $rawTasks = ChecklistRole::defaultTasksForRole($role);
            $template->forceFill([
                'tasks' => $rawTasks,
                'active' => true,
            ])->save();
        }

        $tasks = $this->normalizeTasks($rawTasks);
        $todayIst = Carbon::now('Asia/Kolkata')->startOfDay();

        $instance = $repository->firstOrCreateDaily(
            tenantId: $user->tenant_id,
            templateId: $template->id,
            role: $role,
            assigneeUserId: $user->id,
            dateIst: $todayIst,
            shift: 'Daily',
            tasks: $tasks
        );

        // Older records may exist without checklist items; backfill from template once.
        if ($instance->items()->count() === 0 && $tasks !== []) {
            DB::transaction(function () use ($instance, $tasks): void {
                $now = now();
                $rows = [];

                foreach ($tasks as $task) {
                    $rows[] = [
                        'tenant_id' => $instance->tenant_id,
                        'instance_id' => $instance->id,
                        'code' => $task['code'],
                        'label' => $task['label'],
                        'require_photo' => (bool) ($task['require_photo'] ?? false),
                        'require_comment' => (bool) ($task['require_comment'] ?? false),
                        'state' => 'Pending',
                        'comment' => null,
                        'photo_urls' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                ChecklistItem::query()->insert($rows);
                $instance->forceFill([
                    'total_tasks' => count($rows),
                    'completed_tasks' => 0,
                ])->save();
            });
        }

        return $instance->load(['items', 'template']);
    }

    /**
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
