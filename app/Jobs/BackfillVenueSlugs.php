<?php

namespace App\Jobs;

use App\Actions\Venues\GenerateVenueSlugAction;
use App\Models\Venue;
use App\Support\Cache\PublicListingsCache;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BackfillVenueSlugs implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 3600;

    public function handle(
        GenerateVenueSlugAction $generateVenueSlugAction,
        PublicListingsCache $publicListingsCache,
    ): void {
        Venue::query()
            ->with([
                'address.country',
                'address.state',
                'address.district',
                'address.subdistrict',
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->chunk(100, function ($venues) use ($generateVenueSlugAction): void {
                foreach ($venues as $venue) {
                    $slug = $generateVenueSlugAction->forVenue($venue);

                    if ($venue->slug === $slug) {
                        continue;
                    }

                    Venue::withoutTimestamps(function () use ($venue, $slug): void {
                        $venue->forceFill([
                            'slug' => $slug,
                        ])->saveQuietly();
                    });
                }
            });

        $publicListingsCache->bustMajlisListing();
    }

    public function uniqueId(): string
    {
        return 'venue-slug-backfill';
    }
}
