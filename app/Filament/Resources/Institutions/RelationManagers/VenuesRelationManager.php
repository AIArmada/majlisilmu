<?php

namespace App\Filament\Resources\Institutions\RelationManagers;

use App\Filament\Resources\Venues\VenueResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Table;

class VenuesRelationManager extends RelationManager
{
    protected static string $relationship = 'venues';

    protected static ?string $relatedResource = VenueResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
