<?php

namespace App\Livewire\Pages\Contributions;

use App\Actions\Contributions\SubmitStagedContributionCreateAction;
use App\Enums\ContributionSubjectType;
use App\Forms\InstitutionContributionFormSchema;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Institution;
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
#[Title('Submit Institution')]
class SubmitInstitution extends Component implements HasActions, HasForms
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
            'type' => 'masjid',
            'state_id' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->model(new Institution)
            ->statePath('data')
            ->components(InstitutionContributionFormSchema::components(includeMedia: true, requireGoogleMaps: true));
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
