<?php

namespace App\Filament\CampusManager\Widgets\CampusManager;

use App\Models\ActivityFeedEntry;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Arr;

/**
 * Activity feed widget displayed on the Campus Manager dashboard.
 *
 * Implements HasForms to provide an inline note entry form.
 * The form uses statePath('entryData') for Filament v3 compatibility -- without this,
 * form state is not properly isolated and field values may collide with widget properties.
 * All entries are scoped to the current tenant (resolved from subdomain or user's tenant_id).
 */
class ActivityFeedWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.campus-manager.widgets.activity-feed';

    protected int|string|array $columnSpan = [
        'md' => 2,
    ];

    public ?array $entryData = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Add Note')
                    ->required()
                    ->maxLength(120),
                Forms\Components\Textarea::make('body')
                    ->rows(2)
                    ->maxLength(500)
                    ->placeholder('Optional description'),
                Forms\Components\Select::make('visibility')
                    ->label('Visibility')
                    ->options([
                        'tenant' => 'Campus team',
                        'internal' => 'Internal only',
                        'staff' => 'All staff',
                    ])
                    ->default('tenant'),
            ])
            ->statePath('entryData');
    }

    public function saveEntry(): void
    {
        $data = $this->form->getState();

        try {
            $tenantId = tenant('id') ?? auth()->user()?->tenant_id;
        } catch (\Exception $e) {
            $tenantId = auth()->user()?->tenant_id;
        }
        
        ActivityFeedEntry::create([
            'tenant_id' => $tenantId,
            'type' => Arr::get($data, 'type', 'note.manual'),
            'channel' => 'manual',
            'title' => Arr::get($data, 'title'),
            'body' => Arr::get($data, 'body'),
            'metadata' => [],
            'created_by' => auth()->id(),
            'visibility' => Arr::get($data, 'visibility', 'tenant'),
        ]);

        $this->form->fill();

        Notification::make()
            ->success()
            ->title('Note logged')
            ->send();
    }

    protected function getViewData(): array
    {
        try {
            // Get tenant ID - prioritize tenant context from subdomain
            $tenantId = null;
            try {
                if (function_exists('tenant') && tenant()) {
                    $tenantId = tenant()->id;
                }
            } catch (\Exception $e) {
                // tenant() might not be available
            }
            
            if (!$tenantId) {
                $user = auth()->user();
                $tenantId = $user?->tenant_id;
            }
            
            // Explicitly scope by tenant_id to ensure we get data for current tenant
            // Even if TenantScope bypasses for Super Admin, we want tenant-specific data
            $entriesQuery = ActivityFeedEntry::query()
                ->orderByDesc('created_at')
                ->limit(8);
            
            if ($tenantId) {
                $entriesQuery->where('tenant_id', $tenantId);
            }
            
            $entries = $entriesQuery->get();

            return [
                'entries' => $entries,
                'form' => $this->form ?? null,
            ];
        } catch (\Exception $e) {
            \Log::error('ActivityFeedWidget error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'entries' => collect([]),
                'form' => $this->form ?? null,
            ];
        }
    }

}

