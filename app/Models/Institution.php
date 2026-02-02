<?php

namespace App\Models;

use AIArmada\FilamentAuthz\Concerns\HasAuthzScope;
use App\Enums\InstitutionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\DeletedModels\Models\Concerns\KeepsDeletedModels;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Institution extends Model implements AuditableContract, HasMedia
{
    /** @use HasFactory<\Database\Factories\InstitutionFactory> */
    use \App\Models\Concerns\HasAddress, \App\Models\Concerns\HasContacts, \App\Models\Concerns\HasDonationChannels, \App\Models\Concerns\HasLanguages, \App\Models\Concerns\HasSocialMedia, Auditable, HasAuthzScope, HasFactory, HasUuids, InteractsWithMedia, KeepsDeletedModels;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'name',
        'slug',
        'description',

        'status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => InstitutionType::class,
            'is_active' => 'boolean',
        ];
    }

    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function series(): HasMany
    {
        return $this->hasMany(Series::class);
    }

    public function speakers(): BelongsToMany
    {
        return $this->belongsToMany(Speaker::class, 'institution_speaker')
            ->withPivot(['position', 'is_primary', 'joined_at'])
            ->withTimestamps();
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'institution_user')
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
        $this->addMediaCollection('logo')
            ->singleFile();

        $this->addMediaCollection('cover')
            ->singleFile();

        $this->addMediaCollection('gallery');
    }

    public function getAuthzScopeLabel(): string
    {
        return 'Institution: ' . $this->name;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
