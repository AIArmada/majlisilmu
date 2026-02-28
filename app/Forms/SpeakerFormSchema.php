<?php

namespace App\Forms;

use App\Enums\Gender;
use App\Enums\Honorific;
use App\Enums\PostNominal;
use App\Enums\PreNominal;
use App\Forms\Components\Select;
use App\Models\District;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\State;
use App\Models\Subdistrict;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class SpeakerFormSchema
{
    /**
     * Shared createOptionForm for Speaker selects.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function createOptionForm(): array
    {
        return [
            TextInput::make('name')
                ->label(__('Speaker Name'))
                ->required()
                ->maxLength(255)
                ->placeholder(__('e.g., Ustaz Ahmad bin Hassan')),

            Radio::make('gender')
                ->label(__('Gender'))
                ->required()
                ->options(Gender::class)
                ->default(Gender::Male->value)
                ->inline(),

            SpatieMediaLibraryFileUpload::make('avatar')
                ->label(__('Avatar'))
                ->collection('avatar')
                ->avatar()
                ->alignCenter()
                ->imageEditor()
                ->circleCropper()
                ->image()
                ->conversion('thumb')
                ->belowContent(
                    Schema::center([
                        Text::make(__('Recommended: Square image, at least 400x400px'))
                            ->extraAttributes(['class' => 'text-center']),
                    ])
                ),

            SpatieMediaLibraryFileUpload::make('cover')
                ->label(__('Cover Image'))
                ->collection('cover')
                ->image()
                ->imageEditor()
                ->imageAspectRatio('16:9')
                ->automaticallyOpenImageEditorForAspectRatio()
                ->imageEditorAspectRatioOptions(['16:9'])
                ->automaticallyCropImagesToAspectRatio()
                ->responsiveImages()
                ->conversion('banner')
                ->helperText(__('Cover image for speaker profile')),

            RichEditor::make('bio')
                ->label(__('Biography'))
                ->json()
                ->placeholder(__('Share a short biography of the speaker')),

            Select::make('honorific')
                ->label(__('Honorific'))
                ->multiple()
                ->options(Honorific::class)
                ->searchable()
                ->placeholder(__('Select honorifics')),

            Select::make('pre_nominal')
                ->label(__('Pre-nominal'))
                ->multiple()
                ->options(PreNominal::class)
                ->searchable()
                ->placeholder(__('Select pre-nominals')),

            Select::make('post_nominal')
                ->label(__('Post-nominal'))
                ->multiple()
                ->options(PostNominal::class)
                ->searchable()
                ->placeholder(__('Select post-nominals')),

            TextInput::make('job_title')
                ->label(__('Job Title'))
                ->maxLength(255)
                ->placeholder(__('e.g., Imam, Lecturer')),

            Select::make('state_id')
                ->label(__('Negeri'))
                ->options(fn () => State::where('country_id', 132)->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->afterStateUpdatedJs(<<<'JS'
                    $set('district_id', null)
                    $set('subdistrict_id', null)
                    JS),

            Select::make('district_id')
                ->label(__('Daerah'))
                ->options(function (Get $get) {
                    $stateId = $get('state_id');
                    if (! $stateId) {
                        return [];
                    }

                    return District::where('state_id', $stateId)
                        ->orderBy('name')
                        ->pluck('name', 'id');
                })
                ->searchable()
                ->afterStateUpdatedJs(<<<'JS'
                    $set('subdistrict_id', null)
                    JS)
                ->visibleJs(<<<'JS'
                    $get('state_id') != null && $get('state_id') !== ''
                    JS),

            Select::make('subdistrict_id')
                ->label(__('Daerah Kecil / Bandar / Mukim'))
                ->options(function (Get $get) {
                    $districtId = $get('district_id');
                    if (! $districtId) {
                        return [];
                    }

                    return Subdistrict::where('district_id', $districtId)
                        ->orderBy('name')
                        ->pluck('name', 'id');
                })
                ->searchable()
                ->visibleJs(<<<'JS'
                    $get('district_id') != null && $get('district_id') !== ''
                    JS),

            Select::make('institution_id')
                ->label(__('Affiliated Institution'))
                ->options(fn () => Institution::query()
                    ->whereIn('status', ['verified', 'pending'])
                    ->orderBy('name')
                    ->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->live()
                ->closeOnSelect()
                ->createOptionForm(InstitutionFormSchema::createOptionForm())
                ->createOptionUsing(fn (array $data, Schema $schema): string => InstitutionFormSchema::createOptionUsing($data, $schema)),

            TextInput::make('institution_position')
                ->label(__('Position'))
                ->maxLength(255)
                ->placeholder(__('e.g., Imam, Mudir, Committee Member'))
                ->visible(fn (Get $get): bool => filled($get('institution_id'))),

            Toggle::make('is_freelance')
                ->label(__('Penceramah Bebas'))
                ->default(false),
        ];
    }

    /**
     * Shared createOptionUsing callback for Speaker selects.
     *
     * @param  array<string, mixed>  $data
     */
    public static function createOptionUsing(array $data, ?Schema $schema = null): string
    {
        $speaker = Speaker::create([
            'name' => $data['name'],
            'gender' => $data['gender'] ?? Gender::Male->value,
            'honorific' => empty($data['honorific']) ? null : $data['honorific'],
            'pre_nominal' => empty($data['pre_nominal']) ? null : $data['pre_nominal'],
            'post_nominal' => empty($data['post_nominal']) ? null : $data['post_nominal'],
            'job_title' => $data['job_title'] ?? null,
            'bio' => $data['bio'] ?? null,
            'is_freelance' => (bool) ($data['is_freelance'] ?? false),
            'slug' => Str::slug((string) $data['name']).'-'.Str::lower(Str::random(7)),
            'status' => 'pending',
        ]);

        // Save media uploads (avatar/cover) via Filament's relationship-saving mechanism
        $schema?->model($speaker)->saveRelationships();

        if (! empty($data['state_id'])) {
            $speaker->address()->create([
                'state_id' => $data['state_id'],
                'district_id' => $data['district_id'] ?? null,
                'subdistrict_id' => $data['subdistrict_id'] ?? null,
            ]);
        }

        $institutionIds = [];

        if (filled($data['institution_id'] ?? null)) {
            $institutionIds[] = (string) $data['institution_id'];
        } elseif (! empty($data['institutions'])) {
            $institutionIds = array_filter(
                array_map(
                    fn (mixed $institutionId): ?string => filled($institutionId) ? (string) $institutionId : null,
                    (array) $data['institutions']
                )
            );
        }

        if ($institutionIds !== []) {
            $position = filled($data['institution_position'] ?? null) ? $data['institution_position'] : null;
            $institutionData = [];

            foreach ($institutionIds as $institutionId) {
                $institutionData[$institutionId] = [
                    'position' => $position,
                ];
            }

            $speaker->institutions()->attach($institutionData);
        }

        return (string) $speaker->getKey();
    }
}
