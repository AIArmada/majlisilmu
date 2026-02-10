<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Enums\PrayerOffset;
use App\Enums\PrayerReference;
use App\Enums\TagType;
use App\Enums\TimingMode;
use App\Forms\InstitutionFormSchema;
use App\Forms\SpeakerFormSchema;
use App\Forms\VenueFormSchema;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\Tag;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nnjeim\World\Models\Language;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('EventTabs')
                    ->tabs([
                        Tab::make('Maklumat Majlis')
                            ->icon('heroicon-m-document-text')
                            ->schema([
                                Section::make('Maklumat Utama')
                                    ->schema([
                                        TextInput::make('title')
                                            ->label('Tajuk Majlis')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (Set $set, ?string $state): mixed => $set('slug', Str::slug($state))),
                                        TextInput::make('slug')
                                            ->label('Slug')
                                            ->required()
                                            ->maxLength(255)
                                            ->unique(ignoreRecord: true)
                                            ->suffixAction(
                                                Action::make('generateSlug')
                                                    ->icon(Heroicon::ArrowPath)
                                                    ->tooltip('Jana semula slug daripada tajuk')
                                                    ->actionJs(<<<'JS'
                                                        const title = $get('title') || '';
                                                        $set('slug', title.toLowerCase()
                                                            .replace(/[^\w\s-]/g, '')
                                                            .replace(/\s+/g, '-')
                                                            .replace(/-+/g, '-')
                                                            .replace(/^-|-$/g, ''))
                                                    JS),
                                            ),
                                        RichEditor::make('description')
                                            ->label('Keterangan')
                                            ->columnSpanFull()
                                            ->maxLength(5000),
                                    ])
                                    ->columns(2),
                                Section::make('Waktu & Format')
                                    ->schema([
                                        Select::make('timing_mode')
                                            ->label('Mode Waktu')
                                            ->options(
                                                collect(TimingMode::cases())
                                                    ->mapWithKeys(fn (TimingMode $case): array => [$case->value => $case->label()])
                                                    ->toArray(),
                                            )
                                            ->default(TimingMode::Absolute->value)
                                            ->required()
                                            ->live(),
                                        Callout::make('Waktu akan dikira secara automatik')
                                            ->description('Pilih jenis waktu solat dan offset untuk memaparkan waktu relatif.')
                                            ->warning()
                                            ->visible(fn (Get $get): bool => $get('timing_mode') === TimingMode::PrayerRelative->value),
                                        Fieldset::make('Waktu Solat')
                                            ->schema([
                                                Select::make('prayer_reference')
                                                    ->label('Waktu Solat')
                                                    ->options(
                                                        collect(PrayerReference::cases())
                                                            ->mapWithKeys(fn (PrayerReference $case): array => [$case->value => $case->label()])
                                                            ->toArray(),
                                                    )
                                                    ->required(fn (Get $get): bool => $get('timing_mode') === TimingMode::PrayerRelative->value),
                                                Select::make('prayer_offset')
                                                    ->label('Offset')
                                                    ->options(
                                                        collect(PrayerOffset::cases())
                                                            ->mapWithKeys(fn (PrayerOffset $case): array => [$case->value => $case->label()])
                                                            ->toArray(),
                                                    )
                                                    ->required(fn (Get $get): bool => $get('timing_mode') === TimingMode::PrayerRelative->value),
                                            ])
                                            ->columns(2)
                                            ->visible(fn (Get $get): bool => $get('timing_mode') === TimingMode::PrayerRelative->value),
                                        DateTimePicker::make('starts_at')
                                            ->label('Waktu Mula')
                                            ->seconds(false)
                                            ->minutesStep(5)
                                            ->required(),
                                        DateTimePicker::make('ends_at')
                                            ->label('Waktu Tamat')
                                            ->seconds(false)
                                            ->minutesStep(5),
                                        TextInput::make('timezone')
                                            ->label('Timezone')
                                            ->default('Asia/Kuala_Lumpur')
                                            ->required()
                                            ->maxLength(64),
                                        Select::make('event_format')
                                            ->label('Format Majlis')
                                            ->options(
                                                collect(EventFormat::cases())
                                                    ->mapWithKeys(fn (EventFormat $case): array => [$case->value => $case->label()])
                                                    ->toArray(),
                                            )
                                            ->required(),
                                        Select::make('visibility')
                                            ->label('Keterlihatan')
                                            ->options(EventVisibility::class)
                                            ->default(EventVisibility::Public->value)
                                            ->required(),
                                        TextInput::make('event_url')
                                            ->label('Pautan Majlis')
                                            ->url()
                                            ->maxLength(255),
                                        TextInput::make('live_url')
                                            ->label('Pautan Siaran Langsung')
                                            ->url()
                                            ->maxLength(255),
                                        TextInput::make('recording_url')
                                            ->label('Pautan Rakaman')
                                            ->url()
                                            ->maxLength(255),
                                    ])
                                    ->columns(2),
                                Section::make('Sasaran Peserta')
                                    ->schema([
                                        Select::make('gender')
                                            ->label('Jantina')
                                            ->options(EventGenderRestriction::class)
                                            ->required(),
                                        Select::make('age_group')
                                            ->label('Peringkat Umur')
                                            ->options(EventAgeGroup::class)
                                            ->multiple()
                                            ->closeOnSelect()
                                            ->required(),
                                        Toggle::make('children_allowed')
                                            ->label('Kanak-kanak Dibenarkan'),
                                        Toggle::make('is_muslim_only')
                                            ->label('Terbuka untuk Muslim Sahaja'),
                                    ])
                                    ->columns(2),
                                Section::make('Bahasa')
                                    ->schema([
                                        Select::make('languages')
                                            ->label('Bahasa')
                                            ->placeholder('Pilih bahasa...')
                                            ->multiple()
                                            ->closeOnSelect()
                                            ->searchable()
                                            ->preload()
                                            ->options(fn (): array => self::getLanguageOptions()),
                                    ]),
                            ]),
                        Tab::make('Kategori & Bidang')
                            ->icon('heroicon-m-tag')
                            ->schema([
                                Section::make('Kategori')
                                    ->schema([
                                        Select::make('event_type')
                                            ->label('Jenis Majlis')
                                            ->required()
                                            ->multiple()
                                            ->closeOnSelect()
                                            ->searchable()
                                            ->options(self::getEventTypeOptions()),
                                        Select::make('domain_tags')
                                            ->label('Kategori')
                                            ->helperText('Pilih kategori ceramah utama.')
                                            ->multiple()
                                            ->closeOnSelect()
                                            ->searchable()
                                            ->preload()
                                            ->options(fn (): array => self::getTagOptions(TagType::Domain)),
                                        Select::make('discipline_tags')
                                            ->label('Bidang Ilmu')
                                            ->multiple()
                                            ->closeOnSelect()
                                            ->searchable()
                                            ->preload()
                                            ->options(fn (): array => self::getTagOptions(TagType::Discipline))
                                            ->createOptionForm([
                                                TextInput::make('name')
                                                    ->label('Nama Bidang')
                                                    ->required()
                                                    ->maxLength(255),
                                            ])
                                            ->createOptionUsing(fn (array $data): string => self::createPendingTag($data, TagType::Discipline)),
                                        Select::make('source_tags')
                                            ->label('Sumber Utama')
                                            ->multiple()
                                            ->closeOnSelect()
                                            ->searchable()
                                            ->preload()
                                            ->options(fn (): array => self::getTagOptions(TagType::Source)),
                                        Select::make('issue_tags')
                                            ->label('Tema / Isu')
                                            ->multiple()
                                            ->closeOnSelect()
                                            ->searchable()
                                            ->preload()
                                            ->options(fn (): array => self::getTagOptions(TagType::Issue))
                                            ->createOptionForm([
                                                TextInput::make('name')
                                                    ->label('Nama Tema')
                                                    ->required()
                                                    ->maxLength(255),
                                            ])
                                            ->createOptionUsing(fn (array $data): string => self::createPendingTag($data, TagType::Issue)),
                                    ])
                                    ->columns(2),
                                Section::make('Rujukan')
                                    ->schema([
                                        Select::make('references')
                                            ->label('Rujukan Kitab / Buku')
                                            ->relationship('references', 'title')
                                            ->multiple()
                                            ->closeOnSelect()
                                            ->searchable()
                                            ->preload()
                                            ->quickAdd(),
                                    ]),
                            ]),
                        Tab::make('Penganjur & Lokasi')
                            ->icon('heroicon-m-building-office')
                            ->schema([
                                Section::make('Penganjur')
                                    ->schema([
                                        Select::make('organizer_type')
                                            ->label('Jenis Penganjur')
                                            ->options([
                                                Institution::class => 'Institusi',
                                                Speaker::class => 'Penceramah',
                                            ])
                                            ->live(),
                                        Select::make('organizer_id')
                                            ->label('Penganjur')
                                            ->searchable()
                                            ->preload()
                                            ->options(fn (Get $get): array => self::getOrganizerOptions($get('organizer_type')))
                                            ->required(fn (Get $get): bool => filled($get('organizer_type'))),
                                        Select::make('series')
                                            ->label('Siri')
                                            ->relationship('series', 'title')
                                            ->multiple()
                                            ->searchable()
                                            ->preload(),
                                    ])
                                    ->columns(2),
                                Section::make('Lokasi')
                                    ->schema([
                                        Select::make('institution_id')
                                            ->label('Institusi')
                                            ->relationship('institution', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm(InstitutionFormSchema::createOptionForm())
                                            ->createOptionUsing(fn (array $data): string => InstitutionFormSchema::createOptionUsing($data)),
                                        Select::make('venue_id')
                                            ->label('Lokasi')
                                            ->relationship('venue', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm(VenueFormSchema::createOptionForm())
                                            ->createOptionUsing(fn (array $data): string => VenueFormSchema::createOptionUsing($data)),
                                        Select::make('space_id')
                                            ->label('Ruang')
                                            ->relationship('space', 'name', fn ($query, Get $get): mixed => $get('institution_id')
                                                ? $query->whereHas('institutions', fn ($relatedQuery): mixed => $relatedQuery->where('institutions.id', $get('institution_id')))->where('is_active', true)
                                                : $query->where('is_active', true))
                                            ->searchable()
                                            ->preload(),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('Penceramah & Media')
                            ->icon('heroicon-m-user-group')
                            ->schema([
                                Section::make('Penceramah')
                                    ->schema([
                                        Select::make('speakers')
                                            ->label('Penceramah')
                                            ->relationship('speakers', 'name', fn ($query): mixed => $query->whereIn('status', ['verified', 'pending']))
                                            ->multiple()
                                            ->closeOnSelect()
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm(SpeakerFormSchema::createOptionForm())
                                            ->createOptionUsing(fn (array $data): string => SpeakerFormSchema::createOptionUsing($data)),
                                    ]),
                                Section::make('Media')
                                    ->schema([
                                        SpatieMediaLibraryFileUpload::make('poster')
                                            ->label('Gambar Utama')
                                            ->collection('poster')
                                            ->image()
                                            ->imageEditor()
                                            ->conversion('thumb')
                                            ->responsiveImages(),
                                        SpatieMediaLibraryFileUpload::make('gallery')
                                            ->label('Galeri')
                                            ->collection('gallery')
                                            ->multiple()
                                            ->reorderable()
                                            ->image()
                                            ->imageEditor()
                                            ->conversion('thumb')
                                            ->responsiveImages(),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('Semak & Moderasi')
                            ->icon('heroicon-m-shield-check')
                            ->schema([
                                Section::make('Moderasi')
                                    ->schema([
                                        Select::make('status')
                                            ->label('Status')
                                            ->options([
                                                'draft' => 'Draft',
                                                'pending' => 'Pending Review',
                                                'needs_changes' => 'Needs Changes',
                                                'approved' => 'Approved',
                                                'rejected' => 'Rejected',
                                            ])
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->helperText('Status boleh ditukar melalui butang moderasi di bahagian atas halaman.'),
                                        Toggle::make('is_priority')
                                            ->label('Priority Review'),
                                        Toggle::make('is_featured')
                                            ->label('Featured Event'),
                                        DateTimePicker::make('published_at')
                                            ->label('Tarikh Terbit')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->helperText('Ditetapkan secara automatik apabila majlis diluluskan.'),
                                        DateTimePicker::make('escalated_at')
                                            ->label('Tarikh Eskalasi'),
                                        Select::make('submitter_id')
                                            ->label('Penghantar')
                                            ->relationship('submitter', 'email')
                                            ->searchable()
                                            ->preload(),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->persistTabInQueryString(),
            ]);
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected static function getEventTypeOptions(): array
    {
        return collect(EventType::cases())
            ->mapToGroups(fn (EventType $type): array => [
                $type->getGroup() => [$type->value => $type->getLabel()],
            ])
            ->map(fn (Collection $group): array => $group->collapse()->toArray())
            ->toArray();
    }

    /**
     * @return array<int, string>
     */
    protected static function getLanguageOptions(): array
    {
        return Language::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * @return array<string, string>
     */
    protected static function getTagOptions(TagType $type): array
    {
        return Tag::query()
            ->ofType($type)
            ->whereIn('status', ['verified', 'pending'])
            ->ordered()
            ->get()
            ->mapWithKeys(fn (Tag $tag): array => [
                (string) $tag->id => $tag->getTranslation('name', app()->getLocale()),
            ])
            ->toArray();
    }

    protected static function createPendingTag(array $data, TagType $type): string
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
    protected static function getOrganizerOptions(?string $organizerType): array
    {
        return match ($organizerType) {
            Institution::class, 'institution' => Institution::query()
                ->whereIn('status', ['verified', 'pending'])
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray(),
            Speaker::class, 'speaker' => Speaker::query()
                ->whereIn('status', ['verified', 'pending'])
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray(),
            default => [],
        };
    }
}
