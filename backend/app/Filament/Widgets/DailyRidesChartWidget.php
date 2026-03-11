<?php

namespace App\Filament\Widgets;

use App\Models\Ride;
use Filament\Widgets\ChartWidget;

class DailyRidesChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Daily Completed Rides (30 days)';
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $rides = Ride::selectRaw("DATE(created_at) as date, COUNT(*) as count")
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        return [
            'datasets' => [
                [
                    'label'           => 'Completed Rides',
                    'data'            => $rides->values()->toArray(),
                    'borderColor'     => '#6366f1',
                    'backgroundColor' => 'rgba(99,102,241,0.1)',
                    'fill'            => true,
                    'tension'         => 0.4,
                ],
            ],
            'labels' => $rides->keys()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
