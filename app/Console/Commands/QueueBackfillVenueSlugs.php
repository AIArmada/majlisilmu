<?php

namespace App\Console\Commands;

use App\Jobs\BackfillVenueSlugs;
use Illuminate\Console\Command;

class QueueBackfillVenueSlugs extends Command
{
    protected $signature = 'venues:queue-slug-backfill';

    protected $description = 'Queue regeneration of venue slugs using the geographic slug format.';

    public function handle(): int
    {
        BackfillVenueSlugs::dispatch();

        $this->info('Queued venue slug backfill job.');

        return self::SUCCESS;
    }
}
