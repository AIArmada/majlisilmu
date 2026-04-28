<?php

namespace App\Models;

use App\Actions\References\GenerateReferenceSlugAction;
use App\Enums\MemberSubjectType;
use App\Enums\ReferencePartType;
use App\Enums\ReferenceType;
use App\Models\Concerns\AuditsModelChanges;
use App\Models\Concerns\HasFollowers;
use App\Models\Concerns\HasSocialMedia;
use Database\Factories\ReferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
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

            $reference->normalizeReferencePartFields();
        });
    }

    protected $fillable = [
        'title',
        'slug',
        'parent_reference_id',
        'author',
        'type',
        'part_type',
        'part_number',
        'part_label',
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

    /**
     * @param  list<string>  $referenceIds
     * @return list<string>
     */
    public static function expandRootReferenceIdsForFiltering(array $referenceIds): array
    {
        $normalizedReferenceIds = collect($referenceIds)
            ->map(static fn (string $referenceId): string => trim($referenceId))
            ->filter(static fn (string $referenceId): bool => $referenceId !== '')
            ->unique()
            ->values();

        if ($normalizedReferenceIds->isEmpty()) {
            return [];
        }

        /** @var Collection<int, self> $selectedReferences */
        $selectedReferences = self::query()
            ->whereIn('id', $normalizedReferenceIds->all())
            ->get(['id', 'parent_reference_id']);

        $selectedRootIds = $selectedReferences
            ->filter(static fn (self $reference): bool => blank($reference->parent_reference_id))
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values();

        $expandedChildIds = $selectedRootIds->isEmpty()
            ? collect()
            : self::query()
                ->whereIn('parent_reference_id', $selectedRootIds->all())
                ->pluck('id')
                ->map(static fn (mixed $id): string => (string) $id);

        return $normalizedReferenceIds
            ->merge($expandedChildIds)
            ->unique()
            ->values()
            ->all();
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

    /**
     * Scope a query to root references and standalone references.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function root(Builder $query): void
    {
        $query->whereNull('parent_reference_id');
    }

    /**
     * Scope a query to child part references.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function part(Builder $query): void
    {
        $query->whereNotNull('parent_reference_id');
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
            'parent_reference_id',
            'part_type',
            'part_number',
            'part_label',
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
            'parent_reference_id' => filled($this->parent_reference_id) ? (string) $this->parent_reference_id : null,
            'part_type' => filled($this->part_type) ? (string) $this->part_type : null,
            'part_number' => filled($this->part_number) ? (string) $this->part_number : null,
            'part_label' => filled($this->part_label) ? (string) $this->part_label : null,
            'display_title' => $this->displayTitle(),
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
            'part_type' => filled($this->part_type) ? (string) $this->part_type : null,
            'part_number' => filled($this->part_number) ? (string) $this->part_number : null,
            'part_label' => filled($this->part_label) ? (string) $this->part_label : null,
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
            trim((string) $this->displayTitle()),
            trim((string) $this->part_label),
            trim((string) $this->part_number),
            trim((string) $this->author),
            trim((string) $this->publisher),
            trim(strip_tags((string) $this->description)),
        ])));
    }

    /**
     * @return BelongsTo<Reference, $this>
     */
    public function parentReference(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_reference_id');
    }

    /**
     * @return HasMany<Reference, $this>
     */
    public function childReferences(): HasMany
    {
        return $this->hasMany(self::class, 'parent_reference_id')
            ->orderBy('part_type')
            ->orderBy('part_number')
            ->orderBy('title');
    }

    public function isPart(): bool
    {
        return filled($this->parent_reference_id);
    }

    public function isRootReference(): bool
    {
        return ! $this->isPart();
    }

    public function familyRootId(): ?string
    {
        if ($this->isPart()) {
            return (string) $this->parent_reference_id;
        }

        $key = $this->getKey();

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * @return list<string>
     */
    public function familyReferenceIds(): array
    {
        $rootId = $this->familyRootId();

        if ($rootId === null) {
            return [];
        }

        return self::query()
            ->where('id', $rootId)
            ->orWhere('parent_reference_id', $rootId)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function defaultEventReferenceIds(): array
    {
        if ($this->isPart()) {
            $key = $this->getKey();

            return is_string($key) && $key !== '' ? [$key] : [];
        }

        return $this->familyReferenceIds();
    }

    public function displayTitle(): string
    {
        $title = trim((string) $this->title);
        $partLabel = $this->resolvedPartLabel();

        if (! $this->isPart() || $partLabel === '') {
            return $title;
        }

        if (str_contains(mb_strtolower($title), mb_strtolower($partLabel))) {
            return $title;
        }

        return trim("{$title} — {$partLabel}");
    }

    public function getDisplayTitleAttribute(): string
    {
        return $this->displayTitle();
    }

    private function resolvedPartLabel(): string
    {
        $partLabel = trim((string) $this->part_label);

        if ($partLabel !== '') {
            return $partLabel;
        }

        $partType = ReferencePartType::tryFrom((string) $this->part_type);
        $partNumber = trim((string) $this->part_number);

        if (! $partType instanceof ReferencePartType) {
            return $partNumber;
        }

        $label = $partType->getLabel();

        return $partNumber !== '' ? "{$label} {$partNumber}" : $label;
    }

    private function normalizeReferencePartFields(): void
    {
        if ((string) $this->type !== ReferenceType::Book->value || blank($this->parent_reference_id)) {
            $this->parent_reference_id = null;
            $this->part_type = null;
            $this->part_number = null;
            $this->part_label = null;

            return;
        }

        $this->part_type = (ReferencePartType::tryFrom((string) $this->part_type) ?? ReferencePartType::Jilid)->value;
        $this->part_number = $this->nullableTrimmedString($this->part_number);
        $this->part_label = $this->nullableTrimmedString($this->part_label);

        $this->ensureValidParentReference();
    }

    private function ensureValidParentReference(): void
    {
        if ((string) $this->parent_reference_id === (string) $this->getKey()) {
            throw ValidationException::withMessages([
                'parent_reference_id' => __('A reference part cannot use itself as the parent book.'),
            ]);
        }

        if ($this->exists && $this->childReferences()->exists()) {
            throw ValidationException::withMessages([
                'parent_reference_id' => __('A reference with child parts cannot itself become a child part.'),
            ]);
        }

        $parentReference = self::query()
            ->whereKey($this->parent_reference_id)
            ->first(['id', 'parent_reference_id', 'type']);

        if (! $parentReference instanceof self) {
            throw ValidationException::withMessages([
                'parent_reference_id' => __('The selected parent reference does not exist.'),
            ]);
        }

        if ($parentReference->isPart() || (string) $parentReference->type !== ReferenceType::Book->value) {
            throw ValidationException::withMessages([
                'parent_reference_id' => __('Reference parts can only belong to a root book reference.'),
            ]);
        }
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
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
