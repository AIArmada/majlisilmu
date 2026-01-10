<section class="px-6 sm:px-10 lg:px-16 pb-16">
    <div class="flex flex-col gap-10">
        <div class="flex flex-col gap-3 animate-rise">
            <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Speakers') }}</span>
            <h1 class="font-display text-4xl sm:text-5xl">{{ __('Scholars and teachers') }}</h1>
            <p class="text-ink-soft max-w-2xl">{{ __('Browse the voices shaping community learning and follow their upcoming sessions.') }}</p>
        </div>
        <div class="grid gap-8 lg:grid-cols-[280px_1fr]">
            <aside class="flex flex-col gap-6 rounded-3xl border border-ink/10 bg-white/80 p-6 shadow-xl shadow-ink/5">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium">{{ __('Filters') }}</span>
                    <button type="button" class="text-xs uppercase tracking-[0.2em] text-ink-soft" wire:click="resetFilters">{{ __('Reset') }}</button>
                </div>
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-xs uppercase tracking-[0.2em] text-ink-soft" for="speaker-search">{{ __('Search') }}</label>
                        <input id="speaker-search" type="text" wire:model.debounce.400ms="search" placeholder="{{ __('Speaker name') }}" class="w-full rounded-2xl border border-ink/10 bg-white px-3 py-2 text-sm focus:border-ink/30 focus:outline-none" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-xs uppercase tracking-[0.2em] text-ink-soft" for="speaker-status">{{ __('Verification') }}</label>
                        <select id="speaker-status" wire:model="status" class="w-full rounded-2xl border border-ink/10 bg-white px-3 py-2 text-sm focus:border-ink/30 focus:outline-none">
                            <option value="">{{ __('All statuses') }}</option>
                            <option value="verified">{{ __('verified') }}</option>
                            <option value="pending">{{ __('pending') }}</option>
                            <option value="unverified">{{ __('unverified') }}</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-xs uppercase tracking-[0.2em] text-ink-soft" for="speaker-sort">{{ __('Sort by') }}</label>
                        <select id="speaker-sort" wire:model="sort" class="w-full rounded-2xl border border-ink/10 bg-white px-3 py-2 text-sm focus:border-ink/30 focus:outline-none">
                            <option value="trust">{{ __('Highest trust') }}</option>
                            <option value="name">{{ __('Name') }}</option>
                            <option value="recent">{{ __('Newest') }}</option>
                        </select>
                    </div>
                </div>
            </aside>
            <div class="flex flex-col gap-6">
                <div class="flex items-center justify-between">
                    <div class="flex flex-col gap-1">
                        <h2 class="font-display text-2xl">{{ __('Speakers') }}</h2>
                        <span class="text-sm text-ink-soft">{{ $this->speakers->total() }} {{ __('results') }}</span>
                    </div>
                    <div class="hidden items-center gap-2 text-xs uppercase tracking-[0.2em] text-ink-soft" wire:loading.class.remove="hidden">
                        <span class="h-2 w-2 animate-pulse rounded-full bg-amber"></span>
                        {{ __('Updating') }}
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @forelse ($this->speakers as $speaker)
                        <a href="{{ route('speakers.show', $speaker->slug) }}" wire:key="speaker-card-{{ $speaker->id }}" class="group flex h-full flex-col gap-4 rounded-3xl border border-ink/10 bg-white/80 p-5 shadow-lg shadow-ink/5 transition hover:-translate-y-1">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-ink text-parchment text-xs font-semibold">{{ strtoupper(substr($speaker->name, 0, 1)) }}</span>
                                <div class="flex flex-col">
                                    <span class="font-medium text-ink group-hover:text-ink-soft">{{ $speaker->name }}</span>
                                    <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __($speaker->verification_status) }}</span>
                                </div>
                            </div>
                            <p class="text-sm text-ink-soft">{{ \Illuminate\Support\Str::limit($speaker->bio ?? __('Focused on community learning and education.'), 90) }}</p>
                            <div class="flex items-center justify-between text-xs text-ink-soft">
                                <span>{{ __('Trust score') }}</span>
                                <span class="font-medium text-ink">{{ $speaker->trust_score ?? 0 }}</span>
                            </div>
                            <div class="text-xs text-ink-soft">{{ __(':count events featured', ['count' => $speaker->events_count]) }}</div>
                        </a>
                    @empty
                        <div class="col-span-full rounded-3xl border border-dashed border-ink/15 bg-white/70 p-6 text-sm text-ink-soft">{{ __('No speakers match your filters yet.') }}</div>
                    @endforelse
                </div>
                <div class="flex justify-center">
                    {{ $this->speakers->onEachSide(1)->links() }}
                </div>
            </div>
        </div>
    </div>
</section>
