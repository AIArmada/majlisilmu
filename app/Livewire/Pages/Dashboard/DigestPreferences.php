<?php

namespace App\Livewire\Pages\Dashboard;

use App\Enums\NotificationChannel;
use App\Enums\NotificationFrequency;
use App\Enums\NotificationPreferenceKey;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class DigestPreferences extends Component
{
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

    protected function hydrateDigestNotificationPreferences(User $user): void
    {
        $preference = $user->notificationPreferenceFor(NotificationPreferenceKey::SavedSearchDigest->value);

        if (! $preference instanceof NotificationPreference) {
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
        return view('livewire.pages.dashboard.digest-preferences');
    }
}
