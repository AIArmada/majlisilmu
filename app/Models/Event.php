<?php

namespace App\Models;

use AIArmada\FilamentAuthz\Facades\Authz;
use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Enums\PrayerOffset;
use App\Enums\PrayerReference;
use App\Enums\TagType;
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
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\ModelStates\HasStates;
use Spatie\Tags\HasTags;

class Event extends Model implements AuditableContract, HasMedia
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use \App\Models\Concerns\HasAddress, \App\Models\Concerns\HasDonationChannels, \App\Models\Concerns\HasLanguages, Auditable, HasFactory, HasStates, HasTags, HasUuids, InteractsWithMedia, KeepsDeletedModels, Searchable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::deleting(function (Event $event) {
            $event->members()->detach();
            $event->speakers()->detach();
            $event->references()->detach();
            $event->savedBy()->detach();
            $event->interestedBy()->detach();
            $event->goingBy()->detach();

            $event->registrations()->each(fn (\App\Models\Registration $registration) => $registration->delete());
            $event->submissions()->each(fn (\App\Models\EventSubmission $submission) => $submission->delete());
            $event->moderationReviews()->each(fn (\App\Models\ModerationReview $review) => $review->delete());
            $event->mediaLinks()->each(fn (\App\Models\MediaLink $mediaLink) => $mediaLink->delete());

            // Note: MediaLibrary works automatically via InteractsWithMedia if we delete the model,
            // but we can also be explicit if needed.
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'institution_id',
        'submitter_id',
        'venue_id',
        'space_id',
        'organizer_type',
        'organizer_id',

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
        'event_type',
        'gender',
        'age_group',
        'children_allowed',
        'event_format',
        'visibility',
        'status',
        'live_url',
        'event_url',
        'recording_url',
        'views_count',
        'saves_count',
        'registrations_count',
        'interests_count',
        'going_count',
        'published_at',
        'escalated_at',
        'is_priority',
        'is_featured',
        'is_active',
        'is_muslim_only',
    ];

    protected function casts(): array
    {
        return [
            'status' => \App\States\EventStatus\EventStatus::class,
            'description' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'timing_mode' => TimingMode::class,
            'prayer_reference' => PrayerReference::class,
            'prayer_offset' => PrayerOffset::class,
            'gender' => EventGenderRestriction::class,
            'age_group' => \Illuminate\Database\Eloquent\Casts\AsEnumCollection::of(EventAgeGroup::class),
            'event_format' => EventFormat::class,
            'event_type' => \Illuminate\Database\Eloquent\Casts\AsEnumCollection::of(EventType::class),
            'visibility' => EventVisibility::class,
            'children_allowed' => 'boolean',
            'views_count' => 'integer',
            'saves_count' => 'integer',
            'registrations_count' => 'integer',
            'interests_count' => 'integer',
            'going_count' => 'integer',
            'published_at' => 'datetime',
            'escalated_at' => 'datetime',
            'is_priority' => 'boolean',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'is_muslim_only' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active events (approved + pending public events).
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->whereIn('status', ['approved', 'pending'])
            ->where('visibility', EventVisibility::Public);
    }

    /**
     * Determine if the model should be searchable.
     * Index approved and pending public events.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->is_active
            && ($this->status->equals(\App\States\EventStatus\Approved::class)
                || $this->status->equals(\App\States\EventStatus\Pending::class))
            && $this->visibility === EventVisibility::Public;
    }

    /**
     * Get the indexable data array for the model.
     * Schema matches documentation B8.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing(['institution', 'venue', 'speakers', 'address', 'tags']);

        $ageGroupCollection = $this->age_group;

        $ageGroupValues = $ageGroupCollection instanceof \Illuminate\Support\Collection && $ageGroupCollection->isNotEmpty()
            ? $ageGroupCollection->map(fn ($e) => $e instanceof EventAgeGroup ? $e->value : $e)->toArray()
            : ['all_ages'];

        $topicIds = $this->tags
            ->filter(fn ($tag) => in_array($tag->type, [TagType::Discipline->value, TagType::Issue->value], true))
            ->whereIn('status', ['verified', 'pending'])
            ->pluck('id')
            ->values()
            ->all();

        $array = [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description ?? '',
            'slug' => $this->slug,
            'speaker_names' => $this->speakers->pluck('name')->implode(', '),
            'institution_name' => $this->institution?->name ?? '',
            'venue_name' => $this->venue?->name ?? '',
            'state_id' => $this->venue?->address?->state_id ?? ($this->address?->state_id ?? ''),
            'district_id' => $this->venue?->address?->district_id ?? ($this->address?->district_id ?? ''),
            'language' => $this->language,
            'event_type' => $this->event_type?->map(fn ($e) => $e->value)->toArray() ?? [],
            'gender' => $this->gender?->value ?? 'all',
            'age_group' => $ageGroupValues,
            'audience' => $ageGroupValues,
            'event_format' => $this->event_format?->value ?? 'physical',
            'children_allowed' => $this->children_allowed ?? true,
            'status' => (string) $this->status,
            'visibility' => $this->visibility?->value ?? 'public',
            'topic_ids' => $topicIds,
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
        return $this->belongsToMany(User::class, 'event_user')
            ->using(EventUser::class)
            ->withPivot(['joined_at'])
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

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function series(): BelongsToMany
    {
        return $this->belongsToMany(Series::class, 'event_series')
            ->withPivot('order_column')
            ->withTimestamps()
            ->orderByPivot('order_column');
    }

    // EventType relationship removed in favor of Enum

    public function settings(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EventSettings::class);
    }

    public function speakers(): BelongsToMany
    {
        return $this->belongsToMany(Speaker::class, 'event_speaker')
            ->withPivot('order_column')
            ->withTimestamps()
            ->orderByPivot('order_column');
    }

    public function references(): BelongsToMany
    {
        return $this->belongsToMany(Reference::class, 'event_reference')
            ->withPivot('order_column')
            ->withTimestamps()
            ->orderByPivot('order_column');
    }

    public function mediaLinks(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\App\Models\MediaLink::class, 'mediable');
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
            ->useDisk(config('media-library.disk_name'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useFallbackUrl(asset('images/placeholders/event.png'))
            ->withResponsiveImages()
            ->singleFile();

        $this->addMediaCollection('gallery')
            ->useDisk(config('media-library.disk_name'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->withResponsiveImages();
    }

    /**
     * Register media conversions for optimized image delivery.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(368)
            ->height(232)
            ->sharpen(10)
            ->format('webp')
            ->performOnCollections('poster', 'gallery');

        $this->addMediaConversion('preview')
            ->width(800)
            ->format('webp')
            ->performOnCollections('poster');
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
        // Use venue coordinates
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
     * Priority: Poster thumb -> Institution logo thumb -> Speaker avatar -> Default.
     */
    public function getCardImageUrlAttribute(): string
    {
        // 1. Poster thumb conversion from Spatie Media Library
        if ($this->hasMedia('poster')) {
            return $this->getFirstMediaUrl('poster', 'thumb');
        }

        // 2. Institution logo thumb
        if ($this->institution && $this->institution->hasMedia('logo')) {
            return $this->institution->getFirstMediaUrl('logo', 'thumb');
        }

        // 3. Fallback to first speaker's avatar
        if ($this->speakers->isNotEmpty() && $url = $this->speakers->first()->avatar_url) {
            return $url;
        }

        // 4. Global default (placeholder)
        return asset('images/placeholders/event.png');
    }

    /**
     * Map 'genre' to 'event_type' for compatibility.
     */
    public function getGenreAttribute(): mixed
    {
        return $this->event_type?->map(fn ($e) => $e->value)->toArray();
    }

    /**
     * Map 'audience' to 'age_group' for compatibility.
     * Returns string values for search indexing.
     */
    public function getAudienceAttribute(): array
    {
        $ageGroup = $this->age_group;

        if ($ageGroup instanceof \Illuminate\Support\Collection) {
            return $ageGroup->map(fn ($e) => $e instanceof EventAgeGroup ? $e->value : $e)->toArray();
        }

        return is_array($ageGroup) ? $ageGroup : ['all_ages'];
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

    /**
     * Get the organizer model (Institution or Speaker).
     */
    public function organizer(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if a user can manage this event.
     * Uses Authz scoped roles via event membership or organizer/institution scope.
     */
    public function userCanManage(User $user): bool
    {
        // 1. Check event-scoped roles via Authz
        if (Authz::userCanInScope($user, 'event.update', $this)) {
            return true;
        }

        // 2. Check organizer scope permissions (if organizer is set)
        if ($this->organizer_id && $this->organizer) {
            return Authz::userCanInScope($user, 'event.update', $this->organizer);
        }

        // 3. Fallback to institution scope
        if ($this->institution_id && $this->institution) {
            return Authz::userCanInScope($user, 'event.update', $this->institution);
        }

        return false;
    }

    /**
     * Check if a user can delete this event.
     * More restrictive than manage: requires event.delete permission in scope.
     */
    public function userCanDelete(User $user): bool
    {
        // 1. Check event-scoped delete permission via Authz
        if (Authz::userCanInScope($user, 'event.delete', $this)) {
            return true;
        }

        // 2. Check organizer scope delete permission
        if ($this->organizer_id && $this->organizer) {
            return Authz::userCanInScope($user, 'event.delete', $this->organizer);
        }

        // 3. Fallback to institution scope
        if ($this->institution_id && $this->institution) {
            return Authz::userCanInScope($user, 'event.delete', $this->institution);
        }

        return false;
    }

    /**
     * Check if a user can view this event (private events).
     */
    public function userCanView(User $user): bool
    {
        // Event members can view
        if ($this->members()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // Check organizer scope
        if ($this->organizer_id && $this->organizer) {
            return Authz::userCanInScope($user, 'event.view', $this->organizer);
        }

        // Fallback to institution scope
        if ($this->institution_id && $this->institution) {
            return Authz::userCanInScope($user, 'event.view', $this->institution);
        }

        return false;
    }
}
