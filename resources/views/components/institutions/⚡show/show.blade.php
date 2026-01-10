<div>
    <section class="px-6 sm:px-10 lg:px-16 pb-16">
        <div class="flex flex-col gap-8">
            <div class="flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-ink-soft">
                <a href="{{ route('institutions.index') }}" class="hover:text-ink">{{ __('Institutions') }}</a>
                <span>/</span>
                <span>{{ $institution->name }}</span>
            </div>
            <div class="grid gap-8 lg:grid-cols-[1.5fr_0.8fr]">
                <div class="flex flex-col gap-6">
                    <div class="flex flex-col gap-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="rounded-full border border-ink/10 bg-white px-3 py-1 text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __($institution->type) }}</span>
                            <span class="rounded-full bg-amber/20 px-3 py-1 text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __($institution->verification_status) }}</span>
                        </div>
                        <h1 class="font-display text-4xl sm:text-5xl">{{ $institution->name }}</h1>
                        <p class="text-ink-soft">{{ $institution->description ?? __('A trusted space for community learning and gatherings.') }}</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-3 rounded-3xl border border-ink/10 bg-white/80 p-5">
                            <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Contact') }}</span>
                            <div class="flex flex-col gap-2 text-sm text-ink-soft">
                                @if ($institution->phone)
                                    <span>{{ $institution->phone }}</span>
                                @endif
                                @if ($institution->email)
                                    <span>{{ $institution->email }}</span>
                                @endif
                                @if ($institution->website_url)
                                    <a href="{{ $institution->website_url }}" target="_blank" rel="noopener" class="text-ink hover:text-ink-soft">{{ $institution->website_url }}</a>
                                @endif
                                @if (! $institution->phone && ! $institution->email && ! $institution->website_url)
                                    <span>{{ __('No contact details shared yet.') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex flex-col gap-3 rounded-3xl border border-ink/10 bg-white/80 p-5">
                            <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Address') }}</span>
                            <div class="flex flex-col gap-2 text-sm text-ink-soft">
                                <span>{{ $institution->address_line1 }}</span>
                                @if ($institution->address_line2)
                                    <span>{{ $institution->address_line2 }}</span>
                                @endif
                                <span>{{ $institution->postcode }} {{ $institution->city }}</span>
                                <span>{{ $institution->district?->name ?? $institution->state?->name ?? __('Malaysia') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <aside class="flex flex-col gap-6">
                    <div class="flex flex-col gap-4 rounded-3xl border border-ink/10 bg-white/80 p-6 shadow-xl shadow-ink/10">
                        <div class="flex items-center justify-between">
                            <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Trust score') }}</span>
                            <span class="font-display text-2xl">{{ $institution->trust_score ?? 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Events') }}</span>
                            <span class="text-sm font-medium">{{ $institution->events_count }}</span>
                        </div>
                        <a href="{{ route('events.index', ['institution_id' => $institution->id]) }}" class="inline-flex items-center justify-center rounded-full bg-ink px-4 py-3 text-sm font-medium text-parchment shadow-lg shadow-ink/20">{{ __('See events') }}</a>
                    </div>
                    @if ($institution->donationAccounts->isNotEmpty())
                        <div class="flex flex-col gap-3 rounded-3xl border border-ink/10 bg-parchment p-5">
                            <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Donation accounts') }}</span>
                            <div class="flex flex-col gap-3">
                                @foreach ($institution->donationAccounts as $account)
                                    <div class="rounded-2xl border border-ink/10 bg-white px-3 py-2 text-sm text-ink-soft" wire:key="institution-donation-{{ $account->id }}">
                                        <span class="font-medium text-ink">{{ $account->label }}</span>
                                        <div>{{ $account->bank_name }} - {{ $account->account_number }}</div>
                                        <div>{{ $account->recipient_name }}</div>
                                        @if ($account->duitnow_id)
                                            <div>{{ __('DuitNow: :id', ['id' => $account->duitnow_id]) }}</div>
                                        @endif
                                    </div>
                                @endforeach
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
                <div class="flex flex-col gap-2">
                    <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('Upcoming') }}</span>
                    <h2 class="font-display text-3xl">{{ __('Upcoming events') }}</h2>
                </div>
                <a href="{{ route('events.index', ['institution_id' => $institution->id]) }}" class="text-sm font-medium text-ink hover:text-ink-soft">{{ __('View all') }}</a>
            </div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($this->upcomingEvents as $event)
                    <a href="{{ route('events.show', $event->slug) }}" wire:key="institution-event-{{ $event->id }}" class="flex flex-col gap-3 rounded-3xl border border-ink/10 bg-white/80 p-4 transition hover:-translate-y-1">
                        <span class="text-xs uppercase tracking-[0.2em] text-ink-soft">{{ $event->starts_at?->translatedFormat('D, d M') }}</span>
                        <span class="font-display text-xl">{{ $event->title }}</span>
                        <div class="flex flex-wrap gap-2 text-xs text-ink-soft">
                            @foreach ($event->speakers->take(2) as $speaker)
                                <span class="rounded-full border border-ink/10 bg-white px-2 py-1" wire:key="institution-event-speaker-{{ $event->id }}-{{ $speaker->id }}">{{ $speaker->name }}</span>
                            @endforeach
                        </div>
                    </a>
                @empty
                    <div class="rounded-3xl border border-dashed border-ink/15 bg-white/70 p-6 text-sm text-ink-soft">{{ __('No upcoming events listed yet.') }}</div>
                @endforelse
            </div>
        </div>
    </section>
</div>
