<?php

use App\Models\Event;
use App\Services\CalendarService;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Event $event;

    public bool $isSaved = false;

    public bool $isInterested = false;

    public int $interestsCount = 0;

    public function mount(Event $event): void
    {
        if ($event->status !== 'approved' || $event->visibility !== 'public' || $event->published_at === null) {
            abort(404);
        }

        $event->load(['institution', 'venue', 'speakers', 'topics', 'donationAccount']);

        $this->event = $event;
        $this->syncEngagementStates();
    }

    #[Computed]
    public function calendarLinks(): array
    {
        return app(CalendarService::class)->getAllCalendarLinks($this->event);
    }

    public function toggleSave(): void
    {
        $user = auth()->user();

        if (! $user) {
            $this->redirectRoute('login');
            return;
        }

        if ($this->event->status !== 'approved' || $this->event->visibility !== 'public' || $this->event->published_at === null) {
            abort(403);
        }

        if ($this->isSaved) {
            $detached = $user->savedEvents()->detach($this->event->id);

            if ($detached) {
                $this->event->decrement('saves_count');
            }

            $this->isSaved = false;
            return;
        }

        $changes = $user->savedEvents()->syncWithoutDetaching([$this->event->id]);

        if (! empty($changes['attached'])) {
            $this->event->increment('saves_count');
        }

        $this->isSaved = true;
    }

    public function toggleInterest(): void
    {
        $user = auth()->user();

        if (! $user) {
            $this->redirectRoute('login');
            return;
        }

        if ($this->event->status !== 'approved' || $this->event->visibility !== 'public' || $this->event->published_at === null) {
            abort(403);
        }

        if ($this->event->starts_at && $this->event->starts_at->isPast()) {
            abort(403);
        }

        if ($this->isInterested) {
            $detached = $user->interestedEvents()->detach($this->event->id);

            if ($detached) {
                $this->event->decrement('interests_count');
                $this->interestsCount = max(0, $this->interestsCount - 1);
            }

            $this->isInterested = false;
            return;
        }

        $changes = $user->interestedEvents()->syncWithoutDetaching([$this->event->id]);

        if (! empty($changes['attached'])) {
            $this->event->increment('interests_count');
            $this->interestsCount++;
        }

        $this->isInterested = true;
    }

    protected function syncEngagementStates(): void
    {
        $this->interestsCount = max(0, (int) ($this->event->interests_count ?? 0));

        $user = auth()->user();

        if (! $user) {
            $this->isSaved = false;
            $this->isInterested = false;
            return;
        }

        $this->isSaved = $user->savedEvents()
            ->where('event_id', $this->event->id)
            ->exists();

        $this->isInterested = $user->interestedEvents()
            ->where('event_id', $this->event->id)
            ->exists();
    }
};
?>

@extends('layouts.app')

@section('title', $this->event->title . ' - ' . config('app.name'))

@push('head')
    <x-event-json-ld :event="$this->event" />
    <!-- OpenGraph / Twitter Cards -->
    <meta property="og:title" content="{{ $this->event->title }}">
    <meta property="og:description" content="{{ Str::limit(strip_tags($this->event->description), 160) }}">
    <meta property="og:type" content="event">
    <meta property="og:url" content="{{ route('events.show', $this->event) }}">
    <meta property="article:published_time" content="{{ $this->event->starts_at?->toIso8601String() }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $this->event->title }}">
    <meta name="twitter:description" content="{{ Str::limit(strip_tags($this->event->description), 160) }}">
@endpush

@section('content')
    @php
        $event = $this->event;
        $calendarLinks = $this->calendarLinks;
        $isSaved = $this->isSaved;
        $isInterested = $this->isInterested;
        $interestsCount = $this->interestsCount;
        $shareData = [
            'title' => $event->title,
            'text' => Str::limit(strip_tags($event->description), 100),
            'url' => route('events.show', $event),
        ];
        $copyMessage = __('Link copied to clipboard!');
        $copyPrompt = __('Copy this link:');
    @endphp
    @php
        $venueAddress = $event->venue?->addressModel;
        $institutionAddress = $event->institution?->addressModel;
        $lat = $venueAddress?->lat ?? $institutionAddress?->lat;
        $lng = $venueAddress?->lng ?? $institutionAddress?->lng;
    @endphp

    <div class="bg-slate-50 min-h-screen pb-20" x-data='{
        registerOpen: false,
        shareData: @json($shareData),
        copyMessage: @json($copyMessage),
        copyPrompt: @json($copyPrompt),
        share() {
            if (navigator.share) {
                navigator.share(this.shareData);
                return;
            }

            if (navigator.clipboard) {
                navigator.clipboard.writeText(this.shareData.url).then(() => {
                    alert(this.copyMessage);
                });
                return;
            }

            window.prompt(this.copyPrompt, this.shareData.url);
        }
    }'>
        <!-- Header / Banner -->
        <div class="relative h-64 lg:h-96 bg-slate-900 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-900/50 to-slate-900/90 z-10"></div>
            <!-- Institution Image or Default -->
            @php
                $heroImage = $event->institution?->getFirstMediaUrl('hero');
                if (!$heroImage) {
                    $heroImage = asset('images/default-mosque-hero.png');
                }
            @endphp
            <img
                src="{{ $heroImage }}"
                alt="{{ $event->institution?->name ?? $event->title }}"
                class="absolute inset-0 w-full h-full object-cover"
            >

            <div class="container mx-auto px-6 lg:px-12 relative z-20 h-full flex flex-col justify-end pb-12">
                <div class="flex flex-wrap gap-2 mb-4">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/20 border border-emerald-500/30 px-3 py-1 text-sm font-medium text-emerald-300 backdrop-blur-sm">
                        {{ ucfirst($event->genre ?? 'General') }}
                    </span>
                    @if($event->language && $event->language !== 'malay')
                        <span
                            class="inline-flex items-center rounded-full bg-white/10 border border-white/20 px-3 py-1 text-sm font-medium text-white backdrop-blur-sm">
                            {{ ucfirst($event->language) }}
                        </span>
                    @endif
                    @if($event->institution)
                        <a href="{{ route('institutions.show', $event->institution) }}" wire:navigate
                            class="inline-flex items-center gap-1.5 rounded-full bg-white/10 border border-white/20 px-3 py-1 text-sm font-medium text-white backdrop-blur-sm hover:bg-white/20 transition-colors">
                            {{ $event->institution->name }}
                        </a>
                    @endif
                </div>
                <h1 class="font-heading text-3xl lg:text-5xl font-bold text-white shadow-sm max-w-4xl leading-tight">
                    {{ $event->title }}
                </h1>
            </div>
        </div>

        <div class="container mx-auto px-6 lg:px-12 -mt-8 relative z-30 grid lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Overview Card -->
                <div class="bg-white rounded-3xl p-8 shadow-xl shadow-slate-200/50 border border-slate-100">
                    <h2 class="font-heading text-2xl font-bold text-slate-900 mb-6">{{ __('About this Event') }}</h2>
                    <div class="prose prose-slate prose-lg max-w-none">
                        {!! nl2br(e($event->description)) !!}
                    </div>

                    <!-- Topics -->
                    @if($event->topics->isNotEmpty())
                        <div class="mt-8 pt-6 border-t border-slate-100">
                            <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-3">{{ __('Topics') }}
                            </h3>
                            <div class="flex flex-wrap gap-2">
                                @foreach($event->topics as $topic)
                                    <span
                                        class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-600">
                                        {{ $topic->name }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Speakers -->
                @if($event->speakers->isNotEmpty())
                    <div class="bg-white rounded-3xl p-8 shadow-xl shadow-slate-200/50 border border-slate-100">
                        <h2 class="font-heading text-2xl font-bold text-slate-900 mb-6">{{ __('Speakers') }}</h2>
                        <div class="grid sm:grid-cols-2 gap-6">
                            @foreach($event->speakers as $speaker)
                                <a href="{{ route('speakers.show', $speaker) }}" wire:navigate
                                    class="flex items-center gap-4 p-4 rounded-xl bg-slate-50 border border-slate-100 hover:border-emerald-200 hover:bg-emerald-50/50 transition-colors">
                                    <div
                                        class="h-14 w-14 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 font-bold text-xl">
                                        {{ substr($speaker->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-slate-900">{{ $speaker->name }}</h4>
                                        @if($speaker->title)
                                            <p class="text-xs text-slate-500">{{ $speaker->title }}</p>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Location Map -->
                @if($lat && $lng)
                    <div class="bg-white rounded-3xl overflow-hidden shadow-xl shadow-slate-200/50 border border-slate-100">
                        <div class="p-6 border-b border-slate-100">
                            <h2 class="font-heading text-xl font-bold text-slate-900">{{ __('Location Map') }}</h2>
                        </div>
                        <iframe
                            width="100%"
                            height="400"
                            frameborder="0"
                            style="border:0"
                            src="https://maps.google.com/maps?q={{ $lat }},{{ $lng }}&hl={{ app()->getLocale() }}&z=15&output=embed"
                            allowfullscreen
                            loading="lazy">
                        </iframe>
                    </div>
                @endif


                <!-- Livestream / Recording Links -->
                @if($event->live_url || $event->recording_url)
                    <div class="bg-white rounded-3xl p-8 shadow-xl shadow-slate-200/50 border border-slate-100">
                        <h2 class="font-heading text-2xl font-bold text-slate-900 mb-6">{{ __('Watch Online') }}</h2>
                        <div class="flex flex-wrap gap-4">
                            @if($event->live_url)
                                <a href="{{ $event->live_url }}" target="_blank" rel="noopener"
                                    class="inline-flex items-center gap-3 px-6 py-4 rounded-xl bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold shadow-lg shadow-red-500/20 hover:shadow-red-500/30 hover:-translate-y-0.5 transition-all">
                                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        <path
                                            d="M15.91 11.672a.375.375 0 010 .656l-5.603 3.113a.375.375 0 01-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112z" />
                                    </svg>
                                    {{ __('Watch Live') }}
                                </a>
                            @endif
                            @if($event->recording_url)
                                <a href="{{ $event->recording_url }}" target="_blank" rel="noopener"
                                    class="inline-flex items-center gap-3 px-6 py-4 rounded-xl bg-slate-100 text-slate-700 font-semibold hover:bg-slate-200 transition-colors">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ __('Watch Recording') }}
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Actions Card -->
                <div class="bg-white rounded-3xl p-6 shadow-xl shadow-slate-200/50 border border-slate-100 sticky top-24">
                    <div class="space-y-6">
                        <!-- Date & Time -->
                        <div class="flex items-start gap-4">
                            <div class="p-3 rounded-xl bg-emerald-50 text-emerald-600">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-500">{{ __('Date & Time') }}</p>
                                <p class="font-bold text-slate-900">{{ $event->starts_at?->format('l, F j, Y') }}</p>
                                <p class="text-slate-600">
                                    <x-event-timing :event="$event" :show-date="false" />
                                    @if($event->ends_at && $event->timing_mode === \App\Enums\TimingMode::Absolute)
                                        - {{ $event->ends_at?->format('h:i A') }}
                                    @endif
                                </p>
                            </div>
                        </div>

                        <!-- Location -->
                        <div class="flex items-start gap-4">
                            <div class="p-3 rounded-xl bg-teal-50 text-teal-600">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-slate-500">{{ __('Location') }}</p>
                                <p class="font-bold text-slate-900">
                                    @if($event->venue && $event->institution)
                                        {{ $event->venue->name }}, {{ $event->institution->name }}
                                    @else
                                        {{ $event->venue?->name ?? $event->institution?->name ?? __('Online') }}
                                    @endif
                                </p>
                                @if($venueAddress?->address1)
                                    <p class="text-sm text-slate-500 mt-1">{{ $venueAddress->address1 }}</p>
                                @elseif($institutionAddress?->address1)
                                    <p class="text-sm text-slate-500 mt-1">{{ $institutionAddress->address1 }}</p>
                                @endif
                            </div>
                        </div>

                        <!-- Navigation Buttons (Waze / Google Maps) -->
                        @if($lat && $lng)
                            <div class="flex gap-2 pt-2">
                                <a href="https://www.waze.com/ul?ll={{ $lat }},{{ $lng }}&navigate=yes" target="_blank"
                                    rel="noopener"
                                    class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-blue-500 text-white text-sm font-semibold hover:bg-blue-600 transition-colors">
                                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                                        <path
                                            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
                                    </svg>
                                    Waze
                                </a>
                                <a href="https://www.google.com/maps/dir/?api=1&destination={{ $lat }},{{ $lng }}"
                                    target="_blank" rel="noopener"
                                    class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-slate-100 text-slate-700 text-sm font-semibold hover:bg-slate-200 transition-colors">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />
                                    </svg>
                                    {{ __('Maps') }}
                                </a>
                            </div>
                        @endif
                    </div>

                    <!-- Donation Section -->
                    @if($event->donationAccount)
                        <div class="mt-6 pt-6 border-t border-slate-100">
                            <h3 class="font-semibold text-slate-900 mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-amber-500" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2zm0 2v12h16V6H4zm2 3h12v2H6V9zm0 4h8v2H6v-2z" />
                                </svg>
                                {{ __('Support this Program') }}
                            </h3>
                            <div class="bg-amber-50 rounded-xl p-4 border border-amber-100">
                                <p class="text-sm font-medium text-amber-800">{{ $event->donationAccount->bank_name }}</p>
                                <p class="text-lg font-bold text-amber-900 font-mono mt-1">
                                    {{ $event->donationAccount->account_number }}</p>
                                <p class="text-sm text-amber-700 mt-1">{{ $event->donationAccount->account_name }}</p>
                                @if($event->donationAccount->reference_note)
                                    <p class="text-xs text-amber-600 mt-2">{{ __('Ref:') }}
                                        {{ $event->donationAccount->reference_note }}</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Add to Calendar -->
                    <div class="mt-6 pt-6 border-t border-slate-100">
                        <div class="relative" x-data="{ open: false }">
                            <button type="button" @click="open = !open"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-gradient-to-r from-violet-500 to-purple-600 text-white text-sm font-semibold shadow-lg shadow-purple-500/20 hover:shadow-purple-500/30 hover:-translate-y-0.5 transition-all">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                {{ __('Add to Calendar') }}
                                <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': open }" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <!-- Calendar Options Dropdown -->
                            <div x-show="open" @click.away="open = false"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                                class="absolute z-50 mt-2 w-full rounded-xl bg-white shadow-xl border border-slate-100 overflow-hidden">

                                <!-- Google Calendar -->
                                <a href="{{ $calendarLinks['google'] }}" target="_blank" rel="noopener"
                                    class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors">
                                    <svg class="w-5 h-5 text-[#4285F4]" viewBox="0 0 24 24" fill="currentColor">
                                        <path
                                            d="M19.5 22h-15A2.5 2.5 0 012 19.5v-15A2.5 2.5 0 014.5 2H9v2H4.5a.5.5 0 00-.5.5v15a.5.5 0 00.5.5h15a.5.5 0 00.5-.5V15h2v4.5a2.5 2.5 0 01-2.5 2.5z" />
                                        <path
                                            d="M8 10h2v2H8v-2zm0 4h2v2H8v-2zm4-4h2v2h-2v-2zm0 4h2v2h-2v-2zm4-4h2v2h-2v-2z" />
                                    </svg>
                                    <span class="text-sm font-medium text-slate-700">Google Calendar</span>
                                </a>

                                <!-- Apple Calendar / iCal -->
                                <a href="{{ $calendarLinks['ics'] }}" download
                                    class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors border-t border-slate-50">
                                    <svg class="w-5 h-5 text-slate-600" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    <span class="text-sm font-medium text-slate-700">Apple Calendar / iCal (.ics)</span>
                                </a>

                                <!-- Outlook -->
                                <a href="{{ $calendarLinks['outlook'] }}" target="_blank" rel="noopener"
                                    class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors border-t border-slate-50">
                                    <svg class="w-5 h-5 text-[#0078D4]" viewBox="0 0 24 24" fill="currentColor">
                                        <path
                                            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
                                    </svg>
                                    <span class="text-sm font-medium text-slate-700">Outlook.com</span>
                                </a>

                                <!-- Office 365 -->
                                <a href="{{ $calendarLinks['office365'] }}" target="_blank" rel="noopener"
                                    class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors border-t border-slate-50">
                                    <svg class="w-5 h-5 text-[#D83B01]" viewBox="0 0 24 24" fill="currentColor">
                                        <path
                                            d="M21 5H3a1 1 0 00-1 1v12a1 1 0 001 1h18a1 1 0 001-1V6a1 1 0 00-1-1zM3 6h18v2H3V6zm0 12V10h18v8H3z" />
                                    </svg>
                                    <span class="text-sm font-medium text-slate-700">Office 365</span>
                                </a>

                                <!-- Yahoo Calendar -->
                                <a href="{{ $calendarLinks['yahoo'] }}" target="_blank" rel="noopener"
                                    class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors border-t border-slate-50">
                                    <svg class="w-5 h-5 text-[#6001D2]" viewBox="0 0 24 24" fill="currentColor">
                                        <path
                                            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z" />
                                    </svg>
                                    <span class="text-sm font-medium text-slate-700">Yahoo Calendar</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Save Event -->
                    <div class="mt-4">
                        @auth
                            <button type="button" wire:click="toggleSave" wire:loading.attr="disabled"
                                wire:loading.class="opacity-70" wire:target="toggleSave"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border border-rose-200 bg-rose-50 text-rose-700 text-sm font-semibold hover:bg-rose-100 transition-all group {{ $isSaved ? 'saved shadow-inner bg-rose-100' : '' }}">
                                <svg class="w-5 h-5 {{ $isSaved ? 'fill-rose-500' : 'fill-none' }} stroke-rose-500 transition-all group-hover:scale-110"
                                    viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                </svg>
                                <span>{{ $isSaved ? __('Event Disimpan') : __('Simpan Majlis') }}</span>
                            </button>
                        @else
                            <a href="{{ route('login') }}"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border border-rose-200 bg-rose-50 text-rose-700 text-sm font-semibold hover:bg-rose-100 transition-all group">
                                <svg class="w-5 h-5 fill-none stroke-rose-500 transition-all group-hover:scale-110"
                                    viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                </svg>
                                {{ __('Simpan Majlis') }}
                            </a>
                        @endauth
                    </div>

                    <!-- Actions Row (Save, Share) -->
                    <div class="mt-6 pt-6 border-t border-slate-100 flex gap-3">
                        <!-- Save Button -->
                        @auth
                            <button type="button" wire:click="toggleSave" wire:loading.attr="disabled"
                                wire:loading.class="opacity-70" wire:target="toggleSave"
                                class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border-2 border-slate-200 text-slate-600 text-sm font-semibold hover:border-emerald-500 hover:text-emerald-600 transition-colors {{ $isSaved ? 'border-emerald-500 text-emerald-600' : '' }}">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                                </svg>
                                {{ $isSaved ? __('Saved') : __('Save') }}
                            </button>
                        @else
                            <a href="{{ route('login') }}"
                                class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border-2 border-slate-200 text-slate-600 text-sm font-semibold hover:border-emerald-500 hover:text-emerald-600 transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                                </svg>
                                {{ __('Save') }}
                            </a>
                        @endauth

                        <!-- Share Button -->
                        <button type="button" @click="share()"
                            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border-2 border-slate-200 text-slate-600 text-sm font-semibold hover:border-green-500 hover:text-green-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                            </svg>
                            {{ __('Share') }}
                        </button>
                    </div>

                    <!-- Registration CTA -->
                    <div class="mt-6 pt-6 border-t border-slate-100">
                        @if($event->registration_required)
                            @php
                                $regOpen = !$event->registration_opens_at || $event->registration_opens_at <= now();
                                $regClosed = $event->registration_closes_at && $event->registration_closes_at < now();
                                $atCapacity = $event->capacity && $event->registrations_count >= $event->capacity;
                            @endphp

                            @if($regClosed)
                                <button disabled
                                    class="flex w-full items-center justify-center rounded-xl bg-slate-100 px-6 py-3.5 text-sm font-bold text-slate-400 cursor-not-allowed">
                                    {{ __('Registration Closed') }}
                                </button>
                            @elseif($atCapacity)
                                <button disabled
                                    class="flex w-full items-center justify-center rounded-xl bg-amber-100 px-6 py-3.5 text-sm font-bold text-amber-600 cursor-not-allowed">
                                    {{ __('Fully Booked') }}
                                </button>
                            @elseif(!$regOpen)
                                <button disabled
                                    class="flex w-full items-center justify-center rounded-xl bg-slate-100 px-6 py-3.5 text-sm font-bold text-slate-500 cursor-not-allowed">
                                    {{ __('Opens') }} {{ $event->registration_opens_at->format('M d, h:i A') }}
                                </button>
                            @else
                                <a href="#register" @click.prevent="registerOpen = true"
                                    class="flex w-full items-center justify-center rounded-xl bg-emerald-600 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-emerald-500/20 hover:bg-emerald-700 hover:-translate-y-0.5 transition-all">
                                    {{ __('Register Now') }}
                                    @if($event->capacity)
                                        <span class="ml-2 text-xs opacity-75">({{ $event->capacity - $event->registrations_count }}
                                            {{ __('spots left') }})</span>
                                    @endif
                                </a>
                            @endif
                        @else
                            <div class="flex items-center justify-center gap-2 text-sm text-slate-500 py-2">
                                <svg class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                {{ __('No registration required - just show up!') }}
                            </div>
                        @endif
                    </div>

                    <!-- Interest Section -->
                    @php
                        $eventIsPast = $event->starts_at && $event->starts_at->isPast();
                    @endphp
                    @if(!$eventIsPast)
                        <div class="mt-6 pt-6 border-t border-slate-100">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-semibold text-slate-700">{{ __('Berminat Hadir?') }}</h3>
                                <div class="flex items-center gap-1.5 text-sm text-slate-500">
                                    <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                    </svg>
                                    <span>{{ $interestsCount }}</span>
                                    <span>{{ __('berminat') }}</span>
                                </div>
                            </div>
                            @auth
                                <button type="button" wire:click="toggleInterest" wire:loading.attr="disabled"
                                    wire:loading.class="opacity-70" wire:target="toggleInterest"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border-2 transition-all group
                                    {{ $isInterested 
                                        ? 'border-blue-500 bg-blue-50 text-blue-700 shadow-inner' 
                                        : 'border-slate-200 bg-white text-slate-600 hover:border-blue-500 hover:text-blue-600' }}">
                                    <svg class="w-5 h-5 transition-transform group-hover:scale-110 {{ $isInterested ? 'text-blue-500' : '' }}" 
                                        fill="{{ $isInterested ? 'currentColor' : 'none' }}" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>{{ $isInterested ? __('Anda Berminat') : __('Saya Berminat') }}</span>
                                </button>
                            @else
                                <a href="{{ route('login') }}"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border-2 border-slate-200 bg-white text-slate-600 text-sm font-semibold hover:border-blue-500 hover:text-blue-600 transition-all group">
                                    <svg class="w-5 h-5 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ __('Log Masuk untuk Berminat') }}
                                </a>
                            @endauth
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Registration Form Modal (if needed) -->
        @if($event->registration_required)
            <div id="registerModal" x-show="registerOpen" x-cloak x-transition.opacity
                class="fixed inset-0 z-50 items-center justify-center bg-black/50 backdrop-blur-sm">
                <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4 shadow-2xl">
                    <h3 class="font-heading text-2xl font-bold text-slate-900 mb-6">{{ __('Register for this Event') }}</h3>
                    <form action="{{ route('events.register', $event) }}" method="POST">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Name') }} *</label>
                                <input type="text" name="name" required
                                    class="w-full h-12 px-4 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Email') }}</label>
                                <input type="email" name="email"
                                    class="w-full h-12 px-4 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Phone') }}</label>
                                <input type="tel" name="phone"
                                    class="w-full h-12 px-4 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none">
                            </div>
                            <p class="text-xs text-slate-500">{{ __('Please provide either email or phone.') }}</p>
                        </div>
                        <div class="mt-6 flex gap-3">
                            <button type="button" @click="registerOpen = false"
                                class="flex-1 px-4 py-3 rounded-xl border border-slate-200 text-slate-600 font-semibold hover:bg-slate-50 transition-colors">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit"
                                class="flex-1 px-4 py-3 rounded-xl bg-emerald-600 text-white font-semibold hover:bg-emerald-700 transition-colors">
                                {{ __('Register') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>

@endsection
