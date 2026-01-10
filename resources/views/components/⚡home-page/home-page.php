<?php

use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\State;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public function getFeaturedEventsProperty(): Collection
    {
        return Event::query()
            ->where('status', 'approved')
            ->where('visibility', 'public')
            ->whereNotNull('published_at')
            ->where('starts_at', '>=', now()->subDay())
            ->with(['institution', 'venue', 'state', 'speakers', 'topics'])
            ->orderBy('starts_at')
            ->limit(6)
            ->get();
    }

    public function getTrustedInstitutionsProperty(): Collection
    {
        return Institution::query()
            ->orderByDesc('trust_score')
            ->limit(6)
            ->get();
    }

    public function getFeaturedSpeakersProperty(): Collection
    {
        return Speaker::query()
            ->orderByDesc('trust_score')
            ->limit(6)
            ->get();
    }

    public function getTopicHighlightsProperty(): Collection
    {
        return Topic::query()
            ->orderByDesc('is_official')
            ->orderBy('name')
            ->limit(12)
            ->get();
    }

    public function getStatesProperty(): Collection
    {
        return State::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return array{events:int, institutions:int, speakers:int}
     */
    public function getStatsProperty(): array
    {
        return [
            'events' => Event::query()
                ->where('status', 'approved')
                ->where('visibility', 'public')
                ->whereNotNull('published_at')
                ->count(),
            'institutions' => Institution::query()->count(),
            'speakers' => Speaker::query()->count(),
        ];
    }
};
