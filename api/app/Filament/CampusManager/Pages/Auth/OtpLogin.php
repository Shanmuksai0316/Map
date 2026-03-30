<?php

namespace App\Filament\CampusManager\Pages\Auth;

use App\Services\FilamentOtpService;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class OtpLogin extends BaseLogin
{
    protected static string $view = 'filament.campus-manager.pages.auth.otp-login';

    public ?string $phone = null;
    public ?string $otp = null;
    public bool $otpSent = false;
    public ?int $userId = null;
    public ?string $userName = null;

    // Make otpSent reactive to Livewire
    protected $listeners = ['refreshComponent' => '$refresh'];

    /**
     * Override mount to prevent errors when panel URL is accessed before tenant is initialized
     */
    public function mount(): void
    {
        try {
            parent::mount();
        } catch (\Illuminate\Routing\Exceptions\RouteNotFoundException $e) {
            // If route not found (e.g., laundry-requests page route issue), continue anyway
            // The login page doesn't need navigation items
            \Illuminate\Support\Facades\Log::warning('OtpLogin mount error: Route not found (non-critical)', [
                'error' => $e->getMessage(),
                'route' => $e->getMessage(),
            ]);
            // Don't rethrow - allow login page to render even if navigation fails
            // This prevents 500 errors when discovered pages have missing routes
        } catch (\Exception $e) {
            // If parent mount fails (e.g., panel URL issue), continue anyway
            // The login page doesn't need navigation items
            \Illuminate\Support\Facades\Log::warning('OtpLogin mount error (non-critical)', [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 300),
            ]);
            // Don't rethrow - allow login page to render even if navigation fails
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getPhoneFormComponent(),
                $this->getOtpFormComponent(),
            ]);
    }

    protected function getPhoneFormComponent(): Component
    {
        return TextInput::make('phone')
            ->label('Phone Number')
            ->placeholder('+919900000999')
            ->tel()
            ->required()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1])
            ->hidden(fn () => $this->otpSent)
            ->helperText('Enter your registered phone number');
    }

    protected function getOtpFormComponent(): Component
    {
        $bypassEnabled = $this->isBypassEnabled();
        $bypassCode = config('otp.bypass_code', '123456');
        
        return TextInput::make('otp')
            ->label('Enter OTP')
            ->placeholder($bypassEnabled ? "{$bypassCode} (bypass code)" : '123456')
            ->length(6)
            ->required()
            ->numeric()
            ->extraInputAttributes(['tabindex' => 2, 'autocomplete' => 'one-time-code'])
            ->hidden(fn () => !$this->otpSent && !$bypassEnabled)
            ->helperText($bypassEnabled && !$this->otpSent 
                ? "Enter the 6-digit OTP or use bypass code: {$bypassCode}" 
                : 'Enter the 6-digit OTP sent to your phone');
    }

    public function sendOtp(): void
    {
        $phone = $this->getFormValue('phone') ?? $this->phone;
        $normalizedPhone = $this->normalizePhone($phone);

        if (! $normalizedPhone || ! preg_match('/^\+[1-9]\d{7,14}$/', $normalizedPhone)) {
            throw ValidationException::withMessages([
                'data.phone' => 'Enter a valid phone number (example: +919900000999).',
            ]);
        }

        try {
            $otpService = app(FilamentOtpService::class);
            $result = $otpService->sendOtp($normalizedPhone);

            $this->userId = $result['user_id'];
            $this->userName = $result['user_name'];
            $this->phone = $normalizedPhone;
            $this->otpSent = true;

            // Keep form state in sync after normalization.
            $this->form->fill([
                'phone' => $normalizedPhone,
            ]);

            Notification::make()
                ->success()
                ->title('OTP Sent')
                ->body(
                    app()->environment('local') && ! empty($result['otp'])
                        ? "OTP has been sent to {$normalizedPhone}. Test OTP: {$result['otp']}"
                        : "OTP has been sent to {$normalizedPhone}"
                )
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function authenticate(): ?LoginResponse
    {
        // Dev-only bypass: if already authenticated, return login response
        if (app()->environment('local') && Auth::guard('web')->check()) {
            return app(LoginResponse::class);
        }

        // Read from form state first, then from component properties.
        $phone = $this->normalizePhone($this->getFormValue('phone') ?? $this->phone);
        $otp = trim((string) ($this->getFormValue('otp') ?? $this->otp ?? ''));

        // Validate phone is provided
        if (empty($phone)) {
            throw ValidationException::withMessages([
                'data.phone' => 'Phone number is required.',
            ]);
        }

        // Check if bypass OTP is enabled and being used
        $bypassEnabled = $this->isBypassEnabled();
        $bypassCode = config('otp.bypass_code', '123456');
        $isBypassAttempt = $bypassEnabled && !empty($otp) && $otp === $bypassCode;

        // If not using bypass and OTP not sent, send OTP first
        if (!$isBypassAttempt && !$this->otpSent) {
            $this->sendOtp();
            return null;
        }

        // Validate OTP is provided (required for both normal and bypass flows)
        if (empty($otp)) {
            throw ValidationException::withMessages([
                'data.otp' => 'OTP is required.',
            ]);
        }

        $rateLimitKey = 'campus-otp-login:' . sha1(($phone ?? '') . '|' . request()->ip());
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            throw ValidationException::withMessages([
                'data.otp' => "Too many attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        RateLimiter::hit($rateLimitKey, 60);

        if (! preg_match('/^\d{6}$/', $otp)) {
            throw ValidationException::withMessages([
                'data.otp' => 'OTP must be a 6-digit number.',
            ]);
        }

        try {
            $otpService = app(FilamentOtpService::class);
            
            // Store phone for bypass flow if not already stored
            if (!$this->phone) {
                $this->phone = $phone;
            }

            // Verify OTP (this handles both normal OTP and bypass code)
            $user = $otpService->verifyOtp($this->phone, $otp);

            // Ensure tenant context is set before login
            if (!tenant() && $user->tenant_id) {
                $tenant = \App\Models\Tenant::find($user->tenant_id);
                if ($tenant) {
                    \Stancl\Tenancy\Facades\Tenancy::initialize($tenant);
                }
            }

            Auth::guard('web')->login($user, remember: true);

            session()->regenerate();
            RateLimiter::clear($rateLimitKey);

            return app(LoginResponse::class);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('OTP authentication error', [
                'phone' => substr($this->phone ?? $phone ?? '', -4),
                'otp_type' => $isBypassAttempt ? 'bypass' : 'normal',
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 500),
            ]);
            
            throw ValidationException::withMessages([
                'data.otp' => $e->getMessage(),
            ]);
        }
    }

    private function getFormValue(string $key): mixed
    {
        try {
            $state = $this->form->getState();

            return $state[$key] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (! filled($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (! $digits) {
            return null;
        }

        // 10-digit India mobile -> +91XXXXXXXXXX
        if (strlen($digits) === 10) {
            return '+91' . $digits;
        }

        // 12-digit India mobile with 91 prefix -> +91XXXXXXXXXX
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return '+' . $digits;
        }

        // Generic E.164-ish fallback.
        return str_starts_with((string) $phone, '+') ? '+' . $digits : '+' . $digits;
    }

    public function resendOtp(): void
    {
        try {
            $this->sendOtp();

            Notification::make()
                ->success()
                ->title('OTP Resent')
                ->body('A new OTP has been sent to your phone.')
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function changePhone(): void
    {
        $this->otpSent = false;
        $this->otp = null;
        $this->userId = null;
        $this->userName = null;
    }

    protected function hasFullWidthFormActions(): bool
    {
        return true;
    }

    public function getSendOtpAction(): Action
    {
        return Action::make('sendOtp')
            ->label('Send OTP')
            ->submit('sendOtp')
            ->color('primary')
            ->size('lg');
    }

    public function getVerifyOtpAction(): Action
    {
        $bypassEnabled = $this->isBypassEnabled();
        
        return Action::make('authenticate')
            ->label($bypassEnabled && !$this->otpSent ? 'Login with Bypass Code' : 'Verify & Login')
            ->submit('authenticate')
            ->color('primary')
            ->size('lg')
            ->visible(fn () => $this->otpSent || $bypassEnabled);
    }

    private function isBypassEnabled(): bool
    {
        return config('otp.bypass_enabled', false)
            || (app()->environment('local')
                && ! config('services.msg91.enabled')
                && ! config('services.stpl.enabled'));
    }

    public function getResendOtpAction(): Action
    {
        return Action::make('resendOtp')
            ->label('Resend OTP')
            ->action('resendOtp')
            ->color('gray')
            ->link();
    }

    public function getChangePhoneAction(): Action
    {
        return Action::make('changePhone')
            ->label('Change Phone Number')
            ->action('changePhone')
            ->color('gray')
            ->link();
    }
}
