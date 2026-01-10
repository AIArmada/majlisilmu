<section class="px-6 sm:px-10 lg:px-16 pb-16">
    <div class="flex flex-col gap-10">
        <div class="flex flex-col gap-3 animate-rise">
            <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Institutions') }}</span>
            <h1 class="font-display text-4xl sm:text-5xl">{{ __('Spaces that host learning') }}</h1>
            <p class="text-ink-soft max-w-2xl">{{ __('Discover mosques, surau, and learning centers that open their doors for the community.') }}</p>
        </div>
        <div class="grid gap-8 lg:grid-cols-[280px_1fr]">
            <aside class="flex flex-col gap-6 rounded-3xl border border-ink/10 bg-white/80 p-6 shadow-xl shadow-ink/5">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium">{{ __('Filters') }}</span>
                    <button type="button" class="text-xs uppercase tracking-[0.2em] text-ink-soft" wire:click="resetFilters">{{ __('Reset') }}</button>
                </div>
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-xs uppercase tracking-[0.2em] text-ink-soft" for="institution-search">{{ __('Search') }}</label>
                        <input id="institution-search" type="text" wire:model.debounce.400ms="search" placeholder="{{ __('Institution name') }}" class="w-full rounded-2xl border border-ink/10 bg-white px-3 py-2 text-sm focus:border-ink/30 focus:outline-none" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-xs uppercase tracking-[0.2em] text-ink-soft" for="institution-state">{{ __('State') }}</label>
                        <select id="institution-state" wire:model="stateId" class="w-full rounded-2xl border border-ink/10 bg-white px-3 py-2 text-sm focus:border-ink/30 focus:outline-none">
                            <option value="">{{ __('All states') }}</option>
                            @foreach ($this->states as $state)
                                <option value="{{ $state->id }}" wire:key="institutions-state-{{ $state->id }}">{{ $state->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-xs uppercase tracking-[0.2em] text-ink-soft" for="institution-type">{{ __('Type') }}</label>
                        <select id="institution-type" wire:model="type" class="w-full rounded-2xl border border-ink/10 bg-white px-3 py-2 text-sm focus:border-ink/30 focus:outline-none">
                            <option value="">{{ __('All types') }}</option>
                            <option value="masjid">{{ __('masjid') }}</option>
                            <option value="surau">{{ __('surau') }}</option>
                            <option value="others">{{ __('others') }}</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-xs uppercase tracking-[0.2em] text-ink-soft" for="institution-sort">{{ __('Sort by') }}</label>
                        <select id="institution-sort" wire:model="sort" class="w-full rounded-2xl border border-ink/10 bg-white px-3 py-2 text-sm focus:border-ink/30 focus:outline-none">
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
                        <h2 class="font-display text-2xl">{{ __('Institutions') }}</h2>
                        <span class="text-sm text-ink-soft">{{ $this->institutions->total() }} {{ __('results') }}</span>
                    </div>
                    <div class="hidden items-center gap-2 text-xs uppercase tracking-[0.2em] text-ink-soft" wire:loading.class.remove="hidden">
                        <span class="h-2 w-2 animate-pulse rounded-full bg-amber"></span>
                        {{ __('Updating') }}
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @forelse ($this->institutions as $institution)
                        <a href="{{ route('institutions.show', $institution->slug) }}" wire:key="institution-card-{{ $institution->id }}" class="group flex h-full flex-col gap-4 rounded-3xl border border-ink/10 bg-white/80 p-5 shadow-lg shadow-ink/5 transition hover:-translate-y-1">
                            <div class="flex items-center justify-between text-xs uppercase tracking-[0.2em] text-ink-soft">
                                <span>{{ __($institution->type) }}</span>
                                <span class="rounded-full bg-amber/20 px-2 py-1">{{ __($institution->verification_status) }}</span>
                            </div>
                            <div class="flex flex-col gap-2">
                                <h3 class="font-display text-xl text-ink group-hover:text-ink-soft">{{ $institution->name }}</h3>
                                <span class="text-sm text-ink-soft">{{ $institution->district?->name ?? $institution->state?->name ?? __('Malaysia') }}</span>
                            </div>
                            <div class="flex items-center justify-between text-xs text-ink-soft">
                                <span>{{ __('Trust score') }}</span>
                                <span class="font-medium text-ink">{{ $institution->trust_score ?? 0 }}</span>
                            </div>
                            <div class="text-xs text-ink-soft">{{ __(':count events hosted', ['count' => $institution->events_count]) }}</div>
                        </a>
                    @empty
                        <div class="col-span-full rounded-3xl border border-dashed border-ink/15 bg-white/70 p-6 text-sm text-ink-soft">{{ __('No institutions match your filters yet.') }}</div>
                    @endforelse
                </div>
                <div class="flex justify-center">
                    {{ $this->institutions->onEachSide(1)->links() }}
                </div>
            </div>
        </div>
    </div>
</section>
