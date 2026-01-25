<?php

namespace App\Models;

use App\Enums\EventAgeGroup;
use App\Enums\EventGenderRestriction;
use App\Enums\EventType;
use App\Enums\PrayerOffset;
use App\Enums\PrayerReference;
use App\Enums\TimingMode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Laravel\Scout\Searchable;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\DeletedModels\Models\Concerns\KeepsDeletedModels;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\ModelStates\HasStates;

class Event extends Model implements AuditableContract, HasMedia
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use \App\Models\Concerns\HasAddress, \App\Models\Concerns\HasDonationChannels, \App\Models\Concerns\HasLanguages, Auditable, HasFactory, HasStates, HasUuids, InteractsWithMedia, KeepsDeletedModels, Searchable;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'institution_id',
        'submitter_id',
        'venue_id',
        'series_id',

        'title',
        'slug',
        'description',
        'starts_at',
        'ends_at',
        'timezone',
        'timing_mode',
        'prayer_reference',
        'prayer_offset',
        'prayer_display_text',
        'prayer_calc_lat',
        'prayer_calc_lng',
        'event_type',
        'gender_restriction',
        'age_group',
        'children_allowed',
        'visibility',
        'status',
        'live_url',
        'recording_url',
        'registration_required',
        'capacity',
        'registration_opens_at',
        'registration_closes_at',
        'views_count',
        'saves_count',
        'registrations_count',
        'interests_count',
        'going_count',
        'published_at',
        'escalated_at',
        'is_priority',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'status' => \App\States\EventStatus\EventStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'timing_mode' => TimingMode::class,
            'prayer_reference' => PrayerReference::class,
            'prayer_offset' => PrayerOffset::class,
            'prayer_calc_lat' => 'decimal:8',
            'prayer_calc_lng' => 'decimal:8',
            'event_type' => EventType::class,
            'gender_restriction' => EventGenderRestriction::class,
            'age_group' => EventAgeGroup::class,
            'children_allowed' => 'boolean',
            'registration_required' => 'boolean',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'capacity' => 'integer',
            'views_count' => 'integer',
            'saves_count' => 'integer',
            'registrations_count' => 'integer',
            'interests_count' => 'integer',
            'going_count' => 'integer',
            'published_at' => 'datetime',
            'escalated_at' => 'datetime',
            'is_priority' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * Determine if the model should be searchable.
     * Only index approved public events per documentation B8a.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->status->equals(\App\States\EventStatus\Approved::class) && $this->visibility === 'public';
    }

    /**
     * Get the indexable data array for the model.
     * Schema matches documentation B8.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing(['institution', 'venue', 'speakers', 'topics', 'address']);

        $array = [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description ?? '',
            'slug' => $this->slug,
            'speaker_names' => $this->speakers->pluck('name')->implode(', '),
            'institution_name' => $this->institution?->name ?? '',
            'venue_name' => $this->venue?->name ?? '',
            'event_type' => $this->event_type?->value ?? 'kuliah',
            'gender_restriction' => $this->gender_restriction?->value ?? 'all',
            'age_group' => $this->age_group?->value ?? 'all_ages',
            'children_allowed' => $this->children_allowed ?? true,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'topic_ids' => $this->topics->pluck('id')->toArray(),
            'speaker_ids' => $this->speakers->pluck('id')->toArray(),
            'starts_at' => $this->starts_at?->timestamp ?? 0,
            'ends_at' => $this->ends_at?->timestamp,
            'saves_count' => $this->saves_count ?? 0,
            'registrations_count' => $this->registrations_count ?? 0,
        ];

        // Add geolocation if available
        if ($this->venue?->lat && $this->venue?->lng) {
            $array['location'] = [$this->venue->lat, $this->venue->lng];
        } elseif ($this->address?->lat) {
            $array['location'] = [$this->address->lat, $this->address->lng];
        }

        return $array;
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitter_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_members')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
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



    public function speakers(): BelongsToMany
    {
        return $this->belongsToMany(Speaker::class, 'event_speakers')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'event_topics')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function mediaLinks(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\App\Models\EventMedia::class, 'mediable');
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

    public function interestedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_interests')->withTimestamps();
    }

    public function goingBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_attendees')->withTimestamps();
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'entity');
    }

    public function donationChannel(): MorphOne
    {
        return $this->morphOne(DonationChannel::class, 'donatable')
            ->where('is_default', true);
    }

    /**
     * Register media collections for Spatie Media Library.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('poster')
            ->singleFile();

        $this->addMediaCollection('gallery');
    }

    /**
     * Check if this event uses prayer-relative timing.
     */
    public function isPrayerRelative(): bool
    {
        return $this->timing_mode === TimingMode::PrayerRelative;
    }

    /**
     * Get the human-readable timing display text.
     * Returns prayer-relative text (e.g., "Selepas Maghrib") or formatted time.
     */
    public function getTimingDisplayAttribute(): string
    {
        if ($this->isPrayerRelative() && $this->prayer_display_text) {
            return $this->prayer_display_text;
        }

        // Fallback to formatted time
        return $this->starts_at?->format('g:i A') ?? '';
    }

    /**
     * Get the full timing display with date context.
     */
    public function getFullTimingDisplayAttribute(): string
    {
        $date = $this->starts_at?->translatedFormat('l, j F Y') ?? '';
        $time = $this->timing_display;

        return "{$date} - {$time}";
    }

    /**
     * Get coordinates for prayer time calculation.
     * Falls back to venue coordinates if specific coords not set.
     */
    public function getPrayerCoordinatesAttribute(): ?array
    {
        // Use event-specific coordinates if set
        if ($this->prayer_calc_lat && $this->prayer_calc_lng) {
            return [
                'lat' => (float) $this->prayer_calc_lat,
                'lng' => (float) $this->prayer_calc_lng,
            ];
        }

        // Fall back to venue coordinates
        if ($this->venue && $this->venue->lat && $this->venue->lng) {
            return [
                'lat' => (float) $this->venue->lat,
                'lng' => (float) $this->venue->lng,
            ];
        }

        return null;
    }

    /**
     * Get the card image URL for frontend.
     * Priority: Poster collection -> Institution logo -> Speaker avatar -> Default.
     */
    public function getCardImageUrlAttribute(): string
    {
        // 1. Poster from Spatie Media Library
        if ($this->hasMedia('poster')) {
            return $this->getFirstMediaUrl('poster');
        }

        // 2. Institution logo
        if ($this->institution && $this->institution->hasMedia('logo')) {
            return $this->institution->getFirstMediaUrl('logo');
        }

        // 3. Fallback to first speaker's avatar
        if ($this->speakers->isNotEmpty() && $url = $this->speakers->first()->avatar_url) {
            return $url;
        }

        // 4. Global default (placeholder)
        return asset('images/default-event-placeholder.png');
    }

    /**
     * Map 'genre' to 'event_type' for compatibility.
     */
    public function getGenreAttribute(): mixed
    {
        return $this->event_type;
    }

    /**
     * Map 'audience' to 'age_group' for compatibility.
     */
    public function getAudienceAttribute(): mixed
    {
        return $this->age_group;
    }

    /**
     * Map 'language' to primary language from relationship.
     */
    public function getLanguageAttribute(): string
    {
        // Check if there's a 'language' column first (to avoid recursion if we add it later)
        if (array_key_exists('language', $this->attributes)) {
            return $this->attributes['language'];
        }

        return $this->languages->first()?->code ?? 'ms';
    }
}
