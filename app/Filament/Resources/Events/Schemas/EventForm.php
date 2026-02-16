<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Enums\RegistrationMode;
use App\Enums\ScheduleKind;
use App\Enums\ScheduleState;
use App\Enums\TagType;
use App\Forms\Components\Select;
use App\Forms\InstitutionFormSchema;
use App\Forms\SpeakerFormSchema;
use App\Forms\VenueFormSchema;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\Tag;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
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
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Maklumat Majlis')
                            ->icon('heroicon-m-document-text')
                            ->schema([
                                Section::make('Maklumat Utama')
                                    ->columnSpanFull()
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
                                    ->columnSpanFull()
                                    ->schema([
                                        DatePicker::make('event_date')
                                            ->label('Tarikh')
                                            ->native()
                                            ->required()
                                            ->live(),
                                        Select::make('prayer_time')
                                            ->label('Waktu')
                                            ->options(
                                                collect(EventPrayerTime::cases())
                                                    ->mapWithKeys(fn (EventPrayerTime $case): array => [$case->value => $case->getLabel()])
                                                    ->toArray(),
                                            )
                                            ->default(EventPrayerTime::LainWaktu->value)
                                            ->required()
                                            ->live(),
                                        TimePicker::make('custom_time')
                                            ->label('Masa Mula')
                                            ->native()
                                            ->seconds(false)
                                            ->minutesStep(5)
                                            ->required(fn (Get $get): bool => $get('prayer_time') === EventPrayerTime::LainWaktu->value)
                                            ->visible(fn (Get $get): bool => $get('prayer_time') === EventPrayerTime::LainWaktu->value),
                                        TimePicker::make('end_time')
                                            ->label('Masa Akhir')
                                            ->native()
                                            ->seconds(false)
                                            ->minutesStep(5),
                                        DateTimePicker::make('starts_at')
                                            ->dehydrated(false)
                                            ->visible(false),
                                        DateTimePicker::make('ends_at')
                                            ->dehydrated(false)
                                            ->visible(false),
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
                                        Select::make('schedule_kind')
                                            ->label('Jenis Jadual')
                                            ->options(
                                                collect(ScheduleKind::cases())
                                                    ->mapWithKeys(fn (ScheduleKind $case): array => [$case->value => $case->label()])
                                                    ->toArray(),
                                            )
                                            ->default(ScheduleKind::Single->value)
                                            ->required(),
                                        Select::make('schedule_state')
                                            ->label('Status Jadual')
                                            ->options(
                                                collect(ScheduleState::cases())
                                                    ->mapWithKeys(fn (ScheduleState $case): array => [$case->value => $case->label()])
                                                    ->toArray(),
                                            )
                                            ->default(ScheduleState::Active->value)
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
                                    ->columnSpanFull()
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
                                    ->columnSpanFull()
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
                                    ->columnSpanFull()
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
                                            ->options(fn (): array => self::getTagOptions(TagType::Domain))
                                            ->getOptionLabelUsing(fn (mixed $value): ?string => self::getTagLabel($value))
                                            ->getOptionLabelsUsing(fn (array $values): array => self::getTagLabels($values)),
                                        Select::make('discipline_tags')
                                            ->label('Bidang Ilmu')
                                            ->multiple()
                                            ->closeOnSelect()
                                            ->searchable()
                                            ->preload()
                                            ->options(fn (): array => self::getTagOptions(TagType::Discipline))
                                            ->getOptionLabelUsing(fn (mixed $value): ?string => self::getTagLabel($value))
                                            ->getOptionLabelsUsing(fn (array $values): array => self::getTagLabels($values))
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
                                            ->options(fn (): array => self::getTagOptions(TagType::Source))
                                            ->getOptionLabelUsing(fn (mixed $value): ?string => self::getTagLabel($value))
                                            ->getOptionLabelsUsing(fn (array $values): array => self::getTagLabels($values)),
                                        Select::make('issue_tags')
                                            ->label('Tema / Isu')
                                            ->multiple()
                                            ->closeOnSelect()
                                            ->searchable()
                                            ->preload()
                                            ->options(fn (): array => self::getTagOptions(TagType::Issue))
                                            ->getOptionLabelUsing(fn (mixed $value): ?string => self::getTagLabel($value))
                                            ->getOptionLabelsUsing(fn (array $values): array => self::getTagLabels($values))
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
                                    ->columnSpanFull()
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
                                    ->columnSpanFull()
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
                                    ->columnSpanFull()
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
                                    ->columnSpanFull()
                                    ->schema([
                                        Select::make('speakers')
                                            ->label('Penceramah')
                                            ->relationship('speakers', 'name', fn ($query): mixed => $query->whereIn('status', ['verified', 'pending']))
                                            ->multiple()
                                            ->closeOnSelect()
                                            ->searchable()
                                            ->preload()
                                            ->getOptionLabelUsing(fn (mixed $value): ?string => Speaker::query()->find($value)?->formatted_name)
                                            ->getOptionLabelsUsing(fn (array $values): array => Speaker::query()
                                                ->whereIn('id', $values)
                                                ->get()
                                                ->mapWithKeys(fn (Speaker $speaker): array => [(string) $speaker->id => $speaker->formatted_name])
                                                ->toArray())
                                            ->createOptionForm(SpeakerFormSchema::createOptionForm())
                                            ->createOptionUsing(fn (array $data): string => SpeakerFormSchema::createOptionUsing($data)),
                                    ]),
                                Section::make('Media')
                                    ->columnSpanFull()
                                    ->schema([
                                        SpatieMediaLibraryFileUpload::make('poster')
                                            ->label('Gambar Utama')
                                            ->collection('poster')
                                            ->image()
                                            ->imageEditor()
                                            ->imageAspectRatio('3:2')
                                            ->imageEditorAspectRatioOptions(['3:2'])
                                            ->automaticallyCropImagesToAspectRatio()
                                            ->conversion('thumb')
                                            ->responsiveImages(),
                                        SpatieMediaLibraryFileUpload::make('gallery')
                                            ->label('Galeri')
                                            ->collection('gallery')
                                            ->multiple()
                                            ->reorderable()
                                            ->maxFiles(10)
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
                                    ->columnSpanFull()
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
                                        Toggle::make('is_active')
                                            ->label('Active')
                                            ->helperText('Nyahaktifkan untuk menyembunyikan majlis daripada paparan awam tanpa menukar status.')
                                            ->default(true),
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
                                        Select::make('registration_mode')
                                            ->label('Mod Pendaftaran')
                                            ->options(
                                                collect(RegistrationMode::cases())
                                                    ->mapWithKeys(fn (RegistrationMode $mode): array => [$mode->value => $mode->label()])
                                                    ->toArray(),
                                            )
                                            ->disabled(fn (?Event $record): bool => $record?->registrations()->exists() ?? false)
                                            ->helperText(fn (?Event $record): ?string => ($record?->registrations()->exists() ?? false)
                                                ? __('Registration mode is locked after first registration.')
                                                : null)
                                            ->default(RegistrationMode::Event->value),
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

    /**
     * @param  array{name: string}  $data
     */
    protected static function createPendingTag(array $data, TagType $type): string
    {
        $tag = Tag::create([
            'name' => ['ms' => $data['name'], 'en' => $data['name']],
            'type' => $type->value,
            'status' => 'pending',
        ]);

        return (string) $tag->getKey();
    }

    protected static function getTagLabel(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return Tag::query()->find($value)?->getTranslation('name', app()->getLocale());
    }

    /**
     * @param  array<int, string>  $values
     * @return array<string, string>
     */
    protected static function getTagLabels(array $values): array
    {
        return Tag::query()
            ->whereIn('id', $values)
            ->get()
            ->mapWithKeys(fn (Tag $tag): array => [
                (string) $tag->id => $tag->getTranslation('name', app()->getLocale()),
            ])
            ->toArray();
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
                ->get()
                ->mapWithKeys(fn (Speaker $speaker): array => [(string) $speaker->id => $speaker->formatted_name])
                ->toArray(),
            default => [],
        };
    }
}
