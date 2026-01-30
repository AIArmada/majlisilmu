<?php

namespace App\Filament\Resources\Institutions\RelationManagers;

use App\Filament\Resources\Series\SeriesResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Table;

class SeriesRelationManager extends RelationManager
{
    protected static string $relationship = 'series';

    protected static ?string $relatedResource = SeriesResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
