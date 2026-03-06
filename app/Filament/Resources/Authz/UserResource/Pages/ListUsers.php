<?php

declare(strict_types=1);

namespace App\Filament\Resources\Authz\UserResource\Pages;

use App\Filament\Resources\Authz\UserResource;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;
}
