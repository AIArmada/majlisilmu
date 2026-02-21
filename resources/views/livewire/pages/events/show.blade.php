@push('head')
    <x-event-json-ld :event="$this->event" />
    <meta property="og:title" content="{{ $event->title }}">
    <meta property="og:description" content="{{ Str::limit($event->description_text, 160) }}">
    <meta property="og:type" content="event">
    <meta property="og:url" content="{{ route('events.show', $event) }}">
    <meta property="og:image" content="{{ $event->card_image_url }}">
    <meta property="article:published_time" content="{{ $event->starts_at?->toIso8601String() }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $event->title }}">
    <meta name="twitter:description" content="{{ Str::limit($event->description_text, 160) }}">
    <meta name="twitter:image" content="{{ $event->card_image_url }}">
@endpush

@php
    $venueAddress = $event->venue?->addressModel;
    $institutionAddress = $event->institution?->addressModel;
    $primaryAddress = $venueAddress ?? $institutionAddress;
    $lat = $venueAddress?->lat ?? $institutionAddress?->lat;
    $lng = $venueAddress?->lng ?? $institutionAddress?->lng;
    $galleryImages = $this->galleryImages;
    $upcomingSessions = $this->upcomingSessions;
    $nextSession = $upcomingSessions->first();
    $registrationMode = $this->registrationMode();
    $relatedEvents = $this->relatedEvents;
    $shareLinks = $this->shareLinks;
    $sharePreviewImage = $event->card_image_url;
    $eventTimeStatus = $this->eventTimeStatus;
    $descriptionHtml = $this->descriptionHtml;

    $shareData = [
        'title' => $event->title,
        'text' => Str::limit($event->description_text, 100),
        'url' => route('events.show', $event),
    ];
    $copyMessage = __('Link copied to clipboard!');
    $copyPrompt = __('Copy this link:');

    // Hero image fallback chain
    $heroImage = $event->getFirstMedia('poster')?->getAvailableUrl(['preview', 'thumb']) ?? '';
    if (!$heroImage) {
        $heroImage = $event->institution?->getFirstMedia('cover')?->getAvailableUrl(['banner']) ?? '';
    }
    if (!$heroImage) {
        $heroImage = $event->venue?->getFirstMedia('main')?->getAvailableUrl(['banner']) ?? '';
    }

    // Format label
    $formatLabel = $event->event_format?->getLabel() ?? __('Physical');
    $formatIcon = match($event->event_format) {
        \App\Enums\EventFormat::Online => 'globe',
        \App\Enums\EventFormat::Hybrid => 'arrows-right-left',
        default => 'map-pin',
    };

    // Schedule kind label
    $scheduleKindLabel = $event->schedule_kind?->label();

    // Age group labels
    $ageGroupLabels = [];
    if ($event->age_group instanceof \Illuminate\Support\Collection && $event->age_group->isNotEmpty()) {
        $ageGroupLabels = $event->age_group->map(fn ($ag) => $ag->getLabel())->all();
    }

    // Language
    $primaryLanguage = $event->languages->first();
    $languageName = $primaryLanguage?->name ?? __('Bahasa Melayu');

    // Organize tags by type
    $tagsByType = $event->tags->groupBy('type');

    // Schedule state
    $scheduleState = $event->schedule_state;

    // Full address parts for venue/institution
    $fullAddressParts = array_filter([
        $primaryAddress?->line1,
        $primaryAddress?->line2,
    ]);
    $fullAddressCityLine = implode(' ', array_filter([
        $primaryAddress?->postcode,
        $primaryAddress?->city?->name,
    ]));
    $fullAddressStateName = $primaryAddress?->state?->name;
    $fullAddressDistrictName = $primaryAddress?->district?->name;

    // Location short label (for hero)
    $locationShortLabel = implode(', ', array_filter([
        $primaryAddress?->district?->name ?? $primaryAddress?->city?->name,
        $primaryAddress?->state?->name,
    ]));

    // Navigation URLs: prefer stored URLs, fall back to lat/lng
    $wazeNavUrl = filled($primaryAddress?->waze_url) ? (string) $primaryAddress->waze_url : ($lat && $lng ? "https://www.waze.com/ul?ll={$lat},{$lng}&navigate=yes" : null);
    $googleMapsNavUrl = filled($primaryAddress?->google_maps_url) ? (string) $primaryAddress->google_maps_url : ($lat && $lng ? "https://www.google.com/maps/dir/?api=1&destination={$lat},{$lng}" : null);

    // Institution contacts
    $institutionEmail = null;
    $institutionPhone = null;
    if ($event->institution && $event->institution->relationLoaded('contacts')) {
        $institutionEmail = $event->institution->contacts->firstWhere('category', \App\Enums\ContactCategory::Email)?->value;
        $institutionPhone = $event->institution->contacts->firstWhere('category', \App\Enums\ContactCategory::Phone)?->value;
    }
@endphp

<div class="min-h-screen bg-slate-50 pb-28 lg:pb-16"
    x-data='{
        registerOpen: false,
        shareModalOpen: false,
        copied: false,
        shareData: @json($shareData),
        copyMessage: @json($copyMessage),
        copyPrompt: @json($copyPrompt),
        nativeShare() {
            if (navigator.share) {
                navigator.share(this.shareData);
                return;
            }
            this.copyLink();
        },
        copyLink() {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(this.shareData.url).then(() => {
                    this.copied = true;
                    setTimeout(() => { this.copied = false; }, 2000);
                });
                return;
            }
            window.prompt(this.copyPrompt, this.shareData.url);
        },
        openShareModal() {
            this.shareModalOpen = true;
            this.copied = false;
        },
    }'>

    {{-- ==============================
         HERO SECTION (Full Width)
         ============================== --}}
    <div class="relative w-full bg-slate-950 pt-20 lg:pt-0">
        {{-- Background Image with Parallax/Fade --}}
        <div class="absolute inset-0 overflow-hidden">
            @if($heroImage)
                <img src="{{ $heroImage }}" alt="" class="size-full object-cover opacity-40 mix-blend-overlay" loading="eager" aria-hidden="true">
            @else
                {{-- Geometric Islamic-inspired pattern --}}
                <div class="absolute inset-0 opacity-[0.08]" aria-hidden="true">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <pattern id="hero-hex" x="0" y="0" width="56" height="96" patternUnits="userSpaceOnUse">
                                <polygon points="28,4 52,18 52,46 28,60 4,46 4,18" fill="none" stroke="white" stroke-width="1.5"/>
                                <polygon points="28,48 52,62 52,90 28,104 4,90 4,62" fill="none" stroke="white" stroke-width="1.5"/>
                            </pattern>
                        </defs>
                        <rect width="100%" height="100%" fill="url(#hero-hex)"/>
                    </svg>
                </div>
            @endif
            
            {{-- Radial ambient glow --}}
            <div class="absolute -left-40 -top-40 size-[600px] rounded-full bg-emerald-500/20 blur-[100px]" aria-hidden="true"></div>
            <div class="absolute -bottom-40 right-0 size-[500px] rounded-full bg-teal-400/10 blur-[100px]" aria-hidden="true"></div>
            
            {{-- Gradient overlays for text readability and blending into page --}}
            <div class="absolute inset-0 bg-gradient-to-t from-background via-slate-950/60 to-slate-950/30" aria-hidden="true"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-slate-950/80 via-slate-950/40 to-transparent" aria-hidden="true"></div>
            <div class="noise-overlay"></div>
        </div>

        {{-- Main content container --}}
        <div class="container relative mx-auto px-5 pt-12 pb-24 sm:px-8 lg:px-12 lg:pt-32 lg:pb-32">
            <div class="grid gap-12 lg:grid-cols-12 lg:gap-8">
                {{-- Left: Content --}}
                <div class="lg:col-span-8 xl:col-span-7 flex flex-col justify-center">
                    {{-- Badges --}}
                    <div class="animate-fade-in-up" style="--reveal-d: 100ms;">
                        <div class="flex flex-wrap gap-2.5">
                            @if($event->status instanceof \App\States\EventStatus\Pending)
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-400/30 bg-amber-400/10 px-3 py-1 text-xs font-semibold tracking-wide text-amber-300 backdrop-blur-md">
                                    <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-amber-400 opacity-75"></span><span class="relative inline-flex size-2 rounded-full bg-amber-500"></span></span>
                                    {{ __('Menunggu Kelulusan') }}
                                </span>
                            @endif

                            @php
                                $eventTypeValues = $event->event_type;
                                $firstEventType = $eventTypeValues instanceof \Illuminate\Support\Collection ? $eventTypeValues->first() : null;
                            @endphp
                            @if($firstEventType)
                                <span class="inline-flex items-center rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-xs font-semibold tracking-wide text-emerald-300 backdrop-blur-md">
                                    {{ $firstEventType->getLabel() }}
                                </span>
                            @endif

                            <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-medium tracking-wide text-white/80 backdrop-blur-md">
                                @if($event->event_format === \App\Enums\EventFormat::Online)
                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                                @elseif($event->event_format === \App\Enums\EventFormat::Hybrid)
                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
                                @else
                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                                @endif
                                {{ $formatLabel }}
                            </span>
                            
                            @if($scheduleKindLabel && $event->schedule_kind !== \App\Enums\ScheduleKind::Single)
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-violet-400/30 bg-violet-400/10 px-3 py-1 text-xs font-medium tracking-wide text-violet-300 backdrop-blur-md">
                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    {{ $scheduleKindLabel }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Title --}}
                    <h1 class="mt-6 font-heading text-4xl font-bold leading-[1.1] tracking-tight text-white drop-shadow-2xl sm:text-5xl lg:text-6xl xl:text-7xl animate-fade-in-up text-balance" style="--reveal-d: 200ms;">
                        {{ $event->title }}
                    </h1>

                    {{-- Organiser --}}
                    @if($event->institution)
                        <div class="mt-6 animate-fade-in-up" style="--reveal-d: 300ms;">
                            <a href="{{ route('institutions.show', $event->institution) }}" wire:navigate
                                class="group inline-flex items-center gap-3 rounded-full border border-white/10 bg-white/5 pr-4 p-1.5 transition-all hover:bg-white/10 hover:border-white/20 backdrop-blur-md">
                                @php $heroInstLogo = $event->institution->getFirstMediaUrl('logo', 'thumb'); @endphp
                                @if($heroInstLogo)
                                    <img src="{{ $heroInstLogo }}" alt="" class="size-8 rounded-full bg-white object-cover" loading="lazy">
                                @else
                                    <div class="flex size-8 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-300">
                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    </div>
                                @endif
                                <span class="text-sm font-medium text-white/90 group-hover:text-white">{{ $event->institution->name }}</span>
                            </a>
                        </div>
                    @endif

                    {{-- Date + Location frosted chips --}}
                    <div class="mt-8 flex flex-wrap gap-4 animate-fade-in-up" style="--reveal-d: 400ms;">
                        @if($event->starts_at)
                            <div class="flex items-center gap-4 rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-md shadow-xl shadow-black/20">
                                <div class="flex size-12 shrink-0 flex-col items-center justify-center rounded-xl bg-gradient-to-b from-emerald-400 to-emerald-600 shadow-inner">
                                    <span class="text-lg font-bold leading-none text-white">{{ \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($event->starts_at, 'j') }}</span>
                                    <span class="mt-0.5 text-[9px] font-bold uppercase tracking-widest text-emerald-50">{{ \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($event->starts_at, 'M') }}</span>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-white">{{ \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($event->starts_at, 'l, j F Y') }}</p>
                                    <p class="mt-0.5 text-sm text-emerald-200/80 font-medium">
                                        {{ \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($event->starts_at, 'g:i A') }}
                                        @if($event->ends_at) — {{ \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($event->ends_at, 'g:i A') }}@endif
                                    </p>
                                </div>
                            </div>
                        @endif

                        @if($event->venue || $event->institution)
                            <div class="flex items-center gap-4 rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-md shadow-xl shadow-black/20">
                                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-white/10 shadow-inner">
                                    <svg class="size-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-white">{{ $event->venue?->name ?? $event->institution?->name ?? __('Online') }}</p>
                                    @if($locationShortLabel)
                                        <p class="mt-0.5 text-sm text-white/60 font-medium">{{ $locationShortLabel }}</p>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Right: Poster or Speakers Showcase --}}
                @if($event->hasMedia('poster') || $event->speakers->isNotEmpty())
                    <div class="lg:col-span-4 xl:col-span-5 flex items-center justify-center lg:justify-end animate-fade-in-up" style="--reveal-d: 500ms;">
                        @if($event->hasMedia('poster'))
                            <div class="relative group">
                                <div class="absolute -inset-1 rounded-[2rem] bg-gradient-to-b from-emerald-400 to-teal-600 opacity-30 blur-xl transition duration-500 group-hover:opacity-50"></div>
                                <div class="relative w-64 sm:w-72 overflow-hidden rounded-[2rem] border border-white/20 bg-slate-900/50 backdrop-blur-xl shadow-2xl">
                                    <img src="{{ $event->getFirstMediaUrl('poster', 'preview') }}" alt="{{ $event->title }}" class="w-full h-auto object-cover transition duration-700 group-hover:scale-105" loading="lazy">
                                </div>
                            </div>
                        @else
                            @php $heroSpeakersList = $event->speakers; @endphp
                            @if($heroSpeakersList->count() === 1)
                                @php
                                    $heroPortraitSpeaker = $heroSpeakersList->first();
                                    $heroPortraitUrl = $heroPortraitSpeaker->getFirstMediaUrl('avatar', 'profile')
                                        ?: ($heroPortraitSpeaker->avatar_url ?: $heroPortraitSpeaker->default_avatar_url);
                                @endphp
                                <div class="relative group">
                                    <div class="absolute -inset-1 rounded-[2rem] bg-gradient-to-b from-emerald-400 to-teal-600 opacity-30 blur-xl transition duration-500 group-hover:opacity-50"></div>
                                    <div class="relative w-64 sm:w-72 overflow-hidden rounded-[2rem] border border-white/20 bg-slate-900/50 backdrop-blur-xl shadow-2xl">
                                        <div class="aspect-[4/5] w-full">
                                            <img src="{{ $heroPortraitUrl }}" alt="{{ $heroPortraitSpeaker->name }}" class="size-full object-cover object-top transition duration-700 group-hover:scale-105" loading="lazy">
                                        </div>
                                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-950 via-slate-950/80 to-transparent p-6 pt-12">
                                            <p class="text-xs font-bold uppercase tracking-widest text-emerald-400">{{ __('Speaker') }}</p>
                                            <h3 class="mt-1 font-heading text-xl font-bold text-white">{{ $heroPortraitSpeaker->formatted_name ?? $heroPortraitSpeaker->name }}</h3>
                                        </div>
                                    </div>
                                </div>
                            @elseif($heroSpeakersList->count() >= 2)
                                <div class="relative w-full max-w-md">
                                    <div class="absolute -inset-4 rounded-[3rem] bg-gradient-to-br from-emerald-500/20 to-teal-500/20 blur-2xl"></div>
                                    <div class="relative grid grid-cols-2 gap-4">
                                        @foreach($heroSpeakersList->take(4) as $index => $heroPortraitSpeaker)
                                            @php
                                                $heroPortraitUrl = $heroPortraitSpeaker->getFirstMediaUrl('avatar', 'profile')
                                                    ?: ($heroPortraitSpeaker->avatar_url ?: $heroPortraitSpeaker->default_avatar_url);
                                                $mt = $index % 2 !== 0 ? 'mt-8' : '';
                                            @endphp
                                            <div class="group relative overflow-hidden rounded-3xl border border-white/10 bg-slate-900/50 shadow-xl backdrop-blur-md {{ $mt }}">
                                                <div class="aspect-square w-full">
                                                    <img src="{{ $heroPortraitUrl }}" alt="{{ $heroPortraitSpeaker->name }}" class="size-full object-cover object-top transition duration-500 group-hover:scale-110" loading="lazy">
                                                </div>
                                                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-950 via-slate-950/80 to-transparent p-4 pt-8">
                                                    <h3 class="font-heading text-sm font-bold text-white leading-tight">{{ $heroPortraitSpeaker->formatted_name ?? $heroPortraitSpeaker->name }}</h3>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ==============================
         FLOATING ACTION BAR
         ============================== --}}
    <div class="container relative z-30 mx-auto px-5 sm:px-8 lg:px-12 -mt-12 mb-12">
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-white/40 bg-white/80 p-4 shadow-2xl shadow-slate-200/50 backdrop-blur-xl sm:p-6">
            <div class="flex flex-wrap items-center gap-3">
                @auth
                    <button type="button" wire:click="toggleGoing" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 rounded-2xl px-6 py-3 text-sm font-bold shadow-sm transition-all
                        {{ $isGoing
                            ? 'bg-emerald-600 text-white shadow-emerald-200'
                            : 'bg-slate-900 text-white hover:-translate-y-0.5 hover:bg-emerald-600 hover:shadow-lg hover:shadow-emerald-500/30' }}">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            @if($isGoing)
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            @else
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            @endif
                        </svg>
                        {{ $isGoing ? __('Saya Hadir') : __('Saya Akan Hadir') }}
                        @if($goingCount > 0)
                            <span class="ml-1 rounded-full bg-white/20 px-2 py-0.5 text-xs">{{ $goingCount }}</span>
                        @endif
                    </button>

                    <button type="button" wire:click="toggleInterest" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 rounded-2xl border-2 px-5 py-3 text-sm font-bold transition-all
                        {{ $isInterested
                            ? 'border-rose-200 bg-rose-50 text-rose-600'
                            : 'border-slate-200 bg-white text-slate-700 hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600' }}">
                        <svg class="size-5 {{ $isInterested ? 'fill-rose-500' : '' }}" viewBox="0 0 24 24" fill="{{ $isInterested ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                        {{ __('Minat') }}
                        @if($interestsCount > 0)
                            <span class="text-xs opacity-75">{{ $interestsCount }}</span>
                        @endif
                    </button>

                    <button type="button" wire:click="toggleSave" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 rounded-2xl border-2 px-5 py-3 text-sm font-bold transition-all
                        {{ $isSaved
                            ? 'border-blue-200 bg-blue-50 text-blue-600'
                            : 'border-slate-200 bg-white text-slate-700 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-600' }}">
                        <svg class="size-5 {{ $isSaved ? 'fill-blue-500' : '' }}" viewBox="0 0 24 24" fill="{{ $isSaved ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                        </svg>
                        {{ $isSaved ? __('Disimpan') : __('Simpan') }}
                    </button>
                @else
                    <a href="{{ route('login') }}"
                        class="inline-flex items-center gap-2 rounded-2xl bg-slate-900 px-6 py-3 text-sm font-bold text-white shadow-sm transition-all hover:-translate-y-0.5 hover:bg-emerald-600 hover:shadow-lg hover:shadow-emerald-500/30">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                        {{ __('Log Masuk untuk Hadir') }}
                    </a>
                @endauth
            </div>

            <div class="flex items-center gap-3">
                <button type="button" @click="openShareModal()"
                    class="inline-flex items-center gap-2 rounded-2xl border-2 border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition-all hover:border-slate-300 hover:bg-slate-50">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                    </svg>
                    {{ __('Kongsi') }}
                </button>

                @can('update', $event)
                    <a href="{{ \App\Filament\Resources\Events\EventResource::getUrl('edit', ['record' => $event]) }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 rounded-2xl border-2 border-amber-200 bg-amber-50 px-5 py-3 text-sm font-bold text-amber-700 transition-all hover:bg-amber-100">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487a2.25 2.25 0 113.182 3.182L7.5 19.213l-4.5.9.9-4.5L16.862 3.487z"/></svg>
                        <span class="hidden sm:inline">{{ __('Edit Event') }}</span>
                    </a>
                @endcan
            </div>
        </div>
    </div>

    {{-- ==============================
         STATUS BANNERS
         ============================== --}}
    <div class="container mx-auto px-5 sm:px-8 lg:px-12">
        @if($event->status instanceof \App\States\EventStatus\Pending)
            <div class="relative z-30 -mt-4 mb-4">
                <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                    <svg class="mt-0.5 size-5 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    <div>
                        <p class="text-sm font-bold text-amber-800">{{ __('Menunggu Kelulusan') }}</p>
                        <p class="mt-0.5 text-sm text-amber-700">{{ __('Majlis ini belum disahkan oleh pentadbir. Sila pastikan sendiri kesahihan majlis ini sebelum hadir.') }}</p>
                    </div>
                </div>
            </div>
        @endif

        @if($eventTimeStatus === 'past')
            <div class="relative z-30 {{ $event->status instanceof \App\States\EventStatus\Pending ? '' : '-mt-4' }} mb-4">
                <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-100 p-4">
                    <svg class="size-5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-sm font-medium text-slate-600">{{ __('Majlis ini telah berlalu.') }} {{ $event->recording_url ? __('Anda boleh menonton rakaman di bawah.') : '' }}</p>
                </div>
            </div>
        @elseif($eventTimeStatus === 'happening_now')
            <div class="relative z-30 {{ $event->status instanceof \App\States\EventStatus\Pending ? '' : '-mt-4' }} mb-4">
                <div class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                    <span class="relative flex size-3">
                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex size-3 rounded-full bg-emerald-500"></span>
                    </span>
                    <p class="text-sm font-bold text-emerald-800">{{ __('Sedang Berlangsung') }}</p>
                    @if($event->live_url)
                        <a href="{{ $event->live_url }}" target="_blank" rel="noopener" class="ml-auto inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-emerald-700">
                            <svg class="size-3" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/></svg>
                            {{ __('Tonton Sekarang') }}
                        </a>
                    @endif
                </div>
            </div>
        @elseif($eventTimeStatus === 'starting_soon' && $event->starts_at)
            <div class="relative z-30 {{ $event->status instanceof \App\States\EventStatus\Pending ? '' : '-mt-4' }} mb-4">
                <div class="flex items-center gap-3 rounded-2xl border border-blue-200 bg-blue-50 p-4"
                    x-data="{
                        target: {{ $event->starts_at->timestamp * 1000 }},
                        display: '',
                        update() {
                            const diff = this.target - Date.now();
                            if (diff <= 0) { this.display = ''; return; }
                            const h = Math.floor(diff / 3600000);
                            const m = Math.floor((diff % 3600000) / 60000);
                            this.display = (h > 0 ? h + 'j ' : '') + m + 'm';
                        }
                    }"
                    x-init="update(); setInterval(() => update(), 60000)">
                    <svg class="size-5 shrink-0 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-sm font-medium text-blue-800">
                        {{ __('Bermula Tidak Lama Lagi') }}
                        <span x-show="display" class="ml-1 font-bold" x-text="'(' + display + ' lagi)'"></span>
                    </p>
                </div>
            </div>
        @endif

        @if($scheduleState === \App\Enums\ScheduleState::Paused)
            <div class="mb-4">
                <div class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <svg class="size-5 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-sm font-medium text-amber-700">{{ __('Jadual ditangguhkan buat sementara waktu.') }}</p>
                </div>
            </div>
        @elseif($scheduleState === \App\Enums\ScheduleState::Cancelled)
            <div class="mb-4">
                <div class="flex items-center gap-3 rounded-2xl border border-red-200 bg-red-50 p-4">
                    <svg class="size-5 shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                    <p class="text-sm font-bold text-red-700">{{ __('Majlis ini telah dibatalkan.') }}</p>
                </div>
            </div>
        @endif
    </div>

    {{-- ==============================
         MAIN GRID
         ============================== --}}
    <div class="container mx-auto mt-2 grid gap-8 px-5 sm:px-8 lg:grid-cols-3 lg:px-12">

        {{-- ====== LEFT COLUMN (Main Content) ====== --}}
        <div class="space-y-8 lg:col-span-2">

            {{-- ABOUT --}}
            <section class="group relative overflow-hidden rounded-3xl border border-slate-200/60 bg-white/80 p-6 shadow-xl shadow-slate-200/40 backdrop-blur-xl transition-all hover:shadow-2xl hover:shadow-slate-200/50 sm:p-8 scroll-reveal reveal-up"
                x-intersect.once="$el.classList.add('revealed')">
                <div class="absolute -right-20 -top-20 size-64 rounded-full bg-emerald-50 opacity-50 blur-3xl transition-opacity group-hover:opacity-100"></div>
                
                <div class="relative z-10">
                    <div class="flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('About this Event') }}</h2>
                    </div>
                    
                    <div class="prose prose-slate prose-lg mt-6 max-w-none prose-headings:font-heading prose-headings:font-bold prose-a:text-emerald-600 hover:prose-a:text-emerald-500 prose-img:rounded-2xl">
                        {!! $descriptionHtml !!}
                    </div>

                    {{-- Tags Organized by Type --}}
                    @if($event->tags->isNotEmpty())
                        <div class="mt-8 border-t border-slate-100 pt-6">
                            @foreach($tagsByType as $type => $tags)
                                @php
                                    $typeEnum = \App\Enums\TagType::tryFrom($type);
                                    $tagColor = match($type) {
                                        'domain' => 'bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100',
                                        'discipline' => 'bg-cyan-50 text-cyan-700 border-cyan-200 hover:bg-cyan-100',
                                        'source' => 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100',
                                        'issue' => 'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100',
                                        default => 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100',
                                    };
                                @endphp
                                <div class="{{ !$loop->first ? 'mt-4' : '' }}">
                                    @if($typeEnum)
                                        <p class="mb-2.5 text-xs font-bold uppercase tracking-widest text-slate-400">{{ $typeEnum->label() }}</p>
                                    @endif
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($tags as $tag)
                                            <span wire:key="tag-{{ $tag->id }}" class="inline-flex items-center rounded-xl border px-3.5 py-1.5 text-xs font-bold transition-colors {{ $tagColor }}">
                                                {{ $tag->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            {{-- SPEAKERS --}}
            @if($event->speakers->isNotEmpty())
                <section class="scroll-reveal reveal-up" x-intersect.once="$el.classList.add('revealed')">
                    <div class="mb-6 flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-teal-100 text-teal-600">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                        <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Speakers') }}</h2>
                    </div>
                    
                    <div class="grid gap-5 {{ $event->speakers->count() === 1 ? 'max-w-2xl' : 'sm:grid-cols-2' }}">
                        @foreach($event->speakers as $speaker)
                            @php
                                $speakerProfileImg = $speaker->getFirstMediaUrl('avatar', 'profile') ?: null;
                                $speakerThumbImg = $speaker->avatar_url ?: $speaker->default_avatar_url;
                                $speakerCoverImg = $speaker->getFirstMediaUrl('cover', 'banner') ?: null;
                            @endphp
                            <a wire:key="speaker-{{ $speaker->id }}" href="{{ route('speakers.show', $speaker) }}" wire:navigate
                                class="group relative flex flex-col overflow-hidden rounded-3xl border border-slate-200/60 bg-white/80 shadow-lg shadow-slate-200/40 backdrop-blur-xl transition-all duration-300 hover:-translate-y-1 hover:border-emerald-300 hover:shadow-xl hover:shadow-emerald-100">
                                
                                {{-- Cover background --}}
                                <div class="relative h-32 w-full overflow-hidden bg-slate-100">
                                    @if($speakerCoverImg)
                                        <img src="{{ $speakerCoverImg }}" alt="" class="size-full object-cover transition duration-700 group-hover:scale-105 group-hover:opacity-80" loading="lazy">
                                    @else
                                        <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/20 via-teal-500/10 to-cyan-500/20"></div>
                                        <div class="absolute inset-0 opacity-[0.05]" style="background-image: url('{{ asset('images/pattern-bg.png') }}');"></div>
                                    @endif
                                    <div class="absolute inset-0 bg-gradient-to-t from-white via-white/20 to-transparent"></div>
                                </div>

                                {{-- Profile overlay --}}
                                <div class="relative -mt-12 flex flex-col items-center px-6 pb-6 text-center">
                                    <div class="relative size-24 shrink-0 overflow-hidden rounded-2xl border-4 border-white bg-white shadow-xl transition-transform duration-300 group-hover:-translate-y-2">
                                        <img src="{{ $speakerProfileImg ?: $speakerThumbImg }}" alt="{{ $speaker->name }}"
                                            class="size-full object-cover" width="96" height="96" loading="lazy">
                                    </div>
                                    
                                    <div class="mt-3">
                                        <h4 class="font-heading text-lg font-bold text-slate-900 transition-colors group-hover:text-emerald-700">{{ $speaker->formatted_name ?? $speaker->name }}</h4>
                                        @if($speaker->title)
                                            <p class="mt-1 text-sm font-medium text-slate-500">{{ $speaker->title }}</p>
                                        @endif
                                    </div>
                                </div>

                                {{-- Bio snippet --}}
                                @if($speaker->bio)
                                    <div class="mt-auto border-t border-slate-100 bg-slate-50/50 px-6 py-4 transition-colors group-hover:bg-emerald-50/30">
                                        <p class="line-clamp-2 text-sm leading-relaxed text-slate-600">{{ Str::limit(strip_tags(is_array($speaker->bio) ? ($speaker->bio['html'] ?? '') : $speaker->bio), 120) }}</p>
                                    </div>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- VENUE & INSTITUTION SHOWCASE --}}
            @if($event->venue || $event->institution)
                <section class="scroll-reveal reveal-up" x-intersect.once="$el.classList.add('revealed')">
                    <div class="mb-6 flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-blue-100 text-blue-600">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Location & Organizer') }}</h2>
                    </div>

                    <div class="overflow-hidden rounded-3xl border border-slate-200/60 bg-white/80 shadow-xl shadow-slate-200/40 backdrop-blur-xl">
                        {{-- Venue --}}
                        @if($event->venue)
                            @php
                                $venueCover = $event->venue->getFirstMediaUrl('cover', 'banner');
                                $venueThumb = $event->venue->getFirstMediaUrl('cover', 'thumb');
                            @endphp
                            <div class="relative {{ $event->institution ? 'border-b border-slate-100' : '' }}">
                                @if($venueCover)
                                    <div class="group relative h-56 overflow-hidden sm:h-64">
                                        <img src="{{ $venueCover }}" alt="{{ $event->venue->name }}" class="size-full object-cover transition duration-700 group-hover:scale-105" loading="lazy">
                                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/90 via-slate-900/40 to-transparent"></div>
                                        
                                        <div class="absolute inset-x-0 bottom-0 p-6 sm:p-8">
                                            <div class="flex items-start justify-between gap-4">
                                                <div>
                                                    <span class="inline-flex items-center rounded-lg bg-white/20 px-2.5 py-1 text-xs font-bold uppercase tracking-widest text-white backdrop-blur-md">{{ __('Venue') }}</span>
                                                    <h3 class="mt-2 font-heading text-2xl font-bold text-white sm:text-3xl">{{ $event->venue->name }}</h3>
                                                    @if($venueAddress)
                                                        <p class="mt-2 text-sm font-medium leading-relaxed text-white/80 max-w-lg">
                                                            @if($venueAddress->line1) {{ $venueAddress->line1 }} @endif
                                                            @if($venueAddress->line2), {{ $venueAddress->line2 }}@endif
                                                            @if($fullAddressCityLine)
                                                                <br>{{ $fullAddressCityLine }}
                                                            @endif
                                                            @if($venueAddress->state?->name)
                                                                , {{ $venueAddress->state->name }}
                                                            @endif
                                                        </p>
                                                    @endif
                                                </div>
                                                
                                                @if($wazeNavUrl || $googleMapsNavUrl)
                                                    <div class="hidden shrink-0 flex-col gap-2 sm:flex">
                                                        @if($wazeNavUrl)
                                                            <a href="{{ $wazeNavUrl }}" target="_blank" rel="noopener" class="inline-flex size-10 items-center justify-center rounded-xl bg-white/10 text-white backdrop-blur-md transition hover:bg-white/20 hover:scale-110" title="Waze">
                                                                <img src="{{ asset('images/waze-app-icon-seeklogo.svg') }}" alt="Waze" class="size-5" loading="lazy">
                                                            </a>
                                                        @endif
                                                        @if($googleMapsNavUrl)
                                                            <a href="{{ $googleMapsNavUrl }}" target="_blank" rel="noopener" class="inline-flex size-10 items-center justify-center rounded-xl bg-white/10 text-white backdrop-blur-md transition hover:bg-white/20 hover:scale-110" title="Google Maps">
                                                                <img src="{{ asset('images/google-maps.svg') }}" alt="Google Maps" class="size-5" loading="lazy">
                                                            </a>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="p-6 sm:p-8">
                                        <div class="flex items-start gap-5">
                                            @if($venueThumb)
                                                <div class="size-20 shrink-0 overflow-hidden rounded-2xl border border-slate-200 shadow-md">
                                                    <img src="{{ $venueThumb }}" alt="{{ $event->venue->name }}" class="size-full object-cover" loading="lazy">
                                                </div>
                                            @else
                                                <div class="flex size-20 shrink-0 items-center justify-center rounded-2xl bg-blue-50 border border-blue-100">
                                                    <svg class="size-8 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/></svg>
                                                </div>
                                            @endif
                                            <div class="flex-1">
                                                <span class="text-xs font-bold uppercase tracking-widest text-slate-400">{{ __('Venue') }}</span>
                                                <h3 class="mt-1 font-heading text-xl font-bold text-slate-900">{{ $event->venue->name }}</h3>
                                                @if($venueAddress)
                                                    <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                                        @if($venueAddress->line1) {{ $venueAddress->line1 }} @endif
                                                        @if($venueAddress->line2), {{ $venueAddress->line2 }}@endif
                                                        @if($fullAddressCityLine)
                                                            <br>{{ $fullAddressCityLine }}
                                                        @endif
                                                        @if($venueAddress->state?->name)
                                                            , {{ $venueAddress->state->name }}
                                                        @endif
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Institution --}}
                        @if($event->institution)
                            @php
                                $instCover = $event->institution->getFirstMediaUrl('cover', 'banner');
                                $instLogo = $event->institution->getFirstMediaUrl('logo', 'thumb');
                            @endphp
                            <div class="p-6 sm:p-8">
                                <a href="{{ route('institutions.show', $event->institution) }}" wire:navigate class="group flex items-center gap-5">
                                    <div class="size-16 shrink-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-transform duration-300 group-hover:scale-105 group-hover:shadow-md">
                                        @if($instLogo)
                                            <img src="{{ $instLogo }}" alt="{{ $event->institution->name }}" class="size-full object-contain p-1.5" loading="lazy">
                                        @else
                                            <div class="flex size-full items-center justify-center bg-emerald-50">
                                                <svg class="size-7 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z"/></svg>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <span class="text-xs font-bold uppercase tracking-widest text-slate-400">{{ __('Organized by') }}</span>
                                        <h3 class="mt-1 font-heading text-lg font-bold text-slate-900 transition-colors group-hover:text-emerald-600">{{ $event->institution->name }}</h3>
                                    </div>
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-slate-50 text-slate-400 transition-colors group-hover:bg-emerald-50 group-hover:text-emerald-600">
                                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                </a>

                                {{-- Institution contact info --}}
                                @if($institutionPhone || $institutionEmail)
                                    <div class="mt-4 flex flex-wrap gap-4 pl-[5.25rem]">
                                        @if($institutionPhone)
                                            <a href="tel:{{ $institutionPhone }}" class="inline-flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-emerald-50 hover:text-emerald-700">
                                                <svg class="size-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
                                                {{ $institutionPhone }}
                                            </a>
                                        @endif
                                        @if($institutionEmail)
                                            <a href="mailto:{{ $institutionEmail }}" class="inline-flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-emerald-50 hover:text-emerald-700">
                                                <svg class="size-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                                                {{ $institutionEmail }}
                                            </a>
                                        @endif
                                    </div>
                                @endif

                                {{-- Show institution cover below logo --}}
                                @if($instCover)
                                    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-100">
                                        <img src="{{ $instCover }}" alt="{{ $event->institution->name }}" class="h-40 w-full object-cover" loading="lazy">
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Navigation Buttons (Mobile/Fallback) --}}
                        @if(($wazeNavUrl || $googleMapsNavUrl) && empty($venueCover))
                            <div class="flex gap-3 border-t border-slate-100 bg-slate-50/50 p-6 sm:hidden">
                                @if($wazeNavUrl)
                                    <a href="{{ $wazeNavUrl }}" target="_blank" rel="noopener"
                                        class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl border border-cyan-200 bg-white px-4 py-3 text-sm font-bold text-cyan-700 shadow-sm transition hover:bg-cyan-50 hover:shadow">
                                        <img src="{{ asset('images/waze-app-icon-seeklogo.svg') }}" alt="Waze" class="size-5" loading="lazy">
                                        Waze
                                    </a>
                                @endif
                                @if($googleMapsNavUrl)
                                    <a href="{{ $googleMapsNavUrl }}" target="_blank" rel="noopener"
                                        class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl border border-blue-200 bg-white px-4 py-3 text-sm font-bold text-blue-700 shadow-sm transition hover:bg-blue-50 hover:shadow">
                                        <img src="{{ asset('images/google-maps.svg') }}" alt="Google Maps" class="size-5" loading="lazy">
                                        Google Maps
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            {{-- UPCOMING SESSIONS --}}
            @if($upcomingSessions->isNotEmpty())
                <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-xl shadow-slate-200/50 sm:p-8">
                    <div class="flex items-center justify-between">
                        <h2 class="font-heading text-xl font-bold text-slate-900 sm:text-2xl">{{ __('Upcoming Sessions') }}</h2>
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">{{ $upcomingSessions->count() }} {{ __('sessions') }}</span>
                    </div>
                    <div class="relative mt-5">
                        {{-- Timeline line --}}
                        <div class="absolute bottom-0 left-3 top-0 w-px bg-slate-200"></div>
                        <div class="space-y-4">
                            @foreach($upcomingSessions->take(8) as $session)
                                <div wire:key="session-{{ $session->id }}" class="relative flex items-start gap-4 pl-8">
                                    <div class="absolute left-1.5 top-2 size-3 rounded-full border-2 border-emerald-500 bg-white"></div>
                                    <div class="flex-1 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                                        <p class="text-sm font-semibold text-slate-800">
                                            {{ \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($session->starts_at, 'l, j M Y') }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-slate-500">
                                            {{ \App\Support\Timezone\UserDateTimeFormatter::format($session->starts_at, 'h:i A') }}
                                            @if($session->ends_at)
                                                - {{ \App\Support\Timezone\UserDateTimeFormatter::format($session->ends_at, 'h:i A') }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif

            {{-- REFERENCES --}}
            @if($event->references->isNotEmpty())
                <section class="scroll-reveal reveal-up" x-intersect.once="$el.classList.add('revealed')">
                    <div class="mb-6 flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        </div>
                        <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Reference Materials') }}</h2>
                    </div>
                    
                    <div class="grid gap-5 sm:grid-cols-2">
                        @foreach($event->references as $reference)
                            <div wire:key="ref-{{ $reference->id }}" class="group flex gap-5 rounded-3xl border border-slate-200/60 bg-white/80 p-5 shadow-lg shadow-slate-200/40 backdrop-blur-xl transition-all duration-300 hover:-translate-y-1 hover:border-indigo-300 hover:shadow-xl hover:shadow-indigo-100">
                                @php $coverUrl = $reference->getFirstMediaUrl('front_cover', 'thumb'); @endphp
                                @if($coverUrl)
                                    <div class="w-20 shrink-0 overflow-hidden rounded-xl shadow-md transition-transform duration-300 group-hover:scale-105">
                                        <img src="{{ $coverUrl }}" alt="{{ $reference->title }}" class="h-28 w-full object-cover" loading="lazy">
                                    </div>
                                @else
                                    <div class="flex w-20 shrink-0 items-center justify-center rounded-xl bg-indigo-50 border border-indigo-100">
                                        <svg class="size-8 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                    </div>
                                @endif
                                <div class="min-w-0 flex-1 py-1">
                                    <h4 class="font-heading text-base font-bold text-slate-900 transition-colors group-hover:text-indigo-700">{{ $reference->title }}</h4>
                                    @if($reference->author)
                                        <p class="mt-1.5 text-sm font-medium text-slate-600">{{ $reference->author }}</p>
                                    @endif
                                    @if($reference->publisher)
                                        <p class="mt-1 text-xs text-slate-400">{{ $reference->publisher }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- SERIES CONTEXT --}}
            @if($event->series->isNotEmpty())
                <section class="scroll-reveal reveal-up" x-intersect.once="$el.classList.add('revealed')">
                    <div class="mb-6 flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-fuchsia-100 text-fuchsia-600">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        </div>
                        <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Part of a Series') }}</h2>
                    </div>
                    
                    <div class="space-y-4">
                        @foreach($event->series as $series)
                            <a wire:key="series-{{ $series->id }}" href="{{ route('series.show', $series) }}" wire:navigate
                                class="group flex items-center gap-5 rounded-3xl border border-slate-200/60 bg-white/80 p-5 shadow-lg shadow-slate-200/40 backdrop-blur-xl transition-all duration-300 hover:-translate-y-1 hover:border-fuchsia-300 hover:shadow-xl hover:shadow-fuchsia-100">
                                @php $seriesCover = $series->getFirstMediaUrl('cover', 'thumb'); @endphp
                                @if($seriesCover)
                                    <div class="size-20 shrink-0 overflow-hidden rounded-2xl shadow-md transition-transform duration-300 group-hover:scale-105">
                                        <img src="{{ $seriesCover }}" alt="{{ $series->title }}" class="size-full object-cover" loading="lazy">
                                    </div>
                                @else
                                    <div class="flex size-20 shrink-0 items-center justify-center rounded-2xl bg-fuchsia-50 border border-fuchsia-100">
                                        <svg class="size-8 text-fuchsia-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                    </div>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <h4 class="font-heading text-lg font-bold text-slate-900 transition-colors group-hover:text-fuchsia-700">{{ $series->title }}</h4>
                                    @if($series->description)
                                        <p class="mt-1.5 line-clamp-2 text-sm leading-relaxed text-slate-600">{{ Str::limit(strip_tags($series->description), 120) }}</p>
                                    @endif
                                </div>
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-slate-50 text-slate-400 transition-colors group-hover:bg-fuchsia-50 group-hover:text-fuchsia-600">
                                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- GALLERY --}}
            @if($galleryImages !== [])
                <section class="scroll-reveal reveal-up"
                    x-intersect.once="$el.classList.add('revealed')"
                    x-data="{ active: 0, images: @js($galleryImages), next() { this.active = (this.active + 1) % this.images.length }, prev() { this.active = (this.active - 1 + this.images.length) % this.images.length }, go(index) { this.active = index } }">
                    <div class="mb-6 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 items-center justify-center rounded-xl bg-rose-100 text-rose-600">
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Event Gallery') }}</h2>
                        </div>
                        <div class="flex items-center gap-2 rounded-full bg-white/80 px-4 py-1.5 text-sm font-bold text-slate-500 shadow-sm backdrop-blur-md border border-slate-200/60">
                            <span x-text="active + 1" class="text-slate-900"></span>
                            <span class="text-slate-300">/</span>
                            <span x-text="images.length"></span>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-3xl border border-slate-200/60 bg-white/80 p-3 shadow-xl shadow-slate-200/40 backdrop-blur-xl">
                        <div class="group relative overflow-hidden rounded-2xl bg-slate-900">
                            <img :src="images[active]?.url" :alt="images[active]?.alt" class="h-[400px] w-full object-cover transition-transform duration-700 sm:h-[500px]" loading="lazy">
                            
                            {{-- Navigation Overlays --}}
                            <div class="absolute inset-0 flex items-center justify-between p-4 opacity-0 transition-opacity duration-300 group-hover:opacity-100">
                                <button type="button" @click="prev()"
                                    class="inline-flex size-12 items-center justify-center rounded-full bg-white/20 text-white backdrop-blur-md transition hover:bg-white/40 hover:scale-110" aria-label="{{ __('Previous image') }}">
                                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                </button>
                                <button type="button" @click="next()"
                                    class="inline-flex size-12 items-center justify-center rounded-full bg-white/20 text-white backdrop-blur-md transition hover:bg-white/40 hover:scale-110" aria-label="{{ __('Next image') }}">
                                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </button>
                            </div>
                        </div>

                        @if(count($galleryImages) > 1)
                            <div class="mt-3 grid grid-cols-4 gap-3 sm:grid-cols-6">
                                @foreach($galleryImages as $index => $image)
                                    <button type="button" @click="go({{ $index }})" 
                                        class="relative overflow-hidden rounded-xl transition-all duration-300"
                                        :class="{ 'ring-2 ring-rose-500 ring-offset-2 scale-95': active === {{ $index }}, 'opacity-60 hover:opacity-100': active !== {{ $index }} }">
                                        <img src="{{ $image['thumb'] }}" alt="{{ $image['alt'] }}" class="h-16 w-full object-cover" loading="lazy">
                                        <div class="absolute inset-0 bg-black/20 transition-opacity" :class="{ 'opacity-0': active === {{ $index }} }"></div>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            {{-- LOCATION MAP --}}
            @if($lat && $lng)
                <section class="scroll-reveal reveal-up" x-intersect.once="$el.classList.add('revealed')">
                    <div class="mb-6 flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                        </div>
                        <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Location Map') }}</h2>
                    </div>
                    
                    <div class="overflow-hidden rounded-3xl border border-slate-200/60 bg-white/80 p-2 shadow-xl shadow-slate-200/40 backdrop-blur-xl">
                        <div class="overflow-hidden rounded-2xl">
                            <iframe
                                width="100%" height="400" frameborder="0" style="border:0"
                                src="https://maps.google.com/maps?q={{ $lat }},{{ $lng }}&hl={{ app()->getLocale() }}&z=15&output=embed"
                                allowfullscreen loading="lazy" class="grayscale-[20%] contrast-[90%] transition duration-700 hover:grayscale-0 hover:contrast-100">
                            </iframe>
                        </div>
                    </div>
                </section>
            @endif

            {{-- WATCH ONLINE --}}
            @if($event->live_url || $event->recording_url)
                <section class="scroll-reveal reveal-up" x-intersect.once="$el.classList.add('revealed')">
                    <div class="mb-6 flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-red-100 text-red-600">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </div>
                        <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Watch Online') }}</h2>
                    </div>
                    
                    <div class="flex flex-wrap gap-4 rounded-3xl border border-slate-200/60 bg-white/80 p-6 shadow-xl shadow-slate-200/40 backdrop-blur-xl sm:p-8">
                        @if($event->live_url)
                            <a href="{{ $event->live_url }}" target="_blank" rel="noopener"
                                class="group relative inline-flex items-center gap-3 overflow-hidden rounded-2xl bg-slate-900 px-8 py-4 font-bold text-white shadow-lg shadow-slate-900/20 transition-all hover:-translate-y-1 hover:shadow-xl hover:shadow-slate-900/30">
                                <div class="absolute inset-0 bg-gradient-to-r from-red-600 to-red-500 opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
                                <div class="relative flex items-center gap-3">
                                    <span class="relative flex size-3">
                                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                                        <span class="relative inline-flex size-3 rounded-full bg-red-500"></span>
                                    </span>
                                    {{ __('Watch Live') }}
                                </div>
                            </a>
                        @endif
                        @if($event->recording_url)
                            <a href="{{ $event->recording_url }}" target="_blank" rel="noopener"
                                class="group inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-8 py-4 font-bold text-slate-700 shadow-sm transition-all hover:-translate-y-1 hover:border-slate-300 hover:bg-slate-50 hover:shadow-md">
                                <svg class="size-5 text-slate-400 transition-colors group-hover:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ __('Watch Recording') }}
                            </a>
                        @endif
                    </div>
                </section>
            @endif

            {{-- RELATED EVENTS --}}
            @if($relatedEvents->isNotEmpty())
                <section class="scroll-reveal reveal-up" x-intersect.once="$el.classList.add('revealed')">
                    <div class="mb-6 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            </div>
                            <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Related Events') }}</h2>
                        </div>
                        <a href="{{ route('events.index') }}" wire:navigate
                            class="inline-flex items-center gap-2 rounded-full bg-white/80 px-4 py-2 text-sm font-bold text-slate-600 shadow-sm backdrop-blur-md border border-slate-200/60 transition hover:bg-white hover:text-emerald-600">
                            {{ __('Browse All') }}
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                    
                    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($relatedEvents as $relatedEvent)
                            <a wire:key="related-{{ $relatedEvent->id }}" href="{{ route('events.show', $relatedEvent) }}" wire:navigate
                                class="group flex flex-col overflow-hidden rounded-3xl border border-slate-200/60 bg-white/80 shadow-lg shadow-slate-200/40 backdrop-blur-xl transition-all duration-300 hover:-translate-y-1 hover:border-emerald-300 hover:shadow-xl hover:shadow-emerald-100">
                                <div class="relative h-40 overflow-hidden">
                                    <img src="{{ $relatedEvent->card_image_url }}" alt="{{ $relatedEvent->title }}"
                                        class="size-full object-cover transition duration-700 group-hover:scale-105" loading="lazy">
                                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 via-slate-900/20 to-transparent"></div>
                                    <div class="absolute bottom-3 left-3 right-3">
                                        <span class="inline-flex items-center rounded-lg bg-white/20 px-2.5 py-1 text-xs font-bold text-white backdrop-blur-md">
                                            {{ $relatedEvent->starts_at ? \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($relatedEvent->starts_at, 'd M Y') : __('TBC') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex flex-1 flex-col p-5">
                                    <h3 class="font-heading text-base font-bold leading-tight text-slate-900 transition-colors group-hover:text-emerald-700">{{ $relatedEvent->title }}</h3>
                                    <div class="mt-auto pt-4">
                                        <p class="flex items-center gap-1.5 text-xs font-medium text-slate-500">
                                            <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            <span class="truncate">{{ $relatedEvent->institution?->name ?? $relatedEvent->venue?->name ?? __('Independent') }}</span>
                                        </p>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        {{-- ====== RIGHT COLUMN (Sidebar) ====== --}}
        <aside class="space-y-8">

            {{-- EVENT DETAILS CARD --}}
            <div class="sticky top-28 space-y-8">
                <div class="overflow-hidden rounded-3xl border border-slate-200/60 bg-white/80 shadow-xl shadow-slate-200/40 backdrop-blur-xl">
                    <div class="border-b border-slate-100/80 bg-slate-50/50 px-6 py-5">
                        <h3 class="font-heading text-lg font-bold text-slate-900">{{ __('Event Details') }}</h3>
                    </div>

                    <div class="p-6 space-y-6">
                        {{-- Date & Time --}}
                        <div class="flex items-start gap-4">
                            <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-100">
                                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1 pt-0.5">
                                <p class="text-xs font-bold uppercase tracking-widest text-slate-400">{{ __('Date & Time') }}</p>
                                <p class="mt-1.5 font-heading text-base font-bold text-slate-900">{{ $event->starts_at ? \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($event->starts_at, 'l, j F Y') : __('TBC') }}</p>
                                <p class="mt-1 text-sm font-medium text-slate-600">
                                    <x-event-timing :event="$event" :show-date="false" />
                                    @if($event->ends_at && $event->timing_mode === \App\Enums\TimingMode::Absolute)
                                        - {{ \App\Support\Timezone\UserDateTimeFormatter::format($event->ends_at, 'h:i A') }}
                                    @endif
                                </p>
                                @if($nextSession)
                                    <div class="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">
                                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        {{ __('Next:') }} {{ \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($nextSession->starts_at, 'd M, h:i A') }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Location --}}
                        <div class="flex items-start gap-4">
                            @php
                                $sidebarVenueThumb = $event->venue?->getFirstMediaUrl('cover', 'thumb');
                                $sidebarInstLogo = $event->institution?->getFirstMediaUrl('logo', 'thumb');
                            @endphp
                            @if($sidebarVenueThumb)
                                <div class="size-12 shrink-0 overflow-hidden rounded-2xl border border-slate-200 shadow-sm">
                                    <img src="{{ $sidebarVenueThumb }}" alt="{{ $event->venue->name }}" class="size-full object-cover" loading="lazy">
                                </div>
                            @else
                                <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 border border-blue-100">
                                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </div>
                            @endif
                            <div class="min-w-0 flex-1 pt-0.5">
                                <p class="text-xs font-bold uppercase tracking-widest text-slate-400">{{ __('Location') }}</p>
                                <p class="mt-1.5 font-heading text-base font-bold text-slate-900">
                                    @if($event->venue && $event->institution)
                                        {{ $event->venue->name }}
                                    @else
                                        {{ $event->venue?->name ?? $event->institution?->name ?? __('Online') }}
                                    @endif
                                </p>
                                @if($event->space)
                                    <p class="mt-0.5 text-sm font-medium text-slate-600">{{ $event->space->name }}</p>
                                @endif
                                @if($event->venue && $event->institution)
                                    <div class="mt-2 flex items-center gap-2">
                                        @if($sidebarInstLogo)
                                            <img src="{{ $sidebarInstLogo }}" alt="" class="size-5 rounded-md object-contain bg-white border border-slate-100" loading="lazy">
                                        @endif
                                        <a href="{{ route('institutions.show', $event->institution) }}" wire:navigate class="text-sm font-medium text-emerald-600 hover:text-emerald-700 hover:underline">{{ $event->institution->name }}</a>
                                    </div>
                                @endif
                                {{-- Full address --}}
                                @if($primaryAddress && ($primaryAddress->line1 || $primaryAddress->city?->name || $primaryAddress->state?->name))
                                    <p class="mt-2 text-sm leading-relaxed text-slate-500">
                                        @if($primaryAddress->line1) {{ $primaryAddress->line1 }} @endif
                                        @if($primaryAddress->line2), {{ $primaryAddress->line2 }}@endif
                                        @if($fullAddressCityLine)
                                            <br>{{ $fullAddressCityLine }}
                                        @endif
                                        @if($fullAddressStateName)
                                            , {{ $fullAddressStateName }}
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </div>

                        {{-- Navigation Buttons (Waze / Google Maps) --}}
                        @if($wazeNavUrl || $googleMapsNavUrl)
                            <div class="flex gap-3 pt-2">
                                @if($wazeNavUrl)
                                    <a href="{{ $wazeNavUrl }}" target="_blank" rel="noopener"
                                        class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm font-bold text-cyan-700 shadow-sm transition hover:bg-cyan-100 hover:shadow">
                                        <img src="{{ asset('images/waze-app-icon-seeklogo.svg') }}" alt="Waze" class="size-5" loading="lazy">
                                        Waze
                                    </a>
                                @endif
                                @if($googleMapsNavUrl)
                                    <a href="{{ $googleMapsNavUrl }}" target="_blank" rel="noopener"
                                        class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-bold text-blue-700 shadow-sm transition hover:bg-blue-100 hover:shadow">
                                        <img src="{{ asset('images/google-maps.svg') }}" alt="Google Maps" class="size-5" loading="lazy">
                                        Google Maps
                                    </a>
                                @endif
                            </div>
                        @endif

                        {{-- Audience Info --}}
                        @if(($event->gender && $event->gender->value !== 'all') || !empty($ageGroupLabels) || $event->children_allowed === false || $event->is_muslim_only)
                            <div class="flex items-start gap-4">
                                <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-violet-50 text-violet-600 border border-violet-100">
                                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                </div>
                                <div class="min-w-0 flex-1 pt-0.5">
                                    <p class="text-xs font-bold uppercase tracking-widest text-slate-400">{{ __('Audience') }}</p>
                                    <div class="mt-1.5 space-y-1.5 text-sm font-medium text-slate-700">
                                        @if($event->gender && $event->gender->value !== 'all')
                                            <p class="flex items-center gap-2">
                                                <span class="size-1.5 rounded-full bg-violet-400"></span>
                                                {{ $event->gender->getLabel() }}
                                            </p>
                                        @endif
                                        @if(!empty($ageGroupLabels))
                                            <p class="flex items-center gap-2">
                                                <span class="size-1.5 rounded-full bg-violet-400"></span>
                                                {{ implode(', ', $ageGroupLabels) }}
                                            </p>
                                        @endif
                                        @if($event->children_allowed === false)
                                            <p class="flex items-center gap-2 text-rose-600">
                                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                                {{ __('Kanak-kanak tidak dibenarkan') }}
                                            </p>
                                        @endif
                                        @if($event->is_muslim_only)
                                            <p class="flex items-center gap-2 text-emerald-600">
                                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                {{ __('Muslim sahaja') }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Language --}}
                        @if($primaryLanguage)
                            <div class="flex items-start gap-4">
                                <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-sky-50 text-sky-600 border border-sky-100">
                                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/></svg>
                                </div>
                                <div class="pt-0.5">
                                    <p class="text-xs font-bold uppercase tracking-widest text-slate-400">{{ __('Language') }}</p>
                                    <p class="mt-1.5 font-heading text-base font-bold text-slate-900">{{ $languageName }}</p>
                                </div>
                            </div>
                        @endif

                        {{-- Contact --}}
                        @if($institutionPhone || $institutionEmail)
                            <div class="flex items-start gap-4">
                                <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-orange-50 text-orange-600 border border-orange-100">
                                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
                                </div>
                                <div class="min-w-0 flex-1 pt-0.5">
                                    <p class="text-xs font-bold uppercase tracking-widest text-slate-400">{{ __('Contact') }}</p>
                                    @if($institutionPhone)
                                        <a href="tel:{{ $institutionPhone }}" class="mt-1.5 block font-heading text-base font-bold text-slate-900 transition hover:text-emerald-600">{{ $institutionPhone }}</a>
                                    @endif
                                    @if($institutionEmail)
                                        <a href="mailto:{{ $institutionEmail }}" class="mt-1 block truncate text-sm font-medium text-slate-500 transition hover:text-emerald-600">{{ $institutionEmail }}</a>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Registration CTA --}}
                    <div class="border-t border-slate-100/80 bg-slate-50/50 p-6">
                        @if($event->settings?->registration_required)
                            @php
                                $regOpen = !$event->settings?->registration_opens_at || $event->settings->registration_opens_at <= now();
                                $regClosed = $event->settings?->registration_closes_at && $event->settings->registration_closes_at < now();
                                $atCapacity = $registrationMode === \App\Enums\RegistrationMode::Event && $event->settings?->capacity && $event->registrations_count >= $event->settings->capacity;
                                $sessionModeUnavailable = $registrationMode === \App\Enums\RegistrationMode::Session && $upcomingSessions->isEmpty();
                            @endphp

                            @if($regClosed)
                                <button disabled class="flex w-full items-center justify-center rounded-2xl bg-slate-200 px-6 py-4 text-sm font-bold text-slate-500 cursor-not-allowed">
                                    {{ __('Registration Closed') }}
                                </button>
                            @elseif($sessionModeUnavailable)
                                <button disabled class="flex w-full items-center justify-center rounded-2xl bg-slate-200 px-6 py-4 text-sm font-bold text-slate-500 cursor-not-allowed">
                                    {{ __('No available sessions') }}
                                </button>
                            @elseif($atCapacity)
                                <button disabled class="flex w-full items-center justify-center rounded-2xl bg-amber-100 px-6 py-4 text-sm font-bold text-amber-700 cursor-not-allowed">
                                    {{ __('Fully Booked') }}
                                </button>
                            @elseif(!$regOpen)
                                <button disabled class="flex w-full items-center justify-center rounded-2xl bg-slate-200 px-6 py-4 text-sm font-bold text-slate-600 cursor-not-allowed">
                                    {{ __('Opens') }} {{ \App\Support\Timezone\UserDateTimeFormatter::format($event->settings->registration_opens_at, 'M d, h:i A') }}
                                </button>
                            @else
                                <a href="#register" @click.prevent="registerOpen = true"
                                    class="group relative flex w-full items-center justify-center overflow-hidden rounded-2xl bg-emerald-600 px-6 py-4 text-sm font-bold text-white shadow-lg shadow-emerald-500/20 transition-all hover:-translate-y-1 hover:shadow-xl hover:shadow-emerald-500/30">
                                    <div class="absolute inset-0 bg-gradient-to-r from-emerald-500 to-teal-400 opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
                                    <span class="relative flex items-center gap-2">
                                        {{ __('Register Now') }}
                                        <svg class="size-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                    </span>
                                    @if($registrationMode === \App\Enums\RegistrationMode::Event && $event->settings?->capacity)
                                        <span class="relative ml-2 text-xs opacity-80">({{ $event->settings->capacity - $event->registrations_count }} {{ __('spots left') }})</span>
                                    @endif
                                </a>
                            @endif
                        @else
                            <div class="flex items-center justify-center gap-2 rounded-2xl bg-emerald-50/50 py-3 text-sm font-bold text-emerald-700 border border-emerald-100/50">
                                <svg class="size-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                {{ __('No registration required') }}
                            </div>
                        @endif
                    </div>
                </div>

                {{-- ADD TO CALENDAR --}}
                <div class="relative" x-data="{ open: false }">
                    <button type="button" @click="open = !open"
                        class="group relative flex w-full items-center justify-center gap-3 overflow-hidden rounded-3xl bg-slate-900 px-6 py-4 text-sm font-bold text-white shadow-xl shadow-slate-900/20 transition-all hover:-translate-y-1 hover:shadow-2xl hover:shadow-slate-900/30">
                        <div class="absolute inset-0 bg-gradient-to-r from-violet-600 to-purple-600 opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
                        <span class="relative flex items-center gap-3">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            {{ __('Add to Calendar') }}
                            <svg class="size-4 transition-transform duration-300" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </span>
                    </button>

                    <div x-show="open" @click.away="open = false"
                        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 -translate-y-2 scale-95"
                        class="absolute z-50 mt-3 w-full overflow-hidden rounded-2xl border border-slate-200/60 bg-white/90 p-2 shadow-2xl backdrop-blur-xl">
                        <a href="{{ $this->calendarLinks['google'] }}" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl px-4 py-3 transition hover:bg-slate-100">
                            <svg class="size-5 text-[#4285F4]" viewBox="0 0 24 24" fill="currentColor"><path d="M19.5 22h-15A2.5 2.5 0 012 19.5v-15A2.5 2.5 0 014.5 2H9v2H4.5a.5.5 0 00-.5.5v15a.5.5 0 00.5.5h15a.5.5 0 00.5-.5V15h2v4.5a2.5 2.5 0 01-2.5 2.5z"/><path d="M8 10h2v2H8v-2zm0 4h2v2H8v-2zm4-4h2v2h-2v-2zm0 4h2v2h-2v-2zm4-4h2v2h-2v-2z"/></svg>
                            <span class="text-sm font-bold text-slate-700">Google Calendar</span>
                        </a>
                        <a href="{{ $this->calendarLinks['ics'] }}" download class="flex items-center gap-3 rounded-xl px-4 py-3 transition hover:bg-slate-100">
                            <svg class="size-5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            <span class="text-sm font-bold text-slate-700">Apple / iCal (.ics)</span>
                        </a>
                        <a href="{{ $this->calendarLinks['outlook'] }}" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl px-4 py-3 transition hover:bg-slate-100">
                            <svg class="size-5 text-[#0078D4]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                            <span class="text-sm font-bold text-slate-700">Outlook.com</span>
                        </a>
                        <a href="{{ $this->calendarLinks['office365'] }}" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl px-4 py-3 transition hover:bg-slate-100">
                            <svg class="size-5 text-[#D83B01]" viewBox="0 0 24 24" fill="currentColor"><path d="M21 5H3a1 1 0 00-1 1v12a1 1 0 001 1h18a1 1 0 001-1V6a1 1 0 00-1-1zM3 6h18v2H3V6zm0 12V10h18v8H3z"/></svg>
                            <span class="text-sm font-bold text-slate-700">Office 365</span>
                        </a>
                        <a href="{{ $this->calendarLinks['yahoo'] }}" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl px-4 py-3 transition hover:bg-slate-100">
                            <svg class="size-5 text-[#6001D2]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                            <span class="text-sm font-bold text-slate-700">Yahoo Calendar</span>
                        </a>
                    </div>
                </div>

                {{-- DONATION --}}
                @if($event->donationChannel)
                    <div class="overflow-hidden rounded-3xl border border-amber-200/60 bg-gradient-to-br from-amber-50 to-orange-50/50 shadow-xl shadow-amber-100/50">
                        <div class="p-6">
                            <h3 class="flex items-center gap-3 font-heading text-lg font-bold text-amber-900">
                                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                                    <svg class="size-5" fill="currentColor" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2zm0 2v12h16V6H4zm2 3h12v2H6V9zm0 4h8v2H6v-2z"/></svg>
                                </div>
                                {{ __('Support this Program') }}
                            </h3>
                            <div class="mt-5 rounded-2xl border border-amber-200/60 bg-white/60 p-5 backdrop-blur-sm">
                                <p class="text-xs font-bold uppercase tracking-widest text-amber-600">{{ $event->donationChannel->bank_name }}</p>
                                <p class="mt-1.5 font-mono text-xl font-bold tracking-tight text-amber-900">{{ $event->donationChannel->account_number }}</p>
                                <p class="mt-1 text-sm font-medium text-amber-800">{{ $event->donationChannel->account_name }}</p>
                                @if($event->donationChannel->reference_note)
                                    <div class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-amber-100/50 px-2.5 py-1 text-xs font-bold text-amber-700">
                                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                        {{ __('Ref:') }} {{ $event->donationChannel->reference_note }}
                                    </div>
                                @endif
                            </div>

                            {{-- QR Code --}}
                            @php $qrUrl = $event->donationChannel->getFirstMediaUrl('qr', 'thumb'); @endphp
                            @if($qrUrl)
                                <div class="mt-5 flex justify-center">
                                    <div class="rounded-2xl border border-amber-200/60 bg-white p-3 shadow-sm">
                                        <img src="{{ $qrUrl }}" alt="{{ __('QR Code') }}" class="size-32 rounded-xl" loading="lazy">
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- GUEST BENEFITS CTA --}}
                @guest
                    <div class="group relative overflow-hidden rounded-3xl border border-emerald-200/60 bg-gradient-to-br from-emerald-50 to-teal-50/50 p-6 shadow-xl shadow-emerald-100/50 transition-all hover:shadow-2xl hover:shadow-emerald-200/50">
                        <div class="absolute -right-10 -top-10 size-40 rounded-full bg-emerald-200/40 blur-3xl transition-opacity group-hover:opacity-100"></div>
                        
                        <div class="relative z-10">
                            <div class="flex items-center gap-3">
                                <div class="flex size-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600">
                                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                                </div>
                                <h3 class="font-heading text-xl font-bold text-slate-900">{{ __('Join MajlisIlmu') }}</h3>
                            </div>
                            <ul class="mt-5 space-y-3">
                                <li class="flex items-start gap-3 text-sm font-medium text-slate-700">
                                    <div class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                                        <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    {{ __('Simpan majlis & tandakan kehadiran') }}
                                </li>
                                <li class="flex items-start gap-3 text-sm font-medium text-slate-700">
                                    <div class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                                        <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    {{ __('Daftar untuk majlis yang memerlukan pendaftaran') }}
                                </li>
                                <li class="flex items-start gap-3 text-sm font-medium text-slate-700">
                                    <div class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                                        <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    {{ __('Dapatkan cadangan majlis yang berkaitan') }}
                                </li>
                                <li class="flex items-start gap-3 text-sm font-medium text-slate-700">
                                    <div class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                                        <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    {{ __('Hantar majlis anda sendiri') }}
                                </li>
                            </ul>
                            <div class="mt-6 space-y-3">
                                <a href="{{ route('register') }}" class="flex w-full items-center justify-center rounded-2xl bg-emerald-600 py-3.5 text-sm font-bold text-white shadow-lg shadow-emerald-500/20 transition-all hover:-translate-y-0.5 hover:bg-emerald-700 hover:shadow-xl hover:shadow-emerald-500/30">
                                    {{ __('Daftar Akaun Percuma') }}
                                </a>
                                <a href="{{ route('login') }}" class="flex w-full items-center justify-center rounded-2xl border-2 border-emerald-200/60 bg-white/50 py-3 text-sm font-bold text-emerald-700 transition-all hover:border-emerald-300 hover:bg-white">
                                    {{ __('Log Masuk') }}
                                </a>
                            </div>
                        </div>
                    </div>
                @endguest
            </div>
        </aside>
    </div>

    {{-- ==============================
         SHARE MODAL
         ============================== --}}
    <div x-show="shareModalOpen" x-cloak x-transition.opacity @keydown.escape.window="shareModalOpen = false"
        class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm" aria-modal="true" role="dialog">
        <div class="flex min-h-screen items-center justify-center p-4 sm:p-6">
            <div @click.away="shareModalOpen = false" 
                x-show="shareModalOpen"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-8 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 translate-y-8 scale-95"
                class="w-full max-w-xl overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-slate-200/50">
                
                <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/50 px-6 py-5">
                    <h3 class="font-heading text-xl font-bold text-slate-900">{{ __('Share Preview') }}</h3>
                    <button type="button" @click="shareModalOpen = false" class="inline-flex size-10 items-center justify-center rounded-full bg-white text-slate-500 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-50 hover:text-slate-700">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                
                <div class="p-6 sm:p-8">
                    <article class="overflow-hidden rounded-2xl border border-slate-200/60 bg-white shadow-lg shadow-slate-200/40">
                        <div class="relative h-56 overflow-hidden bg-slate-100">
                            <img src="{{ $sharePreviewImage }}" alt="{{ $event->title }}" class="size-full object-cover" loading="lazy">
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 to-transparent"></div>
                        </div>
                        <div class="p-5">
                            <h4 class="font-heading text-lg font-bold leading-tight text-slate-900">{{ $event->title }}</h4>
                            <p class="mt-2 flex items-center gap-1.5 text-sm font-medium text-emerald-600">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                {{ $event->starts_at ? \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($event->starts_at, 'd M Y, h:i A') : __('TBC') }}
                            </p>
                            <p class="mt-3 line-clamp-2 text-sm leading-relaxed text-slate-600">{{ Str::limit($event->description_text, 140) }}</p>
                        </div>
                    </article>
                    
                    <div class="mt-8 grid grid-cols-2 gap-4">
                        <button type="button" @click="nativeShare()" class="group relative flex items-center justify-center gap-2 overflow-hidden rounded-2xl bg-emerald-600 px-4 py-3.5 text-sm font-bold text-white shadow-lg shadow-emerald-500/20 transition-all hover:-translate-y-0.5 hover:shadow-xl hover:shadow-emerald-500/30">
                            <div class="absolute inset-0 bg-gradient-to-r from-emerald-500 to-teal-400 opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
                            <span class="relative flex items-center gap-2">
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684l6.632 3.316m-6.632-6l6.632-3.316"/></svg>
                                {{ __('Share Now') }}
                            </span>
                        </button>
                        <button type="button" @click="copyLink()" class="flex items-center justify-center gap-2 rounded-2xl border-2 border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 transition-all hover:border-emerald-500 hover:text-emerald-700 hover:shadow-md">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16h8M8 12h8m-6-8H6a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2v-4"/></svg>
                            {{ __('Copy Link') }}
                        </button>
                    </div>
                    
                    <div x-show="copied" 
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 -translate-y-2"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        class="mt-4 flex items-center justify-center gap-2 rounded-xl bg-emerald-50 py-2 text-sm font-bold text-emerald-600">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        {{ $copyMessage }}
                    </div>
                    
                    <div class="mt-8">
                        <p class="mb-4 text-center text-xs font-bold uppercase tracking-widest text-slate-400">{{ __('Or share via') }}</p>
                        <div class="grid grid-cols-5 gap-3">
                            <a href="{{ $shareLinks['whatsapp'] }}" target="_blank" rel="noopener" class="flex flex-col items-center gap-2 rounded-2xl border border-slate-200/60 bg-slate-50 p-3 transition hover:-translate-y-1 hover:border-[#25D366] hover:bg-[#25D366]/10 hover:text-[#25D366]">
                                <svg class="size-6" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                                <span class="text-[10px] font-bold">WhatsApp</span>
                            </a>
                            <a href="{{ $shareLinks['telegram'] }}" target="_blank" rel="noopener" class="flex flex-col items-center gap-2 rounded-2xl border border-slate-200/60 bg-slate-50 p-3 transition hover:-translate-y-1 hover:border-[#0088cc] hover:bg-[#0088cc]/10 hover:text-[#0088cc]">
                                <svg class="size-6" fill="currentColor" viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 000 12a12 12 0 0012 12 12 12 0 0012-12A12 12 0 0012 0a12 12 0 00-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 01.171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                                <span class="text-[10px] font-bold">Telegram</span>
                            </a>
                            <a href="{{ $shareLinks['facebook'] }}" target="_blank" rel="noopener" class="flex flex-col items-center gap-2 rounded-2xl border border-slate-200/60 bg-slate-50 p-3 transition hover:-translate-y-1 hover:border-[#1877F2] hover:bg-[#1877F2]/10 hover:text-[#1877F2]">
                                <svg class="size-6" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.469h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                <span class="text-[10px] font-bold">Facebook</span>
                            </a>
                            <a href="{{ $shareLinks['x'] }}" target="_blank" rel="noopener" class="flex flex-col items-center gap-2 rounded-2xl border border-slate-200/60 bg-slate-50 p-3 transition hover:-translate-y-1 hover:border-slate-900 hover:bg-slate-900/10 hover:text-slate-900">
                                <svg class="size-6" fill="currentColor" viewBox="0 0 24 24"><path d="M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z"/></svg>
                                <span class="text-[10px] font-bold">X</span>
                            </a>
                            <a href="{{ $shareLinks['email'] }}" class="flex flex-col items-center gap-2 rounded-2xl border border-slate-200/60 bg-slate-50 p-3 transition hover:-translate-y-1 hover:border-emerald-500 hover:bg-emerald-500/10 hover:text-emerald-600">
                                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <span class="text-[10px] font-bold">Email</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ==============================
         REGISTRATION MODAL
         ============================== --}}
    @if($event->settings?->registration_required)
        <div x-show="registerOpen" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm">
            <div @click.away="registerOpen = false" 
                x-show="registerOpen"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-8 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 translate-y-8 scale-95"
                class="mx-4 w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-slate-200/50">
                
                <div class="bg-slate-50/50 px-8 py-6 border-b border-slate-100">
                    <h3 class="font-heading text-2xl font-bold text-slate-900">{{ __('Register for this Event') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Please fill in your details below.') }}</p>
                </div>
                
                <form action="{{ route('events.register', $event) }}" method="POST" class="p-8">
                    @csrf
                    <div class="space-y-5">
                        <div>
                            <label class="mb-1.5 block text-sm font-bold text-slate-700">{{ __('Name') }} <span class="text-rose-500">*</span></label>
                            <input type="text" name="name" required class="h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 font-medium text-slate-900 transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-500/10">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-bold text-slate-700">{{ __('Email') }}</label>
                            <input type="email" name="email" class="h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 font-medium text-slate-900 transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-500/10">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-bold text-slate-700">{{ __('Phone') }}</label>
                            <input type="tel" name="phone" class="h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 font-medium text-slate-900 transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-500/10">
                        </div>
                        @if($registrationMode === \App\Enums\RegistrationMode::Session)
                            <div>
                                <label class="mb-1.5 block text-sm font-bold text-slate-700">{{ __('Session') }} <span class="text-rose-500">*</span></label>
                                <select name="event_session_id" required class="h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 font-medium text-slate-900 transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-500/10">
                                    <option value="">{{ __('Choose a session') }}</option>
                                    @foreach($upcomingSessions as $session)
                                        <option value="{{ $session->id }}">
                                            {{ \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($session->starts_at, 'd M Y, h:i A') }}
                                            @if($session->ends_at) - {{ \App\Support\Timezone\UserDateTimeFormatter::format($session->ends_at, 'h:i A') }} @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="rounded-xl bg-blue-50 p-3">
                            <p class="flex items-start gap-2 text-xs font-medium text-blue-700">
                                <svg class="mt-0.5 size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ __('Please provide either email or phone number so we can send your registration confirmation.') }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-8 flex gap-3">
                        <button type="button" @click="registerOpen = false" class="flex-1 rounded-xl border-2 border-slate-200 bg-white px-4 py-3.5 text-sm font-bold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" class="group relative flex-1 overflow-hidden rounded-xl bg-emerald-600 px-4 py-3.5 text-sm font-bold text-white shadow-lg shadow-emerald-500/20 transition-all hover:-translate-y-0.5 hover:shadow-xl hover:shadow-emerald-500/30">
                            <div class="absolute inset-0 bg-gradient-to-r from-emerald-500 to-teal-400 opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
                            <span class="relative">{{ __('Register') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ==============================
         MOBILE BOTTOM ACTION BAR
         ============================== --}}
    <div class="fixed inset-x-0 bottom-0 z-40 lg:hidden">
        <div class="border-t border-slate-200/60 bg-white/80 px-4 py-3 backdrop-blur-xl shadow-[0_-8px_30px_-15px_rgba(0,0,0,0.1)]" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
            <div class="flex items-center gap-2">
                @auth
                    <button type="button" wire:click="toggleGoing" wire:loading.attr="disabled"
                        class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-bold transition-all
                        {{ $isGoing
                            ? 'border-2 border-emerald-200 bg-emerald-50 text-emerald-700 shadow-inner'
                            : 'bg-emerald-600 text-white shadow-lg shadow-emerald-500/20 hover:bg-emerald-700' }}">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            @if($isGoing)
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            @else
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            @endif
                        </svg>
                        {{ $isGoing ? __('Hadir') : __('Akan Hadir') }}
                    </button>

                    <button type="button" wire:click="toggleInterest" wire:loading.attr="disabled"
                        class="rounded-xl border-2 p-3 transition-all
                        {{ $isInterested ? 'border-rose-200 bg-rose-50 text-rose-500 shadow-inner' : 'border-slate-200 bg-white text-slate-500 hover:border-rose-200 hover:text-rose-500' }}">
                        <svg class="size-5 {{ $isInterested ? 'fill-current' : '' }}" viewBox="0 0 24 24" fill="{{ $isInterested ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                    </button>

                    <button type="button" wire:click="toggleSave" wire:loading.attr="disabled"
                        class="rounded-xl border-2 p-3 transition-all
                        {{ $isSaved ? 'border-blue-200 bg-blue-50 text-blue-500 shadow-inner' : 'border-slate-200 bg-white text-slate-500 hover:border-blue-200 hover:text-blue-500' }}">
                        <svg class="size-5 {{ $isSaved ? 'fill-current' : '' }}" viewBox="0 0 24 24" fill="{{ $isSaved ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                        </svg>
                    </button>
                @else
                    <a href="{{ route('login') }}" class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-500/20 transition hover:bg-emerald-700">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                        {{ __('Log Masuk') }}
                    </a>
                @endauth

                <button type="button" @click="openShareModal()"
                    class="rounded-xl border-2 border-slate-200 bg-white p-3 text-slate-500 transition-all hover:border-slate-300 hover:text-slate-700">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</div>
