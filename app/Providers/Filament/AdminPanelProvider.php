<?php

namespace App\Providers\Filament;

use App\Filament\Navigation\NocNavigation;
use App\Filament\Pages\ComingSoon;
use App\Filament\Pages\NocDashboard;
use App\Filament\Pages\NetworkDiscovery;
use App\Filament\Pages\NocWallboard;
use App\Filament\Pages\Devices;
use App\Filament\Pages\Settings;
use App\Filament\Widgets\BgpStatus;
use App\Filament\Widgets\DiscoveredDevices;
use App\Filament\Widgets\NetworkTopology;
use App\Filament\Widgets\NocOverviewMetrics;
use App\Filament\Widgets\RecentEvents;
use App\Filament\Widgets\RouterMonitoring;
use Filament\Actions\Action;
use Filament\Enums\ThemeMode;
use Filament\Enums\UserMenuPosition;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('MikroTik Network Management & NOC')
            ->font('Instrument Sans')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->darkMode()
            ->defaultThemeMode(ThemeMode::System)
            ->themeSwitcher(false)
            ->profile()
            ->userMenu(true, UserMenuPosition::Topbar)
            ->userMenuItems([
                'profile' => fn (Action $action): Action => $action->label('Profile'),
                'logout' => fn (Action $action): Action => $action->label('Logout'),
            ])
            ->renderHook(
                PanelsRenderHook::USER_MENU_PROFILE_BEFORE,
                fn (): View => view('filament.partials.user-menu-profile-summary'),
            )
            ->globalSearch(false)
            ->databaseNotifications(false)
            ->navigationGroups(NocNavigation::groups())
            ->navigationItems(NocNavigation::items())
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                NocDashboard::class,
                ComingSoon::class,
                NetworkDiscovery::class,
                Devices::class,
                Settings::class,
                NocWallboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                NocOverviewMetrics::class,
                RouterMonitoring::class,
                DiscoveredDevices::class,
                BgpStatus::class,
                NetworkTopology::class,
                RecentEvents::class,
            ])
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn (): View => view('filament.partials.sidebar-brand-subtitle'),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_LOGO_AFTER,
                fn (): View => view('filament.partials.topbar-brand-subtitle'),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): View => view('filament.partials.topbar-search-placeholder'),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): View => view('filament.partials.topbar-controls'),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);

        if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'))) {
            $panel->viteTheme('resources/css/filament/admin/theme.css');
        }

        return $panel;
    }
}
