@section('title', __('Submit Speaker') . ' - ' . config('app.name'))

@include('partials.filament-assets', [
    'scripts' => ['filament/support', 'filament/schemas', 'filament/forms', 'filament/actions'],
])

<div class="min-h-screen bg-slate-50 py-10 pb-28">
    <div class="container mx-auto max-w-7xl px-6 lg:px-8">
        <section class="rounded-4xl border border-slate-200 bg-white p-6 shadow-sm md:p-8 lg:p-10">
            <p class="text-xs font-bold uppercase tracking-[0.22em] text-emerald-600">{{ __('Community Contribution') }}</p>
            <h1 class="mt-3 font-heading text-3xl font-bold text-slate-900 md:text-4xl">{{ __('Add a New Speaker') }}</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 md:text-base">
                {{ __('Submit a speaker profile for the public directory. This works best for people who are already known in the community but do not yet have a record in MajlisIlmu.') }}
            </p>

            <form wire:submit="submit" class="mt-8 space-y-6">
                {{ $this->form }}

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700">
                        {{ __('Submit Speaker') }}
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
                <p class="text-xs font-bold uppercase tracking-[0.22em] text-emerald-300">{{ __('Review flow') }}</p>
                <div class="mt-5 space-y-4 text-sm text-slate-300">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="font-semibold text-white">{{ __('A full pending profile is staged') }}</p>
                        <p class="mt-2">{{ __('MajlisIlmu stores the speaker details, relationships, and media up front so reviewers can approve the real profile directly.') }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="font-semibold text-white">{{ __('Approval simply publishes it') }}</p>
                        <p class="mt-2">{{ __('Once approved, the staged speaker becomes the live public record and stays linked to the original contributor.') }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-4xl border border-slate-200 bg-white p-6 shadow-sm md:p-7">
                <p class="text-sm font-semibold text-slate-900">{{ __('Need to add an institution too?') }}</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('You can submit the institution first, then come back and connect speakers to it after approval.') }}</p>
                <a href="{{ route('contributions.submit-institution') }}" wire:navigate
                    class="mt-4 inline-flex items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100">
                    {{ __('Submit Institution') }}
                </a>
            </section>
        </div>
    </div>
</div>
