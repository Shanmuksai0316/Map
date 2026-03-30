<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\Widgets;
use Filament\Facades\Filament;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Http\Middleware\SuperAdminAccess;
use App\Http\Middleware\RateLimitSuperAdmin;
use App\Http\Middleware\SetLocale;
use Filament\View\PanelsRenderHook;
use App\Filament\Shared\Pages\Profile as SharedProfile;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $adminDomain = env('FILAMENT_ADMIN_DOMAIN');

        if (empty($adminDomain) && ! app()->environment('local')) {
            $adminDomain = 'admin.mapservices.in';
        }

        return $panel
            ->id('admin')
            ->path('admin')
            ->domain($adminDomain ?: null)
            ->authGuard('web')
            ->login(\App\Filament\Admin\Pages\Auth\Login::class)
            ->passwordReset()
            ->homeUrl(fn () => route('filament.admin.pages.dashboard'))
            ->brandName('Super Admin')
            ->brandLogo(asset('images/map-logo.svg'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('images/map-logo.svg'))
            ->darkMode(false)
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
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Tenant Management')
                    ->icon('heroicon-o-building-office')
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Staff Management')
                    ->icon('heroicon-o-users')
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Operations')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsed(true),
                NavigationGroup::make()
                    ->label('Reports')
                    ->icon('heroicon-o-chart-bar'),
            ])
            ->discoverResources(in: app_path('Filament/Resources/Admin'), for: 'App\\Filament\\Resources\\Admin')
            ->discoverPages(in: app_path('Filament/Pages/Admin'), for: 'App\\Filament\\Pages\\Admin')
            ->pages([
                \App\Filament\Pages\Admin\Dashboard::class,
                \App\Filament\Pages\Admin\SuperAdminReportCenter::class,
                SharedProfile::class,
            ])
            ->globalSearch(false)
            ->discoverWidgets(in: app_path('Filament/Widgets/SuperAdmin'), for: 'App\\Filament\\Widgets\\SuperAdmin')
            ->widgets([
                \App\Filament\Widgets\SuperAdmin\GreetingWidget::class,
                \App\Filament\Widgets\SuperAdmin\SuperAdminStatsWidget::class,
            ])
            ->renderHook('panels::sidebar.start', fn () => view('components.admin-sidebar-styles'))
            ->renderHook('sidebar.start', fn () => view('components.admin-sidebar-styles'))
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => view('components.admin-sidebar-styles-head'))
            ->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn () => view('components.account-sticky'))
            ->tenant(null)
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->simplePageMaxContentWidth(MaxWidth::SevenExtraLarge)
            ->renderHook('body.start', fn () => view('components.impersonation-banner'))
            ->middleware([
                SetLocale::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SuperAdminAccess::class,
                RateLimitSuperAdmin::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
