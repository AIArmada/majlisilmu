<?php

namespace App\Forms;

use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventParticipantRole;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Enums\TagType;
use App\Forms\Components\Select;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Series;
use App\Models\Space;
use App\Models\Speaker;
use App\Models\Tag;
use App\Models\Venue;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Nnjeim\World\Models\Language;

class EventContributionFormSchema
{
    /**
     * @return array<int, Component>
     */
    public static function components(): array
    {
        return [
            Section::make(__('Maklumat Majlis'))
                ->schema([
                    TextInput::make('title')
                        ->label(__('Event Title'))
                        ->required()
                        ->maxLength(255),
                    Select::make('event_type')
                        ->label(__('Jenis Majlis'))
                        ->options(self::eventTypeOptions())
                        ->multiple()
                        ->closeOnSelect()
                        ->searchable()
                        ->required(),
                    RichEditor::make('description')
                        ->label(__('Description'))
                        ->json()
                        ->columnSpanFull(),
                    DateTimePicker::make('starts_at')
                        ->label(__('Starts At'))
                        ->seconds(false)
                        ->required(),
                    DateTimePicker::make('ends_at')
                        ->label(__('Ends At'))
                        ->seconds(false),
                    TextInput::make('timezone')
                        ->label(__('Timezone'))
                        ->required()
                        ->maxLength(64),
                    Select::make('event_format')
                        ->label(__('Format Majlis'))
                        ->options(EventFormat::class)
                        ->required(),
                    Select::make('visibility')
                        ->label(__('Visibility'))
                        ->options(EventVisibility::class)
                        ->required(),
                    TextInput::make('event_url')
                        ->label(__('Event URL'))
                        ->url()
                        ->maxLength(255),
                    TextInput::make('live_url')
                        ->label(__('Live URL'))
                        ->url()
                        ->maxLength(255),
                    TextInput::make('recording_url')
                        ->label(__('Recording URL'))
                        ->url()
                        ->maxLength(255),
                ])
                ->columns(2),
            Section::make(__('Audience & Language'))
                ->schema([
                    Select::make('gender')
                        ->label(__('Gender'))
                        ->options(EventGenderRestriction::class)
                        ->required(),
                    Select::make('age_group')
                        ->label(__('Age Group'))
                        ->options(EventAgeGroup::class)
                        ->multiple()
                        ->closeOnSelect()
                        ->required(),
                    Toggle::make('children_allowed')
                        ->label(__('Children Allowed'))
                        ->default(true),
                    Toggle::make('is_muslim_only')
                        ->label(__('Muslim Only'))
                        ->default(false),
                    Select::make('language_ids')
                        ->label(__('Languages'))
                        ->options(fn (): array => Language::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make(__('Kategori & Rujukan'))
                ->schema([
                    Select::make('domain_tags')
                        ->label(__('Kategori'))
                        ->options(fn (): array => self::tagOptions(TagType::Domain))
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->closeOnSelect(),
                    Select::make('discipline_tags')
                        ->label(__('Bidang Ilmu'))
                        ->options(fn (): array => self::tagOptions(TagType::Discipline))
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->closeOnSelect()
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label(__('Nama Bidang'))
                                ->required()
                                ->maxLength(255),
                        ])
                        ->createOptionUsing(fn (array $data): string => self::createPendingTag($data, TagType::Discipline)),
                    Select::make('source_tags')
                        ->label(__('Sumber Utama'))
                        ->options(fn (): array => self::tagOptions(TagType::Source))
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->closeOnSelect(),
                    Select::make('issue_tags')
                        ->label(__('Tema / Isu'))
                        ->options(fn (): array => self::tagOptions(TagType::Issue))
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->closeOnSelect()
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label(__('Nama Tema'))
                                ->required()
                                ->maxLength(255),
                        ])
                        ->createOptionUsing(fn (array $data): string => self::createPendingTag($data, TagType::Issue)),
                    Select::make('reference_ids')
                        ->label(__('References'))
                        ->options(fn (): array => Reference::query()->orderBy('title')->pluck('title', 'id')->all())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make(__('Organizer & Location'))
                ->schema([
                    Select::make('organizer_type')
                        ->label(__('Organizer Type'))
                        ->options([
                            Institution::class => __('Institution'),
                            Speaker::class => __('Speaker'),
                        ])
                        ->live(),
                    Select::make('organizer_id')
                        ->label(__('Organizer'))
                        ->options(fn (Get $get): array => self::organizerOptions($get('organizer_type')))
                        ->searchable()
                        ->preload(),
                    Select::make('series_ids')
                        ->label(__('Series'))
                        ->options(fn (): array => Series::query()->orderBy('title')->pluck('title', 'id')->all())
                        ->multiple()
                        ->searchable()
                        ->preload(),
                    Select::make('institution_id')
                        ->label(__('Institution'))
                        ->options(fn (): array => Institution::query()->whereIn('status', ['verified', 'pending'])->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->disabled(fn (Get $get): bool => filled($get('venue_id'))),
                    Select::make('venue_id')
                        ->label(__('Venue'))
                        ->options(fn (): array => Venue::query()->whereIn('status', ['verified', 'pending'])->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->disabled(fn (Get $get): bool => filled($get('institution_id'))),
                    Select::make('space_id')
                        ->label(__('Space'))
                        ->options(function (Get $get): array {
                            $institutionId = $get('institution_id');

                            if (! filled($institutionId)) {
                                return [];
                            }

                            return Space::query()
                                ->where('is_active', true)
                                ->where(function ($query) use ($institutionId): void {
                                    $query
                                        ->whereHas('institutions', fn ($relatedQuery) => $relatedQuery->where('institutions.id', $institutionId))
                                        ->orWhereDoesntHave('institutions');
                                })
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all();
                        })
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get): bool => filled($get('institution_id')) && blank($get('venue_id'))),
                ])
                ->columns(2),
            Section::make(__('Penceramah & Peranan'))
                ->schema([
                    Select::make('speaker_ids')
                        ->label(__('Speakers'))
                        ->options(fn (): array => Speaker::query()
                            ->whereIn('status', ['verified', 'pending'])
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (Speaker $speaker): array => [(string) $speaker->id => $speaker->formatted_name])
                            ->all())
                        ->multiple()
                        ->searchable()
                        ->preload(),
                    Repeater::make('other_key_people')
                        ->label(__('Other Key People'))
                        ->default([])
                        ->schema([
                            Select::make('role')
                                ->label(__('Role'))
                                ->options(EventParticipantRole::nonSpeakerOptions())
                                ->required(),
                            Select::make('speaker_id')
                                ->label(__('Linked Speaker Profile'))
                                ->options(fn (): array => Speaker::query()
                                    ->whereIn('status', ['verified', 'pending'])
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (Speaker $speaker): array => [(string) $speaker->id => $speaker->formatted_name])
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->live(),
                            TextInput::make('name')
                                ->label(__('Display Name'))
                                ->required(fn (Get $get): bool => blank($get('speaker_id')))
                                ->disabled(fn (Get $get): bool => filled($get('speaker_id')))
                                ->dehydrated(fn (Get $get): bool => blank($get('speaker_id')))
                                ->maxLength(255),
                            Toggle::make('is_public')
                                ->label(__('Public'))
                                ->default(true),
                            Textarea::make('notes')
                                ->label(__('Notes'))
                                ->rows(2)
                                ->maxLength(500),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                ])
                ->columns(1),
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function eventTypeOptions(): array
    {
        return collect(EventType::cases())
            ->mapToGroups(fn (EventType $type): array => [
                $type->getGroup() => [$type->value => $type->getLabel()],
            ])
            ->map(fn ($group): array => $group->collapse()->toArray())
            ->toArray();
    }

    /**
     * @return array<string, string>
     */
    private static function tagOptions(TagType $type): array
    {
        return Tag::query()
            ->ofType($type)
            ->whereIn('status', ['verified', 'pending'])
            ->ordered()
            ->get()
            ->mapWithKeys(fn (Tag $tag): array => [(string) $tag->id => $tag->getTranslation('name', app()->getLocale())])
            ->toArray();
    }

    /**
     * @param  array{name: string}  $data
     */
    private static function createPendingTag(array $data, TagType $type): string
    {
        $tag = Tag::create([
            'name' => ['ms' => $data['name'], 'en' => $data['name']],
            'type' => $type->value,
            'status' => 'pending',
        ]);

        return (string) $tag->getKey();
    }

    /**
     * @return array<string, string>
     */
    private static function organizerOptions(mixed $organizerType): array
    {
        return match ($organizerType) {
            Institution::class => Institution::query()->whereIn('status', ['verified', 'pending'])->orderBy('name')->pluck('name', 'id')->all(),
            Speaker::class => Speaker::query()
                ->whereIn('status', ['verified', 'pending'])
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Speaker $speaker): array => [(string) $speaker->id => $speaker->formatted_name])
                ->all(),
            default => [],
        };
    }
}
