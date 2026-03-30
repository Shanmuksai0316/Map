<?php

namespace App\Services\Checklists;

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\Checklists\Models\ChecklistItem;

/**
 * Sync checklist instance items from template tasks so staff (e.g. warden, guard)
 * always see what was set in the web panel by campus manager.
 */
final class ChecklistInstanceSyncService
{
    /**
     * Ensure instance items match template tasks: add missing, update label/requirements.
     *
     * @param  array<int, array{code:string, label:string, require_photo?:bool, require_comment?:bool}>  $tasks
     */
    public function syncInstanceItemsFromTemplate(ChecklistInstance $instance, array $tasks): void
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

        $instance->forceFill([
            'total_tasks' => $instance->items()->count(),
            'completed_tasks' => $instance->items()->where('state', 'Done')->count(),
        ])->save();
    }

    /**
     * Normalize template tasks to have code, label, require_photo, require_comment.
     *
     * @param  array<int, mixed>  $rawTasks
     * @return array<int, array{code:string, label:string, require_photo?:bool, require_comment?:bool}>
     */
    public function normalizeTasks(array $rawTasks): array
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
