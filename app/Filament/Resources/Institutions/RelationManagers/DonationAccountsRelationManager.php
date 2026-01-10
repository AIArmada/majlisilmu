<?php

namespace App\Filament\Resources\Institutions\RelationManagers;

use App\Filament\Resources\DonationAccounts\DonationAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class DonationAccountsRelationManager extends RelationManager
{
    protected static string $relationship = 'donationAccounts';

    protected static ?string $relatedResource = DonationAccountResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
