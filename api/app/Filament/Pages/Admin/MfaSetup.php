<?php

namespace App\Filament\Pages\Admin;

use App\Services\MfaService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class MfaSetup extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static string $view = 'filament.pages.admin.mfa-setup';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'MFA Setup';

    /** Hide MFA from left sidebar per product requirement. */
    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        // MFA setup page has been deprecated/removed from the admin panel.
        // Returning false here ensures the route is effectively inaccessible
        // even if someone knows the URL.
        return false;
    }

    public function mount(): void
    {
        $user = Auth::user();
        $this->form->fill([
            'mfa_enabled' => (bool) $user?->mfa_enabled,
            'mfa_secret' => $user?->mfa_secret,
            'code' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Toggle::make('mfa_enabled')
                    ->label('Enable TOTP MFA')
                    ->helperText('Adds a 6-digit code from your authenticator app during login.'),
                Forms\Components\TextInput::make('mfa_secret')
                    ->label('Secret')
                    ->readOnly()
                    ->dehydrated(false)
                    ->helperText('Scan or copy into your authenticator app.'),
                Forms\Components\TextInput::make('code')
                    ->label('Authenticator Code')
                    ->numeric()
                    ->length(6)
                    ->helperText('Enter a current 6-digit code to confirm enablement.'),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save MFA Settings')
                ->action('save')
                ->color('primary'),
        ];
    }

    public function save(): void
    {
        $user = Auth::user();
        $data = $this->form->getState();
        $mfa = app(MfaService::class);

        if (!$user) {
            Notification::make()->title('Not authenticated')->danger()->send();
            return;
        }

        // Disable flow
        if (!($data['mfa_enabled'] ?? false)) {
            $mfa->disable($user);
            $this->form->fill([
                'mfa_enabled' => false,
                'mfa_secret' => null,
                'code' => null,
            ]);
            Notification::make()->title('MFA disabled')->success()->send();
            return;
        }

        // Ensure secret exists
        if (empty($user->mfa_secret)) {
            $secret = $mfa->generateSecret();
            $user->update([
                'mfa_secret' => $secret,
                'mfa_enabled' => false,
            ]);
            $this->form->fill([
                'mfa_enabled' => false,
                'mfa_secret' => $secret,
                'code' => null,
            ]);
            Notification::make()
                ->title('Secret generated')
                ->body('Scan the secret in your authenticator app, then enter a 6-digit code to enable.')
                ->info()
                ->send();
            return;
        }

        // Verify code
        $code = $data['code'] ?? '';
        if (!$mfa->verifyCode($user->fresh(), $code)) {
            Notification::make()
                ->title('Invalid code')
                ->body('Enter a valid 6-digit authenticator code to enable MFA.')
                ->danger()
                ->send();
            return;
        }

        $user->update(['mfa_enabled' => true]);
        Notification::make()
            ->title('MFA enabled')
            ->body('TOTP codes are now required for sensitive actions.')
            ->success()
            ->send();
    }
}

