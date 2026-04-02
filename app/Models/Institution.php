<?php

namespace App\Models;

use App\Enums\InstitutionType;
use App\Enums\MemberSubjectType;
use App\Models\Concerns\AuditsModelChanges;
use App\Models\Concerns\HasAddress;
use App\Models\Concerns\HasContacts;
use App\Models\Concerns\HasDonationChannels;
use App\Models\Concerns\HasFollowers;
use App\Models\Concerns\HasLanguages;
use App\Models\Concerns\HasSocialMedia;
use Database\Factories\InstitutionFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\DeletedModels\Models\Concerns\KeepsDeletedModels;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Institution extends Model implements AuditableContract, HasMedia
{
    /** @use HasFactory<InstitutionFactory> */
    use AuditsModelChanges, HasAddress, HasContacts, HasDonationChannels, HasFactory, HasFollowers, HasLanguages, HasSocialMedia, HasUuids, InteractsWithMedia, KeepsDeletedModels;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'name',
        'nickname',
        'slug',
        'description',

        'status',
        'is_active',
        'allow_public_event_submission',
        'public_submission_locked_at',
        'public_submission_locked_by',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'type' => InstitutionType::class,
            'is_active' => 'boolean',
            'allow_public_event_submission' => 'boolean',
            'public_submission_locked_at' => 'datetime',
        ];
    }

    public function getDisplayNameAttribute(): string
    {
        return self::formatDisplayName($this->name, $this->nickname);
    }

    public static function formatDisplayName(?string $name, ?string $nickname): string
    {
        $normalizedName = trim((string) $name);
        $normalizedNickname = is_string($nickname) ? trim($nickname) : '';

        if ($normalizedNickname === '') {
            return $normalizedName;
        }

        return $normalizedName === ''
            ? $normalizedNickname
            : "{$normalizedName} ({$normalizedNickname})";
    }

    /**
     * @return BelongsToMany<Space, $this>
     */
    public function spaces(): BelongsToMany
    {
        return $this->belongsToMany(Space::class, 'institution_space')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * @return BelongsToMany<Speaker, $this>
     */
    public function speakers(): BelongsToMany
    {
        return $this->belongsToMany(Speaker::class, 'institution_speaker')
            ->withPivot(['position', 'is_primary', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'institution_user')
            ->withTimestamps();
    }

    /**
     * @return HasMany<MemberInvitation, $this>
     */
    public function memberInvitations(): HasMany
    {
        return $this->hasMany(MemberInvitation::class, 'subject_id')
            ->where('subject_type', MemberSubjectType::Institution->value);
    }

    /**
     * @return HasMany<MembershipClaim, $this>
     */
    public function membershipClaims(): HasMany
    {
        return $this->hasMany(MembershipClaim::class, 'subject_id')
            ->where('subject_type', MemberSubjectType::Institution->value);
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
        $this->addMediaCollection('logo')
            ->useDisk(config('media-library.disk_name'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'])
            ->useFallbackUrl(asset('images/placeholders/institution.png'))
            ->singleFile();

        $this->addMediaCollection('cover')
            ->useDisk(config('media-library.disk_name'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useFallbackUrl(asset('images/placeholders/institution.png'))
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
            ->performOnCollections('logo')
            ->width(100)
            ->height(100)
            ->sharpen(10)
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

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function searchNameOrNickname(Builder $query, string $search): void
    {
        $normalizedSearch = preg_replace('/\s+/u', ' ', trim($search)) ?? '';

        if ($normalizedSearch === '') {
            return;
        }

        $wildcardSearch = '%'.str_replace(' ', '%', $normalizedSearch).'%';
        $driverName = DB::connection($query->getModel()->getConnectionName())->getDriverName();
        $operator = $driverName === 'pgsql' ? 'ilike' : 'like';

        $query->where(function (Builder $innerQuery) use ($normalizedSearch, $wildcardSearch, $operator): void {
            $innerQuery
                ->where('institutions.name', $operator, "%{$normalizedSearch}%")
                ->orWhere('institutions.name', $operator, $wildcardSearch)
                ->orWhere('institutions.nickname', $operator, "%{$normalizedSearch}%")
                ->orWhere('institutions.nickname', $operator, $wildcardSearch);
        });
    }
}
