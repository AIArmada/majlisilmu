<?php

namespace App\Livewire\Pages\Contributions;

use App\Actions\Contributions\SubmitStagedContributionCreateAction;
use App\Enums\ContributionSubjectType;
use App\Forms\InstitutionContributionFormSchema;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Institution;
use App\Models\User;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
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

    public function submit(SubmitStagedContributionCreateAction $submitStagedContributionCreateAction): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        $submitStagedContributionCreateAction->handle(
            ContributionSubjectType::Institution,
            $this->contributionForm()->getState(),
            $user,
            function (Institution $institution): void {
                $this->contributionForm()->model($institution)->saveRelationships();
            },
        );

        $this->successToast(__('Institution submitted for review.'));

        $this->redirect(route('contributions.index'), navigate: true);
    }

    protected function contributionForm(): Schema
    {
        return $this->getForm('form') ?? throw new RuntimeException('Institution contribution form is not available.');
    }
}
