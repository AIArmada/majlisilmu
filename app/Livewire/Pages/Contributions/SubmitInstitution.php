<?php

namespace App\Livewire\Pages\Contributions;

use App\Enums\ContributionSubjectType;
use App\Forms\InstitutionContributionFormSchema;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Institution;
use App\Models\User;
use App\Services\ContributionEntityMutationService;
use App\Services\ContributionWorkflowService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

#[Layout('layouts.app')]
#[Title('Submit Institution')]
class SubmitInstitution extends Component implements HasForms
{
    use InteractsWithForms;
    use InteractsWithToasts;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->contributionForm()->fill([
            'type' => 'masjid',
            'address' => [
                'country_id' => 132,
            ],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->model(new Institution)
            ->statePath('data')
            ->components([
                ...InstitutionContributionFormSchema::components(includeMedia: true),
                Section::make(__('Submission Note'))
                    ->schema([
                        Textarea::make('proposer_note')
                            ->label(__('Anything the reviewer should know?'))
                            ->rows(4)
                            ->maxLength(2000),
                    ]),
            ]);
    }

    public function submit(ContributionWorkflowService $workflow, ContributionEntityMutationService $entityMutationService): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        $state = $this->contributionForm()->getState();
        $note = isset($state['proposer_note']) && is_string($state['proposer_note'])
            ? trim($state['proposer_note'])
            : null;

        unset($state['proposer_note']);

        $institution = $entityMutationService->createInstitution($state, $user);
        $this->contributionForm()->model($institution)->saveRelationships();

        $workflow->submitCreateRequest(
            ContributionSubjectType::Institution,
            $user,
            Arr::except($state, ['logo', 'cover', 'gallery']),
            $note !== '' ? $note : null,
            $institution,
        );

        $this->successToast(__('Institution submitted for review.'));

        $this->redirect(route('contributions.index'), navigate: true);
    }

    protected function contributionForm(): Schema
    {
        return $this->getForm('form') ?? throw new RuntimeException('Institution contribution form is not available.');
    }
}
