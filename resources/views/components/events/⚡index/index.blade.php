<section class="px-6 sm:px-10 lg:px-16 pb-16">
    <div class="flex flex-col gap-10">
        <div class="flex flex-col gap-3 animate-rise">
            <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Events') }}</span>
            <h1 class="font-display text-4xl sm:text-5xl">{{ __('Find gatherings near you') }}</h1>
            <p class="text-ink-soft max-w-2xl">{{ __('Search across states, speakers, and topics. Every listing is reviewed for trust and accuracy.') }}</p>
        </div>
        <div class="grid gap-8 lg:grid-cols-[280px_1fr]">
            <aside class="flex flex-col gap-6 rounded-3xl border border-ink/10 bg-white/80 p-6 shadow-xl shadow-ink/5">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium">{{ __('Filters') }}</span>
                    <button type="button" class="text-xs uppercase tracking-[0.2em] text-ink-soft" wire:click="resetFilters">{{ __('Reset') }}</button>
                </div>
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-xs uppercase tracking-[0.2em] text-ink-soft" for="event-search">{{ __('Search') }}</label>
                        <input id="event-search" type="text" wire:model.debounce.400ms="search" placeholder="{{ __('Title, topic, or speaker') }}" class="w-full rounded-2xl border border-ink/10 bg-white px-3 py-2 text-sm focus:border-ink/30 focus:outline-none" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-xs uppercase tracking-[0.2em] text-ink-soft" for="event-state">{{ __('State') }}</label>
                        <select id="event-state" wire:model="stateId" class="w-full rounded-2xl border border-ink/10 bg-white px-3 py-2 text-sm focus:border-ink/30 focus:outline-none">
                            <option value="">{{ __('All states') }}</option>
                            @foreach ($this->states as $state)
                                <option value="{{ $state->id }}" wire:key="events-state-{{ $state->id }}">{{ $state->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-xs uppercase tracking-[0.2em] text-ink-soft" for="event-district">{{ __('District') }}</label>
                        <select id="event-district" wire:model="districtId" class="w-full rounded-2xl border border-ink/10 bg-white px-3 py-2 text-sm focus:border-ink/30 focus:outline-none" @disabled($this->stateId === null)>
                            <option value="">{{ __('All districts') }}</option>
                            @foreach ($this->districts as $district)
                                <option value="{{ $district->id }}" wire:key="events-district-{{ $district->id }}">{{ $district->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-xs uppercase tracking-[0.2em] text-ink-soft" for="event-topic">{{ __('Topic') }}</label>
                        <select id="event-topic" wire:model="topicId" class="w-full rounded-2xl border border-ink/10 bg-white px-3 py-2 text-sm focus:border-ink/30 focus:outline-none">
                            <option value="">{{ __('All topics') }}</option>
                            @foreach ($this->topics as $topic)
                                <option value="{{ $topic->id }}" wire:key="events-topic-{{ $topic->id }}">{{ $topic->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-xs uppercase tracking-[0.2em] text-ink-soft" for="event-speaker">{{ __('Speaker') }}</label>
                        <select id="event-speaker" wire:model="speakerId" class="w-full rounded-2xl border border-ink/10 bg-white px-3 py-2 text-sm focus:border-ink/30 focus:outline-none">
                            <option value="">{{ __('All speakers') }}</option>
                            @foreach ($this->speakers as $speaker)
                                <option value="{{ $speaker->id }}" wire:key="events-speaker-{{ $speaker->id }}">{{ $speaker->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-xs uppercase tracking-[0.2em] text-ink-soft" for="event-language">{{ __('Language') }}</label>
                        <input id="event-language" type="text" wire:model.debounce.400ms="language" placeholder="{{ __('Malay, English, Mandarin, Tamil, Javanese') }}" class="w-full rounded-2xl border border-ink/10 bg-white px-3 py-2 text-sm focus:border-ink/30 focus:outline-none" />
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="flex flex-col gap-2">
                            <label class="text-xs uppercase tracking-[0.2em] text-ink-soft" for="event-genre">{{ __('Genre') }}</label>
                            <input id="event-genre" type="text" wire:model.debounce.400ms="genre" placeholder="{{ __('Tafsir, Fiqh, Seerah') }}" class="w-full rounded-2xl border border-ink/10 bg-white px-3 py-2 text-sm focus:border-ink/30 focus:outline-none" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-xs uppercase tracking-[0.2em] text-ink-soft" for="event-audience">{{ __('Audience') }}</label>
                            <input id="event-audience" type="text" wire:model.debounce.400ms="audience" placeholder="{{ __('Youth, family, open') }}" class="w-full rounded-2xl border border-ink/10 bg-white px-3 py-2 text-sm focus:border-ink/30 focus:outline-none" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Timeframe') }}</span>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" class="rounded-full border border-ink/10 px-3 py-2 text-xs font-medium transition hover:border-ink/30 {{ $timeframe === 'upcoming' ? 'bg-ink text-parchment' : 'bg-white text-ink' }}" wire:click="$set('timeframe', 'upcoming')">{{ __('Upcoming') }}</button>
                            <button type="button" class="rounded-full border border-ink/10 px-3 py-2 text-xs font-medium transition hover:border-ink/30 {{ $timeframe === 'week' ? 'bg-ink text-parchment' : 'bg-white text-ink' }}" wire:click="$set('timeframe', 'week')">{{ __('7 days') }}</button>
                            <button type="button" class="rounded-full border border-ink/10 px-3 py-2 text-xs font-medium transition hover:border-ink/30 {{ $timeframe === 'month' ? 'bg-ink text-parchment' : 'bg-white text-ink' }}" wire:click="$set('timeframe', 'month')">{{ __('30 days') }}</button>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-xs uppercase tracking-[0.2em] text-ink-soft" for="event-sort">{{ __('Sort by') }}</label>
                        <select id="event-sort" wire:model="sort" class="w-full rounded-2xl border border-ink/10 bg-white px-3 py-2 text-sm focus:border-ink/30 focus:outline-none">
                            <option value="time">{{ __('Soonest') }}</option>
                            <option value="recent">{{ __('Recently added') }}</option>
                            <option value="popular">{{ __('Most saved') }}</option>
                        </select>
                    </div>
                </div>
            </aside>
            <div class="flex flex-col gap-6">
                <div class="flex flex-col gap-2">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex flex-col gap-1">
                            <h2 class="font-display text-2xl">{{ __('Events') }}</h2>
                            <span class="text-sm text-ink-soft">{{ $this->events->total() }} {{ __('results') }}</span>
                        </div>
                        <div class="hidden items-center gap-2 text-xs uppercase tracking-[0.2em] text-ink-soft" wire:loading.class.remove="hidden">
                            <span class="h-2 w-2 animate-pulse rounded-full bg-amber"></span>
                            {{ __('Updating') }}
                        </div>
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @forelse ($this->events as $event)
                        <a href="{{ route('events.show', $event->slug) }}" wire:key="events-card-{{ $event->id }}" class="group flex h-full flex-col gap-4 rounded-3xl border border-ink/10 bg-white/80 p-5 shadow-lg shadow-ink/5 transition hover:-translate-y-1">
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
                                    <span class="rounded-full border border-ink/10 bg-white px-2 py-1" wire:key="events-card-speaker-{{ $event->id }}-{{ $speaker->id }}">{{ $speaker->name }}</span>
                                @endforeach
                                @foreach ($event->topics->take(2) as $topic)
                                    <span class="rounded-full border border-ink/10 bg-white px-2 py-1" wire:key="events-card-topic-{{ $event->id }}-{{ $topic->id }}">{{ $topic->name }}</span>
                                @endforeach
                            </div>
                            <div class="flex flex-wrap gap-2 text-xs uppercase tracking-[0.2em] text-ink-soft">
                                @if ($event->livestream_url)
                                    <span class="rounded-full bg-sea/20 px-2 py-1">{{ __('Online') }}</span>
                                @endif
                                @if ($event->registration_required)
                                    <span class="rounded-full bg-ink/10 px-2 py-1">{{ __('Registration') }}</span>
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="col-span-full rounded-3xl border border-dashed border-ink/15 bg-white/70 p-6 text-sm text-ink-soft">{{ __('No events match your filters yet.') }}</div>
                    @endforelse
                </div>
                <div class="flex justify-center">
                    {{ $this->events->onEachSide(1)->links() }}
                </div>
            </div>
        </div>
    </div>
</section>
