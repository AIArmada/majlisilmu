<?php

declare(strict_types=1);

namespace App\Support\ApiDocumentation\Schemas;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Contracts\Support\Arrayable;

#[SchemaName('SpeakerDetailResponse')]
final readonly class SpeakerDetailResponse implements Arrayable
{
    /**
     * @param  array{request_id: string}  $meta
     */
    public function __construct(
        public SpeakerDetailPage $data,
        public array $meta,
    ) {}

    /**
     * @return array{data: SpeakerDetailPage, meta: array{request_id: string}}
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data->toArray(),
            'meta' => $this->meta,
        ];
    }
}
