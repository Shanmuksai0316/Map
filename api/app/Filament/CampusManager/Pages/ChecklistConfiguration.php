<?php

namespace App\Filament\CampusManager\Pages;

use App\Domain\Checklists\Models\ChecklistTemplate;
use App\Domain\Checklists\Support\ChecklistRole;
use App\Services\FeatureFlagsService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ChecklistConfiguration extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Checklist Configuration';
    protected static ?string $navigationGroup = 'Checklist';
    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.campus-manager.pages.checklist-configuration';

    public string $role = 'Warden';
    public bool $active = true;
    public array $tasks = [];

    private ?ChecklistTemplate $template = null;

    public function mount(): void
    {
        // Ensure all roles have distinct saved defaults for this tenant.
        $this->seedAllRoleTemplates(false);

        $requestedRole = request()->query('role');
        $this->role = ChecklistRole::canonical($requestedRole ?? $this->role);
        $this->loadTemplateForRole($this->role);
        $this->form->fill([
            'role' => $this->role,
            'active' => $this->active,
            'tasks' => $this->tasks,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('role'),

            Forms\Components\Section::make('Select Role')
                ->schema([
                    Forms\Components\View::make('filament.campus-manager.components.checklist-role-picker')
                        ->viewData(fn (): array => [
                            'options' => ChecklistRole::options(),
                            'currentRole' => $this->role,
                        ]),
                ]),

            Forms\Components\Section::make('Checklist Items')
                ->description('One checklist per role. Up to 10 items. Each item can optionally require photo/comment.')
                ->schema([
                    Forms\Components\Repeater::make('tasks')
                        ->key(fn (): string => 'tasks-' . $this->role)
                        ->schema([
                            Forms\Components\TextInput::make('code')
                                ->label('Code')
                                ->required()
                                ->maxLength(50)
                                ->regex('/^[A-Z][A-Z0-9_]*$/')
                                ->helperText('Unique identifier (uppercase, underscores)'),
                            Forms\Components\TextInput::make('label')
                                ->label('Label')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\Toggle::make('require_photo')
                                ->label('Require Photo')
                                ->default(false),
                            Forms\Components\Toggle::make('require_comment')
                                ->label('Require Comment')
                                ->default(false),
                        ])
                        ->columns(2)
                        ->minItems(1)
                        ->maxItems(10)
                        ->collapsible()
                        ->reorderable()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? $state['code'] ?? 'Item'),
                ]),

            Forms\Components\Section::make('Settings')
                ->schema([
                    Forms\Components\Toggle::make('active')
                        ->label('Active')
                        ->default(true),
                ]),
        ]);
    }

    public function selectRole(string $role): void
    {
        $this->role = ChecklistRole::canonical($role);
        $this->loadTemplateForRole($this->role);
        $this->form->fill([
            'role' => $this->role,
            'active' => $this->active,
            'tasks' => $this->tasks,
        ]);
    }

    public function updatedRole(string $value): void
    {
        $this->loadTemplateForRole($value);
        $this->form->fill([
            'role' => $this->role,
            'active' => $this->active,
            'tasks' => $this->tasks,
        ]);
    }

    public function seedAllRoleTemplates(bool $notify = true): void
    {
        $user = auth()->user();
        $tenantId = $user?->tenant_id;

        if (! $tenantId) {
            return;
        }

        $created = 0;
        $updated = 0;

        foreach (ChecklistRole::options() as $roleKey => $label) {
            $defaults = ChecklistRole::defaultTasksForRole($roleKey);

            $template = ChecklistTemplate::query()
                ->where('tenant_id', $tenantId)
                ->where('role', $roleKey)
                ->first();

            if ($template) {
                $template->forceFill([
                    'title' => $roleKey . ' Daily Checklist',
                    'tasks' => $defaults,
                    'active' => true,
                    'created_by_user_id' => $user?->id,
                ])->save();
                $updated++;
            } else {
                ChecklistTemplate::query()->create([
                    'tenant_id' => $tenantId,
                    'role' => $roleKey,
                    'title' => $roleKey . ' Daily Checklist',
                    'tasks' => $defaults,
                    'active' => true,
                    'created_by_user_id' => $user?->id,
                ]);
                $created++;
            }
        }

        $this->loadTemplateForRole($this->role);
        $this->form->fill([
            'role' => $this->role,
            'active' => $this->active,
            'tasks' => $this->tasks,
        ]);

        if ($notify) {
            Notification::make()
                ->success()
                ->title('Defaults applied')
                ->body("Created {$created}, updated {$updated} templates.")
                ->send();
        }
    }

    public function getRoleOptions(): array
    {
        return ChecklistRole::options();
    }

    public function save(): void
    {
        $user = auth()->user();
        $tenantId = $user?->tenant_id;

        $state = $this->form->getState();
        $role = ChecklistRole::canonical($state['role'] ?? $this->role);

        $tasks = is_array($state['tasks'] ?? null) ? $state['tasks'] : [];
        $tasks = array_slice($tasks, 0, 10);

        // De-dupe by code and normalize flags.
        $seen = [];
        $normalized = [];
        foreach ($tasks as $t) {
            $code = (string) ($t['code'] ?? '');
            $label = (string) ($t['label'] ?? '');
            if ($code === '' || $label === '') {
                continue;
            }
            if (isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $normalized[] = [
                'code' => $code,
                'label' => $label,
                'require_photo' => (bool) ($t['require_photo'] ?? false),
                'require_comment' => (bool) ($t['require_comment'] ?? false),
            ];
        }

        // Ensure default sample checklist if none (use role-specific defaults).
        if ($normalized === []) {
            $normalized = ChecklistRole::defaultTasksForRole($role);
        }

        ChecklistTemplate::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'role' => $role,
            ],
            [
                'title' => $role . ' Daily Checklist',
                'tasks' => $normalized,
                'active' => (bool) ($state['active'] ?? true),
                'created_by_user_id' => $user?->id,
            ]
        );

        Notification::make()
            ->success()
            ->title('Checklist saved')
            ->body('Checklist configuration updated for ' . (ChecklistRole::options()[$role] ?? $role) . '.')
            ->send();

        // Reload from DB to show canonical state.
        $this->loadTemplateForRole($role);
        $this->form->fill([
            'role' => $this->role,
            'active' => $this->active,
            'tasks' => $this->tasks,
        ]);
    }

    private function loadTemplateForRole(string $role): void
    {
        $user = auth()->user();
        $tenantId = $user?->tenant_id;

        $role = ChecklistRole::canonical($role);
        $defaultTasks = ChecklistRole::defaultTasksForRole($role);

        $this->template = ChecklistTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where('role', $role)
            ->first();

        if (! $this->template) {
            // Create default sample template on the fly (requirement).
            $this->template = ChecklistTemplate::query()->create([
                'tenant_id' => $tenantId,
                'role' => $role,
                'title' => $role . ' Daily Checklist',
                'tasks' => $defaultTasks,
                'active' => true,
                'created_by_user_id' => $user?->id,
            ]);
        } else {
            // If a template exists but has no tasks (old/bad data), auto-seed role defaults so the UI isn't blank.
            $tasks = is_array($this->template->tasks) ? $this->template->tasks : [];
            if (count($tasks) === 0) {
                $this->template->forceFill([
                    'tasks' => $defaultTasks,
                    'active' => true,
                ])->save();
                $this->template->refresh();
            }
        }

        $this->role = $role;
        $this->active = (bool) ($this->template->active ?? true);
        $this->tasks = is_array($this->template->tasks) ? $this->template->tasks : [];
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if (! $user->hasAnyRole(['Campus Manager', 'Super Admin'])) {
            return false;
        }

        return app(FeatureFlagsService::class)->enabled('checklists_module', $user->tenant_id);
    }
}

