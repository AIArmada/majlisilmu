<?php

namespace App\Models;

use AIArmada\FilamentAuthz\Concerns\HasAuthzScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Institution extends Model implements AuditableContract, HasMedia
{
    /** @use HasFactory<\Database\Factories\InstitutionFactory> */
    use \App\Models\Concerns\HasAddress, \App\Models\Concerns\HasContacts, \App\Models\Concerns\HasDonations, \App\Models\Concerns\HasSocialMedia, Auditable, HasAuthzScope, HasFactory, HasUuids, InteractsWithMedia;

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
    ];

    protected function casts(): array
    {
        return [];
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

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'institution_members')
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
    }

    public function getAuthzScopeLabel(): string
    {
        return 'Institution: '.$this->name;
    }
}
