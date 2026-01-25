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
    ];

    protected function casts(): array
    {
        return [
            'qualifications' => 'array',
            'is_freelance' => 'boolean',
        ];
    }

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
                    // If parts exist, join them. If not, don't overwrite if manual?
                    // User said "lock one as derived-only". So we overwrite.

                    // De-duplicate
                    $parts = array_unique($parts);

                    if (!empty($parts)) {
                        $speaker->post_nominal = implode(', ', $parts);
                    }
                }
            }
        });
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->hasMedia('avatar')) {
            return $this->getFirstMediaUrl('avatar');
        }

        return null;
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_speakers')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'speaker_topic');
    }

    public function institutions(): BelongsToMany
    {
        return $this->belongsToMany(Institution::class, 'institution_speaker')
            ->withPivot(['position', 'is_primary', 'joined_at'])
            ->withTimestamps();
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
        return 'Speaker: ' . $this->name;
    }

    /**
     * Compatibility alias for job_title
     */
    public function getTitleAttribute(): ?string
    {
        return $this->job_title;
    }
}
