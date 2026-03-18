@section('title', __('Report Record') . ' - ' . config('app.name'))

<div class="min-h-screen bg-slate-50 py-10 pb-28">
    <div class="container mx-auto max-w-4xl px-6 lg:px-8">
        <div class="grid gap-8 lg:grid-cols-[1.1fr_0.9fr]">
            <section class="rounded-4xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                <p class="text-xs font-bold uppercase tracking-[0.22em] text-rose-600">{{ __('Safety & Trust') }}</p>
                <h1 class="mt-3 font-heading text-3xl font-bold text-slate-900">{{ __('Report a Record') }}</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">
                    {{ __('Use this form when a listing is fake, misleading, outdated, or otherwise unsafe. Reports are queued for moderation review.') }}
                </p>

                <div class="mt-6 rounded-3xl border border-rose-200 bg-rose-50 p-5">
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-rose-700">{{ __('Reporting') }}</p>
                    <p class="mt-3 text-sm font-medium text-slate-600">{{ __('Selected :subject', ['subject' => strtolower($this->context['subject_label'])]) }}</p>
                    <p class="mt-1 text-xl font-semibold text-slate-950">{{ $this->context['subject_title'] }}</p>
                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        {{ __('Please confirm this is the :subject you want to report before submitting.', ['subject' => strtolower($this->context['subject_label'])]) }}
                    </p>
                    <a href="{{ $this->context['redirect_url'] }}"
                        class="mt-4 inline-flex items-center justify-center rounded-xl border border-rose-200 bg-white px-4 py-2 text-sm font-semibold text-rose-700 transition hover:border-rose-300 hover:text-rose-800">
                        {{ __('View this :subject', ['subject' => strtolower($this->context['subject_label'])]) }}
                    </a>
                </div>

                <form wire:submit="submit" class="mt-8 space-y-6">
                    {{ $this->form }}

                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit"
                            class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-rose-700">
                            {{ __('Submit Report') }}
                        </button>
                    </div>
                </form>
            </section>

            <aside class="space-y-6">
                <section class="rounded-4xl border border-slate-200 bg-slate-950 p-6 text-white shadow-sm md:p-7">
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-rose-300">{{ __('Moderation notes') }}</p>
                    <div class="mt-5 space-y-4 text-sm text-slate-300">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="font-semibold text-white">{{ __('Reports are reviewed, not auto-hidden') }}</p>
                            <p class="mt-2">{{ __('This keeps legitimate records visible while moderators verify the claim.') }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="font-semibold text-white">{{ __('Duplicate reports are limited') }}</p>
                            <p class="mt-2">{{ __('The same reporter can only file one report per record every 24 hours.') }}</p>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</div>
