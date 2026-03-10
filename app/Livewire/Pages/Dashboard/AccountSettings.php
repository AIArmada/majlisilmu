<?php

namespace App\Livewire\Pages\Dashboard;

use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\User;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class AccountSettings extends Component implements HasForms
{
    use InteractsWithForms;
    use InteractsWithToasts;

    protected bool $syncingInheritedTriggerState = false;

    #[Url(as: 'tab')]
    public string $tab = 'profile';

    /**
     * @var array{name: string, email: string, phone: string, timezone: string}
     */
    public array $formData = [
        'name' => '',
        'email' => '',
        'phone' => '',
        'timezone' => '',
    ];

    /**
     * @var array<string, mixed>
     */
    public array $notificationSettingsState = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $notificationFamiliesState = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $notificationTriggersState = [];

    /**
     * @var array<string, list<array<string, mixed>>>
     */
    public array $notificationGroupedTriggers = [];

    /**
     * @var array<string, mixed>
     */
    public array $notificationOptions = [];

    /**
     * @var array<string, mixed>
     */
    public array $notificationDestinations = [];

    /**
     * @var array<int, string>
     */
    public array $preferredChannelSlots = ['', '', '', ''];

    /**
     * @var array<int, string>
     */
    public array $fallbackChannelSlots = ['', '', '', ''];

    public function mount(): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        $this->name = $user->name;
        $this->email = (string) ($user->email ?? '');
        $this->phone = (string) ($user->phone ?? '');
        $this->timezone = (string) ($user->timezone ?? '');
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function timezoneOptions(): array
    {
        return collect(DateTimeZone::listIdentifiers())
            ->mapWithKeys(fn (string $timezone): array => [$timezone => $timezone])
            ->all();
    }

    public function saveAccountSettings(): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        $this->name = trim($this->name);
        $this->email = trim($this->email);
        $this->phone = trim($this->phone);
        $this->timezone = trim($this->timezone);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'required_without:phone',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($user->id),
            ],
            'phone' => [
                'nullable',
                'required_without:email',
                'string',
                'max:20',
                Rule::unique(User::class, 'phone')->ignore($user->id),
            ],
            'timezone' => ['nullable', 'string', Rule::in($this->allowedTimezones())],
        ]);

        $normalizedEmail = filled($validated['email']) ? trim((string) $validated['email']) : null;
        $normalizedPhone = filled($validated['phone']) ? trim((string) $validated['phone']) : null;
        $normalizedTimezone = filled($validated['timezone']) ? (string) $validated['timezone'] : null;

        $emailChanged = $normalizedEmail !== $user->email;
        $phoneChanged = $normalizedPhone !== $user->phone;

        $user->forceFill([
            'name' => trim((string) $validated['name']),
            'email' => $normalizedEmail,
            'phone' => $normalizedPhone,
            'timezone' => $normalizedTimezone,
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
            'phone_verified_at' => $phoneChanged ? null : $user->phone_verified_at,
        ])->save();

        $this->email = (string) ($user->email ?? '');
        $this->phone = (string) ($user->phone ?? '');
        $this->timezone = (string) ($user->timezone ?? '');

        if (request()->hasSession()) {
            if ($normalizedTimezone !== null) {
                request()->session()->put('user_timezone', $normalizedTimezone);
            } else {
                request()->session()->forget('user_timezone');
            }
        }

        $this->successToast(__('Account settings updated.'));
        $this->hydrateNotificationCenter($freshUser);
    }

    public function saveNotificationPreferences(): void
    {
        $user = $this->currentUser();

        $this->settingsManager()->save($user, $this->notificationPayload());
        $this->hydrateNotificationCenter($user->fresh());

        $this->successToast(__('notifications.flash.updated'));
        $this->tab = 'notifications';
    }

    protected function hydrateNotificationCenter(User $user): void
    {
        $state = $this->settingsManager()->stateFor($user);

        $this->notificationSettingsState = $state['settings'];
        $this->notificationFamiliesState = $state['families'];
        $this->notificationTriggersState = $state['triggers'];
        $this->notificationGroupedTriggers = $state['grouped_triggers'];
        $this->notificationOptions = $state['options'];
        $this->notificationDestinations = $state['destinations'];
        $this->preferredChannelSlots = array_pad(
            array_values($this->notificationSettingsState['preferred_channels'] ?? []),
            4,
            ''
        );
        $this->fallbackChannelSlots = array_pad(
            array_values($this->notificationSettingsState['fallback_channels'] ?? []),
            4,
            ''
        );
        $this->syncInheritedTriggerStates();
    }

    /**
     * @return array<string, mixed>
     */
    protected function notificationPayload(): array
    {
        $preferredChannels = collect($this->preferredChannelSlots)
            ->map(static fn (mixed $value): string => (string) $value)
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();
        $fallbackChannels = collect($this->fallbackChannelSlots)
            ->map(static fn (mixed $value): string => (string) $value)
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();

        $settings = $this->notificationSettingsState;
        $settings['preferred_channels'] = $preferredChannels;
        $settings['fallback_channels'] = $fallbackChannels;
        $settings['timezone'] = (string) ($this->notificationSettingsState['timezone'] ?? config('app.timezone'));

        return [
            'settings' => $settings,
            'families' => $this->notificationFamiliesState,
            'triggers' => $this->notificationTriggersState,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function allowedTimezones(): array
    {
        return DateTimeZone::listIdentifiers();
    }

    public function render(): View
    {
        return view('livewire.pages.dashboard.account-settings');
    }
}
