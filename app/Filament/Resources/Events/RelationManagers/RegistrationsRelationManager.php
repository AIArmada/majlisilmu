<?php

namespace App\Filament\Resources\Events\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    #[\Override]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->preload(),
                Select::make('event_session_id')
                    ->relationship(
                        name: 'session',
                        titleAttribute: 'id',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->where('event_id', $this->getOwnerRecord()->id),
                    )
                    ->getOptionLabelFromRecordUsing(fn (\App\Models\EventSession $record): string => $record->starts_at?->translatedFormat('d M Y, h:i A') ?? (string) $record->id)
                    ->searchable()
                    ->preload(),
                TextInput::make('name')
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->tel()
                    ->maxLength(50),
                Select::make('status')
                    ->options([
                        'registered' => 'Registered',
                        'cancelled' => 'Cancelled',
                        'attended' => 'Attended',
                        'no_show' => 'No show',
                    ])
                    ->required(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('email')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('session.starts_at')
                    ->label('Session')
                    ->dateTime()
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
