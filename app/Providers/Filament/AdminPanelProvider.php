<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\LocalAvatarProvider;
use App\Http\Middleware\DesktopAutoLogin;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
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
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile()
            ->brandName('حساب نقاط المسابقات')
            ->brandLogo(asset('images/brand/lockup-horizontal.svg'))
            ->darkModeBrandLogo(asset('images/brand/lockup-horizontal-white.svg'))
            ->brandLogoHeight('2.6rem')
            ->favicon(asset('favicon.svg'))
            ->colors([
                'primary' => Color::Emerald,
                'gray' => Color::Slate,
                'info' => Color::Sky,
                'success' => Color::Green,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
            ])
            // خط Cairo محلي (يعمل دون إنترنت)
            ->font('Cairo', url: asset('fonts/cairo/cairo.css'), provider: LocalFontProvider::class)
            ->defaultAvatarProvider(LocalAvatarProvider::class)
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->databaseNotifications(false)
            ->navigationGroups([
                NavigationGroup::make('إدارة المسابقات')->icon(Heroicon::OutlinedFolder),
                NavigationGroup::make('الأدوات')->icon(Heroicon::OutlinedWrenchScrewdriver),
            ])
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => '<link rel="stylesheet" href="'.asset('css/scoring.css').'?v=2">')
            ->renderHook(PanelsRenderHook::FOOTER, fn () => view('filament.footer'))
            // نبض دوري يخبر برنامج سطح المكتب أن النافذة ما زالت مفتوحة
            ->renderHook(PanelsRenderHook::BODY_END, fn () => config('app.desktop')
                ? '<script>(function(){var p=function(){fetch("/__desktop/ping",{cache:"no-store"}).catch(function(){})};p();setInterval(p,15000);})();</script>'
                : '')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                DesktopAutoLogin::class,
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
    }
}
