<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;

class IndexEventsToTypesense extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:index-events 
                            {--fresh : Drop existing index and recreate}
                            {--chunk=500 : Number of records to process per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Index all searchable events to Typesense';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (config('scout.driver') !== 'typesense') {
            $this->error('Scout driver is not set to Typesense. Current driver: '.config('scout.driver'));
            $this->info('Set SCOUT_DRIVER=typesense in your .env file.');

            return self::FAILURE;
        }

        $this->info('Starting event indexing to Typesense...');

        // Fresh index - flush first
        if ($this->option('fresh')) {
            $this->warn('Flushing existing index...');

            try {
                Event::removeAllFromSearch();
                $this->info('Index cleared.');
            } catch (\Exception $e) {
                $this->warn('Could not clear index: '.$e->getMessage());
            }
        }

        // Count searchable events
        $total = Event::query()
            ->whereState('status', \App\States\EventStatus\Approved::class)
            ->where('visibility', 'public')
            ->count();

        $this->info("Found {$total} searchable events.");

        if ($total === 0) {
            $this->warn('No events to index.');

            return self::SUCCESS;
        }

        // Process in chunks
        $chunk = (int) $this->option('chunk');
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $indexed = 0;

        Event::query()
            ->whereState('status', \App\States\EventStatus\Approved::class)
            ->where('visibility', 'public')
            ->with(['institution', 'venue', 'speakers', 'state'])
            ->chunkById($chunk, function ($events) use (&$indexed, $bar) {
                $events->searchable();
                $indexed += $events->count();
                $bar->advance($events->count());
            });

        $bar->finish();
        $this->newLine(2);

        $this->info("Successfully indexed {$indexed} events to Typesense.");

        return self::SUCCESS;
    }
}
