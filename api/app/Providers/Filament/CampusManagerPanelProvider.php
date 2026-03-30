<?php

namespace App\Providers\Filament;

use App\Filament\CampusManager\Pages\Auth\OtpLogin;
use App\Filament\CampusManager\Pages\Dashboard;
use App\Filament\CampusManager\Widgets\CampusManager\StatsOverview;
use App\Filament\CampusManager\Http\Responses\CampusManagerLoginResponse;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
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
use App\Http\Middleware\DevInitializeTenancyFromSession;
use App\Http\Middleware\DevExposeException;
use App\Http\Middleware\SetPostgresSessionTenant;
use Illuminate\Support\Facades\Storage;
use App\Filament\Shared\Pages\Profile as SharedProfile;

class CampusManagerPanelProvider extends PanelProvider
{
    /**
     * Campus Manager panel configuration -- the main dashboard for hostel managers.
     *
     * Tenant resolution: Subdomain-based via InitializeTenancyByDomain middleware.
     * Authentication: OTP-based login (bypass code configurable via config('otp.bypass_code')).
     * Brand colors: Military Green (#2F4F2F) primary, Golden Yellow (#D4AF37) accent.
     * Navigation groups: Dashboard, Student Management, Room & Allocation, Checklist,
     *                    Requests, Communications, Emergency, Operations.
     */
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('campus-manager')
            ->path('campus-manager')
            ->authGuard('web')
            ->login(OtpLogin::class)
            ->homeUrl('/campus-manager')
            ->passwordReset()
            // Brand: use tenant-specific logo where available, fallback to MAP logo.
            ->brandName('Campus Manager Dashboard')
            ->brandLogo(function () {
                $tenant = function_exists('tenant') ? tenant() : null;
                $logoPath = $tenant ? data_get($tenant->settings, 'branding.logo_path') : null;

                /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                $disk = Storage::disk('public');

                return $logoPath
                    ? $disk->url($logoPath)
                    : asset('images/map-logo.svg');
            })
            ->brandLogoHeight('3rem')
            ->favicon(function () {
                $tenant = function_exists('tenant') ? tenant() : null;
                $logoPath = $tenant ? data_get($tenant->settings, 'branding.logo_path') : null;

                /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                $disk = Storage::disk('public');

                return $logoPath
                    ? $disk->url($logoPath)
                    : asset('images/map-logo.svg');
            })
            // Disable universal/global search bar for Campus Manager
            ->globalSearch(false)
            ->darkMode(false)  // Light theme only per client requirement
            ->font('DM Sans')
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
            // Match Super Admin sidebar: explicit navigation groups with icons.
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Dashboard')
                    ->icon('heroicon-o-home')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Student Management')
                    ->icon('heroicon-o-academic-cap')
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Rooms & Allocation')
                    ->icon('heroicon-o-building-office')
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Checklist')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Requests')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Communications')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Emergency')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Sports Management')
                    ->icon('heroicon-o-trophy')
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Security')
                    ->icon('heroicon-o-shield-check')
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Add-ons')
                    ->icon('heroicon-o-sparkles')
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Imports')
                    ->icon('heroicon-o-arrow-down-on-square-stack')
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Operations')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsed(true),
            ])
            ->discoverResources(in: app_path('Filament/CampusManager/Resources'), for: 'App\\Filament\\CampusManager\\Resources')
            ->discoverPages(in: app_path('Filament/CampusManager/Pages'), for: 'App\\Filament\\CampusManager\\Pages')
            ->pages([
                Dashboard::class,
                SharedProfile::class,
            ])
            ->discoverWidgets(in: app_path('Filament/CampusManager/Widgets'), for: 'App\\Filament\\CampusManager\\Widgets')
            ->widgets([
                \Filament\Widgets\AccountWidget::class,
            ])
            ->renderHook('panels::sidebar.start', fn () => view('components.admin-sidebar-styles'))
            ->renderHook('sidebar.start', fn () => view('components.admin-sidebar-styles'))
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => view('components.admin-sidebar-styles-head'))
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn () => view('filament.campus-manager.sidebar-footer')
            )
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            // Tenancy is resolved by subdomain; keep panel non-tenantized to avoid tenant IDs in URLs
            ->tenant(null)
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                // Initialize tenancy BEFORE any tenant-dependent operations
                InitializeTenancyByDomain::class,
                PreventAccessFromCentralDomains::class,
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
}
