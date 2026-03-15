<?php

use App\Models\Event;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function events(): Collection
    {
        $now = now();

        return Event::active()
            ->where('starts_at', '>=', $now)
            ->where('starts_at', '<=', $now->copy()->addDays(7))
            ->orderByDesc('is_featured')
            ->orderByRaw('(going_count * 5 + saves_count * 2 + views_count * 0.1) DESC')
            ->orderBy('starts_at')
            ->with([
                'media' => fn ($query) => $query
                    ->where('collection_name', 'poster')
                    ->ordered(),
                'institution.media' => fn ($query) => $query
                    ->where('collection_name', 'logo')
                    ->ordered(),
                'speakers.media' => fn ($query) => $query
                    ->where('collection_name', 'avatar')
                    ->ordered(),
            ])
            ->take(6)
            ->get();
    }
};
?>

@php
    $hasEvents = $this->events->isNotEmpty();
    $featuredEvent = $this->events->first();
    $otherEvents = $this->events->skip(1);
@endphp

@placeholder
<section class="bg-white py-16">
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

<section class="bg-white py-16" @if(!$hasEvents) style="display: none;" @endif>
    <div class="container mx-auto px-6 lg:px-12">
        <!-- Section Header -->
        <div class="flex items-end justify-between mb-10">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gold-100 text-gold-700 text-sm font-medium mb-3">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                    {{ __('Pilihan Minggu Ini') }}
                </div>
                <h2 class="text-3xl font-bold text-slate-900">{{ __('Majlis Pilihan') }}</h2>
            </div>
            <a href="{{ route('events.index') }}" wire:navigate
                class="hidden sm:inline-flex items-center gap-2 text-emerald-600 font-semibold hover:text-emerald-700 transition-colors">
                {{ __('Lihat Semua') }}
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                </svg>
            </a>
        </div>
        
        <!-- Bento Grid -->
        <div class="grid lg:grid-cols-3 gap-6">
            @if($featuredEvent)
                <!-- Large Featured Card -->
                <div class="lg:col-span-2 lg:row-span-2">
                    <article class="group relative h-full min-h-[400px] lg:min-h-[500px] rounded-3xl overflow-hidden bg-slate-100">
                        <!-- Background Image -->
                        <img src="{{ $featuredEvent->card_image_url }}" 
                            alt="{{ $featuredEvent->title }}"
                            class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                        
                        <!-- Gradient Overlay -->
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/90 via-slate-900/40 to-transparent"></div>
                        
                        <!-- Content -->
                        <div class="absolute inset-0 p-8 flex flex-col justify-end">
                            <!-- Date Badge -->
                            <div class="absolute top-6 left-6 flex items-center gap-3">
                                <div class="bg-white rounded-xl px-3 py-2 shadow-lg">
                                    <div class="text-xs font-bold text-slate-400 uppercase">{{ \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($featuredEvent->starts_at, 'M') }}</div>
                                    <div class="text-xl font-black text-slate-900 leading-none">{{ \App\Support\Timezone\UserDateTimeFormatter::format($featuredEvent->starts_at, 'd') }}</div>
                                </div>
                                @if($featuredEvent->isPrayerRelative() && $featuredEvent->prayer_display_text)
                                    <div class="bg-emerald-500 text-white text-xs font-bold px-3 py-1.5 rounded-full">
                                        {{ $featuredEvent->prayer_display_text }}
                                    </div>
                                @endif
                            </div>
                            
                            <!-- Tags -->
                            <div class="flex items-center gap-2 mb-3">
                                <span class="inline-block rounded-full bg-white/20 backdrop-blur-sm px-3 py-1 text-xs font-medium text-white">
                                    {{ $featuredEvent->eventType?->name ?? __('Kuliah') }}
                                </span>
                                @if($featuredEvent->speakers->isNotEmpty())
                                    <span class="inline-block rounded-full bg-emerald-500/80 backdrop-blur-sm px-3 py-1 text-xs font-medium text-white">
                                        {{ $featuredEvent->speakers->first()?->name }}
                                    </span>
                                @endif
                            </div>
                            
                            <!-- Title -->
                            <h3 class="text-2xl lg:text-3xl font-bold text-white mb-3">
                                <a href="{{ route('events.show', $featuredEvent) }}" wire:navigate class="hover:text-emerald-300 transition-colors">
                                    {{ $featuredEvent->title }}
                                </a>
                            </h3>
                            
                            <!-- Meta -->
                            <div class="flex items-center gap-4 text-white/80 text-sm">
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    </svg>
                                    <span>{{ $featuredEvent->venue?->name ?? $featuredEvent->institution?->name ?? __('Online') }}</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>{{ \App\Support\Timezone\UserDateTimeFormatter::format($featuredEvent->starts_at, 'h:i A') }}</span>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            @endif
            
            <!-- Smaller Cards -->
            @foreach($otherEvents->take(4) as $event)
                <div class="{{ $loop->first || $loop->second ? 'lg:col-span-1' : 'lg:col-span-1' }}">
                    <article class="group relative h-[200px] lg:h-[240px] rounded-2xl overflow-hidden bg-slate-100">
                        <!-- Background Image -->
                        <img src="{{ $event->card_image_url }}" 
                            alt="{{ $event->title }}"
                            class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                        
                        <!-- Gradient Overlay -->
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 to-transparent"></div>
                        
                        <!-- Content -->
                        <div class="absolute inset-0 p-5 flex flex-col justify-end">
                            <!-- Date Badge -->
                            <div class="absolute top-4 left-4 bg-white rounded-lg px-2 py-1 shadow-md">
                                <div class="text-xs font-bold text-slate-400 uppercase">{{ \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($event->starts_at, 'M') }}</div>
                                <div class="text-lg font-black text-slate-900 leading-none">{{ \App\Support\Timezone\UserDateTimeFormatter::format($event->starts_at, 'd') }}</div>
                            </div>
                            
                            <!-- Title -->
                            <h3 class="text-lg font-bold text-white line-clamp-2 mb-1">
                                <a href="{{ route('events.show', $event) }}" wire:navigate class="hover:text-emerald-300 transition-colors">
                                    {{ $event->title }}
                                </a>
                            </h3>
                            
                            <!-- Meta -->
                            <div class="flex items-center gap-2 text-white/70 text-xs">
                                <span>{{ \App\Support\Timezone\UserDateTimeFormatter::format($event->starts_at, 'h:i A') }}</span>
                                <span>•</span>
                                <span class="truncate">{{ $event->venue?->name ?? $event->institution?->name ?? __('Online') }}</span>
                            </div>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
        
        <!-- Mobile CTA -->
        <div class="mt-8 text-center sm:hidden">
            <a href="{{ route('events.index') }}" wire:navigate
                class="inline-flex items-center justify-center gap-2 h-12 px-8 rounded-full bg-emerald-600 text-white font-semibold shadow-lg shadow-emerald-500/20 hover:bg-emerald-700 transition-colors">
                {{ __('Lihat Semua Majlis') }}
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                </svg>
            </a>
        </div>
    </div>
</section>
