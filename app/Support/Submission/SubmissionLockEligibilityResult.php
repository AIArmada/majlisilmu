<?php

namespace App\Support\Submission;

final class SubmissionLockEligibilityResult
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public readonly bool $eligible,
        public readonly array $reasons = [],
    ) {}

    public static function eligible(): self
    {
        return new self(true, []);
    }

    /**
     * @param  list<string>  $reasons
     */
    public static function ineligible(array $reasons): self
    {
        return new self(false, $reasons);
    }
}
