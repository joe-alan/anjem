<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\DispatchTimelinePage;
use App\Filament\Pages\DriverQueuePage;
use App\Filament\Pages\LiveMapPage;
use App\Filament\Pages\LiveMonitoringPage;
use App\Filament\Pages\SystemHealthPage;
use App\Filament\Resources\AuditLogResource;
use App\Filament\Resources\DriverProfileResource;
use App\Filament\Resources\DriverResource;
use App\Filament\Resources\FailedJobResource;
use App\Filament\Resources\KycResource;
use App\Filament\Resources\LocationResource;
use App\Filament\Resources\RideRequestResource;
use App\Filament\Resources\RideResource;
use App\Filament\Resources\RiderResource;
use App\Filament\Resources\RouteCacheResource;
use App\Filament\Widgets\DailyRidesChartWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
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
            ->domain(env('FILAMENT_DOMAIN', 'admin.anjem.me'))
            ->path('')
            ->login()
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('14rem')
            ->collapsedSidebarWidth('4.5rem')
            ->navigationGroups([
                'Users',
                'Rides',
                'System',
            ])
            ->resources([
                DriverResource::class,
                KycResource::class,
                RiderResource::class,
                DriverProfileResource::class,
                RideResource::class,
                RideRequestResource::class,
                LocationResource::class,
                RouteCacheResource::class,
                AuditLogResource::class,
                FailedJobResource::class,
            ])
            ->pages([
                Dashboard::class,
                LiveMonitoringPage::class,
                DriverQueuePage::class,
                LiveMapPage::class,
                DispatchTimelinePage::class,
                SystemHealthPage::class,
            ])
            ->widgets([
                StatsOverviewWidget::class,
                DailyRidesChartWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
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
    }
}
