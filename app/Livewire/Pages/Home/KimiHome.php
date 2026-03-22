<?php

namespace App\Livewire\Pages\Home;

use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use App\Support\Cache\SafeModelCache;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Majlis Ilmu - Cari Kuliah & Majlis Ilmu di Malaysia')]
class KimiHome extends Component
{
    /**
     * @var array<string, int>
     */
    public array $stats = [];

    /**
     * @var Collection<int, Event>
     */
    public Collection $featuredEvents;

    /**
     * @var Collection<int, Event>
     */
    public Collection $upcomingEvents;

    /**
     * @var array<int, array{name: string, icon: string, color: string, search: string}>
     */
    public array $categories = [];

    public function mount(): void
    {
        $this->loadStats();
        $this->loadFeaturedEvents();
        $this->loadUpcomingEvents();
        $this->loadCategories();
    }

    private function loadStats(): void
    {
        $this->stats = Cache::remember('kimi_home_stats', 300, fn () => [
            'events' => Event::count(),
            'institutions' => Institution::count(),
            'speakers' => Speaker::count(),
            'this_week' => Event::whereBetween('starts_at', [now(), now()->addWeek()])->count(),
        ]);
    }

    private function loadFeaturedEvents(): void
    {
        /** @var list<string> $featuredEventIds */
        $featuredEventIds = array_values(array_map(
            static fn (mixed $eventId): string => (string) $eventId,
            app(SafeModelCache::class)->rememberScalarList(
                key: 'kimi_featured_events_v2',
                ttl: 300,
                resolver: fn (): array => Event::query()
                    ->where('starts_at', '>=', now())
                    ->active()
                    ->orderBy('starts_at')
                    ->limit(6)
                    ->pluck('id')
                    ->all(),
            ),
        ));

        $this->featuredEvents = $this->orderedEvents(
            eventIds: $featuredEventIds,
            relationships: ['institution', 'speakers', 'media'],
        );
    }

    private function loadUpcomingEvents(): void
    {
        /** @var list<string> $upcomingEventIds */
        $upcomingEventIds = array_values(array_map(
            static fn (mixed $eventId): string => (string) $eventId,
            app(SafeModelCache::class)->rememberScalarList(
                key: 'kimi_upcoming_events_v2',
                ttl: 300,
                resolver: fn (): array => Event::query()
                    ->where('starts_at', '>=', now())
                    ->active()
                    ->orderBy('starts_at')
                    ->limit(4)
                    ->pluck('id')
                    ->all(),
            ),
        ));

        $this->upcomingEvents = $this->orderedEvents(
            eventIds: $upcomingEventIds,
            relationships: ['institution', 'speakers'],
        );
    }

    private function loadCategories(): void
    {
        $this->categories = [
            ['name' => 'Tazkirah', 'icon' => 'book-open', 'color' => 'emerald', 'search' => 'Tazkirah'],
            ['name' => 'Tafsir', 'icon' => 'document-text', 'color' => 'blue', 'search' => 'Tafsir'],
            ['name' => 'Fiqh', 'icon' => 'scale', 'color' => 'amber', 'search' => 'Fiqh'],
            ['name' => 'Aqidah', 'icon' => 'star', 'color' => 'violet', 'search' => 'Aqidah'],
            ['name' => 'Sirah', 'icon' => 'users', 'color' => 'rose', 'search' => 'Sirah'],
            ['name' => 'Hadith', 'icon' => 'chat-bubble-left-right', 'color' => 'teal', 'search' => 'Hadith'],
        ];
    }

    public function render(): View
    {
        return view('livewire.pages.home.kimi-home');
    }

    /**
     * @param  list<string>  $eventIds
     * @param  array<int|string, mixed>  $relationships
     * @return Collection<int, Event>
     */
    private function orderedEvents(array $eventIds, array $relationships): Collection
    {
        if ($eventIds === []) {
            return new Collection;
        }

        /** @var Collection<int, Event> $events */
        $events = Event::query()
            ->with($relationships)
            ->whereKey($eventIds)
            ->get()
            ->keyBy(fn (Event $event): string => (string) $event->getKey());

        return new Collection(
            collect($eventIds)
                ->map(fn (string $eventId): ?Event => $events->get($eventId))
                ->filter()
                ->values()
                ->all(),
        );
    }
}
