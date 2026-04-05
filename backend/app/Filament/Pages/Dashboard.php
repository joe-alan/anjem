<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DailyRidesChartWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?int $navigationSort = -2;

    public function getWidgets(): array
    {
        return [
            StatsOverviewWidget::class,
            DailyRidesChartWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 1;
    }
}
