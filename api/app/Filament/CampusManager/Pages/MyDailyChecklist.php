<?php

namespace App\Filament\CampusManager\Pages;

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\Checklists\Models\ChecklistItem;
use App\Domain\Checklists\Models\ChecklistTemplate;
use App\Domain\Checklists\Support\ChecklistRole;
use App\Services\FeatureFlagsService;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MyDailyChecklist extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'My Daily Checklist';

    protected static ?string $navigationGroup = 'Checklist';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.campus-manager.pages.my-daily-checklist';

    public ?ChecklistInstance $todayChecklist = null;

    public ?ChecklistTemplate $template = null;

    public array $completedItems = [];

    public function mount(): void
    {
        $user = Auth::user();
        $tenantId = $user?->tenant_id;
        $role = ChecklistRole::canonical('CampusManager');
        $defaultTasks = [
            ['code' => 'CHECK_WATER', 'label' => 'Check water tanker', 'require_photo' => false, 'require_comment' => false],
            ['code' => 'CHECK_HYGIENE', 'label' => 'Check hygiene & washrooms', 'require_photo' => false, 'require_comment' => false],
            ['code' => 'CHECK_LIGHTS', 'label' => 'Check lights in corridors', 'require_photo' => false, 'require_comment' => false],
            ['code' => 'CHECK_SECURITY', 'label' => 'Check security post log book', 'require_photo' => false, 'require_comment' => false],
            ['code' => 'CHECK_NOTICES', 'label' => 'Check new notices / announcements', 'require_photo' => false, 'require_comment' => false],
        ];
        
        // Get template (tenant-scoped)
        $this->template = ChecklistTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where('role', $role)
            ->where('active', true)
            ->first();

        if (! $this->template) {
            // No template configured yet; Checklist Configuration page will create it on demand.
            $this->todayChecklist = null;
            return;
        }

        // If template exists but has no tasks, auto-seed defaults to avoid blank UI.
        $templateTasks = is_array($this->template->tasks) ? $this->template->tasks : [];
        if (count($templateTasks) === 0) {
            $this->template->forceFill(['tasks' => $defaultTasks, 'active' => true])->save();
            $this->template->refresh();
        }

        // Get or create today's instance for this Campus Manager user.
        $this->todayChecklist = DB::transaction(function () use ($tenantId, $role, $user) {
            $existing = ChecklistInstance::query()
                ->with('items')
                ->where('tenant_id', $tenantId)
                ->where('template_id', $this->template->id)
                ->whereDate('date', today())
                ->where('shift', 'Daily')
                ->where('role', $role)
                ->where('assignee_user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $tasks = is_array($this->template->tasks) ? $this->template->tasks : [];
            $tasks = array_slice($tasks, 0, 10);

            $instance = ChecklistInstance::query()->create([
                'tenant_id' => $tenantId,
                'template_id' => $this->template->id,
                'date' => today(),
                'shift' => 'Daily',
                'role' => $role,
                'assignee_user_id' => $user->id,
                'status' => 'Pending',
                'review_status' => 'Pending',
                'total_tasks' => count($tasks),
                'completed_tasks' => 0,
            ]);

            $now = now();
            $rows = [];
            foreach ($tasks as $t) {
                if (!is_array($t)) {
                    continue;
                }
                $code = (string) ($t['code'] ?? '');
                $label = (string) ($t['label'] ?? '');
                if ($code === '' || $label === '') {
                    continue;
                }
                $rows[] = [
                    'tenant_id' => $tenantId,
                    'instance_id' => $instance->id,
                    'code' => $code,
                    'label' => $label,
                    'require_photo' => (bool) ($t['require_photo'] ?? false),
                    'require_comment' => (bool) ($t['require_comment'] ?? false),
                    'state' => 'Pending',
                    'comment' => null,
                    'photo_urls' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($rows) {
                ChecklistItem::query()->insert($rows);
            }

            return $instance->load('items');
        }, 3);

        // Backfill items if an instance exists but has none (e.g., older instances created when tasks were empty).
        if ($this->todayChecklist && $this->todayChecklist->items()->count() === 0) {
            $tasks = is_array($this->template->tasks) ? $this->template->tasks : [];
            $tasks = array_slice($tasks, 0, 10);
            if (count($tasks) > 0) {
                $now = now();
                $rows = [];
                foreach ($tasks as $t) {
                    if (!is_array($t)) {
                        continue;
                    }
                    $code = (string) ($t['code'] ?? '');
                    $label = (string) ($t['label'] ?? '');
                    if ($code === '' || $label === '') {
                        continue;
                    }
                    $rows[] = [
                        'tenant_id' => $tenantId,
                        'instance_id' => $this->todayChecklist->id,
                        'code' => $code,
                        'label' => $label,
                        'require_photo' => (bool) ($t['require_photo'] ?? false),
                        'require_comment' => (bool) ($t['require_comment'] ?? false),
                        'state' => 'Pending',
                        'comment' => null,
                        'photo_urls' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($rows) {
                    ChecklistItem::query()->insert($rows);
                    $this->todayChecklist->forceFill(['total_tasks' => count($rows)])->save();
                }
                $this->todayChecklist->refresh()->load('items');
            }
        }

        $this->syncCompletedItems();
    }

    public function toggleItem(string $itemKey): void
    {
        if (! $this->todayChecklist) {
            return;
        }

        $item = $this->todayChecklist->items()->where('code', $itemKey)->first();
        if (! $item) {
            return;
        }

        $isDone = $item->state === 'Done';
        $item->forceFill([
            'state' => $isDone ? 'Pending' : 'Done',
            'completed_at' => $isDone ? null : now(),
        ])->save();

        $this->todayChecklist->forceFill([
            'completed_tasks' => $this->todayChecklist->items()->where('state', 'Done')->count(),
        ])->save();

        $this->todayChecklist->refresh()->load('items');
        $this->syncCompletedItems();
    }

    public function submit(): void
    {
        if (!$this->todayChecklist) {
            Notification::make()
                ->danger()
                ->title('No checklist available')
                ->send();
            return;
        }

        $totalTasks = (int) ($this->todayChecklist->total_tasks ?? 0);
        
        if (count($this->completedItems) < $totalTasks) {
            Notification::make()
                ->warning()
                ->title('Incomplete Checklist')
                ->body('Please complete all items before submitting.')
                ->send();
            return;
        }

        $this->todayChecklist->update([
            'status' => 'Submitted',
            'submitted_at' => now(),
            'completed_at' => now(),
        ]);

        Notification::make()
            ->success()
            ->title('Checklist Submitted')
            ->body('Your daily checklist has been submitted successfully.')
            ->send();
    }

    public function getHeading(): string
    {
        return 'Campus Manager Daily Routine';
    }

    public function getSubheading(): ?string
    {
        return today()->format('l, d F Y');
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if (!$user->hasAnyRole(['Campus Manager', 'Super Admin'])) {
            return false;
        }

        return app(FeatureFlagsService::class)->enabled('checklists_module', $user->tenant_id);
    }

    public function isAllCompleted(): bool
    {
        if (!$this->todayChecklist) return false;
        $totalTasks = (int) ($this->todayChecklist->total_tasks ?? 0);
        return $totalTasks > 0 && count($this->completedItems) >= $totalTasks;
    }

    public function getChecklistItems(): array
    {
        if (!$this->todayChecklist) {
            return [];
        }

        return $this->todayChecklist->items()
            ->orderBy('id')
            ->get()
            ->map(fn (ChecklistItem $i) => [
                'code' => $i->code,
                'label' => $i->label,
                'state' => $i->state,
            ])
            ->toArray();
    }

    private function syncCompletedItems(): void
    {
        if (! $this->todayChecklist) {
            $this->completedItems = [];
            return;
        }

        $this->completedItems = $this->todayChecklist->items()
            ->where('state', 'Done')
            ->pluck('code')
            ->values()
            ->toArray();
    }
}

