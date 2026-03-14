<?php

namespace App\Livewire\Pages\Contributions;

use App\Actions\Contributions\SubmitStagedContributionCreateAction;
use App\Enums\ContributionSubjectType;
use App\Forms\SpeakerContributionFormSchema;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Speaker;
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
#[Title('Submit Speaker')]
class SubmitSpeaker extends Component implements HasForms
{
    use InteractsWithForms;
    use InteractsWithToasts;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->contributionForm()->fill([
            'gender' => 'male',
            'address' => [
                'country_id' => 132,
            ],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->model(new Speaker)
            ->statePath('data')
            ->components([
                ...SpeakerContributionFormSchema::components(includeMedia: true),
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
            ContributionSubjectType::Speaker,
            $this->contributionForm()->getState(),
            $user,
            function (Speaker $speaker): void {
                $this->contributionForm()->model($speaker)->saveRelationships();
            },
        );

        $this->successToast(__('Speaker submitted for review.'));

        $this->redirect(route('contributions.index'), navigate: true);
    }

    protected function contributionForm(): Schema
    {
        return $this->getForm('form') ?? throw new RuntimeException('Speaker contribution form is not available.');
    }
}
