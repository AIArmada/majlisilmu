@section('title', __('Institution Dashboard') . ' - ' . config('app.name'))

@php
    $institutions = $this->institutions;
    $selectedInstitution = $this->selectedInstitution;
    $stats = $this->institutionStats;
    $events = $this->institutionEvents;
    $members = $this->institutionMembers;
    $memberRoleMap = $this->institutionMemberRoleMap;
    $eventStatusOptions = $this->eventStatusOptions;
    $eventVisibilityOptions = $this->eventVisibilityOptions;
    $eventSortOptions = $this->eventSortOptions;
    $institutionRoleOptions = $this->institutionRoleOptions;
    $canManageMembers = $this->canManageMembers;
    $translateStatusLabel = static function (string $status): string {
        $translated = __($status);

        if ($translated !== $status) {
            return $translated;
        }

        return str($status)->replace('_', ' ')->headline()->toString();
    };
    $translateRoleLabel = static function (string $role): string {
        $label = str($role)->replace('_', ' ')->headline()->toString();
        $translated = __($label);

        return $translated !== $label ? $translated : $label;
    };
    $canEditInstitution = $selectedInstitution !== null && (auth()->user()?->can('update', $selectedInstitution) ?? false);
    $ahliInstitutionEditUrl = $canEditInstitution
        ? \App\Filament\Ahli\Resources\Institutions\InstitutionResource::getUrl('edit', ['record' => $selectedInstitution], panel: 'ahli')
        : null;
    $advancedCreateUrl = $selectedInstitution !== null
        ? route('dashboard.events.create-advanced', ['institution' => $selectedInstitution->id])
        : null;
@endphp

<div class="min-h-screen bg-slate-50 py-12 pb-32">
    <div class="container mx-auto px-6 lg:px-12">
        <div class="mx-auto max-w-6xl space-y-8">
            <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm md:p-8">
                <div class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-600">{{ __('Institution Dashboard') }}</p>
                        <h1 class="mt-2 font-heading text-3xl font-bold text-slate-900">{{ __('Manage Institution Operations') }}</h1>
                        <p class="mt-2 text-sm text-slate-500">{{ __('Review institution profile, events, and members in one place.') }}</p>
                    </div>

                    <div class="w-full md:w-80">
                        <label for="institution-dashboard-select" class="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">
                            {{ __('Institution') }}
                        </label>
                        <select
                            id="institution-dashboard-select"
                            wire:model.live="institutionId"
                            class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-900 focus:border-emerald-500 focus:outline-none"
                        >
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
                    <p class="mt-2 text-sm text-slate-500">{{ __('Ask an institution owner or admin to add you as a member to unlock this dashboard.') }}</p>
                </section>
            @else
                <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm md:p-8">
                    <div class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h2 class="font-heading text-2xl font-bold text-slate-900">{{ $selectedInstitution->name }}</h2>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ __('Type:') }} {{ $selectedInstitution->type?->value ? $translateStatusLabel($selectedInstitution->type->value) : __('Not specified') }}
                            </p>
                            @if($ahliInstitutionEditUrl)
                                <a
                                    href="{{ $ahliInstitutionEditUrl }}"
                                    class="mt-3 inline-flex items-center rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100"
                                >
                                    {{ __('Edit Institution') }}
                                </a>
                            @endif
                        </div>

                        <div class="w-full rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3 md:max-w-xs">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Majlis') }}</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['events_count'] }}</p>
                            <p class="mt-1 text-[11px] text-slate-500">{{ __('Public active: :count', ['count' => $stats['public_events_count']]) }}</p>
                            <p class="text-[11px] text-slate-500">{{ __('Internal / hidden: :count', ['count' => $stats['internal_events_count']]) }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm md:p-8">
                    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                        <div>
                            <h3 id="institution-events" class="font-heading text-2xl font-bold text-slate-900">{{ __('Event List') }}</h3>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ __('Filter the event list by title, status, visibility, or sort order so urgent work is easier to spot.') }}
                            </p>
                        </div>

                        <div class="flex flex-col items-start gap-3 md:items-end">
                            @if($advancedCreateUrl)
                                <a
                                    href="{{ $advancedCreateUrl }}"
                                    wire:navigate
                                    class="inline-flex items-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100"
                                >
                                    {{ __('Create Advanced Program') }}
                                </a>
                            @endif

                            <p class="text-sm font-medium text-slate-500">
                                {{ __('Showing :count of :total events', ['count' => $events->count(), 'total' => $events->total()]) }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                        {{ __('This dashboard shows all institution events, including draft, hidden, unlisted, and inactive records. Public institution pages only show events that are public + active.') }}
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                        <div class="xl:col-span-2">
                            <label for="institution-event-search" class="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">
                                {{ __('Search') }}
                            </label>
                            <input
                                id="institution-event-search"
                                type="text"
                                wire:model.live.debounce.300ms="eventSearch"
                                placeholder="{{ __('Search by event title or venue') }}"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none"
                            >
                        </div>

                        <div>
                            <label for="institution-event-status" class="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">
                                {{ __('Status') }}
                            </label>
                            <select
                                id="institution-event-status"
                                wire:model.live="eventStatus"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none"
                            >
                                @foreach($eventStatusOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="institution-event-visibility" class="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">
                                {{ __('Visibility') }}
                            </label>
                            <select
                                id="institution-event-visibility"
                                wire:model.live="eventVisibility"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none"
                            >
                                @foreach($eventVisibilityOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4 xl:grid-cols-2">
                            <div class="col-span-2 xl:col-span-1">
                                <label for="institution-event-sort" class="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">
                                    {{ __('Sort by') }}
                                </label>
                                <select
                                    id="institution-event-sort"
                                    wire:model.live="eventSort"
                                    class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none"
                                >
                                    @foreach($eventSortOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-span-2 xl:col-span-1">
                                <label for="institution-event-per-page" class="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">
                                    {{ __('Per page') }}
                                </label>
                                <select
                                    id="institution-event-per-page"
                                    wire:model.live="eventPerPage"
                                    class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none"
                                >
                                    @foreach([8, 15, 25] as $perPage)
                                        <option value="{{ $perPage }}">{{ $perPage }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div wire:loading.delay.short wire:target="institutionId,eventSearch,eventStatus,eventVisibility,eventSort,eventPerPage" class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <div class="grid grid-cols-7 gap-3 border-b border-slate-100 px-4 py-3">
                            @for($column = 0; $column < 7; $column++)
                                <div class="h-4 animate-pulse rounded bg-slate-200"></div>
                            @endfor
                        </div>

                        <div class="space-y-3 px-4 py-4">
                            @for($row = 0; $row < 6; $row++)
                                <div class="grid grid-cols-7 gap-3">
                                    @for($column = 0; $column < 7; $column++)
                                        <div class="h-8 animate-pulse rounded-xl bg-slate-100"></div>
                                    @endfor
                                </div>
                            @endfor
                        </div>
                    </div>

                    <div wire:loading.remove wire:target="institutionId,eventSearch,eventStatus,eventVisibility,eventSort,eventPerPage">
                    @if($events->isEmpty())
                        <div class="mt-5 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                            <p class="text-base font-semibold text-slate-700">{{ __('No events match the current filters.') }}</p>
                        </div>
                    @else
                        <div class="mt-5 overflow-x-auto">
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
                                            $isAwaitingApproval = $statusValue === 'pending';
                                            $visibilityValue = $event->visibility?->value ?? (string) $event->visibility;
                                            $isPublicListed = $event->is_active
                                                && in_array($statusValue, \App\Models\Event::PUBLIC_STATUSES, true)
                                                && $visibilityValue === \App\Enums\EventVisibility::Public->value;
                                            $canViewEvent = auth()->user()?->can('view', $event) ?? false;
                                            $canEditEvent = auth()->user()?->can('update', $event) ?? false;
                                            $canViewEventRegistrations = $canViewEvent
                                                && \App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager::canViewForRecord(
                                                    $event,
                                                    \App\Filament\Ahli\Resources\Events\Pages\ViewEvent::class
                                                );
                                            $ahliEventEditUrl = $canEditEvent
                                                ? \App\Filament\Ahli\Resources\Events\EventResource::getUrl('edit', ['record' => $event], panel: 'ahli')
                                                : null;
                                            $createChildEventUrl = $event->isParentProgram()
                                                ? route('submit-event.create', ['parent' => $event->id])
                                                : null;
                                            $ahliEventRegistrationsUrl = $canViewEventRegistrations
                                                ? \App\Filament\Ahli\Resources\Events\EventResource::getUrl('view', ['record' => $event, 'relation' => 'registrations'], panel: 'ahli')
                                                : null;
                                        @endphp
                                        <tr
                                            wire:key="institution-event-{{ $event->id }}"
                                            data-event-status="{{ $isAwaitingApproval ? 'pending-attention' : $statusValue }}"
                                            class="{{ $isAwaitingApproval ? 'bg-amber-50/80' : '' }}"
                                        >
                                            <td class="py-4 pr-4 {{ $isAwaitingApproval ? 'border-l-4 border-amber-400 pl-4' : '' }}">
                                                <a
                                                    href="{{ route('events.show', $event) }}"
                                                    wire:navigate
                                                    class="font-semibold {{ $isAwaitingApproval ? 'text-amber-950 hover:text-amber-800' : 'text-slate-900 hover:text-emerald-700' }}"
                                                >
                                                    {{ $event->title }}
                                                </a>
                                                @if($isAwaitingApproval)
                                                    <div class="mt-2">
                                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-semibold text-amber-900 ring-1 ring-amber-300">
                                                            {{ __('Pending Approval') }}
                                                        </span>
                                                    </div>
                                                @endif
                                                @if($ahliEventEditUrl)
                                                    <div class="mt-1">
                                                        <a href="{{ $ahliEventEditUrl }}" class="text-xs font-semibold text-emerald-700 hover:underline">
                                                            {{ $isAwaitingApproval ? __('Review') : __('Edit') }}
                                                        </a>
                                                    </div>
                                                @endif
                                                @if($createChildEventUrl)
                                                    <div class="mt-1">
                                                        <a href="{{ $createChildEventUrl }}" wire:navigate class="text-xs font-semibold text-indigo-700 hover:underline">
                                                            {{ __('Add Child Event') }}
                                                        </a>
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="py-4 pr-4">{{ $event->starts_at ? \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($event->starts_at, 'd M Y, h:i A') : __('TBC') }}</td>
                                            <td class="py-4 pr-4">{{ $event->venue?->name ?? __('Online / TBD') }}</td>
                                            <td class="py-4 pr-4">
                                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $isAwaitingApproval ? 'bg-amber-100 text-amber-900 ring-1 ring-amber-300' : 'bg-slate-100 text-slate-700' }}">
                                                    {{ $translateStatusLabel($statusValue) }}
                                                </span>
                                            </td>
                                            <td class="py-4 pr-4">
                                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $visibilityValue === \App\Enums\EventVisibility::Public->value ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                                    {{ match ($visibilityValue) {
                                                        \App\Enums\EventVisibility::Public->value => __('Public'),
                                                        \App\Enums\EventVisibility::Unlisted->value => __('Unlisted'),
                                                        default => __('Hidden'),
                                                    } }}
                                                </span>
                                            </td>
                                            <td class="py-4 pr-4">
                                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $isPublicListed ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">
                                                    {{ $isPublicListed ? __('Visible on public page') : __('Internal only') }}
                                                </span>
                                            </td>
                                            <td class="py-4 font-semibold text-slate-900">
                                                @if($ahliEventRegistrationsUrl)
                                                    <a href="{{ $ahliEventRegistrationsUrl }}" class="text-emerald-700 hover:underline">
                                                        {{ $event->registrations_count }}
                                                    </a>
                                                @else
                                                    {{ $event->registrations_count }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6">
                            {{ $events->links(data: ['scrollTo' => '#institution-events']) }}
                        </div>
                    @endif
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm md:p-8">
                    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                        <div>
                            <h3 id="institution-members" class="font-heading text-2xl font-bold text-slate-900">{{ __('Members & Roles') }}</h3>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ __('Keep institution access up to date and assign the right Ahli roles for each member.') }}
                            </p>
                        </div>

                        <p class="text-sm font-medium text-slate-500">{{ __('Members') }}: {{ $members->total() }}</p>
                    </div>

                    @if($canManageMembers)
                        <form wire:submit="addMember" class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_auto] lg:items-end">
                                <div>
                                    <label for="institution-member-email" class="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">
                                        {{ __('Email address') }}
                                    </label>
                                    <input
                                        id="institution-member-email"
                                        type="email"
                                        wire:model="newMemberEmail"
                                        placeholder="{{ __('Existing user email') }}"
                                        class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none"
                                    >
                                    @error('newMemberEmail')
                                        <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="institution-member-roles" class="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">
                                        {{ __('Roles') }}
                                    </label>
                                    <select
                                        id="institution-member-roles"
                                        wire:model="newMemberRoleId"
                                        class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none"
                                    >
                                        <option value="">{{ __('Select a role') }}</option>
                                        @foreach($institutionRoleOptions as $roleId => $roleName)
                                            <option value="{{ $roleId }}">{{ $translateRoleLabel($roleName) }}</option>
                                        @endforeach
                                    </select>
                                    @error('newMemberRoleId')
                                        <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <button
                                    type="submit"
                                    class="inline-flex h-11 items-center justify-center rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white transition hover:bg-emerald-700"
                                >
                                    {{ __('Add Member') }}
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            {{ __('Only institution owners and admins can add, remove, or update member roles from this dashboard.') }}
                        </div>
                    @endif

                    @if($members->isEmpty())
                        <div class="mt-5 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                            <p class="text-base font-semibold text-slate-700">{{ __('No institution members found yet.') }}</p>
                        </div>
                    @else
                        <div class="mt-5 overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100">
                                <thead>
                                    <tr class="text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                        <th class="pb-3 pr-4">{{ __('Name') }}</th>
                                        <th class="pb-3 pr-4">{{ __('Email address') }}</th>
                                        <th class="pb-3 pr-4">{{ __('Roles') }}</th>
                                        @if($canManageMembers)
                                            <th class="pb-3">{{ __('Actions') }}</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                                    @foreach($members as $member)
                                        @php
                                            $roleNames = $memberRoleMap[$member->id] ?? [];
                                            $isEditingMember = $canManageMembers && $this->editingMemberId === $member->id;
                                            $isProtectedOwner = in_array('owner', $roleNames, true);
                                        @endphp
                                        <tr wire:key="institution-member-{{ $member->id }}">
                                            <td class="py-4 pr-4">
                                                <div class="font-semibold text-slate-900">{{ $member->name }}</div>
                                            </td>
                                            <td class="py-4 pr-4">{{ $member->email }}</td>
                                            <td class="py-4 pr-4">
                                                @if($isEditingMember)
                                                    <div class="max-w-sm">
                                                        <select
                                                            wire:model="editingMemberRoleId"
                                                            class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none"
                                                        >
                                                            <option value="">{{ __('Select a role') }}</option>
                                                            @foreach($institutionRoleOptions as $roleId => $roleName)
                                                                <option value="{{ $roleId }}">{{ $translateRoleLabel($roleName) }}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('editingMemberRoleId')
                                                            <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                                                        @enderror
                                                    </div>
                                                @elseif($roleNames !== [])
                                                    <div class="flex flex-wrap gap-2">
                                                        @foreach($roleNames as $roleName)
                                                            <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                                                {{ $translateRoleLabel($roleName) }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <span class="text-slate-400">{{ __('No roles assigned') }}</span>
                                                @endif
                                            </td>
                                            @if($canManageMembers)
                                                <td class="py-4 align-top">
                                                    <div class="flex flex-wrap gap-2">
                                                        @if($isEditingMember)
                                                            <button
                                                                type="button"
                                                                wire:click="saveMemberRoles"
                                                                class="inline-flex items-center rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700"
                                                            >
                                                                {{ __('Save Roles') }}
                                                            </button>
                                                            <button
                                                                type="button"
                                                                wire:click="cancelEditingMemberRoles"
                                                                class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                                            >
                                                                {{ __('Cancel') }}
                                                            </button>
                                                        @else
                                                            <button
                                                                type="button"
                                                                wire:click="startEditingMemberRoles('{{ $member->id }}')"
                                                                class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                                            >
                                                                {{ __('Edit Roles') }}
                                                            </button>
                                                            @if(!$isProtectedOwner)
                                                                <button
                                                                    type="button"
                                                                    wire:click="removeMember('{{ $member->id }}')"
                                                                    wire:confirm="{{ __('Remove this member from the institution?') }}"
                                                                    class="inline-flex items-center rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100"
                                                                >
                                                                    {{ __('Remove') }}
                                                                </button>
                                                            @else
                                                                <span class="inline-flex items-center rounded-xl border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800">
                                                                    {{ __('Owner cannot be removed') }}
                                                                </span>
                                                            @endif
                                                        @endif
                                                    </div>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6">
                            {{ $members->links(data: ['scrollTo' => '#institution-members']) }}
                        </div>
                    @endif
                </section>
            @endif
        </div>
    </div>
</div>
