<?php

namespace App\Livewire\Pages\Dashboard;

use App\Models\DawahShareLink;
use App\Models\User;
use App\Services\DawahShare\DawahShareAnalyticsService;
use App\Services\DawahShare\DawahShareService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class DawahImpactLinkShow extends Component
{
    public DawahShareLink $link;

    public function mount(DawahShareLink $link): void
    {
        abort_unless($link->user_id === $this->currentUser()->id, 404);

        $this->link = $link;
    }

    /**
     * @return array{
     *     visits: int,
     *     unique_visitors: int,
     *     outcomes: int,
     *     signups: int,
     *     event_registrations: int
     * }
     */
    #[Computed]
    public function summary(): array
    {
        return $this->analytics()->summaryForLink($this->link);
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function shareLinks(): array
    {
        return $this->shareService()->redirectLinks(
            $this->link->destination_url,
            trim(($this->link->title_snapshot ?: config('app.name')).' - '.config('app.name')),
            $this->link->title_snapshot,
        );
    }

    /**
     * @return Collection<int, array{
     *     date: string,
     *     visits: int,
     *     outcomes: int,
     *     signups: int,
     *     event_registrations: int
     * }>
     */
    #[Computed]
    public function dailyPerformance(): Collection
    {
        return $this->analytics()->dailyPerformanceForLink($this->link, 14);
    }

    /**
     * @return Collection<int, array{outcome_type: string, label: string, count: int}>
     */
    #[Computed]
    public function outcomeBreakdown(): Collection
    {
        return $this->analytics()->outcomeBreakdownForLink($this->link);
    }

    /**
     * @return Collection<int, \App\Models\DawahShareVisit>
     */
    #[Computed]
    public function recentVisits(): Collection
    {
        return $this->analytics()->recentVisitsForLink($this->link, 20);
    }

    /**
     * @return Collection<int, \App\Models\DawahShareOutcome>
     */
    #[Computed]
    public function recentOutcomes(): Collection
    {
        return $this->analytics()->recentOutcomesForLink($this->link, 20);
    }

    /**
     * @return array{
     *     first_seen_at: \Carbon\CarbonInterface|null,
     *     last_visit_at: \Carbon\CarbonInterface|null,
     *     last_outcome_at: \Carbon\CarbonInterface|null,
     *     latest_activity_at: \Carbon\CarbonInterface|null
     * }
     */
    #[Computed]
    public function activityWindow(): array
    {
        return $this->analytics()->activityWindowForLink($this->link);
    }

    protected function analytics(): DawahShareAnalyticsService
    {
        return app(DawahShareAnalyticsService::class);
    }

    protected function shareService(): DawahShareService
    {
        return app(DawahShareService::class);
    }

    protected function currentUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    public function render(): View
    {
        return view('livewire.pages.dashboard.dawah-impact-link-show');
    }
}
