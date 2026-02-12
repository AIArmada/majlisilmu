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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Laravel\Scout\Searchable;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\DeletedModels\Models\Concerns\KeepsDeletedModels;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\ModelStates\HasStates;
use Spatie\Tags\HasTags;

/**
 * @property string $id
 * @property string $title
 * @property string $slug
 * @property array<string, mixed>|string|null $description
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property \App\States\EventStatus\EventStatus|string $status
 * @property \App\Enums\EventVisibility|string|null $visibility
 * @property \App\Enums\EventFormat|string|null $event_format
 * @property \App\Enums\EventGenderRestriction|string|null $gender
 * @property \Illuminate\Support\Collection<int, \App\Enums\EventAgeGroup>|array<int, string>|null $age_group
 * @property \Illuminate\Support\Collection<int, \App\Enums\EventType>|array<int, string>|null $event_type
 * @property bool $is_active
 * @property-read Address|null $address
 * @property-read Address|null $addressModel
 * @property-read Institution|null $institution
 * @property-read Venue|null $venue
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Speaker> $speakers
 */
class Event extends Model implements AuditableContract, HasMedia
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use \App\Models\Concerns\HasAddress, \App\Models\Concerns\HasDonationChannels, \App\Models\Concerns\HasLanguages, Auditable, HasFactory, HasStates, HasTags, HasUuids, InteractsWithMedia, KeepsDeletedModels, Searchable;

    public $incrementing = false;

    protected $keyType = 'string';

    #[\Override]
    protected static function booted(): void
    {
        static::deleting(function (Event $event) {
            $event->members()->detach();
            $event->speakers()->detach();
            $event->references()->detach();
            $event->savedBy()->detach();
            $event->interestedBy()->detach();
            $event->goingBy()->detach();

            $event->registrations()->each(function (Registration $registration): void {
                $registration->delete();
            });
            $event->submissions()->each(function (EventSubmission $submission): void {
                $submission->delete();
            });
            $event->moderationReviews()->each(function (ModerationReview $review): void {
                $review->delete();
            });
            $event->mediaLinks()->each(function (MediaLink $mediaLink): void {
                $mediaLink->delete();
            });

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

    #[\Override]
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
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)
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
            && in_array((string) $this->status, ['approved', 'pending'], true)
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
        $this->loadMissing(['institution', 'venue', 'venue.address', 'speakers', 'address', 'tags']);
        $venueAddress = $this->venue?->addressModel;
        $eventAddress = $this->addressModel;
        $institution = $this->institution;
        $venue = $this->venue;
        $gender = $this->gender;
        $eventFormat = $this->event_format;
        $visibility = $this->visibility;

        $ageGroupCollection = $this->age_group;

        $ageGroupValues = $ageGroupCollection instanceof \Illuminate\Support\Collection && $ageGroupCollection->isNotEmpty()
            ? $ageGroupCollection->map(fn (EventAgeGroup $value): string => $value->value)->toArray()
            : ['all_ages'];

        /** @var \Illuminate\Database\Eloquent\Collection<int, Tag> $tags */
        $tags = $this->tags;

        $topicIds = $tags
            ->filter(fn (Tag $tag): bool => in_array($tag->type, [TagType::Discipline->value, TagType::Issue->value], true))
            ->whereIn('status', ['verified', 'pending'])
            ->pluck('id')
            ->values()
            ->all();

        $array = [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description_text,
            'slug' => $this->slug,
            'speaker_names' => $this->speakers->pluck('name')->implode(', '),
            'institution_name' => $institution instanceof Institution ? $institution->name : '',
            'venue_name' => $venue instanceof Venue ? $venue->name : '',
            'state_id' => $venueAddress instanceof Address ? $venueAddress->state_id : ($eventAddress instanceof Address ? $eventAddress->state_id : ''),
            'district_id' => $venueAddress instanceof Address ? $venueAddress->district_id : ($eventAddress instanceof Address ? $eventAddress->district_id : ''),
            'subdistrict_id' => $venueAddress instanceof Address ? $venueAddress->subdistrict_id : ($eventAddress instanceof Address ? $eventAddress->subdistrict_id : ''),
            'language' => $this->language,
            'event_type' => $this->normalizedEventTypeValues(),
            'gender' => $gender instanceof EventGenderRestriction ? $gender->value : ((is_string($gender) && $gender !== '') ? $gender : 'all'),
            'age_group' => $ageGroupValues,
            'audience' => $ageGroupValues,
            'event_format' => $eventFormat instanceof EventFormat ? $eventFormat->value : ((is_string($eventFormat) && $eventFormat !== '') ? $eventFormat : 'physical'),
            'children_allowed' => $this->children_allowed ?? true,
            'is_active' => (bool) $this->is_active,
            'status' => (string) $this->status,
            'visibility' => $visibility instanceof EventVisibility ? $visibility->value : ((is_string($visibility) && $visibility !== '') ? $visibility : 'public'),
            'topic_ids' => $topicIds,
            'speaker_ids' => $this->speakers->pluck('id')->toArray(),
            'starts_at' => $this->starts_at instanceof \Illuminate\Support\Carbon ? $this->starts_at->timestamp : 0,
            'ends_at' => $this->ends_at instanceof \Illuminate\Support\Carbon ? $this->ends_at->timestamp : null,
            'saves_count' => $this->saves_count ?? 0,
            'registrations_count' => $this->registrations_count ?? 0,
        ];

        if ($venueAddress instanceof Address && $venueAddress->lat !== null && $venueAddress->lng !== null) {
            $array['location'] = [(float) $venueAddress->lat, (float) $venueAddress->lng];
        } elseif ($eventAddress instanceof Address && $eventAddress->lat !== null && $eventAddress->lng !== null) {
            $array['location'] = [(float) $eventAddress->lat, (float) $eventAddress->lng];
        }

        return $array;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitter_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<User, $this, EventUser>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_user')
            ->using(EventUser::class)
            ->withPivot(['joined_at'])
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * @return BelongsTo<Space, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * @return BelongsToMany<Series, $this>
     */
    public function series(): BelongsToMany
    {
        return $this->belongsToMany(Series::class, 'event_series')
            ->withPivot('order_column')
            ->withTimestamps()
            ->orderByPivot('order_column');
    }

    // EventType relationship removed in favor of Enum

    /**
     * @return HasOne<EventSettings, $this>
     */
    public function settings(): HasOne
    {
        return $this->hasOne(EventSettings::class);
    }

    /**
     * @return BelongsToMany<Speaker, $this>
     */
    public function speakers(): BelongsToMany
    {
        return $this->belongsToMany(Speaker::class, 'event_speaker')
            ->withPivot('order_column')
            ->withTimestamps()
            ->orderByPivot('order_column');
    }

    /**
     * @return BelongsToMany<Reference, $this>
     */
    public function references(): BelongsToMany
    {
        return $this->belongsToMany(Reference::class, 'event_reference')
            ->withPivot('order_column')
            ->withTimestamps()
            ->orderByPivot('order_column');
    }

    /**
     * @return MorphMany<MediaLink, $this>
     */
    public function mediaLinks(): MorphMany
    {
        return $this->morphMany(MediaLink::class, 'mediable');
    }

    /**
     * @return HasMany<EventSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(EventSubmission::class);
    }

    /**
     * @return HasMany<ModerationReview, $this>
     */
    public function moderationReviews(): HasMany
    {
        return $this->hasMany(ModerationReview::class);
    }

    /**
     * @return HasOne<ModerationReview, $this>
     */
    public function latestModerationReview(): HasOne
    {
        return $this->hasOne(ModerationReview::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_saves')->withTimestamps();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function interestedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_interests')->withTimestamps();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function goingBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_attendees')->withTimestamps();
    }

    /**
     * @return MorphMany<Report, $this>
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'entity');
    }

    /**
     * @return MorphOne<DonationChannel, $this>
     */
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
            ->performOnCollections('poster', 'gallery')
            ->width(368)
            ->height(232)
            ->sharpen(10)
            ->format('webp');

        $this->addMediaConversion('preview')
            ->performOnCollections('poster')
            ->width(800)
            ->format('webp');
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
     *
     * @return array{lat: float, lng: float}|null
     */
    public function getPrayerCoordinatesAttribute(): ?array
    {
        $venueAddress = $this->venue?->addressModel;

        if ($venueAddress instanceof Address && $venueAddress->lat !== null && $venueAddress->lng !== null) {
            return [
                'lat' => (float) $venueAddress->lat,
                'lng' => (float) $venueAddress->lng,
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

        $firstSpeaker = $this->speakers->first();

        if ($firstSpeaker instanceof Speaker && filled($firstSpeaker->avatar_url)) {
            return (string) $firstSpeaker->avatar_url;
        }

        // 4. Global default (placeholder)
        return asset('images/placeholders/event.png');
    }

    /**
     * Map 'genre' to 'event_type' for compatibility.
     *
     * @return list<string>
     */
    public function getGenreAttribute(): array
    {
        return $this->normalizedEventTypeValues();
    }

    /**
     * Map 'audience' to 'age_group' for compatibility.
     * Returns string values for search indexing.
     *
     * @return list<string>
     */
    public function getAudienceAttribute(): array
    {
        $ageGroup = $this->age_group;

        if ($ageGroup instanceof \Illuminate\Support\Collection) {
            return $ageGroup
                ->map(fn (EventAgeGroup $value): string => $value->value)
                ->values()
                ->all();
        }

        return is_array($ageGroup)
            ? array_values(array_map(strval(...), $ageGroup))
            : ['all_ages'];
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

        $language = $this->languages->first();

        if ($language instanceof \Nnjeim\World\Models\Language && is_string($language->code) && $language->code !== '') {
            return $language->code;
        }

        return 'ms';
    }

    public function getDescriptionTextAttribute(): string
    {
        return $this->normalizeDescriptionText($this->description);
    }

    /**
     * @return list<string>
     */
    private function normalizedEventTypeValues(): array
    {
        $eventType = $this->event_type;

        if ($eventType instanceof \Illuminate\Support\Collection) {
            return $eventType
                ->map(fn (EventType $value): string => $value->value)
                ->filter(fn (string $value): bool => $value !== '')
                ->values()
                ->all();
        }

        if (is_array($eventType)) {
            return array_values(array_filter(array_map(strval(...), $eventType), static fn (string $value): bool => $value !== ''));
        }

        if ($eventType instanceof EventType) {
            return [$eventType->value];
        }

        if (is_string($eventType) && $eventType !== '') {
            return [$eventType];
        }

        return [];
    }

    private function normalizeDescriptionText(mixed $description): string
    {
        if (is_string($description)) {
            return trim(strip_tags($description));
        }

        if (! is_array($description)) {
            return '';
        }

        $html = data_get($description, 'html');

        if (is_string($html) && $html !== '') {
            return trim(strip_tags($html));
        }

        $content = data_get($description, 'content');

        if (is_string($content) && $content !== '') {
            return trim($content);
        }

        return trim(collect($description)
            ->flatten()
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->implode(' '));
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function organizer(): MorphTo
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
