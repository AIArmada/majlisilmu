<?php

use App\Models\Speaker;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public Speaker $speaker;

    public int $upcomingPerPage = 10;

    public int $pastPerPage = 10;

    public bool $isFollowing = false;

    public function mount(Speaker $speaker): void
    {
        if ($speaker->status !== 'verified' && ! auth()->user()?->hasAnyRole(['super_admin', 'moderator'])) {
            abort(404);
        }

        $speaker->load([
            'media',
            'socialMedia',
            'address.state',
            'address.city',
            'address.district',
            'address.subdistrict',
            'address.country',
            'institutions' => fn ($q) => $q->orderByPivot('is_primary', 'desc')->limit(3),
            'institutions.media',
        ]);

        $this->speaker = $speaker;
        $this->isFollowing = auth()->user()?->isFollowing($speaker) ?? false;
    }

    public function toggleFollow(): void
    {
        $user = auth()->user();

        if (! $user) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        if ($this->isFollowing) {
            $user->unfollow($this->speaker);
            $this->isFollowing = false;
        } else {
            $user->follow($this->speaker);
            $this->isFollowing = true;
        }
    }

    public function loadMoreUpcoming(): void
    {
        $this->upcomingPerPage += 10;
    }

    public function loadMorePast(): void
    {
        $this->pastPerPage += 10;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Event>
     */
    public function getUpcomingEventsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->speaker->events()
            ->active()
            ->where('starts_at', '>=', now())
            ->with([
                'institution',
                'institution.address.state',
                'institution.address.district',
                'institution.address.subdistrict',
                'venue.address.state',
                'venue.address.district',
                'venue.address.subdistrict',
                'media',
            ])
            ->orderBy('starts_at', 'asc')
            ->take($this->upcomingPerPage)
            ->get();
    }

    public function getUpcomingTotalProperty(): int
    {
        return $this->speaker->events()
            ->active()
            ->where('starts_at', '>=', now())
            ->count();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Event>
     */
    public function getPastEventsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->speaker->events()
            ->active()
            ->where('starts_at', '<', now())
            ->with([
                'institution',
                'institution.address.state',
                'institution.address.district',
                'institution.address.subdistrict',
                'venue.address.state',
                'venue.address.district',
                'venue.address.subdistrict',
                'media',
            ])
            ->orderBy('starts_at', 'desc')
            ->take($this->pastPerPage)
            ->get();
    }

    public function getPastTotalProperty(): int
    {
        return $this->speaker->events()
            ->active()
            ->where('starts_at', '<', now())
            ->count();
    }

    public function rendering($view): void
    {
        $view->title($this->speaker->formatted_name . ' - ' . config('app.name'));
    }
};
?>

@php
    $speaker = $this->speaker;
    $upcomingEvents = $this->upcomingEvents;
    $pastEvents = $this->pastEvents;
    $upcomingTotal = $this->upcomingTotal;
    $pastTotal = $this->pastTotal;

    $avatarUrl = $speaker->hasMedia('avatar')
        ? $speaker->getFirstMediaUrl('avatar', 'profile')
        : $speaker->default_avatar_url;
    $coverUrl = $speaker->getFirstMedia('cover')?->getAvailableUrl(['banner']) ?? '';
    $gallery = $speaker->getMedia('gallery');
    $bioRenderer = RichContentRenderer::make($speaker->bio);
    $bioHtml = is_array($speaker->bio)
        ? $bioRenderer->toHtml()
        : $speaker->bio;
    $isBioFilled = is_array($speaker->bio) ? filled($bioRenderer->toText()) : filled($speaker->bio);
    $bioExcerpt = $isBioFilled ? Str::limit(strip_tags($bioHtml), 180) : null;

    // Social media
    $socialLinks = $speaker->socialMedia->mapWithKeys(fn ($s) => [$s->platform => $s->url]);
    $hasSocialLinks = $socialLinks->isNotEmpty();
    $showJoinCta = auth()->guest();
    $hasInspiration = \App\Models\Inspiration::query()->active()->forLocale()->exists();
    $useDesktopSidebar = $showJoinCta || $hasInspiration;

    // Institutions (primary first, max 3)
    $institutions = $speaker->institutions;

    // Qualifications
    $qualifications = is_array($speaker->qualifications) ? $speaker->qualifications : [];

    // Location
    $locationParts = array_filter([
        $speaker->addressModel?->subdistrict?->name,
        $speaker->addressModel?->district?->name,
        $speaker->addressModel?->state?->name,
    ]);
    $locationString = implode(', ', $locationParts);

    // Event type label
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
    $resolveVenueLocation = static function (\App\Models\Event $event): string {
        $venueName = $event->venue?->name;
        $institutionName = $event->institution?->name;
        $primaryLocationName = $venueName ?: $institutionName;
        $address = $event->venue?->addressModel ?? $event->institution?->addressModel;

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
            'pending' => $e->status instanceof \App\States\EventStatus\Pending,
            'is_remote' => in_array($formatValue, ['online', 'hybrid'], true),
        ];
    })->values())->toArray();
@endphp

<div class="min-h-screen bg-slate-50/80">

    {{-- ═══════════════════════════════════════════════════════════
         CINEMATIC HERO — Dramatic, layered depth with atmosphere
    ═══════════════════════════════════════════════════════════ --}}
    <header class="noise-overlay relative isolate overflow-hidden bg-gradient-to-br from-slate-950 via-emerald-950/80 to-slate-950" style="min-height: 300px">
        {{-- Ambient gradient background orbs — animated floating --}}
        <div class="pointer-events-none absolute inset-0">
            <div class="animate-float-drift absolute -top-20 left-[10%] h-[30rem] w-[30rem] rounded-full bg-emerald-500/25 blur-[100px]"></div>
            <div class="animate-float-drift-alt absolute -bottom-16 right-[5%] h-[24rem] w-[24rem] rounded-full bg-gold-400/18 blur-[90px]"></div>
            <div class="animate-float-drift-slow absolute top-8 right-[35%] h-[20rem] w-[20rem] rounded-full bg-teal-400/18 blur-[80px]"></div>
            {{-- Extra accent orbs for richer depth --}}
            <div class="animate-float-drift-alt absolute -top-10 right-[15%] h-[16rem] w-[16rem] rounded-full bg-emerald-400/12 blur-[70px]"></div>
            <div class="animate-float-drift absolute -bottom-8 left-[30%] h-[18rem] w-[18rem] rounded-full bg-gold-300/10 blur-[100px]"></div>
        </div>

        {{-- Spotlight radial glow behind content --}}
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_80%_70%_at_55%_50%,rgba(16,185,129,0.14)_0%,transparent_60%)]"></div>

        {{-- Cover image or atmospheric fallback --}}
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

        {{-- Hero content: Avatar + Name side-by-side on the hero --}}
        <div class="container relative z-10 mx-auto flex max-w-6xl flex-col items-center gap-6 px-6 pb-10 pt-12 sm:flex-row sm:items-center sm:gap-8 lg:px-8 lg:pb-12 lg:pt-16">
            {{-- Avatar with decorative ring --}}
            <div class="animate-scale-in relative shrink-0" style="animation-delay: 200ms; opacity: 0;">
                <div class="animate-glow-breathe absolute -inset-3 rounded-full bg-gradient-to-br from-emerald-400/30 via-gold-400/15 to-emerald-600/30 blur-lg"></div>
                <div class="absolute -inset-1.5 rounded-full bg-gradient-to-br from-emerald-400/20 via-transparent to-gold-400/20"></div>
                <div class="relative h-32 w-32 overflow-hidden rounded-full ring-2 ring-white/25 shadow-2xl shadow-emerald-950/50 sm:h-36 sm:w-36 lg:h-44 lg:w-44">
                    <img src="{{ $avatarUrl }}" alt="{{ $speaker->name }}" class="h-full w-full object-cover" width="160" height="160">
                </div>
            </div>

            {{-- Name & title on the hero --}}
            <div class="animate-fade-in-up text-center sm:text-left" style="animation-delay: 350ms; opacity: 0;">
                @if($speaker->job_title)
                    <p class="mb-1.5 text-sm font-medium tracking-wider text-emerald-400/80 uppercase">{{ $speaker->job_title }}</p>
                @endif
                <h1 class="text-hero-glow font-heading text-3xl font-bold tracking-tight text-white sm:text-4xl lg:text-5xl">
                    {{ $speaker->formatted_name }}
                </h1>
                {{-- Decorative gold shimmer line --}}
                <div class="shimmer-line mx-auto mt-3 h-0.5 w-20 rounded-full bg-gradient-to-r from-gold-400/80 via-gold-300/60 to-gold-600/30 sm:mx-0"></div>

                {{-- Institution affiliation --}}
                @if($institutions->isNotEmpty())
                    <div class="mt-3 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 sm:justify-start">
                        @foreach($institutions as $inst)
                            @php
                                $position = $inst->pivot->position;
                                $isPrimary = $inst->pivot->is_primary;
                                $institutionChipImageUrl = $inst->getFirstMediaUrl('cover', 'banner') ?: $inst->getFirstMediaUrl('logo');
                            @endphp
                            <a href="{{ route('institutions.show', $inst) }}" wire:navigate
                               class="group inline-flex items-center gap-2.5 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 backdrop-blur-sm transition-all duration-200 hover:border-emerald-400/30 hover:bg-white/10">
                                @if($institutionChipImageUrl)
                                    <img src="{{ $institutionChipImageUrl }}" alt="{{ $inst->name }}" class="aspect-video w-8 shrink-0 rounded object-cover">
                                @else
                                    <svg class="h-4 w-4 shrink-0 text-emerald-400/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>
                                @endif
                                <span class="flex flex-col leading-tight">
                                    <span class="text-xs font-semibold text-white/90 group-hover:text-emerald-300 transition-colors duration-200">{{ $inst->name }}</span>
                                    @if($position)
                                        <span class="text-[10px] text-slate-400/80">{{ $position }}</span>
                                    @endif
                                </span>
                                @if($isPrimary)
                                    <span class="ml-0.5 inline-flex h-1.5 w-1.5 rounded-full bg-emerald-400" title="{{ __('Institusi Utama') }}"></span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif

                {{-- Follow button + quick badges --}}
                <div class="mt-4 flex flex-wrap items-center justify-center gap-2.5 sm:justify-start">
                    {{-- Follow button --}}
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
                    @if($locationString)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-medium text-slate-300 backdrop-blur-sm">
                            <svg class="h-3 w-3 text-emerald-400/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                            {{ $locationString }}
                        </span>
                    @endif
                    @if($speaker->is_freelance)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-gold-500/20 bg-gold-500/10 px-3 py-1 text-xs font-semibold text-gold-300 backdrop-blur-sm">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                            {{ __('Bebas / Freelance') }}
                        </span>
                    @endif
                    @can('update', $speaker)
                        <a href="{{ route('filament.admin.resources.speakers.edit', ['record' => $speaker]) }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-white/10 px-4 py-1.5 text-xs font-semibold text-white transition-all duration-200 hover:border-gold-400/40 hover:bg-gold-500/20 hover:text-gold-200 backdrop-blur-sm">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487a2.25 2.25 0 113.182 3.182L7.5 19.213l-4.5.9.9-4.5L16.862 3.487z"/></svg>
                            {{ __('Edit Penceramah') }}
                        </a>
                    @endcan
                </div>

            </div>
        </div>
        {{-- Bottom edge accent — layered glow line --}}
        <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-emerald-400/60 to-transparent"></div>
        <div class="absolute inset-x-0 -bottom-0.5 h-1 bg-gradient-to-r from-transparent via-emerald-500/20 to-transparent blur-sm"></div>
    </header>

    {{-- ═══════════════════════════════════════════════════════════
         MAIN CONTENT — Events-first layout
    ═══════════════════════════════════════════════════════════ --}}
    <div class="relative">
        {{-- Subtle continuation of hero atmosphere into content --}}
        <div class="pointer-events-none absolute inset-x-0 top-0 h-64 bg-gradient-to-b from-emerald-50/60 via-slate-50/40 to-transparent"></div>

        <div class="container relative z-10 mx-auto mt-0 max-w-6xl px-4 pb-16 pt-8 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-8 {{ $useDesktopSidebar ? 'lg:grid lg:grid-cols-[1fr_340px]' : '' }}">

            {{-- LEFT COLUMN — Main Content --}}
            <div class="speaker-main-column order-1 space-y-10">

                {{-- ─── EVENTS SECTION (Tabs: Upcoming / Past) ─── --}}
                <section class="scroll-reveal reveal-up"
                         x-intersect.once="$el.classList.add('revealed')"
                         x-data="{ tab: 'upcoming', view: 'list', calendarMonth: new Date().getMonth(), calendarYear: new Date().getFullYear(), calendarEvents: {{ Js::from($calendarEvents) }} }">
                    {{-- Section header --}}
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

                        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                            {{-- Tab toggle: Upcoming / Past --}}
                            <div class="flex items-center gap-1 rounded-xl bg-slate-100 p-1">
                                <button @click="tab = 'upcoming'; view = 'list'" :class="tab === 'upcoming' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-700'" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all duration-200">
                                    <svg class="h-3.5 w-3.5 hidden sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                                    {{ __('Akan Datang') }}
                                    <span class="ml-0.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-slate-200 px-1.5 text-[10px] font-bold text-slate-600">{{ $upcomingTotal }}</span>
                                </button>
                                <button @click="tab = 'past'" :class="tab === 'past' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-700'" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all duration-200 disabled:cursor-not-allowed disabled:opacity-50" @disabled($pastTotal === 0)>
                                    <svg class="h-3.5 w-3.5 hidden sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ __('Lepas') }}
                                    <span class="ml-0.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-slate-200 px-1.5 text-[10px] font-bold text-slate-600">{{ $pastTotal }}</span>
                                </button>
                            </div>

                            {{-- View toggle (list/calendar) — only for upcoming tab --}}
                            @if($upcomingEvents->isNotEmpty())
                                <div x-show="tab === 'upcoming'" class="flex items-center gap-1 rounded-xl bg-slate-100 p-1">
                                    <button @click="view = 'list'" :class="view === 'list' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-700'" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all duration-200">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                                        <span class="hidden sm:inline">{{ __('Senarai') }}</span>
                                    </button>
                                    <button @click="view = 'calendar'" :class="view === 'calendar' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-700'" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all duration-200">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z"/></svg>
                                        <span class="hidden sm:inline">{{ __('Kalendar') }}</span>
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>

                {{-- ═══ UPCOMING TAB ═══ --}}
                <div x-show="tab === 'upcoming'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                @if($upcomingEvents->isEmpty())
                    <div class="rounded-2xl border-2 border-dashed border-slate-200 bg-white/80 p-10 text-center sm:p-12">
                        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-50 ring-1 ring-emerald-100">
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
                                    $isPendingEvent = $event->status instanceof \App\States\EventStatus\Pending;
                                @endphp
                                <a href="{{ route('events.show', $event) }}" wire:navigate wire:key="upcoming-{{ $event->id }}"
                                   class="group relative flex overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-transparent transition-all duration-300 hover:-translate-y-0.5 hover:border-emerald-200/80 hover:ring-emerald-100 hover:shadow-xl hover:shadow-emerald-500/[0.08]">
                                    {{-- Date accent sidebar --}}
                                    <div class="flex w-[4.5rem] shrink-0 flex-col items-center justify-center bg-gradient-to-b {{ $isPendingEvent ? 'from-amber-600 to-amber-800' : ($isRemoteEvent ? 'from-sky-600 to-sky-800' : 'from-emerald-600 to-emerald-800') }} p-2.5 text-white sm:w-24 sm:p-3">
                                        <span class="text-[10px] font-bold uppercase tracking-widest {{ $isPendingEvent ? 'text-amber-200/80' : ($isRemoteEvent ? 'text-sky-200/80' : 'text-emerald-200/80') }} sm:text-[11px]">{{ \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($event->starts_at, 'l') }}</span>
                                        <span class="font-heading text-2xl font-black leading-none sm:text-4xl">{{ \App\Support\Timezone\UserDateTimeFormatter::format($event->starts_at, 'd') }}</span>
                                        <span class="mt-0.5 text-[11px] font-bold tracking-wide {{ $isPendingEvent ? 'text-amber-200/80' : ($isRemoteEvent ? 'text-sky-200/80' : 'text-emerald-200/80') }} sm:text-[13px]">{{ \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($event->starts_at, 'F') }}</span>
                                    </div>
                                    {{-- Event details --}}
                                    <div class="flex flex-1 flex-col justify-center gap-2 p-4 sm:p-5">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-200/60">
                                                {{ $resolveEventTypeLabel($event->event_type) }}
                                            </span>
                                            @if($event->status instanceof \App\States\EventStatus\Pending)
                                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-200/60">
                                                    {{ __('Menunggu Kelulusan') }}
                                                </span>
                                            @endif
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
                                    {{-- Arrow indicator --}}
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
                                    let startDay = first.getDay(); // 0=Sun
                                    startDay = startDay === 0 ? 6 : startDay - 1; // Convert to Mon=0
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
                                                <span class="text-xs font-medium"
                                                    :class="cell.events?.length > 0 ? (cell.events.some(ev => ev.pending) ? 'font-bold text-amber-700' : (cell.events.some(ev => ev.is_remote) ? 'font-bold text-sky-700' : 'font-bold text-emerald-700')) : 'text-slate-400'"
                                                    x-text="cell.day"></span>
                                                <template x-if="cell.events?.length > 0">
                                                    <div class="mt-0.5 space-y-0.5">
                                                        <template x-for="ev in cell.events.slice(0, 2)" :key="ev.id">
                                                            <a :href="ev.url" class="block rounded px-1 py-0.5 text-[10px] font-medium leading-snug whitespace-normal break-words transition"
                                                               :class="ev.pending ? 'bg-amber-50 text-amber-700 hover:bg-amber-100' : (ev.is_remote ? 'bg-sky-50 text-sky-700 hover:bg-sky-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100')"
                                                               x-text="ev.title"></a>
                                                        </template>
                                                        <template x-if="cell.events?.length > 2">
                                                            <span class="block text-[9px] font-semibold"
                                                                  :class="cell.events.some(ev => ev.pending) ? 'text-amber-500' : (cell.events.some(ev => ev.is_remote) ? 'text-sky-500' : 'text-emerald-500')"
                                                                  x-text="'+' + (cell.events.length - 2) + ' ' + @js(__('lagi'))"></span>
                                                        </template>
                                                    </div>
                                                </template>
                                                <template x-if="cell.events?.length > 0">
                                                    <div class="absolute bottom-1 left-1/2 flex -translate-x-1/2 gap-0.5 sm:hidden">
                                                        <template x-for="i in Math.min(cell.events.length, 3)" :key="i">
                                                            <span class="h-1 w-1 rounded-full" :class="cell.events.some(ev => ev.pending) ? 'bg-amber-500' : (cell.events.some(ev => ev.is_remote) ? 'bg-sky-500' : 'bg-emerald-500')"></span>
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
                                $isPendingEvent = $event->status instanceof \App\States\EventStatus\Pending;
                            @endphp
                            <a href="{{ route('events.show', $event) }}" wire:navigate wire:key="past-{{ $event->id }}"
                               class="group relative flex overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-transparent transition-all duration-300 hover:-translate-y-0.5 hover:border-slate-300 hover:ring-slate-200 hover:shadow-xl hover:shadow-slate-500/[0.06]">
                                {{-- Date accent sidebar --}}
                                <div class="flex w-[4.5rem] shrink-0 flex-col items-center justify-center bg-gradient-to-b {{ $isPendingEvent ? 'from-amber-600 to-amber-800' : ($isRemoteEvent ? 'from-sky-600 to-sky-800' : 'from-slate-500 to-slate-700') }} p-2.5 text-white sm:w-24 sm:p-3">
                                    <span class="text-[10px] font-bold uppercase tracking-widest {{ $isPendingEvent ? 'text-amber-200/80' : ($isRemoteEvent ? 'text-sky-200/80' : 'text-slate-300/80') }} sm:text-[11px]">{{ \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($event->starts_at, 'l') }}</span>
                                    <span class="font-heading text-2xl font-black leading-none sm:text-4xl">{{ \App\Support\Timezone\UserDateTimeFormatter::format($event->starts_at, 'd') }}</span>
                                    <span class="mt-0.5 text-[11px] font-bold tracking-wide {{ $isPendingEvent ? 'text-amber-200/80' : ($isRemoteEvent ? 'text-sky-200/80' : 'text-slate-300/80') }} sm:text-[13px]">{{ \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($event->starts_at, 'F') }}</span>
                                </div>
                                {{-- Event details --}}
                                <div class="flex flex-1 flex-col justify-center gap-2 p-4 sm:p-5">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200/60">
                                            {{ $resolveEventTypeLabel($event->event_type) }}
                                        </span>
                                        @if($event->status instanceof \App\States\EventStatus\Pending)
                                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-200/60">
                                                {{ __('Menunggu Kelulusan') }}
                                            </span>
                                        @endif
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
                                {{-- Arrow indicator --}}
                                <div class="hidden items-center pr-5 sm:flex">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-400 transition-all duration-300 group-hover:bg-slate-200 group-hover:text-slate-600">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    {{-- Load more past --}}
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

            {{-- Cover image --}}
            @if($coverUrl)
                <div class="scroll-reveal reveal-scale overflow-hidden rounded-2xl shadow-lg shadow-slate-900/5 ring-1 ring-slate-200/60"
                     x-data x-intersect.once="$el.classList.add('revealed')">
                    <img src="{{ $coverUrl }}" alt="{{ $speaker->name }}" class="w-full object-cover" loading="lazy">
                </div>
            @endif

            {{-- ─── GALLERY ─── --}}
            @if($gallery->count() > 0)
                <section class="scroll-reveal reveal-up" x-data x-intersect.once="$el.classList.add('revealed')">
                    <div class="mb-5 flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-slate-600 to-slate-800 text-white shadow-lg shadow-slate-500/20">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H3.75a2.25 2.25 0 00-2.25 2.25v12a2.25 2.25 0 002.25 2.25zm15-14.25a1.125 1.125 0 11-2.25 0 1.125 1.125 0 012.25 0z"/></svg>
                        </div>
                        <div>
                            <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Galeri') }}</h2>
                            <div class="mt-0.5 h-0.5 w-12 rounded-full bg-gradient-to-r from-slate-400 to-transparent"></div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-3">
                        @foreach($gallery as $index => $image)
                            <div class="group relative aspect-[4/3] overflow-hidden rounded-xl bg-slate-100 ring-1 ring-slate-200/50 transition-all duration-300 hover:ring-emerald-200 hover:shadow-lg hover:shadow-emerald-500/10 {{ $index === 0 && $gallery->count() >= 3 ? 'col-span-2 row-span-2 aspect-square sm:aspect-[4/3]' : '' }}">
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

            {{-- Qualifications — Timeline style (hidden for now) --}}
            @if(false && $qualifications !== [])
                <section class="scroll-reveal reveal-up" x-data x-intersect.once="$el.classList.add('revealed')">
                    <div class="mb-5 flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-gold-500 to-gold-700 text-white shadow-lg shadow-gold-500/20">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.62 48.62 0 0112 20.904a48.62 48.62 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342"/></svg>
                        </div>
                        <div>
                            <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Kelayakan Akademik') }}</h2>
                            <div class="mt-0.5 h-0.5 w-10 rounded-full bg-gradient-to-r from-gold-500 to-transparent"></div>
                        </div>
                    </div>
                    <div class="relative space-y-0 pl-8">
                        <div class="absolute left-3 top-2 bottom-2 w-px bg-gradient-to-b from-gold-300 via-gold-200 to-transparent"></div>
                        @foreach($qualifications as $index => $qual)
                            @php
                                $degree = $qual['degree'] ?? null;
                                $field = $qual['field'] ?? null;
                                $institution = $qual['institution'] ?? null;
                                $year = $qual['year'] ?? null;
                            @endphp
                            <div class="group relative pb-6 last:pb-0">
                                <div class="absolute -left-5 top-1.5 flex h-4 w-4 items-center justify-center">
                                    <div class="h-2.5 w-2.5 rounded-full border-2 border-gold-400 bg-white transition-all duration-300 group-hover:scale-125 group-hover:border-gold-500 group-hover:bg-gold-50"></div>
                                </div>
                                <div class="rounded-xl border border-slate-200/70 bg-white p-4 shadow-sm transition-all duration-300 hover:border-gold-200/80 hover:shadow-md hover:shadow-gold-500/[0.04]">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-bold text-slate-900">
                                                {{ $degree }}
                                                @if($field)
                                                    <span class="font-normal text-slate-500">{{ __('dalam') }} {{ $field }}</span>
                                                @endif
                                            </p>
                                            @if($institution)
                                                <p class="mt-1 flex items-center gap-1.5 text-xs text-slate-500">
                                                    <svg class="h-3 w-3 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6M4.5 9.75v10.5h15V9.75"/></svg>
                                                    {{ $institution }}
                                                </p>
                                            @endif
                                        </div>
                                        @if($year)
                                            <span class="shrink-0 rounded-lg bg-gold-50 px-2.5 py-1 text-xs font-bold text-gold-700 ring-1 ring-gold-200/60">{{ $year }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- ─── BIODATA ─── --}}
            @if($isBioFilled)
                <section class="scroll-reveal reveal-up" x-data x-intersect.once="$el.classList.add('revealed')">
                    <div class="mb-5 flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-white shadow-lg shadow-emerald-500/25">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                        </div>
                        <div>
                            <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Biodata') }}</h2>
                            <div class="mt-0.5 h-0.5 w-12 rounded-full bg-gradient-to-r from-emerald-500 to-transparent"></div>
                        </div>
                    </div>
                    <div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-100/50">
                        {{-- Decorative left accent --}}
                        <div class="absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-emerald-400 via-emerald-500 to-emerald-300"></div>
                        <div class="p-6 pl-7 md:p-8 md:pl-9">
                            <div class="prose prose-slate prose-sm max-w-none prose-headings:font-heading prose-headings:tracking-tight prose-p:leading-relaxed prose-a:text-emerald-600 prose-a:no-underline hover:prose-a:underline prose-strong:text-slate-800">
                                {!! $bioHtml !!}
                            </div>
                        </div>
                    </div>
                </section>
            @endif

            </div>

            {{-- RIGHT COLUMN — Sidebar --}}
            @if($hasSocialLinks || $showJoinCta || $hasInspiration)
                <div class="speaker-sidebar-column order-2 space-y-6 {{ $useDesktopSidebar ? 'lg:sticky lg:top-6 lg:self-start' : '' }}">

                {{-- ─── ISLAMIC INSPIRATION ─── --}}
                <x-sidebar-inspiration />
                {{-- ─── SOCIAL MEDIA ─── --}}
                @if($hasSocialLinks)
                    <div class="scroll-reveal reveal-right rounded-2xl border border-slate-200/70 bg-white shadow-sm" x-intersect.once="$el.classList.add('revealed')">
                        <div class="border-b border-slate-100 px-5 py-4">
                            <h3 class="flex items-center gap-2 text-sm font-bold text-slate-900">
                                <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 119 0v3.75m-9 0h9m-9 0l-1.5 9.75h12L22.5 10.5m-9 0V4.875a2.625 2.625 0 00-5.25 0V10.5"/></svg>
                                {{ __('Media Sosial') }}
                            </h3>
                        </div>
                        <div class="p-5">
                            <div class="flex flex-wrap items-center gap-3">
                            @if($socialLinks->has('website'))
                                <a href="{{ $socialLinks->get('website') }}" target="_blank" rel="noopener" class="group inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-slate-50/50 text-slate-500 transition-all duration-200 hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-600 hover:shadow-md hover:shadow-emerald-500/10 hover:-translate-y-0.5" title="{{ __('Laman Web') }}">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/></svg>
                                </a>
                            @endif
                            @if($socialLinks->has('facebook'))
                                <a href="{{ $socialLinks->get('facebook') }}" target="_blank" rel="noopener" class="group inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-slate-50/50 text-slate-500 transition-all duration-200 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-600 hover:shadow-md hover:shadow-blue-500/10 hover:-translate-y-0.5" title="{{ __('Facebook') }}">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                </a>
                            @endif
                            @if($socialLinks->has('instagram'))
                                <a href="{{ $socialLinks->get('instagram') }}" target="_blank" rel="noopener" class="group inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-slate-50/50 text-slate-500 transition-all duration-200 hover:border-pink-200 hover:bg-pink-50 hover:text-pink-600 hover:shadow-md hover:shadow-pink-500/10 hover:-translate-y-0.5" title="{{ __('Instagram') }}">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="5"/><circle cx="17.5" cy="6.5" r="1.5" fill="currentColor" stroke="none"/></svg>
                                </a>
                            @endif
                            @if($socialLinks->has('youtube'))
                                <a href="{{ $socialLinks->get('youtube') }}" target="_blank" rel="noopener" class="group inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-slate-50/50 text-slate-500 transition-all duration-200 hover:border-red-200 hover:bg-red-50 hover:text-red-600 hover:shadow-md hover:shadow-red-500/10 hover:-translate-y-0.5" title="{{ __('YouTube') }}">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/></svg>
                                </a>
                            @endif
                            @if($socialLinks->has('twitter') || $socialLinks->has('x'))
                                <a href="{{ $socialLinks->get('twitter') ?? $socialLinks->get('x') }}" target="_blank" rel="noopener" class="group inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-slate-50/50 text-slate-500 transition-all duration-200 hover:border-slate-300 hover:bg-slate-100 hover:text-slate-900 hover:shadow-md hover:shadow-slate-500/10 hover:-translate-y-0.5" title="{{ __('X') }}">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                                </a>
                            @endif
                            @if($socialLinks->has('tiktok'))
                                <a href="{{ $socialLinks->get('tiktok') }}" target="_blank" rel="noopener" class="group inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-slate-50/50 text-slate-500 transition-all duration-200 hover:border-slate-300 hover:bg-slate-100 hover:text-slate-900 hover:shadow-md hover:shadow-slate-500/10 hover:-translate-y-0.5" title="{{ __('TikTok') }}">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1v-3.5a6.37 6.37 0 00-.79-.05A6.34 6.34 0 003.15 15.2a6.34 6.34 0 0010.86 4.48V13a8.28 8.28 0 005.58 2.15V11.7a4.84 4.84 0 01-3.77-1.78v-.01l.01-.01V6.69h3.76z"/></svg>
                                </a>
                            @endif
                            @if($socialLinks->has('telegram'))
                                <a href="{{ $socialLinks->get('telegram') }}" target="_blank" rel="noopener" class="group inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-slate-50/50 text-slate-500 transition-all duration-200 hover:border-sky-200 hover:bg-sky-50 hover:text-sky-600 hover:shadow-md hover:shadow-sky-500/10 hover:-translate-y-0.5" title="{{ __('Telegram') }}">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 000 12a12 12 0 0012 12 12 12 0 0012-12A12 12 0 0012 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 01.171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                                </a>
                            @endif
                            </div>
                        </div>
                    </div>
                @endif

                {{-- ─── JOIN MAJLISILMU CTA ─── --}}
                <x-join-majlisilmu-cta />

                </div>
            @endif
        </div>
    </div>
</div>
