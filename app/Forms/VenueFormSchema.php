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

            SpatieMediaLibraryFileUpload::make('main')
                ->label(__('Gambar Utama'))
                ->collection('main')
                ->image()
                ->imageEditor()
                ->conversion('thumb')
                ->responsiveImages()
                ->maxSize(5120)
                ->helperText(__('Gambar pengepala atau imej hiasan')),

            SpatieMediaLibraryFileUpload::make('gallery')
                ->label(__('Galeri'))
                ->collection('gallery')
                ->multiple()
                ->image()
                ->imageEditor()
                ->conversion('thumb')
                ->responsiveImages()
                ->maxSize(5120)
                ->maxFiles(10)
                ->helperText(__('Sehingga 10 gambar lokasi')),

            ...SharedFormSchema::addressFields(),

            SharedFormSchema::socialMediaRepeater('Tambah pautan media sosial untuk lokasi ini'),
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
            'slug' => Str::slug($data['name']).'-'.Str::random(6),
            'type' => $data['type'],
            'status' => 'pending',
        ]);

        // Save media uploads (main, gallery) via Filament's relationship-saving mechanism
        $schema?->model($venue)->saveRelationships();

        SharedFormSchema::createAddressFromData($venue, $data);
        SharedFormSchema::createSocialMediaFromData($venue, $data);

        return (string) $venue->getKey();
    }
}
