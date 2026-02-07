<?php

namespace App\Forms;

use App\Enums\Gender;
use App\Enums\Honorific;
use App\Enums\PreNominal;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\State;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;

class SpeakerFormSchema
{
    /**
     * Shared createOptionForm for Speaker selects.
     *
     * @return array<int, \Filament\Forms\Components\Component>
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
                ->imageEditor()
                ->image()
                ->maxSize(5120)
                ->helperText(__('Recommended: Square image, at least 400x400px')),

            Select::make('honorific')
                ->label(__('Honorific'))
                ->multiple()
                ->options(Honorific::class)
                ->searchable()
                ->placeholder(__('Select honorifics')),

            Select::make('pre_nominal')
                ->label(__('Pre-nominal'))
                ->options(PreNominal::class)
                ->searchable()
                ->placeholder(__('Select pre-nominals')),

            TextInput::make('job_title')
                ->label(__('Job Title'))
                ->maxLength(255)
                ->placeholder(__('e.g., Imam, Lecturer')),

            Select::make('state_id')
                ->label(__('State'))
                ->options(fn () => State::where('country_id', 132)->pluck('name', 'id'))
                ->searchable()
                ->preload(),

            Select::make('institutions')
                ->label(__('Affiliated Institutions'))
                ->options(fn () => Institution::query()
                    ->whereIn('status', ['verified', 'pending'])
                    ->orderBy('name')
                    ->pluck('name', 'id'))
                ->multiple()
                ->searchable()
                ->preload(),
        ];
    }

    /**
     * Shared createOptionUsing callback for Speaker selects.
     */
    public static function createOptionUsing(array $data): string
    {
        $speaker = Speaker::create([
            'name' => $data['name'],
            'gender' => $data['gender'] ?? Gender::Male->value,
            'honorific' => ! empty($data['honorific']) ? $data['honorific'] : null,
            'pre_nominal' => ! empty($data['pre_nominal']) ? $data['pre_nominal'] : null,
            'job_title' => $data['job_title'] ?? null,
            'slug' => Str::slug($data['name']).'-'.Str::random(6),
            'status' => 'pending',
        ]);

        if (! empty($data['state_id'])) {
            $speaker->address()->create([
                'state_id' => $data['state_id'],
            ]);
        }

        if (! empty($data['institutions'])) {
            $speaker->institutions()->attach($data['institutions']);
        }

        return (string) $speaker->getKey();
    }
}
