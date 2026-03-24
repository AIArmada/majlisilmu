@php
    $myRequests = $this->myRequests;
    $pendingApprovals = $this->pendingApprovals;
    $statusClass = static fn (string $status): string => match ($status) {
        'approved' => 'bg-emerald-100 text-emerald-700',
        'rejected' => 'bg-rose-100 text-rose-700',
        'cancelled' => 'bg-slate-200 text-slate-700',
        default => 'bg-amber-100 text-amber-700',
    };
    $subjectLabel = static fn (string $subject): string => str($subject)->headline()->toString();
@endphp

@section('title', __('My Contributions') . ' - ' . config('app.name'))

@include('partials.filament-assets', [
    'scripts' => ['filament/support', 'filament/schemas', 'filament/forms'],
])

<div class="min-h-screen bg-slate-50 py-10 pb-28">
    <div class="container mx-auto max-w-6xl px-6 lg:px-8">
        <section class="rounded-4xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
            <div class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-emerald-600">{{ __('Community Contribution') }}</p>
                    <h1 class="mt-3 font-heading text-3xl font-bold text-slate-900">{{ __('My Contributions') }}</h1>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">
                        {{ __('Track your submissions, review pending decisions, and approve update requests for records you already maintain.') }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('contributions.submit-institution') }}" wire:navigate
                        class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                        {{ __('Submit Institution') }}
                    </a>
                    <a href="{{ route('contributions.submit-speaker') }}" wire:navigate
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                        {{ __('Submit Speaker') }}
                    </a>
                </div>
            </div>
        </section>

        <section class="mt-8 rounded-4xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-2xl">
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-emerald-600">{{ __('Membership') }}</p>
                    <h2 class="mt-3 font-heading text-2xl font-bold text-slate-900">{{ __('Tuntut Keahlian') }}</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        {{ __('Jika anda benar-benar mengurus institusi atau penceramah tertentu, cari rekodnya di sini dan teruskan ke borang tuntutan. Laluan ini diletakkan di halaman sumbangan kerana ia hanya relevan kepada sebilangan kecil pengguna.') }}
                    </p>
                </div>

                <a href="{{ route('membership-claims.index') }}" wire:navigate
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                    {{ __('View My Claims') }}
                </a>
            </div>

            <form wire:submit="startMembershipClaim" class="mt-6 space-y-6">
                {{ $this->form }}

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700">
                        {{ __('Continue to Claim Form') }}
                    </button>
                </div>
            </form>
        </section>

        <div class="mt-8 grid gap-8 xl:grid-cols-[1.15fr_0.85fr]">
            <section class="rounded-4xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('My Request History') }}</h2>
                        <p class="mt-2 text-sm text-slate-500">{{ __('Everything you submitted, including approvals, rejections, and cancellations.') }}</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $myRequests->count() }}</span>
                </div>

                <div class="mt-6 space-y-4">
                    @forelse($myRequests as $request)
                        <article class="rounded-2xl border border-slate-200 p-5">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass((string) $request->status->value) }}">
                                            {{ str($request->status->value)->headline() }}
                                        </span>
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                                            {{ str($request->type->value)->headline() }} {{ $subjectLabel($request->subject_type->value) }}
                                        </span>
                                    </div>

                                    <p class="text-sm text-slate-600">
                                        {{ __('Submitted :date', ['date' => $request->created_at?->diffForHumans()]) }}
                                    </p>

                                    @if(filled($request->proposer_note))
                                        <p class="text-sm leading-6 text-slate-700">{{ $request->proposer_note }}</p>
                                    @endif

                                    @if(filled($request->reviewer_note))
                                        <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-700">{{ $request->reviewer_note }}</p>
                                    @endif
                                </div>

                                @if($request->status === \App\Enums\ContributionRequestStatus::Pending)
                                    <button type="button" wire:click="cancel('{{ $request->id }}')"
                                        class="inline-flex items-center justify-center rounded-xl border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50">
                                        {{ __('Cancel') }}
                                    </button>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-12 text-center">
                            <p class="text-base font-semibold text-slate-700">{{ __('No contribution requests yet.') }}</p>
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="rounded-4xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Pending Approvals') }}</h2>
                        <p class="mt-2 text-sm text-slate-500">{{ __('Requests you can approve because you already manage the affected record.') }}</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $pendingApprovals->count() }}</span>
                </div>

                <div class="mt-6 space-y-4">
                    @forelse($pendingApprovals as $request)
                        <article class="rounded-2xl border border-slate-200 p-5">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                    {{ str($request->type->value)->headline() }} {{ $subjectLabel($request->subject_type->value) }}
                                </span>
                                <span class="text-xs text-slate-500">{{ __('From :name', ['name' => $request->proposer?->name ?? __('Unknown')]) }}</span>
                            </div>

                            @if(filled($request->proposer_note))
                                <p class="mt-3 text-sm leading-6 text-slate-700">{{ $request->proposer_note }}</p>
                            @endif

                            <div class="mt-4 space-y-3">
                                <textarea wire:model.defer="reviewNotes.{{ $request->id }}"
                                    class="min-h-24 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 focus:border-emerald-500 focus:outline-none"
                                    placeholder="{{ __('Add an optional note for the contributor') }}"></textarea>

                                <input type="text" wire:model.defer="rejectionReasons.{{ $request->id }}"
                                    class="h-11 w-full rounded-2xl border border-slate-200 px-4 text-sm text-slate-700 focus:border-emerald-500 focus:outline-none"
                                    placeholder="{{ __('Optional rejection code, e.g. needs_more_evidence') }}">

                                <div class="flex flex-wrap gap-3">
                                    <button type="button" wire:click="approve('{{ $request->id }}')"
                                        class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                                        {{ __('Approve') }}
                                    </button>
                                    <button type="button" wire:click="reject('{{ $request->id }}')"
                                        class="inline-flex items-center justify-center rounded-xl border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700 transition hover:bg-rose-50">
                                        {{ __('Reject') }}
                                    </button>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-12 text-center">
                            <p class="text-base font-semibold text-slate-700">{{ __('No pending approvals right now.') }}</p>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</div>
