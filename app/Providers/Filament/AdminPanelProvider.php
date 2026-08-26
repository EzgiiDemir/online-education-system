<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\LmsStatsOverview;
use App\Http\Middleware\AddRequestIdToLogs;
use App\Http\Middleware\SetLocale;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
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
            ->brandName('Arucad Sürekli Öğrenme Merkezi Yönetimi')
            ->brandLogo(asset('images/sdg/arucad_home_logo.png'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('images/sdg/arucad_home_logo.png'))
            ->sidebarCollapsibleOnDesktop()
            ->spa()
            ->login()
            ->colors([
                'primary' => Color::hex('#4D6787'),
                'info' => Color::hex('#898AA6'),
                'success' => Color::hex('#94A378'),
                'warning' => Color::hex('#F2D388'),
            ])
            ->navigationGroups(['Katalog', 'Eğitim yönetimi', 'Canlı sınıflar', 'Katılımcılar', 'Sistem'])
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
