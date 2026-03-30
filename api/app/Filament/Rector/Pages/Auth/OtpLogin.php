<?php

namespace App\Filament\Rector\Pages\Auth;

use App\Filament\Rector\Http\Responses\RectorLoginResponse;
use App\Services\FilamentOtpService;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;

class OtpLogin extends BaseLogin
{
    protected static string $view = 'filament.rector.pages.auth.otp-login';

    public ?string $phone = null;
    public ?string $otp = null;
    public bool $otpSent = false;
    public ?int $userId = null;
    public ?string $userName = null;

    protected $listeners = ['refreshComponent' => '$refresh'];

    public function mount(): void
    {
        parent::mount();

        // Avoid cross-panel session conflicts: non-rector users should not be
        // considered authenticated for the Rector panel login flow.
        if (! Auth::guard('web')->check()) {
            return;
        }

        $user = Auth::guard('web')->user();

        if (! $user || (! $user->hasRole('Rector') && ! $user->hasRole('Super Admin'))) {
            Auth::guard('web')->logout();
            session()->invalidate();
            session()->regenerateToken();
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
            ->placeholder('+919900000003')
            ->tel()
            ->required()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1])
            ->hidden(fn () => $this->otpSent)
            ->helperText('Enter your registered phone number');
    }

    protected function getOtpFormComponent(): Component
    {
        return TextInput::make('otp')
            ->label('OTP Code')
            ->placeholder('123456')
            ->required()
            ->length(6)
            ->numeric()
            ->extraInputAttributes(['tabindex' => 2])
            ->hidden(fn () => !$this->otpSent)
            ->helperText('Enter the 6-digit OTP sent to your phone');
    }

    public function sendOtp(): void
    {
        $phone = $this->data['phone'] ?? $this->phone;

        $this->validate([
            'data.phone' => ['required', 'string', 'regex:/^\+?[1-9]\d{1,14}$/'],
        ]);

        try {
            $otpService = app(FilamentOtpService::class);
            $result = $otpService->sendOtp($phone);

            $this->userId = $result['user_id'];
            $this->userName = $result['user_name'];
            $this->phone = $phone;
            $this->otpSent = true;

            $this->dispatch('$refresh');

            Notification::make()
                ->success()
                ->title('OTP Sent')
                ->body("OTP has been sent to {$phone}")
                ->send();

            if ($result['otp']) {
                Notification::make()
                    ->info()
                    ->title('Development Mode - OTP Code')
                    ->body("Your OTP is: {$result['otp']}")
                    ->persistent()
                    ->send();
            }
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
        if (app()->environment('local') && Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();

            if ($user && ($user->hasRole('Rector') || $user->hasRole('Super Admin'))) {
                return app(RectorLoginResponse::class);
            }

            Auth::guard('web')->logout();
            session()->invalidate();
            session()->regenerateToken();
        }
        if (!$this->otpSent) {
            $this->sendOtp();
            return null;
        }

        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            throw ValidationException::withMessages([
                'data.otp' => "Too many attempts. Please try again in {$exception->secondsUntilAvailable} seconds.",
            ]);
        }

        $this->validate([
            'data.otp' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ]);

        try {
            $otpService = app(FilamentOtpService::class);
            $otp = $this->data['otp'] ?? $this->otp;
            $user = $otpService->verifyOtp($this->phone, $otp);

            // Check if user has Rector role
            if (!$user->hasRole('Rector')) {
                throw new \Exception('You do not have access to the Rector panel. Please contact your administrator.');
            }

            Auth::guard('web')->login($user, remember: true);

            session()->regenerate();

            // Use custom RectorLoginResponse for proper redirects
            return app(RectorLoginResponse::class);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'data.otp' => $e->getMessage(),
            ]);
        }
    }

    public function resendOtp(): void
    {
        $this->otpSent = false;
        $this->otp = null;
        $this->data['otp'] = null;
        
        $this->sendOtp();
    }
}

