<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationFrequency;
use App\Support\Submission\PublicSubmissionLockService;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
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
        static::updated(function (User $user): void {
            if (! $user->wasChanged('phone_verified_at')) {
                return;
            }

            app(PublicSubmissionLockService::class)->syncForUser($user);
        });

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
            $user->eventCheckins()->each(fn ($checkin) => $checkin->delete());
            $user->savedSearches()->each(fn ($search) => $search->delete());
            $user->notificationEndpoints()->each(fn ($endpoint) => $endpoint->delete());
            $user->notificationPreferences()->each(fn ($preference) => $preference->delete());

            \Illuminate\Support\Facades\DB::table('followings')->where('user_id', $user->id)->delete();
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
        'timezone',
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

    /**
     * @return BelongsToMany<Institution, $this>
     */
    public function institutions(): BelongsToMany
    {
        return $this->belongsToMany(Institution::class, 'institution_user')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Speaker, $this>
     */
    public function speakers(): BelongsToMany
    {
        return $this->belongsToMany(Speaker::class, 'speaker_user')
            ->withTimestamps();
    }

    /**
     * @return HasMany<EventSubmission, $this>
     */
    public function eventSubmissions(): HasMany
    {
        return $this->hasMany(EventSubmission::class, 'submitted_by');
    }

    /**
     * @return HasMany<ModerationReview, $this>
     */
    public function moderationReviews(): HasMany
    {
        return $this->hasMany(ModerationReview::class, 'reviewer_id');
    }

    /**
     * @return HasMany<Report, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    /**
     * @return HasMany<Report, $this>
     */
    public function handledReports(): HasMany
    {
        return $this->hasMany(Report::class, 'handled_by');
    }

    /**
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * @return HasMany<EventCheckin, $this>
     */
    public function eventCheckins(): HasMany
    {
        return $this->hasMany(EventCheckin::class);
    }

    /**
     * @return HasMany<SocialAccount, $this>
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * @return HasMany<SavedSearch, $this>
     */
    public function savedSearches(): HasMany
    {
        return $this->hasMany(SavedSearch::class);
    }

    /**
     * @return BelongsToMany<Event, $this>
     */
    public function savedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_saves')->withTimestamps();
    }

    /**
     * @return MorphToMany<Speaker, $this>
     */
    public function followingSpeakers(): MorphToMany
    {
        return $this->morphedByMany(Speaker::class, 'followable', 'followings')
            ->withTimestamps();
    }

    public function follow(Model $followable): void
    {
        \Illuminate\Support\Facades\DB::table('followings')->insertOrIgnore([
            'user_id' => $this->id,
            'followable_id' => $followable->getKey(),
            'followable_type' => $followable->getMorphClass(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function unfollow(Model $followable): void
    {
        \Illuminate\Support\Facades\DB::table('followings')
            ->where('user_id', $this->id)
            ->where('followable_id', $followable->getKey())
            ->where('followable_type', $followable->getMorphClass())
            ->delete();
    }

    public function isFollowing(Model $followable): bool
    {
        return \Illuminate\Support\Facades\DB::table('followings')
            ->where('user_id', $this->id)
            ->where('followable_id', $followable->getKey())
            ->where('followable_type', $followable->getMorphClass())
            ->exists();
    }

    /**
     * @return BelongsToMany<Event, $this>
     */
    public function interestedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_interests')->withTimestamps();
    }

    /**
     * @return BelongsToMany<Event, $this>
     */
    public function goingEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_attendees')->withTimestamps();
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function ownedEvents(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * @return BelongsToMany<Event, $this, EventUser>
     */
    public function memberEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_user')
            ->using(EventUser::class)
            ->withPivot(['joined_at'])
            ->withTimestamps();
    }

    /**
     * @return MorphMany<NotificationEndpoint, $this>
     */
    public function notificationEndpoints(): MorphMany
    {
        return $this->morphMany(NotificationEndpoint::class, 'owner');
    }

    /**
     * @return MorphMany<NotificationPreference, $this>
     */
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

        $preference = $this->notificationPreferences()
            ->where('notification_key', $notificationKey)
            ->first();

        return $preference instanceof NotificationPreference ? $preference : null;
    }

    /**
     * @param  list<string>  $defaultChannels
     * @return list<string>
     */
    public function notificationChannelsFor(
        string $notificationKey,
        array $defaultChannels = [NotificationChannel::Email->value]
    ): array {
        $preference = $this->notificationPreferenceFor($notificationKey);

        if (! $preference instanceof NotificationPreference) {
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
        if ($panel->getId() === 'ahli') {
            return true;
        }

        if ($panel->getId() === 'admin') {
            return $this->hasGlobalRoleAssignment();
        }

        return $this->roles()->exists();
    }

    private function hasGlobalRoleAssignment(): bool
    {
        $modelHasRolesTable = (string) (config('permission.table_names.model_has_roles') ?? 'model_has_roles');
        $modelMorphKey = (string) (config('permission.column_names.model_morph_key') ?? 'model_id');
        $teamForeignKey = (string) (config('permission.column_names.team_foreign_key') ?? 'team_id');

        $query = DB::table($modelHasRolesTable)
            ->where($modelMorphKey, $this->getKey())
            ->where('model_type', $this->getMorphClass());

        if (config('permission.teams')) {
            $query->whereNull($teamForeignKey);
        }

        return $query->exists();
    }
}
