@php
    $myRequests = $this->myRequests;
    $myUpdateRequests = $this->myUpdateRequests;
    $submittedEvents = $this->submittedEvents;
    $myReports = $this->myReports;
    $statusClass = static fn (string $status): string => match ($status) {
        'approved' => 'bg-emerald-100 text-emerald-700',
        'rejected' => 'bg-rose-100 text-rose-700',
        'cancelled' => 'bg-slate-200 text-slate-700',
        'open' => 'bg-amber-100 text-amber-700',
        'triaged' => 'bg-sky-100 text-sky-700',
        'resolved' => 'bg-emerald-100 text-emerald-700',
        'dismissed' => 'bg-slate-200 text-slate-700',
        'needs_changes' => 'bg-amber-100 text-amber-700',
        'draft' => 'bg-slate-100 text-slate-700',
        default => 'bg-amber-100 text-amber-700',
    };
    $statusLabel = static fn (string $status): string => str($status)->headline()->toString();
    $requestTypeLabel = static fn ($request): string => str($request->type->value)->headline().' · '.str($request->subject_type->value)->headline();
    $eventStatusLabel = static fn (object|string $status): string => method_exists($status, 'getLabel')
        ? $status->getLabel()
        : str(class_basename($status))->headline()->toString();
    $reportEntityMetadata = app(\App\Actions\Reports\ResolveReportEntityMetadataAction::class);
    $reportCategoryOptions = app(\App\Actions\Reports\ResolveReportCategoryOptionsAction::class);
@endphp

@section('title', __('My Contributions') . ' - ' . config('app.name'))

@include('partials.filament-assets', [
    'scripts' => ['filament/support', 'filament/schemas', 'filament/forms'],
])

<div class="min-h-screen bg-slate-50 py-10 pb-28">
    <div class="container mx-auto max-w-6xl px-6 lg:px-8">
        <div class="mb-8 flex flex-wrap gap-3 md:justify-end">
            <a href="{{ route('submit-event.create') }}" wire:navigate
                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                {{ __('Submit Event') }}
            </a>
            <a href="{{ route('contributions.submit-institution') }}" wire:navigate
                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                {{ __('Submit Institution') }}
            </a>
            <a href="{{ route('contributions.submit-speaker') }}" wire:navigate
                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                {{ __('Submit Speaker') }}
            </a>
        </div>

        <div class="mt-8 space-y-8">
            <section class="rounded-4xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Event Submissions') }}</h2>
                        <p class="mt-2 text-sm text-slate-500">{{ __('When you submit events, they will appear here with status and related details.') }}</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $submittedEvents->total() }}</span>
                </div>

                <div class="mt-6 space-y-4">
                    @forelse($submittedEvents as $submission)
                        @php
                            $event = $submission->event;
                            $eventStatus = str(class_basename($event->status))->snake()->toString();
                            $eventDetails = $this->eventSubmissionDetails($submission);
                        @endphp

                        <article class="rounded-2xl border border-slate-200 p-5">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                            {{ __('Event Submission') }}
                                        </span>
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass($eventStatus) }}">
                                            {{ $eventStatusLabel($event->status) }}
                                        </span>
                                    </div>

                                    <p class="text-sm text-slate-600">
                                        {{ __('Submitted :date', ['date' => $submission->created_at?->diffForHumans()]) }}
                                    </p>

                                    <p class="text-base font-semibold text-slate-900">
                                        {{ $event->title }}
                                    </p>

                                    @if(filled($submission->notes))
                                        <p class="text-sm leading-6 text-slate-700">{{ $submission->notes }}</p>
                                    @endif

                                    @if($eventDetails !== [])
                                        <div class="flex flex-wrap gap-2 pt-1">
                                            @foreach($eventDetails as $detail)
                                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                                                    {{ $detail }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-12 text-center">
                            <p class="text-base font-semibold text-slate-700">{{ __('No event submissions yet.') }}</p>
                        </div>
                    @endforelse
                </div>

                @if($submittedEvents->hasPages())
                    <div class="mt-6">
                        {{ $submittedEvents->links() }}
                    </div>
                @endif
            </section>

            <section class="rounded-4xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Update Submissions') }}</h2>
                        <p class="mt-2 text-sm text-slate-500">{{ __('Updates you submit here will appear with their status and review notes.') }}</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $myUpdateRequests->total() }}</span>
                </div>

                <div class="mt-6 space-y-4">
                    @forelse($myUpdateRequests as $request)
                        <article class="rounded-2xl border border-slate-200 p-5">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass((string) $request->status->value) }}">
                                            {{ $statusLabel($request->status->value) }}
                                        </span>
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                                            {{ $requestTypeLabel($request) }}
                                        </span>
                                    </div>

                                    <p class="text-sm text-slate-600">
                                        {{ __('Submitted :date', ['date' => $request->created_at?->diffForHumans()]) }}
                                    </p>

                                    <p class="text-base font-semibold text-slate-900">
                                        {{ \App\Filament\Resources\ContributionRequests\Support\ContributionRequestPresenter::entityTitle($request) }}
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
                            <p class="text-base font-semibold text-slate-700">{{ __('No update submissions yet.') }}</p>
                        </div>
                    @endforelse
                </div>

                @if($myUpdateRequests->hasPages())
                    <div class="mt-6">
                        {{ $myUpdateRequests->links() }}
                    </div>
                @endif
            </section>

            <section class="rounded-4xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Contribution Requests') }}</h2>
                        <p class="mt-2 text-sm text-slate-500">{{ __('Requests for new institutions and speakers.') }}</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $myRequests->total() }}</span>
                </div>

                <div class="mt-6 space-y-4">
                    @forelse($myRequests as $request)
                        <article class="rounded-2xl border border-slate-200 p-5">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass((string) $request->status->value) }}">
                                            {{ $statusLabel($request->status->value) }}
                                        </span>
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                                            {{ $requestTypeLabel($request) }}
                                        </span>
                                    </div>

                                    <p class="text-sm text-slate-600">
                                        {{ __('Submitted :date', ['date' => $request->created_at?->diffForHumans()]) }}
                                    </p>

                                    <p class="text-base font-semibold text-slate-900">
                                        {{ \App\Filament\Resources\ContributionRequests\Support\ContributionRequestPresenter::entityTitle($request) }}
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

                @if($myRequests->hasPages())
                    <div class="mt-6">
                        {{ $myRequests->links() }}
                    </div>
                @endif
            </section>

            <section class="rounded-4xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Report Submissions') }}</h2>
                        <p class="mt-2 text-sm text-slate-500">{{ __('Reports you submit here will appear with their status and review notes.') }}</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $myReports->total() }}</span>
                </div>

                <div class="mt-6 space-y-4">
                    @forelse($myReports as $report)
                        @php
                            $reportEntityLabel = $reportEntityMetadata->handle($report->entity_type)['label'];
                            $reportCategoryLabel = $reportCategoryOptions->handle($report->entity_type)[$report->category] ?? str($report->category)->headline()->toString();
                            $reportTitle = \App\Filament\Resources\Reports\Support\ReportPresenter::entityTitle($report);
                        @endphp

                        <article class="rounded-2xl border border-slate-200 p-5">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">
                                            {{ __('Report Submission') }}
                                        </span>
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass((string) $report->status) }}">
                                            {{ $statusLabel($report->status) }}
                                        </span>
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                                            {{ $reportEntityLabel }}
                                        </span>
                                    </div>

                                    <p class="text-sm text-slate-600">
                                        {{ __('Submitted :date', ['date' => $report->created_at?->diffForHumans()]) }}
                                    </p>

                                    <p class="text-base font-semibold text-slate-900">
                                        {{ $reportTitle }}
                                    </p>

                                    <p class="text-sm leading-6 text-slate-700">
                                        {{ $reportCategoryLabel }}
                                    </p>

                                    @if(filled($report->description))
                                        <p class="text-sm leading-6 text-slate-700">{{ $report->description }}</p>
                                    @endif

                                    @if(filled($report->resolution_note))
                                        <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-700">{{ $report->resolution_note }}</p>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-12 text-center">
                            <p class="text-base font-semibold text-slate-700">{{ __('No report submissions yet.') }}</p>
                        </div>
                    @endforelse
                </div>

                @if($myReports->hasPages())
                    <div class="mt-6">
                        {{ $myReports->links() }}
                    </div>
                @endif
            </section>
        </div>

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
    </div>
</div>
