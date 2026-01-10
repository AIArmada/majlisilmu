<div>
    <section class="px-6 sm:px-10 lg:px-16 pb-16">
        <div class="flex flex-col gap-8">
            <div class="flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-ink-soft">
                <a href="{{ route('events.index') }}" class="hover:text-ink">{{ __('Series') }}</a>
                <span>/</span>
                <span>{{ $series->title }}</span>
            </div>
            <div class="grid gap-8 lg:grid-cols-[1.5fr_0.8fr]">
                <div class="flex flex-col gap-6">
                    <div class="flex flex-col gap-4">
                        <h1 class="font-display text-4xl sm:text-5xl">{{ $series->title }}</h1>
                        <p class="text-ink-soft">{{ $series->description ?? __('A curated learning series for the community.') }}</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-3 rounded-3xl border border-ink/10 bg-white/80 p-5">
                            <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Host') }}</span>
                            <div class="flex flex-col gap-2 text-sm text-ink-soft">
                                @if ($series->institution)
                                    <a href="{{ route('institutions.show', $series->institution->slug) }}" class="text-ink hover:text-ink-soft">{{ $series->institution->name }}</a>
                                @endif
                                @if ($series->venue)
                                    <span>{{ $series->venue->name }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex flex-col gap-3 rounded-3xl border border-ink/10 bg-white/80 p-5">
                            <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Defaults') }}</span>
                            <div class="flex flex-col gap-2 text-sm text-ink-soft">
                                <span>{{ __('Language') }}: {{ $series->default_language ?? __('Varies') }}</span>
                                <span>{{ __('Audience') }}: {{ $series->default_audience ?? __('Open to all') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <aside class="flex flex-col gap-6">
                    <div class="flex flex-col gap-4 rounded-3xl border border-ink/10 bg-white/80 p-6 shadow-xl shadow-ink/10">
                        <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Visibility') }}</span>
                        <span class="text-lg font-medium text-ink">{{ __($series->visibility) }}</span>
                        <a href="{{ route('events.index', ['series_id' => $series->id]) }}" class="inline-flex items-center justify-center rounded-full bg-ink px-4 py-3 text-sm font-medium text-parchment shadow-lg shadow-ink/20">{{ __('Browse series events') }}</a>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    <section class="px-6 sm:px-10 lg:px-16 pb-16">
        <div class="flex flex-col gap-6">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-3xl">{{ __('Series events') }}</h2>
                <span class="text-sm text-ink-soft">{{ __(':count events', ['count' => $this->events->count()]) }}</span>
            </div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($this->events as $event)
                    <a href="{{ route('events.show', $event->slug) }}" wire:key="series-event-{{ $event->id }}" class="flex flex-col gap-3 rounded-3xl border border-ink/10 bg-white/80 p-4 transition hover:-translate-y-1">
                        <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ $event->starts_at?->translatedFormat('D, d M') }}</span>
                        <span class="font-display text-xl">{{ $event->title }}</span>
                        <div class="flex flex-wrap gap-2 text-xs text-ink-soft">
                            @foreach ($event->speakers->take(2) as $speaker)
                                <span class="rounded-full border border-ink/10 bg-white px-2 py-1" wire:key="series-event-speaker-{{ $event->id }}-{{ $speaker->id }}">{{ $speaker->name }}</span>
                            @endforeach
                        </div>
                    </a>
                @empty
                    <div class="rounded-3xl border border-dashed border-ink/15 bg-white/70 p-6 text-sm text-ink-soft">{{ __('No events added to this series yet.') }}</div>
                @endforelse
            </div>
        </div>
    </section>
</div>
