@section('title', __('Dashboard') . ' - ' . config('app.name'))

@php
    $user = auth()->user();
    $stats = $this->profileStats;
    $myEvents = $this->myEvents;
    $myRegistrations = $this->myRegistrations;
    $mySavedEvents = $this->mySavedEvents;
    $mySavedSearches = $this->mySavedSearches;
    $digestFrequencyOptions = $this->digestFrequencyOptions;
    $digestChannelOptions = $this->digestChannelOptions;
@endphp

<div class="bg-slate-50 min-h-screen py-12 pb-32">
    <div class="container mx-auto px-6 lg:px-12">
        <div class="mx-auto max-w-6xl space-y-8">
            <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm md:p-8">
                <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-600">{{ __('My Dashboard') }}</p>
                        <h1 class="mt-2 font-heading text-3xl font-bold text-slate-900">{{ $user?->name }}</h1>
                        <p class="mt-2 text-sm text-slate-500">{{ $user?->email }}</p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ __('Joined') }} {{ $user?->created_at?->translatedFormat('d M Y') }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                        <div class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Institutions') }}</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['institutions_count'] }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('My Events') }}</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['events_count'] }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Registrations') }}</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['registrations_count'] }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Saved Events') }}</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['saved_events_count'] }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Saved Searches') }}</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['saved_searches_count'] }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm md:p-8">
                <div class="mb-5 flex items-center justify-between gap-4">
                    <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('My Events') }}</h2>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('dashboard.events.create-advanced') }}" wire:navigate
                            class="inline-flex items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 transition-colors hover:bg-emerald-100">
                            {{ __('Create Advanced Event') }}
                        </a>
                        <a href="{{ route('submit-event.create') }}" wire:navigate
                            class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-700">
                            {{ __('Submit New Event') }}
                        </a>
                    </div>
                </div>

                @if($myEvents->isEmpty())
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                        <p class="text-base font-semibold text-slate-700">{{ __('No events yet.') }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Create your first event to see it here.') }}</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead>
                                <tr class="text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                    <th class="pb-3 pr-4">{{ __('Title') }}</th>
                                    <th class="pb-3 pr-4">{{ __('Date') }}</th>
                                    <th class="pb-3 pr-4">{{ __('Institution') }}</th>
                                    <th class="pb-3">{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                                @foreach($myEvents as $event)
                                    <tr wire:key="dashboard-event-{{ $event->id }}" class="align-top">
                                        <td class="py-4 pr-4">
                                            <a href="{{ route('events.show', $event) }}" wire:navigate
                                                class="font-semibold text-slate-900 hover:text-emerald-700">
                                                {{ $event->title }}
                                            </a>
                                            @if($event->userCanManage($user))
                                                <p class="mt-1">
                                                    <a href="{{ route('dashboard.events.schedule', $event) }}" wire:navigate
                                                        class="text-xs font-semibold text-emerald-700 hover:text-emerald-800">
                                                        {{ __('Manage Schedule') }}
                                                    </a>
                                                </p>
                                            @endif
                                            @if($event->venue?->name)
                                                <p class="mt-1 text-xs text-slate-500">{{ $event->venue->name }}</p>
                                            @endif
                                        </td>
                                        <td class="py-4 pr-4">
                                            {{ $event->starts_at?->translatedFormat('d M Y, h:i A') ?? __('TBC') }}
                                        </td>
                                        <td class="py-4 pr-4">{{ $event->institution?->name ?? __('Independent') }}</td>
                                        <td class="py-4">
                                            <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                                {{ str((string) $event->status)->replace('_', ' ')->headline() }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6">
                        {{ $myEvents->links() }}
                    </div>
                @endif
            </section>

            <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm md:p-8">
                <div class="mb-5 flex items-center justify-between gap-4">
                    <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('My Registrations') }}</h2>
                    <a href="{{ route('events.index') }}" wire:navigate
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:border-emerald-500 hover:text-emerald-700">
                        {{ __('Find More Events') }}
                    </a>
                </div>

                @if($myRegistrations->isEmpty())
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                        <p class="text-base font-semibold text-slate-700">{{ __('No registrations found.') }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Your event registrations will appear here.') }}</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($myRegistrations as $registration)
                            @php
                                $event = $registration->event;
                            @endphp
                            <article wire:key="dashboard-registration-{{ $registration->id }}"
                                class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        @if($event)
                                            <a href="{{ route('events.show', $event) }}" wire:navigate
                                                class="font-semibold text-slate-900 hover:text-emerald-700">
                                                {{ $event->title }}
                                            </a>
                                            <p class="mt-1 text-xs text-slate-500">
                                                {{ $event->starts_at?->translatedFormat('d M Y, h:i A') ?? __('TBC') }}
                                                @if($event->institution?->name)
                                                    • {{ $event->institution->name }}
                                                @endif
                                            </p>
                                        @else
                                            <p class="font-semibold text-slate-700">{{ __('Event unavailable') }}</p>
                                        @endif
                                    </div>
                                    <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 border border-slate-200">
                                        {{ str($registration->status)->replace('_', ' ')->headline() }}
                                    </span>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <div class="mt-6">
                        {{ $myRegistrations->links() }}
                    </div>
                @endif
            </section>

            <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm md:p-8">
                <div class="mb-5 flex items-center justify-between gap-4">
                    <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('My Saved Events') }}</h2>
                    <a href="{{ route('events.index') }}" wire:navigate
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:border-emerald-500 hover:text-emerald-700">
                        {{ __('Discover Events') }}
                    </a>
                </div>

                @if($mySavedEvents->isEmpty())
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                        <p class="text-base font-semibold text-slate-700">{{ __('No saved events yet.') }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Save events from discovery to quickly access them here.') }}</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead>
                                <tr class="text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                    <th class="pb-3 pr-4">{{ __('Title') }}</th>
                                    <th class="pb-3 pr-4">{{ __('Date') }}</th>
                                    <th class="pb-3 pr-4">{{ __('Institution') }}</th>
                                    <th class="pb-3">{{ __('Venue') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                                @foreach($mySavedEvents as $event)
                                    <tr wire:key="dashboard-saved-event-{{ $event->id }}" class="align-top">
                                        <td class="py-4 pr-4">
                                            <a href="{{ route('events.show', $event) }}" wire:navigate
                                                class="font-semibold text-slate-900 hover:text-emerald-700">
                                                {{ $event->title }}
                                            </a>
                                        </td>
                                        <td class="py-4 pr-4">{{ $event->starts_at?->translatedFormat('d M Y, h:i A') ?? __('TBC') }}</td>
                                        <td class="py-4 pr-4">{{ $event->institution?->name ?? __('Independent') }}</td>
                                        <td class="py-4">{{ $event->venue?->name ?? __('Online / TBD') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6">
                        {{ $mySavedEvents->links() }}
                    </div>
                @endif
            </section>

            <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm md:p-8">
                <div class="mb-5 flex items-center justify-between gap-4">
                    <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('My Saved Searches') }}</h2>
                    <a href="{{ route('saved-searches.index') }}" wire:navigate
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:border-emerald-500 hover:text-emerald-700">
                        {{ __('Manage Saved Searches') }}
                    </a>
                </div>

                @if($mySavedSearches->isEmpty())
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                        <p class="text-base font-semibold text-slate-700">{{ __('No saved searches yet.') }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Create one from the events page or saved searches page.') }}</p>
                    </div>
                @else
                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach($mySavedSearches as $savedSearch)
                            <article wire:key="dashboard-saved-search-{{ $savedSearch->id }}"
                                class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 class="font-semibold text-slate-900">{{ $savedSearch->name }}</h3>
                                        <p class="mt-1 text-xs text-slate-500">{{ __('Updated') }} {{ $savedSearch->updated_at?->diffForHumans() }}</p>
                                    </div>
                                    <span class="inline-flex rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 border border-slate-200">
                                        {{ str($savedSearch->notify)->headline() }}
                                    </span>
                                </div>

                                @if($savedSearch->query)
                                    <p class="mt-3 text-sm text-slate-600">
                                        <span class="font-semibold text-slate-800">{{ __('Keyword:') }}</span>
                                        {{ $savedSearch->query }}
                                    </p>
                                @endif

                                <div class="mt-4">
                                    <a href="{{ route('events.index', $this->toSavedSearchQueryParams($savedSearch)) }}" wire:navigate
                                        class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition-colors hover:bg-emerald-600">
                                        {{ __('Run Search') }}
                                    </a>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <div class="mt-6">
                        {{ $mySavedSearches->links() }}
                    </div>
                @endif
            </section>

            <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm md:p-8">
                <div class="mb-5">
                    <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Notification Preferences') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Control how saved search digests are delivered to you.') }}</p>
                </div>

                @if (session('digest_preferences_status'))
                    <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3 text-sm font-semibold text-emerald-700">
                        {{ session('digest_preferences_status') }}
                    </div>
                @endif

                <form wire:submit="saveDigestNotificationPreferences" class="space-y-5">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" wire:model="digestNotificationsEnabled"
                                class="mt-1 size-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span>
                                <span class="block text-sm font-semibold text-slate-800">{{ __('Enable saved search digest notifications') }}</span>
                                <span class="block text-xs text-slate-500">{{ __('If disabled, digest notifications are fully turned off.') }}</span>
                            </span>
                        </label>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <label for="digest-notification-frequency" class="text-sm font-semibold text-slate-700">{{ __('Frequency') }}</label>
                            <select id="digest-notification-frequency" wire:model="digestNotificationFrequency"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none"
                                @disabled(! $this->digestNotificationsEnabled)>
                                @foreach($digestFrequencyOptions as $frequencyValue => $frequencyLabel)
                                    <option value="{{ $frequencyValue }}">{{ $frequencyLabel }}</option>
                                @endforeach
                            </select>
                            @error('digestNotificationFrequency')
                                <p class="text-sm text-danger-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <fieldset class="space-y-2">
                            <legend class="text-sm font-semibold text-slate-700">{{ __('Channels') }}</legend>
                            <div class="space-y-2 rounded-xl border border-slate-200 bg-white p-3">
                                @foreach($digestChannelOptions as $channelValue => $channelLabel)
                                    <label class="flex items-center gap-2 text-sm text-slate-700">
                                        <input type="checkbox"
                                            wire:model="digestNotificationChannels"
                                            value="{{ $channelValue }}"
                                            class="size-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                            @disabled(! $this->digestNotificationsEnabled)>
                                        <span>{{ $channelLabel }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('digestNotificationChannels')
                                <p class="text-sm text-danger-600">{{ $message }}</p>
                            @enderror
                            @error('digestNotificationChannels.*')
                                <p class="text-sm text-danger-600">{{ $message }}</p>
                            @enderror
                        </fieldset>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                            class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-700">
                            {{ __('Save Preferences') }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</div>
