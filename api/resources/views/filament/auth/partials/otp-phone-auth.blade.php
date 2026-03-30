@php
    $showBypassHelp = $showBypassHelp ?? false;
    $useConfigBypass = $useConfigBypass ?? true;
    $bypassEnabled = $useConfigBypass
        ? (
            config('otp.bypass_enabled', false)
            || (app()->environment('local')
                && ! config('services.msg91.enabled')
                && ! config('services.stpl.enabled'))
        )
        : false;
    $bypassCode = $bypassCode ?? config('otp.bypass_code', '123456');
@endphp

@if (!$otpSent && !$showBypassHelp)
    <p class="text-sm text-[#2F4F2F]/80 mb-4">
        Enter your registered phone number to receive an OTP
    </p>
@elseif (!$otpSent && $showBypassHelp && ! $bypassEnabled)
    <p class="text-sm text-[#2F4F2F]/80 mb-4">
        Enter your registered phone number to receive an OTP
    </p>
@elseif (!$otpSent && $showBypassHelp && $bypassEnabled)
    <p class="text-sm text-[#2F4F2F]/80 mb-4">
        Enter your registered phone number and OTP (or use bypass code:
        <code class="bg-gray-100 px-1 py-0.5 rounded">{{ $bypassCode }}</code>)
    </p>
@else
    <div class="space-y-2 mb-4">
        <p class="text-sm font-medium text-[#2F4F2F]">
            OTP sent to {{ $phone }}
        </p>
        <p class="text-xs text-[#2F4F2F]/80">
            Enter the 6-digit code we sent to your phone
        </p>
    </div>
@endif

<form wire:submit="authenticate">
    {{ $this->form }}

    <div class="space-y-3 mt-6">
        @if (!$otpSent && ! $bypassEnabled)
            <button
                type="button"
                wire:click="sendOtp"
                class="fi-btn fi-btn-size-lg fi-btn-color-primary btn-gradient-primary w-full"
            >
                Send OTP
            </button>
        @else
            <x-filament::button
                type="submit"
                size="lg"
                color="primary"
                class="w-full"
            >
                {{ ($showBypassHelp && $bypassEnabled && ! $otpSent) ? 'Login with Bypass Code' : 'Verify & Login' }}
            </x-filament::button>

            @if ($otpSent)
                <div class="flex items-center justify-between text-sm mt-4">
                    <button
                        type="button"
                        wire:click="resendOtp"
                        class="text-[#2F4F2F] hover:text-[#244224] font-medium underline-offset-2 hover:underline"
                    >
                        Resend OTP
                    </button>

                    <button
                        type="button"
                        wire:click="changePhone"
                        class="text-[#2F4F2F]/80 hover:text-[#2F4F2F] underline-offset-2 hover:underline"
                    >
                        Change Phone
                    </button>
                </div>
            @endif
        @endif
    </div>
</form>

@if ($otpSent)
    <div class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
        <div class="flex items-start">
            <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-3 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
            </svg>
            <div class="text-sm text-blue-800">
                <p class="font-medium">OTP will expire in 5 minutes</p>
                <p class="mt-1 text-blue-700">Didn't receive the code? Click "Resend OTP" to get a new one.</p>
            </div>
        </div>
    </div>
@endif

<div class="mt-6 text-center">
    <p class="text-xs text-[#2F4F2F]/80">
        Secure OTP-based authentication
    </p>
</div>
