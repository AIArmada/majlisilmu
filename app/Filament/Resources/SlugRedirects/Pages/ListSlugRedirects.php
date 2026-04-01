<?php

namespace App\Filament\Resources\SlugRedirects\Pages;

use App\Filament\Resources\SlugRedirects\SlugRedirectResource;
use Filament\Resources\Pages\ListRecords;

class ListSlugRedirects extends ListRecords
{
    protected static string $resource = SlugRedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
