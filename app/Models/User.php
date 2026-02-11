<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationFrequency;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    #[\Override]
    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            $user->socialAccounts()->each(fn ($account) => $account->delete());
            $user->institutions()->detach();
            $user->speakers()->detach();
            $user->memberEvents()->detach();
            $user->savedEvents()->detach();
            $user->interestedEvents()->detach();
            $user->goingEvents()->detach();

            $user->ownedEvents()->update(['user_id' => null]);
            $user->eventSubmissions()->update(['submitted_by' => null]);
            $user->moderationReviews()->update(['reviewer_id' => null]);
            $user->reports()->update(['reporter_id' => null]);
            $user->handledReports()->update(['handled_by' => null]);
            $user->registrations()->each(fn ($reg) => $reg->delete());
            $user->savedSearches()->each(fn ($search) => $search->delete());
            $user->notificationEndpoints()->each(fn ($endpoint) => $endpoint->delete());
            $user->notificationPreferences()->each(fn ($preference) => $preference->delete());
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'email_verified_at',
        'phone_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function institutions(): BelongsToMany
    {
        return $this->belongsToMany(Institution::class, 'institution_user')
            ->withTimestamps();
    }

    public function speakers(): BelongsToMany
    {
        return $this->belongsToMany(Speaker::class, 'speaker_user')
            ->withTimestamps();
    }

    public function eventSubmissions(): HasMany
    {
        return $this->hasMany(EventSubmission::class, 'submitted_by');
    }

    public function moderationReviews(): HasMany
    {
        return $this->hasMany(ModerationReview::class, 'reviewer_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    public function handledReports(): HasMany
    {
        return $this->hasMany(Report::class, 'handled_by');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function savedSearches(): HasMany
    {
        return $this->hasMany(SavedSearch::class);
    }

    public function savedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_saves')->withTimestamps();
    }

    public function interestedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_interests')->withTimestamps();
    }

    public function goingEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_attendees')->withTimestamps();
    }

    public function ownedEvents(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function memberEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_user')
            ->using(EventUser::class)
            ->withPivot(['joined_at'])
            ->withTimestamps();
    }

    public function notificationEndpoints(): MorphMany
    {
        return $this->morphMany(NotificationEndpoint::class, 'owner');
    }

    public function notificationPreferences(): MorphMany
    {
        return $this->morphMany(NotificationPreference::class, 'owner');
    }

    public function notificationPreferenceFor(string $notificationKey): ?NotificationPreference
    {
        $loadedPreference = $this->relationLoaded('notificationPreferences')
            ? $this->notificationPreferences->firstWhere('notification_key', $notificationKey)
            : null;

        if ($loadedPreference instanceof NotificationPreference) {
            return $loadedPreference;
        }

        return $this->notificationPreferences()
            ->where('notification_key', $notificationKey)
            ->first();
    }

    public function notificationChannelsFor(
        string $notificationKey,
        array $defaultChannels = [NotificationChannel::Email->value]
    ): array {
        $preference = $this->notificationPreferenceFor($notificationKey);

        if (! $preference instanceof \App\Models\NotificationPreference) {
            return $defaultChannels;
        }

        if (! $preference->enabled || $preference->frequency === NotificationFrequency::Off) {
            return [];
        }

        $channels = is_array($preference->channels) && $preference->channels !== []
            ? $preference->channels
            : $defaultChannels;

        return array_values(array_unique(array_filter(
            array_map(fn (mixed $channel): string => (string) $channel, $channels),
            fn (string $channel): bool => $channel !== ''
        )));
    }

    public function shouldReceiveNotificationFor(string $notificationKey, ?string $frequency = null): bool
    {
        $preference = $this->notificationPreferenceFor($notificationKey);

        if (! $preference instanceof \App\Models\NotificationPreference) {
            return true;
        }

        if (! $preference->enabled || $preference->frequency === NotificationFrequency::Off) {
            return false;
        }

        if ($frequency !== null && in_array($preference->frequency, [NotificationFrequency::Daily, NotificationFrequency::Weekly], true)) {
            return $preference->frequency->value === $frequency;
        }

        return true;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        $registrar = app(PermissionRegistrar::class);
        $teams = $registrar->teams;
        $registrar->teams = false;

        try {
            return $this->roles()->exists();
        } finally {
            $registrar->teams = $teams;
        }
    }
}
