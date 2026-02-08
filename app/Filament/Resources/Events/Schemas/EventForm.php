<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Enums\PrayerOffset;
use App\Enums\PrayerReference;
use App\Enums\TimingMode;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
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
use Illuminate\Support\Str;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('EventTabs')
                    ->tabs([
                        Tab::make('Overview')
                            ->icon('heroicon-m-information-circle')
                            ->schema([
                                TextInput::make('title')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn(Set $set, ?string $state) => $set('slug', Str::slug($state))),
                                TextInput::make('slug')
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
                                \Filament\Forms\Components\RichEditor::make('description')
                                    ->columnSpanFull()
                                    ->maxLength(5000),
                                Select::make('series_id')
                                    ->relationship('series', 'title')
                                    ->searchable()
                                    ->preload()
                                    ->quickAdd(),
                                Select::make('speakers')
                                    ->relationship('speakers', 'name')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->quickAdd(),
                            ])
                            ->columns(2),
                        Tab::make('Logistics')
                            ->icon('heroicon-m-map')
                            ->schema([
                                Section::make('Waktu / Jadual')
                                    ->description('Tetapkan waktu majlis berdasarkan waktu solat atau waktu tertentu')
                                    ->schema([
                                        Select::make('timing_mode')
                                            ->label('Mode Waktu')
                                            ->options(collect(TimingMode::cases())->mapWithKeys(fn($case) => [$case->value => $case->label()]))
                                            ->default(TimingMode::Absolute->value)
                                            ->required()
                                            ->helperText(\Filament\Schemas\JsContent::make(<<<'JS'
                                                $get('timing_mode') === 'prayer_relative'
                                                    ? 'Waktu akan dikira berdasarkan waktu solat di lokasi majlis'
                                                    : 'Tetapkan waktu yang tepat'
                                            JS)),

                                        // Prayer-relative timing fields
                                        Callout::make('Waktu akan dikira secara automatik')
                                            ->description('Pastikan venue telah ditetapkan di bahagian Location supaya waktu solat dapat dikira berdasarkan koordinat lokasi.')
                                            ->warning()
                                            ->visible(fn(Get $get): bool => $get('timing_mode') === TimingMode::PrayerRelative)
                                            ->visibleJs(<<<'JS'
                                                $get('timing_mode') === 'prayer_relative'
                                            JS),
                                        Fieldset::make('Waktu Solat')
                                            ->schema([
                                                Select::make('prayer_reference')
                                                    ->label('Waktu Solat')
                                                    ->options(collect(PrayerReference::cases())->mapWithKeys(fn($case) => [$case->value => $case->label()]))
                                                    ->required(),
                                                Select::make('prayer_offset')
                                                    ->label('Masa')
                                                    ->options(collect(PrayerOffset::cases())->mapWithKeys(fn($case) => [$case->value => $case->label()]))
                                                    ->default(PrayerOffset::Immediately->value)
                                                    ->required(),
                                                \Filament\Forms\Components\Placeholder::make('prayer_display_text_placeholder')
                                                    ->label('Paparan Waktu')
                                                    ->content(\Filament\Schemas\JsContent::make(<<<'JS'
                                                        const prayer = $get('prayer_reference');
                                                        const offset = $get('prayer_offset');
                                                        if (!prayer || !offset) return '';
                                                        
                                                        // Mapping logic roughly matching PrayerOffset::displayText
                                                        // This is a simplified client-side preview.
                                                        const prayerLabel = {
                                                            'fajr': 'Subuh', 'syuruk': 'Syuruk', 'dhuha': 'Dhuha', 
                                                            'zuhur': 'Zuhur', 'asar': 'Asar', 'maghrib': 'Maghrib', 'isyak': 'Isyak'
                                                        }[prayer] || prayer;

                                                        const offsetLabels = {
                                                            'before_30': `30 minit sebelum ${prayerLabel}`,
                                                            'before_15': `15 minit sebelum ${prayerLabel}`,
                                                            'immediately': `Sejurus selepas ${prayerLabel}`, # Check wording in PrayerOffset
                                                            'after_15': `15 minit selepas ${prayerLabel}`,
                                                            'after_30': `30 minit selepas ${prayerLabel}`,
                                                            'after_45': `45 minit selepas ${prayerLabel}`,
                                                            'after_60': `1 jam selepas ${prayerLabel}`
                                                        };
                                                        
                                                        return offsetLabels[offset] || (`${offset} ${prayerLabel}`);
                                                    JS))
                                                    ->helperText('Teks ini akan dipaparkan kepada pengguna'),
                                            ])
                                            ->columns(3)
                                            ->visible(fn(Get $get): bool => $get('timing_mode') === TimingMode::PrayerRelative)
                                            ->visibleJs(<<<'JS'
                                                $get('timing_mode') === 'prayer_relative'
                                            JS),

                                        // Absolute timing fields
                                        DateTimePicker::make('starts_at')
                                            ->label('Waktu Mula')
                                            ->required()
                                            ->visible(fn(Get $get): bool => $get('timing_mode') === TimingMode::Absolute)
                                            ->visibleJs(<<<'JS'
                                                $get('timing_mode') === 'absolute'
                                            JS),

                                        // Event date (for prayer-relative mode)
                                        DatePicker::make('event_date')
                                            ->label('Tarikh Majlis')
                                            ->required()
                                            ->visible(fn(Get $get): bool => $get('timing_mode') === TimingMode::PrayerRelative)
                                            ->visibleJs(<<<'JS'
                                                $get('timing_mode') === 'prayer_relative'
                                            JS)
                                            ->dehydrated(false)
                                            ->helperText('Pilih tarikh majlis. Waktu sebenar akan dikira berdasarkan waktu solat pada tarikh ini.'),

                                        DateTimePicker::make('ends_at')
                                            ->label('Waktu Tamat'),

                                        TextInput::make('timezone')
                                            ->default('Asia/Kuala_Lumpur')
                                            ->required()
                                            ->maxLength(64),
                                    ])
                                    ->columns(2),
                                Section::make('Location')
                                    ->schema([
                                        Select::make('venue_id')
                                            ->relationship('venue', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->quickAdd()
                                            ->helperText('Lokasi venue akan digunakan untuk mengira waktu solat'),
                                        Select::make('institution_id')
                                            ->relationship('institution', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->quickAdd(),
                                        Select::make('space_id')
                                            ->relationship('space', 'name', fn($query, \Filament\Schemas\Components\Utilities\Get $get) => $get('institution_id')
                                                ? $query->where('institution_id', $get('institution_id'))->where('is_active', true)
                                                : $query->where('is_active', true))
                                            ->searchable()
                                            ->preload()
                                            ->label('Space / Room'),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('Settings')
                            ->icon('heroicon-m-cog-6-tooth')
                            ->schema([
                                Section::make('Classification')
                                    ->schema([
                                        Select::make('language')
                                            ->options([
                                                'bm' => 'Bahasa Melayu',
                                                'en' => 'English',
                                                'ar' => 'Arabic',
                                            ]),
                                        Select::make('event_type')
                                            ->label('Event Type')
                                            ->options(\App\Enums\EventType::class)
                                            ->multiple()
                                            ->searchable(),
                                        Select::make('audience')
                                            ->options([
                                                'general' => 'General',
                                                'youth' => 'Youth',
                                                'muslimah' => 'Muslimah',
                                                'family' => 'Family',
                                            ]),
                                    ])->columns(2),
                                Section::make('Visibility & Status')
                                    ->schema([
                                        Select::make('visibility')
                                            ->options([
                                                'public' => 'Public',
                                                'unlisted' => 'Unlisted',
                                                'private' => 'Private',
                                            ])
                                            ->required()
                                            ->default('public'),
                                        Select::make('status')
                                            ->options([
                                                'draft' => 'Draft',
                                                'pending' => 'Pending',
                                                'approved' => 'Approved',
                                                'rejected' => 'Rejected',
                                                'cancelled' => 'Cancelled',
                                                'postponed' => 'Postponed',
                                            ])
                                            // Status is nullable now, so we can remove required or keep it if admin must choose
                                            // User said status should be nullable without default.
                                            // In form, usually we force a selection for admin.
                                            // But let's leave default removed if we want.
                                            ->placeholder('Select Status'),
                                        Toggle::make('is_muslim_only')
                                            ->label('Muslim Only')
                                            ->helperText('Hanya untuk penganut agama Islam.'),
                                        Toggle::make('is_featured')
                                            ->label('Featured Event')
                                            ->onColor('success')
                                            ->offColor('gray')
                                            ->helperText('Featured events appear at the top of lists.'),
                                        DateTimePicker::make('published_at'),
                                    ])->columns(2),
                                Section::make('Registration')
                                    ->schema([
                                        Toggle::make('registration_required')
                                            ->label('Registration required'),
                                        TextInput::make('capacity')
                                            ->numeric()
                                            ->minValue(1),
                                        DateTimePicker::make('registration_opens_at'),
                                        DateTimePicker::make('registration_closes_at'),
                                    ])->columns(2),
                            ]),
                        Tab::make('Media & Donation Channels')
                            ->icon('heroicon-m-video-camera')
                            ->schema([
                                TextInput::make('live_url')
                                    ->url()
                                    ->label('Live URL')
                                    ->maxLength(255),
                                TextInput::make('recording_url')
                                    ->url()
                                    ->maxLength(255),
                            ]),
                    ])
                    ->persistTabInQueryString(),
            ]);
    }
}
