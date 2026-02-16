<?php

namespace App\Forms;

use App\Enums\InstitutionType;
use App\Models\Institution;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class InstitutionFormSchema
{
    /**
     * Shared createOptionForm for Institution selects.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function createOptionForm(): array
    {
        return [
            TextInput::make('name')
                ->label(__('Institution Name'))
                ->required()
                ->maxLength(255)
                ->placeholder(__('e.g., Masjid Al-Falah, Surau An-Nur')),

            Select::make('type')
                ->label(__('Institution Type'))
                ->required()
                ->options(InstitutionType::class)
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
                ->label(__('Gallery'))
                ->collection('gallery')
                ->multiple()
                ->image()
                ->imageEditor()
                ->conversion('gallery_thumb')
                ->responsiveImages()
                ->maxFiles(10)
                ->helperText(__('Up to 10 photos of the institution')),

            ...SharedFormSchema::addressFields(),

            SharedFormSchema::socialMediaRepeater('Add social media links for this institution'),
        ];
    }

    /**
     * Shared createOptionUsing callback for Institution selects.
     *
     * @param  array<string, mixed>  $data
     */
    public static function createOptionUsing(array $data, ?Schema $schema = null): string
    {
        $institution = Institution::create([
            'name' => $data['name'],
            'slug' => Str::slug((string) $data['name']).'-'.Str::lower(Str::random(7)),
            'type' => $data['type'],
            'status' => 'pending',
        ]);

        // Save media uploads (cover, gallery) via Filament's relationship-saving mechanism
        $schema?->model($institution)->saveRelationships();

        SharedFormSchema::createAddressFromData($institution, $data);
        SharedFormSchema::createSocialMediaFromData($institution, $data);

        return (string) $institution->getKey();
    }
}
