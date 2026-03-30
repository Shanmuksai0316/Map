<?php

namespace App\Filament\Pages\Admin;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\SystemSetting;

/**
 * System Configuration Page
 * 
 * Global settings and feature flags for the entire MAP-HMS platform.
 * Only accessible to Super Admin.
 */
class SystemConfiguration extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.pages.admin.system-configuration';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;
    
    protected static ?string $navigationLabel = 'Settings';
    
    protected static ?string $title = 'System Settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return false;
    }

    public function mount(): void
    {
        // Load from database if available, otherwise use config
        $this->form->fill([
            'feature_flags' => [
                'onboarding_v2' => SystemSetting::get('feature_flags.onboarding_v2', config('features.onboarding_v2', true)),
                'super_admin_staff_mgmt' => SystemSetting::get('feature_flags.super_admin_staff_mgmt', config('features.super_admin_staff_mgmt', false)),
                'sms_events' => SystemSetting::get('feature_flags.sms_events', config('features.sms_events', true)),
                'mfa_totp' => SystemSetting::get('feature_flags.mfa_totp', false),
            ],
            'msg91_auth_key' => SystemSetting::get('msg91_auth_key', config('services.msg91.auth_key', '')),
            's3_bucket' => SystemSetting::get('s3_bucket', config('filesystems.disks.s3.bucket', '')),
            'maintenance_mode' => app()->isDownForMaintenance(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Feature Flags')
                    ->description('Enable/disable features globally across all tenants')
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('feature_flags.onboarding_v2')
                                ->label('Onboarding Wizard V2')
                                ->helperText('Enable the simplified multi-step onboarding wizard')
                                ->default(true),
                            Toggle::make('feature_flags.super_admin_staff_mgmt')
                                ->label('Super Admin Staff Management')
                                ->helperText('Allow Super Admin to manage staff across tenants')
                                ->default(false),
                            Toggle::make('feature_flags.mfa_totp')
                                ->label('TOTP MFA')
                                ->helperText('Require TOTP codes for sensitive admin actions')
                                ->default(false),
                        ]),
                        Grid::make(2)->schema([
                            Toggle::make('feature_flags.sms_events')
                                ->label('SMS Notifications')
                                ->helperText('Enable MSG91 SMS notifications')
                                ->default(true),
                        ]),
                    ]),
                
                Section::make('Integration Settings')
                    ->description('Third-party service credentials and configuration')
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('msg91_auth_key')
                                ->label('MSG91 Auth Key')
                                ->helperText('SMS gateway authentication key')
                                ->password()
                                ->revealable(),
                        ]),
                        Grid::make(2)->schema([
                            TextInput::make('s3_bucket')
                                ->label('S3 Bucket Name')
                                ->helperText('AWS S3 bucket for file storage'),
                            TextInput::make('s3_region')
                                ->label('S3 Region')
                                ->default('ap-south-1')
                                ->helperText('AWS region (e.g., ap-south-1 for Mumbai)'),
                        ]),
                    ]),
                
                Section::make('System Maintenance')
                    ->description('System-wide maintenance and operational settings')
                    ->schema([
                        Toggle::make('maintenance_mode')
                            ->label('Maintenance Mode')
                            ->helperText('Enable to block all access except Super Admin')
                            ->reactive(),
                        Textarea::make('maintenance_message')
                            ->label('Maintenance Message')
                            ->helperText('Message shown to users during maintenance')
                            ->rows(3)
                            ->visible(fn ($get) => $get('maintenance_mode')),
                    ]),
                
                Section::make('Advanced Settings')
                    ->description('Advanced configuration options')
                    ->collapsed()
                    ->schema([
                        KeyValue::make('custom_settings')
                            ->label('Custom Settings (JSON)')
                            ->helperText('Additional key-value settings for custom features')
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Configuration')
                ->action('save')
                ->requiresConfirmation()
                ->modalHeading('Update System Configuration')
                ->modalDescription('This will update global settings. Changes will affect all tenants.')
                ->color('primary'),
            
            Action::make('clearCache')
                ->label('Clear Cache')
                ->action('clearCache')
                ->color('warning')
                ->requiresConfirmation(),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        
        try {
            // Save feature flags
            foreach ($data['feature_flags'] ?? [] as $key => $value) {
                \App\Models\SystemSetting::set(
                    "feature_flags.{$key}",
                    $value,
                    'boolean',
                    "Feature flag: {$key}"
                );
            }
            
            // Save integration settings
            if (isset($data['msg91_auth_key'])) {
                \App\Models\SystemSetting::set(
                    'msg91_auth_key',
                    $data['msg91_auth_key'],
                    'string',
                    'MSG91 SMS gateway authentication key'
                );
            }
            
            if (isset($data['s3_bucket'])) {
                \App\Models\SystemSetting::set(
                    's3_bucket',
                    $data['s3_bucket'],
                    'string',
                    'AWS S3 bucket for file storage'
                );
            }
            
            // Clear config cache to reload settings
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            
            Notification::make()
                ->title('Configuration saved')
                ->body('System settings have been updated and saved to database.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Log::error('SystemConfiguration: Error saving settings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            Notification::make()
                ->title('Configuration save failed')
                ->body('An error occurred while saving settings. Please try again.')
                ->danger()
                ->send();
        }
    }

    public function clearCache(): void
    {
        Cache::flush();
        
        Notification::make()
            ->title('Cache cleared')
            ->body('All cached data has been cleared.')
            ->success()
            ->send();
    }
}
