<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use AIArmada\FilamentSignals\Pages\PageViewsReport;
use App\Services\ShareTracking\AdminShareAnalyticsService;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

final class ShareAnalytics extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-share';

    protected static string|UnitEnum|null $navigationGroup = 'Insights';

    protected static ?int $navigationSort = 11;

    protected static ?string $title = 'Share Analytics';

    protected string $view = 'filament.pages.share-analytics';

    /**
     * @var array{
     *     summary: array<string, int>,
     *     provider_breakdown: list<array<string, int|string>>,
     *     top_sharers: list<array<string, int|string|null>>,
     *     top_links: list<array<string, int|string|null>>,
     *     recent_visits: list<array<string, int|string|null>>,
     *     recent_outcomes: list<array<string, int|string|null>>
     * }
     */
    public array $report = [];

    public ?string $signalsReportUrl = null;

    public function mount(AdminShareAnalyticsService $analytics): void
    {
        $this->report = $analytics->dashboard();
        $this->signalsReportUrl = class_exists(PageViewsReport::class)
            ? PageViewsReport::getUrl(panel: 'admin')
            : null;
    }
}
