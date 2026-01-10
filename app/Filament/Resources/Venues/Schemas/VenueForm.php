<?php

namespace App\Filament\Resources\Venues\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VenueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Details')
                    ->components([
                        Select::make('institution_id')
                            ->relationship('institution', 'name')
                            ->searchable()
                            ->preload(),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                    ])
                    ->columns(2),
                Section::make('Location')
                    ->components([
                        Select::make('state_id')
                            ->relationship('state', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('district_id')
                            ->relationship('district', 'name')
                            ->searchable()
                            ->preload(),
                        TextInput::make('address_line1')
                            ->maxLength(255),
                        TextInput::make('address_line2')
                            ->maxLength(255),
                        TextInput::make('postcode')
                            ->maxLength(16),
                        TextInput::make('city')
                            ->maxLength(255),
                        TextInput::make('lat')
                            ->numeric()
                            ->minValue(-90)
                            ->maxValue(90),
                        TextInput::make('lng')
                            ->numeric()
                            ->minValue(-180)
                            ->maxValue(180),
                    ])
                    ->columns(2),
                Section::make('Maps & Facilities')
                    ->components([
                        TextInput::make('google_maps_place_id')
                            ->maxLength(255),
                        TextInput::make('waze_place_url')
                            ->url()
                            ->maxLength(255),
                        CheckboxList::make('facilities')
                            ->options([
                                'parking' => 'Parking',
                                'oku' => 'OKU Access',
                                'women_section' => 'Women Section',
                                'ablution_area' => 'Ablution Area',
                            ])
                            ->columns(2)
                            ->afterStateHydrated(function (CheckboxList $component, $state): void {
                                if (! is_array($state)) {
                                    return;
                                }

                                $component->state(array_keys(array_filter($state)));
                            })
                            ->dehydrateStateUsing(function ($state): array {
                                return array_fill_keys($state ?? [], true);
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
