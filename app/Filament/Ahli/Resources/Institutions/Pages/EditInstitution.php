<?php

namespace App\Filament\Ahli\Resources\Institutions\Pages;

use App\Filament\Ahli\Resources\Institutions\InstitutionResource;
use Filament\Resources\Pages\EditRecord;

class EditInstitution extends EditRecord
{
    protected static string $resource = InstitutionResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}

