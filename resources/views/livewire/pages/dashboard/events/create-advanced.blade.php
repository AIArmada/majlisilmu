@section('title', __('Create Advanced Event') . ' - ' . config('app.name'))

<div class="bg-slate-50 min-h-screen py-12 pb-24">
    <div class="container mx-auto px-6 lg:px-12">
        <div class="mx-auto max-w-5xl space-y-6">
            <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm md:p-8">
                <h1 class="font-heading text-3xl font-bold text-slate-900">{{ __('Create Advanced Event') }}</h1>
                <p class="mt-2 text-sm text-slate-500">{{ __('Use this form for multi-day, recurring, and custom chained schedules.') }}</p>
            </div>

            <form wire:submit="submit" class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm md:p-8 space-y-8">
                <div class="grid gap-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Title') }} *</label>
                        <input type="text" wire:model.defer="form.title" class="h-12 w-full rounded-xl border border-slate-200 px-4" />
                        @error('form.title')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Description') }}</label>
                        <textarea wire:model.defer="form.description" class="min-h-28 w-full rounded-xl border border-slate-200 px-4 py-3"></textarea>
                        @error('form.description')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Schedule Kind') }} *</label>
                        <select wire:model.live="form.schedule_kind" class="h-12 w-full rounded-xl border border-slate-200 px-4">
                            <option value="single">{{ __('Single Day') }}</option>
                            <option value="multi_day">{{ __('Multi-day') }}</option>
                            <option value="recurring">{{ __('Recurring') }}</option>
                            <option value="custom_chain">{{ __('Custom Chain') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Timezone') }}</label>
                        <input type="text" wire:model.defer="form.timezone" class="h-12 w-full rounded-xl border border-slate-200 px-4" />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Event Format') }}</label>
                        <select wire:model.defer="form.event_format" class="h-12 w-full rounded-xl border border-slate-200 px-4">
                            <option value="physical">{{ __('Physical') }}</option>
                            <option value="online">{{ __('Online') }}</option>
                            <option value="hybrid">{{ __('Hybrid') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Visibility') }}</label>
                        <select wire:model.defer="form.visibility" class="h-12 w-full rounded-xl border border-slate-200 px-4">
                            <option value="public">{{ __('Public') }}</option>
                            <option value="unlisted">{{ __('Unlisted') }}</option>
                            <option value="private">{{ __('Private') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Registration Mode') }}</label>
                        <select wire:model.defer="form.registration_mode" class="h-12 w-full rounded-xl border border-slate-200 px-4">
                            <option value="event">{{ __('Whole Event') }}</option>
                            <option value="session">{{ __('Per Session') }}</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-3 pt-8">
                        <input id="registration_required" type="checkbox" wire:model.defer="form.registration_required" class="size-4 rounded border-slate-300" />
                        <label for="registration_required" class="text-sm font-medium text-slate-700">{{ __('Registration Required') }}</label>
                    </div>
                </div>

                @if(($form['schedule_kind'] ?? 'single') === 'recurring')
                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-5 space-y-5">
                        <h2 class="text-lg font-bold text-slate-900">{{ __('Recurring Rule') }}</h2>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Frequency') }}</label>
                                <select wire:model.defer="form.recurrence.frequency" class="h-12 w-full rounded-xl border border-slate-200 px-4">
                                    <option value="daily">{{ __('Daily') }}</option>
                                    <option value="weekly">{{ __('Weekly') }}</option>
                                    <option value="monthly">{{ __('Monthly') }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Interval') }}</label>
                                <input type="number" min="1" wire:model.defer="form.recurrence.interval" class="h-12 w-full rounded-xl border border-slate-200 px-4" />
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Start Date') }}</label>
                                <input type="date" wire:model.defer="form.recurrence.start_date" class="h-12 w-full rounded-xl border border-slate-200 px-4" />
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Until Date') }}</label>
                                <input type="date" wire:model.defer="form.recurrence.until_date" class="h-12 w-full rounded-xl border border-slate-200 px-4" />
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Occurrence Count') }}</label>
                                <input type="number" min="1" wire:model.defer="form.recurrence.occurrence_count" class="h-12 w-full rounded-xl border border-slate-200 px-4" />
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Timing Mode') }}</label>
                                <select wire:model.live="form.recurrence.timing_mode" class="h-12 w-full rounded-xl border border-slate-200 px-4">
                                    <option value="absolute">{{ __('Exact Time') }}</option>
                                    <option value="prayer_relative">{{ __('Prayer Time') }}</option>
                                </select>
                            </div>

                            @if(($form['recurrence']['timing_mode'] ?? 'absolute') === 'absolute')
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Start Time') }}</label>
                                    <input type="time" wire:model.defer="form.recurrence.starts_time" class="h-12 w-full rounded-xl border border-slate-200 px-4" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('End Time') }}</label>
                                    <input type="time" wire:model.defer="form.recurrence.ends_time" class="h-12 w-full rounded-xl border border-slate-200 px-4" />
                                </div>
                            @else
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Prayer Reference') }}</label>
                                    <input type="text" wire:model.defer="form.recurrence.prayer_reference" placeholder="maghrib / isha / fajr" class="h-12 w-full rounded-xl border border-slate-200 px-4" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Prayer Offset') }}</label>
                                    <input type="text" wire:model.defer="form.recurrence.prayer_offset" placeholder="immediately / after_15" class="h-12 w-full rounded-xl border border-slate-200 px-4" />
                                </div>
                            @endif

                            @if(($form['recurrence']['frequency'] ?? 'weekly') === 'weekly')
                                <div class="md:col-span-2">
                                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Weekdays (0=Sun .. 6=Sat)') }}</label>
                                    <input type="text" wire:model.defer="form.recurrence.by_weekdays.0" placeholder="Example: 5 for Friday" class="h-12 w-full rounded-xl border border-slate-200 px-4" />
                                </div>
                            @endif

                            @if(($form['recurrence']['frequency'] ?? 'weekly') === 'monthly')
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Month Day') }}</label>
                                    <input type="number" min="1" max="31" wire:model.defer="form.recurrence.by_month_day" class="h-12 w-full rounded-xl border border-slate-200 px-4" />
                                </div>
                            @endif
                        </div>

                        @error('form.recurrence.until_date')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
                        @error('form.recurrence.occurrence_count')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                @else
                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-5 space-y-5">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-bold text-slate-900">{{ __('Sessions') }}</h2>
                            <button type="button" wire:click="addSession" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">{{ __('Add Session') }}</button>
                        </div>

                        <div class="space-y-4">
                            @foreach(($form['sessions'] ?? []) as $index => $session)
                                <div wire:key="session-row-{{ $index }}" class="rounded-xl border border-slate-200 bg-white p-4 grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('Starts At') }}</label>
                                        <input type="datetime-local" wire:model.defer="form.sessions.{{ $index }}.starts_at" class="h-11 w-full rounded-lg border border-slate-200 px-3" />
                                        @error('form.sessions.' . $index . '.starts_at')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('Ends At') }}</label>
                                        <input type="datetime-local" wire:model.defer="form.sessions.{{ $index }}.ends_at" class="h-11 w-full rounded-lg border border-slate-200 px-3" />
                                        @error('form.sessions.' . $index . '.ends_at')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('Status') }}</label>
                                        <select wire:model.defer="form.sessions.{{ $index }}.status" class="h-11 w-full rounded-lg border border-slate-200 px-3">
                                            <option value="scheduled">{{ __('Scheduled') }}</option>
                                            <option value="paused">{{ __('Paused') }}</option>
                                            <option value="cancelled">{{ __('Cancelled') }}</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('Capacity (optional)') }}</label>
                                        <input type="number" min="1" wire:model.defer="form.sessions.{{ $index }}.capacity" class="h-11 w-full rounded-lg border border-slate-200 px-3" />
                                    </div>

                                    <div class="md:col-span-2 flex justify-end">
                                        <button type="button" wire:click="removeSession({{ $index }})" class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700">{{ __('Remove') }}</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="flex justify-end gap-3">
                    <a href="{{ route('dashboard') }}" wire:navigate class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700">{{ __('Cancel') }}</a>
                    <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white">{{ __('Create Advanced Event') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
