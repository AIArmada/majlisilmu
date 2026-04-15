@php
    $latestPendingRequest = $this->latestPendingRequest;
    $canDirectEdit = $this->canDirectEdit;
@endphp

@section('title', ($canDirectEdit ? __('Update Record') : __('Suggest Update')) . ' - ' . config('app.name'))

@include('partials.filament-assets', [
    'scripts' => ['filament/support', 'filament/schemas', 'filament/forms'],
])

<div class="min-h-screen bg-slate-50 py-4 pb-20 sm:py-6 lg:py-10">
    <div class="mx-auto w-full max-w-6xl px-3 sm:px-4 lg:px-6">
        <div class="space-y-4 sm:space-y-6">
            @if($latestPendingRequest)
                <section class="rounded-3xl border border-amber-200 bg-amber-50 px-4 py-4 shadow-sm sm:px-5 sm:py-5">
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-amber-700">{{ __('Pending Request') }}</p>
                    <p class="mt-2 text-sm leading-6 text-amber-900">
                        {{ __('You already have a pending update request for this record from :date.', ['date' => $latestPendingRequest->created_at?->diffForHumans()]) }}
                    </p>
                </section>
            @endif

            <section class="rounded-3xl border border-slate-200 bg-white px-4 py-5 shadow-sm sm:px-6 sm:py-6 lg:px-8 lg:py-8">
                <p class="text-xs font-bold uppercase tracking-[0.22em] text-emerald-600">
                    {{ $canDirectEdit ? __('Maintainer Edit') : __('Community Suggestion') }}
                </p>
                <h1 class="mt-3 font-heading text-2xl font-bold text-slate-900 sm:text-3xl lg:text-4xl">
                    {{ $canDirectEdit ? __('Apply an Update') : __('Suggest an Update') }}
                </h1>
                @if ($canDirectEdit)
                    <p class="mt-3 text-sm leading-6 text-slate-600 sm:text-base max-w-none">
                        {{ __('You already have edit access for this record, so changes from this form will be applied immediately.') }}
                    </p>
                @endif

                <form wire:submit="submit" class="mt-6 space-y-5 sm:mt-8 sm:space-y-6">
                    {{ $this->form }}

                    @error('data')
                        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</div>
                    @enderror

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <button type="submit"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700 sm:w-auto">
                            {{ $canDirectEdit ? __('Save Changes') : __('Submit Update Request') }}
                        </button>
                    </div>
                </form>
            </section>

            <x-filament-actions::modals />
        </div>
    </div>
</div>
