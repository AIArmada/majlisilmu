<?php

namespace App\Livewire\Pages\Dashboard;

use App\Models\User;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class AccountSettings extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $timezone = '';

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

        session()->flash('account_settings_status', __('Account settings updated.'));
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
