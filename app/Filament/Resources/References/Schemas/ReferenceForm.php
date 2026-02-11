<?php

namespace App\Filament\Resources\References\Schemas;

use App\Enums\SocialMediaPlatform;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ReferenceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Reference Details')
                    ->components([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('author')
                            ->maxLength(255),
                        Select::make('type')
                            ->options([
                                'book' => 'Book',
                                'kitab' => 'Kitab',
                                'article' => 'Article',
                                'video' => 'Video',
                                'other' => 'Other',
                            ])
                            ->default('kitab')
                            ->required(),
                        TextInput::make('publication_year')
                            ->maxLength(255),
                        TextInput::make('publisher')
                            ->maxLength(255),
                        \Filament\Forms\Components\Toggle::make('is_canonical')
                            ->label('Canonical / Official')
                            ->helperText('Is this a standard reference?'),
                        Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'verified' => 'Verified',
                            ])
                            ->default('verified')
                            ->required(),
                        \Filament\Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])->columns(2),
                \Filament\Schemas\Components\Section::make('Imagery')
                    ->components([
                        \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('front_cover')
                            ->label('Front Cover')
                            ->collection('front_cover')
                            ->image()
                            ->imageEditor()
                            ->conversion('thumb')
                            ->responsiveImages(),
                        \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('back_cover')
                            ->label('Back Cover')
                            ->collection('back_cover')
                            ->image()
                            ->imageEditor()
                            ->conversion('thumb')
                            ->responsiveImages(),
                        \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('gallery')
                            ->label('Gallery')
                            ->collection('gallery')
                            ->multiple()
                            ->image()
                            ->imageEditor()
                            ->conversion('gallery_thumb')
                            ->responsiveImages()
                            ->maxFiles(10)
                            ->columnSpanFull(),
                    ])->columns(2),
                \Filament\Schemas\Components\Section::make('Links')
                    ->components([
                        \Filament\Forms\Components\Repeater::make('socialMedia')
                            ->relationship()
                            ->schema([
                                Select::make('platform')
                                    ->options(SocialMediaPlatform::class)
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(1),
                                TextInput::make('username')
                                    ->label('Username / Handle')
                                    ->placeholder('@username')
                                    ->columnSpan(1),
                                TextInput::make('url')
                                    ->label('URL')
                                    ->url()
                                    ->required()
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->defaultItems(0)
                            ->addActionLabel('Add Link')
                            ->itemLabel(function (array $state): ?string {
                                $platform = $state['platform'] ?? null;

                                if ($platform instanceof SocialMediaPlatform) {
                                    return $platform->getLabel();
                                }

                                if (is_string($platform)) {
                                    return SocialMediaPlatform::tryFrom($platform)?->getLabel() ?? $platform;
                                }

                                return null;
                            }),
                    ]),
                \Filament\Schemas\Components\Section::make('Description')
                    ->components([
                        \Filament\Forms\Components\Textarea::make('description')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
