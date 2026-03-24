@section('title', __('Submit Institution') . ' - ' . config('app.name'))

@include('partials.filament-assets', [
    'scripts' => ['filament/support', 'filament/schemas', 'filament/forms', 'filament/actions'],
])

<div class="min-h-screen bg-slate-50 py-10 pb-28">
    <div class="container mx-auto max-w-7xl px-6 lg:px-8">
        <section class="rounded-4xl border border-slate-200 bg-white p-6 shadow-sm md:p-8 lg:p-10">
            <p class="text-xs font-bold uppercase tracking-[0.22em] text-emerald-600">{{ __('Community Contribution') }}</p>
            <h1 class="mt-3 font-heading text-3xl font-bold text-slate-900 md:text-4xl">{{ __('Add a New Institution') }}</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 md:text-base">
                {{ __('Submit a new institution record for the MajlisIlmu directory. Maintainers will review it before it goes live, and the contributor will become the initial owner once approved.') }}
            </p>

            <form wire:submit="submit" class="mt-8 space-y-6">
                {{ $this->form }}

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700">
                        {{ __('Submit Institution') }}
                    </button>
                    <a href="{{ route('contributions.index') }}" wire:navigate
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                        {{ __('View My Contributions') }}
                    </a>
                </div>
            </form>
        </section>

        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            <section class="rounded-4xl border border-slate-200 bg-slate-950 p-6 text-white shadow-sm md:p-7">
                <p class="text-xs font-bold uppercase tracking-[0.22em] text-emerald-300">{{ __('What happens next') }}</p>
                <div class="mt-5 space-y-4 text-sm text-slate-300">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="font-semibold text-white">{{ __('1. A pending record is staged') }}</p>
                        <p class="mt-2">{{ __('Your institution details, address, and media are saved in a pending state so reviewers see the real record, not just notes.') }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="font-semibold text-white">{{ __('2. Approval publishes it') }}</p>
                        <p class="mt-2">{{ __('A maintainer or admin can approve the staged institution with one action once everything looks correct.') }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="font-semibold text-white">{{ __('3. You become the maintainer') }}</p>
                        <p class="mt-2">{{ __('The original contributor is attached as the initial owner so future edits can be managed directly.') }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-4xl border border-slate-200 bg-white p-6 shadow-sm md:p-7">
                <p class="text-sm font-semibold text-slate-900">{{ __('Need to add a speaker instead?') }}</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('Use the speaker submission flow when the person does not already exist in the directory.') }}</p>
                <a href="{{ route('contributions.submit-speaker') }}" wire:navigate
                    class="mt-4 inline-flex items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100">
                    {{ __('Submit Speaker') }}
                </a>
            </section>
        </div>
    </div>
</div>
