<?php

use App\Models\Series;
use Livewire\Component;

new class extends Component
{
    public Series $series;

    public function mount(Series $series): void
    {
        if ($series->visibility !== 'public') {
            abort(404);
        }

        $series->load([
            'events' => function ($query) {
                $query->where('status', 'approved')
                    ->where('visibility', 'public')
                    ->where('starts_at', '>=', now())
                    ->orderBy('starts_at', 'asc')
                    ->take(10);
            },
        ]);

        $this->series = $series;
    }
};
?>

@php
    $series = $this->series;
@endphp

@section('title', $series->title . ' - ' . config('app.name'))

<div class="bg-slate-50 min-h-screen">
        <!-- Banner -->
        <div class="h-64 lg:h-80 bg-slate-900 relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-900/50 to-slate-900/90 z-10"></div>
            <!-- Pattern -->
            <div class="absolute inset-0 opacity-20" style="background-image: url('{{ asset('images/pattern-bg.png') }}'); background-size: 300px;"></div>
            
            <div class="container mx-auto px-6 lg:px-12 relative z-20 h-full flex items-center">
                <div class="max-w-3xl">
                    <span class="inline-block bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider mb-4">{{ __('Series') }}</span>
                    <h1 class="font-heading text-4xl lg:text-6xl font-bold text-white mb-4 leading-tight">{{ $series->title }}</h1>
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

                    <div class="border-t border-slate-200 pt-12">
                        <h2 class="font-heading text-2xl font-bold text-slate-900 mb-8">{{ __('Upcoming Events in this Series') }}</h2>
                        
                        @if($series->events->isEmpty())
                            <div class="bg-white rounded-3xl p-8 text-center border border-slate-100 shadow-sm">
                                <p class="text-slate-500">{{ __('No upcoming events scheduled for this series.') }}</p>
                            </div>
                        @else
                            <div class="space-y-6">
                                @foreach($series->events as $event)
                                    <article class="flex flex-col md:flex-row gap-6 bg-white rounded-3xl p-6 border border-slate-100 shadow-sm hover:shadow-xl hover:shadow-emerald-500/10 hover:-translate-y-1 transition-all group">
                                         <div class="md:w-48 h-32 md:h-auto rounded-2xl bg-slate-100 relative overflow-hidden flex-shrink-0">
                                            @if($event->poster_url)
                                                <img src="{{ $event->poster_url }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-emerald-50 to-teal-50 text-emerald-200">
                                                    <svg class="w-12 h-12 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                </div>
                                            @endif
                                            <div class="absolute top-2 left-2 inline-flex flex-col items-center justify-center bg-white/90 backdrop-blur-sm rounded-lg px-2 py-1 shadow-sm border border-white/50 min-w-[3rem]">
                                                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">{{ $event->starts_at?->format('M') }}</span>
                                                <span class="text-lg font-black text-slate-900 leading-none">{{ $event->starts_at?->format('d') }}</span>
                                            </div>
                                        </div>

                                        <div class="flex-grow flex flex-col justify-center">
                                            <h3 class="font-heading text-xl font-bold text-slate-900 group-hover:text-emerald-600 transition-colors mb-2">
                                                <a href="{{ route('events.show', $event) }}" wire:navigate>{{ $event->title }}</a>
                                            </h3>
                                            <div class="flex items-center gap-4 text-sm text-slate-500 mb-4">
                                                <span class="flex items-center gap-1.5">
                                                    <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                    {{ $event->starts_at?->format('l, h:i A') }}
                                                </span>
                                            </div>
                                            <p class="text-slate-600 line-clamp-2 mb-4">{{ Str::limit(strip_tags($event->description), 120) }}</p>
                                            
                                            <div class="mt-auto">
                                                <a href="{{ route('events.show', $event) }}" wire:navigate class="text-sm font-bold text-emerald-600 hover:text-emerald-700 inline-flex items-center gap-1 group-hover/link:gap-2 transition-all">
                                                    {{ __('View Details') }} &rarr;
                                                </a>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>