<?php

namespace App\Models;

use AIArmada\FilamentAuthz\Concerns\HasAuthzScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\DeletedModels\Models\Concerns\KeepsDeletedModels;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Speaker extends Model implements AuditableContract, HasMedia
{
    /** @use HasFactory<\Database\Factories\SpeakerFactory> */
    use \App\Models\Concerns\HasAddress, \App\Models\Concerns\HasContacts, \App\Models\Concerns\HasDonationChannels, \App\Models\Concerns\HasLanguages, \App\Models\Concerns\HasSocialMedia, Auditable, HasAuthzScope, HasFactory, HasUuids, InteractsWithMedia, KeepsDeletedModels;

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
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'honorific' => 'array',
            'pre_nominal' => 'array',
            'post_nominal' => 'array',
            'qualifications' => 'array',
            'is_freelance' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    #[\Override]
    protected static function booted(): void
    {
        static::saving(function (Speaker $speaker) {
            if ($speaker->isDirty('qualifications')) {
                // Logic to compute post_nominal from qualifications
                $qualifications = $speaker->qualifications ?? [];
                // Assuming qualifications is an array of arrays with 'degree' or 'post_nominal' key
                // The factory uses: ['institution' => ..., 'degree' => ..., 'field' => ..., 'year' => ...]
                // The user said: "post_nominal is a computed display string derived from qualifications"

                // Let's assume we map degree/field to a string.
                // Example: PhD (Oxford), MA (Cairo)
                // Or just simpler: PhD, MA.
                // The factory has 'degree' in qualifications.

                if (is_array($qualifications)) {
                    $parts = [];
                    foreach ($qualifications as $qual) {
                        if (isset($qual['degree'])) {
                            $parts[] = $qual['degree'];
                        }
                    }
                    // De-duplicate
                    $parts = array_unique($parts);

                    if ($parts !== []) {
                        $speaker->post_nominal = array_values($parts);
                    }
                }
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
            return asset('images/avatar-female.png');
        }

        return asset('images/avatar-male.png');
    }

    public function getFormattedNameAttribute(): string
    {
        $honorificLabels = null;
        $preNominalLabels = null;

        // Convert honorific enum values to labels
        if (is_array($this->honorific) && $this->honorific !== []) {
            $honorificLabels = collect($this->honorific)
                ->map(fn ($value) => \App\Enums\Honorific::tryFrom($value)?->getLabel())
                ->filter()
                ->implode(', ');
        }

        // Convert pre_nominal enum values to labels
        if (is_array($this->pre_nominal) && $this->pre_nominal !== []) {
            $preNominalLabels = collect($this->pre_nominal)
                ->map(fn ($value) => \App\Enums\PreNominal::tryFrom($value)?->getLabel())
                ->filter()
                ->implode(' ');
        }

        $parts = array_filter([
            $honorificLabels,
            $preNominalLabels,
            $this->name,
        ], filled(...));

        $formatted = trim(implode(' ', $parts));

        if (is_array($this->post_nominal) && $this->post_nominal !== []) {
            $postNominalStr = implode(', ', $this->post_nominal);
            $formatted = trim($formatted.', '.$postNominalStr);
        } elseif (filled($this->post_nominal)) {
            $formatted = trim($formatted.', '.$this->post_nominal);
        }

        return $formatted;
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_speaker')
            ->withPivot('order_column')
            ->withTimestamps()
            ->orderByPivot('order_column');
    }

    public function institutions(): BelongsToMany
    {
        return $this->belongsToMany(Institution::class, 'institution_speaker')
            ->withPivot(['position', 'is_primary', 'joined_at'])
            ->withTimestamps();
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'speaker_user')
            ->withTimestamps();
    }

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

        $this->addMediaCollection('main')
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
            ->width(80)
            ->height(80)
            ->sharpen(10)
            ->format('webp')
            ->performOnCollections('avatar');

        $this->addMediaConversion('profile')
            ->width(400)
            ->height(400)
            ->format('webp')
            ->performOnCollections('avatar');

        $this->addMediaConversion('banner')
            ->width(1200)
            ->format('webp')
            ->performOnCollections('main');

        $this->addMediaConversion('gallery_thumb')
            ->width(368)
            ->height(232)
            ->sharpen(10)
            ->format('webp')
            ->performOnCollections('gallery');
    }

    public function getAuthzScopeLabel(): string
    {
        return 'Speaker: '.$this->name;
    }

    /**
     * Scope a query to only include active speakers.
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function active($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Compatibility alias for job_title
     */
    public function getTitleAttribute(): ?string
    {
        return $this->job_title;
    }
}
