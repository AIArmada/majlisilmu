<?php

namespace App\Filament\Resources\SlugRedirects\Pages;

use App\Filament\Resources\SlugRedirects\SlugRedirectResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSlugRedirect extends ViewRecord
{
    protected static string $resource = SlugRedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
