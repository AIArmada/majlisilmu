<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function institutions(): BelongsToMany
    {
        return $this->belongsToMany(Institution::class, 'institution_members')
            ->withTimestamps();
    }

    public function speakers(): BelongsToMany
    {
        return $this->belongsToMany(Speaker::class, 'speaker_members')
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
        return $this->belongsToMany(Event::class, 'event_members')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
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
