@section('title', __('Dashboard') . ' - ' . config('app.name'))

@php
    $user = auth()->user();
    $stats = $this->profileStats;
    $myEvents = $this->myEvents;
    $myRegistrations = $this->myRegistrations;
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

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
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
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Saved Searches') }}</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['saved_searches_count'] }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm md:p-8">
                <div class="mb-5 flex items-center justify-between gap-4">
                    <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('My Events') }}</h2>
                    <a href="{{ route('submit-event.create') }}" wire:navigate
                        class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-700">
                        {{ __('Submit New Event') }}
                    </a>
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
        </div>
    </div>
</div>
