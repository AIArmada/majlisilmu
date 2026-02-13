@section('title', __('Manage Event Schedule') . ' - ' . config('app.name'))

<div class="bg-slate-50 min-h-screen py-12 pb-24">
    <div class="container mx-auto px-6 lg:px-12">
        <div class="mx-auto max-w-6xl space-y-6">
            <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm md:p-8">
                <h1 class="font-heading text-3xl font-bold text-slate-900">{{ __('Manage Schedule') }}</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $event->title }}</p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" wire:click="pauseSeries" class="rounded-lg bg-amber-500 px-4 py-2 text-xs font-semibold text-white">{{ __('Pause Series') }}</button>
                    <button type="button" wire:click="resumeSeries" class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white">{{ __('Resume Series') }}</button>
                    <button type="button" wire:click="cancelSeries" class="rounded-lg bg-rose-600 px-4 py-2 text-xs font-semibold text-white">{{ __('Cancel Series') }}</button>
                    <button type="button" wire:click="regenerateRecurring" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700">{{ __('Regenerate Recurrence') }}</button>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm md:p-8 space-y-4">
                <h2 class="text-xl font-bold text-slate-900">{{ __('Add Manual Session') }}</h2>
                <div class="grid gap-4 md:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('Starts At') }}</label>
                        <input type="datetime-local" wire:model.defer="newSession.starts_at" class="h-11 w-full rounded-lg border border-slate-200 px-3" />
                        @error('newSession.starts_at')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('Ends At') }}</label>
                        <input type="datetime-local" wire:model.defer="newSession.ends_at" class="h-11 w-full rounded-lg border border-slate-200 px-3" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('Status') }}</label>
                        <select wire:model.defer="newSession.status" class="h-11 w-full rounded-lg border border-slate-200 px-3">
                            <option value="scheduled">{{ __('Scheduled') }}</option>
                            <option value="paused">{{ __('Paused') }}</option>
                            <option value="cancelled">{{ __('Cancelled') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('Capacity') }}</label>
                        <input type="number" min="1" wire:model.defer="newSession.capacity" class="h-11 w-full rounded-lg border border-slate-200 px-3" />
                    </div>
                </div>
                <div>
                    <button type="button" wire:click="addSession" class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white">{{ __('Add Session') }}</button>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm md:p-8">
                <h2 class="text-xl font-bold text-slate-900">{{ __('Sessions') }}</h2>

                <div class="mt-4 space-y-3">
                    @forelse($event->sessions as $session)
                        <div wire:key="schedule-session-{{ $session->id }}" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="grid gap-3 md:grid-cols-5">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('Starts At') }}</label>
                                    <input type="datetime-local" wire:model.defer="editSessions.{{ $session->id }}.starts_at" class="h-10 w-full rounded-lg border border-slate-200 px-2" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('Ends At') }}</label>
                                    <input type="datetime-local" wire:model.defer="editSessions.{{ $session->id }}.ends_at" class="h-10 w-full rounded-lg border border-slate-200 px-2" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('Status') }}</label>
                                    <select wire:model.defer="editSessions.{{ $session->id }}.status" class="h-10 w-full rounded-lg border border-slate-200 px-2">
                                        <option value="scheduled">{{ __('Scheduled') }}</option>
                                        <option value="paused">{{ __('Paused') }}</option>
                                        <option value="cancelled">{{ __('Cancelled') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('Capacity') }}</label>
                                    <input type="number" min="1" wire:model.defer="editSessions.{{ $session->id }}.capacity" class="h-10 w-full rounded-lg border border-slate-200 px-2" />
                                </div>
                                <div class="flex items-end gap-2">
                                    <button type="button" wire:click="saveSession('{{ $session->id }}')" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white">{{ __('Save') }}</button>
                                    <button type="button" wire:click="cancelSession('{{ $session->id }}')" class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700">{{ __('Cancel Session') }}</button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="mt-3 text-sm text-slate-500">{{ __('No sessions yet.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
