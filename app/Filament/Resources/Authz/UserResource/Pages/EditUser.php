<?php

declare(strict_types=1);

namespace App\Filament\Resources\Authz\UserResource\Pages;

use App\Filament\Resources\Authz\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;
}
