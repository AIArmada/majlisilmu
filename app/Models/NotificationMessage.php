<?php

namespace App\Models;

use App\Enums\NotificationFamily;
use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationPriority;
use App\Enums\NotificationTrigger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationMessage extends Model
{
    /** @use HasFactory<\Database\Factories\NotificationMessageFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'fingerprint',
        'family',
        'trigger',
        'title',
        'body',
        'action_url',
        'entity_type',
        'entity_id',
        'priority',
        'occurred_at',
        'read_at',
        'channels_attempted',
        'meta',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'family' => NotificationFamily::class,
            'trigger' => NotificationTrigger::class,
            'priority' => NotificationPriority::class,
            'occurred_at' => 'datetime',
            'read_at' => 'datetime',
            'channels_attempted' => 'array',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<NotificationDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class, 'notification_message_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleInInbox(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $messageQuery): void {
                $messageQuery
                    ->where('meta->inbox_visible', true)
                    ->orWhereNull('meta->inbox_visible');
            })
            ->whereHas('deliveries', function (Builder $deliveryQuery): void {
                $deliveryQuery
                    ->where('channel', NotificationChannel::InApp->value)
                    ->where('status', NotificationDeliveryStatus::Delivered->value)
                    ->where(function (Builder $metaQuery): void {
                        $metaQuery
                            ->whereNull('meta->digest')
                            ->orWhere('meta->digest', false);
                    });
            });
    }

    public function markAsRead(): void
    {
        if ($this->read_at !== null) {
            return;
        }

        $this->forceFill(['read_at' => now()])->save();
    }
}
