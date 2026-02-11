<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Address extends Model
{
    use HasUuids;

    protected $fillable = [
        'addressable_type',
        'addressable_id',
        'type',
        'line1',
        'line2',
        'postcode',
        'country_id',
        'state_id',
        'district_id',
        'subdistrict_id',
        'city_id',
        'lat',
        'lng',
        'google_maps_url',
        'google_place_id',
        'waze_url',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }

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
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * @return BelongsTo<Subdistrict, $this>
     */
    public function subdistrict(): BelongsTo
    {
        return $this->belongsTo(Subdistrict::class);
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
