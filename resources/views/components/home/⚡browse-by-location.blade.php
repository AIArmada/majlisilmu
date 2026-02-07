<?php

use App\Models\Address;
use App\Models\Event;
use App\Models\State;
use App\Models\Venue;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function popularStates(): Collection
    {
        $now = now();

        // Count events per state through venue->address relationship
        $eventCountsByState = Event::query()
            ->where('events.status', 'approved')
            ->where('events.visibility', 'public')
            ->where('events.starts_at', '>=', $now)
            ->whereNotNull('events.venue_id')
            ->join('venues', 'events.venue_id', '=', 'venues.id')
            ->where('venues.status', 'verified')
            ->join('addresses', function ($join) {
                $join->on('addresses.addressable_id', '=', 'venues.id')
                    ->where('addresses.addressable_type', '=', Venue::class);
            })
            ->whereNotNull('addresses.state_id')
            ->selectRaw('addresses.state_id, count(*) as events_count')
            ->groupBy('addresses.state_id')
            ->pluck('events_count', 'state_id');

        if ($eventCountsByState->isEmpty()) {
            return collect();
        }

        return State::query()
            ->whereIn('id', $eventCountsByState->keys())
            ->get()
            ->map(function ($state) use ($eventCountsByState) {
                $state->events_count = $eventCountsByState[$state->id] ?? 0;
                return $state;
            })
            ->filter(fn($state) => $state->events_count > 0)
            ->sortByDesc('events_count')
            ->take(8)
            ->values();
    }

    #[Computed]
    public function popularTitles(): Collection
    {
        $now = now();

        return Event::query()
            ->where('status', 'approved')
            ->where('visibility', 'public')
            ->where('starts_at', '>=', $now)
            ->selectRaw('title, count(*) as events_count')
            ->groupBy('title')
            ->orderByDesc('events_count')
            ->limit(12)
            ->get();
    }
};
?>

@placeholder
<section class="bg-white py-20">
    <div class="container mx-auto px-6 lg:px-12">
        <div class="flex items-center justify-center py-12">
            <svg class="animate-spin h-8 w-8 text-emerald-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
    </div>
</section>
@endplaceholder

<section class="bg-white py-20">
    <div class="container mx-auto px-6 lg:px-12">
        <div class="grid lg:grid-cols-2 gap-12">
            <!-- Browse by State -->
            <div>
                <div class="flex items-center gap-3 mb-8">
                    <div class="p-3 rounded-xl bg-blue-100 text-blue-600">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Cari Mengikut Negeri') }}</h2>
                        <p class="text-slate-500 text-sm">{{ __('Majlis ilmu berdekatan dengan anda') }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    @foreach($this->popularStates as $state)
                        <div wire:key="state-{{ $state->id }}">
                            <a href="{{ route('events.index', ['state_id' => $state->id]) }}" wire:navigate
                                class="group flex items-center justify-between p-4 rounded-xl bg-slate-50 hover:bg-blue-50 border border-slate-100 hover:border-blue-200 transition-all">
                                <span class="font-medium text-slate-700 group-hover:text-blue-700">{{ $state->name }}</span>
                                <span
                                    class="text-sm font-bold text-slate-400 group-hover:text-blue-600">{{ $state->events_count }}</span>
                            </a>
                        </div>
                    @endforeach
                </div>

                <a href="{{ route('events.index') }}" wire:navigate
                    class="inline-flex items-center gap-2 mt-6 text-blue-600 font-semibold hover:text-blue-700">
                    {{ __('Lihat semua negeri') }}
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 8l4 4m0 0l-4 4m4-4H3" />
                    </svg>
                </a>
            </div>

            <!-- Browse by Title -->
            <div>
                <div class="flex items-center gap-3 mb-8">
                    <div class="p-3 rounded-xl bg-purple-100 text-purple-600">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Cari Mengikut Tajuk') }}</h2>
                        <p class="text-slate-500 text-sm">{{ __('Majlis popular berdasarkan tajuk') }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    @foreach($this->popularTitles as $item)
                        <div wire:key="title-{{ $loop->index }}">
                            <a href="{{ route('events.index', ['search' => $item->title]) }}" wire:navigate
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-purple-50 text-purple-700 font-medium hover:bg-purple-100 border border-purple-100 hover:border-purple-200 transition-all">
                                {{ $item->title }}
                                @if($item->events_count > 1)
                                    <span
                                        class="text-xs bg-purple-200 text-purple-800 px-2 py-0.5 rounded-full">{{ $item->events_count }}</span>
                                @endif
                            </a>
                        </div>
                    @endforeach
                </div>

                <a href="{{ route('events.index') }}" wire:navigate
                    class="inline-flex items-center gap-2 mt-6 text-purple-600 font-semibold hover:text-purple-700">
                    {{ __('Lihat semua majlis') }}
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 8l4 4m0 0l-4 4m4-4H3" />
                    </svg>
                </a>
            </div>
        </div>
    </div>
</section>