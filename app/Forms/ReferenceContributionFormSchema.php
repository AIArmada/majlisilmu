<?php

namespace App\Forms;

use App\Enums\ReferenceType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;

class ReferenceContributionFormSchema
{
    /**
     * @return array<int, Component>
     */
    public static function components(bool $includeMedia = false): array
    {
        $components = [
            Section::make(__('Reference Details'))
                ->schema([
                    TextInput::make('title')
                        ->label(__('Title'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('author')
                        ->label(__('Author'))
                        ->maxLength(255),
                    Select::make('type')
                        ->label(__('Reference Type'))
                        ->options(ReferenceType::class)
                        ->default(ReferenceType::Book->value)
                        ->required(),
                    TextInput::make('publication_year')
                        ->label(__('Publication Year'))
                        ->maxLength(255),
                    TextInput::make('publisher')
                        ->label(__('Publisher'))
                        ->maxLength(255),
                    Textarea::make('description')
                        ->label(__('Description'))
                        ->rows(5)
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make(__('Links'))
                ->schema([
                    SharedFormSchema::socialMediaRepeater('Add relevant links for this reference (e.g. YouTube video, Blog article, etc.)'),
                ]),
        ];

        if ($includeMedia) {
            array_splice($components, 1, 0, [
                Section::make(__('Imagery'))
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('front_cover')
                            ->label(__('Front Cover'))
                            ->collection('front_cover')
                            ->image()
                            ->imageEditor()
                            ->conversion('thumb')
                            ->responsiveImages(),
                        SpatieMediaLibraryFileUpload::make('back_cover')
                            ->label(__('Back Cover'))
                            ->collection('back_cover')
                            ->image()
                            ->imageEditor()
                            ->conversion('thumb')
                            ->responsiveImages(),
                        SpatieMediaLibraryFileUpload::make('gallery')
                            ->label(__('Gallery'))
                            ->collection('gallery')
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->conversion('gallery_thumb')
                            ->responsiveImages()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
        }

        return $components;
    }
}
