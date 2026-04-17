<?php

namespace App\Data\Api\Report;

use App\Models\Report;
use Spatie\LaravelData\Data;

class ReportSubmissionData extends Data
{
    public function __construct(
        public string $id,
    ) {}

    public static function fromModel(Report $report): self
    {
        return new self(
            id: (string) $report->id,
        );
    }
}
