<?php

use App\Models\Institution;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component {
    public Institution $institution;

    public int $upcomingPerPage = 6;

    public int $pastPerPage = 6;

    public bool $isFollowing = false;

    public function mount(Institution $institution): void
    {
        if ($institution->status !== 'verified' && ! auth()->user()?->hasAnyRole(['super_admin', 'moderator'])) {
            abort(404);
        }

        $this->institution = $institution->load([
            'media',
            'address.state',
            'address.city',
            'address.district',
            'address.subdistrict',
            'address.country',
            'contacts',
            'socialMedia',
            'donationChannels.media',
            'speakers' => fn ($q) => $q->where('status', 'verified')->orderByPivot('is_primary', 'desc')->limit(12),
            'speakers.media',
            'spaces' => fn ($q) => $q->where('is_active', true),
            'languages',
        ]);

        $this->isFollowing = auth()->user()?->isFollowing($institution) ?? false;
    }

    public function toggleFollow(): void
    {
        $user = auth()->user();

        if (! $user) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        if ($this->isFollowing) {
            $user->unfollow($this->institution);
            $this->isFollowing = false;
        } else {
            $user->follow($this->institution);
            $this->isFollowing = true;
        }
    }

    public function loadMoreUpcoming(): void
    {
        $this->upcomingPerPage += 6;
    }

    public function loadMorePast(): void
    {
        $this->pastPerPage += 6;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Event>
     */
    public function getUpcomingEventsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->institution->events()
            ->active()
            ->where('status', 'approved')
            ->where('starts_at', '>=', now())
            ->with([
                'venue.address.state',
                'venue.address.district',
                'venue.address.subdistrict',
                'speakers.media',
                'media',
            ])
            ->orderBy('starts_at', 'asc')
            ->take($this->upcomingPerPage)
            ->get();
    }

    public function getUpcomingTotalProperty(): int
    {
        return $this->institution->events()
            ->active()
            ->where('status', 'approved')
            ->where('starts_at', '>=', now())
            ->count();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Event>
     */
    public function getPastEventsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->institution->events()
            ->active()
            ->where('status', 'approved')
            ->where('starts_at', '<', now())
            ->with([
                'venue.address.state',
                'venue.address.district',
                'venue.address.subdistrict',
                'speakers.media',
                'media',
            ])
            ->orderBy('starts_at', 'desc')
            ->take($this->pastPerPage)
            ->get();
    }

    public function getPastTotalProperty(): int
    {
        return $this->institution->events()
            ->active()
            ->where('status', 'approved')
            ->where('starts_at', '<', now())
            ->count();
    }

    public function rendering($view): void
    {
        $view->title($this->institution->name . ' - ' . config('app.name'));
    }
};

?>

<style>
    @media (max-width: 1023px) {
        .institution-main-column {
            order: 1 !important;
        }

        .institution-sidebar-column {
            order: 2 !important;
        }
    }
</style>

@php
    $institution = $this->institution;
    $upcomingEvents = $this->upcomingEvents;
    $pastEvents = $this->pastEvents;
    $upcomingTotal = $this->upcomingTotal;
    $pastTotal = $this->pastTotal;

    $coverUrl = $institution->getFirstMediaUrl('cover', 'banner');
    $logoUrl = $institution->getFirstMediaUrl('logo', 'thumb');
    $heroInstitutionImageUrl = $coverUrl ?: $institution->getFirstMediaUrl('logo');
    $gallery = $institution->getMedia('gallery');
    $speakers = $institution->speakers;
    $spaces = $institution->spaces;
    $donationChannels = $institution->donationChannels;
    $socialLinks = $institution->socialMedia->filter(fn ($s) => filled($s->url));
    $contacts = $institution->contacts->where('is_public', true);
    $languages = $institution->languages;
    $institutionUrl = route('institutions.show', $institution);
    $shareText = trim($institution->name . ' - ' . config('app.name'));
    $encodedInstitutionUrl = urlencode($institutionUrl);
    $encodedShareText = urlencode($shareText);
    $encodedShareBody = urlencode($shareText . "\n" . $institutionUrl);
    $shareData = [
        'title' => $institution->name,
        'text' => __('Lihat profil institusi ini di :app', ['app' => config('app.name')]),
        'url' => $institutionUrl,
    ];
    $shareLinks = [
        'whatsapp' => "https://wa.me/?text={$encodedShareText}%20{$encodedInstitutionUrl}",
        'telegram' => "https://t.me/share/url?url={$encodedInstitutionUrl}&text={$encodedShareText}",
        'line' => "https://social-plugins.line.me/lineit/share?url={$encodedInstitutionUrl}",
        'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$encodedInstitutionUrl}",
        'x' => "https://x.com/intent/tweet?text={$encodedShareText}&url={$encodedInstitutionUrl}",
        'instagram' => 'https://www.instagram.com/',
        'tiktok' => 'https://www.tiktok.com/',
        'email' => "mailto:?subject={$encodedShareText}&body={$encodedShareBody}",
    ];

    // Location
    $address = $institution->addressModel;
    $locationParts = array_filter([
        $address?->subdistrict?->name,
        $address?->district?->name,
        $address?->state?->name,
    ]);
    $locationString = implode(', ', $locationParts);
    $googleMapsApiKey = (string) config('services.google.maps_api_key', '');
    $mapQuery = implode(', ', array_filter([
        $institution->name,
        $address?->line1,
        $address?->line2,
        $address?->city?->name,
        $address?->state?->name,
        $address?->country?->name,
    ]));
    $normalizedMapQuery = null;
    if (filled($address?->google_maps_url)) {
        $parsedQueryString = parse_url((string) $address->google_maps_url, PHP_URL_QUERY);
        if (is_string($parsedQueryString) && $parsedQueryString !== '') {
            parse_str($parsedQueryString, $queryParams);
            $queryValue = $queryParams['query'] ?? $queryParams['q'] ?? null;
            if (is_string($queryValue) && $queryValue !== '') {
                $normalizedMapQuery = $queryValue;
            }
        }
    }
    if (! filled($normalizedMapQuery) && filled($mapQuery)) {
        $normalizedMapQuery = $mapQuery;
    }

    $googleMapsEmbedUrl = null;
    if (filled($address?->google_maps_url) && filled($googleMapsApiKey) && filled($normalizedMapQuery)) {
        $googleMapsEmbedUrl = 'https://www.google.com/maps/embed/v1/place?key=' . urlencode($googleMapsApiKey) . '&q=' . urlencode((string) $normalizedMapQuery);
    } elseif (filled($address?->google_maps_url) && filled($normalizedMapQuery)) {
        $googleMapsEmbedUrl = 'https://www.google.com/maps?q=' . urlencode((string) $normalizedMapQuery) . '&output=embed';
    }
    $wazeUrl = filled($address?->waze_url) ? (string) $address->waze_url : null;
    // Institution type label
    $typeLabel = $institution->type?->getLabel();

    // Event type label resolver
    $resolveEventTypeLabel = static function (mixed $eventType): string {
        if ($eventType instanceof \Illuminate\Support\Collection) {
            $eventType = $eventType->first();
        } elseif (is_array($eventType)) {
            $eventType = $eventType[0] ?? null;
        }
        if ($eventType instanceof \App\Enums\EventType) {
            return $eventType->getLabel();
        }
        if (is_string($eventType) && $eventType !== '') {
            return \App\Enums\EventType::tryFrom($eventType)?->getLabel() ?? __('Umum');
        }
        return __('Umum');
    };

    // Event location helper — Venue/Institution, subdistrict, district, state
    $resolveVenueLocation = static function (\App\Models\Event $event) use ($institution): string {
        $venueName = $event->venue?->name;
        $institutionName = $event->institution?->name ?? $institution->name;
        $primaryLocationName = $venueName ?: $institutionName;
        $address = $event->venue?->addressModel ?? $event->institution?->addressModel ?? $institution->addressModel;

        $districtName = $address->district?->name;
        $stateName = $address->state?->name;

        $stateHiddenDistricts = [
            'kuala lumpur',
            'putrajaya',
            'labuan',
        ];

        if (is_string($districtName) && in_array(Str::lower(trim($districtName)), $stateHiddenDistricts, true)) {
            $stateName = null;
        }

        $parts = array_filter([
            $primaryLocationName,
            $address->subdistrict?->name,
            $districtName,
            $stateName,
        ]);

        if ($parts !== []) {
            return implode(', ', $parts);
        }

        if (is_string($primaryLocationName) && $primaryLocationName !== '') {
            return $primaryLocationName;
        }

        return implode(', ', $parts);
    };

    $resolveEventTimeDisplay = static function (\App\Models\Event $event): string {
        return $event->timing_display !== ''
            ? $event->timing_display
            : \App\Support\Timezone\UserDateTimeFormatter::format($event->starts_at, 'h:i A');
    };

    $resolveEventEndTimeDisplay = static function (\App\Models\Event $event): string {
        return \App\Support\Timezone\UserDateTimeFormatter::format($event->ends_at, 'h:i A');
    };

    // Calendar data: map events to dates for the calendar view
    $calendarEvents = $upcomingEvents->groupBy(fn ($e) => \App\Support\Timezone\UserDateTimeFormatter::format($e->starts_at, 'Y-m-d'))->map(fn ($group) => $group->map(function (\App\Models\Event $e) use ($resolveEventTypeLabel) {
        $typeLabel = $resolveEventTypeLabel($e->event_type);
        $formatValue = $e->event_format?->value ?? $e->event_format;

        return [
            'id' => $e->id,
            'title' => (string) str($e->title)
                ->replace($typeLabel.': ', '')
                ->replace($typeLabel.' - ', '')
                ->replace(' ('.$typeLabel.')', '')
                ->trim(),
            'url' => route('events.show', $e),
            'is_remote' => in_array($formatValue, ['online', 'hybrid'], true),
        ];
    })->values())->toArray();
@endphp

<div class="min-h-screen bg-slate-50/80"
     x-data='{
        shareModalOpen: false,
        copied: false,
        shareData: @json($shareData),
        copyPrompt: @json(__('Copy this link:')),
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

    {{-- ═══════════════════════════════════════════════════════════
         CINEMATIC HERO — Dramatic, atmospheric header
    ═══════════════════════════════════════════════════════════ --}}
    <header class="noise-overlay relative isolate overflow-hidden bg-gradient-to-br from-slate-950 via-emerald-950/80 to-slate-950" style="min-height: 320px">
        {{-- Ambient gradient background orbs — animated floating --}}
        <div class="pointer-events-none absolute inset-0">
            <div class="animate-float-drift absolute -top-24 left-[15%] h-[28rem] w-[28rem] rounded-full bg-emerald-500/25 blur-[100px]"></div>
            <div class="animate-float-drift-alt absolute -bottom-20 right-[10%] h-[22rem] w-[22rem] rounded-full bg-gold-400/18 blur-[90px]"></div>
            <div class="animate-float-drift-slow absolute top-10 right-[40%] h-[18rem] w-[18rem] rounded-full bg-teal-400/18 blur-[80px]"></div>
            {{-- Extra accent orbs for richer depth --}}
            <div class="animate-float-drift-alt absolute -top-10 right-[20%] h-[16rem] w-[16rem] rounded-full bg-emerald-400/12 blur-[70px]"></div>
            <div class="animate-float-drift absolute -bottom-8 left-[25%] h-[18rem] w-[18rem] rounded-full bg-gold-300/10 blur-[100px]"></div>
        </div>

        {{-- Spotlight radial glow behind content --}}
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_80%_70%_at_55%_50%,rgba(16,185,129,0.14)_0%,transparent_60%)]"></div>

        {{-- Cover image --}}
        @if($coverUrl)
            <img src="{{ $coverUrl }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-30 mix-blend-luminosity" loading="eager">
        @endif
        {{-- Islamic geometric pattern overlay --}}
        <div class="absolute inset-0 opacity-[0.03]" style="background-image: url('{{ asset('images/pattern-bg.png') }}'); background-size: 200px;"></div>
        {{-- Mesh gradient layer for depth --}}
        <div class="absolute inset-0 bg-[conic-gradient(from_140deg_at_75%_30%,transparent_35%,rgba(16,185,129,0.08)_50%,transparent_65%)]"></div>
        {{-- Bottom gradient fade — lighter to let colors breathe --}}
        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-transparent to-transparent"></div>
        {{-- Side vignette for depth --}}
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,transparent_60%,rgba(0,0,0,0.3)_100%)]"></div>
        {{-- Top inner shadow for containment --}}
        <div class="absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-slate-950/60 to-transparent"></div>

        {{-- Hero content: Logo + Name --}}
        <div class="container relative z-10 mx-auto flex max-w-6xl flex-col items-center gap-6 px-6 pb-10 pt-12 sm:flex-row sm:items-center sm:gap-8 lg:px-8 lg:pb-12 lg:pt-16">
            {{-- Logo with breathing glow --}}
            <div class="animate-scale-in relative shrink-0" style="animation-delay: 200ms; opacity: 0;">
                <div class="animate-glow-breathe absolute -inset-2.5 rounded-[1.5rem] bg-gradient-to-br from-emerald-400/30 via-gold-400/15 to-emerald-600/30 blur-lg"></div>
                <div class="absolute -inset-1.5 rounded-[1.25rem] bg-gradient-to-br from-emerald-400/20 via-transparent to-gold-400/20"></div>
                <div class="relative aspect-video w-44 overflow-hidden rounded-2xl border-2 border-white/20 bg-slate-800 shadow-2xl shadow-emerald-950/50 ring-1 ring-white/15 sm:w-56">
                    @if($heroInstitutionImageUrl)
                        <img src="{{ $heroInstitutionImageUrl }}" alt="{{ $institution->name }}" class="h-full w-full object-cover" width="224" height="126">
                    @else
                        <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-emerald-800 to-emerald-950 relative">
                            <div class="absolute inset-0 opacity-10" style="background-image: url('{{ asset('images/pattern-bg.png') }}'); background-size: 80px;"></div>
                            @php
                                $initials = collect(explode(' ', $institution->name))
                                    ->filter(fn($w) => !in_array(mb_strtolower($w), ['al', 'bin', 'b.', 'masjid', 'surau', 'pusat', 'dan', 'dan']))
                                    ->map(fn($word) => mb_substr($word, 0, 1))
                                    ->take(2)
                                    ->implode('');
                                if (empty($initials)) {
                                    $initials = mb_substr($institution->name, 0, 1);
                                }
                            @endphp
                            <span class="relative font-heading text-4xl font-black text-white/80 tracking-tighter select-none sm:text-5xl">{{ $initials }}</span>
                        </div>
                    @endif
                </div>
                {{-- Institution type badge --}}
                @if($typeLabel)
                    <span class="absolute -bottom-1.5 -right-1.5 flex items-center justify-center rounded-xl border-2 border-slate-950 bg-gradient-to-br from-emerald-500 to-emerald-700 px-2.5 py-1 text-[10px] font-bold text-white shadow-lg">
                        {{ $typeLabel }}
                    </span>
                @endif
            </div>

            {{-- Name & meta --}}
            <div class="animate-fade-in-up flex-1 text-center sm:text-left" style="animation-delay: 350ms; opacity: 0;">
                <h1 class="text-hero-glow font-heading text-3xl font-bold tracking-tight text-white sm:text-4xl lg:text-5xl">
                    {{ $institution->name }}
                </h1>
                {{-- Decorative gold shimmer line --}}
                <div class="shimmer-line mx-auto mt-3 h-0.5 w-20 rounded-full bg-gradient-to-r from-gold-400/80 via-gold-300/60 to-gold-600/30 sm:mx-0"></div>

                {{-- Quick stats --}}
                <div class="mt-4 flex flex-wrap items-center justify-center gap-2.5 sm:justify-start">
                    @if($languages->isNotEmpty())
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-medium text-slate-300 backdrop-blur-sm">
                            <svg class="h-3 w-3 text-slate-400/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 21l5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 016-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 01-3.827-5.802"/></svg>
                            {{ $languages->pluck('name')->implode(', ') }}
                        </span>
                    @endif
                </div>

                {{-- Follow button --}}
                <div class="mt-4 flex flex-wrap items-center justify-center gap-2.5 sm:justify-start">
                    <button wire:click="toggleFollow" wire:loading.attr="disabled"
                            class="inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-xs font-semibold transition-all duration-200 {{ $this->isFollowing ? 'border border-emerald-400/40 bg-emerald-500/20 text-emerald-300 hover:border-red-400/40 hover:bg-red-500/20 hover:text-red-300' : 'border border-white/15 bg-white/10 text-white hover:border-emerald-400/40 hover:bg-emerald-500/20 hover:text-emerald-300' }} backdrop-blur-sm"
                            x-data="{ hovering: false }"
                            @mouseenter="hovering = true"
                            @mouseleave="hovering = false">
                        @if($this->isFollowing)
                            <template x-if="!hovering">
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                    {{ __('Mengikuti') }}
                                </span>
                            </template>
                            <template x-if="hovering">
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    {{ __('Nyahikut') }}
                                </span>
                            </template>
                        @else
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            {{ __('Ikuti') }}
                        @endif
                    </button>

                    <button type="button" @click="openShareModal()"
                            class="inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-white/10 px-4 py-1.5 text-xs font-semibold text-white transition-all duration-200 hover:border-emerald-400/40 hover:bg-emerald-500/20 hover:text-emerald-300 backdrop-blur-sm">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        {{ __('Kongsi') }}
                    </button>

                    @if($locationString)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-medium text-slate-300 backdrop-blur-sm">
                            <svg class="h-3 w-3 text-emerald-400/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                            {{ $locationString }}
                        </span>
                    @endif

                    @can('update', $institution)
                        <a href="{{ route('filament.admin.resources.institutions.edit', ['record' => $institution]) }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-white/10 px-4 py-1.5 text-xs font-semibold text-white transition-all duration-200 hover:border-gold-400/40 hover:bg-gold-500/20 hover:text-gold-200 backdrop-blur-sm">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487a2.25 2.25 0 113.182 3.182L7.5 19.213l-4.5.9.9-4.5L16.862 3.487z"/></svg>
                            {{ __('Edit Institusi') }}
                        </a>
                    @endcan
                </div>

            </div>
        </div>
        {{-- Layered bottom edge --}}
        <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-emerald-500/40 to-transparent"></div>
        <div class="absolute inset-x-0 bottom-px h-px bg-gradient-to-r from-transparent via-gold-400/20 to-transparent"></div>
        <div class="absolute inset-x-0 bottom-0 h-8 bg-gradient-to-t from-slate-50/80 to-transparent"></div>
    </header>

    {{-- ═══════════════════════════════════════════════════════════
         MAIN CONTENT
    ═══════════════════════════════════════════════════════════ --}}
    {{-- Gradient continuation from hero --}}
    <div class="pointer-events-none relative -mt-1">
        <div class="absolute inset-x-0 top-0 h-40 bg-gradient-to-b from-emerald-950/[0.06] via-emerald-50/30 to-transparent"></div>
    </div>
    <div class="container relative mx-auto mt-4 max-w-6xl px-6 pb-16 lg:px-8">
        <div class="flex flex-col gap-8 lg:grid lg:grid-cols-[1fr_340px]">

            {{-- LEFT COLUMN — Main Content --}}
            <div class="institution-main-column order-2 space-y-10 lg:order-1">

                {{-- ─── EVENTS (Upcoming / Past) ─── --}}
                <section class="scroll-reveal reveal-up" x-intersect.once="$el.classList.add('revealed')" x-data="{ tab: 'upcoming', view: 'list', calendarMonth: new Date().getMonth(), calendarYear: new Date().getFullYear(), calendarEvents: {{ Js::from($calendarEvents) }} }">
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-lg shadow-emerald-500/25">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                            </div>
                            <div>
                                <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Majlis') }}</h2>
                                <div class="mt-0.5 h-0.5 w-12 rounded-full bg-gradient-to-r from-emerald-500 to-transparent"></div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            {{-- Tab toggle: Upcoming / Past --}}
                            <div class="flex items-center gap-1 rounded-xl bg-slate-100 p-1">
                                <button @click="tab = 'upcoming'; view = 'list'" :class="tab === 'upcoming' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-700'" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all duration-200">
                                    <svg class="hidden h-3.5 w-3.5 sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                                    {{ __('Akan Datang') }}
                                    <span class="ml-0.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-slate-200 px-1.5 text-[10px] font-bold text-slate-600">{{ $upcomingTotal }}</span>
                                </button>
                                <button @click="tab = 'past'" :class="tab === 'past' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-700'" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all duration-200 disabled:cursor-not-allowed disabled:opacity-50" @disabled($pastTotal === 0)>
                                    <svg class="hidden h-3.5 w-3.5 sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ __('Lepas') }}
                                    <span class="ml-0.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-slate-200 px-1.5 text-[10px] font-bold text-slate-600">{{ $pastTotal }}</span>
                                </button>
                            </div>

                            {{-- View toggle (list/calendar) — only for upcoming tab --}}
                            @if($upcomingEvents->isNotEmpty())
                                <div x-show="tab === 'upcoming'" class="flex items-center gap-1 rounded-xl bg-slate-100 p-1">
                                    <button @click="view = 'list'" :class="view === 'list' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-700'" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all duration-200">
                                        <svg class="hidden h-3.5 w-3.5 sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                                        {{ __('Senarai') }}
                                    </button>
                                    <button @click="view = 'calendar'" :class="view === 'calendar' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-700'" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all duration-200">
                                        <svg class="hidden h-3.5 w-3.5 sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z"/></svg>
                                        {{ __('Kalendar') }}
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- ═══ UPCOMING TAB ═══ --}}
                    <div x-show="tab === 'upcoming'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                    @if($upcomingEvents->isEmpty())
                        <div class="rounded-2xl border-2 border-dashed border-slate-200 bg-white/60 p-12 text-center">
                            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-50">
                                <svg class="h-8 w-8 text-emerald-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                            </div>
                            <p class="text-base font-semibold text-slate-500">{{ __('Tiada majlis dijadualkan buat masa ini') }}</p>
                            <p class="mt-1 text-sm text-slate-400">{{ __('Semak semula nanti untuk kemas kini terbaru.') }}</p>
                        </div>
                    @else
                        {{-- LIST VIEW --}}
                        <div x-show="view === 'list'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                            <div class="space-y-4">
                                @foreach($upcomingEvents as $event)
                                    @php
                                        $venueLocation = $resolveVenueLocation($event);
                                        $eventFormatValue = $event->event_format?->value ?? $event->event_format;
                                        $isRemoteEvent = in_array($eventFormatValue, ['online', 'hybrid'], true);
                                    @endphp
                                    <a href="{{ route('events.show', $event) }}" wire:navigate wire:key="upcoming-{{ $event->id }}"
                                       class="group relative flex overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-xl hover:shadow-emerald-500/[0.08]">
                                        {{-- Date accent sidebar --}}
                                        <div class="flex w-[4.5rem] shrink-0 flex-col items-center justify-center bg-gradient-to-b {{ $isRemoteEvent ? 'from-sky-600 to-sky-800' : 'from-emerald-600 to-emerald-800' }} p-2.5 text-white sm:w-24 sm:p-3">
                                            <span class="text-[10px] font-bold uppercase tracking-widest {{ $isRemoteEvent ? 'text-sky-200/80' : 'text-emerald-200/80' }} sm:text-[11px]">{{ \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($event->starts_at, 'l') }}</span>
                                            <span class="font-heading text-2xl font-black leading-none sm:text-4xl">{{ \App\Support\Timezone\UserDateTimeFormatter::format($event->starts_at, 'd') }}</span>
                                            <span class="mt-0.5 text-[11px] font-bold tracking-wide {{ $isRemoteEvent ? 'text-sky-200/80' : 'text-emerald-200/80' }} sm:text-[13px]">{{ \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($event->starts_at, 'F') }}</span>
                                        </div>
                                        {{-- Event details --}}
                                        <div class="flex flex-1 flex-col justify-center gap-2 p-4 sm:p-5">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-200/60">
                                                    {{ $resolveEventTypeLabel($event->event_type) }}
                                                </span>
                                                @if($isRemoteEvent)
                                                    <span class="inline-flex animate-pulse items-center gap-1 rounded-full bg-sky-50 px-2.5 py-0.5 text-[11px] font-semibold text-sky-700 ring-1 ring-sky-200/80">
                                                        <span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span>
                                                        {{ $eventFormatValue === 'hybrid' ? __('Hybrid') : __('Online') }}
                                                    </span>
                                                @endif
                                            </div>
                                            <h3 class="font-heading text-base font-bold leading-snug text-slate-900 transition-colors group-hover:text-emerald-700 sm:text-lg">
                                                {{ $event->title }}
                                            </h3>
                                            <div class="space-y-1 text-sm text-slate-500">
                                                <div class="flex items-center gap-1.5">
                                                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    {{ $resolveEventTimeDisplay($event) }}
                                                    @if($event->ends_at)
                                                        <span class="text-slate-300">–</span> {{ $resolveEventEndTimeDisplay($event) }}
                                                    @endif
                                                </div>
                                                @if($venueLocation && $eventFormatValue !== 'online')
                                                    <div class="flex items-center gap-1.5">
                                                        <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                                                        <span class="line-clamp-1">{{ $venueLocation }}</span>
                                                    </div>
                                                @elseif($event->institution && $eventFormatValue !== 'online')
                                                    <div class="flex items-center gap-1.5">
                                                        <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6M4.5 9.75v10.5h15V9.75"/></svg>
                                                        {{ $event->institution->name }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                        {{-- Arrow --}}
                                        <div class="hidden items-center pr-5 sm:flex">
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-400 transition-all duration-300 group-hover:bg-emerald-100 group-hover:text-emerald-600">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>

                            {{-- Load more --}}
                            @if($upcomingTotal > $upcomingEvents->count())
                                <div class="mt-6 text-center">
                                    <button wire:click="loadMoreUpcoming" wire:loading.attr="disabled"
                                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 shadow-sm transition-all duration-200 hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700 disabled:opacity-50">
                                        <span wire:loading.remove wire:target="loadMoreUpcoming">{{ __('Lihat Lagi') }} ({{ $upcomingTotal - $upcomingEvents->count() }} {{ __('lagi') }})</span>
                                        <span wire:loading wire:target="loadMoreUpcoming" class="inline-flex items-center gap-2">
                                            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
                                            {{ __('Memuatkan...') }}
                                        </span>
                                    </button>
                                </div>
                            @endif
                        </div>

                        {{-- CALENDAR VIEW --}}
                        <div x-show="view === 'calendar'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                            <div class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-sm">
                                {{-- Calendar header --}}
                                <div class="flex items-center justify-between border-b border-slate-100 bg-gradient-to-r from-slate-50 to-transparent px-5 py-3">
                                    <button @click="calendarMonth--; if(calendarMonth < 0) { calendarMonth = 11; calendarYear--; }" class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
                                    </button>
                                    <h3 class="text-sm font-bold text-slate-700" x-text="new Date(calendarYear, calendarMonth).toLocaleDateString('{{ app()->getLocale() }}', { month: 'long', year: 'numeric' })"></h3>
                                    <button @click="calendarMonth++; if(calendarMonth > 11) { calendarMonth = 0; calendarYear++; }" class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                                    </button>
                                </div>
                                {{-- Day headers --}}
                                <div class="grid grid-cols-7 border-b border-slate-100 bg-slate-50/50">
                                    <template x-for="day in ['{{ __('Isn') }}','{{ __('Sel') }}','{{ __('Rab') }}','{{ __('Kha') }}','{{ __('Jum') }}','{{ __('Sab') }}','{{ __('Ahd') }}']">
                                        <div class="py-2 text-center text-[10px] font-bold uppercase tracking-widest text-slate-400" x-text="day"></div>
                                    </template>
                                </div>
                                {{-- Calendar grid --}}
                                <div class="grid grid-cols-7">
                                    <template x-for="(cell, idx) in (() => {
                                        const first = new Date(calendarYear, calendarMonth, 1);
                                        const lastDay = new Date(calendarYear, calendarMonth + 1, 0).getDate();
                                        let startDay = first.getDay();
                                        startDay = startDay === 0 ? 6 : startDay - 1;
                                        const cells = [];
                                        for (let i = 0; i < startDay; i++) cells.push({ day: null });
                                        for (let d = 1; d <= lastDay; d++) {
                                            const key = calendarYear + '-' + String(calendarMonth + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
                                            cells.push({ day: d, key, events: calendarEvents[key] || [] });
                                        }
                                        return cells;
                                    })()" :key="idx">
                                        <div class="relative min-h-[6rem] border-b border-r border-slate-100 p-1 sm:min-h-[7.5rem] sm:p-1.5"
                                            :class="cell.day === null ? 'bg-slate-50/30' : ''">
                                            <template x-if="cell.day !== null">
                                                <div>
                                                    <span class="text-xs font-medium" :class="cell.events?.length > 0 ? 'font-bold text-emerald-700' : 'text-slate-400'" x-text="cell.day"></span>
                                                    <template x-if="cell.events?.length > 0">
                                                        <div class="mt-0.5 space-y-0.5">
                                                            <template x-for="ev in cell.events.slice(0, 2)" :key="ev.id">
                                                                <a :href="ev.url" class="block rounded px-1 py-0.5 text-[10px] font-medium leading-snug whitespace-normal break-words transition"
                                                                   :class="ev.is_remote ? 'bg-sky-50 text-sky-700 hover:bg-sky-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'"
                                                                   x-text="ev.title"></a>
                                                            </template>
                                                            <template x-if="cell.events?.length > 2">
                                                                <span class="block text-[9px] font-semibold"
                                                                      :class="cell.events.some(ev => ev.is_remote) ? 'text-sky-500' : 'text-emerald-500'"
                                                                      x-text="'+' + (cell.events.length - 2) + ' lagi'"></span>
                                                            </template>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    @endif
                    </div>{{-- end upcoming tab --}}

                    {{-- ═══ PAST EVENTS TAB ═══ --}}
                    @if($pastEvents->isNotEmpty())
                    <div x-show="tab === 'past'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                        <div class="space-y-4">
                            @foreach($pastEvents as $event)
                                @php
                                    $pastVenueLocation = $resolveVenueLocation($event);
                                    $eventFormatValue = $event->event_format?->value ?? $event->event_format;
                                    $isRemoteEvent = in_array($eventFormatValue, ['online', 'hybrid'], true);
                                @endphp
                                <a href="{{ route('events.show', $event) }}" wire:navigate wire:key="past-{{ $event->id }}"
                                   class="group relative flex overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-xl hover:shadow-slate-500/[0.06]">
                                    <div class="flex w-[4.5rem] shrink-0 flex-col items-center justify-center bg-gradient-to-b {{ $isRemoteEvent ? 'from-sky-600 to-sky-800' : 'from-slate-500 to-slate-700' }} p-2.5 text-white sm:w-24 sm:p-3">
                                        <span class="text-[10px] font-bold uppercase tracking-widest {{ $isRemoteEvent ? 'text-sky-200/80' : 'text-slate-300/80' }} sm:text-[11px]">{{ \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($event->starts_at, 'l') }}</span>
                                        <span class="font-heading text-2xl font-black leading-none sm:text-4xl">{{ \App\Support\Timezone\UserDateTimeFormatter::format($event->starts_at, 'd') }}</span>
                                        <span class="mt-0.5 text-[11px] font-bold tracking-wide {{ $isRemoteEvent ? 'text-sky-200/80' : 'text-slate-300/80' }} sm:text-[13px]">{{ \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($event->starts_at, 'F') }}</span>
                                    </div>
                                    <div class="flex flex-1 flex-col justify-center gap-2 p-4 sm:p-5">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200/60">
                                                {{ $resolveEventTypeLabel($event->event_type) }}
                                            </span>
                                            @if($isRemoteEvent)
                                                <span class="inline-flex animate-pulse items-center gap-1 rounded-full bg-sky-50 px-2.5 py-0.5 text-[11px] font-semibold text-sky-700 ring-1 ring-sky-200/80">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span>
                                                    {{ $eventFormatValue === 'hybrid' ? __('Hybrid') : __('Online') }}
                                                </span>
                                            @endif
                                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-200/60">
                                                {{ __('Selesai') }}
                                            </span>
                                        </div>
                                        <h3 class="font-heading text-base font-bold leading-snug text-slate-900 transition-colors group-hover:text-slate-700 sm:text-lg">
                                            {{ $event->title }}
                                        </h3>
                                        <div class="space-y-1 text-sm text-slate-500">
                                            <div class="flex items-center gap-1.5">
                                                <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                {{ $resolveEventTimeDisplay($event) }}
                                                @if($event->ends_at)
                                                    <span class="text-slate-300">–</span> {{ $resolveEventEndTimeDisplay($event) }}
                                                @endif
                                            </div>
                                            @if($pastVenueLocation && $eventFormatValue !== 'online')
                                                <div class="flex items-center gap-1.5">
                                                    <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                                                    <span class="line-clamp-1">{{ $pastVenueLocation }}</span>
                                                </div>
                                            @elseif($event->institution && $eventFormatValue !== 'online')
                                                <div class="flex items-center gap-1.5">
                                                    <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6M4.5 9.75v10.5h15V9.75"/></svg>
                                                    {{ $event->institution->name }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="hidden items-center pr-5 sm:flex">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-400 transition-all duration-300 group-hover:bg-slate-200 group-hover:text-slate-600">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>

                        @if($pastTotal > $pastEvents->count())
                            <div class="mt-6 text-center">
                                <button wire:click="loadMorePast" wire:loading.attr="disabled"
                                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 shadow-sm transition-all duration-200 hover:border-slate-300 hover:bg-slate-50 disabled:opacity-50">
                                    <span wire:loading.remove wire:target="loadMorePast">{{ __('Lihat Lagi') }} ({{ $pastTotal - $pastEvents->count() }} {{ __('lagi') }})</span>
                                    <span wire:loading wire:target="loadMorePast" class="inline-flex items-center gap-2">
                                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
                                        {{ __('Memuatkan...') }}
                                    </span>
                                </button>
                            </div>
                        @endif
                    </div>
                    @endif
                </section>

                {{-- ─── SPEAKERS ─── --}}
                @if($speakers->isNotEmpty())
                    <section class="scroll-reveal reveal-up" x-intersect.once="$el.classList.add('revealed')" style="--reveal-d: 80ms">
                        <div class="mb-5 flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-gold-500 to-gold-700 text-white shadow-lg shadow-gold-500/20">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                            </div>
                            <div>
                                <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Barisan Penceramah') }}</h2>
                                <div class="mt-0.5 h-0.5 w-12 rounded-full bg-gradient-to-r from-gold-500 to-transparent"></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                            @foreach($speakers as $speaker)
                                @php
                                    $speakerAvatarUrl = $speaker->hasMedia('avatar')
                                        ? $speaker->getFirstMediaUrl('avatar', 'profile')
                                        : ($speaker->default_avatar_url ?? '');
                                    $speakerPosition = $speaker->pivot->position;
                                    $isPrimarySpeaker = $speaker->pivot->is_primary;
                                @endphp
                                <a href="{{ route('speakers.show', $speaker) }}" wire:navigate wire:key="speaker-{{ $speaker->id }}"
                                   class="group relative flex flex-col items-center gap-3 rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:border-gold-200 hover:shadow-lg hover:shadow-gold-500/[0.06]">
                                    @if($isPrimarySpeaker)
                                        <div class="absolute right-2 top-2 flex h-5 w-5 items-center justify-center rounded-full bg-gold-100 text-gold-600" title="{{ __('Penceramah Utama') }}">
                                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.4 7.4h7.6l-6 4.6 2.3 7-6.3-4.6L5.7 21l2.3-7L2 9.4h7.6z"/></svg>
                                        </div>
                                    @endif
                                    <div class="relative h-20 w-20 overflow-hidden rounded-full border-2 border-slate-100 bg-slate-100 transition-all duration-300 group-hover:border-gold-200 group-hover:shadow-md">
                                        @if($speakerAvatarUrl)
                                            <img src="{{ $speakerAvatarUrl }}" alt="{{ $speaker->name }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110" loading="lazy">
                                        @else
                                            <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-slate-100 to-slate-200">
                                                <svg class="h-8 w-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="min-w-0 text-center">
                                        <h3 class="truncate text-sm font-bold text-slate-900 transition-colors group-hover:text-gold-700">{{ $speaker->name }}</h3>
                                        @if($speakerPosition)
                                            <p class="mt-0.5 truncate text-xs text-slate-500">{{ $speakerPosition }}</p>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- ─── ABOUT / DESCRIPTION ─── --}}
                @if($institution->description)
                    <section class="scroll-reveal reveal-scale" x-intersect.once="$el.classList.add('revealed')" style="--reveal-d: 120ms">
                        <div class="mb-5 flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-white shadow-lg shadow-emerald-500/20">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6M4.5 9.75v10.5h15V9.75"/></svg>
                            </div>
                            <div>
                                <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Mengenai Kami') }}</h2>
                                <div class="mt-0.5 h-0.5 w-12 rounded-full bg-gradient-to-r from-emerald-500 to-transparent"></div>
                            </div>
                        </div>
                        <div class="relative rounded-2xl border border-slate-200/70 bg-white p-6 shadow-sm md:p-8">
                            {{-- Left accent bar --}}
                            <div class="absolute inset-y-4 left-0 w-1 rounded-full bg-gradient-to-b from-emerald-400 via-emerald-500/60 to-transparent"></div>
                            <div class="prose prose-slate prose-sm max-w-none pl-4 prose-headings:font-heading prose-headings:tracking-tight prose-p:leading-relaxed prose-a:text-emerald-600 prose-a:no-underline hover:prose-a:underline prose-strong:text-slate-800">
                                {!! $institution->description !!}
                            </div>
                        </div>
                    </section>
                @endif

                {{-- ─── GALLERY ─── --}}
                @if($gallery->count() > 0)
                    <section class="scroll-reveal reveal-blur" x-intersect.once="$el.classList.add('revealed')" style="--reveal-d: 160ms">
                        <div class="mb-5 flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-slate-600 to-slate-800 text-white shadow-lg shadow-slate-500/20">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H3.75a2.25 2.25 0 00-2.25 2.25v12a2.25 2.25 0 002.25 2.25zm15-14.25a1.125 1.125 0 11-2.25 0 1.125 1.125 0 012.25 0z"/></svg>
                            </div>
                            <div>
                                <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Galeri') }}</h2>
                                <div class="mt-0.5 h-0.5 w-12 rounded-full bg-gradient-to-r from-slate-400 to-transparent"></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach($gallery as $index => $image)
                                <div class="group relative aspect-[4/3] overflow-hidden rounded-xl bg-slate-100 ring-1 ring-slate-200/50 transition-all duration-300 hover:ring-2 hover:ring-emerald-300/50 hover:shadow-lg {{ $index === 0 && $gallery->count() >= 3 ? 'col-span-2 row-span-2 aspect-square sm:aspect-[4/3]' : '' }}">
                                    <img src="{{ $image->getAvailableUrl(['gallery_thumb']) }}" alt="{{ __('Galeri') }}"
                                         class="h-full w-full object-cover transition-all duration-700 group-hover:scale-110" loading="lazy">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/30 via-transparent to-transparent opacity-0 transition-opacity duration-500 group-hover:opacity-100"></div>
                                    <div class="absolute bottom-3 left-3 opacity-0 transition-all duration-500 group-hover:opacity-100">
                                        <span class="rounded-lg bg-black/40 px-2.5 py-1 text-[11px] font-medium text-white/90 backdrop-blur-sm">{{ $index + 1 }}/{{ $gallery->count() }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

            </div>

            {{-- RIGHT COLUMN — Sidebar --}}
            <div class="institution-sidebar-column order-1 space-y-6 lg:order-2 lg:sticky lg:top-6 lg:self-start">

                {{-- ─── ISLAMIC INSPIRATION ─── --}}
                <x-sidebar-inspiration />

                {{-- ─── SOCIAL MEDIA (Mobile: below inspiration) ─── --}}
                @if($socialLinks->isNotEmpty())
                    <section class="scroll-reveal reveal-right rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-100/50" x-intersect.once="$el.classList.add('revealed')">
                        <div class="mb-4 flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-slate-600 to-slate-800 text-white shadow-lg shadow-slate-500/20">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 119 0v3.75m-9 0h9m-9 0l-1.5 9.75h12L22.5 10.5m-9 0V4.875a2.625 2.625 0 00-5.25 0V10.5"/></svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">{{ __('Media Sosial') }}</h3>
                                <div class="mt-0.5 h-0.5 w-10 rounded-full bg-gradient-to-r from-slate-400 to-transparent"></div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            @foreach($socialLinks as $social)
                                @php
                                    $platform = strtolower((string) $social->platform);
                                    $linkClass = match ($platform) {
                                        'website' => 'hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-600 hover:shadow-emerald-500/10',
                                        'facebook' => 'hover:border-blue-200 hover:bg-blue-50 hover:text-blue-600 hover:shadow-blue-500/10',
                                        'instagram' => 'hover:border-pink-200 hover:bg-pink-50 hover:text-pink-600 hover:shadow-pink-500/10',
                                        'youtube' => 'hover:border-red-200 hover:bg-red-50 hover:text-red-600 hover:shadow-red-500/10',
                                        'telegram' => 'hover:border-sky-200 hover:bg-sky-50 hover:text-sky-600 hover:shadow-sky-500/10',
                                        'whatsapp' => 'hover:border-green-200 hover:bg-green-50 hover:text-green-600 hover:shadow-green-500/10',
                                        default => 'hover:border-slate-300 hover:bg-slate-100 hover:text-slate-900 hover:shadow-slate-500/10',
                                    };
                                    $title = \App\Enums\SocialMediaPlatform::tryFrom($platform)?->getLabel() ?? ucfirst($platform);
                                @endphp
                                <a href="{{ $social->url }}" target="_blank" rel="noopener noreferrer"
                                   class="group inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-slate-50/50 text-slate-500 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md {{ $linkClass }}"
                                   title="{{ $title }}">
                                    @switch($platform)
                                        @case('website')
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/></svg>
                                            @break
                                        @case('facebook')
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                            @break
                                        @case('instagram')
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="5"/><circle cx="17.5" cy="6.5" r="1.5" fill="currentColor" stroke="none"/></svg>
                                            @break
                                        @case('youtube')
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/></svg>
                                            @break
                                        @case('twitter')
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                                            @break
                                        @case('tiktok')
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1v-3.5a6.37 6.37 0 00-.79-.05A6.34 6.34 0 003.15 15.2a6.34 6.34 0 0010.86 4.48V13a8.28 8.28 0 005.58 2.15V11.7a4.84 4.84 0 01-3.77-1.78v-.01l.01-.01V6.69h3.76z"/></svg>
                                            @break
                                        @case('telegram')
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 000 12a12 12 0 0012 12 12 12 0 0012-12A12 12 0 0012 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 01.171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                                            @break
                                        @case('whatsapp')
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20.52 3.48A11.86 11.86 0 0012.06 0C5.45 0 .08 5.37.08 11.98c0 2.11.55 4.17 1.59 5.99L0 24l6.2-1.63a11.9 11.9 0 005.86 1.49h.01c6.61 0 11.98-5.37 11.98-11.98 0-3.2-1.25-6.2-3.53-8.4zM12.07 21.8h-.01a9.8 9.8 0 01-5-1.37l-.36-.21-3.68.97.98-3.59-.23-.37a9.76 9.76 0 01-1.5-5.25c0-5.42 4.41-9.83 9.84-9.83 2.63 0 5.1 1.02 6.96 2.88a9.77 9.77 0 012.88 6.95c0 5.43-4.42 9.84-9.85 9.84zm5.4-7.35c-.3-.15-1.78-.88-2.06-.98-.28-.1-.48-.15-.68.15-.2.3-.78.98-.95 1.18-.17.2-.35.23-.65.08-.3-.15-1.26-.46-2.4-1.48a8.96 8.96 0 01-1.66-2.07c-.18-.3-.02-.46.14-.61.14-.14.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.38-.02-.53-.08-.15-.68-1.64-.94-2.25-.25-.6-.5-.52-.68-.53h-.58c-.2 0-.53.08-.8.38-.27.3-1.04 1.02-1.04 2.49 0 1.47 1.07 2.89 1.22 3.09.15.2 2.1 3.2 5.1 4.49.71.31 1.26.49 1.69.63.71.23 1.35.2 1.86.12.57-.08 1.78-.73 2.03-1.44.25-.71.25-1.31.17-1.44-.07-.13-.27-.2-.57-.35z"/></svg>
                                            @break
                                        @default
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-1.061l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/></svg>
                                    @endswitch
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- ─── CONTACT & ADDRESS ─── --}}
                @if($contacts->isNotEmpty() || $address)
                    <div class="scroll-reveal reveal-right rounded-2xl border border-slate-200/70 bg-white shadow-sm" x-intersect.once="$el.classList.add('revealed')">
                        <div class="border-b border-slate-100 px-5 py-4">
                            <h3 class="flex items-center gap-2 text-sm font-bold text-slate-900">
                                <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
                                {{ __('Hubungi') }}
                            </h3>
                        </div>
                        <div class="space-y-4 p-5">
                            @foreach($contacts as $contact)
                                @if($contact->value)
                                    <div class="flex items-center gap-3 group">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 transition-colors group-hover:bg-emerald-100">
                                            @if(($contact->category instanceof \App\Enums\ContactCategory && $contact->category === \App\Enums\ContactCategory::Email) || $contact->category === 'email')
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                                            @else
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
                                            @endif
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <span class="block break-all text-sm font-medium text-slate-800">{{ $contact->value }}</span>
                                        </div>
                                    </div>
                                @endif
                            @endforeach

                            @if($address && ($address->line1 || $address->city?->name || $address->state?->name))
                                <div class="flex items-center gap-3 group">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 transition-colors group-hover:bg-emerald-100">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium leading-snug text-slate-800">
                                            @if($address->line1) {{ $address->line1 }} @endif
                                            @if($address->line2), {{ $address->line2 }}@endif
                                            @if($address->line1 || $address->line2) <br> @endif
                                            @if($address->postcode) {{ $address->postcode }} @endif
                                            @if($address->city?->name) {{ $address->city->name }} @endif
                                            @if($address->state?->name)
                                                <br>{{ $address->state->name }}
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                {{-- Navigation buttons --}}
                                @if($wazeUrl || $address->google_maps_url)
                                    <div class="flex gap-2 pl-11">
                                        @if($wazeUrl)
                                            <a href="{{ $wazeUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-1.5 text-xs font-bold text-cyan-700 transition-colors hover:bg-cyan-100">
                                                <img src="{{ asset('images/waze-app-icon-seeklogo.svg') }}" alt="Waze" class="h-3.5 w-3.5" loading="lazy">
                                                Waze
                                            </a>
                                        @endif
                                        @if($address->google_maps_url)
                                            <a href="{{ $address->google_maps_url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 transition-colors hover:bg-blue-100">
                                                <img src="{{ asset('images/google-maps.svg') }}" alt="Google Maps" class="h-3.5 w-3.5" loading="lazy">
                                                Google Maps
                                            </a>
                                        @endif
                                    </div>
                                @endif

                                {{-- Mini map preview --}}
                                @if($address->google_maps_url)
                                    @if($googleMapsEmbedUrl)
                                        <div class="overflow-hidden rounded-xl border border-slate-200/70">
                                            <iframe src="{{ $googleMapsEmbedUrl }}"
                                                    class="h-[220px] w-full"
                                                    loading="lazy"
                                                    referrerpolicy="no-referrer-when-downgrade"
                                                    allowfullscreen
                                                    title="{{ __('Peta Lokasi') }}"></iframe>
                                        </div>
                                    @elseif($address->lat && $address->lng && filled($googleMapsApiKey))
                                        <div class="overflow-hidden rounded-xl border border-slate-200/70">
                                            <a href="{{ $address->google_maps_url }}" target="_blank" rel="noopener" class="block">
                                                <img src="https://maps.googleapis.com/maps/api/staticmap?center={{ $address->lat }},{{ $address->lng }}&zoom=15&size=340x180&scale=2&markers=color:0x059669%7C{{ $address->lat }},{{ $address->lng }}&style=feature:poi%7Cvisibility:off&key={{ $googleMapsApiKey }}"
                                                     alt="{{ __('Peta') }}"
                                                     class="h-[140px] w-full object-cover transition-opacity hover:opacity-90"
                                                     loading="lazy"
                                                     onerror="this.parentElement.parentElement.style.display='none'">
                                            </a>
                                        </div>
                                    @else
                                        <a href="{{ $address->google_maps_url }}" target="_blank" rel="noopener" class="flex items-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-semibold text-blue-700 transition-colors hover:bg-blue-100">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                                            {{ __('Buka di Google Maps') }}
                                        </a>
                                    @endif
                                @endif
                            @endif
                        </div>
                    </div>
                @endif

                {{-- ─── SPACES / FACILITIES ─── --}}
                @if($spaces->isNotEmpty())
                    <div class="scroll-reveal reveal-right rounded-2xl border border-slate-200/70 bg-white shadow-sm" x-intersect.once="$el.classList.add('revealed')" style="--reveal-d: 100ms">
                        <div class="border-b border-slate-100 px-5 py-4">
                            <h3 class="flex items-center gap-2 text-sm font-bold text-slate-900">
                                <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>
                                {{ __('Ruang & Kemudahan') }}
                            </h3>
                        </div>
                        <div class="divide-y divide-slate-100">
                            @foreach($spaces as $space)
                                <div class="flex items-center gap-3 px-5 py-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 01-1.125-1.125v-3.75zM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 01-1.125-1.125v-8.25zM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 01-1.125-1.125v-2.25z"/></svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <span class="text-sm font-semibold text-slate-800">{{ $space->name }}</span>
                                        @if($space->capacity)
                                            <span class="ml-1.5 text-xs text-slate-400">({{ $space->capacity }} {{ __('orang') }})</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- ─── DONATION CHANNELS ─── --}}
                @if($donationChannels->isNotEmpty())
                    <div class="scroll-reveal reveal-right rounded-2xl border border-gold-200/60 bg-gradient-to-b from-gold-50/50 to-white shadow-sm" x-intersect.once="$el.classList.add('revealed')" style="--reveal-d: 180ms">
                        <div class="border-b border-gold-200/40 px-5 py-4">
                            <h3 class="flex items-center gap-2 text-sm font-bold text-slate-900">
                                <svg class="h-4 w-4 text-gold-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
                                {{ __('Saluran Derma') }}
                            </h3>
                        </div>
                        <div class="divide-y divide-gold-100/50 p-2">
                            @foreach($donationChannels as $channel)
                                <div class="rounded-xl p-4 transition-colors hover:bg-gold-50/50">
                                    <div class="flex items-start gap-3">
                                        @php $qrUrl = $channel->getFirstMediaUrl('qr', 'thumb'); @endphp
                                        @if($qrUrl)
                                            <div class="shrink-0 overflow-hidden rounded-lg border border-gold-200/60 bg-white p-1 shadow-sm">
                                                <img src="{{ $qrUrl }}" alt="{{ __('Kod QR') }}" class="h-16 w-16 object-contain" loading="lazy">
                                            </div>
                                        @endif
                                        <div class="min-w-0 flex-1">
                                            @if($channel->label)
                                                <p class="text-xs font-bold text-gold-700">{{ $channel->label }}</p>
                                            @endif
                                            <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ $channel->recipient }}</p>
                                            <p class="mt-0.5 text-xs text-slate-500">
                                                <span class="inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-600">{{ $channel->method_display }}</span>
                                            </p>
                                            <p class="mt-1 text-xs font-medium text-slate-600">{{ $channel->payment_details }}</p>
                                            @if($channel->reference_note)
                                                <p class="mt-1 text-[11px] italic text-slate-400">{{ $channel->reference_note }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- ─── JOIN MAJLISILMU CTA ─── --}}
                <x-join-majlisilmu-cta />

            </div>
        </div>
    </div>

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
                class="w-full max-w-lg overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-slate-200/50">

                <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/50 px-6 py-5">
                    <h3 class="font-heading text-xl font-bold text-slate-900">{{ __('Kongsi Institusi') }}</h3>
                    <button type="button" @click="shareModalOpen = false" class="inline-flex size-10 items-center justify-center rounded-full bg-white text-slate-500 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-50 hover:text-slate-700">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-6 p-6 sm:p-8">
                    <div class="grid grid-cols-2 gap-4">
                        <button type="button" @click="nativeShare()" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-4 py-3.5 text-sm font-bold text-white transition hover:bg-emerald-700">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684l6.632 3.316m-6.632-6l6.632-3.316"/></svg>
                            {{ __('Share Now') }}
                        </button>
                        <button type="button" @click="copyLink()" class="inline-flex items-center justify-center gap-2 rounded-2xl border-2 border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 transition hover:border-emerald-500 hover:text-emerald-700">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16h8M8 12h8m-6-8H6a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2v-4"/></svg>
                            {{ __('Copy Link') }}
                        </button>
                    </div>

                    <div x-show="copied"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 -translate-y-2"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        class="flex items-center justify-center gap-2 rounded-xl bg-emerald-50 py-2 text-sm font-bold text-emerald-600">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        {{ __('Pautan disalin ke papan klip!') }}
                    </div>

                    <div>
                        <p class="mb-4 text-center text-xs font-bold uppercase tracking-widest text-slate-400">{{ __('Or share via') }}</p>
                        <div class="grid grid-cols-4 gap-3">
                            <a href="{{ $shareLinks['whatsapp'] }}" target="_blank" rel="noopener" class="flex flex-col items-center gap-1.5 rounded-2xl border border-slate-200/60 bg-slate-50 p-3 text-[10px] font-bold transition hover:-translate-y-1 hover:border-[#25D366] hover:bg-[#25D366]/10 hover:text-[#25D366]">WhatsApp</a>
                            <a href="{{ $shareLinks['telegram'] }}" target="_blank" rel="noopener" class="flex flex-col items-center gap-1.5 rounded-2xl border border-slate-200/60 bg-slate-50 p-3 text-[10px] font-bold transition hover:-translate-y-1 hover:border-[#0088cc] hover:bg-[#0088cc]/10 hover:text-[#0088cc]">Telegram</a>
                            <a href="{{ $shareLinks['line'] }}" target="_blank" rel="noopener" class="flex flex-col items-center gap-1.5 rounded-2xl border border-slate-200/60 bg-slate-50 p-3 text-[10px] font-bold transition hover:-translate-y-1 hover:border-[#06C755] hover:bg-[#06C755]/10 hover:text-[#06C755]">LINE</a>
                            <a href="{{ $shareLinks['facebook'] }}" target="_blank" rel="noopener" class="flex flex-col items-center gap-1.5 rounded-2xl border border-slate-200/60 bg-slate-50 p-3 text-[10px] font-bold transition hover:-translate-y-1 hover:border-[#1877F2] hover:bg-[#1877F2]/10 hover:text-[#1877F2]">Facebook</a>
                            <a href="{{ $shareLinks['x'] }}" target="_blank" rel="noopener" class="flex flex-col items-center gap-1.5 rounded-2xl border border-slate-200/60 bg-slate-50 p-3 text-[10px] font-bold transition hover:-translate-y-1 hover:border-slate-900 hover:bg-slate-900/10 hover:text-slate-900">X</a>
                            <a href="{{ $shareLinks['instagram'] }}" target="_blank" rel="noopener" @click="copyLink()" class="flex flex-col items-center gap-1.5 rounded-2xl border border-slate-200/60 bg-slate-50 p-3 text-[10px] font-bold transition hover:-translate-y-1 hover:border-[#E4405F] hover:bg-[#E4405F]/10 hover:text-[#E4405F]">Instagram</a>
                            <a href="{{ $shareLinks['tiktok'] }}" target="_blank" rel="noopener" @click="copyLink()" class="flex flex-col items-center gap-1.5 rounded-2xl border border-slate-200/60 bg-slate-50 p-3 text-[10px] font-bold transition hover:-translate-y-1 hover:border-black hover:bg-black/10 hover:text-black">TikTok</a>
                            <a href="{{ $shareLinks['email'] }}" class="flex flex-col items-center gap-1.5 rounded-2xl border border-slate-200/60 bg-slate-50 p-3 text-[10px] font-bold transition hover:-translate-y-1 hover:border-emerald-500 hover:bg-emerald-500/10 hover:text-emerald-600">Email</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
