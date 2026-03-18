<?php

namespace App\Livewire\Pages\Contributions;

use App\Actions\Contributions\SubmitStagedContributionCreateAction;
use App\Enums\ContributionSubjectType;
use App\Forms\SpeakerContributionFormSchema;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Speaker;
use App\Models\User;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

#[Layout('layouts.app')]
#[Title('Submit Speaker')]
class SubmitSpeaker extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithToasts;
    use WithFileUploads;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->contributionForm()->fill([
            'gender' => 'male',
            'state_id' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->model(new Speaker)
            ->statePath('data')
            ->components(SpeakerContributionFormSchema::components(includeMedia: true));
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
