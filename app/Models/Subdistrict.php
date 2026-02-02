<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subdistrict extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'country_id',
        'state_id',
        'district_id',
        'name',
        'country_code',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(\Nnjeim\World\Models\Country::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }
}
