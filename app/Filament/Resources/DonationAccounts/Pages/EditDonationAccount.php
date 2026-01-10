<?php

namespace App\Filament\Resources\DonationAccounts\Pages;

use App\Filament\Resources\DonationAccounts\DonationAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDonationAccount extends EditRecord
{
    protected static string $resource = DonationAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
