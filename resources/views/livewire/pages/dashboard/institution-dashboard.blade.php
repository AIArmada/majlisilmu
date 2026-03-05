@section('title', __('Institution Dashboard') . ' - ' . config('app.name'))

@php
    $institutions = $this->institutions;
    $selectedInstitution = $this->selectedInstitution;
    $stats = $this->institutionStats;
    $events = $this->institutionEvents;
    $registrations = $this->institutionRegistrations;
@endphp

<div class="bg-slate-50 min-h-screen py-12 pb-32">
    <div class="container mx-auto px-6 lg:px-12">
        <div class="mx-auto max-w-6xl space-y-8">
            <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm md:p-8">
                <div class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-600">{{ __('Institution Dashboard') }}</p>
                        <h1 class="mt-2 font-heading text-3xl font-bold text-slate-900">{{ __('Manage Institution Operations') }}</h1>
                        <p class="mt-2 text-sm text-slate-500">{{ __('Review institution profile, events, and registrations in one place.') }}</p>
                    </div>

                    <div class="w-full md:w-80">
                        <label for="institution-dashboard-select" class="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">
                            {{ __('Institution') }}
                        </label>
                        <select id="institution-dashboard-select" wire:model.live="institutionId"
                            class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-900 focus:border-emerald-500 focus:outline-none">
                            @forelse($institutions as $institution)
                                <option value="{{ $institution->id }}">{{ $institution->name }}</option>
                            @empty
                                <option value="">{{ __('No institution membership') }}</option>
                            @endforelse
                        </select>
                    </div>
                </div>
            </section>

            @if(!$selectedInstitution)
                <section class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center">
                    <p class="text-lg font-semibold text-slate-700">{{ __('You do not have institution access yet.') }}</p>
                    <p class="mt-2 text-sm text-slate-500">{{ __('Ask an institution admin to add you as a member to unlock this dashboard.') }}</p>
                </section>
            @else
                <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm md:p-8">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="font-heading text-2xl font-bold text-slate-900">{{ $selectedInstitution->name }}</h2>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ __('Type:') }} {{ $selectedInstitution->type?->value ? str($selectedInstitution->type->value)->headline() : __('Not specified') }}
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Events (All)') }}</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['events_count'] }}</p>
                                <p class="mt-1 text-[11px] text-slate-500">{{ __('Public active: :count', ['count' => $stats['public_events_count']]) }}</p>
                                <p class="text-[11px] text-slate-500">{{ __('Internal / hidden: :count', ['count' => $stats['internal_events_count']]) }}</p>
                            </div>
                            <div class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Registrations (All)') }}</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['registrations_count'] }}</p>
                                <p class="mt-1 text-[11px] text-slate-500">{{ __('Public active: :count', ['count' => $stats['public_registrations_count']]) }}</p>
                                <p class="text-[11px] text-slate-500">{{ __('Internal / hidden: :count', ['count' => $stats['internal_registrations_count']]) }}</p>
                            </div>
                            <div class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Members') }}</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['members_count'] }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm md:p-8">
                    <h3 class="mb-5 font-heading text-2xl font-bold text-slate-900">{{ __('Institution Events') }}</h3>
                    <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                        {{ __('This dashboard shows all institution events, including draft, private, and inactive records. Public institution pages only show events that are public + active.') }}
                    </div>

                    @if($events->isEmpty())
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                            <p class="text-base font-semibold text-slate-700">{{ __('No events found for this institution.') }}</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100">
                                <thead>
                                    <tr class="text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                        <th class="pb-3 pr-4">{{ __('Title') }}</th>
                                        <th class="pb-3 pr-4">{{ __('Date') }}</th>
                                        <th class="pb-3 pr-4">{{ __('Venue') }}</th>
                                        <th class="pb-3 pr-4">{{ __('Status') }}</th>
                                        <th class="pb-3 pr-4">{{ __('Visibility') }}</th>
                                        <th class="pb-3 pr-4">{{ __('Public Page') }}</th>
                                        <th class="pb-3">{{ __('Registrations') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                                    @foreach($events as $event)
                                        @php
                                            $statusValue = (string) $event->status;
                                            $visibilityValue = $event->visibility?->value ?? (string) $event->visibility;
                                            $isPublicListed = $event->is_active
                                                && in_array($statusValue, \App\Models\Event::PUBLIC_STATUSES, true)
                                                && $visibilityValue === \App\Enums\EventVisibility::Public->value;
                                        @endphp
                                        <tr wire:key="institution-event-{{ $event->id }}">
                                            <td class="py-4 pr-4">
                                                <a href="{{ route('events.show', $event) }}" wire:navigate
                                                    class="font-semibold text-slate-900 hover:text-emerald-700">
                                                    {{ $event->title }}
                                                </a>
                                            </td>
                                            <td class="py-4 pr-4">{{ $event->starts_at ? \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($event->starts_at, 'd M Y, h:i A') : __('TBC') }}</td>
                                            <td class="py-4 pr-4">{{ $event->venue?->name ?? __('Online / TBD') }}</td>
                                            <td class="py-4 pr-4">
                                                <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                                    {{ str((string) $event->status)->replace('_', ' ')->headline() }}
                                                </span>
                                            </td>
                                            <td class="py-4 pr-4">
                                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $visibilityValue === \App\Enums\EventVisibility::Public->value ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                                    {{ $visibilityValue === \App\Enums\EventVisibility::Public->value ? __('Public') : __('Private') }}
                                                </span>
                                            </td>
                                            <td class="py-4 pr-4">
                                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $isPublicListed ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">
                                                    {{ $isPublicListed ? __('Visible on public page') : __('Internal only') }}
                                                </span>
                                            </td>
                                            <td class="py-4 font-semibold text-slate-900">{{ $event->registrations_count }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6">
                            {{ $events->links() }}
                        </div>
                    @endif
                </section>

                <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm md:p-8">
                    <h3 class="mb-5 font-heading text-2xl font-bold text-slate-900">{{ __('Event Registrations') }}</h3>

                    @if($registrations->isEmpty())
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                            <p class="text-base font-semibold text-slate-700">{{ __('No registration records yet.') }}</p>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($registrations as $registration)
                                @php
                                    $registrationEvent = $registration->event;
                                    $registrationStatusValue = (string) ($registrationEvent?->status ?? '');
                                    $registrationVisibilityValue = $registrationEvent?->visibility?->value ?? (string) ($registrationEvent?->visibility ?? '');
                                    $registrationIsPublicListed = $registrationEvent?->is_active
                                        && in_array($registrationStatusValue, \App\Models\Event::PUBLIC_STATUSES, true)
                                        && $registrationVisibilityValue === \App\Enums\EventVisibility::Public->value;
                                @endphp
                                <article wire:key="institution-registration-{{ $registration->id }}"
                                    class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            @if($registration->event)
                                                <a href="{{ route('events.show', $registration->event) }}" wire:navigate
                                                    class="font-semibold text-slate-900 hover:text-emerald-700">
                                                    {{ $registration->event->title }}
                                                </a>
                                                <p class="mt-1 text-xs text-slate-500">
                                                    {{ $registration->event?->starts_at ? \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($registration->event->starts_at, 'd M Y, h:i A') : __('TBC') }}
                                                </p>
                                                <p class="mt-1 text-xs text-slate-500">
                                                    {{ __('Event scope: :scope', ['scope' => $registrationIsPublicListed ? __('Visible on public page') : __('Internal only')]) }}
                                                </p>
                                            @endif
                                            <p class="mt-2 text-sm text-slate-700">
                                                {{ $registration->name ?: __('Unknown attendee') }}
                                                @if($registration->email)
                                                    • {{ $registration->email }}
                                                @endif
                                            </p>
                                        </div>
                                        <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 border border-slate-200">
                                            {{ str($registration->status)->replace('_', ' ')->headline() }}
                                        </span>
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        <div class="mt-6">
                            {{ $registrations->links() }}
                        </div>
                    @endif
                </section>
            @endif
        </div>
    </div>
</div>
