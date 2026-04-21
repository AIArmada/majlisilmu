<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

it('documents validate-only remediation details in the admin api reference', function () {
    $markdown = file_get_contents(base_path('docs/MAJLISILMU_MOBILE_API_REFERENCE.md')) ?: '';

    expect($markdown)
        ->toContain('validate_only=true')
        ->toContain('Validation failures in validate-only mode return machine-readable remediation details')
        ->toContain('`error.details.fix_plan`')
        ->toContain('`error.details.remaining_blockers`')
        ->toContain('`error.details.normalized_payload_preview`')
        ->toContain('`error.details.can_retry`');
});
