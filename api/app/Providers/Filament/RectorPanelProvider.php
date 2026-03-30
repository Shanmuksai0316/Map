<?php

namespace App\Providers\Filament;

use App\Filament\Rector\Pages\Auth\OtpLogin;
use App\Filament\Rector\Pages\Dashboard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use App\Http\Middleware\SetPostgresSessionTenant;
use Illuminate\Support\Facades\Storage;

class RectorPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('rector')
            ->path('rector')
            ->authGuard('web')
            ->login(OtpLogin::class)
            ->homeUrl('/rector')
            ->brandName(fn () => $this->getBrandName())
            ->brandLogo(fn () => $this->getBrandLogo())
            ->brandLogoHeight('3rem')
            ->favicon(asset('images/map-logo.svg'))
            ->darkMode(false)  // Light theme only per client requirement
            ->font('DM Sans')
            ->navigationGroups([
                \Filament\Navigation\NavigationGroup::make()
                    ->label('Dashboard')
                    ->collapsed(false),
                \Filament\Navigation\NavigationGroup::make()
                    ->label('Approvals')
                    ->icon('heroicon-o-check-circle')
                    ->collapsed(true),
                \Filament\Navigation\NavigationGroup::make()
                    ->label('Requests')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->collapsed(true),
                \Filament\Navigation\NavigationGroup::make()
                    ->label('Communications')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->collapsed(true),
                \Filament\Navigation\NavigationGroup::make()
                    ->label('Reports')
                    ->icon('heroicon-o-chart-bar')
                    ->collapsed(true),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->renderHook('panels::sidebar.start', fn () => view('components.admin-sidebar-styles'))
            ->renderHook('sidebar.start', fn () => view('components.admin-sidebar-styles'))
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => view('components.admin-sidebar-styles-head'))
            ->renderHook(PanelsRenderHook::TOPBAR_END, fn () => view('components.topbar-map-logo'))
            ->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn () => view('components.account-sticky'))
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
            ->resources([
                \App\Filament\Rector\Resources\OutPassResource::class,
                \App\Filament\Rector\Resources\LeaveResource::class,
                \App\Filament\Rector\Resources\StudentResource::class,
            ])
            ->discoverPages(in: app_path('Filament/Rector/Pages'), for: 'App\\Filament\\Rector\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Rector/Widgets'), for: 'App\\Filament\\Rector\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            // Tenancy is resolved by subdomain; keep panel non-tenantized to avoid tenant IDs in URLs
            ->tenant(null)
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ...(app()->environment('local') ? [] : [
                    InitializeTenancyByDomain::class,
                    PreventAccessFromCentralDomains::class,
                ]),
                SetPostgresSessionTenant::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    protected function getBrandName(): string
    {
        $tenant = $this->resolveTenantSafely();
        
        if ($tenant) {
            return $tenant->name . ' - Rector App';
        }
        
        return 'Rector App';
    }

    protected function getBrandLogo(): ?string
    {
        $tenant = $this->resolveTenantSafely();
        
        if ($tenant) {
            $logoPath = data_get($tenant->settings, 'branding.logo_path') ?: ($tenant->logo_url ?? null);

            if ($logoPath && filter_var($logoPath, FILTER_VALIDATE_URL)) {
                return $logoPath;
            }

            if ($logoPath) {
                $normalized = ltrim((string) $logoPath, '/');

                foreach (['public_central', 'public'] as $diskName) {
                    try {
                        $disk = Storage::disk($diskName);
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
        }
        
        // Fallback to default MAP logo
        return asset('images/map-logo.svg');
    }

    private function resolveTenantSafely(): ?object
    {
        try {
            if (function_exists('tenant')) {
                return tenant();
            }
        } catch (\Throwable) {
            // Tenancy may be unavailable on local login routes.
        }

        return null;
    }
}
