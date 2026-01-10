<div>
    <section class="px-6 sm:px-10 lg:px-16 pb-16">
        <div class="grid gap-10 lg:grid-cols-[1.2fr_0.8fr] items-center">
            <div class="flex flex-col gap-6">
                <span class="inline-flex w-fit items-center gap-2 rounded-full border border-ink/10 bg-white/70 px-3 py-1 text-xs uppercase tracking-[0.24em] text-ink-soft animate-rise">{{ __('Community verified') }}</span>
                <div class="flex flex-col gap-4">
                    <h1 class="font-display text-4xl sm:text-5xl lg:text-6xl leading-tight animate-rise-delay">{{ __('Find gatherings of knowledge across Malaysia.') }}</h1>
                    <p class="text-lg text-ink-soft max-w-2xl animate-rise-delay-lg">{{ __('Majlis Ilmu maps trusted talks, classes, and study circles so the community can discover and support the spaces that nurture learning.') }}</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('events.index') }}" class="inline-flex items-center gap-2 rounded-full bg-ink px-5 py-2 text-sm font-medium text-parchment shadow-lg shadow-ink/20 transition hover:translate-y-[-1px]">{{ __('Browse events') }}</a>
                    <a href="{{ route('institutions.index') }}" class="inline-flex items-center gap-2 rounded-full border border-ink/15 px-5 py-2 text-sm font-medium transition hover:border-ink/30 hover:bg-ink/5">{{ __('See institutions') }}</a>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="flex flex-col gap-2 rounded-2xl border border-ink/10 bg-white/70 p-4 shadow-lg shadow-ink/5">
                        <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Events') }}</span>
                        <span class="font-display text-2xl">{{ number_format($this->stats['events']) }}</span>
                    </div>
                    <div class="flex flex-col gap-2 rounded-2xl border border-ink/10 bg-white/70 p-4 shadow-lg shadow-ink/5">
                        <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Institutions') }}</span>
                        <span class="font-display text-2xl">{{ number_format($this->stats['institutions']) }}</span>
                    </div>
                    <div class="flex flex-col gap-2 rounded-2xl border border-ink/10 bg-white/70 p-4 shadow-lg shadow-ink/5">
                        <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Speakers') }}</span>
                        <span class="font-display text-2xl">{{ number_format($this->stats['speakers']) }}</span>
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-6">
                <form action="{{ route('events.index') }}" method="GET" class="flex flex-col gap-5 rounded-3xl border border-ink/10 bg-white/80 p-6 shadow-2xl shadow-ink/10">
                    <div class="flex flex-col gap-2">
                        <label class="text-xs uppercase tracking-[0.2em] text-ink-soft" for="search">{{ __('Search events') }}</label>
                        <input id="search" name="q" placeholder="{{ __('Topic, speaker, or venue') }}" class="w-full rounded-2xl border border-ink/10 bg-white px-4 py-3 text-sm focus:border-ink/30 focus:outline-none" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-2">
                            <label class="text-xs uppercase tracking-[0.2em] text-ink-soft" for="state">{{ __('State') }}</label>
                            <select id="state" name="state_id" class="w-full rounded-2xl border border-ink/10 bg-white px-4 py-3 text-sm focus:border-ink/30 focus:outline-none">
                                <option value="">{{ __('All states') }}</option>
                                @foreach ($this->states as $state)
                                    <option value="{{ $state->id }}" wire:key="home-state-{{ $state->id }}">{{ $state->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-xs uppercase tracking-[0.2em] text-ink-soft" for="timeframe">{{ __('When') }}</label>
                            <select id="timeframe" name="timeframe" class="w-full rounded-2xl border border-ink/10 bg-white px-4 py-3 text-sm focus:border-ink/30 focus:outline-none">
                                <option value="upcoming">{{ __('Upcoming') }}</option>
                                <option value="week">{{ __('Next 7 days') }}</option>
                                <option value="month">{{ __('Next 30 days') }}</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-ink px-4 py-3 text-sm font-medium text-parchment shadow-lg shadow-ink/20 transition hover:translate-y-[-1px]">{{ __('Find gatherings') }}</button>
                </form>

                @if ($this->featuredEvents->isNotEmpty())
                    @php($highlight = $this->featuredEvents->first())
                    <div class="flex flex-col gap-4 rounded-3xl border border-ink/10 bg-parchment p-6 shadow-xl shadow-ink/10">
                        <div class="flex items-center justify-between text-xs uppercase tracking-[0.2em] text-ink-soft">
                            <span>{{ __('Highlight') }}</span>
                            <span>{{ $highlight->starts_at?->translatedFormat('D, d M') }}</span>
                        </div>
                        <div class="flex flex-col gap-2">
                            <h3 class="font-display text-2xl">{{ $highlight->title }}</h3>
                            <span class="text-sm text-ink-soft">{{ $highlight->institution?->name ?? __('Community event') }}</span>
                        </div>
                        <a href="{{ route('events.show', $highlight->slug) }}" class="inline-flex items-center gap-2 text-sm font-medium text-ink hover:text-ink-soft">{{ __('View details') }}</a>
                    </div>
                @endif
            </div>
        </div>
    </section>

    <section class="px-6 sm:px-10 lg:px-16 pb-16">
        <div class="flex flex-col gap-6">
            <div class="flex flex-col gap-2">
                <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Upcoming') }}</span>
                <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                    <div class="flex flex-col gap-2">
                        <h2 class="font-display text-3xl">{{ __('Upcoming gatherings') }}</h2>
                        <p class="text-ink-soft">{{ __('Freshly reviewed and ready to join.') }}</p>
                    </div>
                    <a href="{{ route('events.index') }}" class="text-sm font-medium text-ink hover:text-ink-soft">{{ __('View all events') }}</a>
                </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 stagger-children">
                @forelse ($this->featuredEvents as $event)
                    <a href="{{ route('events.show', $event->slug) }}" wire:key="home-event-{{ $event->id }}" class="group flex h-full flex-col gap-4 rounded-3xl border border-ink/10 bg-white/80 p-5 shadow-lg shadow-ink/5 transition hover:-translate-y-1">
                        <div class="flex items-center justify-between text-xs uppercase tracking-[0.2em] text-ink-soft">
                            <span>{{ $event->starts_at?->translatedFormat('D, d M') }}</span>
                            <span class="rounded-full bg-amber/20 px-2 py-1">{{ $event->language ?? __('All') }}</span>
                        </div>
                        <div class="flex flex-col gap-2">
                            <h3 class="font-display text-xl text-ink group-hover:text-ink-soft">{{ $event->title }}</h3>
                            <span class="text-sm text-ink-soft">{{ $event->institution?->name ?? __('Community event') }}</span>
                            <span class="text-sm text-ink-soft">{{ $event->venue?->name ?? $event->district?->name ?? __('Malaysia') }}</span>
                        </div>
                        <div class="flex flex-wrap gap-2 text-xs text-ink-soft">
                            @foreach ($event->speakers->take(2) as $speaker)
                                <span class="rounded-full border border-ink/10 bg-white px-2 py-1" wire:key="home-event-speaker-{{ $event->id }}-{{ $speaker->id }}">{{ $speaker->name }}</span>
                            @endforeach
                            @foreach ($event->topics->take(2) as $topic)
                                <span class="rounded-full border border-ink/10 bg-white px-2 py-1" wire:key="home-event-topic-{{ $event->id }}-{{ $topic->id }}">{{ $topic->name }}</span>
                            @endforeach
                        </div>
                    </a>
                @empty
                    <div class="rounded-3xl border border-dashed border-ink/15 bg-white/70 p-6 text-sm text-ink-soft">{{ __('No upcoming events yet. Check back soon.') }}</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="px-6 sm:px-10 lg:px-16 pb-16">
        <div class="grid gap-10 lg:grid-cols-[1fr_1fr]">
            <div class="flex flex-col gap-6">
                <div class="flex flex-col gap-2">
                    <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Institutions') }}</span>
                    <h2 class="font-display text-3xl">{{ __('Trusted institutions') }}</h2>
                    <p class="text-ink-soft">{{ __('Community spaces with growing trust scores and verified records.') }}</p>
                </div>
                <div class="grid gap-4">
                    @foreach ($this->trustedInstitutions as $institution)
                        <a href="{{ route('institutions.show', $institution->slug) }}" wire:key="home-institution-{{ $institution->id }}" class="group flex items-center justify-between rounded-3xl border border-ink/10 bg-white/80 p-4 transition hover:-translate-y-1">
                            <div class="flex flex-col gap-1">
                                <span class="font-medium text-ink group-hover:text-ink-soft">{{ $institution->name }}</span>
                                <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __($institution->type) }}</span>
                            </div>
                            <div class="flex flex-col items-end">
                                <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Trust') }}</span>
                                <span class="font-display text-lg">{{ $institution->trust_score ?? 0 }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
            <div class="flex flex-col gap-6">
                <div class="flex flex-col gap-2">
                    <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Speakers') }}</span>
                    <h2 class="font-display text-3xl">{{ __('Voices to follow') }}</h2>
                    <p class="text-ink-soft">{{ __('Discover scholars and teachers active across the country.') }}</p>
                </div>
                <div class="grid gap-4">
                    @foreach ($this->featuredSpeakers as $speaker)
                        <a href="{{ route('speakers.show', $speaker->slug) }}" wire:key="home-speaker-{{ $speaker->id }}" class="group flex items-center gap-4 rounded-3xl border border-ink/10 bg-white/80 p-4 transition hover:-translate-y-1">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-ink text-parchment text-sm font-semibold">{{ strtoupper(substr($speaker->name, 0, 1)) }}</span>
                            <div class="flex flex-col gap-1">
                                <span class="font-medium text-ink group-hover:text-ink-soft">{{ $speaker->name }}</span>
                                <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Trust') }} {{ $speaker->trust_score ?? 0 }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="px-6 sm:px-10 lg:px-16 pb-16">
        <div class="flex flex-col gap-6">
            <div class="flex flex-col gap-2">
                <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Topics') }}</span>
                <h2 class="font-display text-3xl">{{ __('Explore by topic') }}</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach ($this->topicHighlights as $topic)
                    <a href="{{ route('events.index', ['topic_id' => $topic->id]) }}" wire:key="home-topic-{{ $topic->id }}" class="rounded-full border border-ink/10 bg-white/80 px-3 py-2 text-sm text-ink-soft transition hover:border-ink/30 hover:text-ink">{{ $topic->name }}</a>
                @endforeach
            </div>
        </div>
    </section>

    <section id="submit" class="px-6 sm:px-10 lg:px-16 pb-20">
        <div class="grid gap-8 rounded-[2.5rem] border border-ink/10 bg-ink px-8 py-10 text-parchment lg:grid-cols-[1.2fr_0.8fr]">
            <div class="flex flex-col gap-4">
                <span class="text-xs uppercase tracking-[0.2em] text-parchment/70">{{ __('Submit an event') }}</span>
                <h2 class="font-display text-3xl">{{ __('Share upcoming classes with the community.') }}</h2>
                <p class="text-parchment/70">{{ __('Help others find knowledge circles by submitting a verified event. Our team will review and publish quickly.') }}</p>
            </div>
            <div class="flex flex-col gap-3">
                <a href="mailto:hello@majlisilmu.my" class="inline-flex items-center justify-center rounded-full bg-parchment px-5 py-3 text-sm font-medium text-ink shadow-lg shadow-black/20">{{ __('Email the team') }}</a>
                <span class="text-xs text-parchment/60">{{ __('Live submission portal is launching soon.') }}</span>
            </div>
        </div>
    </section>
</div>
