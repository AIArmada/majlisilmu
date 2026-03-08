<?php

namespace App\Filament\Pages;

use App\Filament\Ahli\Widgets\PendingApprovalEventsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class AhliDashboard extends BaseDashboard
{
    protected static bool $isDiscovered = false;

    protected static ?string $title = 'Dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    /**
     * @return array<class-string>
     */
    #[\Override]
    public function getWidgets(): array
    {
        return [
            PendingApprovalEventsWidget::class,
        ];
    }

    /**
     * @return int|array<string, ?int>
     */
    #[\Override]
    public function getColumns(): int|array
    {
        return 1;
    }
}
