<?php

namespace App\Filament\Resources\SlugRedirects\Pages;

use App\Filament\Resources\SlugRedirects\SlugRedirectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSlugRedirect extends CreateRecord
{
    protected static string $resource = SlugRedirectResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return SlugRedirectResource::mutateRedirectData($data);
    }
}
