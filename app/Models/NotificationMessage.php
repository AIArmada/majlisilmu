<?php

namespace App\Models;

use App\Enums\NotificationFamily;
use App\Enums\NotificationPriority;
use App\Enums\NotificationTrigger;
use Database\Factories\NotificationMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\DatabaseNotification;

#[UseFactory(NotificationMessageFactory::class)]
class NotificationMessage extends DatabaseNotification
{
    /** @use HasFactory<NotificationMessageFactory> */
    use HasFactory;

    #[\Override]
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'data' => 'array',
            'family' => NotificationFamily::class,
            'trigger' => NotificationTrigger::class,
            'priority' => NotificationPriority::class,
            'occurred_at' => 'datetime',
            'read_at' => 'datetime',
            'inbox_visible' => 'boolean',
            'is_digest' => 'boolean',
        ]);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function visibleInInbox(Builder $query): Builder
    {
        return $query->where('inbox_visible', true);
    }

    protected function title(): Attribute
    {
        return Attribute::get(fn (): string => (string) data_get($this->data, 'title', ''));
    }

    protected function body(): Attribute
    {
        return Attribute::get(fn (): string => (string) data_get($this->data, 'body', ''));
    }

    protected function actionUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->stringOrNull((string) ($this->attributes['action_url'] ?? data_get($this->data, 'action_url'))));
    }

    protected function channelsAttempted(): Attribute
    {
        return Attribute::get(function (): array {
            $channels = data_get($this->data, 'channels_attempted', []);

            if (! is_array($channels)) {
                return [];
            }

            return collect($channels)
                ->map(static fn (mixed $channel): string => (string) $channel)
                ->filter(static fn (string $channel): bool => $channel !== '')
                ->unique()
                ->values()
                ->all();
        });
    }

    protected function meta(): Attribute
    {
        return Attribute::get(fn (): array => is_array(data_get($this->data, 'meta')) ? data_get($this->data, 'meta') : []);
    }

    protected function entityType(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->stringOrNull((string) ($this->attributes['entity_type'] ?? data_get($this->data, 'entity_type'))));
    }

    protected function entityId(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->stringOrNull((string) ($this->attributes['entity_id'] ?? data_get($this->data, 'entity_id'))));
    }

    protected function userId(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->stringOrNull($this->notifiable_id));
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return is_array($this->data) ? $this->data : [];
    }

    protected function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
