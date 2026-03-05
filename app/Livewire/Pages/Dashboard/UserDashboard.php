<?php

namespace App\Livewire\Pages\Dashboard;

use App\Enums\NotificationChannel;
use App\Enums\NotificationFrequency;
use App\Enums\NotificationPreferenceKey;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\Registration;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Validation\Rule;
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

    public bool $digestNotificationsEnabled = true;

    public string $digestNotificationFrequency = NotificationFrequency::Daily->value;

    /**
     * @var array<int, string>
     */
    public array $digestNotificationChannels = [NotificationChannel::Email->value];

    public function mount(): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        $this->hydrateDigestNotificationPreferences($user);
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
                'checkins_count' => 0,
                'saved_events_count' => 0,
                'saved_searches_count' => 0,
            ];
        }

        return [
            'institutions_count' => $user->institutions()->count(),
            'events_count' => $this->myEventsQuery($user)->count(),
            'registrations_count' => $user->registrations()->count(),
            'checkins_count' => EventCheckin::query()->where('user_id', $user->id)->count(),
            'saved_events_count' => $this->mySavedEventsQuery($user)->count(),
            'saved_searches_count' => $user->savedSearches()->count(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Event>
     */
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

    /**
     * @return LengthAwarePaginator<int, Registration>
     */
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

    /**
     * @return LengthAwarePaginator<int, EventCheckin>
     */
    #[Computed]
    public function myCheckins(): LengthAwarePaginator
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return EventCheckin::query()
            ->where('user_id', $user->id)
            ->with([
                'event' => fn ($query) => $query
                    ->select('id', 'title', 'slug', 'starts_at', 'institution_id', 'venue_id')
                    ->with([
                        'institution:id,name',
                        'venue:id,name',
                    ]),
            ])
            ->orderByDesc('checked_in_at')
            ->paginate(perPage: 8, pageName: 'my_checkins_page');
    }

    /**
     * @return LengthAwarePaginator<int, Event&object{pivot: \Illuminate\Database\Eloquent\Relations\Pivot}>
     */
    #[Computed]
    public function mySavedEvents(): LengthAwarePaginator
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $this->mySavedEventsQuery($user)
            ->with([
                'institution:id,name',
                'venue:id,name',
            ])
            ->orderBy('event_saves.created_at', 'desc')
            ->paginate(perPage: 8, pageName: 'my_saved_events_page');
    }

    /**
     * @return LengthAwarePaginator<int, SavedSearch>
     */
    #[Computed]
    public function mySavedSearches(): LengthAwarePaginator
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return SavedSearch::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(perPage: 8, pageName: 'my_saved_searches_page');
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function digestFrequencyOptions(): array
    {
        return [
            NotificationFrequency::Instant->value => __('Instant'),
            NotificationFrequency::Daily->value => __('Daily'),
            NotificationFrequency::Weekly->value => __('Weekly'),
            NotificationFrequency::Off->value => __('Off'),
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function digestChannelOptions(): array
    {
        return [
            NotificationChannel::Email->value => __('Email'),
            NotificationChannel::InApp->value => __('In-app'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toSavedSearchQueryParams(SavedSearch $savedSearch): array
    {
        /** @var array<string, mixed> $params */
        $params = array_merge(
            ['search' => $savedSearch->query],
            is_array($savedSearch->filters) ? $savedSearch->filters : []
        );

        if ($savedSearch->lat !== null && $savedSearch->lng !== null) {
            $params = array_merge($params, [
                'lat' => $savedSearch->lat,
                'lng' => $savedSearch->lng,
                'radius_km' => $savedSearch->radius_km,
                'sort' => 'distance',
            ]);
        }

        return array_filter($params, static function (mixed $value): bool {
            if ($value === null || $value === '') {
                return false;
            }

            if (is_array($value)) {
                return $value !== [];
            }

            return true;
        });
    }

    public function saveDigestNotificationPreferences(): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        $validated = $this->validate([
            'digestNotificationsEnabled' => ['required', 'boolean'],
            'digestNotificationFrequency' => ['required', Rule::in($this->allowedDigestFrequencies())],
            'digestNotificationChannels' => ['present', 'array'],
            'digestNotificationChannels.*' => [Rule::in($this->allowedDigestChannels())],
        ]);

        $requestedFrequency = $validated['digestNotificationsEnabled']
            ? $validated['digestNotificationFrequency']
            : NotificationFrequency::Off->value;

        $enabled = $requestedFrequency !== NotificationFrequency::Off->value;
        $channels = $enabled
            ? array_values(array_unique($validated['digestNotificationChannels']))
            : [];

        if ($enabled && $channels === []) {
            $channels = [NotificationChannel::Email->value];
        }

        $user->notificationPreferences()->updateOrCreate(
            ['notification_key' => NotificationPreferenceKey::SavedSearchDigest->value],
            [
                'enabled' => $enabled,
                'frequency' => $requestedFrequency,
                'channels' => $channels,
                'timezone' => config('app.timezone'),
            ]
        );

        $this->digestNotificationsEnabled = $enabled;
        $this->digestNotificationFrequency = $requestedFrequency;
        $this->digestNotificationChannels = $channels;

        session()->flash('digest_preferences_status', __('Digest notification preferences updated.'));
    }

    /**
     * @return Builder<Event>
     */
    protected function myEventsQuery(User $user): Builder
    {
        return Event::query()
            ->where(function (Builder $eventQuery) use ($user): void {
                $eventQuery
                    ->where('user_id', $user->id)
                    ->orWhere('submitter_id', $user->id)
                    ->orWhereIn('id', \App\Models\EventSubmission::where('submitted_by', $user->id)->select('event_id'))
                    ->orWhereIn('institution_id', $user->institutions()->select('institutions.id'));
            });
    }

    /**
     * @return BelongsToMany<Event, User>
     */
    protected function mySavedEventsQuery(User $user): BelongsToMany
    {
        return $user->savedEvents()->active();
    }

    protected function hydrateDigestNotificationPreferences(User $user): void
    {
        $preference = $user->notificationPreferenceFor(NotificationPreferenceKey::SavedSearchDigest->value);

        if (! $preference instanceof \App\Models\NotificationPreference) {
            return;
        }

        $frequency = $preference->frequency;

        if (! $frequency instanceof NotificationFrequency) {
            return;
        }

        $this->digestNotificationFrequency = $frequency->value;
        $this->digestNotificationsEnabled = $preference->enabled
            && $frequency !== NotificationFrequency::Off;

        $rawChannels = is_array($preference->channels) ? $preference->channels : [];
        $channels = array_values(array_filter(
            array_map(static fn (mixed $channel): string => (string) $channel, $rawChannels),
            fn (string $channel): bool => in_array($channel, $this->allowedDigestChannels(), true)
        ));

        if ($this->digestNotificationsEnabled) {
            $this->digestNotificationChannels = $channels !== []
                ? $channels
                : [NotificationChannel::Email->value];

            return;
        }

        $this->digestNotificationChannels = [];
    }

    /**
     * @return array<int, string>
     */
    protected function allowedDigestFrequencies(): array
    {
        return array_map(
            static fn (NotificationFrequency $frequency): string => $frequency->value,
            NotificationFrequency::cases()
        );
    }

    /**
     * @return array<int, string>
     */
    protected function allowedDigestChannels(): array
    {
        return [
            NotificationChannel::Email->value,
            NotificationChannel::InApp->value,
        ];
    }

    public function render(): View
    {
        return view('livewire.pages.dashboard.user-dashboard');
    }
}
