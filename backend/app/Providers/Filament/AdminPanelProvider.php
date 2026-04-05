<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\DriverQueuePage;
use App\Filament\Pages\LiveMonitoringPage;
use App\Filament\Pages\SystemHealthPage;
use App\Filament\Resources\AuditLogResource;
use App\Filament\Resources\FailedJobResource;
use App\Filament\Resources\DriverResource;
use App\Filament\Resources\KycResource;
use App\Filament\Resources\RiderResource;
use App\Filament\Resources\RideResource;
use App\Filament\Widgets\DailyRidesChartWidget;
use App\Filament\Widgets\KycPendingWidget;
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
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->navigationGroups([
                'Users',
                'Rides',
                'System',
            ])
            ->resources([
                DriverResource::class,
                KycResource::class,
                RiderResource::class,
                RideResource::class,
                AuditLogResource::class,
                FailedJobResource::class,
            ])
            ->pages([
                Dashboard::class,
                LiveMonitoringPage::class,
                DriverQueuePage::class,
                SystemHealthPage::class,
            ])
            ->widgets([
                StatsOverviewWidget::class,
                DailyRidesChartWidget::class,
                KycPendingWidget::class,
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
