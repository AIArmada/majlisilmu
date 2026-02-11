<?php

namespace App\Filament\Resources\Tags\Schemas;

use App\Enums\TagType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tag Details')
                    ->components([
                        Select::make('type')
                            ->options(TagType::class)
                            ->enum(TagType::class)
                            ->required()
                            ->native(false)
                            ->helperText(function ($state) {
                                if (! $state) {
                                    return null;
                                }

                                $type = TagType::tryFrom($state);

                                return $type ? $type->description() : null;
                            }),

                        TextInput::make('name.ms')
                            ->label('Name (Malay)')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Tafsir, Fiqh, Rasuah'),

                        TextInput::make('name.en')
                            ->label('Name (English)')
                            ->maxLength(255)
                            ->placeholder('e.g., Exegesis, Jurisprudence, Corruption'),

                        Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'verified' => 'Verified',
                            ])
                            ->default('verified')
                            ->required()
                            ->native(false)
                            ->helperText('Verified tags are available for public use'),

                        TextInput::make('order_column')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first within the same type'),
                    ])->columns(2),
            ]);
    }
}
