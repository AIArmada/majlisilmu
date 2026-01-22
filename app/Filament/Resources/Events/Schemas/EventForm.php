<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Enums\PrayerOffset;
use App\Enums\PrayerReference;
use App\Enums\TimingMode;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
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
                                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),
                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                                \Filament\Forms\Components\RichEditor::make('description')
                                    ->columnSpanFull()
                                    ->maxLength(5000),
                                Select::make('series_id')
                                    ->relationship('series', 'title')
                                    ->searchable()
                                    ->preload(),
                                Select::make('speakers')
                                    ->relationship('speakers', 'name')
                                    ->multiple()
                                    ->searchable()
                                    ->preload(),
                                Select::make('topics')
                                    ->relationship('topics', 'name')
                                    ->multiple()
                                    ->searchable()
                                    ->preload(),
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
                                            ->options(collect(TimingMode::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                                            ->default(TimingMode::Absolute->value)
                                            ->required()
                                            ->live()
                                            ->helperText(fn (Get $get): string => match ($get('timing_mode')) {
                                                TimingMode::PrayerRelative->value => 'Waktu akan dikira berdasarkan waktu solat di lokasi majlis',
                                                default => 'Tetapkan waktu yang tepat',
                                            }),

                                        // Prayer-relative timing fields
                                        Fieldset::make('Waktu Solat')
                                            ->schema([
                                                Select::make('prayer_reference')
                                                    ->label('Waktu Solat')
                                                    ->options(collect(PrayerReference::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::updatePrayerDisplayText($get, $set)),
                                                Select::make('prayer_offset')
                                                    ->label('Masa')
                                                    ->options(collect(PrayerOffset::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                                                    ->default(PrayerOffset::Immediately->value)
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::updatePrayerDisplayText($get, $set)),
                                                TextInput::make('prayer_display_text')
                                                    ->label('Paparan Waktu')
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->helperText('Teks ini akan dipaparkan kepada pengguna'),
                                            ])
                                            ->columns(3)
                                            ->visible(fn (Get $get): bool => $get('timing_mode') === TimingMode::PrayerRelative->value),

                                        // Absolute timing fields
                                        DateTimePicker::make('starts_at')
                                            ->label('Waktu Mula')
                                            ->required()
                                            ->visible(fn (Get $get): bool => $get('timing_mode') === TimingMode::Absolute->value),

                                        // Event date (for prayer-relative mode)
                                        DatePicker::make('event_date')
                                            ->label('Tarikh Majlis')
                                            ->required()
                                            ->visible(fn (Get $get): bool => $get('timing_mode') === TimingMode::PrayerRelative->value)
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
                                            ->helperText('Lokasi venue akan digunakan untuk mengira waktu solat'),
                                        Select::make('institution_id')
                                            ->relationship('institution', 'name')
                                            ->searchable()
                                            ->preload(),
                                        Select::make('speaker_id')
                                            ->relationship('speaker', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->label('Organizer (Speaker)'),
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
                                        Select::make('genre')
                                            ->options([
                                                'kuliah' => 'Kuliah',
                                                'ceramah' => 'Ceramah',
                                                'tazkirah' => 'Tazkirah',
                                                'forum' => 'Forum',
                                            ]),
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
                        Tab::make('Media & Donation')
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

    /**
     * Update the prayer display text based on selected prayer and offset.
     */
    protected static function updatePrayerDisplayText(Get $get, Set $set): void
    {
        $prayerValue = $get('prayer_reference');
        $offsetValue = $get('prayer_offset');

        if ($prayerValue && $offsetValue) {
            $prayer = PrayerReference::tryFrom($prayerValue);
            $offset = PrayerOffset::tryFrom($offsetValue);

            if ($prayer && $offset) {
                $set('prayer_display_text', $offset->displayText($prayer));
            }
        }
    }
}
