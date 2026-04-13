<?php

declare(strict_types=1);

namespace App\Support\ApiDocumentation\Schemas;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Contracts\Support\Arrayable;

#[SchemaName('EventParticipation')]
final readonly class EventParticipation implements Arrayable
{
    public function __construct(
        public string $id,
        public string $role,
        public ?string $role_label,
        public ?string $display_name,
        public ?EventSummary $event,
    ) {}

    /**
     * @return array{id: string, role: string, role_label: ?string, display_name: ?string, event: EventSummary|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'role_label' => $this->role_label,
            'display_name' => $this->display_name,
            'event' => $this->event?->toArray(),
        ];
    }
}
