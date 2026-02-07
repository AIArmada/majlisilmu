<?php

use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Enums\Gender;
use App\Enums\Honorific;
use App\Enums\InstitutionType;
use App\Enums\PreNominal;
use App\Enums\VenueType;
use App\Forms\InstitutionFormSchema;
use App\Forms\SpeakerFormSchema;
use App\Forms\VenueFormSchema;
use App\Models\District;
use App\Models\Subdistrict;
use App\Models\Space;
use App\Models\Event;
use App\Models\EventSubmission;
use App\Models\Institution;
use App\Models\Speaker;
use App\Enums\TagType;
use App\Models\Reference;
use App\Models\State;
use App\Models\Tag;
use App\Models\Venue;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component implements HasActions, HasForms {
    use InteractsWithActions;
    use InteractsWithForms;
    use WithFileUploads;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'submitter_name' => auth()->user()?->name,
            'submitter_email' => auth()->user()?->email,
            'children_allowed' => true,
            'gender' => EventGenderRestriction::All->value,
            'age_group' => [EventAgeGroup::AllAges],
            'event_format' => EventFormat::Physical,
            'location_same_as_institution' => true,
            'location_type' => 'institution',
            'is_muslim_only' => false,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->model(new Event())
            ->schema([
                Wizard::make([
                                    Step::make(__('Maklumat Majlis'))
                                        ->icon('heroicon-o-document-text')
                                        ->description(__('Maklumat asas tentang majlis'))
                                        ->schema([
                                            Select::make('event_type')
                                                ->label(__('Jenis Majlis'))
                                                ->required()
                                                ->multiple()
                                                ->options(function (): array {
                                                    return collect(\App\Enums\EventType::cases())
                                                        ->mapToGroups(fn(\App\Enums\EventType $type) => [
                                                            $type->getGroup() => [$type->value => $type->getLabel()]
                                                        ])
                                                        ->map(fn($group) => $group->collapse())
                                                        ->toArray();
                                                })
                                                ->searchable(),

                                            Select::make('title')
                                                ->label(__('Tajuk Majlis'))
                                                ->required()
                                                ->searchable()
                                                ->getSearchResultsUsing(fn(string $search): array => Event::query()
                                                    ->whereRaw('LOWER(title) LIKE ?', ['%' . strtolower($search) . '%'])
                                                    ->where('status', 'approved')
                                                    ->limit(10)
                                                    ->pluck('title', 'title')
                                                    ->toArray())
                                                ->getOptionLabelUsing(fn($value): ?string => $value)
                                                ->allowHtml(false)
                                                ->createOptionForm([
                                                    TextInput::make('title')
                                                        ->label(__('Tajuk Majlis'))
                                                        ->required()
                                                        ->maxLength(255)
                                                        ->placeholder(__('cth: Kuliah Maghrib: Tafsir Surah Al-Kahfi')),
                                                ])
                                                ->createOptionUsing(fn(array $data): string => $data['title'])
                                                ->placeholder(__('Cari atau masukkan tajuk majlis...')),

                                            Textarea::make('description')
                                                ->label(__('Keterangan'))
                                                ->required()
                                                ->maxLength(5000)
                                                ->rows(4)
                                                ->placeholder(__('Terangkan mengenai majlis, topik yang akan dikupas, dll.')),

                                            Grid::make(['default' => 1, 'sm' => 2, 'md' => 3])
                                                ->schema([
                                                    DatePicker::make('event_date')
                                                        ->label(__('Tarikh'))
                                                        ->required()
                                                        ->native()
                                                        ->minDate(now()->startOfDay())
                                                        ->live()
                                                        ->afterStateUpdatedJs(<<<'JS'
                                                            $set('prayer_time', null)
                                                        JS),

                                                    Select::make('prayer_time')
                                                        ->label(__('Waktu'))
                                                        ->required()
                                                        ->options(function (Get $get): array {
                                                            $eventDate = $get('event_date');

                                                            return collect(EventPrayerTime::cases())
                                                                ->filter(function (EventPrayerTime $case) use ($eventDate) {
                                                                    if (!$eventDate) {
                                                                        // No date selected — show base options only (no Jumaat/Tarawikh)
                                                                        return !in_array($case, [EventPrayerTime::SelepasJumaat, EventPrayerTime::SelepasTarawikh], true);
                                                                    }

                                                                    $date = Carbon::parse($eventDate, 'Asia/Kuala_Lumpur')->startOfDay();

                                                                    if ($case === EventPrayerTime::SelepasJumaat) {
                                                                        return $date->isFriday();
                                                                    }

                                                                    if ($case === EventPrayerTime::SelepasTarawikh) {
                                                                        return $this->isRamadhan($date);
                                                                    }

                                                                    return true;
                                                                })
                                                                ->mapWithKeys(fn(EventPrayerTime $case) => [$case->value => $case->getLabel()])
                                                                ->toArray();
                                                        }),

                                                    TimePicker::make('custom_time')
                                                        ->label(__('Masa'))
                                                        ->native()
                                                        ->visibleJs(<<<'JS'
                                                            $get('prayer_time') === 'lain_waktu'
                                                            JS)
                                                        ->required(function (Get $get): bool {
                                                            $prayerTime = $get('prayer_time');
                                                            return $prayerTime === EventPrayerTime::LainWaktu;
                                                        })
                                                        ->rule(function (Get $get): Closure {
                                                            return function (string $attribute, $value, Closure $fail) use ($get) {
                                                                $eventDate = $get('event_date');
                                                                $timezone = 'Asia/Kuala_Lumpur';
                                                                $now = Carbon::now($timezone);

                                                                if (!$eventDate) {
                                                                    return;
                                                                }

                                                                $eventDay = Carbon::parse($eventDate, $timezone)->startOfDay();

                                                                // Only validate if event is today
                                                                if ($eventDay->isSameDay($now)) {
                                                                    $timeParts = explode(':', $value);
                                                                    $selectedTime = $eventDay->copy()
                                                                        ->setHour((int) $timeParts[0])
                                                                        ->setMinute((int) $timeParts[1]);

                                                                    if ($selectedTime->lessThan($now)) {
                                                                        $fail(__('Masa yang dipilih tidak boleh pada masa lalu untuk majlis hari ini.'));
                                                                    }
                                                                }
                                                            };
                                                        }),

                                                    Grid::make(2)
                                                        ->schema([
                                                            Select::make('duration_hours')
                                                                ->label(__('Jam'))
                                                                ->options(collect(range(0, 12))->mapWithKeys(fn ($h) => [$h => $h . ' ' . __('jam')]))
                                                                ->placeholder('0')
                                                                ->default(1),

                                                            Select::make('duration_minutes')
                                                                ->label(__('Minit'))
                                                                ->options(collect(range(0, 55, 5))->mapWithKeys(fn ($m) => [$m => str_pad($m, 2, '0', STR_PAD_LEFT) . ' ' . __('minit')]))
                                                                ->placeholder('00')
                                                                ->default(0),
                                                        ])
                                                        ->columnSpanFull(),
                                                ]),

                                            Grid::make(['default' => 1, 'sm' => 2])
                                                ->schema([
                                                    Radio::make('event_format')
                                                        ->label(__('Format Majlis'))
                                                        ->required()
                                                        ->options(EventFormat::class)
                                                        ->default(EventFormat::Physical)
                                                        ->inline(),

                                                    TextInput::make('event_url')
                                                        ->label(__('Pautan Majlis'))
                                                        ->url()
                                                        ->maxLength(255)
                                                        ->placeholder(__('https://example.com/event')),

                                                    TextInput::make('live_url')
                                                        ->label(__('Pautan Siaran Langsung'))
                                                        ->url()
                                                        ->maxLength(255)
                                                        ->placeholder(__('https://youtube.com/...'))
                                                        ->visibleJs(<<<'JS'
                                                    ['online', 'hybrid'].includes($get('event_format'))
                                                    JS)
                                                        ->required(fn(Get $get): bool => in_array($get('event_format'), [EventFormat::Online, EventFormat::Hybrid], true)),
                                                ]),

                                            Grid::make(['default' => 1, 'sm' => 2])
                                                ->schema([
                                                    Select::make('gender')
                                                        ->label(__('Jantina'))
                                                        ->required()
                                                        ->options(EventGenderRestriction::class)
                                                        ->default(EventGenderRestriction::All),

                                                    Select::make('age_group')
                                                        ->label(__('Peringkat Umur'))
                                                        ->required()
                                                        ->options(EventAgeGroup::class)
                                                        ->multiple()
                                                        ->afterStateUpdatedJs(<<<'JS'
                                                            const ageGroups = $state || []
                                                            if (ageGroups.includes('children') || ageGroups.includes('all_ages')) {
                                                                $set('children_allowed', true)
                                                            }
                                                            JS),

                                                    Toggle::make('children_allowed')
                                                        ->label(__('Kanak-kanak Dibenarkan'))
                                                        ->helperText(__('Adakah ibu bapa boleh membawa anak kecil ke majlis ini?'))
                                                        ->default(true)
                                                        ->inline(false)
                                                        ->disabled(function (Get $get): bool {
                                                            $ageGroups = $get('age_group') ?? [];
                                                            return in_array(EventAgeGroup::Children, $ageGroups, true) ||
                                                                in_array(EventAgeGroup::AllAges, $ageGroups, true);
                                                        })
                                                        ->dehydrated(),

                                                    Toggle::make('is_muslim_only')
                                                        ->label(__('Terbuka untuk Muslim Sahaja'))
                                                        ->helperText(__('Pilih jika majlis ini hanya terbuka untuk penganut agama Islam.'))
                                                        ->inline(false)
                                                        ->default(false),
                                                ]),
                                        ]),

                                    Step::make(__('Kategori & Bidang'))
                                        ->icon('heroicon-o-tag')
                                        ->description(__('Kategori ilmu dan rujukan'))
                                        ->schema([
                                            Grid::make(['default' => 1, 'sm' => 2])
                                                ->schema([
                                                    Select::make('domain_tags')
                                                        ->label(__('Kategori'))
                                                        ->helperText(__('Pilih kategori ceramah utama. Boleh pilih lebih daripada satu.'))
                                                        ->placeholder(__('Pilih kategori…'))
                                                        ->multiple()
                                                        ->required()
                                                        ->searchable()
                                                        ->preload()
                                                        ->native(false)
                                                        ->options(fn() => Cache::remember('submit_tags_domain_' . app()->getLocale(), 60, fn() => Tag::query()
                                                            ->where('type', TagType::Domain->value)
                                                            ->orderBy('order_column')
                                                            ->get()
                                                            ->mapWithKeys(fn(Tag $tag) => [$tag->id => $tag->getTranslation('name', app()->getLocale())])))
                                                        ->rules(['min:1', 'max:3'])
                                                        ->validationMessages([
                                                            'required' => __('Sila pilih sekurang-kurangnya 1 kategori.'),
                                                            'min' => __('Sila pilih sekurang-kurangnya 1 kategori.'),
                                                            'max' => __('Maksimum 3 kategori sahaja.'),
                                                        ]),

                                                    Select::make('discipline_tags')
                                                        ->label(__('Bidang Ilmu'))
                                                        ->helperText(__('Pilih bidang yang menggambarkan isi ceramah.'))
                                                        ->placeholder(__('Pilih bidang…'))
                                                        ->multiple()
                                                        ->required()
                                                        ->searchable()
                                                        ->preload()
                                                        ->native(false)
                                                        ->options(fn() => Cache::remember('submit_tags_discipline_' . app()->getLocale(), 60, fn() => Tag::query()
                                                            ->where('type', TagType::Discipline->value)
                                                            ->orderBy('order_column')
                                                            ->get()
                                                            ->mapWithKeys(fn(Tag $tag) => [$tag->id => $tag->getTranslation('name', app()->getLocale())])))
                                                        ->rules(['min:1'])
                                                        ->validationMessages([
                                                            'required' => __('Sila pilih sekurang-kurangnya 1 bidang ilmu.'),
                                                            'min' => __('Sila pilih sekurang-kurangnya 1 bidang ilmu.'),
                                                        ])
                                                        ->createOptionForm([
                                                            TextInput::make('name')
                                                                ->label(__('Nama Bidang'))
                                                                ->required()
                                                                ->maxLength(255)
                                                                ->placeholder(__('cth: Fiqh, Tasawuf')),
                                                        ])
                                                        ->createOptionUsing(function (array $data): string {
                                                            $tag = Tag::create([
                                                                'name' => ['ms' => $data['name'], 'en' => $data['name']],
                                                                'type' => TagType::Discipline->value,
                                                            ]);
                                                            return (string) $tag->getKey();
                                                        }),
                                                ]),

                                            Grid::make(['default' => 1, 'sm' => 2])
                                                ->schema([
                                                    Select::make('source_tags')
                                                        ->label(__('Sumber Utama'))
                                                        ->helperText(__('Pilih sumber rujukan utama (jika ada).'))
                                                        ->placeholder(__('Pilih sumber…'))
                                                        ->multiple()
                                                        ->searchable()
                                                        ->preload()
                                                        ->native(false)
                                                        ->options(fn() => Cache::remember('submit_tags_source_' . app()->getLocale(), 60, fn() => Tag::query()
                                                            ->where('type', TagType::Source->value)
                                                            ->orderBy('order_column')
                                                            ->get()
                                                            ->mapWithKeys(fn(Tag $tag) => [$tag->id => $tag->getTranslation('name', app()->getLocale())]))),

                                                    Select::make('issue_tags')
                                                        ->label(__('Tema / Isu'))
                                                        ->helperText(__('Pilih tema supaya mudah dicari.'))
                                                        ->placeholder(__('Pilih tema…'))
                                                        ->multiple()
                                                        ->searchable()
                                                        ->preload()
                                                        ->native(false)
                                                        ->options(fn() => Cache::remember('submit_tags_issue_' . app()->getLocale(), 60, fn() => Tag::query()
                                                            ->where('type', TagType::Issue->value)
                                                            ->orderBy('order_column')
                                                            ->get()
                                                            ->mapWithKeys(fn(Tag $tag) => [$tag->id => $tag->getTranslation('name', app()->getLocale())])))
                                                        ->createOptionForm([
                                                            TextInput::make('name')
                                                                ->label(__('Nama Tema'))
                                                                ->required()
                                                                ->maxLength(255)
                                                                ->placeholder(__('cth: Palestin, Riba')),
                                                        ])
                                                        ->createOptionUsing(function (array $data): string {
                                                            $tag = Tag::create([
                                                                'name' => ['ms' => $data['name'], 'en' => $data['name']],
                                                                'type' => TagType::Issue->value,
                                                            ]);
                                                            return (string) $tag->getKey();
                                                        }),
                                                ]),

                                            Select::make('references')
                                                ->label(__('Rujukan Kitab'))
                                                ->helperText(__('Pilih kitab atau buku rujukan yang digunakan (jika ada).'))
                                                ->placeholder(__('Cari atau pilih rujukan…'))
                                                ->multiple()
                                                ->searchable()
                                                ->preload()
                                                ->native(false)
                                                ->relationship('references', 'title')
                                                ->createOptionForm([
                                                    TextInput::make('title')
                                                        ->label(__('Tajuk Kitab / Buku'))
                                                        ->required()
                                                        ->maxLength(255)
                                                        ->placeholder(__('cth: Riyadhus Solihin, Ihya Ulumiddin')),
                                                    TextInput::make('author')
                                                        ->label(__('Pengarang'))
                                                        ->maxLength(255)
                                                        ->placeholder(__('cth: Imam Nawawi, Imam Ghazali')),
                                                    Select::make('type')
                                                        ->label(__('Jenis'))
                                                        ->options([
                                                            'kitab' => __('Kitab Turath'),
                                                            'book' => __('Buku Moden'),
                                                            'article' => __('Artikel'),
                                                        ])
                                                        ->default('kitab'),
                                                ])
                                                ->createOptionUsing(function (array $data): string {
                                                    $reference = Reference::create([
                                                        'title' => $data['title'],
                                                        'author' => $data['author'] ?? null,
                                                        'type' => $data['type'] ?? 'kitab',
                                                        'is_canonical' => false,
                                                    ]);

                                                    return (string) $reference->getKey();
                                                }),
                                        ]),

                                    Step::make(__('Penganjur & Lokasi'))
                                        ->icon('heroicon-o-building-office')
                                        ->description(__('Penganjur dan lokasi majlis'))
                                        ->schema([
                                            Section::make(__('Penganjur'))
                                                ->schema([
                                                    Radio::make('organizer_type')
                                                        ->label(__('Jenis Penganjur'))
                                                        ->required()
                                                        ->options([
                                                            'institution' => __('Institusi'),
                                                            'speaker' => __('Penceramah'),
                                                        ])
                                                        ->default('institution')
                                                        ->inline(),

                                                    Select::make('organizer_institution_id')
                                                        ->label(__('Institusi'))
                                                        ->options(fn() => Cache::remember('submit_institutions', 60, fn() => Institution::whereIn('status', ['verified', 'pending'])->pluck('name', 'id')))
                                                        ->searchable()
                                                        ->preload()
                                                        ->visibleJs(<<<'JS'
                                                            $get('organizer_type') === 'institution'
                                                            JS)
                                                        ->required(fn(Get $get): bool => $get('organizer_type') === 'institution')
                                                        ->createOptionForm(InstitutionFormSchema::createOptionForm())
                                                        ->createOptionUsing(fn (array $data): string => InstitutionFormSchema::createOptionUsing($data)),

                                                    Select::make('organizer_speaker_id')
                                                        ->label(__('Penceramah'))
                                                        ->options(fn() => Cache::remember('submit_speakers', 60, fn() => Speaker::query()
                                                            ->whereIn('status', ['verified', 'pending'])
                                                            ->pluck('name', 'id')))
                                                        ->searchable()
                                                        ->preload()
                                                        ->visibleJs(<<<'JS'
                                                            $get('organizer_type') === 'speaker'
                                                            JS)
                                                        ->required(fn(Get $get): bool => $get('organizer_type') === 'speaker')
                                                        ->afterStateUpdatedJs(<<<'JS'
                                                            if ($state) {
                                                                const currentSpeakers = $get('speakers') || []
                                                                if (!currentSpeakers.includes($state)) {
                                                                    $set('speakers', [...currentSpeakers, $state])
                                                                }
                                                            }
                                                            JS)
                                                        ->createOptionForm(SpeakerFormSchema::createOptionForm())
                                                        ->createOptionUsing(function (array $data, Set $set, Get $get): string {
                                                            return SpeakerFormSchema::createOptionUsing($data);
                                                        }),
                                                ]),

                                            Section::make(__('Lokasi'))
                                                ->visibleJs(<<<'JS'
                                                    $get('event_format') !== 'online' && $get('organizer_type')
                                                    JS)
                                                ->schema([
                                                    Toggle::make('location_same_as_institution')
                                                        ->label(__('Sama seperti institusi penganjur'))
                                                        ->default(true)
                                                        ->inline(false)
                                                        ->visibleJs(<<<'JS'
                                                            $get('organizer_type') === 'institution'
                                                            JS),

                                                    Radio::make('location_type')
                                                        ->label(__('Jenis Lokasi'))
                                                        ->options([
                                                            'institution' => __('Institusi'),
                                                            'venue' => __('Tempat'),
                                                        ])
                                                        ->inline()
                                                        ->default('institution')
                                                        ->visibleJs(<<<'JS'
                                                            $get('organizer_type') === 'speaker' || !$get('location_same_as_institution')
                                                            JS)
                                                        ->required(fn(Get $get): bool => ($get('organizer_type') === 'speaker' || !$get('location_same_as_institution')) && $get('event_format') !== 'online'),

                                                    Select::make('location_institution_id')
                                                        ->label(__('Institusi'))
                                                        ->options(fn() => Cache::remember('submit_institutions', 60, fn() => Institution::whereIn('status', ['verified', 'pending'])->pluck('name', 'id')))
                                                        ->searchable()
                                                        ->preload()
                                                        ->visibleJs(<<<'JS'
                                                            ($get('organizer_type') === 'speaker' || !$get('location_same_as_institution')) && $get('location_type') === 'institution'
                                                            JS)
                                                        ->required(fn(Get $get): bool => ($get('organizer_type') === 'speaker' || !$get('location_same_as_institution')) && $get('location_type') === 'institution')
                                                        ->createOptionForm(InstitutionFormSchema::createOptionForm())
                                                        ->createOptionUsing(fn (array $data): string => InstitutionFormSchema::createOptionUsing($data)),

                                                    Select::make('location_venue_id')
                                                        ->label(__('Lokasi'))
                                                        ->options(fn() => Cache::remember('submit_venues', 60, fn() => Venue::whereIn('status', ['verified', 'pending'])->pluck('name', 'id')))
                                                        ->searchable()
                                                        ->preload()
                                                        ->visibleJs(<<<'JS'
                                                            ($get('organizer_type') === 'speaker' || !$get('location_same_as_institution')) && $get('location_type') === 'venue'
                                                            JS)
                                                        ->required(fn(Get $get): bool => ($get('organizer_type') === 'speaker' || !$get('location_same_as_institution')) && $get('location_type') === 'venue')
                                                        ->createOptionForm(VenueFormSchema::createOptionForm())
                                                        ->createOptionUsing(fn (array $data): string => VenueFormSchema::createOptionUsing($data)),

                                                    Select::make('space_id')
                                                        ->label(__('Ruang'))
                                                        ->helperText(__('Pilihan: Pilih ruang tertentu di dalam institusi (cth: Dewan Utama, Ruang Solat).'))
                                                        ->placeholder(__('Pilih ruang…'))
                                                        ->searchable()
                                                        ->preload()
                                                        ->live()
                                                        ->options(function (Get $get): array {
                                                            $institutionId = null;

                                                            if ($get('organizer_type') === 'institution' && ($get('location_same_as_institution') ?? true)) {
                                                                $institutionId = $get('organizer_institution_id');
                                                            } elseif ($get('location_type') === 'institution') {
                                                                $institutionId = $get('location_institution_id');
                                                            }

                                                            if (! $institutionId) {
                                                                return [];
                                                            }

                                                            return Space::query()
                                                                ->where('institution_id', $institutionId)
                                                                ->where('is_active', true)
                                                                ->pluck('name', 'id')
                                                                ->toArray();
                                                        }),
                                                ]),
                                        ]),

                                    Step::make(__('Penceramah & Media'))
                                        ->icon('heroicon-o-user-group')
                                        ->description(__('Penceramah dan media majlis'))
                                        ->schema([
                                            Section::make(__('Penceramah'))
                                                ->schema([
                                                    Select::make('speakers')
                                                        ->label(__('Pilih Penceramah'))
                                                        ->required()
                                                        ->multiple()
                                                        ->relationship('speakers', 'name', fn(Builder $query) => $query->whereIn('status', ['verified', 'pending']))
                                                        ->searchable()
                                                        ->preload()
                                                        ->createOptionForm(SpeakerFormSchema::createOptionForm())
                                                        ->createOptionUsing(fn (array $data): string => SpeakerFormSchema::createOptionUsing($data)),
                                                ]),

                                            Section::make(__('Media'))
                                                ->schema([
                                                    SpatieMediaLibraryFileUpload::make('poster')
                                                        ->label(__('Gambar Utama'))
                                                        ->collection('poster')
                                                        ->image()
                                                        ->imageEditor()
                                                        ->responsiveImages()
                                                        ->helperText(__('Gambar utama untuk paparan majlis.')),
                                                    SpatieMediaLibraryFileUpload::make('gallery')
                                                        ->label(__('Galeri'))
                                                        ->collection('gallery')
                                                        ->multiple()
                                                        ->reorderable()
                                                        ->image()
                                                        ->imageEditor()
                                                        ->responsiveImages()
                                                        ->helperText(__('Gambar tambahan untuk galeri majlis.')),
                                                ])
                                                ->columns(['default' => 1, 'sm' => 2]),
                                        ]),

                                    Step::make(__('Semak & Hantar'))
                                        ->icon('heroicon-o-paper-airplane')
                                        ->description(__('Semak maklumat dan hantar'))
                                        ->schema([
                                            Section::make(__('Maklumat Anda'))
                                                ->schema([
                                                    Grid::make(['default' => 1, 'sm' => 2])
                                                        ->schema([
                                                            TextInput::make('submitter_name')
                                                                ->label(__('Nama Anda'))
                                                                ->required()
                                                                ->maxLength(100),

                                                            TextInput::make('submitter_email')
                                                                ->label(__('Email'))
                                                                ->email()
                                                                ->maxLength(255)
                                                                ->required(fn (Get $get) => ! auth()->check() && empty($get('submitter_phone'))),

                                                            TextInput::make('submitter_phone')
                                                                ->label(__('Telefon'))
                                                                ->tel()
                                                                ->maxLength(20)
                                                                ->required(fn (Get $get) => ! auth()->check() && empty($get('submitter_email'))),
                                                        ]),
                                                ])
                                                ->visible(fn () => ! auth()->check()),

                                            Section::make(__('Nota untuk Pentadbir'))
                                                ->description(__('Pilihan: Tambah maklumat atau permintaan khas untuk moderator.'))
                                                ->schema([
                                                    Textarea::make('notes')
                                                        ->label(__('Nota'))
                                                        ->rows(3)
                                                        ->maxLength(1000)
                                                        ->placeholder(__('cth: Keperluan khas, maklumat tambahan, atau apa sahaja yang perlu diketahui moderator...'))
                                                        ->helperText(__('Maksimum 1000 aksara')),
                                                ]),

                                            Callout::make(__('Semakan Moderator'))
                                                ->description(__('Majlis anda akan disemak oleh moderator kami dalam tempoh 24-48 jam. Anda akan dimaklumkan melalui e-mel setelah majlis diluluskan.'))
                                                ->info(),
                                        ]),
                                ])
                                    ->skippable()
                                    ->persistStepInQueryString()
                                    ->submitAction(new HtmlString(Blade::render(<<<'BLADE'
                                        <x-filament::button
                                            type="submit"
                                            size="lg"
                                            color="success"
                                            class="w-full"
                                        >
                                            {{ __('Hantar Majlis untuk Semakan') }}
                                        </x-filament::button>
                                    BLADE))),
            ])
            ->statePath('data');
    }

    public function submit(): mixed
    {
        $validated = $this->form->getState();

        // Enforce children_allowed when AllAges or Children is selected
        $ageGroups = $validated['age_group'] ?? [];
        if (
            in_array(EventAgeGroup::Children->value, $ageGroups, true) ||
            in_array(EventAgeGroup::AllAges->value, $ageGroups, true)
        ) {
            $validated['children_allowed'] = true;
        }

        $startsAt = $this->resolveStartsAt($validated);

        // Validate contextual prayer time constraints (client-side filtering removed)
        $prayerTimeRaw = $validated['prayer_time'] ?? '';
        $selectedPrayer = $prayerTimeRaw instanceof EventPrayerTime
            ? $prayerTimeRaw
            : EventPrayerTime::tryFrom($prayerTimeRaw);
        $eventDate = Carbon::parse($validated['event_date'], 'Asia/Kuala_Lumpur')->startOfDay();

        if ($selectedPrayer === EventPrayerTime::SelepasJumaat && !$eventDate->isFriday()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'data.prayer_time' => __('Selepas Jumaat hanya boleh dipilih untuk hari Jumaat.'),
            ]);
        }

        if ($selectedPrayer === EventPrayerTime::SelepasTarawikh && !$this->isRamadhan($eventDate)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'data.prayer_time' => __('Selepas Tarawikh hanya boleh dipilih semasa bulan Ramadhan.'),
            ]);
        }

        $organizerType = null;
        $organizerId = null;
        $targetInstitutionId = null;
        $targetVenueId = null;
        $locationInstitutionId = null;

        $locationType = $validated['location_type'] ?? 'institution';
        $locationInstitutionId = $this->data['location_institution_id'] ?? null;
        $venueId = $this->data['location_venue_id'] ?? null;

        // If it was institution, we check if it was pre-selected or created
        if ($locationType === 'institution' && $locationInstitutionId) {
            $venueId = null;
        } elseif ($locationType === 'venue' && $venueId) {
            $locationInstitutionId = null;
        }

        if (($validated['organizer_type'] ?? null) === 'institution' && !empty($validated['organizer_institution_id'])) {
            $organizerType = Institution::class;
            $organizerId = $validated['organizer_institution_id'];

            \Illuminate\Support\Facades\Log::info('Location logic check', [
                'location_same_as_institution' => $validated['location_same_as_institution'] ?? 'not set',
                'organizer_type' => $validated['organizer_type'] ?? 'not set',
            ]);
            if (($validated['location_same_as_institution'] ?? true) == true) {
                $targetInstitutionId = $this->data['organizer_institution_id'];
                $targetVenueId = null;
            } else {
                if (($validated['location_type'] ?? null) === 'institution') {
                    $targetInstitutionId = $this->data['location_institution_id'] ?? null;
                    $targetVenueId = null;
                } else {
                    $targetVenueId = $this->data['location_venue_id'] ?? null;
                    $targetInstitutionId = null;
                }
            }
        } elseif (($validated['organizer_type'] ?? null) === 'speaker' && !empty($validated['organizer_speaker_id'])) {
            $organizerType = Speaker::class;
            $organizerId = $validated['organizer_speaker_id'];

            if ($locationInstitutionId) {
                $targetInstitutionId = $locationInstitutionId;
                $targetVenueId = null;
            } elseif ($venueId) {
                $targetInstitutionId = null;
                $targetVenueId = $venueId;
            }
        }

        $prayerTimeValue = $validated['prayer_time'] ?? '';
        $prayerTime = $prayerTimeValue instanceof EventPrayerTime
            ? $prayerTimeValue
            : EventPrayerTime::tryFrom($prayerTimeValue);
        $prayerReference = $prayerTime?->toPrayerReference();
        $prayerOffset = $prayerTime?->getDefaultOffset();
        $prayerDisplayText = $prayerTime && !$prayerTime->isCustomTime() ? $prayerTime->getLabel() : null;

        // Validate that the resolved start time is not in the past
        $now = Carbon::now('Asia/Kuala_Lumpur');
        if ($startsAt->lessThanOrEqualTo($now)) {
            $errorField = $prayerTime?->isCustomTime() ? 'data.custom_time' : 'data.prayer_time';
            throw \Illuminate\Validation\ValidationException::withMessages([
                $errorField => __('Waktu majlis yang dipilih telah berlalu. Sila pilih waktu lain.'),
            ]);
        }

        $event = Event::create([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']) . '-' . Str::random(6),
            'description' => $validated['description'] ?? null,
            'starts_at' => $startsAt,
            'ends_at' => (($durationMinutes = ((int) ($validated['duration_hours'] ?? 0)) * 60 + ((int) ($validated['duration_minutes'] ?? 0))) > 0) ? $startsAt->copy()->addMinutes($durationMinutes) : null,
            'institution_id' => $targetInstitutionId,
            'venue_id' => $targetVenueId,
            'space_id' => $validated['space_id'] ?? null,
            'event_type' => $validated['event_type'] ?? [\App\Enums\EventType::KuliahCeramah],
            'gender' => $validated['gender'] ?? EventGenderRestriction::All->value,
            'age_group' => $validated['age_group'] ?? [EventAgeGroup::AllAges],
            'children_allowed' => $validated['children_allowed'] ?? true,
            'is_muslim_only' => $validated['is_muslim_only'] ?? false,
            'timing_mode' => $prayerTime?->isCustomTime() ? 'absolute' : 'prayer_relative',
            'prayer_reference' => $prayerReference?->value,
            'prayer_offset' => $prayerOffset?->value,
            'prayer_display_text' => $prayerDisplayText,
            'organizer_type' => $organizerType,
            'organizer_id' => $organizerId,
            'event_format' => $validated['event_format'] ?? EventFormat::Physical->value,
            'event_url' => $validated['event_url'] ?? null,
            'live_url' => $validated['live_url'] ?? null,
            'status' => 'pending',
            'visibility' => \App\Enums\EventVisibility::Public,
            'submitter_id' => auth()->id(),
        ]);

        if (!empty($validated['speakers'])) {
            $event->speakers()->attach($validated['speakers']);
        }

        // Attach tags (merge all selected tags from 4 type fields)
        $allTagIds = array_merge(
            $validated['domain_tags'] ?? [],
            $validated['discipline_tags'] ?? [],
            $validated['source_tags'] ?? [],
            $validated['issue_tags'] ?? []
        );

        if (!empty($allTagIds)) {
            $tags = Tag::whereIn('id', $allTagIds)->get();
            $event->syncTags($tags);
        }

        $this->form->model($event);
        $this->form->saveRelationships();


        $submitterName = $validated['submitter_name'] ?? auth()->user()?->name;

        $submission = EventSubmission::create([
            'event_id' => $event->id,
            'submitter_name' => $submitterName,
            'submitted_by' => auth()->id(),
            'notes' => $validated['notes'] ?? null,
        ]);

        if (!auth()->check()) {
            $this->storeSubmitterContacts($submission, $validated);
        }

        session()->flash('event_title', $event->title);

        return redirect()->route('submit-event.success');
    }

    /**
     * Resolve the starts_at datetime from event_date and prayer_time/custom_time.
     *
     * @param array{event_date: string, prayer_time: string|EventPrayerTime, custom_time?: string|null} $validated
     */
    protected function resolveStartsAt(array $validated): Carbon
    {
        $eventDate = Carbon::parse($validated['event_date'], 'Asia/Kuala_Lumpur')->startOfDay();
        $prayerTimeValue = $validated['prayer_time'] ?? '';
        $prayerTime = $prayerTimeValue instanceof EventPrayerTime
            ? $prayerTimeValue
            : EventPrayerTime::tryFrom($prayerTimeValue);

        if ($prayerTime?->isCustomTime() && !empty($validated['custom_time'])) {
            $time = Carbon::parse($validated['custom_time']);

            return $eventDate->setTime($time->hour, $time->minute);
        }

        $defaultTimes = $this->getDefaultPrayerTimes();

        $timeString = $defaultTimes[$prayerTime?->value ?? ''] ?? '20:00';
        $time = Carbon::parse($timeString);

        return $eventDate->setTime($time->hour, $time->minute);
    }

    /**
     * @return array<string, string>
     */
    protected function getDefaultPrayerTimes(): array
    {
        return [
            EventPrayerTime::SelepasSubuh->value => '06:30',
            EventPrayerTime::SelepasZuhur->value => '13:30',
            EventPrayerTime::SelepasJumaat->value => '14:00',
            EventPrayerTime::SelepasAsar->value => '17:00',
            EventPrayerTime::SelepasMaghrib->value => '20:00',
            EventPrayerTime::SelepasIsyak->value => '21:30',
            EventPrayerTime::SelepasTarawikh->value => '22:30',
        ];
    }

    /**
     * Check if a given date falls within Ramadhan.
     * This uses approximate Gregorian dates for Ramadhan periods.
     */
    protected function isRamadhan(Carbon $date): bool
    {
        $year = $date->year;

        // Ramadhan dates for common years (approximate)
        $ramadhanPeriods = [
            2026 => ['start' => '02-18', 'end' => '03-19'],
            2027 => ['start' => '02-07', 'end' => '03-08'],
            2028 => ['start' => '01-27', 'end' => '02-25'],
            2029 => ['start' => '01-16', 'end' => '02-13'],
            2030 => ['start' => '01-05', 'end' => '02-03'],
        ];

        if (!isset($ramadhanPeriods[$year])) {
            return false;
        }

        $period = $ramadhanPeriods[$year];
        $startDate = Carbon::parse("{$year}-{$period['start']}", 'Asia/Kuala_Lumpur')->startOfDay();
        $endDate = Carbon::parse("{$year}-{$period['end']}", 'Asia/Kuala_Lumpur')->endOfDay();

        return $date->between($startDate, $endDate);
    }

    /**
     * @param array{submitter_email?: string|null, submitter_phone?: string|null} $validated
     */
    protected function storeSubmitterContacts(EventSubmission $submission, array $validated): void
    {
        $email = $validated['submitter_email'] ?? null;
        $phone = $validated['submitter_phone'] ?? null;

        if (filled($email)) {
            $submission->contacts()->create([
                'type' => 'main',
                'category' => 'email',
                'value' => $email,
                'is_public' => false,
            ]);
        }

        if (filled($phone)) {
            $submission->contacts()->create([
                'type' => 'main',
                'category' => 'phone',
                'value' => $phone,
                'is_public' => false,
            ]);
        }
    }
};
?>

@section('title', __('Hantar Majlis') . ' - ' . config('app.name'))

<style>
    /* Ensure wizard stepper header doesn't clip */
    .fi-sc-wizard-header {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }

    .fi-sc-wizard-header::-webkit-scrollbar {
        height: 3px;
    }

    .fi-sc-wizard-header::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 9999px;
    }

    /* Hide step descriptions — 5 steps with descriptions exceed
       the fixed max-w-6xl (1152px) container width */
    .fi-sc-wizard-header-step-description {
        display: none;
    }
</style>

<div class="bg-slate-50 min-h-screen py-12 pb-32">
    <div class="container mx-auto px-6 lg:px-12">
        <div class="max-w-6xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-12">
                <h1 class="font-heading text-4xl font-bold text-slate-900">{{ __('Hantar Majlis Ilmu') }}</h1>
                <p class="text-slate-500 mt-4 text-lg">
                    {{ __('Kongsi majlis ilmu dengan komuniti. Penghantaran anda akan disemak sebelum diterbitkan.') }}
                </p>
            </div>

            <form wire:submit="submit">
                {{ $this->form }}
            </form>

            <x-filament-actions::modals />
        </div>
    </div>
</div>