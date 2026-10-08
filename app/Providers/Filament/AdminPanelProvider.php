<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\LmsStatsOverview;
use App\Http\Middleware\AddRequestIdToLogs;
use App\Http\Middleware\FilamentAuthenticate;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SyncPanelGuards;
use App\Models\PortalSetting;
use Filament\Actions\Action;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
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
            ->brandName(function () {
                $name = PortalSetting::current()->portal_name;

                // While the portal still carries its stock name, the admin
                // brand is a translatable label so it follows the TR/EN
                // switch; a name an admin actually typed is shown verbatim.
                return ($name === null || $name === '' || $name === PortalSetting::DEFAULT_NAME)
                    ? __('Arucad Eğitim Merkezi Yönetimi')
                    : $name;
            })
            ->brandLogo(fn () => PortalSetting::current()->logoUrl())
            ->brandLogoHeight('2rem')
            ->favicon(fn () => PortalSetting::current()->logoUrl())
            ->sidebarCollapsibleOnDesktop()
            ->spa()
            ->login()
            ->authGuard('admin')
            ->colors([
                'primary' => Color::hex('#4D6787'),
                'info' => Color::hex('#898AA6'),
                'success' => Color::hex('#94A378'),
                'warning' => Color::hex('#F2D388'),
            ])
            ->navigationGroups([
                NavigationGroup::make(fn () => __('Katalog')),
                NavigationGroup::make(fn () => __('Eğitim yönetimi')),
                NavigationGroup::make(fn () => __('Canlı sınıflar')),
                NavigationGroup::make(fn () => __('Katılımcılar')),
                NavigationGroup::make(fn () => __('Sistem')),
            ])
            ->renderHook(PanelsRenderHook::CONTENT_START, fn () => view('filament.hooks.back-link'))
            ->userMenuItems([
                Action::make('locale_tr')
                    ->label('Türkçe')
                    ->icon(Heroicon::Language)
                    ->url(fn () => route('language.switch', 'tr'))
                    ->visible(fn () => session('locale', 'tr') !== 'tr'),
                Action::make('locale_en')
                    ->label('English')
                    ->icon(Heroicon::Language)
                    ->url(fn () => route('language.switch', 'en'))
                    ->visible(fn () => session('locale', 'tr') === 'tr'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                LmsStatsOverview::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                AddRequestIdToLogs::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocale::class,
                SyncPanelGuards::class,
            ])
            ->authMiddleware([
                FilamentAuthenticate::class,
            ]);
    }
}
