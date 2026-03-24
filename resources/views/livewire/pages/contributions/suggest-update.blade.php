@php
    $latestPendingRequest = $this->latestPendingRequest;
    $canDirectEdit = $this->canDirectEdit;
@endphp

@section('title', ($canDirectEdit ? __('Update Record') : __('Suggest Update')) . ' - ' . config('app.name'))

@include('partials.filament-assets', [
    'scripts' => ['filament/support', 'filament/schemas', 'filament/forms'],
])

<div class="min-h-screen bg-slate-50 py-10 pb-28">
    <div class="container mx-auto max-w-7xl px-6 lg:px-8">
        <section class="rounded-4xl border border-slate-200 bg-white p-6 shadow-sm md:p-8 lg:p-10">
            <p class="text-xs font-bold uppercase tracking-[0.22em] text-emerald-600">
                {{ $canDirectEdit ? __('Maintainer Edit') : __('Community Suggestion') }}
            </p>
            <h1 class="mt-3 font-heading text-3xl font-bold text-slate-900 md:text-4xl">
                {{ $canDirectEdit ? __('Apply an Update') : __('Suggest an Update') }}
            </h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 md:text-base">
                {{ $canDirectEdit
                    ? __('You already have edit access for this record, so changes from this form will be applied immediately.')
                    : __('Submit a structured change request so the owner or admin team can review it without losing the current record history.') }}
            </p>

            <form wire:submit="submit" class="mt-8 space-y-6">
                {{ $this->form }}

                @error('data')
                    <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</div>
                @enderror

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700">
                        {{ $canDirectEdit ? __('Save Changes') : __('Submit Update Request') }}
                    </button>
                    <a href="{{ route('contributions.index') }}" wire:navigate
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                        {{ __('View My Contributions') }}
                    </a>
                </div>
            </form>
        </section>

        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            @if($latestPendingRequest)
                <section class="rounded-4xl border border-amber-200 bg-amber-50 p-6 shadow-sm md:p-7">
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-amber-700">{{ __('Pending Request') }}</p>
                    <p class="mt-3 text-sm leading-6 text-amber-900">
                        {{ __('You already have a pending update request for this record from :date.', ['date' => $latestPendingRequest->created_at?->diffForHumans()]) }}
                    </p>
                </section>
            @endif

            <section class="rounded-4xl border border-slate-200 bg-slate-950 p-6 text-white shadow-sm md:p-7 {{ $latestPendingRequest ? '' : 'lg:col-span-2' }}">
                <p class="text-xs font-bold uppercase tracking-[0.22em] text-emerald-300">{{ __('Workflow') }}</p>
                <div class="mt-5 grid gap-4 lg:grid-cols-3 text-sm text-slate-300">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="font-semibold text-white">{{ __('Structured change set') }}</p>
                        <p class="mt-2">{{ __('Only changed fields are stored so maintainers can compare your suggestion against the current live record.') }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="font-semibold text-white">{{ __('Owner or admin review') }}</p>
                        <p class="mt-2">{{ __('Pending requests appear in the contribution inbox for maintainers who already manage the record.') }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="font-semibold text-white">{{ __('History is preserved') }}</p>
                        <p class="mt-2">{{ __('Every decision remains attached to the request so contributors and maintainers can track what happened.') }}</p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
