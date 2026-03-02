<?php

use App\Models\Series;
use Livewire\Component;

new class extends Component {
    public Series $series;

    public function mount(Series $series): void
    {
        if ($series->visibility !== 'public') {
            abort(404);
        }

        $series->load([
            'events' => function ($query) {
                $query->active()
                    ->with(['media', 'institution.media'])
                    ->orderBy('starts_at', 'asc');
            },
        ]);

        $this->series = $series;
    }
};
?>

@php
    $series = $this->series;
    $now = now();
    $upcomingEvents = $series->events->filter(fn($e) => $e->starts_at && $e->starts_at->gte($now))->values();
    $pastEvents = $series->events->filter(fn($e) => $e->starts_at && $e->starts_at->lt($now))->sortByDesc('starts_at')->values();
@endphp

@section('title', $series->title . ' - ' . config('app.name'))

<div class="bg-slate-50 min-h-screen">
    <!-- Banner -->
    <div class="h-64 lg:h-80 bg-slate-900 relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-emerald-900/50 to-slate-900/90 z-10"></div>
        <!-- Pattern -->
        <div class="absolute inset-0 opacity-20"
            style="background-image: url('{{ asset('images/pattern-bg.png') }}'); background-size: 300px;"></div>

        <div class="container mx-auto px-6 lg:px-12 relative z-20 h-full flex items-center">
            <div class="max-w-3xl">
                <span
                    class="inline-block bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider mb-4">{{ __('Series') }}</span>
                <h1 class="font-heading text-4xl lg:text-6xl font-bold text-white mb-4 leading-tight">
                    {{ $series->title }}
                </h1>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-6 lg:px-12 relative z-20 py-16">
        <div class="grid lg:grid-cols-3 gap-12">
            <div class="lg:col-span-2 space-y-12">
                @if($series->description)
                    <div class="prose prose-slate max-w-none text-lg leading-relaxed">
                        {!! $series->description !!}
                    </div>
                @endif

                {{-- UPCOMING EVENTS --}}
                <div class="border-t border-slate-200 pt-12">
                    <h2 class="font-heading text-2xl font-bold text-slate-900 mb-8">
                        {{ __('Upcoming Events in this Series') }}
                    </h2>

                    @if($upcomingEvents->isEmpty())
                        <div class="bg-white rounded-3xl p-8 text-center border border-slate-100 shadow-sm">
                            <p class="text-slate-500">{{ __('No upcoming events scheduled for this series.') }}</p>
                        </div>
                    @else
                        <div class="space-y-6">
                            @foreach($upcomingEvents as $event)
                                @include('components.pages.series._event-card', ['event' => $event])
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- PAST EVENTS --}}
                @if($pastEvents->isNotEmpty())
                    <div class="border-t border-slate-200 pt-12">
                        <h2 class="font-heading text-2xl font-bold text-slate-900 mb-2">{{ __('Past Events') }}</h2>
                        <p class="text-sm text-slate-500 mb-8">{{ __('Previously held events in this series.') }}</p>
                        <div class="space-y-6 opacity-80">
                            @foreach($pastEvents as $event)
                                @include('components.pages.series._event-card', ['event' => $event, 'past' => true])
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>