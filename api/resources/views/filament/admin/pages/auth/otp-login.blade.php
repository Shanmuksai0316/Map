@php
    use Filament\Support\Facades\FilamentView;
    use Filament\View\PanelsRenderHook;
@endphp

@component('filament.auth.panel-login-layout', [
    'title' => __('filament-panels::pages/auth/login.form.heading'),
    'subtitle' => __('filament-panels::pages/auth/login.form.subheading'),
])
    <x-filament-panels::form wire:submit="authenticate">
        {{ $this->form }}

        <div class="mt-6">
            @if (!$otpSent)
                {{ $this->getSendOtpAction() }}
            @else
                <div class="space-y-4">
                    {{ $this->getVerifyOtpAction() }}

                    <div class="flex justify-between">
                        {{ $this->getResendOtpAction() }}
                        {{ $this->getChangePhoneAction() }}
                    </div>
                </div>
            @endif
        </div>
    </x-filament-panels::form>
@endcomponent
