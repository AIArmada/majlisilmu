<?php

namespace App\Filament\Resources\Venues\Pages;

use App\Actions\Venues\GenerateVenueSlugAction;
use App\Filament\Resources\Venues\VenueResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateVenue extends CreateRecord
{
    protected static string $resource = VenueResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    #[\Override]
    protected function handleRecordCreation(array $data): Model
    {
        $address = is_array($this->data['address'] ?? null) ? $this->data['address'] : [];
        $data['slug'] = app(GenerateVenueSlugAction::class)->handle((string) ($data['name'] ?? 'Venue'), $address);

        return parent::handleRecordCreation($data);
    }
}
