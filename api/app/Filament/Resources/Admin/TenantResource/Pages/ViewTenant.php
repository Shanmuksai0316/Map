<?php

namespace App\Filament\Resources\Admin\TenantResource\Pages;

use App\Filament\Resources\Admin\TenantResource;
use App\Enums\TenantStatus;
use App\Events\TenantActivated;
use App\Models\Tenant;
use App\Services\IdempotencyService;
use App\Services\Onboarding\PreflightService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ViewTenant extends ViewRecord
{
    protected static string $resource = TenantResource::class;

    // Allow relation managers to show create/edit actions
    // so Super Admin can add hostels to activated tenants
    public function isReadOnly(): bool
    {
        return false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('resumeOnboarding')
                ->label('Resume Onboarding')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn () => $this->record->status === TenantStatus::PROVISIONING)
                ->url(fn () => route('filament.admin.pages.tenant-onboarding-wizard', [
                    'tenant_id' => $this->record->id,
                ])),
            Actions\Action::make('activate')
                ->label('Activate Tenant')
                ->icon('heroicon-o-rocket-launch')
                ->color('success')
                ->visible(fn () => $this->record->status === TenantStatus::PROVISIONING)
                ->requiresConfirmation()
                ->modalHeading('Activate Tenant')
                ->modalDescription('Are you sure you want to activate this tenant? This will run pre-flight checks and lock structural changes.')
                ->modalSubmitActionLabel('Yes, Activate')
                ->form([
                    \Filament\Forms\Components\TextInput::make('idempotency_key')
                        ->label('Idempotency Key')
                        ->maxLength(64)
                        ->helperText('Optional: Prevents duplicate activation within 24h. Auto-generated if empty.'),
                ])
                ->action(function (array $data) {
                    $tenant = $this->record;
                    
                    if ($tenant->status !== TenantStatus::PROVISIONING) {
                        Notification::make()
                            ->title('Invalid status')
                            ->body('Only tenants in provisioning status can be activated.')
                            ->danger()
                            ->send();
                        return;
                    }

                    try {
                        $idempotencyService = app(IdempotencyService::class);
                        $preflightService = app(PreflightService::class);
                        
                        // Get wizard data from tenant
                        $wizardData = $tenant->wizard ?? [];
                        
                        // Generate idempotency key if not provided
                        $idemKey = $data['idempotency_key'] ?? 'activate_' . $tenant->id . '_' . now()->format('YmdHis');
                        
                        // Check idempotency
                        try {
                            $idempotencyService->assertUnique(
                                action: 'tenant_activation',
                                key: $idemKey,
                                userId: auth()->id(),
                                tenantId: (string) $tenant->id,
                                fingerprint: ['tenant_id' => $tenant->id]
                            );
                        } catch (\RuntimeException $e) {
                            Notification::make()
                                ->title('Duplicate activation')
                                ->body($e->getMessage())
                                ->warning()
                                ->send();
                            return;
                        }

                        // Run preflight checks
                        $preflight = $preflightService->evaluate($tenant, $wizardData);
                        
                        if (!$preflight['passed']) {
                            $errors = collect($preflight['errors'])->pluck('message')->join(', ');
                            Notification::make()
                                ->title('Pre-flight checks failed')
                                ->body($errors)
                                ->danger()
                                ->send();
                            return;
                        }

                        // Activate tenant
                        DB::transaction(function () use ($tenant, $wizardData, $idempotencyService, $idemKey) {
                            $tenant->update([
                                'status' => TenantStatus::ACTIVE,
                            ]);

                            event(new TenantActivated($tenant, $wizardData));

                            Log::info('Tenant activated', [
                                'tenant_id' => $tenant->id,
                                'code' => $tenant->code,
                            ]);

                            $idempotencyService->storeResponse('tenant_activation', $idemKey, [
                                'tenant_id' => $tenant->id,
                                'status' => TenantStatus::ACTIVE->value,
                            ]);
                        });

                        Notification::make()
                            ->title('Tenant activated')
                            ->body('Tenant is now active. Structural changes are locked.')
                            ->success()
                            ->send();
                        
                        $this->redirect(route('filament.admin.resources.tenants.index'));

                    } catch (\Exception $e) {
                        Log::error('Failed to activate tenant', [
                            'tenant_id' => $tenant->id,
                            'error' => $e->getMessage(),
                        ]);

                        Notification::make()
                            ->title('Activation failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
