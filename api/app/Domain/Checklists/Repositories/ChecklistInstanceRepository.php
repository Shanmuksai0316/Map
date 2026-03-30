<?php

namespace App\Domain\Checklists\Repositories;

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\Checklists\Models\ChecklistItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class ChecklistInstanceRepository
{
    /**
     * @param  int|string  $tenantId  Tenant ID (int or UUID string)
     * @param  CarbonInterface  $dateIst  Date in desired timezone (e.g. Asia/Kolkata start of day)
     * @param  array<int, array{code:string,label:string,require_photo?:bool,require_comment?:bool}>  $tasks
     */
    public function firstOrCreateDaily(
        int|string $tenantId,
        int $templateId,
        string $role,
        int $assigneeUserId,
        CarbonInterface $dateIst,
        string $shift,
        array $tasks
    ): ChecklistInstance {
        $date = $dateIst->toDateString();
        $shift = trim($shift) === '' ? 'Daily' : $shift;

        return DB::transaction(function () use ($tenantId, $templateId, $role, $assigneeUserId, $date, $shift, $tasks) {
            $existing = ChecklistInstance::query()
                ->where('tenant_id', $tenantId)
                ->where('template_id', $templateId)
                ->whereDate('date', $date)
                ->where('shift', $shift)
                ->where('assignee_user_id', $assigneeUserId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $instance = ChecklistInstance::query()->create([
                'tenant_id' => $tenantId,
                'template_id' => $templateId,
                'role' => $role,
                'assignee_user_id' => $assigneeUserId,
                'date' => $date,
                'shift' => $shift,
                'status' => 'Pending',
                'total_tasks' => count($tasks),
                'completed_tasks' => 0,
            ]);

            if ($tasks !== []) {
                $now = now();
                $rows = [];

                foreach ($tasks as $task) {
                    $rows[] = [
                        'tenant_id' => $tenantId,
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
            }

            return $instance;
        }, 3);
    }
}

