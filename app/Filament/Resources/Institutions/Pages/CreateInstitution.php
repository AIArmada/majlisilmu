<?php

namespace App\Filament\Resources\Institutions\Pages;

use App\Actions\Institutions\GenerateInstitutionSlugAction;
use App\Filament\Resources\Institutions\InstitutionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateInstitution extends CreateRecord
{
    protected static string $resource = InstitutionResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    #[\Override]
    protected function handleRecordCreation(array $data): Model
    {
        $address = is_array($this->data['address'] ?? null) ? $this->data['address'] : [];
        $data['slug'] = app(GenerateInstitutionSlugAction::class)->handle((string) ($data['name'] ?? 'Institution'), $address);

        return parent::handleRecordCreation($data);
    }
}
