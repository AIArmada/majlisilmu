<?php

declare(strict_types=1);

namespace App\Filament\Resources\Authz\UserResource\Pages;

use App\Filament\Resources\Authz\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['roles'], $data['permissions']);

        return $data;
    }
}
