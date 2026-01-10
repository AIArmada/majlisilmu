<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DonationAccount extends Model
{
    /** @use HasFactory<\Database\Factories\DonationAccountFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'institution_id',
        'label',
        'recipient_name',
        'bank_name',
        'account_number',
        'duitnow_id',
        'qr_asset_id',
        'verification_status',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function qrAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'qr_asset_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'entity');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'entity');
    }
}
