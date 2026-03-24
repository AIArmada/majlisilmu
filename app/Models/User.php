<?php

namespace App\Models;

use AIArmada\FilamentAuthz\Facades\Authz;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use App\Enums\NotificationChannel;
use App\Enums\NotificationDestinationStatus;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use App\Notifications\NotificationCenterMessage;
use App\Services\ShareTrackingService;
use App\Support\Submission\PublicSubmissionLockService;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasLocalePreference, MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUuids, MustVerifyEmail, Notifiable;

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
            $user->references()->detach();
            $user->memberEvents()->detach();
            $user->savedEvents()->detach();
            $user->goingEvents()->detach();

            $user->ownedEvents()->update(['user_id' => null]);
            $user->eventSubmissions()->update(['submitted_by' => null]);
            $user->contributionRequests()->update(['proposer_id' => null]);
            $user->reviewedContributionRequests()->update(['reviewer_id' => null]);
            $user->membershipClaims()->update(['claimant_id' => null]);
            $user->reviewedMembershipClaims()->update(['reviewer_id' => null]);
            $user->moderationReviews()->update(['moderator_id' => null]);
            $user->reports()->update(['reporter_id' => null]);
            $user->handledReports()->update(['handled_by' => null]);
            $user->registrations()->each(fn ($reg) => $reg->delete());
            $user->eventCheckins()->each(fn ($checkin) => $checkin->delete());
            $user->savedSearches()->each(fn ($search) => $search->delete());
            app(ShareTrackingService::class)->deleteUserTracking($user);
            $user->notificationSetting()->delete();
            $user->notificationRules()->each(fn ($rule) => $rule->delete());
            $user->notificationDestinations()->each(fn ($destination) => $destination->delete());
            $user->pendingNotifications()->each(fn ($notification) => $notification->delete());
            $user->notificationMessages()->each(fn ($message) => $message->delete());
            $user->notificationDeliveries()->each(fn ($delivery) => $delivery->delete());

            DB::table('followings')->where('user_id', $user->id)->delete();
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
        'daily_prayer_institution_id',
        'friday_prayer_institution_id',
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

    public function canSubmitDirectoryFeedback(): bool
    {
        return ! $this->isDirectoryFeedbackBlocked();
    }

    public function isDirectoryFeedbackBlocked(): bool
    {
        return Authz::withScope(null, function (): bool {
            $permission = Permission::query()
                ->where('name', 'feedback.blocked')
                ->where('guard_name', $this->getDefaultGuardName())
                ->first();

            return $permission instanceof Permission && $this->hasDirectPermission($permission);
        }, $this);
    }

    public function directoryFeedbackBanMessage(): string
    {
        return __('Akaun anda tidak dibenarkan menghantar cadangan kemaskini, tuntutan keahlian, atau laporan buat masa ini.');
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function dailyPrayerInstitution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'daily_prayer_institution_id');
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function fridayPrayerInstitution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'friday_prayer_institution_id');
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
     * @return MorphToMany<Role, $this, MorphPivot, 'pivot'>
     */
    public function globalRoles(): MorphToMany
    {
        $registrar = app(PermissionRegistrar::class);

        if (! $registrar->teams) {
            /** @var MorphToMany<Role, $this, MorphPivot, 'pivot'> $relation */
            $relation = $this->roles();

            return $relation;
        }

        $teamsKey = $registrar->teamsKey;
        $rolesTable = config('permission.table_names.roles', 'roles');
        /** @var class-string<Role> $roleModel */
        $roleModel = config('permission.models.role', Role::class);

        return $this->morphToMany(
            $roleModel,
            'model',
            config('permission.table_names.model_has_roles'),
            config('permission.column_names.model_morph_key'),
            $registrar->pivotRole,
        )
            ->withPivot($teamsKey)
            ->wherePivot($teamsKey)
            ->whereNull("{$rolesTable}.{$teamsKey}");
    }

    /**
     * @return BelongsToMany<Reference, $this>
     */
    public function references(): BelongsToMany
    {
        return $this->belongsToMany(Reference::class, 'reference_user')
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
     * @return HasMany<ContributionRequest, $this>
     */
    public function contributionRequests(): HasMany
    {
        return $this->hasMany(ContributionRequest::class, 'proposer_id');
    }

    /**
     * @return HasMany<ContributionRequest, $this>
     */
    public function reviewedContributionRequests(): HasMany
    {
        return $this->hasMany(ContributionRequest::class, 'reviewer_id');
    }

    /**
     * @return HasMany<MembershipClaim, $this>
     */
    public function membershipClaims(): HasMany
    {
        return $this->hasMany(MembershipClaim::class, 'claimant_id');
    }

    /**
     * @return HasMany<MembershipClaim, $this>
     */
    public function reviewedMembershipClaims(): HasMany
    {
        return $this->hasMany(MembershipClaim::class, 'reviewer_id');
    }

    /**
     * @return HasMany<ModerationReview, $this>
     */
    public function moderationReviews(): HasMany
    {
        return $this->hasMany(ModerationReview::class, 'moderator_id');
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

    /**
     * @return MorphToMany<Institution, $this>
     */
    public function followingInstitutions(): MorphToMany
    {
        return $this->morphedByMany(Institution::class, 'followable', 'followings')
            ->withTimestamps();
    }

    /**
     * @return MorphToMany<Reference, $this>
     */
    public function followingReferences(): MorphToMany
    {
        return $this->morphedByMany(Reference::class, 'followable', 'followings')
            ->withTimestamps();
    }

    /**
     * @return MorphToMany<Series, $this>
     */
    public function followingSeries(): MorphToMany
    {
        return $this->morphedByMany(Series::class, 'followable', 'followings')
            ->withTimestamps();
    }

    public function follow(Model $followable): void
    {
        DB::table('followings')->insertOrIgnore([
            'user_id' => $this->id,
            'followable_id' => $followable->getKey(),
            'followable_type' => $followable->getMorphClass(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function unfollow(Model $followable): void
    {
        DB::table('followings')
            ->where('user_id', $this->id)
            ->where('followable_id', $followable->getKey())
            ->where('followable_type', $followable->getMorphClass())
            ->delete();
    }

    public function isFollowing(Model $followable): bool
    {
        return DB::table('followings')
            ->where('user_id', $this->id)
            ->where('followable_id', $followable->getKey())
            ->where('followable_type', $followable->getMorphClass())
            ->exists();
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
     * @return MorphMany<NotificationMessage, $this>
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(NotificationMessage::class, 'notifiable')
            ->orderByDesc('occurred_at')
            ->orderByDesc('created_at');
    }

    /**
     * @return HasOne<NotificationSetting, $this>
     */
    public function notificationSetting(): HasOne
    {
        return $this->hasOne(NotificationSetting::class);
    }

    /**
     * @return HasMany<NotificationRule, $this>
     */
    public function notificationRules(): HasMany
    {
        return $this->hasMany(NotificationRule::class);
    }

    /**
     * @return HasMany<NotificationDestination, $this>
     */
    public function notificationDestinations(): HasMany
    {
        return $this->hasMany(NotificationDestination::class);
    }

    /**
     * @return HasMany<PendingNotification, $this>
     */
    public function pendingNotifications(): HasMany
    {
        return $this->hasMany(PendingNotification::class)->orderByDesc('occurred_at')->orderByDesc('created_at');
    }

    /**
     * @return MorphMany<NotificationMessage, $this>
     */
    public function notificationMessages(): MorphMany
    {
        return $this->notifications();
    }

    /**
     * @return HasMany<NotificationDelivery, $this>
     */
    public function notificationDeliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    public function preferredLocale(): string
    {
        $locale = $this->notificationSetting()->value('locale');

        return is_string($locale) && $locale !== ''
            ? $locale
            : config('app.locale');
    }

    public function preferredTimezone(): string
    {
        $timezone = $this->notificationSetting()->value('timezone');

        if (is_string($timezone) && $timezone !== '') {
            return $timezone;
        }

        return is_string($this->timezone) && $this->timezone !== ''
            ? $this->timezone
            : (string) config('app.timezone', 'UTC');
    }

    #[\Override]
    public function sendEmailVerificationNotification(): void
    {
        if (! is_string($this->email) || trim($this->email) === '') {
            return;
        }

        $this->notify(new VerifyEmailNotification);
    }

    #[\Override]
    public function sendPasswordResetNotification($token): void
    {
        if (! is_string($this->email) || trim($this->email) === '') {
            return;
        }

        $this->notify(new ResetPasswordNotification((string) $token));
    }

    /**
     * @return array<int, string>|string|null
     */
    public function routeNotificationForMail(Notification $notification): array|string|null
    {
        if (! $notification instanceof NotificationCenterMessage) {
            return $this->email;
        }

        return $this->notificationDestinations()
            ->where('channel', NotificationChannel::Email->value)
            ->where('status', NotificationDestinationStatus::Active->value)
            ->orderByDesc('is_primary')
            ->value('address');
    }

    /**
     * @return Collection<int, NotificationDestination>
     */
    public function routeNotificationForPush(Notification $notification): Collection
    {
        return $this->notificationDestinations()
            ->where('channel', NotificationChannel::Push->value)
            ->where('status', NotificationDestinationStatus::Active->value)
            ->orderByDesc('is_primary')
            ->get();
    }

    /**
     * @return Collection<int, NotificationDestination>
     */
    public function routeNotificationForWhatsapp(Notification $notification): Collection
    {
        return $this->notificationDestinations()
            ->where('channel', NotificationChannel::Whatsapp->value)
            ->where('status', NotificationDestinationStatus::Active->value)
            ->orderByDesc('is_primary')
            ->get();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'ahli') {
            return $this->hasAhliPanelAccess();
        }

        if ($panel->getId() === 'admin') {
            return $this->hasApplicationAdminAccess();
        }

        return $this->roles()->exists();
    }

    public function hasAhliPanelAccess(): bool
    {
        return $this->institutions()->exists()
            || $this->speakers()->exists()
            || $this->references()->exists()
            || $this->memberEvents()->exists();
    }

    public function hasApplicationAdminAccess(): bool
    {
        return $this->hasGlobalRoleAssignment();
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
