<?php

namespace App\Livewire\Pages\Contributions;

use App\Enums\ContributionRequestStatus;
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
use App\Services\ContributionEntityMutationService;
use App\Services\ContributionWorkflowService;
use App\Services\ModerationService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

#[Layout('layouts.app')]
class SuggestUpdate extends Component implements HasForms
{
    use InteractsWithForms;
    use InteractsWithToasts;

    public Event|Institution|Reference|Speaker $entity;

    public string $subjectType;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(string $subjectType, string $subjectId): void
    {
        $this->subjectType = $subjectType;
        $this->entity = $this->resolveEntity($subjectType, $subjectId);

        $user = auth()->user();

        abort_unless($user instanceof User, 403);
        abort_unless($user->can('view', $this->entity), 403);

        if (! $user->canSubmitDirectoryFeedback()) {
            abort(403, $user->directoryFeedbackBanMessage());
        }

        $this->contributionForm()->fill($this->initialState());
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

        return ContributionRequest::query()
            ->where('proposer_id', $user->getKey())
            ->where('entity_type', $this->entity->getMorphClass())
            ->where('entity_id', (string) $this->entity->getKey())
            ->where('status', ContributionRequestStatus::Pending)
            ->latest('created_at')
            ->first();
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
        ContributionWorkflowService $workflow,
        ModerationService $moderationService,
        ContributionEntityMutationService $entityMutationService,
    ): void {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        if (! $user->canSubmitDirectoryFeedback()) {
            abort(403, $user->directoryFeedbackBanMessage());
        }

        $state = $this->contributionForm()->getState();
        $note = isset($state['proposer_note']) && is_string($state['proposer_note'])
            ? trim($state['proposer_note'])
            : null;

        unset($state['proposer_note']);

        $changes = $this->changedPayload($state);

        if ($changes === []) {
            $this->addError('data', __('Make at least one change before continuing.'));

            return;
        }

        if ($this->canDirectEdit()) {
            $dirtyBeforeSave = $entityMutationService->apply($this->entity, $changes);

            if ($this->entity instanceof Event && $dirtyBeforeSave !== []) {
                $moderationService->handleSensitiveChange($this->entity, $dirtyBeforeSave);
            }

            $this->redirect($this->entityUrl(), navigate: true);

            return;
        }

        $workflow->submitUpdateRequest(
            $this->entity,
            $user,
            $changes,
            $note !== '' ? $note : null,
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
            $this->entity instanceof Institution => InstitutionContributionFormSchema::components(includeMedia: false),
            $this->entity instanceof Speaker => SpeakerContributionFormSchema::components(includeMedia: false),
            $this->entity instanceof Reference => ReferenceContributionFormSchema::components(includeMedia: false),
            default => EventContributionFormSchema::components(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function initialState(): array
    {
        return app(ContributionEntityMutationService::class)->stateFor($this->entity);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function changedPayload(array $state): array
    {
        $original = $this->initialState();
        $changes = [];

        foreach ($state as $key => $value) {
            if (! array_key_exists($key, $original)) {
                continue;
            }

            if ($this->valuesEqual($value, $original[$key])) {
                continue;
            }

            $changes[$key] = $value;
        }

        return $changes;
    }

    private function valuesEqual(mixed $left, mixed $right): bool
    {
        return json_encode($this->normalizeComparable($left)) === json_encode($this->normalizeComparable($right));
    }

    private function normalizeComparable(mixed $value): mixed
    {
        if ($value instanceof Carbon) {
            return $value->toISOString();
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof Arrayable) {
            return $this->normalizeComparable($value->toArray());
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalizeComparable($item), $value);
        }

        return $value;
    }

    private function resolveEntity(string $subjectType, string $subjectId): Event|Institution|Reference|Speaker
    {
        return match ($subjectType) {
            'event' => $this->resolveSlugOrUuid(Event::query(), 'events.slug', $subjectId),
            'institution' => $this->resolveSlugOrUuid(Institution::query(), 'institutions.slug', $subjectId),
            'speaker' => $this->resolveSlugOrUuid(Speaker::query(), 'speakers.slug', $subjectId),
            'reference' => $this->resolveReference($subjectId),
            default => abort(404),
        };
    }

    /**
     * @template TModel of Event|Institution|Speaker
     *
     * @param  Builder<TModel>  $query
     * @return TModel
     */
    private function resolveSlugOrUuid($query, string $slugColumn, string $subjectId): Event|Institution|Speaker
    {
        $query->where($slugColumn, $subjectId);

        if (Str::isUuid($subjectId)) {
            $query->orWhere($query->getModel()->getQualifiedKeyName(), $subjectId);
        }

        return $query->firstOrFail();
    }

    private function resolveReference(string $subjectId): Reference
    {
        abort_unless(Str::isUuid($subjectId), 404);

        return Reference::query()->whereKey($subjectId)->firstOrFail();
    }

    private function pageHeading(): string
    {
        return $this->canDirectEdit()
            ? __('Update :subject', ['subject' => $this->subjectLabel()])
            : __('Suggest an Update for :subject', ['subject' => $this->subjectLabel()]);
    }

    private function pageDescription(): string
    {
        return $this->canDirectEdit()
            ? __('Your changes will be applied immediately because you already have maintainer access for this record.')
            : __('Your edits will be stored as a pending contribution request for maintainers to review.');
    }

    private function subjectLabel(): string
    {
        return match (true) {
            $this->entity instanceof Institution => __('Institution'),
            $this->entity instanceof Speaker => __('Speaker'),
            $this->entity instanceof Reference => __('Reference'),
            default => __('Event'),
        };
    }

    private function entityUrl(): string
    {
        return match (true) {
            $this->entity instanceof Institution => route('institutions.show', $this->entity),
            $this->entity instanceof Speaker => route('speakers.show', $this->entity),
            $this->entity instanceof Reference => route('references.show', $this->entity),
            default => route('events.show', $this->entity),
        };
    }

    protected function contributionForm(): Schema
    {
        return $this->getForm('form') ?? throw new RuntimeException('Contribution update form is not available.');
    }
}
