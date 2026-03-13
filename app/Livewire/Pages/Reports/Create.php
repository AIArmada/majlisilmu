<?php

namespace App\Livewire\Pages\Reports;

use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Speaker;
use App\Services\ReportService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

#[Layout('layouts.app')]
class Create extends Component implements HasForms
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

        $this->reportForm()->fill([
            'category' => array_key_first($this->categoryOptions()),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('Report this :subject', ['subject' => strtolower($this->subjectLabel())]))
                    ->description(__('Use this when the record is fake, inaccurate, unsafe, or misleading. Reports go to moderation review.'))
                    ->schema([
                        Select::make('category')
                            ->label(__('Issue Type'))
                            ->options($this->categoryOptions())
                            ->required(),
                        Textarea::make('description')
                            ->label(__('Details'))
                            ->rows(6)
                            ->maxLength(2000)
                            ->helperText(__('Add context if the issue is not obvious.'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function submit(ReportService $reportService): void
    {
        $state = $this->reportForm()->getState();

        if (($state['category'] ?? null) === 'other' && blank($state['description'] ?? null)) {
            $this->addError('data.description', __('Please describe the issue so moderators know what to verify.'));

            return;
        }

        try {
            $reportService->submit(
                $this->entity,
                $this->subjectType,
                auth()->user(),
                $this->resolveReporterFingerprint(),
                (string) $state['category'],
                filled($state['description'] ?? null) ? (string) $state['description'] : null,
            );
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== 'duplicate_report') {
                throw $exception;
            }

            $this->addError('data.category', __('You already reported this record within the last 24 hours.'));

            return;
        }

        $this->redirect($this->entityUrl(), navigate: true);
    }

    public function rendering(object $view): void
    {
        if (method_exists($view, 'title')) {
            $view->title(__('Report :subject', ['subject' => $this->subjectLabel()]).' - '.config('app.name'));
        }
    }

    /**
     * @return array<string, string>
     */
    private function categoryOptions(): array
    {
        return match ($this->subjectType) {
            'event' => [
                'wrong_info' => __('Wrong information'),
                'cancelled_not_updated' => __('Cancelled but not updated'),
                'inappropriate_content' => __('Inappropriate content'),
                'other' => __('Other'),
            ],
            'institution' => [
                'wrong_info' => __('Wrong information'),
                'fake_institution' => __('Fake institution'),
                'other' => __('Other'),
            ],
            'speaker' => [
                'wrong_info' => __('Wrong information'),
                'fake_speaker' => __('Fake speaker'),
                'other' => __('Other'),
            ],
            'reference' => [
                'wrong_info' => __('Wrong information'),
                'fake_reference' => __('Fake reference'),
                'other' => __('Other'),
            ],
            default => ['other' => __('Other')],
        };
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

    private function resolveReporterFingerprint(): string
    {
        $userId = auth()->id();

        if (is_string($userId) && $userId !== '') {
            return 'user:'.$userId;
        }

        $ipAddress = (string) (request()->ip() ?? 'unknown-ip');
        $userAgent = trim((string) (request()->userAgent() ?? 'unknown-agent'));

        return 'guest:'.hash('sha256', "{$ipAddress}|{$userAgent}");
    }

    private function subjectLabel(): string
    {
        return match ($this->subjectType) {
            'institution' => __('Institution'),
            'speaker' => __('Speaker'),
            'reference' => __('Reference'),
            default => __('Event'),
        };
    }

    private function entityUrl(): string
    {
        return match ($this->subjectType) {
            'institution' => route('institutions.show', $this->entity),
            'speaker' => route('speakers.show', $this->entity),
            'reference' => route('references.show', $this->entity),
            default => route('events.show', $this->entity),
        };
    }

    protected function reportForm(): Schema
    {
        return $this->getForm('form') ?? throw new RuntimeException('Report form is not available.');
    }
}
