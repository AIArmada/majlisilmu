<div>
    <section class="px-6 sm:px-10 lg:px-16 pb-16">
        <div class="flex flex-col gap-8">
            <div class="flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-ink-soft">
                <a href="{{ route('events.index') }}" class="hover:text-ink">{{ __('Events') }}</a>
                <span>/</span>
                <span>{{ $event->title }}</span>
            </div>
            <div class="grid gap-8 lg:grid-cols-[1.5fr_0.8fr]">
                <div class="flex flex-col gap-6">
                    <div class="flex flex-col gap-4">
                        <h1 class="font-display text-4xl sm:text-5xl">{{ $event->title }}</h1>
                        <div class="flex flex-wrap gap-2 text-xs text-ink-soft">
                            @foreach ($event->topics as $topic)
                                <span class="rounded-full border border-ink/10 bg-white px-3 py-1" wire:key="event-topic-{{ $event->id }}-{{ $topic->id }}">{{ $topic->name }}</span>
                            @endforeach
                        </div>
                        <p class="text-ink-soft">{{ $event->description ?? __('Event details will be shared soon.') }}</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-3 rounded-3xl border border-ink/10 bg-white/80 p-5">
                            <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Speakers') }}</span>
                            <div class="flex flex-col gap-3">
                                @forelse ($event->speakers as $speaker)
                                    <a href="{{ route('speakers.show', $speaker->slug) }}" wire:key="event-speaker-{{ $event->id }}-{{ $speaker->id }}" class="flex items-center gap-3 rounded-2xl border border-ink/10 bg-white px-3 py-2 text-sm transition hover:-translate-y-0.5">
                                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-ink text-parchment text-xs font-semibold">{{ strtoupper(substr($speaker->name, 0, 1)) }}</span>
                                        <span>{{ $speaker->name }}</span>
                                    </a>
                                @empty
                                    <span class="text-sm text-ink-soft">{{ __('Speaker details to be announced.') }}</span>
                                @endforelse
                            </div>
                        </div>
                        <div class="flex flex-col gap-3 rounded-3xl border border-ink/10 bg-white/80 p-5">
                            <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Host') }}</span>
                            <div class="flex flex-col gap-3">
                                @if ($event->institution)
                                    <a href="{{ route('institutions.show', $event->institution->slug) }}" class="flex flex-col gap-1 rounded-2xl border border-ink/10 bg-white px-3 py-2 text-sm transition hover:-translate-y-0.5">
                                        <span class="font-medium text-ink">{{ $event->institution->name }}</span>
                                        <span class="text-xs text-ink-soft">{{ __($event->institution->type) }}</span>
                                    </a>
                                @endif
                                @if ($event->series)
                                    <a href="{{ route('series.show', $event->series->slug) }}" class="flex flex-col gap-1 rounded-2xl border border-ink/10 bg-white px-3 py-2 text-sm transition hover:-translate-y-0.5">
                                        <span class="font-medium text-ink">{{ $event->series->title }}</span>
                                        <span class="text-xs text-ink-soft">{{ __('Series') }}</span>
                                    </a>
                                @endif
                                @if (! $event->institution && ! $event->series)
                                    <span class="text-sm text-ink-soft">{{ __('Community-hosted event.') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @if ($event->mediaLinks->isNotEmpty())
                        <div class="flex flex-col gap-3 rounded-3xl border border-ink/10 bg-white/80 p-5">
                            <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Media') }}</span>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($event->mediaLinks as $link)
                                    <a href="{{ $link->url }}" target="_blank" rel="noopener" wire:key="event-media-{{ $link->id }}" class="inline-flex items-center gap-2 rounded-full border border-ink/10 bg-white px-3 py-2 text-xs uppercase tracking-[0.2em] text-ink-soft transition hover:border-ink/30 hover:text-ink">{{ $link->type }} {{ $link->provider }}</a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
                <aside class="flex flex-col gap-6">
                    <div class="flex flex-col gap-4 rounded-3xl border border-ink/10 bg-white/80 p-6 shadow-xl shadow-ink/10">
                        <div class="flex items-center justify-between">
                            <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Date') }}</span>
                            <span class="text-sm font-medium">{{ $event->starts_at?->translatedFormat('D, d M Y') }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Time') }}</span>
                            <span class="text-sm font-medium">{{ $event->starts_at?->translatedFormat('g:i A') }} {{ $event->timezone }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Location') }}</span>
                            <span class="text-sm font-medium">{{ $event->venue?->name ?? $event->district?->name ?? $event->state?->name ?? __('Malaysia') }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Audience') }}</span>
                            <span class="text-sm font-medium">{{ $event->audience ?? __('Open to all') }}</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if ($event->registration_required)
                                <span class="rounded-full bg-ink/10 px-3 py-2 text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Registration required') }}</span>
                            @endif
                            @if ($event->livestream_url)
                                <span class="rounded-full bg-sea/20 px-3 py-2 text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Live stream') }}</span>
                            @endif
                        </div>
                        @if ($event->registration_required)
                            <button type="button" class="inline-flex items-center justify-center rounded-full bg-ink px-4 py-3 text-sm font-medium text-parchment shadow-lg shadow-ink/20" disabled>{{ __('Registration opens soon') }}</button>
                        @endif
                    </div>
                    @if ($event->donationAccount)
                        <div class="flex flex-col gap-3 rounded-3xl border border-ink/10 bg-parchment p-5">
                            <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Donation') }}</span>
                            <div class="flex flex-col gap-2 text-sm text-ink-soft">
                                <span class="font-medium text-ink">{{ $event->donationAccount->label ?? __('Support this program') }}</span>
                                <span>{{ $event->donationAccount->bank_name }}</span>
                                <span>{{ $event->donationAccount->account_number }}</span>
                                <span>{{ $event->donationAccount->recipient_name }}</span>
                                @if ($event->donationAccount->duitnow_id)
                                    <span>{{ __('DuitNow: :id', ['id' => $event->donationAccount->duitnow_id]) }}</span>
                                @endif
                            </div>
                        </div>
                    @endif
                </aside>
            </div>
        </div>
    </section>

    <section class="px-6 sm:px-10 lg:px-16 pb-16">
        <div class="flex flex-col gap-6">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-3xl">{{ __('Related events') }}</h2>
                <a href="{{ route('events.index') }}" class="text-sm font-medium text-ink hover:text-ink-soft">{{ __('Browse all') }}</a>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
                @forelse ($this->relatedEvents as $related)
                    <a href="{{ route('events.show', $related->slug) }}" wire:key="related-event-{{ $related->id }}" class="flex flex-col gap-3 rounded-3xl border border-ink/10 bg-white/80 p-4 transition hover:-translate-y-1">
                        <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ $related->starts_at?->translatedFormat('D, d M') }}</span>
                        <span class="font-display text-xl">{{ $related->title }}</span>
                        <span class="text-sm text-ink-soft">{{ $related->institution?->name ?? __('Community event') }}</span>
                    </a>
                @empty
                    <div class="rounded-3xl border border-dashed border-ink/15 bg-white/70 p-6 text-sm text-ink-soft">{{ __('No related events yet.') }}</div>
                @endforelse
            </div>
        </div>
    </section>
</div>
