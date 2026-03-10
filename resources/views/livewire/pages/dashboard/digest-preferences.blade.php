@section('title', __('Digest Preferences') . ' - ' . config('app.name'))

@php
    $digestFrequencyOptions = $this->digestFrequencyOptions;
    $digestChannelOptions = $this->digestChannelOptions;
@endphp

<div class="min-h-screen bg-slate-50 py-12 pb-32">
    <div class="container mx-auto px-6 lg:px-12">
        <div class="mx-auto max-w-4xl space-y-8">
            <section class="overflow-hidden rounded-3xl border border-slate-200/70 bg-white shadow-sm">
                <div class="border-b border-slate-100 bg-gradient-to-r from-emerald-50 via-white to-white px-6 py-8 md:px-8">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div class="max-w-2xl">
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-600">{{ __('Digest Preferences') }}</p>
                            <h1 class="mt-3 font-heading text-3xl font-bold text-slate-900">{{ __('Saved search delivery settings') }}</h1>
                            <p class="mt-3 text-sm leading-6 text-slate-600">
                                {{ __('Control how saved search digests are delivered to you. Your saved searches stay on their own page, while this page manages notification frequency and channels.') }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('saved-searches.index') }}" wire:navigate
                                class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700">
                                {{ __('Saved Searches') }}
                            </a>
                            <a href="{{ route('dashboard') }}" wire:navigate
                                class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-600">
                                {{ __('Back to Dashboard') }}
                            </a>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-8 md:px-8">
                    @if (session('digest_preferences_status'))
                        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3 text-sm font-semibold text-emerald-700">
                            {{ session('digest_preferences_status') }}
                        </div>
                    @endif

                    <form wire:submit="saveDigestNotificationPreferences" class="space-y-6">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <label class="flex cursor-pointer items-start gap-3">
                                <input type="checkbox" wire:model="digestNotificationsEnabled"
                                    class="mt-1 size-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                <span>
                                    <span class="block text-sm font-semibold text-slate-800">{{ __('Enable saved search digest notifications') }}</span>
                                    <span class="mt-1 block text-xs text-slate-500">{{ __('If disabled, digest notifications are fully turned off.') }}</span>
                                </span>
                            </label>
                        </div>

                        <div class="grid gap-6 md:grid-cols-2">
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

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                            {{ __('Digests follow your existing saved searches. This page only controls how often they arrive and where they are sent.') }}
                        </div>

                        <div class="flex justify-end">
                            <button type="submit"
                                class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                                {{ __('Save Preferences') }}
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</div>
