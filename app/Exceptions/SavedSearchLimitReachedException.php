<?php

namespace App\Exceptions;

use RuntimeException;

class SavedSearchLimitReachedException extends RuntimeException
{
    public function __construct(
        public readonly int $maximum,
    ) {
        parent::__construct('saved_search_limit_reached');
    }
}
