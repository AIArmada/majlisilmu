<?php

namespace App\Filament\Resources\DonationAccounts\Pages;

use App\Filament\Resources\DonationAccounts\DonationAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDonationAccount extends CreateRecord
{
    protected static string $resource = DonationAccountResource::class;
}
