<?php

namespace App\Models;

use AIArmada\FilamentAuthz\Concerns\HasAuthzScope;
use App\Enums\EventParticipantRole;
use App\Enums\Honorific;
use App\Enums\PostNominal;
use App\Enums\PreNominal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\DeletedModels\Models\Concerns\KeepsDeletedModels;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property array<int, mixed>|null $honorific
 * @property array<int, mixed>|null $pre_nominal
 * @property array<int, string>|string|null $post_nominal
 * @property array<int, array<string, mixed>>|null $qualifications
 */
class Speaker extends Model implements AuditableContract, HasMedia
{
    /** @use HasFactory<\Database\Factories\SpeakerFactory> */
    use \App\Models\Concerns\HasAddress, \App\Models\Concerns\HasContacts, \App\Models\Concerns\HasDonationChannels, \App\Models\Concerns\HasFollowers, \App\Models\Concerns\HasLanguages, \App\Models\Concerns\HasSocialMedia, Auditable, HasAuthzScope, HasFactory, HasUuids, InteractsWithMedia, KeepsDeletedModels;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'gender',
        'honorific',
        'pre_nominal',
        'post_nominal',
        'slug',
        'bio',
        'status',
        'qualifications',
        'is_freelance',
        'job_title',
        'is_active',
        'allow_public_event_submission',
        'public_submission_locked_at',
        'public_submission_locked_by',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'honorific' => 'array',
            'pre_nominal' => 'array',
            'post_nominal' => 'array',
            'bio' => 'array',
            'qualifications' => 'array',
            'is_freelance' => 'boolean',
            'is_active' => 'boolean',
            'allow_public_event_submission' => 'boolean',
            'public_submission_locked_at' => 'datetime',
        ];
    }

    #[\Override]
    protected static function booted(): void
    {
        static::saving(function (Speaker $speaker) {
            if ($speaker->status === 'rejected') {
                $speaker->is_active = false;
            }

            if ($speaker->isDirty('qualifications')) {
                $qualifications = $speaker->qualifications;
                $allowedPostNominals = array_map(
                    static fn (PostNominal $postNominal): string => $postNominal->value,
                    PostNominal::cases()
                );

                if (! is_array($qualifications) || $qualifications === []) {
                    $speaker->post_nominal = null;

                    return;
                }

                $parts = [];

                foreach ($qualifications as $qualification) {
                    if (! is_array($qualification)) {
                        continue;
                    }

                    $degree = $qualification['degree'] ?? null;

                    if (is_string($degree) && in_array($degree, $allowedPostNominals, true)) {
                        $parts[] = $degree;
                    }
                }

                $parts = array_values(array_unique($parts));
                $speaker->post_nominal = $parts !== [] ? $parts : null;
            }
        });
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->hasMedia('avatar')) {
            return $this->getFirstMediaUrl('avatar', 'thumb');
        }

        return null;
    }

    public function getDefaultAvatarUrlAttribute(): string
    {
        if ($this->avatar_url) {
            return $this->avatar_url;
        }

        if ($this->gender === 'female') {
            return asset('images/placeholders/speaker-female.png');
        }

        return asset('images/placeholders/speaker-male.png');
    }

    public function getFormattedNameAttribute(): string
    {
        $honorificLabels = null;
        $preNominalLabels = null;
        $honorificValues = is_array($this->honorific)
            ? array_values(array_filter($this->honorific, fn (mixed $value): bool => is_string($value) && $value !== ''))
            : [];
        $preNominalValues = is_array($this->pre_nominal)
            ? array_values(array_filter($this->pre_nominal, fn (mixed $value): bool => is_string($value) && $value !== ''))
            : [];

        if ($honorificValues !== []) {
            $honorificLabels = collect($honorificValues)
                ->map(fn (string $value): ?string => Honorific::tryFrom($value)?->getLabel())
                ->filter(fn (?string $label): bool => filled($label))
                ->implode(', ');
        }

        if ($preNominalValues !== []) {
            $preNominalLabels = collect($preNominalValues)
                ->map(fn (string $value): ?string => PreNominal::tryFrom($value)?->getLabel())
                ->filter(fn (?string $label): bool => filled($label))
                ->implode(' ');
        }

        $parts = array_filter([
            $honorificLabels,
            $preNominalLabels,
            $this->name,
        ], filled(...));

        $formatted = trim(implode(' ', $parts));
        $postNominalValues = is_array($this->post_nominal)
            ? array_values(array_filter($this->post_nominal, fn (string $value): bool => $value !== ''))
            : [];

        if ($postNominalValues !== []) {
            $postNominalStr = implode(', ', $postNominalValues);
            $formatted = trim($formatted.', '.$postNominalStr);
        } elseif (filled($this->post_nominal)) {
            $formatted = trim($formatted.', '.$this->post_nominal);
        }

        return $formatted;
    }

    /**
     * Generic key-person link across all event roles.
     *
     * Prefer speakerEvents() for talk history and nonSpeakerEventKeyPeople()
     * when role-specific assignment matters.
     *
     * @return BelongsToMany<Event, $this, EventKeyPersonPivot, 'pivot'>
     */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_key_people', 'speaker_id', 'event_id')
            ->using(EventKeyPersonPivot::class)
            ->withPivot(['id', 'role', 'name', 'order_column', 'is_public', 'notes'])
            ->withTimestamps()
            ->orderByPivot('order_column');
    }

    /**
     * @return BelongsToMany<Event, $this, EventKeyPersonPivot, 'pivot'>
     */
    public function speakerEvents(): BelongsToMany
    {
        return $this->events()
            ->wherePivot('role', EventParticipantRole::Speaker->value)
            ->withPivotValue('role', EventParticipantRole::Speaker->value);
    }

    /**
     * @return HasMany<EventKeyPerson, $this>
     */
    public function eventKeyPeople(): HasMany
    {
        return $this->hasMany(EventKeyPerson::class);
    }

    /**
     * @return HasMany<EventKeyPerson, $this>
     */
    public function nonSpeakerEventKeyPeople(): HasMany
    {
        return $this->eventKeyPeople()
            ->where('role', '!=', EventParticipantRole::Speaker->value)
            ->where('is_public', true)
            ->orderBy('order_column');
    }

    /**
     * @return BelongsToMany<Institution, $this>
     */
    public function institutions(): BelongsToMany
    {
        return $this->belongsToMany(Institution::class, 'institution_speaker')
            ->withPivot(['position', 'is_primary', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'speaker_user')
            ->withTimestamps();
    }

    /**
     * @return MorphMany<Report, $this>
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'entity');
    }

    /**
     * Register media collections for Spatie Media Library.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->useDisk(config('media-library.disk_name'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useFallbackUrl(asset('images/placeholders/speaker.png'))
            ->singleFile();

        $this->addMediaCollection('cover')
            ->useDisk(config('media-library.disk_name'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useFallbackUrl(asset('images/placeholders/speaker.png'))
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
            ->performOnCollections('avatar')
            ->width(80)
            ->height(80)
            ->sharpen(10)
            ->format('webp');

        $this->addMediaConversion('profile')
            ->performOnCollections('avatar')
            ->width(400)
            ->height(400)
            ->format('webp');

        $this->addMediaConversion('banner')
            ->performOnCollections('cover')
            ->fit(Fit::Crop, 1200, 675)
            ->format('webp');

        $this->addMediaConversion('gallery_thumb')
            ->performOnCollections('gallery')
            ->width(368)
            ->height(232)
            ->sharpen(10)
            ->format('webp');
    }

    public function getAuthzScopeLabel(): string
    {
        return 'Speaker: '.$this->name;
    }

    /**
     * Scope a query to only include active speakers.
     *
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Compatibility alias for job_title
     */
    public function getTitleAttribute(): ?string
    {
        return $this->job_title;
    }
}
