<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    /** @use HasFactory<\Database\Factories\DistrictFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'country_id',
        'state_id',
        'country_code',
        'name',
    ];

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<State, $this>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /**
     * @return HasMany<Subdistrict, $this>
     */
    public function subdistricts(): HasMany
    {
        return $this->hasMany(Subdistrict::class);
    }

    /**
     * @return HasMany<Institution, $this>
     */
    public function institutions(): HasMany
    {
        return $this->hasMany(Institution::class);
    }

    /**
     * @return HasMany<Venue, $this>
     */
    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
