<?php

namespace App\Livewire\Pages\Dashboard;

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('My Dashboard')]
class UserDashboard extends Component
{
    use WithPagination;

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function profileStats(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [
                'institutions_count' => 0,
                'events_count' => 0,
                'registrations_count' => 0,
                'saved_searches_count' => 0,
            ];
        }

        return [
            'institutions_count' => $user->institutions()->count(),
            'events_count' => $this->myEventsQuery($user)->count(),
            'registrations_count' => $user->registrations()->count(),
            'saved_searches_count' => $user->savedSearches()->count(),
        ];
    }

    #[Computed]
    public function myEvents(): LengthAwarePaginator
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $this->myEventsQuery($user)
            ->with([
                'institution:id,name',
                'venue:id,name',
            ])
            ->orderBy('starts_at', 'desc')
            ->paginate(perPage: 8, pageName: 'my_events_page');
    }

    #[Computed]
    public function myRegistrations(): LengthAwarePaginator
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return Registration::query()
            ->where('user_id', $user->id)
            ->with([
                'event' => fn ($query) => $query
                    ->select('id', 'title', 'slug', 'starts_at', 'institution_id', 'venue_id')
                    ->with([
                        'institution:id,name',
                        'venue:id,name',
                    ]),
            ])
            ->latest()
            ->paginate(perPage: 8, pageName: 'my_registrations_page');
    }

    protected function myEventsQuery(User $user): Builder
    {
        return Event::query()
            ->where(function (Builder $eventQuery) use ($user): void {
                $eventQuery
                    ->where('user_id', $user->id)
                    ->orWhereIn('institution_id', $user->institutions()->select('institutions.id'));
            });
    }

    public function render(): View
    {
        return view('livewire.pages.dashboard.user-dashboard');
    }
}
