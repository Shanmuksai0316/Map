@php
    use Filament\Support\Facades\FilamentView;
    use Filament\View\PanelsRenderHook;
@endphp

@component('filament.auth.panel-login-layout', [
    'title' => 'Rector Login',
    'subtitle' => 'Enter your registered phone number and OTP to access the rector dashboard.',
])
    {{ FilamentView::renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

    @include('filament.auth.partials.otp-phone-auth', [
        'showBypassHelp' => false,
        'useConfigBypass' => false,
    ])

    {{ FilamentView::renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
@endcomponent
