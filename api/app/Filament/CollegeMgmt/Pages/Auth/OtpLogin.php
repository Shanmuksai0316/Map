<?php

namespace App\Filament\CollegeMgmt\Pages\Auth;

use App\Filament\CollegeMgmt\Http\Responses\CollegeMgmtLoginResponse;
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
    protected static string $view = 'filament.college-mgmt.pages.auth.otp-login';

    public ?string $phone = null;
    public ?string $otp = null;
    public bool $otpSent = false;
    public ?int $userId = null;
    public ?string $userName = null;

    protected $listeners = ['refreshComponent' => '$refresh'];

    /**
     * Override mount to prevent errors when panel URL is accessed before tenant is initialized
     */
    public function mount(): void
    {
        try {
            parent::mount();
        } catch (\Illuminate\Routing\Exceptions\RouteNotFoundException $e) {
            // If route not found (e.g., discovered pages have missing routes), continue anyway
            // The login page doesn't need navigation items
            \Illuminate\Support\Facades\Log::warning('CollegeMgmt OtpLogin mount error: Route not found (non-critical)', [
                'error' => $e->getMessage(),
                'route' => $e->getMessage(),
            ]);
            // Don't rethrow - allow login page to render even if navigation fails
        } catch (\Exception $e) {
            // If parent mount fails (e.g., panel URL issue), continue anyway
            // The login page doesn't need navigation items
            \Illuminate\Support\Facades\Log::warning('CollegeMgmt OtpLogin mount error (non-critical)', [
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
            ->placeholder('9876543210')
            ->tel()
            ->required()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1])
            ->hidden(fn () => $this->otpSent)
            ->helperText('Enter your registered 10-digit mobile number');
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
        $rawPhone = $this->data['phone'] ?? $this->phone;

        $this->validate([
            'data.phone' => ['required', 'string', 'regex:/^(?:\\+91|91)?[6-9]\\d{9}$/'],
        ]);

        try {
            $phone = $this->normalizeIndianPhone($rawPhone);
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
            return app(LoginResponse::class);
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
            $user = $otpService->verifyOtp($this->normalizeIndianPhone($this->phone), $otp);

            // Check if user has College Management role
            if (!$user->hasRole('College Management') && !$user->hasRole('College Mgmt')) {
                throw new \Exception('You do not have access to the College Management panel. Please contact your administrator.');
            }

            Auth::guard('web')->login($user, remember: true);

            session()->regenerate();

            // Use custom CollegeMgmtLoginResponse for proper redirects
            return app(CollegeMgmtLoginResponse::class);
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

    public function changePhone(): void
    {
        $this->otpSent = false;
        $this->otp = null;
        $this->userId = null;
        $this->userName = null;
        $this->data['otp'] = null;
    }

    private function normalizeIndianPhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (str_starts_with($digits, '91') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 10) {
            return '+91' . $digits;
        }

        return (string) $phone;
    }
}
