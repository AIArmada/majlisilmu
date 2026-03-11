@section('title', __('Create Advanced Event') . ' - ' . config('app.name'))

<div class="bg-slate-50 min-h-screen py-12 pb-24">
    <div class="container mx-auto px-6 lg:px-12">
        <div class="mx-auto max-w-6xl space-y-6">
            <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm md:p-8">
                <h1 class="font-heading text-3xl font-bold text-slate-900">{{ __('Create Advanced Event') }}</h1>
                <p class="mt-2 text-sm text-slate-500">{{ __('Create one umbrella program with child events that each have their own title, timing, and public identity.') }}</p>
            </div>

            <form wire:submit="submit" class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm md:p-8 space-y-8">
                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-5 space-y-5">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">{{ __('Parent Program') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ __('This umbrella program will group the child events below and will get its own public overview page once approved.') }}</p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Program Title') }} *</label>
                            <input type="text" wire:model.defer="form.title" class="h-12 w-full rounded-xl border border-slate-200 px-4" />
                            @error('form.title')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Program Description') }}</label>
                            <textarea wire:model.defer="form.description" class="min-h-28 w-full rounded-xl border border-slate-200 px-4 py-3"></textarea>
                            @error('form.description')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Timezone') }}</label>
                            <input type="text" wire:model.defer="form.timezone" class="h-12 w-full rounded-xl border border-slate-200 px-4" />
                            @error('form.timezone')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Visibility') }}</label>
                            <select wire:model.defer="form.visibility" class="h-12 w-full rounded-xl border border-slate-200 px-4">
                                @foreach($visibilityOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Organizer Type') }}</label>
                            <select wire:model.live="form.organizer_type" class="h-12 w-full rounded-xl border border-slate-200 px-4">
                                @if($institutionOptions !== [])
                                    <option value="institution">{{ __('Institution') }}</option>
                                @endif
                                @if($speakerOptions !== [])
                                    <option value="speaker">{{ __('Speaker') }}</option>
                                @endif
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Organizer') }}</label>
                            <select wire:model.defer="form.organizer_id" class="h-12 w-full rounded-xl border border-slate-200 px-4">
                                @foreach(($form['organizer_type'] ?? 'institution') === 'institution' ? $institutionOptions : $speakerOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('form.organizer_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        @if(($form['organizer_type'] ?? 'institution') === 'speaker' && $institutionOptions !== [])
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Location Institution') }}</label>
                                <select wire:model.defer="form.location_institution_id" class="h-12 w-full rounded-xl border border-slate-200 px-4">
                                    <option value="">{{ __('No institution location') }}</option>
                                    @foreach($institutionOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-5 space-y-5">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">{{ __('Shared Defaults') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ __('These values are applied to child events unless you override them individually.') }}</p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Default Event Type') }}</label>
                            <select wire:model.defer="form.default_event_type" class="h-12 w-full rounded-xl border border-slate-200 px-4">
                                @foreach($eventTypeOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Default Format') }}</label>
                            <select wire:model.defer="form.default_event_format" class="h-12 w-full rounded-xl border border-slate-200 px-4">
                                @foreach($eventFormatOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
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
                </div>

                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-5 space-y-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">{{ __('Child Events') }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ __('Each child event is a first-class attendance and discovery unit under the parent program.') }}</p>
                        </div>
                        <button type="button" wire:click="addChild" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">{{ __('Add Child Event') }}</button>
                    </div>

                    @error('form.children')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror

                    <div class="space-y-4">
                        @foreach(($form['children'] ?? []) as $index => $child)
                            <div wire:key="child-event-row-{{ $index }}" class="rounded-2xl border border-slate-200 bg-white p-5 space-y-4">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="text-base font-semibold text-slate-900">{{ __('Child Event #:number', ['number' => $index + 1]) }}</h3>

                                    @if(count($form['children'] ?? []) > 1)
                                        <button type="button" wire:click="removeChild({{ $index }})" class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700">{{ __('Remove') }}</button>
                                    @endif
                                </div>

                                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                    <div class="md:col-span-2 xl:col-span-4">
                                        <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Event Title') }}</label>
                                        <input type="text" wire:model.defer="form.children.{{ $index }}.title" class="h-12 w-full rounded-xl border border-slate-200 px-4" />
                                        @error('form.children.' . $index . '.title')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                                    </div>

                                    <div class="md:col-span-2 xl:col-span-4">
                                        <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Description') }}</label>
                                        <textarea wire:model.defer="form.children.{{ $index }}.description" class="min-h-24 w-full rounded-xl border border-slate-200 px-4 py-3"></textarea>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Starts At') }}</label>
                                        <input type="datetime-local" wire:model.defer="form.children.{{ $index }}.starts_at" class="h-12 w-full rounded-xl border border-slate-200 px-4" />
                                        @error('form.children.' . $index . '.starts_at')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Ends At') }}</label>
                                        <input type="datetime-local" wire:model.defer="form.children.{{ $index }}.ends_at" class="h-12 w-full rounded-xl border border-slate-200 px-4" />
                                        @error('form.children.' . $index . '.ends_at')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Override Type') }}</label>
                                        <select wire:model.defer="form.children.{{ $index }}.event_type" class="h-12 w-full rounded-xl border border-slate-200 px-4">
                                            <option value="">{{ __('Use parent default') }}</option>
                                            @foreach($eventTypeOptions as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Override Format') }}</label>
                                        <select wire:model.defer="form.children.{{ $index }}.event_format" class="h-12 w-full rounded-xl border border-slate-200 px-4">
                                            <option value="">{{ __('Use parent default') }}</option>
                                            @foreach($eventFormatOptions as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('dashboard') }}" wire:navigate class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700">{{ __('Cancel') }}</a>
                    <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white">{{ __('Create Parent Program Draft') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
