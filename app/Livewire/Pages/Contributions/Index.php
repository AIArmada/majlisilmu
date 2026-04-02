<?php

namespace App\Livewire\Pages\Contributions;

use App\Actions\Contributions\ApproveContributionRequestAction;
use App\Actions\Contributions\CancelContributionRequestAction;
use App\Actions\Contributions\RejectContributionRequestAction;
use App\Actions\Contributions\ResolveOwnContributionRequestAction;
use App\Actions\Contributions\ResolvePendingContributionApprovalsAction;
use App\Actions\Contributions\ResolveReviewableContributionRequestAction;
use App\Enums\MemberSubjectType;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\ContributionRequest;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

#[Layout('layouts.app')]
#[Title('My Contributions')]
class Index extends Component implements HasForms
{
    use InteractsWithForms;
    use InteractsWithToasts;

    /** @var array<string, string> */
    public array $reviewNotes = [];

    /** @var array<string, string> */
    public array $rejectionReasons = [];

    /** @var array<string, mixed>|null */
    public ?array $claimData = [];

    public function mount(): void
    {
        abort_unless(auth()->user() instanceof User, 403);

        $this->claimEntryForm()->fill([
            'subject_type' => MemberSubjectType::Institution->value,
            'subject_slug' => null,
        ]);
    }

    /**
     * @return Collection<int, ContributionRequest>
     */
    #[Computed]
    public function myRequests(): Collection
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->contributionRequests()
            ->with(['entity', 'reviewer'])
            ->latest('created_at')
            ->get();
    }

    /**
     * @return Collection<int, ContributionRequest>
     */
    #[Computed]
    public function pendingApprovals(): Collection
    {
        /** @var User $user */
        $user = auth()->user();

        return app(ResolvePendingContributionApprovalsAction::class)->handle($user);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('claimData')
            ->components([
                Section::make(__('Claim Membership'))
                    ->description(__('Cari institusi atau penceramah yang anda urus, kemudian teruskan ke borang tuntutan.'))
                    ->schema([
                        Select::make('subject_type')
                            ->label(__('Record Type'))
                            ->options([
                                MemberSubjectType::Institution->value => __('Institusi'),
                                MemberSubjectType::Speaker->value => __('Penceramah'),
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('subject_slug', null);
                            }),
                        Select::make('subject_slug')
                            ->label(__('Record'))
                            ->placeholder(__('Search by name'))
                            ->required()
                            ->searchable()
                            ->getSearchResultsUsing(fn (Get $get, string $search): array => $this->membershipClaimSearchOptions(
                                subjectType: $this->normalizeNullableString($get('subject_type')),
                                search: $search,
                            ))
                            ->getOptionLabelUsing(fn (mixed $value, Get $get): ?string => $this->membershipClaimOptionLabel(
                                subjectType: $this->normalizeNullableString($get('subject_type')),
                                subjectSlug: is_string($value) ? $value : null,
                            ))
                            ->helperText(__('Hanya rekod institusi dan penceramah yang aktif dipaparkan di sini.')),
                    ])
                    ->columns(2),
            ]);
    }

    public function approve(
        string $requestId,
        ApproveContributionRequestAction $approveContributionRequestAction,
        ResolveReviewableContributionRequestAction $resolveReviewableContributionRequestAction,
    ): void {
        /** @var User $user */
        $user = auth()->user();

        $request = $resolveReviewableContributionRequestAction->handle($user, $requestId);

        $approveContributionRequestAction->handle($request, $user, $this->reviewNotes[$requestId] ?? null);
        unset($this->reviewNotes[$requestId], $this->rejectionReasons[$requestId]);
    }

    public function reject(
        string $requestId,
        RejectContributionRequestAction $rejectContributionRequestAction,
        ResolveReviewableContributionRequestAction $resolveReviewableContributionRequestAction,
    ): void {
        /** @var User $user */
        $user = auth()->user();

        $request = $resolveReviewableContributionRequestAction->handle($user, $requestId);

        $rejectContributionRequestAction->handle(
            $request,
            $user,
            $this->rejectionReasons[$requestId] ?: 'rejected_by_reviewer',
            $this->reviewNotes[$requestId] ?? null,
        );

        unset($this->reviewNotes[$requestId], $this->rejectionReasons[$requestId]);
    }

    public function cancel(
        string $requestId,
        CancelContributionRequestAction $cancelContributionRequestAction,
        ResolveOwnContributionRequestAction $resolveOwnContributionRequestAction,
    ): void {
        /** @var User $user */
        $user = auth()->user();
        $request = $resolveOwnContributionRequestAction->handle($user, $requestId);

        $cancelContributionRequestAction->handle($request, $user);
    }

    public function startMembershipClaim(): void
    {
        $state = $this->claimEntryForm()->getState();
        $subjectType = MemberSubjectType::tryFrom((string) ($state['subject_type'] ?? ''));
        $subjectSlug = is_string($state['subject_slug'] ?? null) ? $state['subject_slug'] : null;

        if (! $subjectType?->isClaimable() || ! filled($subjectSlug)) {
            $this->addError('claimData.subject_slug', __('Select a record before continuing.'));

            return;
        }

        $this->redirectRoute('membership-claims.create', [
            'subjectType' => $subjectType->publicRouteSegment(),
            'subjectId' => $subjectSlug,
        ], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.pages.contributions.index');
    }

    protected function claimEntryForm(): Schema
    {
        return $this->getForm('form') ?? throw new RuntimeException('Membership claim entry form is not available.');
    }

    /**
     * @return array<string, string>
     */
    private function membershipClaimSearchOptions(?string $subjectType, string $search): array
    {
        return match (MemberSubjectType::tryFrom((string) $subjectType)) {
            MemberSubjectType::Institution => Institution::query()
                ->where('status', 'verified')
                ->where('is_active', true)
                ->tap(fn (Builder $query): Builder => filled($search) ? $query->searchNameOrNickname($search) : $query)
                ->orderBy('name')
                ->limit(50)
                ->get(['id', 'slug', 'name', 'nickname'])
                ->mapWithKeys(fn (Institution $institution): array => [$institution->slug => $institution->display_name])
                ->all(),
            MemberSubjectType::Speaker => Speaker::query()
                ->where('status', 'verified')
                ->where('is_active', true)
                ->tap(fn (Builder $query): Builder => $this->applySearchConstraint($query, 'name', $search))
                ->orderBy('name')
                ->limit(50)
                ->get()
                ->mapWithKeys(fn (Speaker $speaker): array => [$speaker->slug => $speaker->formatted_name])
                ->all(),
            default => [],
        };
    }

    private function membershipClaimOptionLabel(?string $subjectType, ?string $subjectSlug): ?string
    {
        if (! filled($subjectSlug)) {
            return null;
        }

        return match (MemberSubjectType::tryFrom((string) $subjectType)) {
            MemberSubjectType::Institution => Institution::query()
                ->where('status', 'verified')
                ->where('is_active', true)
                ->where('slug', $subjectSlug)
                ->first(['id', 'name', 'nickname'])
                ?->display_name,
            MemberSubjectType::Speaker => Speaker::query()
                ->where('status', 'verified')
                ->where('is_active', true)
                ->where('slug', $subjectSlug)
                ->first()?->formatted_name,
            default => null,
        };
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function applySearchConstraint(Builder $query, string $column, string $search): Builder
    {
        $normalizedSearch = trim($search);

        if ($normalizedSearch === '') {
            return $query;
        }

        return $query->where($column, $this->databaseLikeOperator(), "%{$normalizedSearch}%");
    }

    private function databaseLikeOperator(): string
    {
        return config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }
}
