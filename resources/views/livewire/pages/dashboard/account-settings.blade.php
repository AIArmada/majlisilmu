@section('title', __('Account Settings') . ' - ' . config('app.name'))

@php
    $timezoneOptions = $this->timezoneOptions;
    $user = auth()->user();
@endphp

<div class="min-h-screen bg-slate-50 py-12 pb-32">
    <div class="container mx-auto px-6 lg:px-12">
        <div class="mx-auto max-w-4xl space-y-8">
            <section class="overflow-hidden rounded-3xl border border-slate-200/70 bg-white shadow-sm">
                <div class="border-b border-slate-100 bg-gradient-to-r from-emerald-50 via-white to-white px-6 py-8 md:px-8">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div class="max-w-2xl">
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-600">{{ __('Account Settings') }}</p>
                            <h1 class="mt-3 font-heading text-3xl font-bold text-slate-900">{{ __('Manage your profile details') }}</h1>
                            <p class="mt-3 text-sm leading-6 text-slate-600">
                                {{ __('Update the personal details used across your dashboard, registrations, and time-based event displays.') }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('dashboard.digest-preferences') }}" wire:navigate
                                class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700">
                                {{ __('Digest Preferences') }}
                            </a>
                            <a href="{{ route('dashboard') }}" wire:navigate
                                class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-600">
                                {{ __('Back to Dashboard') }}
                            </a>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-8 md:px-8">
                    @if (session('account_settings_status'))
                        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3 text-sm font-semibold text-emerald-700">
                            {{ session('account_settings_status') }}
                        </div>
                    @endif

                    <form wire:submit="saveAccountSettings" class="space-y-8">
                        <section class="space-y-5">
                            <div>
                                <h2 class="font-heading text-xl font-bold text-slate-900">{{ __('Profile Details') }}</h2>
                                <p class="mt-1 text-sm text-slate-500">{{ __('These details identify you across your account and public interactions.') }}</p>
                            </div>

                            <div class="grid gap-6 md:grid-cols-2">
                                <div class="space-y-2 md:col-span-2">
                                    <label for="account-settings-name" class="text-sm font-semibold text-slate-700">{{ __('Full Name') }}</label>
                                    <input id="account-settings-name" type="text" wire:model="name"
                                        class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none">
                                    @error('name')
                                        <p class="text-sm text-danger-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="space-y-2">
                                    <label for="account-settings-email" class="text-sm font-semibold text-slate-700">{{ __('Email Address') }}</label>
                                    <input id="account-settings-email" type="email" wire:model="email"
                                        class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none">
                                    <p class="text-xs text-slate-500">
                                        {{ $user?->email_verified_at ? __('Verified email address.') : __('If you change this address, email verification will need to be completed again.') }}
                                    </p>
                                    @error('email')
                                        <p class="text-sm text-danger-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="space-y-2">
                                    <label for="account-settings-phone" class="text-sm font-semibold text-slate-700">{{ __('Phone Number') }}</label>
                                    <input id="account-settings-phone" type="text" wire:model="phone"
                                        class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none">
                                    <p class="text-xs text-slate-500">
                                        {{ $user?->phone_verified_at ? __('Verified phone number.') : __('Keep at least one contact method on your account: email or phone.') }}
                                    </p>
                                    @error('phone')
                                        <p class="text-sm text-danger-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </section>

                        <section class="space-y-5">
                            <div>
                                <h2 class="font-heading text-xl font-bold text-slate-900">{{ __('Timezone') }}</h2>
                                <p class="mt-1 text-sm text-slate-500">{{ __('This controls how dates and times are shown to you throughout the application.') }}</p>
                            </div>

                            <div class="space-y-2">
                                <label for="account-settings-timezone" class="text-sm font-semibold text-slate-700">{{ __('Preferred Timezone') }}</label>
                                <select id="account-settings-timezone" wire:model="timezone"
                                    class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none">
                                    <option value="">{{ __('Use browser or application default') }}</option>
                                    @foreach($timezoneOptions as $timezoneValue => $timezoneLabel)
                                        <option value="{{ $timezoneValue }}">{{ $timezoneLabel }}</option>
                                    @endforeach
                                </select>
                                @error('timezone')
                                    <p class="text-sm text-danger-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </section>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                            {{ __('Changes to email or phone reset their verification status until they are confirmed again.') }}
                        </div>

                        <div class="flex justify-end">
                            <button type="submit"
                                class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                                {{ __('Save Account Settings') }}
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</div>
