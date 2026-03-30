<?php

namespace App\Jobs;

use App\Domain\Checklists\Models\ChecklistTemplate;
use App\Domain\Checklists\Repositories\ChecklistInstanceRepository;
use App\Domain\Checklists\Support\ChecklistRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Metrics\Metrics;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ChecklistAutoCreateDailyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function tags(): array
    {
        return ['checklist', 'gate'];
    }

    public function handle(ChecklistInstanceRepository $repository): void
    {
        $todayIst = Carbon::now('Asia/Kolkata')->startOfDay();

        Tenant::query()->each(function (Tenant $tenant) use ($todayIst, $repository): void {
            // Set tenant session variable for RLS policies
            \App\Http\Middleware\SetPostgresSessionTenant::setTenantSessionVariable($tenant->id);
            
            try {
                ChecklistTemplate::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('active', true)
                    ->get()
                    ->each(function (ChecklistTemplate $template) use ($tenant, $repository, $todayIst): void {
                        $assignees = $this->resolveAssignees($tenant, $template->role);
                        $rawTasks = is_array($template->tasks) ? $template->tasks : [];

                        // Normalize tasks to ensure code and label exist
                        $tasks = $this->normalizeTasks($rawTasks);

                        foreach ($assignees as $assignee) {
                            $repository->firstOrCreateDaily(
                                tenantId: $tenant->id,
                                templateId: $template->id,
                                role: $template->role,
                                assigneeUserId: $assignee->id,
                                dateIst: $todayIst,
                                shift: 'Daily',
                                tasks: $tasks,
                            );

                            // Send metrics
                            Metrics::count('ChecklistCreated', 1, [
                                'tenant_id' => $tenant->id,
                                'role' => $template->role,
                                'template_id' => $template->id,
                            ]);
                        }
                    });
            } finally {
                // Clear tenant session variable after processing
                \App\Http\Middleware\SetPostgresSessionTenant::clearTenantSessionVariable();
            }
        });
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveAssignees(Tenant $tenant, string $role): Collection
    {
        $canonical = ChecklistRole::canonical($role);
        $spatieNames = ChecklistRole::spatieRoleNames($canonical);

        return User::query()
            ->where('tenant_id', $tenant->id)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $spatieNames))
            ->get();
    }

    /**
     * Normalize tasks to ensure each has code and label.
     * Handles backward compatibility with old format (title only).
     *
     * @param  array<int, array<string, mixed>>  $rawTasks
     * @return array<int, array{code: string, label: string, require_photo?: bool, require_comment?: bool}>
     */
    private function normalizeTasks(array $rawTasks): array
    {
        $normalized = [];

        foreach ($rawTasks as $index => $task) {
            // If task already has code and label, use them
            if (isset($task['code']) && isset($task['label'])) {
                $normalized[] = [
                    'code' => $task['code'],
                    'label' => $task['label'],
                    'require_photo' => $task['require_photo'] ?? false,
                    'require_comment' => $task['require_comment'] ?? false,
                ];

                continue;
            }

            // Backward compatibility: if only 'title' exists, generate code from it
            $label = $task['label'] ?? $task['title'] ?? "Task " . ($index + 1);
            $code = $task['code'] ?? $this->generateCodeFromLabel($label, $index);

            $normalized[] = [
                'code' => $code,
                'label' => $label,
                'require_photo' => $task['require_photo'] ?? false,
                'require_comment' => $task['require_comment'] ?? false,
            ];
        }

        return $normalized;
    }

    /**
     * Generate a code from a label string.
     */
    private function generateCodeFromLabel(string $label, int $index): string
    {
        // Convert to uppercase, replace spaces/special chars with underscore
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $label));

        // Trim underscores and limit length
        $code = trim($code, '_');
        $code = substr($code, 0, 30);

        // Ensure uniqueness by appending index if needed
        if (empty($code)) {
            $code = 'TASK_' . ($index + 1);
        }

        return $code;
    }
}

