<?php

namespace App\Filament\Widgets;

use App\Models\DriverProfile;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class KycPendingWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $count = DriverProfile::where('is_verified', false)->count();

        return [
            Stat::make('KYC Pending', $count)
                ->description('Drivers awaiting admin approval')
                ->icon('heroicon-o-identification')
                ->color($count > 0 ? 'danger' : 'success')
                ->url(route('filament.admin.resources.drivers.index', ['tableFilters[kyc_status][value]' => 'pending'])),
        ];
    }
}
