<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        return [
            Stat::make('Total Events', Event::count())
                ->description('All scheduled events')
                ->descriptionIcon('heroicon-m-calendar')
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->color('primary'),
            Stat::make('Active Speakers', Speaker::count())
                ->description('Registered speakers')
                ->descriptionIcon('heroicon-m-microphone')
                ->chart([15, 4, 10, 2, 12, 4, 12])
                ->color('success'),
            Stat::make('Institutions', Institution::count())
                ->description('Partnered institutions')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('info'),
        ];
    }
}
