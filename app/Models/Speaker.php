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
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Speaker extends Model implements AuditableContract, HasMedia
{
    /** @use HasFactory<\Database\Factories\SpeakerFactory> */
    use \App\Models\Concerns\HasContacts, \App\Models\Concerns\HasDonations, \App\Models\Concerns\HasSocialMedia, Auditable, HasAuthzScope, HasFactory, HasUuids, InteractsWithMedia;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'title',
        'slug',
        'bio',
        'avatar_url',

        'status',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_speakers')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function series(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Series::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'speaker_members')
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
            ->singleFile();
    }

    public function getAuthzScopeLabel(): string
    {
        return 'Speaker: '.$this->name;
    }
}
