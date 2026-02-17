<?php

namespace App\Livewire\Pages\Dashboard;

use App\Models\Event;
use App\Models\Institution;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Institution Dashboard')]
class InstitutionDashboard extends Component
{
    use WithPagination;

    #[Url(as: 'institution')]
    public ?string $institutionId = null;

    public function mount(): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        $this->institutionId = $this->normalizeInstitutionId($this->institutionId);

        if ($this->institutionId === null) {
            $this->institutionId = $this->availableInstitutionsQuery($user)
                ->orderBy('name')
                ->value('institutions.id');
        }

        if ($this->institutionId !== null && ! $this->availableInstitutionsQuery($user)->whereKey($this->institutionId)->exists()) {
            abort(403);
        }
    }

    public function updatedInstitutionId(?string $institutionId): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $this->institutionId = $this->normalizeInstitutionId($institutionId);

        if ($this->institutionId === null) {
            $this->resetPage('institution_events_page');
            $this->resetPage('institution_registrations_page');

            return;
        }

        if (! $this->availableInstitutionsQuery($user)->whereKey($this->institutionId)->exists()) {
            abort(403);
        }

        $this->resetPage('institution_events_page');
        $this->resetPage('institution_registrations_page');
    }

    /**
     * @return Collection<int, Institution>
     */
    #[Computed]
    public function institutions(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return collect();
        }

        return $this->availableInstitutionsQuery($user)
            ->withCount([
                'events' => function (Builder $query) {
                    $query->active();
                },
                'members',
            ])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function selectedInstitution(): ?Institution
    {
        if ($this->institutionId === null || $this->institutionId === '') {
            return null;
        }

        /** @var Institution|null $institution */
        $institution = $this->institutions()
            ->firstWhere('id', $this->institutionId);

        return $institution;
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function institutionStats(): array
    {
        $institution = $this->selectedInstitution();

        if (! $institution instanceof Institution) {
            return [
                'events_count' => 0,
                'registrations_count' => 0,
                'members_count' => 0,
                'venues_count' => 0,
            ];
        }

        return [
            'events_count' => (int) ($institution->events_count ?? 0),
            'registrations_count' => Registration::query()
                ->whereHas('event', fn (Builder $eventQuery) => $eventQuery->where('institution_id', $institution->id))
                ->count(),
            'members_count' => (int) ($institution->members_count ?? 0),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Event>
     */
    #[Computed]
    public function institutionEvents(): LengthAwarePaginator
    {
        $institution = $this->selectedInstitution();

        if (! $institution instanceof Institution) {
            return Event::query()
                ->whereRaw('1 = 0')
                ->paginate(perPage: 8, pageName: 'institution_events_page');
        }

        return Event::query()
            ->where('institution_id', $institution->id)
            ->with(['venue:id,name'])
            ->withCount('registrations')
            ->orderBy('starts_at', 'desc')
            ->paginate(perPage: 8, pageName: 'institution_events_page');
    }

    /**
     * @return LengthAwarePaginator<int, Registration>
     */
    #[Computed]
    public function institutionRegistrations(): LengthAwarePaginator
    {
        $institution = $this->selectedInstitution();

        if (! $institution instanceof Institution) {
            return Registration::query()
                ->whereRaw('1 = 0')
                ->paginate(perPage: 8, pageName: 'institution_registrations_page');
        }

        return Registration::query()
            ->whereHas('event', fn (Builder $eventQuery) => $eventQuery->where('institution_id', $institution->id))
            ->with([
                'event' => fn ($query) => $query
                    ->select('id', 'title', 'slug', 'starts_at'),
            ])
            ->latest()
            ->paginate(perPage: 8, pageName: 'institution_registrations_page');
    }

    /**
     * @return BelongsToMany<Institution, User>
     */
    protected function availableInstitutionsQuery(User $user): BelongsToMany
    {
        return $user->institutions();
    }

    protected function normalizeInstitutionId(?string $institutionId): ?string
    {
        if ($institutionId === null || trim($institutionId) === '') {
            return null;
        }

        return $institutionId;
    }

    public function render(): View
    {
        return view('livewire.pages.dashboard.institution-dashboard');
    }
}
