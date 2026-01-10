<?php

namespace App\Filament\Resources\DonationAccounts\Pages;

use App\Filament\Resources\DonationAccounts\DonationAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDonationAccounts extends ListRecords
{
    protected static string $resource = DonationAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
