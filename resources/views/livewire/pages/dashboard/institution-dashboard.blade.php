@section('title', __('Institution Dashboard') . ' - ' . config('app.name'))

@php
    $institutions = $this->institutions;
    $selectedInstitution = $this->selectedInstitution;
    $members = $this->institutionMembers;
    $memberRoleMap = $this->institutionMemberRoleMap;
    $institutionRoleOptions = $this->institutionRoleOptions;
    $canManageMembers = $this->canManageMembers;
    $canUseSelectedInstitutionForScopedSubmission = $this->canUseSelectedInstitutionForScopedSubmission;
    $translateRoleLabel = static function (string $role): string {
        $label = str($role)->replace('_', ' ')->headline()->toString();
        $translated = __($label);

        return $translated !== $label ? $translated : $label;
    };
    $canEditInstitution = $selectedInstitution !== null && (auth()->user()?->can('update', $selectedInstitution) ?? false);
    $institutionEditUrl = $canEditInstitution
        ? route('contributions.suggest-update', [
            'subjectType' => \App\Enums\ContributionSubjectType::Institution->publicRouteSegment(),
            'subjectId' => $selectedInstitution->slug,
        ])
        : null;
    $ahliInstitutionInvitationsUrl = $selectedInstitution !== null && $canManageMembers
        ? \App\Filament\Ahli\Resources\Institutions\InstitutionResource::getUrl('edit', ['record' => $selectedInstitution, 'relation' => 'member_invitations'], panel: 'ahli')
        : null;
    $institutionSubmitUrl = $selectedInstitution !== null && $canUseSelectedInstitutionForScopedSubmission
        ? route('dashboard.institutions.submit-event', ['institution' => $selectedInstitution->id])
        : null;
@endphp

@include('partials.filament-assets', [
    'scripts' => ['filament/tables'],
])

<div class="min-h-screen bg-slate-50 pt-12">
    <div class="container mx-auto px-6 lg:px-12">
        <div class="mx-auto max-w-6xl space-y-8">
            <div class="flex justify-end" data-testid="institution-dashboard-picker">
                <div class="flex w-full flex-col justify-end gap-3 sm:flex-row sm:items-end sm:justify-end">
                    <div class="w-full sm:max-w-md">
                        <flux:select
                            wire:model.live="institutionId"
                            data-testid="institution-dashboard-select"
                            label="{{ __('Institution') }}"
                            placeholder="{{ __('Select institution') }}"
                            label:class="text-xs font-bold uppercase tracking-wide !text-slate-600"
                            class="h-11 rounded-xl border-slate-300 bg-white !text-slate-900 shadow-xs hover:border-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 [&>option]:text-slate-900"
                        >
                            @foreach($institutions as $institution)
                                <flux:select.option value="{{ $institution->id }}">{{ $institution->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    @if($institutionEditUrl)
                        <a
                            href="{{ $institutionEditUrl }}"
                            wire:navigate
                            class="inline-flex h-11 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100"
                        >
                            {{ __('Edit Institution') }}
                        </a>
                    @endif
                </div>
            </div>

            @if(!$selectedInstitution)
                <section class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center">
                    <p class="text-lg font-semibold text-slate-700">{{ __('You do not have institution access yet.') }}</p>
                    <p class="mt-2 text-sm text-slate-500">{{ __('Ask an institution owner or admin to add you as a member to unlock this dashboard.') }}</p>
                </section>
            @else
                <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h3 id="institution-events" class="font-heading text-2xl font-bold text-slate-900">{{ __('Event List') }}</h3>
                    </div>

                    <div class="flex flex-col items-start gap-3 md:items-end">
                        @if($institutionSubmitUrl)
                            <a
                                href="{{ $institutionSubmitUrl }}"
                                wire:navigate
                                class="inline-flex items-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100"
                            >
                                {{ __('Submit Event') }}
                            </a>
                        @endif
                    </div>
                </div>

                <div class="mt-6 pb-8 sm:pb-10">
                    {{ $this->table }}
                </div>

                @if($canManageMembers)
                    <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm md:p-8">
                        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                            <div>
                                <h3 id="institution-members" class="font-heading text-2xl font-bold text-slate-900">{{ __('Members & Roles') }}</h3>
                            </div>

                            <div class="flex flex-col items-start gap-3 md:items-end">
                                @if($ahliInstitutionInvitationsUrl)
                                    <a
                                        href="{{ $ahliInstitutionInvitationsUrl }}"
                                        class="inline-flex items-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100"
                                    >
                                        {{ __('Manage Invitations') }}
                                    </a>
                                @endif
                            </div>
                        </div>

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
                                                                @if(!$isProtectedOwner)
                                                                    <button
                                                                        type="button"
                                                                        wire:click="startEditingMemberRoles('{{ $member->id }}')"
                                                                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                                                    >
                                                                        {{ __('Edit Roles') }}
                                                                    </button>
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
                                                                        {{ __('Owner role is managed globally') }}
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
            @endif
        </div>
    </div>
</div>
