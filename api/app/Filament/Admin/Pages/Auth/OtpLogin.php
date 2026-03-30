<?php

namespace App\Filament\Admin\Pages\Auth;

use App\Services\FilamentOtpService;
use Filament\Actions\Action;
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
    protected static string $view = 'filament.admin.pages.auth.otp-login';

    public ?string $phone = null;
    public ?string $otp = null;
    public bool $otpSent = false;
    public ?int $userId = null;
    public ?string $userName = null;

    // Make otpSent reactive to Livewire
    protected $listeners = ['refreshComponent' => '$refresh'];

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
        return TextInput::make('otp')
            ->label('Enter OTP')
            ->placeholder('123456')
            ->length(6)
            ->required()
            ->numeric()
            ->extraInputAttributes(['tabindex' => 2, 'autocomplete' => 'one-time-code'])
            ->hidden(fn () => !$this->otpSent)
            ->helperText('Enter the 6-digit OTP sent to your phone');
    }

    public function sendOtp(): void
    {
        // Get phone from form data
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

            // Force Livewire to re-render the component
            $this->dispatch('$refresh');

            Notification::make()
                ->success()
                ->title('OTP Sent')
                ->body("OTP has been sent to {$phone}")
                ->send();

            // In development, show OTP
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

            Auth::guard('web')->login($user, remember: true);

            session()->regenerate();

            return app(LoginResponse::class);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'data.otp' => $e->getMessage(),
            ]);
        }
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
        return Action::make('authenticate')
            ->label('Verify & Login')
            ->submit('authenticate')
            ->color('primary')
            ->size('lg');
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
