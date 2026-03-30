<?php

namespace App\Providers\Filament;

use App\Filament\CollegeMgmt\Pages\Auth\OtpLogin;
use App\Filament\CollegeMgmt\Pages\Dashboard;
use Filament\Navigation\NavigationGroup;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use App\Http\Middleware\DevInitializeTenancyFromSession;
use App\Http\Middleware\DevExposeException;
use Illuminate\Support\Facades\Log;
use App\Filament\Shared\Pages\Profile as SharedProfile;

class CollegeMgmtPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        try {
            return $panel
            ->id('college-mgmt')
            ->path('college-mgmt')
            ->authGuard('web')
            ->login(OtpLogin::class)
            ->homeUrl('/college-mgmt')
            ->brandName(fn () => tenant() ? tenant()->name . ' - MAP HMS (Management)' : 'MAP - HMS (Management)')
            ->brandLogo(fn () => $this->resolveTenantBrandingAsset())
            ->brandLogoHeight('3rem')
            ->favicon(fn () => $this->resolveTenantBrandingAsset())
            ->sidebarCollapsibleOnDesktop()
            ->renderHook('panels::sidebar.start', fn () => view('components.admin-sidebar-styles'))
            ->renderHook('sidebar.start', fn () => view('components.admin-sidebar-styles'))
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => view('components.admin-sidebar-styles-head'))
            ->renderHook(PanelsRenderHook::TOPBAR_END, fn () => view('components.topbar-map-logo'))
            ->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn () => view('components.account-sticky'))
            ->darkMode(false)  // Light theme only per client requirement
            ->font('DM Sans')
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Operations')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsed(false),
            ])
            ->colors([
                // Primary - Military Green (Client Brand Color)
                'primary' => Color::hex('#2F4F2F'),
                // Accent/Warning - Golden Yellow for highlights & key metrics
                'warning' => Color::hex('#D4AF37'),
                // Status colors - distinct from brand colors
                'success' => Color::hex('#059669'),  // Teal-green (not military green)
                'danger' => Color::hex('#DC2626'),   // Red
                'info' => Color::hex('#0284C7'),     // Blue
                'gray' => Color::Slate,
            ])
            ->discoverResources(in: app_path('Filament/CollegeMgmt/Resources'), for: 'App\\Filament\\CollegeMgmt\\Resources')
            ->discoverPages(in: app_path('Filament/CollegeMgmt/Pages'), for: 'App\\Filament\\CollegeMgmt\\Pages')
            ->pages([
                Dashboard::class,
                SharedProfile::class,
            ])
            ->discoverWidgets(in: app_path('Filament/CollegeMgmt/Widgets'), for: 'App\\Filament\\CollegeMgmt\\Widgets')
            ->widgets([])
            // Tenancy resolved by subdomain; keep panel non-tenantized to avoid tenant IDs in URLs
            ->tenant(null)
            ->middleware([
                DevExposeException::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                DevInitializeTenancyFromSession::class,
                ...(app()->environment('local') ? [] : [
                    PreventAccessFromCentralDomains::class,
                    InitializeTenancyByDomain::class,
                ]),
                \App\Http\Middleware\SetPostgresSessionTenant::class, // Required for RLS policies
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
        } catch (\Throwable $e) {
            if (app()->environment('local')) {
                Log::error('COLLEGE_PANEL_BOOT_ERR', [
                    'class' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
            throw $e;
        }
    }

    protected function resolveTenantBrandingAsset(): string
    {
        $tenant = function_exists('tenant') ? tenant() : null;
        $logoPath = $tenant ? (data_get($tenant->settings, 'branding.logo_path') ?: ($tenant->logo_url ?? null)) : null;

        if ($logoPath && filter_var($logoPath, FILTER_VALIDATE_URL)) {
            return $logoPath;
        }

        if ($logoPath) {
            $normalized = ltrim((string) $logoPath, '/');

            foreach (['public_central', 'public'] as $diskName) {
                try {
                    $disk = \Illuminate\Support\Facades\Storage::disk($diskName);
                    if ($disk->exists($normalized)) {
                        return $disk->url($normalized);
                    }
                } catch (\Throwable) {
                    // Ignore invalid disk config and continue fallback chain.
                }
            }

            if (str_starts_with($normalized, 'branding/logos/')) {
                return url('/storage/' . $normalized);
            }
        }

        return asset('images/map-logo.svg');
    }
}
