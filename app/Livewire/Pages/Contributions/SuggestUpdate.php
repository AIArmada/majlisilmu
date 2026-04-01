<?php

namespace App\Livewire\Pages\Contributions;

use App\Actions\Contributions\ApplyDirectContributionUpdateAction;
use App\Actions\Contributions\ResolveContributionChangedPayloadAction;
use App\Actions\Contributions\ResolveContributionSubjectPresentationAction;
use App\Actions\Contributions\ResolveContributionSubmissionStateAction;
use App\Actions\Contributions\ResolveContributionUpdateContextAction;
use App\Actions\Contributions\ResolveLatestPendingContributionRequestAction;
use App\Actions\Contributions\SubmitContributionUpdateRequestAction;
use App\Enums\ContributionSubjectType;
use App\Forms\EventContributionFormSchema;
use App\Forms\InstitutionContributionFormSchema;
use App\Forms\ReferenceContributionFormSchema;
use App\Forms\SpeakerContributionFormSchema;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\ContributionRequest;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Speaker;
use App\Models\User;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

#[Layout('layouts.app')]
class SuggestUpdate extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithToasts;
    use WithFileUploads;

    public Event|Institution|Reference|Speaker $entity;

    public string $subjectType;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, mixed> */
    public array $originalData = [];

    /** @var array{subject_label: string, redirect_url: string} */
    public array $subjectPresentation = [
        'subject_label' => '',
        'redirect_url' => '',
    ];

    public function mount(
        string $subjectType,
        string $subjectId,
        ResolveContributionUpdateContextAction $resolveContributionUpdateContextAction,
        ResolveContributionSubjectPresentationAction $resolveContributionSubjectPresentationAction,
    ): void {
        $resolvedSubjectType = ContributionSubjectType::fromRouteSegment($subjectType);

        abort_unless($resolvedSubjectType instanceof ContributionSubjectType, 404);

        $this->subjectType = $resolvedSubjectType->value;

        $context = $resolveContributionUpdateContextAction->handle($this->subjectType, $subjectId);

        $this->entity = $context['entity'];
        $this->originalData = $context['initial_state'];
        $this->subjectPresentation = $resolveContributionSubjectPresentationAction->handle($this->entity);

        $user = auth()->user();

        abort_unless($user instanceof User, 403);
        abort_unless($user->can('view', $this->entity), 403);

        if (! $user->canSubmitDirectoryFeedback()) {
            abort(403, $user->directoryFeedbackBanMessage());
        }

        if ($this->shouldRedirectToCanonicalSubjectUrl($resolvedSubjectType, $subjectId)) {
            $this->redirectRoute('contributions.suggest-update', [
                'subjectType' => $resolvedSubjectType->publicRouteSegment(),
                'subjectId' => $this->canonicalSubjectId(),
            ], navigate: true);

            return;
        }

        $this->contributionForm()->fill($this->originalData);
    }

    #[Computed]
    public function canDirectEdit(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('update', $this->entity);
    }

    #[Computed]
    public function latestPendingRequest(): ?ContributionRequest
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        return app(ResolveLatestPendingContributionRequestAction::class)->handle($user, $this->entity);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->model($this->entity)
            ->statePath('data')
            ->components([
                Section::make($this->pageHeading())
                    ->description($this->pageDescription())
                    ->schema($this->subjectSchema())
                    ->columns(2),
                Section::make(__('Context for reviewers'))
                    ->schema([
                        Textarea::make('proposer_note')
                            ->label(__('Explain the change'))
                            ->rows(4)
                            ->maxLength(2000),
                    ]),
            ]);
    }

    public function submit(
        ApplyDirectContributionUpdateAction $applyDirectContributionUpdateAction,
        ResolveContributionChangedPayloadAction $resolveContributionChangedPayloadAction,
        ResolveContributionSubmissionStateAction $resolveContributionSubmissionStateAction,
        SubmitContributionUpdateRequestAction $submitContributionUpdateRequestAction,
    ): void {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        if (! $user->canSubmitDirectoryFeedback()) {
            abort(403, $user->directoryFeedbackBanMessage());
        }

        $submissionState = $resolveContributionSubmissionStateAction->handle($this->contributionForm()->getState());
        $state = $submissionState['state'];
        $changes = $resolveContributionChangedPayloadAction->handle($state, $this->originalData);
        $hasInstitutionCoverChange = $this->canDirectEdit() && $this->hasInstitutionCoverChange();

        if ($changes === [] && ! $hasInstitutionCoverChange) {
            $this->addError('data', __('Make at least one change before continuing.'));

            return;
        }

        if ($this->canDirectEdit()) {
            if ($changes !== []) {
                $applyDirectContributionUpdateAction->handle($this->entity, $changes);
            }

            if ($hasInstitutionCoverChange) {
                $this->saveInstitutionCoverChanges($this->entity);
            }

            $this->redirect($this->subjectPresentation['redirect_url'], navigate: true);

            return;
        }

        $submitContributionUpdateRequestAction->handle(
            $this->entity,
            $user,
            $changes,
            $submissionState['proposer_note'],
        );

        $this->redirect(route('contributions.index'), navigate: true);
    }

    public function rendering(object $view): void
    {
        if (method_exists($view, 'title')) {
            $view->title($this->pageHeading().' - '.config('app.name'));
        }
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private function subjectSchema(): array
    {
        return match (true) {
            $this->entity instanceof Institution => $this->institutionSubjectSchema(),
            $this->entity instanceof Speaker => SpeakerContributionFormSchema::components(
                includeMedia: false,
                addressStatePath: 'address',
            ),
            $this->entity instanceof Reference => ReferenceContributionFormSchema::components(includeMedia: false),
            default => EventContributionFormSchema::components(),
        };
    }

    private function pageHeading(): string
    {
        return $this->canDirectEdit()
            ? __('Update :subject', ['subject' => $this->subjectPresentation['subject_label']])
            : __('Suggest an Update for :subject', ['subject' => $this->subjectPresentation['subject_label']]);
    }

    private function pageDescription(): string
    {
        return $this->canDirectEdit()
            ? __('Your changes will be applied immediately because you already have maintainer access for this record.')
            : __('Your edits will be stored as a pending contribution request for maintainers to review.');
    }

    protected function contributionForm(): Schema
    {
        return $this->getForm('form') ?? throw new RuntimeException('Contribution update form is not available.');
    }

    private function canonicalSubjectId(): string
    {
        return match (true) {
            $this->entity instanceof Institution => $this->entity->slug,
            $this->entity instanceof Speaker => $this->entity->slug,
            $this->entity instanceof Reference => $this->entity->slug,
            default => $this->entity->slug,
        };
    }

    private function shouldRedirectToCanonicalSubjectUrl(ContributionSubjectType $subjectType, string $subjectId): bool
    {
        $routeSubjectType = request()->route('subjectType');

        if (! is_string($routeSubjectType) || $routeSubjectType === '') {
            return false;
        }

        return $subjectType->publicRouteSegment() !== $routeSubjectType
            || $this->canonicalSubjectId() !== $subjectId;
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private function institutionSubjectSchema(): array
    {
        $components = InstitutionContributionFormSchema::components(
            includeMedia: false,
            addressStatePath: 'address',
        );

        if ($this->canDirectEdit()) {
            array_splice($components, 1, 0, [$this->institutionCoverSection()]);
        }

        return $components;
    }

    private function institutionCoverSection(): Section
    {
        return Section::make(__('Media'))
            ->schema([
                SpatieMediaLibraryFileUpload::make('cover')
                    ->label(__('Cover Image'))
                    ->collection('cover')
                    ->image()
                    ->imageEditor()
                    ->imageAspectRatio('16:9')
                    ->automaticallyOpenImageEditorForAspectRatio()
                    ->imageEditorAspectRatioOptions(['16:9'])
                    ->automaticallyCropImagesToAspectRatio()
                    ->conversion('banner')
                    ->responsiveImages()
                    ->deletable(false)
                    ->columnSpanFull(),
            ]);
    }

    private function hasInstitutionCoverChange(): bool
    {
        if (! $this->entity instanceof Institution) {
            return false;
        }

        $coverField = $this->institutionCoverField();

        if (! $coverField instanceof SpatieMediaLibraryFileUpload) {
            return false;
        }

        $currentState = array_keys($this->currentInstitutionCoverState());
        $submittedState = array_keys(is_array($coverField->getRawState()) ? $coverField->getRawState() : []);

        sort($currentState);
        sort($submittedState);

        return $currentState !== $submittedState;
    }

    private function institutionCoverField(): ?SpatieMediaLibraryFileUpload
    {
        $field = $this->contributionForm()->getFlatFields(withHidden: true)['cover'] ?? null;

        return $field instanceof SpatieMediaLibraryFileUpload ? $field : null;
    }

    /**
     * @return array<string, string>
     */
    private function currentInstitutionCoverState(): array
    {
        if (! $this->entity instanceof Institution) {
            return [];
        }

        return $this->entity
            ->load('media')
            ->getMedia('cover')
            ->take(1)
            ->mapWithKeys(static fn ($media): array => [$media->uuid => $media->uuid])
            ->all();
    }

    private function saveInstitutionCoverChanges(Institution $record): void
    {
        $this->contributionForm()->model($record)->saveRelationships();
    }
}
