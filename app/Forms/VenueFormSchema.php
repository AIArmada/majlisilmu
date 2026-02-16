<?php

namespace App\Forms;

use App\Enums\VenueType;
use App\Models\Venue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class VenueFormSchema
{
    /**
     * Shared createOptionForm for Venue selects.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function createOptionForm(): array
    {
        return [
            TextInput::make('name')
                ->label(__('Nama Lokasi'))
                ->required()
                ->maxLength(255)
                ->placeholder(__('cth: Dewan Serbaguna, Dewan A')),

            Select::make('type')
                ->label(__('Jenis Lokasi'))
                ->required()
                ->options(VenueType::class)
                ->placeholder(__('Select type...')),

            SpatieMediaLibraryFileUpload::make('cover')
                ->label(__('Cover Image'))
                ->collection('cover')
                ->image()
                ->imageEditor()
                ->imageAspectRatio('16:9')
                ->imageEditorAspectRatioOptions(['16:9'])
                ->automaticallyCropImagesToAspectRatio()
                ->conversion('banner')
                ->responsiveImages()
                ->helperText(__('Header or banner image')),

            SpatieMediaLibraryFileUpload::make('gallery')
                ->label(__('Galeri'))
                ->collection('gallery')
                ->multiple()
                ->image()
                ->imageEditor()
                ->conversion('thumb')
                ->responsiveImages()
                ->maxFiles(10)
                ->helperText(__('Sehingga 10 gambar lokasi')),

            ...SharedFormSchema::addressFields(),

            SharedFormSchema::socialMediaRepeater('Add social media links for this venue'),
        ];
    }

    /**
     * Shared createOptionUsing callback for Venue selects.
     *
     * @param  array<string, mixed>  $data
     */
    public static function createOptionUsing(array $data, ?Schema $schema = null): string
    {
        $venue = Venue::create([
            'name' => $data['name'],
            'slug' => Str::slug((string) $data['name']).'-'.Str::lower(Str::random(7)),
            'type' => $data['type'],
            'status' => 'pending',
        ]);

        // Save media uploads (cover, gallery) via Filament's relationship-saving mechanism
        $schema?->model($venue)->saveRelationships();

        SharedFormSchema::createAddressFromData($venue, $data);
        SharedFormSchema::createSocialMediaFromData($venue, $data);

        return (string) $venue->getKey();
    }
}
