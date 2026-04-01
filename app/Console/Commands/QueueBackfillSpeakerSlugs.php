<?php

namespace App\Console\Commands;

use App\Jobs\BackfillSpeakerSlugs;
use Illuminate\Console\Command;

class QueueBackfillSpeakerSlugs extends Command
{
    protected $signature = 'speakers:queue-slug-backfill';

    protected $description = 'Queue regeneration of speaker slugs using the country-based slug format.';

    public function handle(): int
    {
        BackfillSpeakerSlugs::dispatch();

        $this->info('Queued speaker slug backfill job.');

        return self::SUCCESS;
    }
}
