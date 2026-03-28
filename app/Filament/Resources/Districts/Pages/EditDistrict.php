<?php

namespace App\Filament\Resources\Districts\Pages;

use App\Filament\Resources\Districts\DistrictResource;
use Filament\Resources\Pages\EditRecord;

class EditDistrict extends EditRecord
{
    protected static string $resource = DistrictResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            DistrictResource::makeDeleteAction(),
        ];
    }
}
