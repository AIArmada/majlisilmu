<div>
    <section class="px-6 sm:px-10 lg:px-16 pb-16">
        <div class="flex flex-col gap-8">
            <div class="flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-ink-soft">
                <a href="{{ route('speakers.index') }}" class="hover:text-ink">{{ __('Speakers') }}</a>
                <span>/</span>
                <span>{{ $speaker->name }}</span>
            </div>
            <div class="grid gap-8 lg:grid-cols-[1.5fr_0.8fr]">
                <div class="flex flex-col gap-6">
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-4">
                            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-ink text-parchment text-xl font-semibold">{{ strtoupper(substr($speaker->name, 0, 1)) }}</span>
                            <div class="flex flex-col gap-1">
                                <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __($speaker->verification_status) }}</span>
                                <h1 class="font-display text-4xl sm:text-5xl">{{ $speaker->name }}</h1>
                            </div>
                        </div>
                        <p class="text-ink-soft">{{ $speaker->bio ?? __('Dedicated to sharing knowledge and nurturing the community.') }}</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-3 rounded-3xl border border-ink/10 bg-white/80 p-5">
                            <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Contact') }}</span>
                            <div class="flex flex-col gap-2 text-sm text-ink-soft">
                                @if ($speaker->phone)
                                    <span>{{ $speaker->phone }}</span>
                                @endif
                                @if ($speaker->email)
                                    <span>{{ $speaker->email }}</span>
                                @endif
                                @if (! $speaker->phone && ! $speaker->email)
                                    <span>{{ __('No direct contact details shared.') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex flex-col gap-3 rounded-3xl border border-ink/10 bg-white/80 p-5">
                            <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Online') }}</span>
                            <div class="flex flex-col gap-2 text-sm text-ink-soft">
                                @if ($speaker->website_url)
                                    <a href="{{ $speaker->website_url }}" target="_blank" rel="noopener" class="text-ink hover:text-ink-soft">{{ __('Website') }}</a>
                                @endif
                                @if ($speaker->youtube_url)
                                    <a href="{{ $speaker->youtube_url }}" target="_blank" rel="noopener" class="text-ink hover:text-ink-soft">{{ __('YouTube') }}</a>
                                @endif
                                @if ($speaker->facebook_url)
                                    <a href="{{ $speaker->facebook_url }}" target="_blank" rel="noopener" class="text-ink hover:text-ink-soft">{{ __('Facebook') }}</a>
                                @endif
                                @if ($speaker->instagram_url)
                                    <a href="{{ $speaker->instagram_url }}" target="_blank" rel="noopener" class="text-ink hover:text-ink-soft">{{ __('Instagram') }}</a>
                                @endif
                                @if (! $speaker->website_url && ! $speaker->youtube_url && ! $speaker->facebook_url && ! $speaker->instagram_url)
                                    <span>{{ __('No links shared yet.') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <aside class="flex flex-col gap-6">
                    <div class="flex flex-col gap-4 rounded-3xl border border-ink/10 bg-white/80 p-6 shadow-xl shadow-ink/10">
                        <div class="flex items-center justify-between">
                            <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Trust score') }}</span>
                            <span class="font-display text-2xl">{{ $speaker->trust_score ?? 0 }}</span>
                        </div>
                        <div class="text-sm text-ink-soft">{{ __('Community confidence based on reviews, accuracy, and engagement.') }}</div>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    <section class="px-6 sm:px-10 lg:px-16 pb-16">
        <div class="flex flex-col gap-6">
            <div class="flex items-center justify-between">
                <div class="flex flex-col gap-2">
                    <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Upcoming') }}</span>
                    <h2 class="font-display text-3xl">{{ __('Upcoming events') }}</h2>
                </div>
                <a href="{{ route('events.index', ['speaker_id' => $speaker->id]) }}" class="text-sm font-medium text-ink hover:text-ink-soft">{{ __('Browse all') }}</a>
            </div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($this->upcomingEvents as $event)
                    <a href="{{ route('events.show', $event->slug) }}" wire:key="speaker-event-{{ $event->id }}" class="flex flex-col gap-3 rounded-3xl border border-ink/10 bg-white/80 p-4 transition hover:-translate-y-1">
                        <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ $event->starts_at?->translatedFormat('D, d M') }}</span>
                        <span class="font-display text-xl">{{ $event->title }}</span>
                        <span class="text-sm text-ink-soft">{{ $event->institution?->name ?? __('Community event') }}</span>
                    </a>
                @empty
                    <div class="rounded-3xl border border-dashed border-ink/15 bg-white/70 p-6 text-sm text-ink-soft">{{ __('No upcoming events listed yet.') }}</div>
                @endforelse
            </div>
        </div>
    </section>
</div>
