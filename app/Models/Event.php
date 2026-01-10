<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Event extends Model
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'institution_id',
        'venue_id',
        'series_id',
        'title',
        'slug',
        'description',
        'state_id',
        'district_id',
        'starts_at',
        'ends_at',
        'timezone',
        'language',
        'genre',
        'audience',
        'visibility',
        'status',
        'livestream_url',
        'recording_url',
        'donation_account_id',
        'registration_required',
        'capacity',
        'registration_opens_at',
        'registration_closes_at',
        'views_count',
        'saves_count',
        'registrations_count',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_required' => 'boolean',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'capacity' => 'integer',
            'views_count' => 'integer',
            'saves_count' => 'integer',
            'registrations_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function donationAccount(): BelongsTo
    {
        return $this->belongsTo(DonationAccount::class);
    }

    public function speakers(): BelongsToMany
    {
        return $this->belongsToMany(Speaker::class, 'event_speakers')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'event_topics')->withTimestamps();
    }

    public function mediaLinks(): HasMany
    {
        return $this->hasMany(EventMediaLink::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(EventSubmission::class);
    }

    public function moderationReviews(): HasMany
    {
        return $this->hasMany(ModerationReview::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_saves')->withTimestamps();
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'entity');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'entity');
    }
}
