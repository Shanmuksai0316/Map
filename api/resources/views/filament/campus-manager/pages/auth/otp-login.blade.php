@php
    use Filament\Support\Facades\FilamentView;
    use Filament\View\PanelsRenderHook;
@endphp

@component('filament.auth.panel-login-layout', [
    'title' => 'Campus Manager Login',
    'subtitle' => 'Enter your registered phone number and OTP to access the campus manager dashboard.',
])
    @if (filament()->hasLogin())
        {{ FilamentView::renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

        @include('filament.auth.partials.otp-phone-auth', [
            'showBypassHelp' => true,
            'useConfigBypass' => true,
        ])

        {{ FilamentView::renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
    @endif
@endcomponent
