<x-filament-panels::page>
    @php($user = $this->getRecord())

    <div class="space-y-6">
        <x-filament::section heading="Account">
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <div class="text-sm text-gray-500">Name</div>
                    <div class="font-medium text-gray-950 dark:text-white">{{ $user->name }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Email</div>
                    <div class="font-medium text-gray-950 dark:text-white">{{ $user->email }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Phone</div>
                    <div class="font-medium text-gray-950 dark:text-white">{{ $user->phone ?: '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Timezone</div>
                    <div class="font-medium text-gray-950 dark:text-white">{{ $user->timezone ?: '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Email Verified At</div>
                    <div class="font-medium text-gray-950 dark:text-white">{{ $user->email_verified_at?->format('d M Y H:i') ?: '-' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Phone Verified At</div>
                    <div class="font-medium text-gray-950 dark:text-white">{{ $user->phone_verified_at?->format('d M Y H:i') ?: '-' }}</div>
                </div>
                <div class="md:col-span-3">
                    <div class="text-sm text-gray-500">Global Roles</div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @forelse ($this->roleNames() as $roleName)
                            <x-filament::badge color="primary">{{ $roleName }}</x-filament::badge>
                        @empty
                            <span class="font-medium text-gray-950 dark:text-white">-</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section heading="Summary">
            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <div class="text-sm text-gray-500">Saved Events</div>
                    <div class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $user->savedEvents->count() }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Interested Events</div>
                    <div class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $user->interestedEvents->count() }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Going Events</div>
                    <div class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $user->goingEvents->count() }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Check-ins</div>
                    <div class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $user->eventCheckins->count() }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Registrations</div>
                    <div class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $user->registrations->count() }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Submitted Events</div>
                    <div class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $user->eventSubmissions->count() }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Memberships</div>
                    <div class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $user->institutions->count() + $user->speakers->count() + $user->memberEvents->count() + $user->references->count() }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Saved Searches</div>
                    <div class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $user->savedSearches->count() }}</div>
                </div>
            </div>
        </x-filament::section>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-filament::section heading="Interested Events">
                <div class="space-y-4">
                    @forelse ($user->interestedEvents as $event)
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="flex items-start justify-between gap-3">
                                <a href="{{ $this->eventUrl($event) }}" class="font-medium text-primary-600 hover:underline">
                                    {{ $event->title }}
                                </a>
                                <x-filament::badge :color="$this->eventStatusBadgeColor($event->status)">{{ $this->humanLabel($event->status) }}</x-filament::badge>
                            </div>
                            <div class="mt-3 text-sm text-gray-500">
                                Starts: {{ $event->starts_at?->format('d M Y H:i') ?: '-' }}
                            </div>
                            <div class="text-sm text-gray-500">
                                Institution: {{ $event->institution?->name ?: '-' }}
                            </div>
                            <div class="text-sm text-gray-500">
                                Venue: {{ $event->venue?->name ?: '-' }}
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">No interested events.</div>
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section heading="Going Events">
                <div class="space-y-4">
                    @forelse ($user->goingEvents as $event)
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="flex items-start justify-between gap-3">
                                <a href="{{ $this->eventUrl($event) }}" class="font-medium text-primary-600 hover:underline">
                                    {{ $event->title }}
                                </a>
                                <x-filament::badge :color="$this->eventStatusBadgeColor($event->status)">{{ $this->humanLabel($event->status) }}</x-filament::badge>
                            </div>
                            <div class="mt-3 text-sm text-gray-500">
                                Starts: {{ $event->starts_at?->format('d M Y H:i') ?: '-' }}
                            </div>
                            <div class="text-sm text-gray-500">
                                Institution: {{ $event->institution?->name ?: '-' }}
                            </div>
                            <div class="text-sm text-gray-500">
                                Venue: {{ $event->venue?->name ?: '-' }}
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">No going events.</div>
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section heading="Saved Events">
                <div class="space-y-4">
                    @forelse ($user->savedEvents as $event)
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="flex items-start justify-between gap-3">
                                <a href="{{ $this->eventUrl($event) }}" class="font-medium text-primary-600 hover:underline">
                                    {{ $event->title }}
                                </a>
                                <x-filament::badge :color="$this->eventStatusBadgeColor($event->status)">{{ $this->humanLabel($event->status) }}</x-filament::badge>
                            </div>
                            <div class="mt-3 text-sm text-gray-500">
                                Starts: {{ $event->starts_at?->format('d M Y H:i') ?: '-' }}
                            </div>
                            <div class="text-sm text-gray-500">
                                Institution: {{ $event->institution?->name ?: '-' }}
                            </div>
                            <div class="text-sm text-gray-500">
                                Venue: {{ $event->venue?->name ?: '-' }}
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">No saved events.</div>
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section heading="Check-ins">
                <div class="space-y-4">
                    @forelse ($user->eventCheckins as $checkin)
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="font-medium text-gray-950 dark:text-white">
                                <a href="{{ $this->eventUrl($checkin->event) }}" class="text-primary-600 hover:underline">
                                    {{ $checkin->event?->title ?: '-' }}
                                </a>
                            </div>
                            <div class="mt-3 text-sm text-gray-500">
                                Checked In At: {{ $checkin->checked_in_at?->format('d M Y H:i') ?: '-' }}
                            </div>
                            <div class="text-sm text-gray-500">
                                Method: {{ $this->humanLabel($checkin->method) }}
                            </div>
                            <div class="text-sm text-gray-500">
                                Verified By: {{ $checkin->verifiedBy?->name ?: '-' }}
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">No check-ins.</div>
                    @endforelse
                </div>
            </x-filament::section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-filament::section heading="Event Registrations">
                <div class="space-y-4">
                    @forelse ($user->registrations as $registration)
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="flex items-start justify-between gap-3">
                                <a href="{{ $this->eventUrl($registration->event) }}" class="font-medium text-primary-600 hover:underline">
                                    {{ $registration->event?->title ?: '-' }}
                                </a>
                                <x-filament::badge :color="$this->registrationStatusBadgeColor($registration->status)">{{ $this->humanLabel($registration->status) }}</x-filament::badge>
                            </div>
                            <div class="mt-3 text-sm text-gray-500">
                                Event: {{ $registration->event?->title ?: '-' }}
                            </div>
                            <div class="text-sm text-gray-500">
                                Registered At: {{ $registration->created_at?->format('d M Y H:i') ?: '-' }}
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">No event registrations.</div>
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section heading="Submitted Events">
                <div class="space-y-4">
                    @forelse ($user->eventSubmissions as $submission)
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="flex items-start justify-between gap-3">
                                <a href="{{ $this->eventUrl($submission->event) }}" class="font-medium text-primary-600 hover:underline">
                                    {{ $submission->event?->title ?: '-' }}
                                </a>
                                <x-filament::badge :color="$this->eventStatusBadgeColor($submission->event?->status)">{{ $this->humanLabel($submission->event?->status) }}</x-filament::badge>
                            </div>
                            <div class="mt-3 text-sm text-gray-500">
                                Submitted At: {{ $submission->created_at?->format('d M Y H:i') ?: '-' }}
                            </div>
                            <div class="text-sm text-gray-500">
                                Notes: {{ $submission->notes ?: '-' }}
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">No submitted events.</div>
                    @endforelse
                </div>
            </x-filament::section>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <x-filament::section heading="Following Institutions">
                <div class="space-y-4">
                    @forelse ($user->followingInstitutions as $institution)
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="flex items-start justify-between gap-3">
                                <a href="{{ $this->institutionUrl($institution) }}" class="font-medium text-primary-600 hover:underline">
                                    {{ $institution->name }}
                                </a>
                                <x-filament::badge :color="$this->entityStatusBadgeColor($institution->status)">{{ $this->humanLabel($institution->status) }}</x-filament::badge>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">Not following any institutions.</div>
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section heading="Following Speakers">
                <div class="space-y-4">
                    @forelse ($user->followingSpeakers as $speaker)
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="flex items-start justify-between gap-3">
                                <a href="{{ $this->speakerUrl($speaker) }}" class="font-medium text-primary-600 hover:underline">
                                    {{ $speaker->name }}
                                </a>
                                <x-filament::badge :color="$this->entityStatusBadgeColor($speaker->status)">{{ $this->humanLabel($speaker->status) }}</x-filament::badge>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">Not following any speakers.</div>
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section heading="Following References">
                <div class="space-y-4">
                    @forelse ($user->followingReferences as $reference)
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="flex items-start justify-between gap-3">
                                <a href="{{ $this->referenceUrl($reference) }}" class="font-medium text-primary-600 hover:underline">
                                    {{ $reference->title }}
                                </a>
                                <x-filament::badge :color="$this->entityStatusBadgeColor($reference->status)">{{ $this->humanLabel($reference->status) }}</x-filament::badge>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">Not following any references.</div>
                    @endforelse
                </div>
            </x-filament::section>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <x-filament::section heading="Institution Memberships">
                <div class="space-y-4">
                    @forelse ($user->institutions as $institution)
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="flex items-start justify-between gap-3">
                                <a href="{{ $this->institutionUrl($institution) }}" class="font-medium text-primary-600 hover:underline">
                                    {{ $institution->name }}
                                </a>
                                <x-filament::badge :color="$this->entityStatusBadgeColor($institution->status)">{{ $this->humanLabel($institution->status) }}</x-filament::badge>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">No institution memberships.</div>
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section heading="Speaker Memberships">
                <div class="space-y-4">
                    @forelse ($user->speakers as $speaker)
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="flex items-start justify-between gap-3">
                                <a href="{{ $this->speakerUrl($speaker) }}" class="font-medium text-primary-600 hover:underline">
                                    {{ $speaker->name }}
                                </a>
                                <x-filament::badge :color="$this->entityStatusBadgeColor($speaker->status)">{{ $this->humanLabel($speaker->status) }}</x-filament::badge>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">No speaker memberships.</div>
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section heading="Event Memberships">
                <div class="space-y-4">
                    @forelse ($user->memberEvents as $event)
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="flex items-start justify-between gap-3">
                                <a href="{{ $this->eventUrl($event) }}" class="font-medium text-primary-600 hover:underline">
                                    {{ $event->title }}
                                </a>
                                <x-filament::badge :color="$this->eventStatusBadgeColor($event->status)">{{ $this->humanLabel($event->status) }}</x-filament::badge>
                            </div>
                            <div class="mt-3 text-sm text-gray-500">
                                Starts: {{ $event->starts_at?->format('d M Y H:i') ?: '-' }}
                            </div>
                            <div class="text-sm text-gray-500">
                                Institution: {{ $event->institution?->name ?: '-' }}
                            </div>
                            <div class="text-sm text-gray-500">
                                Venue: {{ $event->venue?->name ?: '-' }}
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">No event memberships.</div>
                    @endforelse
                </div>
            </x-filament::section>
        </div>

        <x-filament::section heading="Saved Searches">
            <div class="space-y-4">
                @forelse ($user->savedSearches as $savedSearch)
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <div class="flex items-start justify-between gap-3">
                            <div class="font-medium text-gray-950 dark:text-white">{{ $savedSearch->name }}</div>
                            <x-filament::badge color="gray">{{ $this->formattedSearchNotify($savedSearch->notify) }}</x-filament::badge>
                        </div>
                        <div class="mt-3 text-sm text-gray-500">
                            Query: {{ $savedSearch->query ?: '-' }}
                        </div>
                        <div class="text-sm text-gray-500">
                            Filters: {{ $this->formatSavedSearchFilters(is_array($savedSearch->filters) ? $savedSearch->filters : null) }}
                        </div>
                        <div class="text-sm text-gray-500">
                            Created At: {{ $savedSearch->created_at?->format('d M Y H:i') ?: '-' }}
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-gray-500">No saved searches.</div>
                @endforelse
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
