<?php

namespace App\Models;

use App\Actions\References\GenerateReferenceSlugAction;
use App\Enums\MemberSubjectType;
use App\Models\Concerns\AuditsModelChanges;
use App\Models\Concerns\HasFollowers;
use App\Models\Concerns\HasSocialMedia;
use Database\Factories\ReferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\DeletedModels\Models\Concerns\KeepsDeletedModels;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Reference extends Model implements AuditableContract, HasMedia
{
    /** @use HasFactory<ReferenceFactory> */
    use AuditsModelChanges, HasFactory, HasFollowers, HasSocialMedia, HasUuids, InteractsWithMedia, KeepsDeletedModels, Searchable;

    #[\Override]
    protected static function booted(): void
    {
        static::saving(function (self $reference): void {
            if (blank($reference->slug)) {
                $reference->slug = app(GenerateReferenceSlugAction::class)->handle($reference->title, (string) $reference->getKey());
            }
        });
    }

    protected $fillable = [
        'title',
        'slug',
        'author',
        'type',
        'publication_year',
        'publisher',
        'description',
        'is_canonical',
        'status',
        'is_active',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'is_canonical' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    #[\Override]
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    #[\Override]
    public function getRouteKey(): mixed
    {
        if ($this->exists && blank($this->slug)) {
            $this->forceFill([
                'slug' => app(GenerateReferenceSlugAction::class)->handle($this->title, (string) $this->getKey()),
            ])->saveQuietly();
        }

        return parent::getRouteKey();
    }

    /**
     * Scope a query to only include active references.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function shouldBeSearchable(): bool
    {
        return $this->is_active
            && in_array((string) $this->status, ['verified', 'pending'], true);
    }

    public function searchIndexShouldBeUpdated(): bool
    {
        return $this->wasRecentlyCreated || $this->wasChanged([
            'title',
            'author',
            'type',
            'publication_year',
            'publisher',
            'description',
            'slug',
            'status',
            'is_active',
        ]);
    }

    /**
     * @param  Builder<Reference>  $query
     * @return Builder<Reference>
     */
    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query
            ->where('references.is_active', true)
            ->whereIn('references.status', ['verified', 'pending']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        if ($this->usesScoutDatabaseDriver()) {
            return $this->toScoutDatabaseSearchableArray();
        }

        $updatedAt = $this->updated_at ?? now();
        $publicationYear = $this->publication_year;
        $normalizedPublicationYear = is_numeric($publicationYear) ? (int) $publicationYear : null;
        $description = trim(strip_tags((string) $this->description));

        return [
            'id' => (string) $this->getKey(),
            'title' => (string) $this->title,
            'author' => filled($this->author) ? (string) $this->author : null,
            'type' => filled($this->type) ? (string) $this->type : null,
            'publication_year' => $normalizedPublicationYear,
            'publisher' => filled($this->publisher) ? (string) $this->publisher : null,
            'description' => $description !== '' ? $description : null,
            'search_text' => $this->searchableText(),
            'slug' => (string) $this->slug,
            'status' => (string) $this->status,
            'is_active' => (bool) $this->is_active,
            'updated_at' => $updatedAt->timestamp,
        ];
    }

    /**
     * @return array<string, string|int>
     */
    private function toScoutDatabaseSearchableArray(): array
    {
        $description = trim(strip_tags((string) $this->description));
        $publicationYear = is_numeric($this->publication_year) ? (int) $this->publication_year : null;

        return array_filter([
            'title' => (string) $this->title,
            'author' => filled($this->author) ? (string) $this->author : null,
            'type' => filled($this->type) ? (string) $this->type : null,
            'publication_year' => $publicationYear,
            'publisher' => filled($this->publisher) ? (string) $this->publisher : null,
            'description' => $description !== '' ? $description : null,
            'slug' => (string) $this->slug,
        ], static fn (mixed $value): bool => (is_string($value) && $value !== '') || is_int($value));
    }

    private function usesScoutDatabaseDriver(): bool
    {
        return (string) config('scout.driver') === 'database';
    }

    private function searchableText(): string
    {
        return trim(implode(' ', array_filter([
            trim((string) $this->title),
            trim((string) $this->author),
            trim((string) $this->publisher),
            trim(strip_tags((string) $this->description)),
        ])));
    }

    /**
     * @return BelongsToMany<Event, $this>
     */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_reference')
            ->withPivot('order_column')
            ->withTimestamps()
            ->orderByPivot('order_column');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'reference_user')
            ->withTimestamps();
    }

    /**
     * @return HasMany<MemberInvitation, $this>
     */
    public function memberInvitations(): HasMany
    {
        return $this->hasMany(MemberInvitation::class, 'subject_id')
            ->where('subject_type', MemberSubjectType::Reference->value);
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
        $this->addMediaCollection('front_cover')
            ->useDisk(config('media-library.disk_name'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->withResponsiveImages()
            ->singleFile();

        $this->addMediaCollection('back_cover')
            ->useDisk(config('media-library.disk_name'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
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
            ->performOnCollections('front_cover', 'back_cover')
            ->width(200)
            ->height(280)
            ->sharpen(10)
            ->format('webp');

        $this->addMediaConversion('gallery_thumb')
            ->performOnCollections('gallery')
            ->width(368)
            ->height(232)
            ->sharpen(10)
            ->format('webp');
    }
}
