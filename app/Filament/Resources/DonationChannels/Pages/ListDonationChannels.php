<?php

namespace App\Filament\Resources\DonationChannels\Pages;

use App\Filament\Resources\DonationChannels\DonationChannelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDonationChannels extends ListRecords
{
    protected static string $resource = DonationChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
